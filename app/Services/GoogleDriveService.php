<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Exceptions\DriveIntegrationException;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\SubmissionVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveService
{
    /**
     * Validates uploaded file format, MIME type, executable blocklist, and max file size server-side.
     */
    public function validateUploadedFile(UploadedFile $file, DocumentType $documentType): array
    {
        $originalName = trim($file->getClientOriginalName());
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = mb_strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $sizeBytes = (int) $file->getSize();

        if ($extension === '') {
            throw new DomainRuleException('Uploaded file must have a valid file extension.');
        }

        $blockedExtensions = config('excellia.security.blocked_extensions', []);
        if (in_array($extension, $blockedExtensions, true)) {
            throw new DomainRuleException("Executable or script files (.{$extension}) are strictly prohibited.");
        }

        // Prevent double-extension tricks like report.php.pdf if inner extension is executable? Allow normal dots, block dangerous trailing patterns
        $blockedMimePrefixes = config('excellia.security.blocked_mime_prefixes', []);
        foreach ($blockedMimePrefixes as $blockedMime) {
            if ($mimeType === $blockedMime || str_starts_with($mimeType, $blockedMime)) {
                throw new DomainRuleException("File MIME type ({$mimeType}) is prohibited by security policy.");
            }
        }

        $allowedExtensions = $documentType->normalizedExtensions();
        if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
            $allowedList = implode(', ', array_map(fn ($e) => ".{$e}", $allowedExtensions));
            throw new DomainRuleException(
                "Unsupported file format (.{$extension}) for {$documentType->name}. Allowed formats: {$allowedList}."
            );
        }

        $allowedMimeMap = config('excellia.security.allowed_mime_map', []);
        if (isset($allowedMimeMap[$extension]) && ! in_array($mimeType, $allowedMimeMap[$extension], true)) {
            throw new DomainRuleException(
                "File content MIME type ({$mimeType}) does not match expected format (.{$extension})."
            );
        }

        $maxBytes = ((int) $documentType->max_file_size_mb) * 1024 * 1024;
        if ($sizeBytes <= 0) {
            throw new DomainRuleException('Uploaded file is empty.');
        }
        if ($sizeBytes > $maxBytes) {
            throw new DomainRuleException(
                "File size exceeds maximum limit of {$documentType->max_file_size_mb} MB for {$documentType->name}."
            );
        }

        return [
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Validates a teacher-linked existing Google Drive file reference, rejecting public 'anyone' share URLs.
     */
    public function validateLinkedDriveFile(
        string $driveFileInput,
        string $fileName,
        int $fileSizeBytes,
        string $mimeType,
        DocumentType $documentType
    ): array {
        if ((bool) config('excellia.drive.simulate_outage', false)) {
            throw new DriveIntegrationException('Google Drive API could not verify linked file permissions because Drive is unavailable.');
        }

        $trimmed = trim($driveFileInput);
        if (str_contains(mb_strtolower($trimmed), 'anyone') || str_contains(mb_strtolower($trimmed), 'usp=sharing_public')) {
            throw new DomainRuleException('Public "Anyone with the link" Google Drive URLs are forbidden by Excellia School security policy.');
        }

        $driveFileId = $this->extractDriveId($trimmed);
        if ($driveFileId === null) {
            throw new DomainRuleException('Invalid Google Drive file ID or URL supplied.');
        }

        $extension = mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $blockedExtensions = config('excellia.security.blocked_extensions', []);
        if ($extension === '' || in_array($extension, $blockedExtensions, true)) {
            throw new DomainRuleException("Linked Drive file has a prohibited or missing extension (.{$extension}).");
        }

        $allowedExtensions = $documentType->normalizedExtensions();
        if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
            $allowedList = implode(', ', array_map(fn ($e) => ".{$e}", $allowedExtensions));
            throw new DomainRuleException("Linked Drive file (.{$extension}) is not permitted for {$documentType->name}. Allowed: {$allowedList}.");
        }

        $maxBytes = ((int) $documentType->max_file_size_mb) * 1024 * 1024;
        if ($fileSizeBytes <= 0 || $fileSizeBytes > $maxBytes) {
            throw new DomainRuleException("Linked Drive file size must be greater than 0 and within {$documentType->max_file_size_mb} MB.");
        }

        if (config('excellia.drive.mode') === 'google_api') {
            $this->verifyGoogleApiFileAccess($driveFileId);
        }

        return [
            'drive_file_id' => $driveFileId,
            'drive_file_name' => $fileName,
            'drive_file_url' => "https://drive.google.com/file/d/{$driveFileId}/view",
            'extension' => $extension,
            'mime_type' => mb_strtolower($mimeType),
            'size_bytes' => $fileSizeBytes,
        ];
    }

    /**
     * Resolves active verified Drive folder mapping for a task's academic context, or builds the expected school folder path.
     */
    public function resolveFolderMappingForTask(DocumentTask $task): ?DriveFolderMapping
    {
        $allocation = $task->relationLoaded('teachingAllocation')
            ? $task->teachingAllocation
            : $task->teachingAllocation()->with(['schoolClass', 'section', 'subject', 'teacher', 'academicYear'])->first();

        if (! $allocation) {
            return null;
        }

        // Exact teacher + document type mapping first
        $mapping = DriveFolderMapping::query()
            ->where('academic_year_id', $task->academic_year_id)
            ->where('class_id', $allocation->class_id)
            ->where('section_id', $allocation->section_id)
            ->where('subject_id', $allocation->subject_id)
            ->where('document_type_id', $task->document_type_id)
            ->where('is_active', true)
            ->where(function ($q) use ($task) {
                $q->where('teacher_user_id', $task->assigned_teacher_id)
                    ->orWhereNull('teacher_user_id');
            })
            ->orderByRaw('CASE WHEN teacher_user_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        if ($mapping) {
            return $mapping;
        }

        // Fallback to subject-root mapping if configured
        return DriveFolderMapping::query()
            ->where('academic_year_id', $task->academic_year_id)
            ->where('class_id', $allocation->class_id)
            ->where('section_id', $allocation->section_id)
            ->where('subject_id', $allocation->subject_id)
            ->whereNull('document_type_id')
            ->where('is_active', true)
            ->first();
    }

    public function buildCanonicalFolderHierarchy(DocumentTask $task): string
    {
        $task->loadMissing([
            'academicYear',
            'documentType',
            'assignedTeacher',
            'teachingAllocation.schoolClass',
            'teachingAllocation.section',
            'teachingAllocation.subject',
        ]);

        $root = config('excellia.drive.root_folder_name', 'EXCELLIA SCHOOL – ACADEMIC SHARING – 2026–27');
        $classLabel = $task->teachingAllocation?->schoolClass?->drive_folder_label
            ?: ($task->teachingAllocation?->schoolClass?->name ?? 'Class');
        $sectionLabel = 'Section ' . ($task->teachingAllocation?->section?->name ?? '');
        $subjectName = $task->teachingAllocation?->subject?->name ?? 'Class Administration';
        $teacherName = $task->assignedTeacher?->full_name ?? 'Teacher';
        $docFolder = $task->documentType?->folder_name ?? 'Documents';

        return "{$root} / {$classLabel} / {$sectionLabel} / {$subjectName} – {$teacherName} / {$docFolder}";
    }

    /**
     * Uploads file to mapped Google Drive folder (or private Workspace Sandbox vault), never creating public links.
     */
    public function storeUploadedSubmissionFile(
        UploadedFile $file,
        DocumentTask $task,
        int $versionNumber,
        User $uploader
    ): array {
        $docType = $task->relationLoaded('documentType') ? $task->documentType : $task->documentType()->firstOrFail();
        $validated = $this->validateUploadedFile($file, $docType);

        if ((bool) config('excellia.drive.simulate_outage', false)) {
            throw new DriveIntegrationException();
        }

        $mapping = $this->resolveFolderMappingForTask($task);
        $targetFolderId = $mapping?->drive_folder_id
            ?? ('1ExcelliaFolder_' . $task->academic_year_id . '_' . $task->teaching_allocation_id . '_' . $docType->folder_code);

        $safeBase = Str::slug(pathinfo($validated['original_name'], PATHINFO_FILENAME)) ?: 'academic-document';
        $canonicalFileName = sprintf(
            '%s_%s_v%d.%s',
            $docType->folder_code,
            $safeBase,
            $versionNumber,
            $validated['extension']
        );

        $checksum = hash_file('sha256', $file->getRealPath());

        if (config('excellia.drive.mode') === 'google_api') {
            $driveResult = $this->uploadViaGoogleApi($file, $canonicalFileName, $validated['mime_type'], $targetFolderId);

            return [
                'drive_file_id' => $driveResult['id'],
                'drive_folder_id' => $targetFolderId,
                'drive_file_name' => $canonicalFileName,
                'drive_file_url' => "https://drive.google.com/file/d/{$driveResult['id']}/view",
                'storage_disk_path' => null,
                'mime_type' => $validated['mime_type'],
                'file_extension' => $validated['extension'],
                'file_size_bytes' => $validated['size_bytes'],
                'sha256_checksum' => $checksum,
            ];
        }

        // Workspace Sandbox mode: private vault storage (not publicly web-accessible) + canonical Drive metadata
        try {
            $disk = config('excellia.drive.local_private_disk', 'local');
            $vaultRelativeDir = sprintf(
                'drive_vault/year_%d/task_%d/v%d',
                $task->academic_year_id,
                $task->id,
                $versionNumber
            );
            $storedPath = $file->storeAs($vaultRelativeDir, $canonicalFileName, ['disk' => $disk]);
            if (! $storedPath) {
                throw new DriveIntegrationException('Failed to write academic document to secure Drive vault.');
            }
        } catch (DriveIntegrationException $e) {
            throw $e;
        } catch (Throwable) {
            throw new DriveIntegrationException('Storage error while uploading document. Submission was aborted.');
        }

        $driveFileId = '1ExcDrive_' . Str::random(24);

        return [
            'drive_file_id' => $driveFileId,
            'drive_folder_id' => $targetFolderId,
            'drive_file_name' => $canonicalFileName,
            'drive_file_url' => "https://drive.google.com/file/d/{$driveFileId}/view",
            'storage_disk_path' => $storedPath,
            'mime_type' => $validated['mime_type'],
            'file_extension' => $validated['extension'],
            'file_size_bytes' => $validated['size_bytes'],
            'sha256_checksum' => $checksum,
        ];
    }

    public function extractDriveId(string $input): ?string
    {
        $trimmed = trim($input);
        if (preg_match('~/(?:file/d|folders)/([a-zA-Z0-9_-]{10,120})~', $trimmed, $matches)) {
            return $matches[1];
        }
        if (preg_match('~[?&]id=([a-zA-Z0-9_-]{10,120})~', $trimmed, $matches)) {
            return $matches[1];
        }
        if (preg_match('~^[a-zA-Z0-9_-]{10,120}$~', $trimmed)) {
            return $trimmed;
        }

        return null;
    }

    public function localVaultExists(SubmissionVersion $version): bool
    {
        if (! $version->storage_disk_path) {
            return false;
        }

        return Storage::disk(config('excellia.drive.local_private_disk', 'local'))->exists($version->storage_disk_path);
    }

    /**
     * Provisions a canonical institutional Google Drive folder (via Drive v3 API in google_api mode,
     * or deterministic private workspace folder ID in workspace_sandbox mode). Never creates public links.
     */
    public function provisionFolder(string $folderName, ?string $parentFolderId = null, string $seedSlug = ''): string
    {
        if ((bool) config('excellia.drive.simulate_outage', false)) {
            throw new DriveIntegrationException('Google Drive service is unavailable for folder provisioning.');
        }

        $effectiveParent = $parentFolderId ?: (string) config('excellia.drive.root_folder_id', '1ExcelliaRoot2026_27AcademicSharing');

        if (config('excellia.drive.mode') === 'google_api') {
            $accessToken = $this->fetchGoogleAccessToken();
            $resp = Http::withToken($accessToken)->post('https://www.googleapis.com/drive/v3/files?fields=id,name', [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$effectiveParent],
            ]);

            if (! $resp->successful() || ! $resp->json('id')) {
                throw new DriveIntegrationException('Failed to create folder on Google Drive (' . $resp->status() . ').');
            }

            return (string) $resp->json('id');
        }

        $cleanSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $seedSlug) ?: Str::random(12);

        return '1ExcFolder_' . substr($cleanSlug, 0, 64);
    }

    private function uploadViaGoogleApi(UploadedFile $file, string $fileName, string $mimeType, string $folderId): array
    {
        $accessToken = $this->fetchGoogleAccessToken();
        $metadata = [
            'name' => $fileName,
            'parents' => [$folderId],
        ];

        $boundary = 'excellia_drive_' . Str::random(16);
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode($metadata) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mimeType}\r\n\r\n"
            . file_get_contents($file->getRealPath()) . "\r\n"
            . "--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink');

        if (! $response->successful() || ! $response->json('id')) {
            throw new DriveIntegrationException('Google Drive upload failed (' . $response->status() . '). Submission was not saved.');
        }

        // Security invariant: never add type=anyone permissions!
        return $response->json();
    }

    private function verifyGoogleApiFileAccess(string $driveFileId): void
    {
        $accessToken = $this->fetchGoogleAccessToken();
        $permResponse = Http::withToken($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$driveFileId}/permissions", [
                'fields' => 'permissions(id,type,role)',
            ]);

        if (! $permResponse->successful()) {
            throw new DriveIntegrationException('Unable to verify Google Drive file access for the assigned reviewer.');
        }

        foreach ($permResponse->json('permissions', []) as $perm) {
            if (($perm['type'] ?? '') === 'anyone') {
                throw new DomainRuleException('Selected Drive file has public "Anyone with the link" sharing enabled. Remove public sharing before linking.');
            }
        }
    }

    private function fetchGoogleAccessToken(): string
    {
        $clientId = config('excellia.drive.oauth_client_id');
        $clientSecret = config('excellia.drive.oauth_client_secret');
        $refreshToken = config('excellia.drive.oauth_refresh_token');

        if ($clientId && $clientSecret && $refreshToken) {
            $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (! $resp->successful() || ! $resp->json('access_token')) {
                throw new DriveIntegrationException('Failed to authenticate with Google Workspace OAuth.');
            }

            return (string) $resp->json('access_token');
        }

        $saEmail = config('excellia.drive.service_account_email');
        $saKey = config('excellia.drive.service_account_private_key');
        if (! $saEmail || ! $saKey) {
            throw new DriveIntegrationException('Google Workspace API credentials are not configured in environment.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = [
            'iss' => $saEmail,
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        if ($sub = config('excellia.drive.impersonate_user_email')) {
            $claims['sub'] = $sub;
        }
        $payload = $this->base64UrlEncode(json_encode($claims));
        $signingInput = "{$header}.{$payload}";
        $formattedKey = str_replace('\n', "\n", (string) $saKey);
        $signature = '';
        if (! openssl_sign($signingInput, $signature, $formattedKey, OPENSSL_ALGO_SHA256)) {
            throw new DriveIntegrationException('Failed to sign Google Workspace Service Account assertion.');
        }
        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new DriveIntegrationException('Google Workspace Service Account authentication failed.');
        }

        return (string) $resp->json('access_token');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiPortalController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $ai,
    ) {}

    public function assist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:task_instructions,review_feedback,teacher_outline,management_summary'],
            'document_type' => ['nullable', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:160'],
            'class_name' => ['nullable', 'string', 'max:160'],
            'period' => ['nullable', 'string', 'max:160'],
            'task_title' => ['nullable', 'string', 'max:200'],
            'teacher_name' => ['nullable', 'string', 'max:160'],
            'decision' => ['nullable', 'string', 'in:APPROVE,RETURN'],
            'keywords' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $result = $this->ai->generateAssistance($request->user(), $validated['action'], $validated);

            return response()->json([
                'ok' => true,
                'content' => $result['content'],
                'model' => $result['model'],
                'source' => $result['source'],
            ]);
        } catch (DomainRuleException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

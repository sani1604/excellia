<?php

namespace App\Console\Commands;

use App\Services\OverdueEscalationService;
use Illuminate\Console\Command;

class ProcessOverdueTasksCommand extends Command
{
    protected $signature = 'excellia:process-overdue';

    protected $description = 'Evaluate task deadlines, mark overdue items, check review SLAs, and dispatch idempotent reminders/escalations';

    public function handle(OverdueEscalationService $service): int
    {
        $summary = $service->processAll();

        $this->info(sprintf(
            'Evaluated %d tasks (%s): %d newly overdue, %d escalations, %d review SLA breaches, %d notifications processed.',
            $summary['evaluated_tasks'],
            $summary['timezone'],
            $summary['marked_overdue'],
            $summary['escalations_triggered'],
            $summary['review_sla_breaches'],
            $summary['notifications_processed']
        ));

        return self::SUCCESS;
    }
}

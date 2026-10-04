<?php

namespace App\Console\Commands;

use App\Services\Sync\RecoveryReconciliationService;
use Illuminate\Console\Command;

class CompleteRecoveryCommand extends Command
{
    protected $signature = 'app:recovery-complete {--owner-id= : Active OWNER user ID} {--reviewed-devices= : Comma-separated active device IDs whose retained operations were reviewed}';

    protected $description = 'Complete recovery after owner reviews every active device and resolves conflicts';

    public function handle(RecoveryReconciliationService $service): int
    {
        $ids = array_filter(explode(',', (string) $this->option('reviewed-devices')));
        try {
            $service->markRecoveryCompleted((int) $this->option('owner-id'), $ids);
        } catch (\Throwable $error) {
            $this->error('Recovery remains paused. Verify active OWNER, every active device and unresolved sync conflicts.');

            return self::FAILURE;
        }
        $this->info('Recovery completed. Clients must refresh bootstrap before resuming sales.');

        return self::SUCCESS;
    }
}

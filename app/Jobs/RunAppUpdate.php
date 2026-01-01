<?php

namespace App\Jobs;

use App\Services\AppUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RunAppUpdate implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $logKey;

    public $timeout = 800;

    public $tries = 3;

    public function __construct()
    {
        $this->logKey = "update:progress";
    }

    public function handle(): void
    {
        // Acquire lock to prevent concurrent updates
        $lock = Cache::lock('app:update:lock', 1200); // 20 minutes

        try {
            if (! $lock->get()) {
                // Could not acquire lock, another update is in progress
                return;
            }

            // Clear previous log to prevent showing old logs
            Cache::forget($this->logKey);

            $appendLog = function (string $line) {
                $logKey = $this->logKey;
                Cache::put($logKey, Cache::get($logKey, '').$line."\n", 3600);
            };

            try {
                $appUpdateService = new AppUpdateService($appendLog);
                $appUpdateService->backupApp();
                $appUpdateService->update();
            } catch (\Throwable $e) {
                $appendLog('❌ Update failed: '.$e->getMessage());
            }
        } finally {
            $lock?->release();
        }
    }
}

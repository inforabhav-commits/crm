<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeploymentPreflight extends Command
{
    protected $signature = 'ops:preflight';

    protected $description = 'Check production deployment prerequisites without changing application data';

    public function handle(): int
    {
        $failed = false;
        $failed = $this->check('APP_KEY configured', (bool) config('app.key')) || $failed;
        $failed = $this->check('Production debug disabled', config('app.env') !== 'production' || config('app.debug') === false) || $failed;
        $failed = $this->check('Storage writable', is_dir(storage_path('app')) && is_writable(storage_path('app'))) || $failed;

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $this->info('OK  Database connection');
        } catch (Throwable) {
            $this->error('FAIL Database connection');
            $failed = true;
        }

        Artisan::call('migrate:status', ['--no-ansi' => true]);
        $migrationOutput = Artisan::output();
        $migrationOk = ! str_contains(strtolower($migrationOutput), 'pending');
        $failed = $this->check('Migration status has no pending migrations', $migrationOk) || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $ok): bool
    {
        if ($ok) {
            $this->info('OK  '.$label);
            return false;
        }

        $this->error('FAIL '.$label);
        return true;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\PracticeLabService;
use Illuminate\Console\Command;

class PracticeExpireSessionsCommand extends Command
{
    protected $signature = 'practice:expire-sessions';

    protected $description = 'Освободить просроченные контейнеры практик (через lab-daemon)';

    public function handle(): int
    {
        $n = PracticeLabService::make()->expireStaleSessions();
        $this->info('Освобождено сессий: '.$n);

        return self::SUCCESS;
    }
}

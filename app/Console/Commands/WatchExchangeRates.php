<?php

namespace App\Console\Commands;

use App\Services\ExchangeRates;
use Illuminate\Console\Command;

class WatchExchangeRates extends Command
{
    protected $signature = 'finance:rates-watch {--once}';

    protected $description = 'Автоматически обновлять курсы Банка России каждые пять минут';

    public function handle(ExchangeRates $rates): int
    {
        $checked = null;
        do {
            try {
                $snapshot = $rates->get();
                if ($snapshot['stale']) throw new \RuntimeException();
                if ($checked !== $snapshot['checked_at']) $this->info('Курсы Банка России проверены: ' . $snapshot['checked_at']);
                $checked = $snapshot['checked_at'];
            } catch (\Throwable $error) {
                $this->warn('Не удалось обновить курсы. Следующая попытка через минуту.');
                if ($this->option('once')) return self::FAILURE;
            }
            if ($this->option('once')) return self::SUCCESS;
            sleep(60);
        } while (true);
    }
}

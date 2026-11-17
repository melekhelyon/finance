<?php

namespace App\Console\Commands;

use App\Services\ExchangeRates;
use Illuminate\Console\Command;

class RefreshExchangeRates extends Command
{
    protected $signature = 'finance:rates';

    protected $description = 'Update official Bank of Russia exchange rates';

    public function handle(ExchangeRates $rates): int
    {
        try {
            $snapshot = $rates->get(null, true);
            if ($snapshot['stale']) throw new \RuntimeException();
            $this->info('Курсы Банка России на ' . $snapshot['rate_date'] . ' обновлены.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Банк России недоступен. Сохранённые курсы не изменены.');
            return self::FAILURE;
        }
    }
}

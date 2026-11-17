<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ExchangeRates
{
    public function get($date = null, $force = false)
    {
        $today = now('Europe/Moscow')->toDateString();
        $date = $date ?: $today;
        $saved = ExchangeRate::where('requested_date', $date)->first();
        if (!$force && $saved && ($date < $today || $saved->checked_at->gt(now()->subMinutes(5)))) return $this->result($saved);
        try {
            if (Cache::has('exchange-rates-failed-' . $date)) throw new \RuntimeException();
            return Cache::lock('exchange-rates-' . $date, 30)->block(15, function () use ($date, $force) {
                $saved = ExchangeRate::where('requested_date', $date)->first();
                if (!$force && $saved && $saved->checked_at->gt(now()->subMinutes(5))) return $this->result($saved);
                $bundle = config('services.cbr.ca_bundle') ?: storage_path('app/private/certificates/ca.pem');
                $options = ['verify' => is_file($bundle) ? $bundle : true];
                if ($options['verify'] === true && PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
                    $options['curl'] = [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA];
                }
                $response = Http::withOptions($options)->connectTimeout(5)->timeout(12)->get('https://www.cbr.ru/scripts/XML_daily.asp', ['date_req' => date('d/m/Y', strtotime($date))])->throw();
                $body = $response->body();
                if (strlen($body) > 1000000 || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) throw new \RuntimeException();
                $previous = libxml_use_internal_errors(true);
                try {
                    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                if (!$xml || $xml->getName() !== 'ValCurs') throw new \RuntimeException();
                $rateDate = \DateTimeImmutable::createFromFormat('!d.m.Y', (string) $xml['Date']);
                if (!$rateDate || $rateDate->format('d.m.Y') !== (string) $xml['Date'] || $rateDate->format('Y-m-d') > $date) throw new \RuntimeException();
                $rates = ['RUB' => '1.00000000'];
                foreach ($xml->Valute as $currency) {
                    $code = (string) $currency->CharCode;
                    if (!in_array($code, ['USD', 'EUR'])) continue;
                    $nominal = (string) $currency->Nominal;
                    $value = str_replace(',', '.', (string) $currency->Value);
                    if (!preg_match('/^\d+$/', $nominal) || !preg_match('/^\d+(\.\d+)?$/', $value) || (int) $nominal <= 0 || (float) $value <= 0) throw new \RuntimeException();
                    $rates[$code] = (string) BigDecimal::of($value)->dividedBy($nominal, 8, RoundingMode::HalfUp);
                }
                if (count($rates) !== 3) throw new \RuntimeException();
                $saved = ExchangeRate::updateOrCreate(['requested_date' => $date], ['rate_date' => $rateDate->format('Y-m-d'), 'rates' => $rates, 'checked_at' => now()]);
                return $this->result($saved);
            });
        } catch (\Throwable $error) {
            Cache::put('exchange-rates-failed-' . $date, true, 60);
            $saved = $saved ?: ExchangeRate::where('requested_date', '<=', $date)->orderByDesc('requested_date')->first();
            if ($saved) return $this->result($saved, true);
            throw ValidationException::withMessages(['rates' => 'Курсы Банка России временно недоступны. Повторите попытку позже.']);
        }
    }

    public function quote($from, $to, $amount, $date = null)
    {
        if ($from === $to) return ['rate' => '1.000000000000', 'rate_date' => null, 'amount' => (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp)];
        $snapshot = $this->get($date);
        if ($snapshot['stale']) throw ValidationException::withMessages(['rates' => 'Не удалось подтвердить курс Банка России. Перевод будет доступен после обновления курсов.']);
        $rate = (string) BigDecimal::of($snapshot['rates'][$from])->dividedBy($snapshot['rates'][$to], 12, RoundingMode::HalfUp);
        return ['rate' => $rate, 'rate_date' => $snapshot['rate_date'], 'amount' => $this->convert($amount, $rate)];
    }

    public function convert($amount, $rate)
    {
        $converted = BigDecimal::of($amount)->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp);
        if ($converted->isLessThan('0.01') || $converted->isGreaterThan('9999999999999.99')) throw ValidationException::withMessages(['amount' => 'Сумма зачисления должна быть от 0,01 до 9999999999999,99.']);
        return (string) $converted;
    }

    private function result($saved, $stale = false)
    {
        return ['source' => 'Банк России', 'source_url' => 'https://www.cbr.ru/currency_base/daily/', 'requested_date' => $saved->requested_date, 'rate_date' => $saved->rate_date, 'checked_at' => $saved->checked_at->toIso8601String(), 'rates' => $saved->rates, 'stale' => $stale];
    }
}

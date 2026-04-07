<?php
/**
 * Currency exchange rates integration with Frankfurter API
 * Frankfurter: https://github.com/lineofflight/frankfurter
 * 
 * Frankfurter is a free foreign exchange rates API based on ECB data.
 * API endpoint: https://api.frankfurter.app/
 */

if (!function_exists('get_exchange_rate')) {
    /**
     * Get exchange rate between two currencies
     * 
     * @param string $from Source currency code (e.g., 'USD')
     * @param string $to Target currency code (e.g., 'EUR')
     * @return float|null Exchange rate or null on failure
     */
    function get_exchange_rate(string $from, string $to): ?float {
        if ($from === $to) {
            return 1.0;
        }

        // Use cache to avoid excessive API calls (expires in 1 hour)
        $cache_key = "exchg_rate_{$from}_{$to}";
        $cache_file = sys_get_temp_dir() . "/recepted_{$cache_key}.php";

        if (file_exists($cache_file)) {
            $cache_time = filemtime($cache_file);
            if (time() - $cache_time < 3600) { // 1 hour cache
                return (float)file_get_contents($cache_file);
            }
        }

        try {
            $url = "https://api.frankfurter.app/latest?from={$from}&to={$to}";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if (!$response) {
                return null;
            }

            $data = json_decode($response, true);
            if (!isset($data['rates'][$to])) {
                return null;
            }

            $rate = (float)$data['rates'][$to];

            // Cache the rate
            @file_put_contents($cache_file, (string)$rate);

            return $rate;
        } catch (Exception $e) {
            error_log('Frankfurter API error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('convert_currency')) {
    /**
     * Convert amount from one currency to another
     * 
     * @param float $amount Amount to convert
     * @param string $from Source currency code (e.g., 'USD')
     * @param string $to Target currency code (e.g., 'EUR')
     * @return float|null Converted amount or null on failure
     */
    function convert_currency(float $amount, string $from, string $to): ?float {
        $rate = get_exchange_rate($from, $to);
        if ($rate === null) {
            return null;
        }
        return $amount * $rate;
    }
}

if (!function_exists('get_supported_currencies')) {
    /**
     * Get list of supported currencies from Frankfurter API
     * 
     * @return array Associative array of currency codes => names
     */
    function get_supported_currencies(): array {
        $cache_file = sys_get_temp_dir() . '/recepted_frankfurter_currencies.php';

        if (file_exists($cache_file)) {
            $cache_time = filemtime($cache_file);
            if (time() - $cache_time < 86400) { // 24 hours cache
                return (array)json_decode(file_get_contents($cache_file), true);
            }
        }

        try {
            $url = 'https://api.frankfurter.app/currencies';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if (!$response) {
                return [];
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                return [];
            }

            // Cache the list
            @file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_UNICODE));

            return $data;
        } catch (Exception $e) {
            error_log('Frankfurter currencies list error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('forex_historical_rates')) {
    /**
     * Get historical exchange rates for a specific date
     * 
     * @param string $date Date in format YYYY-MM-DD
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @return float|null Historical exchange rate or null on failure
     */
    function forex_historical_rates(string $date, string $from, string $to): ?float {
        if ($from === $to) {
            return 1.0;
        }

        try {
            $url = "https://api.frankfurter.app/{$date}?from={$from}&to={$to}";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if (!$response) {
                return null;
            }

            $data = json_decode($response, true);
            if (!isset($data['rates'][$to])) {
                return null;
            }

            return (float)$data['rates'][$to];
        } catch (Exception $e) {
            error_log('Frankfurter historical rates error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('clear_rates_cache')) {
    /**
     * Clear all cached exchange rates
     */
    function clear_rates_cache(): void {
        $temp_dir = sys_get_temp_dir();
        $pattern = $temp_dir . '/recepted_exchg_rate_*.php';
        foreach (glob($pattern) as $file) {
            @unlink($file);
        }
        @unlink($temp_dir . '/recepted_frankfurter_currencies.php');
    }
}

<?php
/**
 * Klasa z funkcjami pomocniczymi dla Multi Wholesale Integration
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Utils
 * 
 * Zawiera funkcje pomocnicze używane w całej wtyczce
 */
class MHI_Utils
{
    /**
     * Pobiera zewnętrzny adres IP serwera
     *
     * @return string Adres IP lub komunikat o błędzie
     */
    public static function get_external_ip()
    {
        $ip_services = [
            'https://api.ipify.org',
            'https://ipinfo.io/ip',
            'https://icanhazip.com',
            'https://ident.me'
        ];

        foreach ($ip_services as $service) {
            $response = wp_remote_get($service, [
                'timeout' => 10,
                'sslverify' => false
            ]);

            if (!is_wp_error($response)) {
                $ip = trim(wp_remote_retrieve_body($response));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        // Fallback - spróbuj pobrać z nagłówków serwera
        $server_ips = [
            $_SERVER['SERVER_ADDR'] ?? '',
            $_SERVER['LOCAL_ADDR'] ?? '',
            gethostbyname(gethostname())
        ];

        foreach ($server_ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return __('Nie udało się określić', 'multi-hurtownie-integration');
    }

    /**
     * Sprawdza czy adres IP jest prywatny
     *
     * @param string $ip Adres IP do sprawdzenia
     * @return bool True jeśli IP jest prywatny
     */
    public static function is_private_ip($ip)
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Formatuje rozmiar pliku
     *
     * @param int $size Rozmiar w bajtach
     * @return string Sformatowany rozmiar
     */
    public static function format_file_size($size)
    {
        if (function_exists('size_format')) {
            return size_format($size);
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit_index = 0;

        while ($size >= 1024 && $unit_index < count($units) - 1) {
            $size /= 1024;
            $unit_index++;
        }

        return round($size, 2) . ' ' . $units[$unit_index];
    }

    /**
     * Sprawdza czy funkcja jest dostępna
     *
     * @param string $function_name Nazwa funkcji
     * @return bool True jeśli funkcja jest dostępna
     */
    public static function is_function_available($function_name)
    {
        return function_exists($function_name) && !in_array($function_name, explode(',', ini_get('disable_functions')));
    }

    /**
     * Sprawdza dostępność rozszerzeń PHP
     *
     * @return array Lista dostępnych rozszerzeń
     */
    public static function check_php_extensions()
    {
        $extensions = [
            'curl' => extension_loaded('curl'),
            'ftp' => extension_loaded('ftp'),
            'ssh2' => extension_loaded('ssh2'),
            'openssl' => extension_loaded('openssl'),
            'xml' => extension_loaded('xml'),
            'simplexml' => extension_loaded('simplexml'),
            'json' => extension_loaded('json')
        ];

        return $extensions;
    }
}
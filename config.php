<?php
/**
 * Plik konfiguracyjny dla Multi Wholesale Integration
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Konfiguracja logowania
define('MHI_DEBUG_MODE', true);
define('MHI_LOG_LEVEL', 'info'); // info, warning, error

// Konfiguracja importu
define('MHI_IMPORT_BATCH_SIZE', 10);
define('MHI_IMPORT_TIMEOUT', 300);
define('MHI_IMPORT_MEMORY_LIMIT', '512M');

// Konfiguracja połączeń
define('MHI_CONNECTION_TIMEOUT', 30);
define('MHI_MAX_RETRIES', 3);

// Protokoły połączeń
define('MHI_DEFAULT_PROTOCOL_FTP', 'ftp');
define('MHI_DEFAULT_PROTOCOL_SFTP', 'sftp');
define('MHI_DEFAULT_PROTOCOL_FTPS', 'ftps');

// Stałe dla hurtowni - WYMAGANE przez admin panel
define('MHI_DEFAULT_MALFINI_API_URL', 'https://malfini.com/api/');
define('MHI_DEFAULT_AXPOL_XML_SERVER', 'ftp2.axpol.com.pl');
define('MHI_DEFAULT_AXPOL_IMG_SERVER', 'ftp.axpol.com.pl');
define('MHI_DEFAULT_MACMA_API_URL', 'http://www.macma.pl/data/webapi/pl/xml/');
define('MHI_DEFAULT_PAR_API_URL', 'http://www.par.com.pl/api/');
define('MHI_DEFAULT_INSPIRION_SERVER', 'ftp.inspirion.pl');
define('MHI_DEFAULT_INSPIRION_EPAPER_URL', 'https://epaper.promotiontops-digital.com/PT2024/PL_mP2_ADC/');

// Domyślne ustawienia
$mhi_default_settings = [
    'cleanup_enabled' => true,
    'auto_fetch_enabled' => false,
    'fetch_interval' => 'daily',
    'max_file_age' => 7 // dni
];

// Mapowanie hurtowni
$mhi_wholesalers = [
    'malfini' => [
        'name' => 'Malfini',
        'class' => 'MHI_Hurtownia_1',
        'file' => 'class-mhi-hurtownia-1.php'
    ],
    'axpol' => [
        'name' => 'Axpol',
        'class' => 'MHI_Hurtownia_2',
        'file' => 'class-mhi-hurtownia-2.php'
    ],
    'macma' => [
        'name' => 'Macma',
        'class' => 'MHI_Hurtownia_5',
        'file' => 'class-mhi-hurtownia-5.php'
    ],
    'par' => [
        'name' => 'Par',
        'class' => 'MHI_Par',
        'file' => 'class-mhi-par.php'
    ],
    'inspirion' => [
        'name' => 'Inspirion',
        'class' => 'MHI_Hurtownia_4',
        'file' => 'class-mhi-hurtownia-4.php'
    ]
];

/**
 * Pobiera bezpieczne dane konfiguracyjne z opcji WordPress
 *
 * @param string $key Klucz konfiguracyjny
 * @return string Wartość konfiguracyjna lub pusty string jeśli nie znaleziono
 */
if (!function_exists('mhi_get_secure_config')) {
    function mhi_get_secure_config($key)
    {
        // Mapowanie kluczy na opcje WordPress
        $option_map = [
            // Hurtownia 2 (Axpol)
            'hurtownia_2_xml_login' => 'mhi_hurtownia_2_xml_login',
            'hurtownia_2_xml_password' => 'mhi_hurtownia_2_xml_password',
            'hurtownia_2_img_login' => 'mhi_hurtownia_2_img_login',
            'hurtownia_2_img_password' => 'mhi_hurtownia_2_img_password',

            // Hurtownia 1 (Malfini) - poprawione klucze
            'hurtownia_1_api_key' => 'mhi_hurtownia_1_api_key',
            'hurtownia_1_login' => 'mhi_hurtownia_1_login',
            'hurtownia_1_password' => 'mhi_hurtownia_1_password',

            // Hurtownia 3 (PAR) - poprawione klucze
            'hurtownia_3_username' => 'mhi_hurtownia_3_username',
            'hurtownia_3_password' => 'mhi_hurtownia_3_password',

            // Hurtownia 4 (Inspirion)
            'hurtownia_4_username' => 'mhi_hurtownia_4_username',
            'hurtownia_4_password' => 'mhi_hurtownia_4_password',

            // Hurtownia 5 (Macma)
            'hurtownia_5_username' => 'mhi_hurtownia_5_username',
            'hurtownia_5_password' => 'mhi_hurtownia_5_password',
        ];

        // Sprawdź czy klucz istnieje w mapowaniu
        if (!isset($option_map[$key])) {
            error_log("MHI: Nieznany klucz konfiguracyjny: {$key}");
            return '';
        }

        $option_name = $option_map[$key];
        $value = get_option($option_name, '');

        // Loguj tylko że pobrano wartość (bez ujawniania hasła)
        if (strpos($key, 'password') !== false) {
            error_log("MHI: Pobrano hasło dla klucza: {$key} (długość: " . strlen($value) . ")");
        } else {
            error_log("MHI: Pobrano konfigurację dla klucza: {$key} = {$value}");
        }

        return $value;
    }
}
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

// Stałe dla hurtowni - WYMAGANE przez admin panel
define('MHI_DEFAULT_MALFINI_API_URL', 'https://malfini.com/api/');
define('MHI_DEFAULT_AXPOL_XML_SERVER', 'https://axpol.com.pl/xml/');
define('MHI_DEFAULT_AXPOL_IMG_SERVER', 'https://axpol.com.pl/images/');
define('MHI_DEFAULT_MACMA_API_URL', 'https://macma.pl/api/');
define('MHI_DEFAULT_PAR_API_URL', 'https://par.com.pl/api/');
define('MHI_DEFAULT_INSPIRION_SERVER', 'https://inspirion.pl/ftp/');
define('MHI_DEFAULT_INSPIRION_EPAPER_URL', 'https://inspirion.pl/epaper/');

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
        'class' => 'MHI_Hurtownia_3',
        'file' => 'class-mhi-hurtownia-3.php'
    ],
    'par' => [
        'name' => 'Par',
        'class' => 'MHI_Hurtownia_4',
        'file' => 'class-mhi-hurtownia-4.php'
    ]
];
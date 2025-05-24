<?php
/**
 * Plik konfiguracyjny pluginu Multi-Wholesale-Integration
 * 
 * Wszystkie wrażliwe dane powinny być konfigurowane przez panel administracyjny
 * lub przez zmienne środowiskowe.
 * 
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

// Domyślne adresy serwerów (publiczne informacje)
define('MHI_DEFAULT_MALFINI_API_URL', 'https://api.malfini.com/api/v4');
define('MHI_DEFAULT_AXPOL_XML_SERVER', 'ftp2.axpol.com.pl');
define('MHI_DEFAULT_AXPOL_IMG_SERVER', 'ftp.axpol.com.pl');
define('MHI_DEFAULT_INSPIRION_SERVER', 'ftp.inspirion.pl');
define('MHI_DEFAULT_INSPIRION_EPAPER_URL', 'https://epaper.promotiontops-digital.com/PT2024/PL_mP2_ADC/');

// Domyślne protokoły
define('MHI_DEFAULT_PROTOCOL_SFTP', 'sftp');
define('MHI_DEFAULT_PROTOCOL_FTP', 'ftp');

// Ustawienia bezpieczeństwa
define('MHI_REQUIRE_CONFIG_FROM_ADMIN', true); // Wymusza konfigurację przez panel administracyjny
define('MHI_LOG_CREDENTIAL_USAGE', false); // Czy logować użycie danych uwierzytelniających (tylko do debugowania)

/**
 * Funkcja pobierająca bezpieczne dane konfiguracyjne
 * 
 * @param string $key Klucz konfiguracji
 * @param mixed $default Wartość domyślna
 * @return mixed
 */
function mhi_get_secure_config($key, $default = '')
{
    // Sprawdź zmienne środowiskowe najpierw
    $env_value = getenv('MHI_' . strtoupper($key));
    if ($env_value !== false) {
        return $env_value;
    }

    // Sprawdź opcje WordPress
    $wp_value = get_option('mhi_' . $key, $default);

    // Loguj użycie danych uwierzytelniających (tylko jeśli włączone)
    if (MHI_LOG_CREDENTIAL_USAGE && strpos($key, 'password') !== false) {
        error_log('MHI: Pobrano hasło dla klucza: ' . $key);
    }

    return $wp_value;
}

/**
 * Funkcja sprawdzająca czy wszystkie wymagane dane konfiguracyjne są ustawione
 * 
 * @param array $required_keys Lista wymaganych kluczy
 * @return bool
 */
function mhi_validate_config($required_keys)
{
    foreach ($required_keys as $key) {
        $value = mhi_get_secure_config($key);
        if (empty($value)) {
            return false;
        }
    }
    return true;
}

/**
 * Funkcja ostrzegająca o braku konfiguracji
 */
function mhi_show_config_warning()
{
    if (is_admin()) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Multi-Wholesale Integration:</strong> ';
            echo 'Skonfiguruj dane uwierzytelniające w <a href="' . admin_url('admin.php?page=multi-hurtownie-integration') . '">ustawieniach pluginu</a>.';
            echo '</p>';
            echo '</div>';
        });
    }
}
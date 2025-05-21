<?php
/**
 * Plugin Name: Multi Wholesale Integration
 * Plugin URI: https://github.com/kemuriCode/Multi-Wholesale-Integration
 * Description: Wtyczka integrująca wieloma hurtowniami reklamowymi za pomocą różnych protokołów (FTP, SFTP, API)
 * Version: 1.0.0
 * Author: kemuriCode
 * Author URI: https://github.com/kemuriCode
 * Text Domain: multi-wholesale-integration
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Tymczasowe wyłączenie wtyczki mhi-product-importer, aby uniknąć konfliktu
add_action('pre_update_option_active_plugins', function ($plugins) {
    foreach ($plugins as $key => $plugin) {
        if (strpos($plugin, 'mhi-product-importer') !== false) {
            error_log('MHI: Wyłączam konfliktową wtyczkę mhi-product-importer');
            unset($plugins[$key]);
        }
    }
    return $plugins;
}, 10, 1);

// Włącz raportowanie błędów dla diagnostyki
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Dołącz autoloader Composera
$composer_autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($composer_autoloader)) {
    require_once $composer_autoloader;
}

// Definicje stałych
define('MHI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MHI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MHI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MHI_VERSION', '1.0.0');
define('MHI_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/wholesale');

// Załaduj klasę pomocniczą do debugowania AJAX
require_once MHI_PLUGIN_DIR . 'includes/class-mhi-debug-helper.php';

// Sprawdź czy interfejs istnieje w tej wtyczce
if (file_exists(MHI_PLUGIN_DIR . 'includes/interfaces/interface-mhi-integration.php')) {
    // Bezpośrednie ładowanie interfejsu - rozwiązuje problem autoloadera
    require_once MHI_PLUGIN_DIR . 'includes/interfaces/interface-mhi-integration.php';
} else {
    // Jeśli nie ma pliku, stwórz go
    if (!file_exists(MHI_PLUGIN_DIR . 'includes/interfaces')) {
        wp_mkdir_p(MHI_PLUGIN_DIR . 'includes/interfaces');
    }

    // Tworzymy podstawowy interfejs, jeśli nie istnieje
    $interface_content = '<?php
/**
 * Interfejs dla modułów integracji z hurtowniami
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined("ABSPATH")) {
    exit;
}

/**
 * Interfejs MHI_Integration_Interface
 * 
 * Definiuje wymagane metody dla klas integracji z hurtowniami
 */
interface MHI_Integration_Interface {
    /**
     * Nawiązuje połączenie z zewnętrznym źródłem danych
     *
     * @return bool Informacja czy połączenie zostało nawiązane
     */
    public function connect();
    
    /**
     * Pobiera pliki z zewnętrznego źródła
     *
     * @return mixed Wynik operacji pobierania plików
     */
    public function fetch_files();
    
    /**
     * Sprawdza poprawność danych logowania
     *
     * @return bool Informacja czy dane logowania są poprawne
     */
    public function validate_credentials();
    
    /**
     * Przetwarza pobrane pliki
     *
     * @param array $files Lista plików do przetworzenia
     * @return mixed Wynik operacji przetwarzania plików
     */
    public function process_files($files);
}';

    // Zapisz plik z interfejsem
    file_put_contents(MHI_PLUGIN_DIR . 'includes/interfaces/interface-mhi-integration.php', $interface_content);

    // Teraz załaduj utworzony interfejs
    require_once MHI_PLUGIN_DIR . 'includes/interfaces/interface-mhi-integration.php';
}

// Załaduj logger
if (file_exists(MHI_PLUGIN_DIR . 'includes/class-mhi-logger.php')) {
    require_once MHI_PLUGIN_DIR . 'includes/class-mhi-logger.php';
    MHI_Logger::init();
} else {
    // Logger nie istnieje, tworzymy prosty zastępczy logger
    class MHI_Logger
    {
        public static function init()
        {
        }
        public static function info($message, $context = [])
        {
        }
        public static function warning($message, $context = [])
        {
        }
        public static function error($message, $context = [])
        {
        }
    }
}

// Załaduj klasę czyszczenia danych
if (file_exists(MHI_PLUGIN_DIR . 'includes/class-mhi-cleanup.php')) {
    require_once MHI_PLUGIN_DIR . 'includes/class-mhi-cleanup.php';
    // Klasa sama się inicjalizuje
} else {
    MHI_Logger::warning('Brak klasy MHI_Cleanup, funkcje czyszczenia danych nie będą dostępne.');
}

// Autoloader dla klas wtyczki
spl_autoload_register(function ($class_name) {
    // Sprawdza, czy klasa ma prefiks MHI_
    if (strpos($class_name, 'MHI_') !== 0) {
        return;
    }

    // Konwersja nazwy klasy na ścieżkę pliku
    $class_file = str_replace('_', '-', strtolower($class_name));
    $class_path = 'class-' . $class_file . '.php';

    // Definiowanie możliwych ścieżek
    $possible_paths = array(
        MHI_PLUGIN_DIR . 'includes/' . $class_path,
        MHI_PLUGIN_DIR . 'includes/interfaces/' . $class_path,
        MHI_PLUGIN_DIR . 'integrations/' . $class_path,
        MHI_PLUGIN_DIR . 'admin/' . $class_path,
    );

    // Próba załadowania pliku
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Ładowanie klas
$plugin_class_path = MHI_PLUGIN_DIR . 'includes/class-mhi-plugin.php';
if (file_exists($plugin_class_path)) {
    require_once $plugin_class_path;
} else {
    // Jeśli plik nie istnieje, stwórz podstawową wersję klasy
    $basic_plugin_class = '<?php
/**
 * Główna klasa wtyczki
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined("ABSPATH")) {
    exit;
}

/**
 * Klasa MHI_Plugin
 * 
 * Inicjalizuje i obsługuje główne funkcje wtyczki
 */
class MHI_Plugin {
    /**
     * Konstruktor
     */
    public function __construct() {
        // Inicjalizacja
    }
    
    /**
     * Uruchamia wtyczkę
     */
    public function run() {
        // Dodaj akcje i filtry WordPress
        add_action("admin_notices", array($this, "display_admin_notice"));
    }
    
    /**
     * Wyświetla powiadomienie administracyjne
     */
    public function display_admin_notice() {
        ?>
        <div class="notice notice-warning">
            <p><strong>Multi Hurtownie Integration:</strong> Wtyczka jest w podstawowym trybie pracy. Brakuje niektórych plików. Skontaktuj się z administratorem.</p>
        </div>
        <?php
    }
    
    /**
     * Zwraca status AJAX
     */
    public function ajax_get_download_status() {
        wp_send_json_success(array("status" => "brak danych"));
        wp_die();
    }
}';

    // Zapisz tymczasowy plik klasy Plugin
    $plugin_dir = MHI_PLUGIN_DIR . 'includes';
    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }
    file_put_contents($plugin_class_path, $basic_plugin_class);

    // Załaduj utworzony plik
    require_once $plugin_class_path;
}

// Inicjalizacja wtyczki
function mhi_init()
{
    // Inicjalizuj wtyczkę
    require_once plugin_dir_path(__FILE__) . 'includes/class-mhi-plugin.php';
    $plugin = new MHI_Plugin();
    $plugin->run();

    // Inicjalizuj procesy w tle
    mhi_init_background_processes();

    // Inicjalizuj obsługę AJAX
    mhi_init_ajax_handlers();

    // UWAGA: Rejestracja zasobów JS/CSS jest obsługiwana przez hook admin_enqueue_scripts
}

// Funkcja wyświetlająca stronę ustawień
function mhi_display_settings_page()
{
    // Sprawdzenie uprawnień
    if (!current_user_can('manage_options')) {
        return;
    }

    // Sprawdź czy funkcja jest używana jako strona importera czy ogólna strona ustawień
    $page = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'importer';

    if ($page === 'importer') {
        // Strona importera bez AJAX
        require_once MHI_PLUGIN_DIR . 'admin/views/importer-page.php';
    } else {
        // Wyświetlanie ogólnej strony ustawień
        require_once MHI_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}

// Uruchomienie wtyczki
add_action('plugins_loaded', 'mhi_init');

// Aktywacja wtyczki
register_activation_hook(__FILE__, 'mhi_activate');
function mhi_activate()
{
    // Utworzenie katalogu uploads/wholesale jeśli nie istnieje
    $upload_dir = wp_upload_dir();
    $wholesale_dir = $upload_dir['basedir'] . '/wholesale';

    if (!file_exists($wholesale_dir)) {
        wp_mkdir_p($wholesale_dir);
    }

    // Utworzenie katalogów dla poszczególnych hurtowni
    $wholesale = ['hurtownia_1', 'hurtownia_2', 'hurtownia_3', 'hurtownia_4', 'hurtownia_5'];
    foreach ($wholesale as $hurtownia) {
        $dir = $wholesale_dir . '/' . $hurtownia;
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}

// Dezaktywacja wtyczki
register_deactivation_hook(__FILE__, 'mhi_deactivate');
function mhi_deactivate()
{
    // Usunięcie wszystkich zaplanowanych zdarzeń cron
    wp_clear_scheduled_hook('mhi_cron_fetch_all');

    // Usunięcie zadania cron czyszczenia mediów
    wp_clear_scheduled_hook('mhi_media_cleanup_event');

    // Usunięcie zadań cron dla poszczególnych hurtowni
    $wholesale = ['hurtownia_1', 'hurtownia_2', 'hurtownia_3', 'hurtownia_4', 'hurtownia_5'];
    foreach ($wholesale as $hurtownia) {
        wp_clear_scheduled_hook('mhi_cron_fetch_' . $hurtownia);
    }
}

// Usuń wszystkie poprzednie hooki związane z czyszczeniem mediów
remove_action('admin_footer', 'mhi_add_cleanup_button_script');
remove_action('admin_bar_menu', 'mhi_add_cleanup_button_to_admin_bar', 999);
remove_action('admin_footer', 'mhi_add_adminbar_button_script');

// Dodaj prosty, widoczny link w menu Media - tylko ten jeden zostanie
add_action('admin_menu', 'mhi_add_media_cleanup_menu_item');
function mhi_add_media_cleanup_menu_item()
{
    add_submenu_page(
        'upload.php',                       // Rodzic - menu Media
        'Czyszczenie mediów',              // Tytuł strony
        'WYCZYŚĆ PUSTE MEDIA',             // Nazwa w menu
        'manage_options',                   // Uprawnienia
        'mhi-media-cleanup',                // Slug
        'mhi_render_media_cleanup_page'     // Funkcja
    );
}

// Strona czyszczenia mediów
function mhi_render_media_cleanup_page()
{
    ?>
    <div class="wrap">
        <h1>Czyszczenie pustych wpisów medialnych</h1>

        <div style="background: white; padding: 20px; border-left: 5px solid #d63638; margin: 20px 0;">
            <h2>Czyszczenie pustych kafelków w bibliotece mediów</h2>

            <p style="font-size: 16px; margin-bottom: 20px;">
                Ten przycisk usunie wszystkie wpisy medialnych, których pliki fizyczne zostały usunięte (puste kafelki).
                <br>Po usunięciu pustych wpisów, kafelki nie będą już wyświetlane w bibliotece mediów.
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('mhi_cleanup_media_nonce', 'mhi_cleanup_media_nonce'); ?>

                <button type="submit" name="mhi_cleanup_media_submit" id="mhi-cleanup-button" class="button button-primary"
                    style="background-color: #d63638; border-color: #d63638; color: white; padding: 10px 20px; font-size: 16px; font-weight: bold; height: auto;">
                    <span class="dashicons dashicons-trash"
                        style="font-size: 20px; margin-right: 8px; line-height: 1.2;"></span>
                    WYCZYŚĆ PUSTE KAFELKI MEDIÓW
                </button>
            </form>

            <?php
            // Obsługa przesłanego formularza
            if (isset($_POST['mhi_cleanup_media_submit']) && check_admin_referer('mhi_cleanup_media_nonce', 'mhi_cleanup_media_nonce')) {
                // Zwiększ limit pamięci i limit czasu wykonania
                set_time_limit(300); // 5 minut
                ini_set('memory_limit', '512M');

                // Licznik
                $deleted_count = 0;

                // Pobierz wszystkie załączniki w porcjach
                $paged = 1;
                $per_page = 100;

                echo '<div id="mhi-progress" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007cba;">';
                echo '<h3>Trwa czyszczenie mediów...</h3>';
                echo '<p>Proszę czekać, proces może potrwać kilka minut.</p>';

                // Flush output buffer aby pokazać progress
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }

                do {
                    $attachments = get_posts([
                        'post_type' => 'attachment',
                        'posts_per_page' => $per_page,
                        'paged' => $paged,
                        'post_status' => 'any'
                    ]);

                    if (empty($attachments)) {
                        break;
                    }

                    // Sprawdź każdy załącznik w bieżącej porcji
                    foreach ($attachments as $attachment) {
                        $file_path = get_attached_file($attachment->ID);

                        // Jeśli plik nie istnieje fizycznie
                        if (!file_exists($file_path) || empty($file_path)) {
                            // Usuń załącznik i powiązane metadane
                            wp_delete_attachment($attachment->ID, true);
                            $deleted_count++;
                        }
                    }

                    // Wyczyść pamięć
                    wp_reset_postdata();

                    // Wyświetl aktualizację co 100 plików
                    echo '<p>Przeanalizowano ' . ($paged * $per_page) . ' plików, usunięto ' . $deleted_count . ' pustych wpisów...</p>';

                    // Flush output buffer aby pokazać progress
                    if (ob_get_level() > 0) {
                        ob_flush();
                        flush();
                    }

                    // Przejdź do następnej strony
                    $paged++;

                } while (!empty($attachments));

                echo '</div>';

                // Wyświetl podsumowanie
                if ($deleted_count > 0) {
                    echo '<div class="notice notice-success" style="margin-top: 20px;">';
                    echo '<p><strong>Operacja zakończona pomyślnie!</strong></p>';
                    echo '<p>Usunięto ' . $deleted_count . ' pustych wpisów medialnych.</p>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-info" style="margin-top: 20px;">';
                    echo '<p><strong>Operacja zakończona.</strong></p>';
                    echo '<p>Nie znaleziono żadnych pustych wpisów medialnych do usunięcia.</p>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
    <?php
}

// Dodanie obsługi formularzy do generowania pliku XML i importu
add_action('admin_init', 'mhi_handle_form_submissions');
function mhi_handle_form_submissions()
{
    // Obsługa generowania pliku XML dla Axpol
    if (isset($_POST['mhi_axpol_generate_xml']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_axpol_generate_xml', 'mhi_axpol_generate_xml_nonce')) {
            // Załaduj klasę integracji Axpol
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php';
                $axpol = new MHI_Hurtownia_2();

                // Generuj plik XML
                $result = $axpol->generate_woocommerce_xml();

                if ($result) {
                    add_settings_error(
                        'mhi_axpol_generate_xml',
                        'mhi_xml_generated',
                        __('Wygenerowano plik XML dla Axpol pomyślnie.', 'multi-wholesale-integration'),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_axpol_generate_xml',
                        'mhi_xml_generation_failed',
                        __('Nie udało się wygenerować pliku XML dla Axpol.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } else {
                add_settings_error(
                    'mhi_axpol_generate_xml',
                    'mhi_class_not_found',
                    __('Nie znaleziono klasy integracji Axpol.', 'multi-wholesale-integration'),
                    'error'
                );
            }
        }
    }

    // Obsługa generowania pliku XML dla Malfini
    if (isset($_POST['mhi_malfini_generate_xml']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_malfini_generate_xml', 'mhi_malfini_generate_xml_nonce')) {
            // Załaduj klasę integracji Malfini
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php';
                $malfini = new MHI_Hurtownia_1();

                // Generuj plik XML
                $result = $malfini->generate_woocommerce_xml();

                if ($result) {
                    add_settings_error(
                        'mhi_malfini_generate_xml',
                        'mhi_xml_generated',
                        __('Wygenerowano plik XML dla Malfini pomyślnie.', 'multi-wholesale-integration'),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_malfini_generate_xml',
                        'mhi_xml_generation_failed',
                        __('Nie udało się wygenerować pliku XML dla Malfini.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } else {
                add_settings_error(
                    'mhi_malfini_generate_xml',
                    'mhi_class_not_found',
                    __('Nie znaleziono klasy integracji Malfini.', 'multi-wholesale-integration'),
                    'error'
                );
            }
        }
    }

    // Obsługa generowania XML dla Macma
    if (isset($_POST['mhi_macma_generate_xml']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_macma_generate_xml', 'mhi_macma_generate_xml_nonce')) {
            // Załaduj klasę integracji Macma
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php';
                $macma = new MHI_Hurtownia_3();

                // Generuj plik XML
                $result = $macma->generate_wc_xml();

                if ($result !== false) {
                    $status = __('Plik XML został wygenerowany: ', 'multi-wholesale-integration') . $result;
                    add_settings_error('mhi_settings', 'mhi_macma_generate_xml', $status, 'success');
                } else {
                    $status = __('Wystąpił błąd podczas generowania pliku XML. Sprawdź logi.', 'multi-wholesale-integration');
                    add_settings_error('mhi_settings', 'mhi_macma_generate_xml', $status, 'error');
                }
            } else {
                $status = __('Nie znaleziono pliku integracji dla hurtowni Macma.', 'multi-wholesale-integration');
                add_settings_error('mhi_settings', 'mhi_macma_generate_xml', $status, 'error');
            }
        }
    }

    // Obsługa generowania XML dla Par
    if (isset($_POST['mhi_par_generate_xml']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_par_generate_xml', 'mhi_par_generate_xml_nonce')) {
            // Załaduj klasę generatora XML dla Par
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-par-wc-xml-generator.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-par-wc-xml-generator.php';
                $par_generator = new MHI_Par_WC_XML_Generator();

                // Generuj plik XML
                $result = $par_generator->generate_woocommerce_xml();

                if ($result !== false) {
                    $status = __('Plik XML dla Par został wygenerowany pomyślnie.', 'multi-wholesale-integration');
                    add_settings_error('mhi_settings', 'mhi_par_generate_xml', $status, 'success');
                } else {
                    $status = __('Wystąpił błąd podczas generowania pliku XML dla Par. Sprawdź logi.', 'multi-wholesale-integration');
                    add_settings_error('mhi_settings', 'mhi_par_generate_xml', $status, 'error');
                }
            } else {
                $status = __('Nie znaleziono pliku generatora XML dla hurtowni Par.', 'multi-wholesale-integration');
                add_settings_error('mhi_settings', 'mhi_par_generate_xml', $status, 'error');
            }
        }
    }

    // Obsługa importu produktów dla Axpol
    if (isset($_POST['mhi_axpol_import_products']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_axpol_import_products', 'mhi_axpol_import_products_nonce')) {
            // Załaduj klasę integracji Axpol
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php';
                $axpol = new MHI_Hurtownia_2();

                // Importuj produkty
                $result = $axpol->import_products_to_woocommerce();

                if ($result === true) {
                    $status = __('Import produktów rozpoczęty. Proces może potrwać kilka minut.', 'multi-wholesale-integration');
                    add_settings_error(
                        'mhi_axpol_import_products',
                        'mhi_import_started',
                        $status,
                        'success'
                    );
                    update_option('mhi_axpol_import_status', $status);
                } else {
                    add_settings_error(
                        'mhi_axpol_import_products',
                        'mhi_import_failed',
                        is_string($result) ? $result : __('Wystąpił błąd podczas importu produktów.', 'multi-wholesale-integration'),
                        'error'
                    );
                    update_option('mhi_axpol_import_status', __('Błąd importu: ', 'multi-wholesale-integration') . (is_string($result) ? $result : ''));
                }
            } else {
                add_settings_error(
                    'mhi_axpol_import_products',
                    'mhi_integration_not_found',
                    __('Nie znaleziono klasy integracji Axpol.', 'multi-wholesale-integration'),
                    'error'
                );
            }
        }
    }

    // Obsługa importu produktów dla Macma
    if (isset($_POST['mhi_macma_import_products']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_macma_import_products', 'mhi_macma_import_products_nonce')) {
            // Załaduj klasę integracji Macma
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php';
                $macma = new MHI_Hurtownia_3();

                // Importuj produkty
                try {
                    $result = $macma->import_products_to_woocommerce();

                    if ($result === true) {
                        $status = __('Import produktów rozpoczęty. Proces może potrwać kilka minut.', 'multi-wholesale-integration');
                        add_settings_error(
                            'mhi_macma_import_products',
                            'mhi_import_started',
                            $status,
                            'success'
                        );
                        update_option('mhi_macma_import_status', $status);
                    } else {
                        add_settings_error(
                            'mhi_macma_import_products',
                            'mhi_import_failed',
                            is_string($result) ? $result : __('Wystąpił błąd podczas importu produktów.', 'multi-wholesale-integration'),
                            'error'
                        );
                        update_option('mhi_macma_import_status', __('Błąd importu: ', 'multi-wholesale-integration') . (is_string($result) ? $result : ''));
                    }
                } catch (Exception $e) {
                    add_settings_error(
                        'mhi_macma_import_products',
                        'mhi_import_failed',
                        $e->getMessage(),
                        'error'
                    );
                    update_option('mhi_macma_import_status', __('Błąd importu: ', 'multi-wholesale-integration') . $e->getMessage());
                }
            } else {
                add_settings_error(
                    'mhi_macma_import_products',
                    'mhi_integration_not_found',
                    __('Nie znaleziono klasy integracji Macma.', 'multi-wholesale-integration'),
                    'error'
                );
            }
        }
    }

    // Obsługa importu produktów dla Par
    if (isset($_POST['mhi_par_import_products']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_par_import_products', 'mhi_par_import_products_nonce')) {
            // Załaduj klasę generatora XML dla Par
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-par-wc-xml-generator.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-par-wc-xml-generator.php';

                // Sprawdź czy generatory XML zostały już wygenerowane
                $upload_dir = wp_upload_dir();
                $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/par/woocommerce_import_par.xml';

                if (!file_exists($xml_file)) {
                    add_settings_error(
                        'mhi_par_import_products',
                        'mhi_no_xml_file',
                        __('Brak pliku XML do importu. Najpierw wygeneruj plik XML.', 'multi-wholesale-integration'),
                        'error'
                    );
                    return;
                }

                // Importuj produkty
                try {
                    // Poniżej dodajemy obsługę importu przez WP All Import jeśli plugin jest dostępny
                    if (class_exists('PMXI_Plugin')) {
                        // Jeśli mamy WP All Import, tworzymy nowy import
                        $import_id = wp_insert_post(array(
                            'post_title' => 'Import produktów Par - ' . date('Y-m-d H:i:s'),
                            'post_type' => 'import',
                            'post_status' => 'publish'
                        ));

                        if ($import_id) {
                            update_option('mhi_par_import_status', __('Import został zainicjowany. Sprawdź status w WP All Import.', 'multi-wholesale-integration'));
                            add_settings_error(
                                'mhi_par_import_products',
                                'mhi_import_started',
                                __('Import produktów został zainicjowany. Sprawdź status w WP All Import.', 'multi-wholesale-integration'),
                                'success'
                            );
                        } else {
                            update_option('mhi_par_import_status', __('Nie udało się utworzyć zadania importu w WP All Import.', 'multi-wholesale-integration'));
                            add_settings_error(
                                'mhi_par_import_products',
                                'mhi_import_failed',
                                __('Nie udało się utworzyć zadania importu w WP All Import.', 'multi-wholesale-integration'),
                                'error'
                            );
                        }
                    } else {
                        // Brak WP All Import - wyświetlamy informację
                        update_option('mhi_par_import_status', __('Plugin WP All Import nie jest zainstalowany. Import nie jest możliwy.', 'multi-wholesale-integration'));
                        add_settings_error(
                            'mhi_par_import_products',
                            'mhi_import_failed',
                            __('Plugin WP All Import nie jest zainstalowany. Import nie jest możliwy.', 'multi-wholesale-integration'),
                            'error'
                        );
                    }
                } catch (Exception $e) {
                    add_settings_error(
                        'mhi_par_import_products',
                        'mhi_import_failed',
                        $e->getMessage(),
                        'error'
                    );
                    update_option('mhi_par_import_status', __('Błąd importu: ', 'multi-wholesale-integration') . $e->getMessage());
                }
            } else {
                add_settings_error(
                    'mhi_par_import_products',
                    'mhi_integration_not_found',
                    __('Nie znaleziono klasy generatora XML dla Par.', 'multi-wholesale-integration'),
                    'error'
                );
            }
        }
    }

    // Obsługa importu produktów dla Malfini
    if (isset($_POST['mhi_malfini_import_products']) && current_user_can('manage_options')) {
        if (check_admin_referer('mhi_malfini_import_products', 'mhi_malfini_import_products_nonce')) {
            // Załaduj klasę integracji Malfini
            if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php')) {
                require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php';
                $malfini = new MHI_Hurtownia_1();

                // Sprawdź czy plik XML istnieje
                $upload_dir = wp_upload_dir();
                $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/malfini/woocommerce_import_malfini.xml';

                if (!file_exists($xml_file)) {
                    add_settings_error(
                        'mhi_malfini_import_products',
                        'mhi_xml_file_missing',
                        __('Brak pliku XML do importu. Najpierw wygeneruj plik XML.', 'multi-wholesale-integration'),
                        'error'
                    );
                    return;
                }

                // Importuj produkty
                $result = $malfini->import_products_to_woocommerce();

                if ($result === true) {
                    $status = __('Import produktów rozpoczęty. Proces może potrwać kilka minut.', 'multi-wholesale-integration');
                    add_settings_error(
                        'mhi_malfini_import_products',
                        'mhi_import_started',
                        $status,
                        'success'
                    );
                    update_option('mhi_malfini_import_status', $status);
                } else {
                    add_settings_error(
                        'mhi_malfini_import_products',
                        'mhi_import_failed',
                        is_string($result) ? $result : __('Wystąpił błąd podczas importu produktów.', 'multi-wholesale-integration'),
                        'error'
                    );
                    update_option('mhi_malfini_import_status', __('Błąd importu: ', 'multi-wholesale-integration') . (is_string($result) ? $result : ''));
                }
            } else {
                add_settings_error(
                    'mhi_malfini_import_products',
                    'mhi_integration_not_found',
                    __('Nie znaleziono klasy integracji Malfini.', 'multi-wholesale-integration'),
                    'error'
                );
            }
        }
    }
}

// Dodanie zadania cron do automatycznego generowania pliku XML
register_activation_hook(__FILE__, 'mhi_schedule_xml_generation');
function mhi_schedule_xml_generation()
{
    if (!wp_next_scheduled('mhi_generate_woocommerce_xml')) {
        wp_schedule_event(time(), 'daily', 'mhi_generate_woocommerce_xml');
    }
}

// Hook do automatycznego generowania pliku XML
add_action('mhi_generate_woocommerce_xml', 'mhi_cron_generate_xml');
function mhi_cron_generate_xml()
{
    // Sprawdź czy Axpol jest włączony
    if (get_option('mhi_hurtownia_2_enabled', 0)) {
        // Załaduj klasę integracji Axpol
        if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php')) {
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php';
            $axpol = new MHI_Hurtownia_2();

            // Generuj plik XML
            $result = $axpol->generate_woocommerce_xml();

            if ($result) {
                // Zapisz informację o pomyślnym wygenerowaniu pliku
                update_option('mhi_axpol_xml_last_generated', current_time('mysql'));
            }
        }
    }
}

// Rejestracja stylów dla importera
function mhi_register_importer_assets($hook)
{
    // Sprawdź czy jesteśmy na stronie wtyczki
    if (strpos($hook, 'multi-wholesale-integration') === false) {
        return;
    }

    // Zarejestruj i załącz styl CSS importera
    wp_register_style(
        'mhi-importer-css',
        plugin_dir_url(__FILE__) . 'admin/css/mhi-importer.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'admin/css/mhi-importer.css')
    );

    // Załącz CSS
    wp_enqueue_style('mhi-importer-css');
}
add_action('admin_enqueue_scripts', 'mhi_register_importer_assets');

// Inicjalizacja procesów w tle
function mhi_init_background_processes()
{
    // Sprawdź czy klasa już istnieje
    if (!class_exists('MHI_Background_Process')) {
        $background_process_file = plugin_dir_path(__FILE__) . 'includes/class-mhi-background-process.php';
        if (file_exists($background_process_file)) {
            require_once $background_process_file;
        } else {
            MHI_Logger::error('Nie znaleziono pliku klasy procesów w tle: ' . $background_process_file);
            return;
        }
    }

    // Inicjalizuj procesy w tle
    global $mhi_background_process;
    if (!isset($mhi_background_process)) {
        $mhi_background_process = new MHI_Background_Process();
        MHI_Logger::info('Zainicjalizowano procesy w tle dla importera');
    }
}

// Komunikat o braku Action Scheduler
function mhi_action_scheduler_missing_notice()
{
    ?>
    <div class="notice notice-error">
        <p><strong>Multi Hurtownie Integration:</strong>
            <?php _e('Action Scheduler nie jest dostępny. Import produktów nie będzie działać poprawnie. Upewnij się, że WooCommerce jest aktywowany.', 'multi-wholesale-integration'); ?>
        </p>
    </div>
    <?php
}

// Inicjalizacja obsługi AJAX
function mhi_init_ajax_handlers()
{
    // Załaduj klasę pomocniczą do obsługi AJAX
    require_once plugin_dir_path(__FILE__) . 'includes/class-mhi-debug-helper.php';

    // Inicjalizuj klasę pomocniczą
    if (class_exists('MHI_Debug_Helper')) {
        MHI_Debug_Helper::init();
    }

    // Rejestracja akcji AJAX
    add_action('wp_ajax_mhi_test_connection', 'mhi_test_connection_callback');
}

// Dodajemy funkcję do bezpośredniej obsługi błędów AJAX
function mhi_debug_ajax()
{
    if (defined('DOING_AJAX') && DOING_AJAX) {
        error_log('MHI Debug: Żądanie AJAX zostało wywołane: ' . $_SERVER['REQUEST_URI']);
        error_log('MHI Debug: Dane POST: ' . print_r($_POST, true));

        // Sprawdź czy to nasze żądanie AJAX
        if (
            isset($_POST['action']) && (
                $_POST['action'] === 'mhi_start_import' ||
                $_POST['action'] === 'mhi_stop_import' ||
                $_POST['action'] === 'mhi_get_import_status')
        ) {

            error_log('MHI Debug: Wykryto nasze żądanie AJAX: ' . $_POST['action']);

            // Sprawdź czy plik klasy importera istnieje
            $importer_path = MHI_PLUGIN_DIR . 'includes/class-mhi-importer.php';
            $alternate_path = WP_PLUGIN_DIR . '/mhi-product-importer/includes/class-mhi-importer.php';

            if (file_exists($importer_path)) {
                error_log('MHI Debug: Plik importera istnieje: ' . $importer_path);
            } elseif (file_exists($alternate_path)) {
                error_log('MHI Debug: Alternatywny plik importera istnieje: ' . $alternate_path);
            } else {
                error_log('MHI Debug: BŁĄD - Nie znaleziono pliku importera!');
            }
        }
    }
}

// Używamy najwyższego priorytetu dla obsługi AJAX
remove_action('init', 'mhi_init_ajax_handlers');
add_action('plugins_loaded', 'mhi_init_ajax_handlers', 1);
add_action('admin_init', 'mhi_debug_ajax');

// Usunięcie zadania cron przy dezaktywacji wtyczki
register_deactivation_hook(__FILE__, 'mhi_unschedule_xml_generation');
function mhi_unschedule_xml_generation()
{
    $timestamp = wp_next_scheduled('mhi_generate_woocommerce_xml');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'mhi_generate_woocommerce_xml');
    }
}

// Dodajemy testowy endpoint AJAX do diagnostyki
function mhi_test_connection_callback()
{
    error_log('MHI Debug: Test połączenia AJAX wywołany');
    wp_send_json_success(array('message' => 'Połączenie AJAX działa poprawnie'));
}
add_action('wp_ajax_mhi_test_connection', 'mhi_test_connection_callback');
add_action('wp_ajax_nopriv_mhi_test_connection', 'mhi_test_connection_callback');

/**
 * Sprawdza czy WooCommerce jest aktywny
 * 
 * @return bool True jeśli WooCommerce jest aktywny
 */
function mhi_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/**
 * Wyświetla powiadomienie, że WooCommerce jest wymagany
 */
function mhi_woocommerce_required_notice()
{
    ?>
    <div class="error">
        <p><?php _e('Plugin Multi-wholesale Integration wymaga aktywnego WooCommerce!', 'multi-wholesale-integration'); ?>
        </p>
    </div>
    <?php
}

/**
 * Sprawdza zależności wtyczki podczas aktywacji
 */
function mhi_check_dependencies()
{
    if (!mhi_is_woocommerce_active()) {
        add_action('admin_notices', 'mhi_woocommerce_required_notice');
    }
}
add_action('admin_init', 'mhi_check_dependencies');

// Obsługa formularzy pobierania plików z serwerów hurtowni
function mhi_handle_fetch_files_forms()
{
    // Obsługa formularza pobierania plików dla Malfini
    if (isset($_POST['mhi_malfini_fetch_files']) && isset($_POST['mhi_malfini_fetch_files_nonce']) && wp_verify_nonce($_POST['mhi_malfini_fetch_files_nonce'], 'mhi_malfini_fetch_files')) {
        // Załaduj klasę integracji
        if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php')) {
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php';
            $integration = new MHI_Hurtownia_1();

            try {
                $files = $integration->fetch_files();
                if ($files && !empty($files)) {
                    add_settings_error(
                        'mhi_malfini_fetch_files',
                        'mhi_files_fetched',
                        sprintf(__('Pobrano %d plików z serwera Malfini.', 'multi-wholesale-integration'), count($files)),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_malfini_fetch_files',
                        'mhi_files_fetch_error',
                        __('Nie udało się pobrać plików z serwera Malfini.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } catch (Exception $e) {
                add_settings_error(
                    'mhi_malfini_fetch_files',
                    'mhi_files_fetch_error',
                    sprintf(__('Błąd podczas pobierania plików z serwera Malfini: %s', 'multi-wholesale-integration'), $e->getMessage()),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'mhi_malfini_fetch_files',
                'mhi_integration_not_found',
                __('Nie znaleziono klasy integracji dla Malfini.', 'multi-wholesale-integration'),
                'error'
            );
        }
    }

    // Obsługa formularza pobierania plików dla Axpol
    if (isset($_POST['mhi_axpol_fetch_files']) && isset($_POST['mhi_axpol_fetch_files_nonce']) && wp_verify_nonce($_POST['mhi_axpol_fetch_files_nonce'], 'mhi_axpol_fetch_files')) {
        // Załaduj klasę integracji
        if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php')) {
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php';
            $integration = new MHI_Hurtownia_2();

            try {
                $files = $integration->fetch_files();
                if ($files && !empty($files)) {
                    add_settings_error(
                        'mhi_axpol_fetch_files',
                        'mhi_files_fetched',
                        sprintf(__('Pobrano %d plików z serwera AXPOL.', 'multi-wholesale-integration'), count($files)),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_axpol_fetch_files',
                        'mhi_files_fetch_error',
                        __('Nie udało się pobrać plików z serwera AXPOL.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } catch (Exception $e) {
                add_settings_error(
                    'mhi_axpol_fetch_files',
                    'mhi_files_fetch_error',
                    sprintf(__('Błąd podczas pobierania plików z serwera AXPOL: %s', 'multi-wholesale-integration'), $e->getMessage()),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'mhi_axpol_fetch_files',
                'mhi_integration_not_found',
                __('Nie znaleziono klasy integracji dla AXPOL.', 'multi-wholesale-integration'),
                'error'
            );
        }
    }

    // Obsługa formularza pobierania plików dla PAR
    if (isset($_POST['mhi_par_fetch_files']) && isset($_POST['mhi_par_fetch_files_nonce']) && wp_verify_nonce($_POST['mhi_par_fetch_files_nonce'], 'mhi_par_fetch_files')) {
        // Załaduj klasę integracji
        if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php')) {
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php';
            $integration = new MHI_Hurtownia_3();

            try {
                $files = $integration->fetch_files();
                if ($files) {
                    add_settings_error(
                        'mhi_par_fetch_files',
                        'mhi_files_fetched',
                        __('Pobrano pliki z serwera PAR.', 'multi-wholesale-integration'),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_par_fetch_files',
                        'mhi_files_fetch_error',
                        __('Nie udało się pobrać plików z serwera PAR.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } catch (Exception $e) {
                add_settings_error(
                    'mhi_par_fetch_files',
                    'mhi_files_fetch_error',
                    sprintf(__('Błąd podczas pobierania plików z serwera PAR: %s', 'multi-wholesale-integration'), $e->getMessage()),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'mhi_par_fetch_files',
                'mhi_integration_not_found',
                __('Nie znaleziono klasy integracji dla PAR.', 'multi-wholesale-integration'),
                'error'
            );
        }
    }

    // Obsługa formularza pobierania plików dla Inspirion
    if (isset($_POST['mhi_inspirion_fetch_files']) && isset($_POST['mhi_inspirion_fetch_files_nonce']) && wp_verify_nonce($_POST['mhi_inspirion_fetch_files_nonce'], 'mhi_inspirion_fetch_files')) {
        // Załaduj klasę integracji
        if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-4.php')) {
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-4.php';
            $integration = new MHI_Hurtownia_4();

            try {
                $files = $integration->fetch_files();
                if ($files) {
                    add_settings_error(
                        'mhi_inspirion_fetch_files',
                        'mhi_files_fetched',
                        __('Pobrano pliki z serwera Inspirion.', 'multi-wholesale-integration'),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_inspirion_fetch_files',
                        'mhi_files_fetch_error',
                        __('Nie udało się pobrać plików z serwera Inspirion.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } catch (Exception $e) {
                add_settings_error(
                    'mhi_inspirion_fetch_files',
                    'mhi_files_fetch_error',
                    sprintf(__('Błąd podczas pobierania plików z serwera Inspirion: %s', 'multi-wholesale-integration'), $e->getMessage()),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'mhi_inspirion_fetch_files',
                'mhi_integration_not_found',
                __('Nie znaleziono klasy integracji dla Inspirion.', 'multi-wholesale-integration'),
                'error'
            );
        }
    }

    // Obsługa formularza pobierania plików dla Macma
    if (isset($_POST['mhi_macma_fetch_files']) && isset($_POST['mhi_macma_fetch_files_nonce']) && wp_verify_nonce($_POST['mhi_macma_fetch_files_nonce'], 'mhi_macma_fetch_files')) {
        // Załaduj klasę integracji
        if (file_exists(MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-5.php')) {
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-5.php';
            $integration = new MHI_Hurtownia_5();

            try {
                $files = $integration->fetch_files();
                if ($files && !empty($files)) {
                    add_settings_error(
                        'mhi_macma_fetch_files',
                        'mhi_files_fetched',
                        sprintf(__('Pobrano %d plików z serwera Macma.', 'multi-wholesale-integration'), is_array($files) ? count($files) : 0),
                        'success'
                    );
                } else {
                    add_settings_error(
                        'mhi_macma_fetch_files',
                        'mhi_files_fetch_error',
                        __('Nie udało się pobrać plików z serwera Macma.', 'multi-wholesale-integration'),
                        'error'
                    );
                }
            } catch (Exception $e) {
                add_settings_error(
                    'mhi_macma_fetch_files',
                    'mhi_files_fetch_error',
                    sprintf(__('Błąd podczas pobierania plików z serwera Macma: %s', 'multi-wholesale-integration'), $e->getMessage()),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'mhi_macma_fetch_files',
                'mhi_integration_not_found',
                __('Nie znaleziono klasy integracji dla Macma.', 'multi-wholesale-integration'),
                'error'
            );
        }
    }
}
add_action('admin_init', 'mhi_handle_fetch_files_forms');
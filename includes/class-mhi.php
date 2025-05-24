<?php
/**
 * Główna klasa pluginu
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Główna klasa pluginu Multi Hurtownie Integration
 */
class MHI
{
    /**
     * Konstruktor
     */
    public function __construct()
    {
        // Definicje stałych
        $this->define_constants();

        // Inicjalizacja
        $this->init();
    }

    /**
     * Definiuje stałe pluginu
     */
    private function define_constants()
    {
        define('MHI_VERSION', '1.0.0');
        define('MHI_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        define('MHI_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
        define('MHI_PLUGIN_BASENAME', plugin_basename(dirname(dirname(__FILE__))) . '/multi-hurtownie-integration.php');
    }

    /**
     * Inicjalizacja pluginu
     */
    private function init()
    {
        // Ładowanie klas
        $this->load_classes();

        // Rejestracja haków
        $this->register_hooks();

        // Dodaj menu admina
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Dodaj akcje AJAX
        add_action('wp_ajax_mhi_start_import', array($this, 'ajax_start_import'));
        add_action('wp_ajax_mhi_get_import_status', array($this, 'ajax_get_import_status'));
        add_action('wp_ajax_mhi_stop_import', array($this, 'ajax_stop_import'));

        // Dodaj skrypty i style
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Obsługa harmonogramu importu (Action Scheduler)
        add_action('mhi_process_import_batch', array($this, 'process_import_batch'), 10, 3);
    }

    /**
     * Ładuje wymagane klasy
     */
    private function load_classes()
    {
        // Załaduj klasę loggera
        require_once MHI_PLUGIN_DIR . 'includes/class-mhi-logger.php';

        // Załaduj klasę importera
        require_once MHI_PLUGIN_DIR . 'includes/class-mhi-importer.php';

        // Załaduj klasę bezpośredniego importera
        require_once MHI_PLUGIN_DIR . 'includes/class-mhi-direct-importer.php';

        // Załaduj pozostałe klasy...
    }

    /**
     * Rejestruje hooki WordPress
     */
    private function register_hooks()
    {
        // Hook aktywacji
        register_activation_hook(MHI_PLUGIN_BASENAME, array($this, 'activate'));

        // Hook deaktywacji
        register_deactivation_hook(MHI_PLUGIN_BASENAME, array($this, 'deactivate'));
    }

    /**
     * Aktywacja pluginu
     */
    public function activate()
    {
        // Tworzenie katalogów dla plików XML
        $upload_dir = wp_upload_dir();
        $hurtownie_dir = trailingslashit($upload_dir['basedir']) . 'wholesale';

        if (!file_exists($hurtownie_dir)) {
            wp_mkdir_p($hurtownie_dir);
        }
    }

    /**
     * Deaktywacja pluginu
     */
    public function deactivate()
    {
        // Wyczyść zaplanowane zadania
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('mhi_process_import_batch');
        }
    }

    /**
     * Dodaje menu administratora
     */
    public function add_admin_menu()
    {
        // Główne menu
        add_menu_page(
            __('Multi Hurtownie Integration', 'multi-hurtownie-integration'),
            __('Multi Hurtownie', 'multi-hurtownie-integration'),
            'manage_options',
            'multi-hurtownie-integration',
            array($this, 'admin_page'),
            'dashicons-store',
            56
        );

        // Podmenu - Import produktów
        add_submenu_page(
            'multi-hurtownie-integration',
            __('Import produktów', 'multi-hurtownie-integration'),
            __('Import produktów', 'multi-hurtownie-integration'),
            'manage_options',
            'mhi-import',
            array($this, 'import_page')
        );

        // Podmenu - Ustawienia
        add_submenu_page(
            'multi-hurtownie-integration',
            __('Ustawienia', 'multi-hurtownie-integration'),
            __('Ustawienia', 'multi-hurtownie-integration'),
            'manage_options',
            'mhi-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Wyświetla główną stronę administracyjną
     */
    public function admin_page()
    {
        require_once MHI_PLUGIN_DIR . 'admin/views/admin-page.php';
    }

    /**
     * Wyświetla stronę importu produktów
     */
    public function import_page()
    {
        // Sprawdź czy wybrano panel importu
        if (isset($_GET['panel']) && $_GET['panel'] === 'import' && isset($_GET['supplier'])) {
            // Wyświetl panel importu dla konkretnej hurtowni
            require_once MHI_PLUGIN_DIR . 'admin/views/import-panel.php';
        } else {
            // Wyświetl stronę wyboru importera
            require_once MHI_PLUGIN_DIR . 'admin/views/import-page.php';
        }
    }

    /**
     * Wyświetla stronę ustawień
     */
    public function settings_page()
    {
        require_once MHI_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Dodaje skrypty i style administratora
     *
     * @param string $hook Bieżący hook admina.
     */
    public function enqueue_admin_scripts($hook)
    {
        // Dodaj style i skrypty tylko na stronach pluginu
        if (strpos($hook, 'multi-hurtownie-integration') === false && strpos($hook, 'mhi-') === false) {
            return;
        }

        // Style
        wp_enqueue_style('mhi-admin-style', MHI_PLUGIN_URL . 'admin/css/admin.css', array(), MHI_VERSION);

        // Skrypty
        wp_enqueue_script('mhi-admin-script', MHI_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), MHI_VERSION, true);

        // Lokalizacja skryptu
        wp_localize_script('mhi-admin-script', 'mhi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhi_ajax_nonce'),
            'import_running' => __('Import w trakcie...', 'multi-hurtownie-integration'),
            'import_completed' => __('Import zakończony', 'multi-hurtownie-integration'),
            'import_failed' => __('Błąd importu', 'multi-hurtownie-integration'),
            'confirm_stop' => __('Czy na pewno chcesz zatrzymać import? Może to spowodować niespójność danych.', 'multi-hurtownie-integration')
        ));
    }

    /**
     * Obsługuje AJAX - rozpoczęcie importu
     */
    public function ajax_start_import()
    {
        // Sprawdź nonce
        check_ajax_referer('mhi_ajax_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nie masz wystarczających uprawnień.', 'multi-hurtownie-integration'));
        }

        // Pobierz dane
        $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';

        if (empty($supplier)) {
            wp_send_json_error(__('Brak wymaganego parametru: dostawca.', 'multi-hurtownie-integration'));
        }

        // Uruchom import bezpośredni
        try {
            $importer = new MHI_Direct_Importer($supplier);
            $result = $importer->import();

            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'status' => 'completed'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'status' => 'error'
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'status' => 'error'
            ));
        }
    }

    /**
     * Obsługuje AJAX - pobieranie statusu importu
     */
    public function ajax_get_import_status()
    {
        // Sprawdź nonce
        check_ajax_referer('mhi_ajax_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nie masz wystarczających uprawnień.', 'multi-hurtownie-integration'));
        }

        // Pobierz dane
        $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';

        if (empty($supplier)) {
            wp_send_json_error(__('Brak wymaganego parametru: dostawca.', 'multi-hurtownie-integration'));
        }

        // Pobierz status importu
        $status = get_option('mhi_import_status_' . $supplier, array());

        if (empty($status)) {
            $status = array(
                'status' => 'idle',
                'message' => __('Import nie został jeszcze rozpoczęty.', 'multi-hurtownie-integration'),
                'percent' => 0
            );
        }

        wp_send_json_success($status);
    }

    /**
     * Obsługuje AJAX - zatrzymanie importu
     */
    public function ajax_stop_import()
    {
        // Sprawdź nonce
        check_ajax_referer('mhi_ajax_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nie masz wystarczających uprawnień.', 'multi-hurtownie-integration'));
        }

        // Pobierz dane
        $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';

        if (empty($supplier)) {
            wp_send_json_error(__('Brak wymaganego parametru: dostawca.', 'multi-hurtownie-integration'));
        }

        // Ustaw flagę zatrzymania importu
        update_option('mhi_stop_import_' . $supplier, true);

        // Pobierz importer i zatrzymaj go
        $importer = new MHI_Importer($supplier);
        $result = $importer->stop_import();

        if ($result) {
            wp_send_json_success(__('Żądanie zatrzymania importu zostało wysłane. Import zostanie zatrzymany przy następnym kroku.', 'multi-hurtownie-integration'));
        } else {
            wp_send_json_error(__('Nie udało się zatrzymać importu.', 'multi-hurtownie-integration'));
        }
    }

    /**
     * Obsługuje przetwarzanie partii importu
     *
     * @param string $import_id ID importu.
     * @param string $supplier_name Nazwa dostawcy.
     * @param int $batch_number Numer partii.
     */
    public function process_import_batch($import_id, $supplier_name, $batch_number)
    {
        // Załaduj importer i przetwórz partię
        $importer = new MHI_Importer($supplier_name);
        $importer->process_batch($batch_number);
    }
}
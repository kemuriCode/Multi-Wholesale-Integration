<?php
/**
 * Główna klasa wtyczki Multi Hurtownie Integration.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Plugin
 * 
 * Główna klasa inicjująca wtyczkę.
 */
class MHI_Plugin
{
    /**
     * Lista zarejestrowanych integracji z hurtowniami.
     *
     * @var array
     */
    private $integrations = array();

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        // Ładowanie zależności
        $this->load_dependencies();
    }

    /**
     * Ładowanie zależności wtyczki
     */
    private function load_dependencies()
    {
        // Ładowanie interfejsu dla integracji
        require_once MHI_PLUGIN_DIR . 'includes/interfaces/interface-mhi-integration.php';

        // Ładowanie klasy admin
        require_once MHI_PLUGIN_DIR . 'admin/class-mhi-admin.php';

        // Ładowanie klasy cron
        require_once MHI_PLUGIN_DIR . 'includes/class-mhi-cron.php';

        // Ładowanie klas integracji z hurtowniami
        require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-1.php';
        require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-2.php';
        require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-3.php';
        require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-4.php';
        require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-hurtownia-5.php';
        require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-par.php';
    }

    /**
     * Uruchamia wtyczkę
     */
    public function run()
    {
        // Rejestracja skryptów i styli
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Inicjalizacja panelu administracyjnego
        $admin = new MHI_Admin();
        $admin->init();

        // Inicjalizacja zadań cron
        $cron = new MHI_Cron();
        $cron->init();

        // Obsługa AJAX dla pobierania statusu pobierania
        add_action('wp_ajax_mhi_get_download_status', array($this, 'ajax_get_download_status'));

        // Obsługa AJAX dla czyszczenia mediów
        add_action('wp_ajax_mhi_cleanup_missing_media', array($this, 'ajax_cleanup_missing_media'));
    }

    /**
     * Rejestracja skryptów i styli dla panelu admin
     */
    public function enqueue_admin_assets($hook)
    {
        // Wczytuj zasoby tylko na stronie wtyczki
        if ('settings_page_multi-hurtownie-integration' !== $hook) {
            return;
        }

        // Style
        wp_enqueue_style(
            'mhi-admin-css',
            MHI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MHI_VERSION
        );

        // Dodaj własne zmienne CSS z kolorami motywu
        $custom_css = '
            :root {
                --mhi-theme-color: ' . get_user_option('admin_color', get_current_user_id()) . ';
            }
        ';
        wp_add_inline_style('mhi-admin-css', $custom_css);

        // Skrypty
        wp_enqueue_script(
            'mhi-admin-js',
            MHI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MHI_VERSION,
            true
        );

        // Przekazanie danych do skryptu
        wp_localize_script('mhi-admin-js', 'mhiData', array(
            'nonce' => wp_create_nonce('mhi-ajax-nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ));
    }

    /**
     * Ładuje wszystkie wymagane klasy.
     */
    private function load_classes()
    {
        // Załadowanie klasy obsługującej logowanie
        require_once MHI_PLUGIN_DIR . 'includes/class-mhi-logger.php';

        // Załadowanie klasy obsługującej zadania cron
        require_once MHI_PLUGIN_DIR . 'includes/class-mhi-cron.php';

        // Załadowanie klasy panelu administracyjnego
        require_once MHI_PLUGIN_DIR . 'admin/class-mhi-admin.php';

        // Inicjalizacja panelu administracyjnego
        $admin = new MHI_Admin();
        $admin->init();

        // Inicjalizacja zadań cron
        $cron = new MHI_Cron();
        $cron->init();

        // Zarejestruj integracje
        $this->register_integrations();
    }

    /**
     * Rejestruje wszystkie integracje z hurtowniami.
     */
    private function register_integrations()
    {
        // Ścieżka do katalogu z integracjami
        $integrations_dir = MHI_PLUGIN_DIR . 'integrations/';

        // Sprawdź, czy istnieje ścieżka do integracji
        if (!file_exists($integrations_dir)) {
            return;
        }

        // Automatycznie załaduj wszystkie dostępne integracje
        $integration_files = glob($integrations_dir . 'class-mhi-hurtownia-*.php');

        foreach ($integration_files as $file) {
            // Pobierz nazwę pliku bez ścieżki i rozszerzenia
            $filename = basename($file, '.php');

            // Przekształć nazwę pliku na nazwę klasy
            $class_name = str_replace('class-', '', $filename);
            $class_name = str_replace('-', '_', $class_name);
            $class_name = strtoupper(substr($class_name, 0, 4)) . substr($class_name, 4);
            $class_name = ucwords($class_name, '_');

            // Sprawdź, czy klasa istnieje i implementuje odpowiedni interfejs
            if (class_exists($class_name)) {
                $integration = new $class_name();

                if ($integration instanceof MHI_Integration_Interface) {
                    $this->integrations[$integration->get_name()] = $integration;
                }
            }
        }
    }

    /**
     * Rejestruje zaplanowane zadania cron.
     */
    private function register_cron_tasks()
    {
        // Dodanie hooka do obsługi zadań cron
        add_action('mhi_cron_fetch_files', array($this, 'cron_fetch_files'));
    }

    /**
     * Pobiera pliki z wszystkich zarejestrowanych hurtowni.
     */
    public function cron_fetch_files()
    {
        foreach ($this->integrations as $integration) {
            try {
                if ($integration->validate_credentials() && $integration->connect()) {
                    $files = $integration->fetch_files();
                    $integration->process_files($files);
                }
            } catch (Exception $e) {
                // Logowanie błędu
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error('Błąd podczas pobierania plików z hurtowni ' . $integration->get_name() . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Dodaje menu administracyjne.
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Multi Hurtownie Integration', 'multi-hurtownie-integration'),
            __('Multi Hurtownie', 'multi-hurtownie-integration'),
            'manage_options',
            'multi-hurtownie-integration',
            array('MHI_Admin', 'render_settings_page'),
            'dashicons-cart',
            56
        );
    }

    /**
     * Pobiera wszystkie zarejestrowane integracje.
     *
     * @return array Lista zarejestrowanych integracji.
     */
    public function get_integrations()
    {
        return $this->integrations;
    }

    /**
     * Inicjalizuje wtyczkę
     */
    public function init()
    {
        // Ładowanie dodatkowych klas
        $this->load_dependencies();

        // Rejestracja hooka aktywacji
        register_activation_hook(MHI_PLUGIN_FILE, array($this, 'activate'));

        // Rejestracja hooka deaktywacji
        register_deactivation_hook(MHI_PLUGIN_FILE, array($this, 'deactivate'));

        // Inicjalizacja panelu admina
        add_action('init', array($this, 'init_admin'));

        // Inicjalizuje zadania cron
        $this->init_cron();
    }

    /**
     * Obsługuje żądania AJAX pobierania statusu pobierania
     */
    public function ajax_get_download_status()
    {
        check_ajax_referer('mhi_ajax_nonce', 'security');

        $status = get_option('mhi_download_status', array());
        wp_send_json_success($status);
        wp_die();
    }

    /**
     * Czyści puste wpisy mediów poprzez AJAX
     */
    public function ajax_cleanup_missing_media()
    {
        // Sprawdź uprawnienia użytkownika
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Brak uprawnień do wykonania tej operacji.', 'multi-hurtownie-integration')]);
            return;
        }

        // Sprawdź nonce
        check_ajax_referer('mhi_cleanup_media_nonce', 'security');

        // Limit czasu wykonania skryptu (300 sekund = 5 minut)
        set_time_limit(300);

        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');

        // Pobierz wszystkie załączniki w porcjach dla lepszej wydajności
        $paged = 1;
        $per_page = 100;
        $deleted_count = 0;

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

            // Przejdź do następnej strony
            $paged++;

        } while (!empty($attachments));

        wp_send_json_success([
            'message' => sprintf(
                __('Operacja zakończona pomyślnie. Usunięto %d pustych wpisów medialnych.', 'multi-hurtownie-integration'),
                $deleted_count
            )
        ]);
    }

    /**
     * Inicjalizuje zadania cron
     */
    private function init_cron()
    {
        $cron = new MHI_Cron();

        // Dodaj filtr do dodania własnego harmonogramu
        add_filter('cron_schedules', array($cron, 'add_cron_interval'));

        // Dodaj hooki dla zdarzeń cron
        add_action('mhi_cron_fetch_all', array($cron, 'fetch_all_files'));

        // Dodaj hooki dla zdarzeń cron poszczególnych hurtowni
        $hurtownie = array('hurtownia_1', 'hurtownia_2', 'hurtownia_3', 'hurtownia_4', 'hurtownia_5');
        foreach ($hurtownie as $hurtownia) {
            add_action('mhi_cron_fetch_' . $hurtownia, array($cron, 'fetch_hurtownia_files'), 10, 1);
        }

        // Dodaj zdarzenie do czyszczenia mediów
        add_action('mhi_media_cleanup_event', array($cron, 'cleanup_empty_media'));

        // Zaplanuj zadania cron
        $cron->schedule_events();

        // Zaplanuj zadanie czyszczenia mediów
        $cron->schedule_media_cleanup();
    }
}
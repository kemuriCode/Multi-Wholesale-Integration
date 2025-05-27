<?php
/**
 * Klasa integracji z hurtownią PAR (API).
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Par
 * 
 * Obsługuje integrację z hurtownią PAR przez API.
 */
class MHI_Par implements MHI_Integration_Interface
{
    /**
     * Nazwa integracji.
     *
     * @var string
     */
    private $name = 'par';

    /**
     * Dane konfiguracyjne.
     *
     * @var array
     */
    private $config;

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        // Inicjalizacja konfiguracji
        $this->config = array(
            'api_base_url' => 'https://www.par.com.pl/api/',
            'api_username' => get_option('mhi_hurtownia_3_api_username', ''),
            'api_password' => get_option('mhi_hurtownia_3_api_password', ''),
            'endpoints' => array(
                'products' => 'products',
                'categories' => 'categories',
                'technics' => 'technics',
                'stocks' => 'stocks'
            ),
            'enabled' => get_option('mhi_hurtownia_3_enabled', 1),
        );
    }

    /**
     * Nawiązuje połączenie z hurtownią przez API.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    public function connect()
    {
        // Sprawdź, czy hurtownia jest włączona
        if (!$this->config['enabled']) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona.');
            }
            return false;
        }

        // Sprawdź, czy dane są poprawne
        if (!$this->validate_credentials()) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowe dane uwierzytelniające dla hurtowni ' . $this->name);
                MHI_Logger::error('Username: ' . ($this->config['api_username'] ? 'USTAWIONE' : 'BRAK'));
                MHI_Logger::error('Password: ' . ($this->config['api_password'] ? 'USTAWIONE' : 'BRAK'));
            }
            return false;
        }

        // Test połączenia z API
        $test_url = $this->config['api_base_url'] . $this->config['endpoints']['products'];

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Próba połączenia z: ' . $test_url);
            MHI_Logger::info('PAR - Username: ' . $this->config['api_username']);
        }

        $args = array(
            'timeout' => 30, // Zmniejszam timeout do 30 sekund dla szybszego debugowania
            'sslverify' => false, // Wyłączam weryfikację SSL
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->config['api_username'] . ':' . $this->config['api_password']),
                'Accept' => 'application/xml',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        );

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Wysyłanie żądania HTTP...');
        }

        $response = wp_remote_get($test_url, $args);

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Błąd HTTP: ' . $response->get_error_message());
                MHI_Logger::error('PAR - UWAGA: Sprawdź czy Twój adres IP jest na białej liście u PAR!');

                // Pobierz zewnętrzny adres IP
                $external_ip = $this->get_external_ip();
                if ($external_ip) {
                    MHI_Logger::error('PAR - Twój zewnętrzny adres IP: ' . $external_ip);
                    MHI_Logger::error('PAR - Skontaktuj się z PAR aby dodać ten IP do białej listy');
                }
            }
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Nieprawidłowa odpowiedź API. Kod HTTP: ' . $http_code);
                $response_body = wp_remote_retrieve_body($response);
                MHI_Logger::error('PAR - Treść odpowiedzi: ' . substr($response_body, 0, 500));
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Połączono z API pomyślnie');
        }
        return true;
    }

    /**
     * Pobiera zewnętrzny adres IP serwera.
     *
     * @return string|false Adres IP lub false w przypadku błędu.
     */
    private function get_external_ip()
    {
        $ip_services = [
            'https://api.ipify.org',
            'https://icanhazip.com',
            'https://ipecho.net/plain'
        ];

        foreach ($ip_services as $service) {
            $response = wp_remote_get($service, array('timeout' => 10));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $ip = trim(wp_remote_retrieve_body($response));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return false;
    }

    /**
     * Pobiera pliki z hurtowni i zapisuje je w odpowiednim folderze.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_files()
    {
        $files = array();

        if (!$this->connect()) {
            return $files;
        }

        // Przygotuj katalog docelowy
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name;

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        if (!is_writable($target_dir)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Katalog docelowy nie ma uprawnień do zapisu: ' . $target_dir);
            }
            return $files;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Rozpoczynam pobieranie plików z API PAR');
        }

        // Pobierz dane z każdego endpointu
        foreach ($this->config['endpoints'] as $name => $endpoint) {
            $file_info = $this->download_api_data($endpoint, $name, $target_dir);
            if ($file_info) {
                $files[] = $file_info;
            }
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Zakończono pobieranie plików z API PAR. Pobrano ' . count($files) . ' plików.');
        }

        return $files;
    }

    /**
     * Pobiera dane z API i zapisuje jako plik XML.
     *
     * @param string $endpoint Endpoint API
     * @param string $name Nazwa pliku
     * @param string $target_dir Katalog docelowy
     * @return array|false Informacje o pliku lub false w przypadku błędu
     */
    private function download_api_data($endpoint, $name, $target_dir)
    {
        $url = $this->config['api_base_url'] . $endpoint;
        $filename = $name . '.xml';
        $local_path = $target_dir . '/' . $filename;

        $args = array(
            'timeout' => 300,
            'sslverify' => false, // Wyłączam weryfikację SSL
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->config['api_username'] . ':' . $this->config['api_password']),
                'Accept' => 'application/xml',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas pobierania ' . $filename . ': ' . $response->get_error_message());
            }
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowa odpowiedź podczas pobierania ' . $filename . '. Kod HTTP: ' . $http_code);
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Pusta odpowiedź podczas pobierania ' . $filename);
            }
            return false;
        }

        // Zapisz plik
        $result = file_put_contents($local_path, $body);
        if ($result === false) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nie udało się zapisać pliku ' . $filename);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Pobrano plik: ' . $filename . ' z API PAR');
        }

        return array(
            'filename' => $filename,
            'local_path' => $local_path,
            'remote_path' => $url,
            'size' => filesize($local_path),
            'timestamp' => time()
        );
    }

    /**
     * Waliduje dane dostępowe do hurtowni.
     *
     * @return bool True jeśli dane są poprawne, false w przeciwnym razie.
     */
    public function validate_credentials()
    {
        return !empty($this->config['api_username']) && !empty($this->config['api_password']);
    }

    /**
     * Przetwarza pobrane pliki.
     *
     * @param array $files Lista plików do przetworzenia.
     * @return bool True w przypadku powodzenia, false w przypadku błędu.
     */
    public function process_files($files)
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Przetworzono ' . count($files) . ' plików XML z hurtowni ' . $this->name);
        }
        return true;
    }

    /**
     * Zwraca nazwę integracji.
     *
     * @return string Nazwa integracji.
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * Anuluje pobieranie plików.
     *
     * @return void
     */
    public function cancel_download()
    {
        update_option('mhi_download_status_' . $this->name, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Anulowano pobieranie dla hurtowni ' . $this->name);
        }
    }

    /**
     * Pobiera pliki zdjęć z hurtowni.
     *
     * @param int $batch_number Numer partii do pobrania
     * @param string $img_dir Katalog ze zdjęciami na serwerze
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_images($batch_number = 1, $img_dir = '/images')
    {
        // PAR korzysta z adresów URL zdjęć w XML
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Hurtownia ' . $this->name . ' nie obsługuje pobierania zdjęć - używa adresów URL');
        }
        return array();
    }

    /**
     * Importuje produkty z hurtowni do WooCommerce.
     *
     * @return string Informacja o wyniku importu.
     * @throws Exception W przypadku błędu podczas importu.
     */
    public function import_products_to_woocommerce()
    {
        // Sprawdź czy plik XML został wygenerowany
        if (!$this->generate_woocommerce_xml()) {
            throw new Exception(__('Nie udało się wygenerować pliku XML do importu.', 'multi-hurtownie-integration'));
        }

        // Przekieruj do strony importu
        $import_url = admin_url('admin.php?page=mhi-product-import&supplier=' . $this->name);
        wp_redirect($import_url);
        exit;
    }

    /**
     * Generuje plik XML do importu do WooCommerce.
     *
     * @return bool True jeśli plik został wygenerowany pomyślnie, false w przeciwnym razie.
     */
    public function generate_woocommerce_xml()
    {
        try {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info("Rozpoczęcie generowania pliku XML dla WooCommerce (hurtownia {$this->name})");
            }

            // Sprawdź czy katalog uploads/wholesale/{$this->name} istnieje
            $upload_dir = wp_upload_dir();
            $hurtownia_dir = $upload_dir['basedir'] . "/wholesale/{$this->name}/";

            if (!file_exists($hurtownia_dir)) {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error("Błąd: Katalog {$hurtownia_dir} nie istnieje.");
                }
                return false;
            }

            // Utwórz generator XML
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-par-wc-xml-generator.php';
            $generator = new MHI_Par_WC_XML_Generator($hurtownia_dir);

            // Generuj plik XML
            $result = $generator->generate_woocommerce_xml();

            if ($result) {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info("Pomyślnie wygenerowano plik XML dla hurtowni {$this->name}");
                }
                return true;
            } else {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error("Błąd: Nie udało się wygenerować pliku XML dla hurtowni {$this->name}.");
                }
                return false;
            }
        } catch (Exception $e) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error("Wyjątek podczas generowania XML dla hurtowni {$this->name}: " . $e->getMessage());
            }
            return false;
        }
    }
}
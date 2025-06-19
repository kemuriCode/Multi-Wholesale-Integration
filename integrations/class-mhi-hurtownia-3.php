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
 * Klasa MHI_Hurtownia_3
 * 
 * Obsługuje integrację z hurtownią PAR przez API.
 * Klasa przepisana na nowo z zaawansowanymi funkcjami pobierania i diagnostyki.
 */
class MHI_Hurtownia_3 implements MHI_Integration_Interface
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
        // Inicjalizacja konfiguracji - PAR API
        $this->config = array(
            'api_base_url' => 'https://www.par.com.pl/api/',
            'api_username' => get_option('mhi_hurtownia_3_api_username', 'dmurawski@promo-mix.pl'),
            'api_password' => get_option('mhi_hurtownia_3_api_password', '#Reklamy!1'),
            'endpoints' => array(
                'products' => 'products',
                'categories' => 'categories',
                'stocks' => 'stocks'  // technics - brak uprawnień (403)
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
                MHI_Logger::info('Hurtownia PAR jest wyłączona.');
            }
            return false;
        }

        // Sprawdź, czy dane są poprawne
        if (!$this->validate_credentials()) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowe dane uwierzytelniające dla PAR');
                MHI_Logger::error('Username: ' . ($this->config['api_username'] ? 'USTAWIONE' : 'BRAK'));
                MHI_Logger::error('Password: ' . ($this->config['api_password'] ? 'USTAWIONE' : 'BRAK'));
            }
            return false;
        }

        // Test połączenia z API - użyj szybkiego endpointu categories
        $test_url = 'https://www.par.com.pl/api/categories';

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Test połączenia: ' . $test_url);
            MHI_Logger::info('PAR - Username: ' . $this->config['api_username']);
        }

        $args = array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->config['api_username'] . ':' . $this->config['api_password']),
                'Accept' => 'application/xml',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        );

        $response = wp_remote_get($test_url, $args);

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Błąd HTTP: ' . $response->get_error_message());
            }
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code === 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('PAR - ✅ Połączenie z API działa!');
            }
            return true;
        } else {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Nieprawidłowa odpowiedź API. Kod HTTP: ' . $http_code);
                if ($http_code === 401) {
                    MHI_Logger::error('PAR - Błąd uwierzytelniania (401) - sprawdź login i hasło');
                } elseif ($http_code === 403) {
                    MHI_Logger::error('PAR - Brak uprawnień (403) - sprawdź uprawnienia konta');
                }
                $response_body = wp_remote_retrieve_body($response);
                MHI_Logger::error('PAR - Treść odpowiedzi: ' . substr($response_body, 0, 200));
            }
            return false;
        }
    }



    /**
     * Pobiera pliki z hurtowni przez API PAR.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_files()
    {
        $files = array();
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name;

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Rozpoczynam pobieranie plików przez API');
        }

        if (!$this->connect()) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Nie udało się połączyć z API');
            }
            return $files;
        }

        if (!is_writable($target_dir)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Katalog docelowy nie ma uprawnień do zapisu: ' . $target_dir);
            }
            return $files;
        }

        // Pobierz dane z każdego endpointu (porządek: małe -> duże pliki)
        $endpoints_order = array(
            'categories' => 'kategorie',
            'stocks' => 'stan_magazynowy',
            'products' => 'lista_produktow'   // Największy plik na końcu
        );

        foreach ($endpoints_order as $endpoint => $name) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info("PAR - Pobieranie endpoint: $endpoint");
            }

            $file_info = $this->download_api_data($endpoint, $name, $target_dir);
            if ($file_info) {
                $files[] = $file_info;
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info("PAR - Sukces: $name.xml (" . $file_info['size'] . " bajtów)");
                }
            } else {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error("PAR - Błąd pobierania: $name.xml");
                }
            }
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Zakończono pobieranie plików. Pobrano ' . count($files) . ' plików.');
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

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Pobieranie danych z: ' . $url);
        }

        // Zwiększ timeout dla dużych plików (szczególnie products)
        $timeout = ($endpoint === 'products') ? 600 : 120; // 10 minut dla products, 2 minuty dla reszty

        $args = array(
            'timeout' => $timeout,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->config['api_username'] . ':' . $this->config['api_password']),
                'Accept' => 'application/xml',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Błąd podczas pobierania ' . $filename . ': ' . $response->get_error_message());
            }
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Nieprawidłowa odpowiedź podczas pobierania ' . $filename . '. Kod HTTP: ' . $http_code);
                $response_body = wp_remote_retrieve_body($response);
                MHI_Logger::error('PAR - Treść odpowiedzi: ' . substr($response_body, 0, 500));
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Pusta odpowiedź podczas pobierania ' . $filename);
            }
            return false;
        }

        // Zapisz plik
        $result = file_put_contents($local_path, $body);
        if ($result === false) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('PAR - Nie udało się zapisać pliku ' . $filename);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Pobrano plik: ' . $filename . ' (' . strlen($body) . ' bajtów)');
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
            MHI_Logger::info('PAR - Przetworzono ' . count($files) . ' plików XML');
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
        update_option('mhi_download_status_' . $this->name, __('Anulowano pobieranie.', 'multi-wholesale-integration'));
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('PAR - Anulowano pobieranie');
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
            MHI_Logger::info('PAR - Nie obsługuje pobierania zdjęć - używa adresów URL');
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
            throw new Exception(__('Nie udało się wygenerować pliku XML do importu.', 'multi-wholesale-integration'));
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
                MHI_Logger::info("PAR - Rozpoczęcie generowania pliku XML dla WooCommerce");
            }

            // Sprawdź czy katalog uploads/wholesale/par istnieje
            $upload_dir = wp_upload_dir();
            $hurtownia_dir = $upload_dir['basedir'] . "/wholesale/{$this->name}/";

            if (!file_exists($hurtownia_dir)) {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error("PAR - Błąd: Katalog {$hurtownia_dir} nie istnieje.");
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
                    MHI_Logger::info("PAR - Pomyślnie wygenerowano plik XML");
                }
                return true;
            } else {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error("PAR - Błąd: Nie udało się wygenerować pliku XML.");
                }
                return false;
            }
        } catch (Exception $e) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error("PAR - Wyjątek podczas generowania XML: " . $e->getMessage());
            }
            return false;
        }
    }
}
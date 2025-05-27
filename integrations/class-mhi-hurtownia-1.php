<?php
/**
 * Klasa integracji z hurtownią Malfini (REST API v4).
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Hurtownia_1
 * 
 * Obsługuje integrację z hurtownią Malfini przez REST API v4.
 */
class MHI_Hurtownia_1 implements MHI_Integration_Interface
{
    /**
     * Nazwa integracji.
     *
     * @var string
     */
    private $name = 'malfini';

    /**
     * Dane konfiguracyjne.
     *
     * @var array
     */
    private $config;

    /**
     * Token autoryzacyjny.
     *
     * @var string
     */
    private $auth_token = '';

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        // Inicjalizacja konfiguracji - Malfini REST API v4
        $this->config = array(
            'enabled' => get_option('mhi_hurtownia_1_enabled', 1), // Domyślnie włączona
            'api_base_url' => 'https://api.malfini.com/api/v4/',
            'api_username' => get_option('mhi_hurtownia_1_api_username', 'dmurawski@promo-mix.pl'),
            'api_password' => get_option('mhi_hurtownia_1_api_password', 'mul4eQ'),
            'endpoints' => array(
                'login' => 'api-auth/login',
                'products' => 'product',
                'availabilities' => 'product/availabilities',
                'prices' => 'product/prices'
            )
        );
    }

    /**
     * Nawiązuje połączenie z hurtownią przez REST API.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    public function connect()
    {
        // Sprawdź, czy hurtownia jest włączona
        if (!$this->config['enabled']) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona.');
            } else {
                error_log('MHI: Hurtownia ' . $this->name . ' jest wyłączona (MHI_Logger nie istnieje)');
            }
            return false;
        }

        // Sprawdź czy dane API są skonfigurowane
        if (empty($this->config['api_username']) || empty($this->config['api_password'])) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Brakuje danych konfiguracyjnych dla połączenia z API Malfini');
            } else {
                error_log('MHI ERROR: Brakuje danych konfiguracyjnych dla połączenia z API Malfini');
            }
            return false;
        }

        // Zaloguj się do API i pobierz token
        $login_response = $this->login();
        if (is_wp_error($login_response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas logowania do API Malfini: ' . $login_response->get_error_message());
            } else {
                error_log('MHI ERROR: Błąd podczas logowania do API Malfini: ' . $login_response->get_error_message());
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($login_response);
        if ($response_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowa odpowiedź podczas logowania do API Malfini. Kod HTTP: ' . $response_code);
            } else {
                error_log('MHI ERROR: Nieprawidłowa odpowiedź podczas logowania do API Malfini. Kod HTTP: ' . $response_code);
            }
            return false;
        }

        $response_body = wp_remote_retrieve_body($login_response);
        $login_data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($login_data['access_token'])) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowa odpowiedź JSON podczas logowania do API Malfini. Dostępne klucze: ' . (is_array($login_data) ? implode(', ', array_keys($login_data)) : 'brak'));
            } else {
                error_log('MHI ERROR: Nieprawidłowa odpowiedź JSON podczas logowania do API Malfini. Dostępne klucze: ' . (is_array($login_data) ? implode(', ', array_keys($login_data)) : 'brak'));
            }
            return false;
        }

        $this->auth_token = $login_data['access_token'];

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Pomyślnie połączono z API Malfini i otrzymano token autoryzacyjny');
        } else {
            error_log('MHI: Pomyślnie połączono z API Malfini i otrzymano token autoryzacyjny');
        }
        return true;
    }

    /**
     * Loguje się do API Malfini i pobiera token autoryzacyjny.
     * 
     * @return array|WP_Error Odpowiedź API lub obiekt błędu.
     */
    private function login()
    {
        $login_url = $this->config['api_base_url'] . $this->config['endpoints']['login'];

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Malfini - Próba logowania do: ' . $login_url);
        } else {
            error_log('MHI: Malfini - Próba logowania do: ' . $login_url);
        }

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => json_encode(array(
                'username' => $this->config['api_username'],
                'password' => $this->config['api_password'],
            )),
        );

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Malfini - Wysyłanie żądania logowania z username: ' . $this->config['api_username']);
        } else {
            error_log('MHI: Malfini - Wysyłanie żądania logowania z username: ' . $this->config['api_username']);
        }

        $response = wp_remote_post($login_url, $args);

        // Loguj odpowiedź
        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Malfini - Błąd HTTP: ' . $response->get_error_message());
            } else {
                error_log('MHI ERROR: Malfini - Błąd HTTP: ' . $response->get_error_message());
            }
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Malfini - Kod odpowiedzi: ' . $response_code);
                MHI_Logger::info('Malfini - Treść odpowiedzi: ' . substr($response_body, 0, 500) . (strlen($response_body) > 500 ? '...' : ''));
            } else {
                error_log('MHI: Malfini - Kod odpowiedzi: ' . $response_code);
                error_log('MHI: Malfini - Treść odpowiedzi: ' . substr($response_body, 0, 500) . (strlen($response_body) > 500 ? '...' : ''));
            }
        }

        return $response;
    }

    /**
     * Wykonuje żądanie do API z autoryzacją Bearer token.
     *
     * @param string $endpoint Endpoint API.
     * @param array  $params   Parametry żądania.
     * @param string $method   Metoda żądania (GET, POST).
     * @return array|WP_Error Odpowiedź API lub obiekt błędu.
     */
    private function make_api_request($endpoint, $params = array(), $method = 'GET')
    {
        // Pełny URL do API
        $url = $this->config['api_base_url'] . $endpoint;

        // Parametry żądania
        $args = array(
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        );

        // Dodaj token autoryzacyjny Bearer
        if (!empty($this->auth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->auth_token;
        }

        // Wykonaj żądanie w zależności od metody
        if ('POST' === $method) {
            $args['body'] = json_encode($params);
            $response = wp_remote_post($url, $args);
        } else {
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
            $response = wp_remote_get($url, $args);
        }

        return $response;
    }

    /**
     * Pobiera pliki z hurtowni i zapisuje je w odpowiednim folderze.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_files()
    {
        $files = array();

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('=== ROZPOCZĘCIE POBIERANIA PLIKÓW MALFINI ===');
        } else {
            error_log('MHI: === ROZPOCZĘCIE POBIERANIA PLIKÓW MALFINI ===');
        }

        // Sprawdź czy hurtownia jest włączona
        $enabled = $this->config['enabled'];
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Status włączenia Malfini: ' . ($enabled ? 'WŁĄCZONA' : 'WYŁĄCZONA'));
        } else {
            error_log('MHI: Status włączenia Malfini: ' . ($enabled ? 'WŁĄCZONA' : 'WYŁĄCZONA'));
        }

        if (!$enabled) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona.');
            }
            return $files;
        }

        // Nawiąż połączenie z API
        if (!$this->connect()) {
            return $files;
        }

        try {
            // Folder lokalny
            $upload_dir = wp_upload_dir();
            $local_path = $upload_dir['basedir'] . '/wholesale/' . $this->name . '/';

            // Upewnij się, że folder lokalny istnieje
            if (!file_exists($local_path)) {
                wp_mkdir_p($local_path);
            }

            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Rozpoczynam pobieranie danych z API Malfini');
            }

            // Pobierz dane z każdego endpointu
            foreach ($this->config['endpoints'] as $name => $endpoint) {
                // Pomiń endpoint logowania
                if ($name === 'login') {
                    continue;
                }

                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info('Pobieranie danych z endpointu: ' . $name . ' (' . $endpoint . ')');
                } else {
                    error_log('MHI: Pobieranie danych z endpointu: ' . $name . ' (' . $endpoint . ')');
                }

                $file_info = $this->download_api_data($endpoint, $name, $local_path);
                if ($file_info) {
                    $files[] = $file_info;
                    if (class_exists('MHI_Logger')) {
                        MHI_Logger::info('✅ Pomyślnie pobrano: ' . $file_info['filename']);
                    } else {
                        error_log('MHI: ✅ Pomyślnie pobrano: ' . $file_info['filename']);
                    }
                } else {
                    if (class_exists('MHI_Logger')) {
                        MHI_Logger::error('❌ Nie udało się pobrać: ' . $name);
                    } else {
                        error_log('MHI ERROR: ❌ Nie udało się pobrać: ' . $name);
                    }
                }
            }

            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Zakończono pobieranie danych z API Malfini. Pobrano ' . count($files) . ' plików.');
                MHI_Logger::info('=== KONIEC POBIERANIA PLIKÓW MALFINI ===');
            } else {
                error_log('MHI: Zakończono pobieranie danych z API Malfini. Pobrano ' . count($files) . ' plików.');
                error_log('MHI: === KONIEC POBIERANIA PLIKÓW MALFINI ===');
            }

        } catch (Exception $e) {
            $error_msg = 'Błąd podczas pobierania plików z hurtowni ' . $this->name . ': ' . $e->getMessage();
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error($error_msg);
                MHI_Logger::error('=== BŁĄD PODCZAS POBIERANIA PLIKÓW MALFINI ===');
            } else {
                error_log('MHI ERROR: ' . $error_msg);
                error_log('MHI ERROR: === BŁĄD PODCZAS POBIERANIA PLIKÓW MALFINI ===');
            }
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('=== ZWRACANIE WYNIKÓW MALFINI: ' . count($files) . ' plików ===');
        } else {
            error_log('MHI: === ZWRACANIE WYNIKÓW MALFINI: ' . count($files) . ' plików ===');
        }

        return $files;
    }

    /**
     * Pobiera dane z API i zapisuje jako plik JSON.
     *
     * @param string $endpoint Endpoint API
     * @param string $name Nazwa pliku
     * @param string $target_dir Katalog docelowy
     * @return array|false Informacje o pliku lub false w przypadku błędu
     */
    private function download_api_data($endpoint, $name, $target_dir)
    {
        $filename = $name . '.json';
        $local_path = $target_dir . $filename;

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Pobieranie danych z endpointu: ' . $endpoint);
        }

        $response = $this->make_api_request($endpoint);

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

        // Sprawdź czy odpowiedź jest prawidłowym JSON
        $json_data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowy JSON w odpowiedzi dla ' . $filename . ': ' . json_last_error_msg());
            }
            return false;
        }

        // Zapisz plik JSON
        $result = file_put_contents($local_path, $body);
        if ($result === false) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nie udało się zapisać pliku ' . $filename);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Pobrano plik: ' . $filename . ' z API Malfini (' . size_format(filesize($local_path)) . ')');
        }

        return array(
            'filename' => $filename,
            'local_path' => $local_path,
            'remote_path' => $this->config['api_base_url'] . $endpoint,
            'size' => filesize($local_path),
            'timestamp' => time(),
            'status' => 'downloaded'
        );
    }

    /**
     * Sprawdza poprawność danych uwierzytelniających.
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
     * @param array $files Lista pobranych plików.
     * @return bool True jeśli przetwarzanie się powiodło, false w przeciwnym razie.
     */
    public function process_files($files)
    {
        if (empty($files)) {
            return false;
        }

        try {
            foreach ($files as $file) {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info('Przetwarzanie pliku: ' . $file['filename']);
                } else {
                    error_log('MHI: Przetwarzanie pliku: ' . $file['filename']);
                }
            }

            return true;
        } catch (Exception $e) {
            $error_msg = 'Błąd podczas przetwarzania plików z hurtowni ' . $this->name . ': ' . $e->getMessage();
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error($error_msg);
            } else {
                error_log('MHI ERROR: ' . $error_msg);
            }
            return false;
        }
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
        } else {
            error_log('MHI: Anulowano pobieranie dla hurtowni ' . $this->name);
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
        // Malfini używa adresów URL zdjęć w API, nie pobieramy bezpośrednio plików
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Hurtownia ' . $this->name . ' nie obsługuje pobierania zdjęć przez API - używa adresów URL');
        } else {
            error_log('MHI: Hurtownia ' . $this->name . ' nie obsługuje pobierania zdjęć przez API - używa adresów URL');
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
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-malfini-wc-xml-generator.php';
            $generator = new MHI_Malfini_WC_XML_Generator($hurtownia_dir);

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
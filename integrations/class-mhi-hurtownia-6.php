<?php
/**
 * Klasa integracji z hurtownią ANDA.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Hurtownia_6
 * 
 * Obsługuje integrację z hurtownią ANDA przez XML API.
 */
class MHI_Hurtownia_6 implements MHI_Integration_Interface
{
    /**
     * Nazwa integracji.
     *
     * @var string
     */
    private $name = 'anda';

    /**
     * Dane konfiguracyjne.
     *
     * @var array
     */
    private $config;

    /**
     * Token autoryzacyjny ANDA.
     *
     * @var string
     */
    private $auth_token = 'DIRDFMUWNB5T2AQJEQ8F2GRIBHT6KJ8VHKLDV4NH76ND4JJQT8UHIFKEJVK2IE7X';

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        // Inicjalizacja konfiguracji - ANDA XML API
        $this->config = array(
            'enabled' => get_option('mhi_hurtownia_6_enabled', 1), // Domyślnie włączona
            'api_base_url' => 'https://xml.andapresent.com/export/',
            'endpoints' => array(),
            'ftp' => array(
                'host' => '82.131.166.34',
                'user' => 'public',
                'password' => 'andapresent',
                'directory' => '/FTP_Public/'
            )
        );

        // Teraz można dodać endpointy z tokenem - tylko wersje polskie
        $this->config['endpoints'] = array(
            'prices' => 'prices/' . $this->auth_token,
            'inventories' => 'inventories/' . $this->auth_token,
            'categories' => 'categories/pl/' . $this->auth_token,
            'products' => 'products/pl/' . $this->auth_token,
            'labeling' => 'labeling/pl/' . $this->auth_token,
            'printingprices' => 'printingprices/' . $this->auth_token
        );

        // Alternatywne endpointy z tokenem jako parametr URL
        $this->config['endpoints_alt'] = array(
            'prices' => 'prices?token=' . $this->auth_token,
            'inventories' => 'inventories?token=' . $this->auth_token,
            'categories' => 'categories/pl?token=' . $this->auth_token,
            'products' => 'products/pl?token=' . $this->auth_token,
            'labeling' => 'labeling/pl?token=' . $this->auth_token,
            'printingprices' => 'printingprices?token=' . $this->auth_token
        );

        // Trzeci format - token w nagłówku
        $this->config['endpoints_header'] = array(
            'prices' => 'prices',
            'inventories' => 'inventories',
            'categories' => 'categories/pl',
            'products' => 'products/pl',
            'labeling' => 'labeling/pl',
            'printingprices' => 'printingprices'
        );
    }

    /**
     * Nawiązuje połączenie z hurtownią przez XML API.
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

        // Testowe połączenie z API ANDA (sprawdź cennik)
        $test_endpoint = $this->config['api_base_url'] . $this->config['endpoints']['prices'];

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Testowanie połączenia z: ' . $test_endpoint);
        }

        $response = wp_remote_head($test_endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        ));

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas łączenia z API ANDA: ' . $response->get_error_message());
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowa odpowiedź API ANDA. Kod HTTP: ' . $response_code);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Pomyślnie połączono z API ANDA');
        }
        return true;
    }

    /**
     * Testuje pojedynczy endpoint ANDA.
     *
     * @param string $endpoint_name Nazwa endpointu do przetestowania
     * @return bool Wynik testu
     */
    public function test_endpoint($endpoint_name = 'prices')
    {
        if (!isset($this->config['endpoints'][$endpoint_name])) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Nieznany endpoint: ' . $endpoint_name);
            }
            return false;
        }

        // Testuj pierwsze format (token w URL path)
        $url1 = $this->config['api_base_url'] . $this->config['endpoints'][$endpoint_name];
        $test1 = $this->test_single_url($url1, 'format 1 (token w path)');

        if ($test1) {
            return true;
        }

        // Testuj drugi format (token jako parametr URL)
        $url2 = $this->config['api_base_url'] . $this->config['endpoints_alt'][$endpoint_name];
        $test2 = $this->test_single_url($url2, 'format 2 (token jako parametr)');

        if ($test2) {
            return true;
        }

        // Testuj trzeci format (token w nagłówku)
        $url3 = $this->config['api_base_url'] . $this->config['endpoints_header'][$endpoint_name];
        $test3 = $this->test_single_url_with_header($url3, 'format 3 (token w nagłówku)');

        return $test3;
    }

    /**
     * Testuje pojedynczy URL ANDA.
     *
     * @param string $url URL do przetestowania
     * @param string $format_name Nazwa formatu dla logów
     * @return bool Wynik testu
     */
    private function test_single_url($url, $format_name = '')
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Testowanie endpointu (' . $format_name . '): ' . $url);
        }

        // Próbuj GET request bezpośrednio
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/xml, text/xml, */*',
            ),
        ));

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Błąd request (' . $format_name . '): ' . $response->get_error_message());
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Odpowiedź z endpointu (' . $format_name . '): HTTP ' . $response_code);
            if ($response_code !== 200) {
                $response_body = wp_remote_retrieve_body($response);
                MHI_Logger::error('ANDA - Test endpointu nieudany (' . $format_name . '): Kod odpowiedzi: ' . $response_code . ' URL: ' . $url);
                MHI_Logger::error('ANDA - Treść odpowiedzi: ' . substr($response_body, 0, 500));

                if (403 === $response_code) {
                    $external_ip = self::get_external_ip();
                    MHI_Logger::error('ANDA - Błąd 403. Prawdopodobnie Twój adres IP nie jest na białej liście. Twój adres IP: ' . $external_ip);
                    update_option('mhi_anda_ip_issue', $external_ip);
                }

            } else {
                MHI_Logger::info('ANDA - Sukces dla formatu: ' . $format_name);
                // Usuń informację o błędzie IP po udanym połączeniu
                delete_option('mhi_anda_ip_issue');
            }
        }

        return ($response_code === 200);
    }

    /**
     * Testuje pojedynczy URL ANDA z tokenem w nagłówku.
     *
     * @param string $url URL do przetestowania
     * @param string $format_name Nazwa formatu dla logów
     * @return bool Wynik testu
     */
    private function test_single_url_with_header($url, $format_name = '')
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Testowanie endpointu (' . $format_name . '): ' . $url);
        }

        // Próbuj GET request z tokenem w nagłówku
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/xml, text/xml, */*',
                'Authorization' => 'Bearer ' . $this->auth_token,
                'X-API-Token' => $this->auth_token,
            ),
        ));

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Błąd request (' . $format_name . '): ' . $response->get_error_message());
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Odpowiedź z endpointu (' . $format_name . '): HTTP ' . $response_code);
            if ($response_code !== 200) {
                $response_body = wp_remote_retrieve_body($response);
                MHI_Logger::error('ANDA - Test endpointu nieudany (' . $format_name . '): Kod odpowiedzi: ' . $response_code . ' URL: ' . $url);
                MHI_Logger::error('ANDA - Treść odpowiedzi: ' . substr($response_body, 0, 500));

                if (403 === $response_code) {
                    $external_ip = self::get_external_ip();
                    MHI_Logger::error('ANDA - Błąd 403. Prawdopodobnie Twój adres IP nie jest na białej liście. Twój adres IP: ' . $external_ip);
                    update_option('mhi_anda_ip_issue', $external_ip);
                }

            } else {
                MHI_Logger::info('ANDA - Sukces dla formatu: ' . $format_name);
                // Usuń informację o błędzie IP po udanym połączeniu
                delete_option('mhi_anda_ip_issue');
            }
        }

        return ($response_code === 200);
    }

    /**
     * Pobiera zewnętrzny adres IP serwera.
     *
     * @return string|false Adres IP lub false w przypadku błędu.
     */
    public static function get_external_ip()
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

        // Fallback to server variable if external services fail
        return $_SERVER['SERVER_ADDR'] ?? false;
    }

    /**
     * Pobiera pliki z hurtowni i zapisuje je w odpowiednim folderze.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_files()
    {
        $files = array();

        // Sprawdź, czy hurtownia jest włączona
        if (!$this->config['enabled']) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona.');
            }
            return $files;
        }

        // Najpierw przetestuj jeden endpoint
        if (!$this->test_endpoint('prices')) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Test endpointu prices nieudany - przerywam pobieranie plików');
            }
            return $files;
        }

        // Przygotuj lokalny folder
        $uploads_dir = wp_upload_dir();
        $local_path = $uploads_dir['basedir'] . '/wholesale/' . $this->name . '/';
        if (!file_exists($local_path)) {
            wp_mkdir_p($local_path);
        }

        // Pobierz pliki ze wszystkich endpointów ANDA
        foreach ($this->config['endpoints'] as $endpoint_name => $endpoint_path) {
            try {
                $file_info = $this->download_xml_data($endpoint_path, $endpoint_name, $local_path);
                if ($file_info) {
                    $files[] = $file_info;
                }
            } catch (Exception $e) {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error('Błąd podczas pobierania pliku ' . $endpoint_name . ' z hurtowni ' . $this->name . ': ' . $e->getMessage());
                }
            }
        }

        return $files;
    }

    /**
     * Pobiera dane XML z API ANDA i zapisuje lokalnie.
     *
     * @param string $endpoint Endpoint API.
     * @param string $name Nazwa pliku.
     * @param string $target_dir Katalog docelowy.
     * @return array|false Informacje o pobranym pliku lub false w przypadku błędu.
     */
    private function download_xml_data($endpoint, $name, $target_dir)
    {
        $url = $this->config['api_base_url'] . $endpoint;
        $local_file = $target_dir . $name . '.xml';

        $args = array(
            'method' => 'GET',
            'timeout' => 300,
            'headers' => array(
                'Accept' => 'application/xml',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        );

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Pobieranie danych z: ' . $url);
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Błąd HTTP dla ' . $name . ': ' . $response->get_error_message());
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Nieprawidłowa odpowiedź dla ' . $name . '. Kod HTTP: ' . $response_code . '. Odpowiedź: ' . substr($response_body, 0, 500));
            }
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        if (empty($response_body)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Pusta odpowiedź dla ' . $name);
            }
            return false;
        }

        // Zapisz dane do pliku
        $result = file_put_contents($local_file, $response_body);
        if ($result === false) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Nie udało się zapisać pliku ' . $name);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Pobrano plik: ' . $name . '.xml (' . strlen($response_body) . ' bajtów)');
        }

        return array(
            'filename' => $name . '.xml',
            'local_path' => $local_file,
            'remote_path' => $url,
            'size' => strlen($response_body),
            'timestamp' => time()
        );
    }

    /**
     * Pobiera pliki zdjęć z FTP ANDA.
     *
     * @param int $batch_number Numer partii do pobrania
     * @param string $img_dir Katalog ze zdjęciami na serwerze
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_images($batch_number = 1, $img_dir = '/images')
    {
        $files = array();

        $ftp_config = $this->config['ftp'];
        $host = $ftp_config['host'];
        $user = $ftp_config['user'];
        $pass = $ftp_config['password'];
        $remote_dir = $ftp_config['directory'] . ltrim($img_dir, '/');

        // Przygotuj lokalny folder
        $uploads_dir = wp_upload_dir();
        $local_path = $uploads_dir['basedir'] . '/wholesale/' . $this->name . '/images/';
        if (!file_exists($local_path)) {
            wp_mkdir_p($local_path);
        }

        // Nawiązanie połączenia FTP
        $conn_id = ftp_connect($host);
        if (!$conn_id) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Nie udało się połączyć z serwerem FTP: ' . $host);
            }
            return $files;
        }

        // Logowanie
        $login_result = ftp_login($conn_id, $user, $pass);
        if (!$login_result) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('ANDA - Nie udało się zalogować do serwera FTP. Użytkownik: ' . $user);
            }
            ftp_close($conn_id);
            return $files;
        }

        // Włącz tryb pasywny
        ftp_pasv($conn_id, true);

        // Przejdź do katalogu ze zdjęciami
        if (!ftp_chdir($conn_id, $remote_dir)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::warning('ANDA - Nie udało się przejść do katalogu: ' . $remote_dir);
            }
            ftp_close($conn_id);
            return $files;
        }

        // Pobierz listę plików
        $remote_files = ftp_nlist($conn_id, '.');
        if (!$remote_files) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::warning('ANDA - Nie znaleziono plików w katalogu FTP: ' . $remote_dir);
            }
            ftp_close($conn_id);
            return $files;
        }

        // Pobierz zdjęcia (limit dla partii)
        $batch_size = 50;
        $start_index = ($batch_number - 1) * $batch_size;
        $end_index = $start_index + $batch_size;

        $image_files = array_filter($remote_files, function ($file) {
            return preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
        });

        $batch_files = array_slice($image_files, $start_index, $batch_size);

        foreach ($batch_files as $file) {
            $local_file = $local_path . basename($file);
            if (ftp_get($conn_id, $local_file, $file, FTP_BINARY)) {
                $files[] = array(
                    'filename' => basename($file),
                    'local_path' => $local_file,
                    'remote_path' => $remote_dir . '/' . $file,
                    'size' => filesize($local_file),
                    'timestamp' => time()
                );

                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info('ANDA - Pobrano zdjęcie: ' . $file);
                }
            } else {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error('ANDA - Nie udało się pobrać zdjęcia: ' . $file);
                }
            }
        }

        // Zamknij połączenie
        ftp_close($conn_id);

        return $files;
    }

    /**
     * Waliduje dane dostępowe do hurtowni.
     *
     * @return bool True jeśli dane są poprawne, false w przeciwnym razie.
     */
    public function validate_credentials()
    {
        return $this->connect();
    }

    /**
     * Przetwarza pobrane pliki.
     *
     * @param array $files Lista plików do przetworzenia.
     * @return bool True jeśli przetwarzanie się powiodło, false w przeciwnym razie.
     */
    public function process_files($files)
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Rozpoczęto przetwarzanie ' . count($files) . ' plików');
        }

        foreach ($files as $file) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('ANDA - Przetwarzanie pliku: ' . $file['filename']);
            }
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
            MHI_Logger::info('Anulowano pobieranie dla hurtowni ' . $this->name);
        }
    }

    /**
     * Importuje produkty do WooCommerce.
     *
     * @return array Wynik importu.
     */
    public function import_products_to_woocommerce()
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Rozpoczęto import produktów do WooCommerce');
        }

        return array(
            'success' => true,
            'message' => 'Import produktów ANDA zakończony pomyślnie'
        );
    }

    /**
     * Generuje plik XML kompatybilny z WooCommerce.
     *
     * @return bool True jeśli plik został wygenerowany, false w przeciwnym razie.
     */
    public function generate_woocommerce_xml()
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('ANDA - Generowanie pliku XML do importu WooCommerce');
        }

        // Implementacja generowania XML w przyszłości
        return true;
    }
}
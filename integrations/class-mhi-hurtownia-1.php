<?php
/**
 * Klasa integracji z hurtownią Malfini (API).
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Hurtownia_1
 * 
 * Obsługuje integrację z hurtownią Malfini przez API.
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
        // Inicjalizacja konfiguracji
        $this->config = array(
            'api_base_url' => get_option('mhi_hurtownia_1_api_url', MHI_DEFAULT_MALFINI_API_URL),
            'api_login' => mhi_get_secure_config('hurtownia_1_api_login'),
            'api_password' => mhi_get_secure_config('hurtownia_1_api_password'),
            'auth_endpoint' => 'api-auth/login',
            'product_endpoint' => 'product',
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
        if (!get_option('mhi_hurtownia_1_enabled', 0)) {
            MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona.');
            return false;
        }

        // Sprawdź, czy dane są poprawne
        if (!$this->validate_credentials()) {
            MHI_Logger::error('Nieprawidłowe dane uwierzytelniające dla hurtowni ' . $this->name);
            return false;
        }

        try {
            // Zaloguj się do API aby uzyskać token
            $response = $this->login();

            if (is_wp_error($response)) {
                MHI_Logger::error('Błąd podczas logowania do API hurtowni ' . $this->name . ': ' . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (empty($data) || !isset($data['access_token'])) {
                MHI_Logger::error('Nieprawidłowa odpowiedź z API hurtowni ' . $this->name . ' podczas logowania. Odpowiedź: ' . print_r($data, true));
                return false;
            }

            // Zapisz token autoryzacyjny
            $this->auth_token = $data['access_token'];

            MHI_Logger::info('Połączono z API hurtowni ' . $this->name);
            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas łączenia z API hurtowni ' . $this->name . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Loguje się do API Malfini.
     * 
     * @return array|WP_Error Odpowiedź API lub obiekt błędu.
     */
    private function login()
    {
        $login_url = trailingslashit($this->config['api_base_url']) . $this->config['auth_endpoint'];

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
                'username' => $this->config['api_login'],
                'password' => $this->config['api_password'],
            )),
        );

        return wp_remote_post($login_url, $args);
    }

    /**
     * Wykonuje żądanie do API z autoryzacją.
     *
     * @param string $endpoint Endpoint API.
     * @param array  $params   Parametry żądania.
     * @param string $method   Metoda żądania (GET, POST).
     * @return array|WP_Error Odpowiedź API lub obiekt błędu.
     */
    private function make_api_request($endpoint, $params = array(), $method = 'GET')
    {
        // Pełny URL do API
        $url = trailingslashit($this->config['api_base_url']) . $endpoint;

        // Parametry żądania
        $args = array(
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        );

        // Dodaj token autoryzacyjny, jeśli istnieje
        if (!empty($this->auth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->auth_token;
        }

        // Wykonaj żądanie w zależności od metody
        if ('POST' === $method) {
            $args['body'] = json_encode($params);
            $response = wp_remote_post($url, $args);
        } else {
            $url = add_query_arg($params, $url);
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

        // Połącz z API
        if (!$this->connect()) {
            return $files;
        }

        try {
            // Folder lokalny
            $local_path = MHI_UPLOADS_DIR . '/' . $this->name . '/';

            // Upewnij się, że folder lokalny istnieje
            if (!file_exists($local_path)) {
                wp_mkdir_p($local_path);
            }

            // Lista endpointów do pobrania i formaty plików
            $endpoints_to_fetch = array(
                $this->config['product_endpoint'] => array(
                    'name' => 'produkty',
                    'format' => get_option('mhi_hurtownia_1_format', 'xml') // Domyślnie XML
                )
                // Tutaj można dodać więcej endpointów, jeśli potrzeba
            );

            // Pobierz dane z każdego endpointu
            foreach ($endpoints_to_fetch as $endpoint => $config) {
                // Stała nazwa pliku bez daty i czasu
                $format = $config['format']; // xml, json lub csv
                $filename = $config['name'] . '.' . $format;
                $local_file = $local_path . $filename;

                // Pobierz produkty
                MHI_Logger::info('Rozpoczęto pobieranie danych z endpointu ' . $endpoint . ' hurtowni ' . $this->name);
                $response = $this->make_api_request($endpoint);

                if (is_wp_error($response)) {
                    MHI_Logger::error('Błąd podczas pobierania danych z API hurtowni ' . $this->name . ': ' . $response->get_error_message());
                    continue;
                }

                $body = wp_remote_retrieve_body($response);

                // Sprawdź czy odpowiedź nie jest pusta
                if (empty($body)) {
                    MHI_Logger::error('Pusta odpowiedź z API hurtowni ' . $this->name . ' dla endpointu ' . $endpoint);
                    continue;
                }

                // Konwertuj dane do wybranego formatu, jeśli to potrzebne
                if ($format === 'xml' && $this->is_json($body)) {
                    $body = $this->convert_json_to_xml($body, $config['name']);
                } elseif ($format === 'csv') {
                    if ($this->is_json($body)) {
                        $body = $this->convert_json_to_csv($body);
                    } else {
                        // Zakładamy, że to XML
                        $body = $this->convert_xml_to_csv($body);
                    }
                }

                // Zapisz plik
                $result = file_put_contents($local_file, $body);

                if ($result === false) {
                    MHI_Logger::error('Nie udało się zapisać pliku: ' . $filename . ' z hurtowni ' . $this->name);
                    continue;
                }

                MHI_Logger::info('Pobrano dane do pliku: ' . $filename . ' z hurtowni ' . $this->name . ' (' . size_format(strlen($body)) . ')');

                $files[] = array(
                    'filename' => $filename,
                    'path' => $local_file,
                    'size' => filesize($local_file),
                    'date' => date('Y-m-d H:i:s'),
                    'endpoint' => $endpoint,
                );
            }

        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas pobierania plików z hurtowni ' . $this->name . ': ' . $e->getMessage());
        }

        return $files;
    }

    /**
     * Sprawdza, czy dane są w formacie JSON.
     *
     * @param string $data Dane do sprawdzenia.
     * @return bool True jeśli dane są w formacie JSON, false w przeciwnym razie.
     */
    private function is_json($data)
    {
        json_decode($data);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Konwertuje dane JSON do formatu XML.
     *
     * @param string $json_data Dane w formacie JSON.
     * @param string $root_element Nazwa elementu głównego w XML.
     * @return string Dane w formacie XML.
     */
    private function convert_json_to_xml($json_data, $root_element = 'data')
    {
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            MHI_Logger::error('Błąd podczas parsowania JSON: ' . json_last_error_msg());
            return $json_data; // Zwróć oryginalne dane
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $root_element . '></' . $root_element . '>');

        // Funkcja rekurencyjna do dodawania danych do XML
        $this->array_to_xml($data, $xml);

        return $xml->asXML();
    }

    /**
     * Rekurencyjna funkcja konwertująca tablicę do struktury XML.
     *
     * @param array $data Dane do konwersji.
     * @param SimpleXMLElement $xml_data Element XML, do którego dodawane są dane.
     */
    private function array_to_xml($data, &$xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key; // Użyj 'item' dla kluczy numerycznych
            }

            // Zastąp niedozwolone znaki w nazwie elementu XML
            $key = preg_replace('/[^a-z0-9_-]/i', '_', $key);

            if (is_array($value)) {
                $subnode = $xml_data->addChild($key);
                $this->array_to_xml($value, $subnode);
            } else {
                // Dla elementów z atrybutami, używany jest specjalny format
                if (is_string($value) && substr($value, 0, 1) === '@') {
                    // To jest atrybut
                    $attr_name = substr($value, 1);
                    $xml_data->addAttribute($key, $attr_name);
                } else {
                    // Escapowanie treści XML
                    $xml_data->addChild($key, htmlspecialchars($value ?? ''));
                }
            }
        }
    }

    /**
     * Konwertuje dane JSON do formatu CSV.
     *
     * @param string $json_data Dane w formacie JSON.
     * @return string Dane w formacie CSV.
     */
    private function convert_json_to_csv($json_data)
    {
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            MHI_Logger::error('Błąd podczas parsowania JSON do CSV: ' . json_last_error_msg());
            return $json_data;
        }

        // Jeśli to tablica asocjacyjna (nie numeryczna), opakuj ją w tablicę
        if (count($data) > 0 && !isset($data[0])) {
            $data = array($data);
        }

        $csv = '';

        // Utwórz CSV z nagłówkami
        if (!empty($data)) {
            $headers = array_keys(reset($data));
            $csv .= implode(',', $headers) . "\n";

            foreach ($data as $row) {
                $values = array();
                foreach ($headers as $header) {
                    $value = isset($row[$header]) ? $row[$header] : '';
                    // Jeśli wartość jest tablicą lub obiektem, przekształć na JSON
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    }
                    // Escapowanie wartości CSV
                    $values[] = '"' . str_replace('"', '""', $value) . '"';
                }
                $csv .= implode(',', $values) . "\n";
            }
        }

        return $csv;
    }

    /**
     * Konwertuje dane XML do formatu CSV.
     *
     * @param string $xml_data Dane w formacie XML.
     * @return string Dane w formacie CSV.
     */
    private function convert_xml_to_csv($xml_data)
    {
        // Konwersja XML do tablicy
        $xml = simplexml_load_string($xml_data);
        if ($xml === false) {
            MHI_Logger::error('Nie udało się załadować XML do konwersji na CSV');
            return $xml_data;
        }

        $json = json_encode($xml);
        $array = json_decode($json, true);

        // Konwertuj tablicę na CSV
        return $this->convert_json_to_csv($json);
    }

    /**
     * Sprawdza poprawność danych uwierzytelniających.
     *
     * @return bool True jeśli dane są poprawne, false w przeciwnym razie.
     */
    public function validate_credentials()
    {
        return !empty($this->config['api_base_url']) && !empty($this->config['api_login']) && !empty($this->config['api_password']);
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
                // TODO: Implementacja przetwarzania plików
                MHI_Logger::info('Przetwarzanie pliku: ' . $file['filename']);
            }

            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas przetwarzania plików z hurtowni ' . $this->name . ': ' . $e->getMessage());
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
        MHI_Logger::info('Anulowano pobieranie dla hurtowni ' . $this->name);
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
        // Zwracamy pustą tablicę jako wynik
        MHI_Logger::info('Hurtownia ' . $this->name . ' nie obsługuje pobierania zdjęć przez API - używa adresów URL');
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

        // Przygotuj ścieżkę do pliku import.php
        $import_script = plugin_dir_path(dirname(__FILE__)) . 'import.php';

        // Sprawdź czy plik import.php istnieje
        if (!file_exists($import_script)) {
            throw new Exception(__('Nie znaleziono skryptu importu: import.php', 'multi-hurtownie-integration'));
        }

        // Przygotuj URL do skryptu importu
        $import_url = admin_url('admin.php?page=mhi-product-import&supplier=' . $this->name);

        // Przekieruj do skryptu importu
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
        // Sprawdź czy klasa generatora istnieje
        $generator_file = MHI_PLUGIN_DIR . 'integrations/class-mhi-malfini-wc-xml-generator.php';
        if (!file_exists($generator_file)) {
            MHI_Logger::error('Nie znaleziono pliku generatora XML WooCommerce dla Malfini');
            return false;
        }

        // Załaduj klasę generatora jeśli nie została jeszcze załadowana
        if (!class_exists('MHI_Malfini_WC_XML_Generator')) {
            require_once $generator_file;
        }

        try {
            // Utwórz instancję generatora
            $generator = new MHI_Malfini_WC_XML_Generator();

            // Generuj plik XML
            $result = $generator->generate_woocommerce_xml();

            if ($result) {
                MHI_Logger::info('Pomyślnie wygenerowano plik XML do importu WooCommerce dla hurtowni ' . $this->name);
                return true;
            } else {
                MHI_Logger::error('Błąd podczas generowania pliku XML WooCommerce dla hurtowni ' . $this->name);
                return false;
            }
        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas generowania pliku XML WooCommerce dla hurtowni ' . $this->name . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Importuje produkty do WooCommerce z wygenerowanego pliku XML.
     *
     * @return bool|string True jeśli import się powiódł, komunikat błędu w przeciwnym razie.
     */
    public function import_products_to_woocommerce_file()
    {
        $upload_dir = wp_upload_dir();
        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name . '/woocommerce_import_' . $this->name . '.xml';

        // Sprawdź czy plik XML istnieje
        if (!file_exists($xml_file)) {
            // Spróbuj go wygenerować
            if (!$this->generate_woocommerce_xml()) {
                return 'Nie można wygenerować pliku XML do importu.';
            }
        }

        // Sprawdź czy plugin WP All Import jest aktywny
        if (!class_exists('PMXI_Plugin')) {
            return 'Plugin WP All Import nie jest aktywny. Nie można zaimportować produktów.';
        }

        try {
            // Implementacja importu przez WP All Import API
            // Tutaj kod do uruchomienia importu przez WP All Import
            // ...

            // Tymczasowo zwracamy true - docelowo powinien być tutaj kod integracji z WP All Import
            return true;
        } catch (Exception $e) {
            return 'Błąd podczas importu: ' . $e->getMessage();
        }
    }
}
<?php
/**
 * Klasa integracji z hurtownią 5 (Macma - API/XML).
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Hurtownia_5
 * 
 * Obsługuje integrację z hurtownią 5 (Macma) przez API/XML.
 */
class MHI_Hurtownia_5 implements MHI_Integration_Interface
{
    /**
     * Nazwa integracji.
     *
     * @var string
     */
    private $name = 'macma';

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
            'endpoints' => array(
                'stany_magazynowe' => 'http://www.macma.pl/data/webapi/pl/xml/stocks.xml',
                'oferta' => 'http://www.macma.pl/data/webapi/pl/xml/offer.xml',
                'promocje' => 'http://www.macma.pl/data/webapi/pl/xml/promotions.xml',
                'kategorie' => 'http://www.macma.pl/data/webapi/pl/xml/categories.xml',
                'materialy' => 'http://www.macma.pl/data/webapi/pl/xml/materials.xml',
                'znakowanie' => 'http://www.macma.pl/data/webapi/pl/xml/markgroups.xml',
                'kolory' => 'http://www.macma.pl/data/webapi/pl/xml/colors.xml',
                'ceny' => 'http://www.macma.pl/data/webapi/pl/xml/prices.xml',
            ),
            'interval' => get_option('mhi_hurtownia_5_interval', 'daily'),
            'enabled' => get_option('mhi_hurtownia_5_enabled', 0),
        );
    }

    /**
     * Nawiązuje połączenie z hurtownią.
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

        // Sprawdź dostępność API (testowe połączenie)
        $test_url = $this->config['endpoints']['stany_magazynowe'];
        $response = wp_remote_head($test_url);

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas łączenia z API hurtowni ' . $this->name . ': ' . $response->get_error_message());
            }
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowa odpowiedź API hurtowni ' . $this->name . '. Kod HTTP: ' . $http_code);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Połączono z API hurtowni ' . $this->name);
        }
        return true;
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
        if (!$this->connect()) {
            return $files;
        }

        // Przygotuj lokalny folder
        $local_path = MHI_UPLOADS_DIR . '/' . $this->name . '/';
        if (!file_exists($local_path)) {
            wp_mkdir_p($local_path);
        }

        // Pobierz pliki ze wszystkich endpointów
        foreach ($this->config['endpoints'] as $endpoint_name => $endpoint_url) {
            try {
                $file_info = $this->download_file($endpoint_url, $local_path, $endpoint_name . '.xml');
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
     * Pobiera plik z API i zapisuje lokalnie.
     *
     * @param string $url URL do pliku.
     * @param string $local_path Ścieżka docelowa.
     * @param string $filename Nazwa pliku.
     * @return array|false Informacje o pobranym pliku lub false w przypadku błędu.
     */
    private function download_file($url, $local_path, $filename)
    {
        $local_file = $local_path . $filename;

        // Pobierz plik
        $response = wp_remote_get($url, array(
            'timeout' => 300,  // Zwiększamy timeout dla większych plików
            'stream' => true,
            'filename' => $local_file
        ));

        if (is_wp_error($response)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas pobierania pliku ' . $filename . ': ' . $response->get_error_message());
            }
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nieprawidłowa odpowiedź podczas pobierania pliku ' . $filename . '. Kod HTTP: ' . $http_code);
            }
            return false;
        }

        if (!file_exists($local_file)) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Nie udało się zapisać pliku ' . $filename);
            }
            return false;
        }

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Pobrano plik: ' . $filename . ' z hurtowni ' . $this->name);
        }

        return array(
            'filename' => $filename,
            'local_path' => $local_file,
            'remote_path' => $url,
            'size' => filesize($local_file),
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
        // W przypadku tej hurtowni nie ma specjalnych danych uwierzytelniających
        // API jest publicznie dostępne
        return true;
    }

    /**
     * Przetwarza pobrane pliki.
     *
     * @param array $files Lista plików do przetworzenia.
     * @return bool True w przypadku powodzenia, false w przypadku błędu.
     */
    public function process_files($files)
    {
        // W tej implementacji nie ma dodatkowego przetwarzania plików XML
        // W przyszłości można dodać parseowanie XML, ekstraktowanie konkretnych danych, itp.

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
        // Macma korzysta z adresów URL zdjęć w XML
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
        throw new Exception(__('Funkcja importu produktów została wyłączona.', 'multi-hurtownie-integration'));
    }

    /**
     * Zwraca status importu (funkcja wyłączona).
     *
     * @return array Status importu.
     */
    public function get_import_status()
    {
        return array(
            'completed' => true,
            'message' => __('Funkcja importu produktów została wyłączona.', 'multi-hurtownie-integration')
        );
    }

    /**
     * Wznawia import produktów (funkcja wyłączona).
     *
     * @return string Status wznowienia.
     */
    public function resume_import()
    {
        throw new Exception(__('Funkcja importu produktów została wyłączona.', 'multi-hurtownie-integration'));
    }
}
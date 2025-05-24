<?php
/**
 * Klasa integracji z hurtownią Macma.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Hurtownia_3
 * 
 * Klasa odpowiedzialna za integrację z hurtownią Macma.
 */
class MHI_Hurtownia_3 implements MHI_Integration_Interface
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    protected $name = 'macma';

    /**
     * Konfiguracja integracji.
     * 
     * @var array
     */
    protected $config = [];

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        $this->load_config();
    }

    /**
     * Ładuje konfigurację integracji z opcji WordPress.
     */
    protected function load_config()
    {
        $this->config = get_option('mhi_hurtownia_3_settings', []);
    }

    /**
     * Nawiązuje połączenie z zewnętrznym źródłem danych
     *
     * @return bool Informacja czy połączenie zostało nawiązane
     */
    public function connect()
    {
        if (empty($this->config)) {
            MHI_Logger::error('Brak konfiguracji dla hurtowni: ' . $this->name);
            return false;
        }

        $host = isset($this->config['ftp_host']) ? $this->config['ftp_host'] : '';
        $port = isset($this->config['ftp_port']) ? intval($this->config['ftp_port']) : 21;
        $user = isset($this->config['ftp_user']) ? $this->config['ftp_user'] : '';
        $pass = isset($this->config['ftp_pass']) ? $this->config['ftp_pass'] : '';

        if (empty($host) || empty($user) || empty($pass)) {
            MHI_Logger::error('Brak kompletnych danych FTP dla hurtowni: ' . $this->name);
            return false;
        }

        // Sprawdź połączenie
        $conn_id = @ftp_connect($host, $port);
        if (!$conn_id) {
            MHI_Logger::error('Nie udało się połączyć z serwerem FTP: ' . $host . ':' . $port);
            return false;
        }

        // Logowanie
        $login_result = @ftp_login($conn_id, $user, $pass);
        if (!$login_result) {
            MHI_Logger::error('Nie udało się zalogować do serwera FTP. Użytkownik: ' . $user);
            ftp_close($conn_id);
            return false;
        }

        // Zamknij połączenie
        ftp_close($conn_id);

        return true;
    }

    /**
     * Pobiera pliki z zewnętrznego źródła
     *
     * @return mixed Wynik operacji pobierania plików
     */
    public function fetch_files()
    {
        return $this->fetch_data();
    }

    /**
     * Pobiera dane z serwera FTP i zapisuje je lokalnie.
     * 
     * @return bool Zwraca true jeśli dane zostały pobrane pomyślnie, false w przypadku błędu.
     */
    public function fetch_data()
    {
        if (empty($this->config)) {
            MHI_Logger::error('Brak konfiguracji dla hurtowni: ' . $this->name);
            return false;
        }

        $host = isset($this->config['ftp_host']) ? $this->config['ftp_host'] : '';
        $port = isset($this->config['ftp_port']) ? intval($this->config['ftp_port']) : 21;
        $user = isset($this->config['ftp_user']) ? $this->config['ftp_user'] : '';
        $pass = isset($this->config['ftp_pass']) ? $this->config['ftp_pass'] : '';
        $directory = isset($this->config['ftp_directory']) ? $this->config['ftp_directory'] : '';

        if (empty($host) || empty($user) || empty($pass)) {
            MHI_Logger::error('Brak kompletnych danych FTP dla hurtowni: ' . $this->name);
            return false;
        }

        // Katalog docelowy
        $uploads_dir = wp_upload_dir();
        $target_dir = $uploads_dir['basedir'] . '/wholesale/' . $this->name . '/';

        // Utwórz katalog jeśli nie istnieje
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        // Pobierz pliki z FTP
        try {
            $ftp_files = $this->fetch_files_from_ftp($host, $user, $pass, $port, $directory, $target_dir);

            if ($ftp_files === false) {
                MHI_Logger::error('Błąd pobierania plików FTP dla hurtowni: ' . $this->name);
                return false;
            }

            MHI_Logger::info('Pobrano pliki dla hurtowni: ' . $this->name . '. Liczba plików: ' . count($ftp_files));
            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas pobierania plików FTP dla hurtowni: ' . $this->name . '. ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sprawdza poprawność danych logowania
     *
     * @return bool Informacja czy dane logowania są poprawne
     */
    public function validate_credentials()
    {
        return $this->connect();
    }

    /**
     * Przetwarza pobrane pliki
     *
     * @param array $files Lista plików do przetworzenia
     * @return mixed Wynik operacji przetwarzania plików
     */
    public function process_files($files)
    {
        // Implementacja przetwarzania plików
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
        // Ta hurtownia nie obsługuje pobierania zdjęć w partiach
        MHI_Logger::info('Hurtownia ' . $this->name . ' nie obsługuje pobierania zdjęć w partiach');
        return array();
    }

    /**
     * Pobiera pliki z serwera FTP i zapisuje je lokalnie.
     * 
     * @param string $host Adres serwera FTP.
     * @param string $user Nazwa użytkownika FTP.
     * @param string $pass Hasło użytkownika FTP.
     * @param int $port Port FTP.
     * @param string $directory Katalog na serwerze FTP.
     * @param string $target_dir Katalog docelowy.
     * @return array|false Tablica z nazwami pobranych plików lub false w przypadku błędu.
     */
    protected function fetch_files_from_ftp($host, $user, $pass, $port, $directory, $target_dir)
    {
        // Nawiązanie połączenia FTP
        $conn_id = ftp_connect($host, $port);
        if (!$conn_id) {
            MHI_Logger::error('Nie udało się połączyć z serwerem FTP: ' . $host . ':' . $port);
            return false;
        }

        // Logowanie
        $login_result = ftp_login($conn_id, $user, $pass);
        if (!$login_result) {
            MHI_Logger::error('Nie udało się zalogować do serwera FTP. Użytkownik: ' . $user);
            ftp_close($conn_id);
            return false;
        }

        // Włącz tryb pasywny
        ftp_pasv($conn_id, true);

        // Przejdź do katalogu jeśli podany
        if (!empty($directory)) {
            if (!ftp_chdir($conn_id, $directory)) {
                MHI_Logger::error('Nie udało się przejść do katalogu: ' . $directory);
                ftp_close($conn_id);
                return false;
            }
        }

        // Pobierz listę plików
        $files = ftp_nlist($conn_id, '.');
        if (!$files) {
            MHI_Logger::error('Nie znaleziono plików w katalogu FTP');
            ftp_close($conn_id);
            return false;
        }

        // Pobierz pliki XML
        $downloaded_files = [];
        foreach ($files as $file) {
            // Pomiń katalogi
            if ($file === '.' || $file === '..' || !preg_match('/\.xml$/i', $file)) {
                continue;
            }

            $local_file = $target_dir . basename($file);
            if (ftp_get($conn_id, $local_file, $file, FTP_BINARY)) {
                $downloaded_files[] = basename($file);
                MHI_Logger::info('Pobrano plik: ' . $file);
            } else {
                MHI_Logger::error('Nie udało się pobrać pliku: ' . $file);
            }
        }

        // Zamknij połączenie
        ftp_close($conn_id);

        return $downloaded_files;
    }

    /**
     * Generuje plik XML dla WooCommerce na podstawie danych z hurtowni.
     * 
     * @return string|false Nazwa pliku lub false w przypadku błędu.
     */
    public function generate_wc_xml()
    {
        try {
            MHI_Logger::info("Rozpoczęcie generowania pliku XML dla WooCommerce (hurtownia {$this->name})");

            // Sprawdź czy katalog uploads/wholesale/{$this->name} istnieje
            $upload_dir = wp_upload_dir();
            $hurtownia_dir = $upload_dir['basedir'] . "/wholesale/{$this->name}/";

            if (!file_exists($hurtownia_dir)) {
                MHI_Logger::error("Błąd: Katalog {$hurtownia_dir} nie istnieje.");
                return false;
            }

            // Utwórz generator XML
            require_once MHI_PLUGIN_DIR . 'integrations/class-mhi-macma-wc-xml-generator.php';
            $generator = new MHI_Macma_WC_XML_Generator($hurtownia_dir);

            // Generuj plik XML
            $result = $generator->generate_woocommerce_xml();

            // Nazwa pliku wynikowego powinna być zgodna z tą tworzoną przez generator
            $output_file = 'woocommerce_import_' . $this->name . '.xml';

            if ($result) {
                MHI_Logger::info("Pomyślnie wygenerowano plik XML: {$output_file}");

                // Zapisz informację o ostatnim przetwarzaniu do opcji
                update_option('mhi_hurtownia_3_last_xml_file', $output_file);
                update_option('mhi_hurtownia_3_last_xml_date', current_time('mysql'));

                return $output_file;
            } else {
                MHI_Logger::error("Błąd: Nie udało się wygenerować pliku XML dla hurtowni {$this->name}.");
                return false;
            }
        } catch (Exception $e) {
            MHI_Logger::error("Wyjątek podczas generowania XML dla hurtowni {$this->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Importuje produkty z pliku XML do WooCommerce.
     *
     * @return string Informacja o wyniku importu.
     * @throws Exception W przypadku błędu podczas importu.
     */
    public function import_products_to_woocommerce()
    {
        throw new Exception(__('Funkcja importu produktów została wyłączona.', 'multi-hurtownie-integration'));
    }
}
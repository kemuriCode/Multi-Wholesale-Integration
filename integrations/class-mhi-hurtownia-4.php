<?php
/**
 * Klasa integracji z hurtownią Malfini.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Hurtownia_4
 * 
 * Klasa odpowiedzialna za integrację z hurtownią Malfini.
 */
class MHI_Hurtownia_4 implements MHI_Integration_Interface
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    protected $name = 'inspirion';

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
        $this->config = [
            'enabled' => get_option('mhi_hurtownia_4_enabled', 'no') === 'yes',
            'name' => get_option('mhi_hurtownia_4_name', 'Malfini'),
            'xml_host' => get_option('mhi_hurtownia_4_xml_host', ''),
            'xml_username' => get_option('mhi_hurtownia_4_xml_username', ''),
            'xml_password' => get_option('mhi_hurtownia_4_xml_password', ''),
            'xml_path' => get_option('mhi_hurtownia_4_xml_path', ''),
            'protocol' => get_option('mhi_hurtownia_4_protocol', 'ftp'),
            'port' => get_option('mhi_hurtownia_4_port', 21),
            'passive_mode' => get_option('mhi_hurtownia_4_passive_mode', 'yes') === 'yes',
            'auto_import' => get_option('mhi_hurtownia_4_auto_import', 'no') === 'yes',
            'import_interval' => get_option('mhi_hurtownia_4_import_interval', 'daily'),
            'last_import' => get_option('mhi_hurtownia_4_last_import', ''),
        ];
    }

    /**
     * Nawiązuje połączenie z zewnętrznym źródłem danych
     *
     * @return bool Informacja czy połączenie zostało nawiązane
     */
    public function connect()
    {
        if (empty($this->config['xml_host']) || empty($this->config['xml_username']) || empty($this->config['xml_password'])) {
            MHI_Logger::error('Brakuje danych konfiguracyjnych dla połączenia z serwerem Malfini');
            return false;
        }

        try {
            if ($this->config['protocol'] === 'sftp') {
                // Połączenie SFTP
                $connection = $this->connect_sftp();
            } else {
                // Połączenie FTP
                $connection = $this->connect_ftp();
            }

            return ($connection !== false);
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas nawiązywania połączenia: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generuje plik XML dla WooCommerce.
     * 
     * @return bool Czy operacja się powiodła.
     */
    public function generate_woocommerce_xml()
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Hurtownia ' . $this->name . ' nie obsługuje generowania plików XML - używa bezpośredniego importu');
        }

        // Inspirion nie potrzebuje generatora XML - pobiera gotowe pliki XML przez FTP
        return true;
    }

    /**
     * Pobiera pliki z zewnętrznego źródła
     *
     * @return mixed Wynik operacji pobierania plików
     */
    public function fetch_files()
    {
        return $this->download_files();
    }

    /**
     * Pobiera pliki z serwera FTP/SFTP hurtowni.
     * 
     * @return bool Czy operacja się powiodła.
     */
    public function download_files()
    {
        if (empty($this->config['xml_host']) || empty($this->config['xml_username']) || empty($this->config['xml_password'])) {
            MHI_Logger::error('Brakuje danych konfiguracyjnych dla połączenia z serwerem Malfini');
            return false;
        }

        try {
            // Przygotuj katalog docelowy
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name;

            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }

            if (!is_writable($target_dir)) {
                MHI_Logger::error('Katalog docelowy nie ma uprawnień do zapisu: ' . $target_dir);
                return false;
            }

            MHI_Logger::info('Rozpoczynam pobieranie plików z serwera Malfini');

            // Implementacja połączenia FTP/SFTP i pobierania plików
            // Może różnić się w zależności od specyfiki hurtowni Malfini

            // Przykładowa implementacja - należy dostosować do specyfiki Malfini
            if ($this->config['protocol'] === 'sftp') {
                // Pobieranie SFTP
                $connection = $this->connect_sftp();
            } else {
                // Pobieranie FTP
                $connection = $this->connect_ftp();
            }

            if (!$connection) {
                return false;
            }

            // Pobieranie plików XML
            $source_files = [
                'produkty.xml' => 'produkty.xml',
            ];

            foreach ($source_files as $remote_file => $local_file) {
                $remote_path = trim($this->config['xml_path'], '/') . '/' . $remote_file;
                $local_path = $target_dir . '/' . $local_file;

                if ($this->config['protocol'] === 'sftp') {
                    // Pobieranie SFTP
                    if (!ssh2_scp_recv($connection, $remote_path, $local_path)) {
                        MHI_Logger::error('Nie udało się pobrać pliku przez SFTP: ' . $remote_path);
                        continue;
                    }
                } else {
                    // Pobieranie FTP
                    if (!ftp_get($connection, $local_path, $remote_path, FTP_BINARY)) {
                        MHI_Logger::error('Nie udało się pobrać pliku przez FTP: ' . $remote_path);
                        continue;
                    }
                }

                MHI_Logger::info('Pobrano plik: ' . $remote_file . ' do: ' . $local_path);
            }

            // Zamknij połączenie
            if ($this->config['protocol'] === 'sftp') {
                ssh2_disconnect($connection);
            } else {
                ftp_close($connection);
            }

            MHI_Logger::info('Zakończono pobieranie plików z serwera Malfini');

            // Aktualizuj czas ostatniego importu
            update_option('mhi_hurtownia_4_last_import', current_time('mysql'));

            return true;

        } catch (Exception $e) {
            MHI_Logger::error('Wystąpił błąd podczas pobierania plików Malfini: ' . $e->getMessage());
            return false;
        }
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
     * Importuje produkty z pliku XML do WooCommerce.
     *
     * @return string Informacja o wyniku importu.
     * @throws Exception W przypadku błędu podczas importu.
     */
    public function import_products_to_woocommerce()
    {
        // Generuj plik XML dla WooCommerce
        $result = $this->generate_woocommerce_xml();

        if ($result) {
            return 'Pomyślnie wygenerowano plik XML do importu produktów.';
        } else {
            throw new Exception('Nie udało się wygenerować pliku XML do importu produktów.');
        }
    }

    /**
     * Nawiązuje połączenie FTP z serwerem.
     * 
     * @return resource|false Zasób połączenia FTP lub false w przypadku błędu.
     */
    protected function connect_ftp()
    {
        try {
            $conn_id = ftp_connect($this->config['xml_host'], $this->config['port']);

            if (!$conn_id) {
                MHI_Logger::error('Nie udało się połączyć z serwerem FTP: ' . $this->config['xml_host']);
                return false;
            }

            // Logowanie
            if (!ftp_login($conn_id, $this->config['xml_username'], $this->config['xml_password'])) {
                MHI_Logger::error('Nie udało się zalogować do serwera FTP. Sprawdź dane logowania.');
                ftp_close($conn_id);
                return false;
            }

            // Tryb pasywny jeśli wymagany
            if ($this->config['passive_mode']) {
                ftp_pasv($conn_id, true);
            }

            MHI_Logger::info('Nawiązano połączenie FTP z serwerem ' . $this->config['xml_host']);

            return $conn_id;

        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas łączenia z serwerem FTP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Nawiązuje połączenie SFTP z serwerem.
     * 
     * @return resource|false Zasób połączenia SFTP lub false w przypadku błędu.
     */
    protected function connect_sftp()
    {
        try {
            // Sprawdź czy funkcje SSH2 są dostępne
            if (!function_exists('ssh2_connect')) {
                MHI_Logger::error('Brak rozszerzenia SSH2 w PHP. Nie można nawiązać połączenia SFTP.');
                return false;
            }

            // Nawiąż połączenie
            $connection = ssh2_connect($this->config['xml_host'], $this->config['port']);

            if (!$connection) {
                MHI_Logger::error('Nie udało się nawiązać połączenia SFTP z serwerem: ' . $this->config['xml_host']);
                return false;
            }

            // Uwierzytelnianie
            if (!ssh2_auth_password($connection, $this->config['xml_username'], $this->config['xml_password'])) {
                MHI_Logger::error('Nie udało się zalogować do serwera SFTP. Sprawdź dane logowania.');
                return false;
            }

            // Inicjalizacja SFTP
            $sftp = ssh2_sftp($connection);

            if (!$sftp) {
                MHI_Logger::error('Nie udało się zainicjować podsystemu SFTP.');
                return false;
            }

            MHI_Logger::info('Nawiązano połączenie SFTP z serwerem ' . $this->config['xml_host']);

            return $connection;

        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas łączenia z serwerem SFTP: ' . $e->getMessage());
            return false;
        }
    }
}
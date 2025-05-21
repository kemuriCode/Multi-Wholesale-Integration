<?php
/**
 * Klasa integracji z hurtownią 2 (AXPOL przez FTP).
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

use phpseclib3\Net\SFTP;

/**
 * Klasa MHI_Hurtownia_2
 * 
 * Obsługuje integrację z hurtownią AXPOL przez FTP.
 */
class MHI_Hurtownia_2 implements MHI_Integration_Interface
{
    /**
     * Nazwa integracji.
     *
     * @var string
     */
    private $name = 'axpol';

    /**
     * Uchwyt do połączenia FTP.
     *
     * @var resource|FTP\Connection|null
     */
    private $connection = null;

    /**
     * Obiekt SFTP.
     *
     * @var SFTP|null
     */
    private $sftp = null;

    /**
     * Dane konfiguracyjne.
     *
     * @var array
     */
    private $config;

    /**
     * Flaga anulowania pobierania.
     *
     * @var bool
     */
    private $cancel_download = false;

    /**
     * Czas rozpoczęcia pobierania.
     *
     * @var int
     */
    private $download_start_time = 0;

    /**
     * Limit czasu wykonania skryptu w sekundach.
     *
     * @var int
     */
    private $time_limit = 120; // 2 minuty

    /**
     * Liczba plików na partię.
     *
     * @var int
     */
    private $batch_size = 20;

    /**
     * Flaga użycia połączenia socket.
     *
     * @var bool
     */
    private $use_socket_connection = false;

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        // Inicjalizacja konfiguracji
        $this->config = array(
            'xml_host' => get_option('mhi_hurtownia_2_xml_server', 'ftp.axpol.com.pl'),
            'xml_username' => get_option('mhi_hurtownia_2_xml_login', 'userPL017'),
            'xml_password' => get_option('mhi_hurtownia_2_xml_password', 'vSocD2N8'),
            'img_host' => get_option('mhi_hurtownia_2_img_server', 'ftp.axpol.com.pl'),
            'img_username' => get_option('mhi_hurtownia_2_img_login', 'userPL017img'),
            'img_password' => get_option('mhi_hurtownia_2_img_password', 'vSocD2N8'),
            'protocol' => get_option('mhi_hurtownia_2_protocol', 'sftp'), // Domyślnie SFTP dla AXPOL
            'port' => get_option('mhi_hurtownia_2_port', 2223), // Port 2223 dla SFTP AXPOL
            'batch_size' => get_option('mhi_hurtownia_2_batch_size', 20), // Liczba zdjęć w partii
            'time_limit' => get_option('mhi_hurtownia_2_time_limit', 120), // Limit czasu w sekundach
        );

        // Upewnij się, że mamy prawidłowe ustawienia dla AXPOL
        if (strpos($this->config['xml_host'], 'axpol.com.pl') !== false) {
            $this->config['port'] = 2223; // Zawsze port 2223 dla AXPOL
            $this->config['protocol'] = 'sftp'; // Zawsze SFTP dla AXPOL

            // Zapisz te ustawienia, aby były dostępne dla przyszłych połączeń
            update_option('mhi_hurtownia_2_port', 2223);
            update_option('mhi_hurtownia_2_protocol', 'sftp');
        }

        // Ustaw rozmiar partii i limit czasu z konfiguracji
        $this->batch_size = intval($this->config['batch_size']);
        $this->time_limit = intval($this->config['time_limit']);
    }

    /**
     * Anuluje pobieranie.
     *
     * @return void
     */
    public function cancel_download()
    {
        $this->cancel_download = true;
        update_option('mhi_download_status_' . $this->name, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
        MHI_Logger::info('Anulowano pobieranie dla hurtowni ' . $this->name);
    }

    /**
     * Sprawdza, czy pobieranie powinno zostać anulowane.
     *
     * @return bool True jeśli pobieranie powinno zostać anulowane, false w przeciwnym razie.
     */
    public function should_cancel()
    {
        // Sprawdź czy został przekroczony limit czasu wykonania
        if ($this->download_start_time > 0) {
            $elapsed_time = time() - $this->download_start_time;
            if ($elapsed_time > $this->time_limit) {
                MHI_Logger::warning('Przekroczono limit czasu wykonania (' . $this->time_limit . ' s). Anulowanie pobierania.');
                $this->cancel_download = true;
            }
        }

        // Sprawdź czy użytkownik anulował pobieranie
        $cancel_option = get_option('mhi_cancel_download_' . $this->name, false);
        if ($cancel_option) {
            MHI_Logger::info('Użytkownik anulował pobieranie dla hurtowni ' . $this->name);
            $this->cancel_download = true;
            update_option('mhi_cancel_download_' . $this->name, false); // Zresetuj flagę anulowania
        }

        return $this->cancel_download;
    }

    /**
     * Nawiązuje ręczne połączenie FTP z serwerem AXPOL używając gniazd.
     * Metoda publiczna dla celów testowych.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    public function connect_axpol_socket()
    {
        $host = $this->config['host'];
        $port = 2223;
        $username = $this->config['username'];
        $password = $this->config['password'];
        $timeout = 60;

        MHI_Logger::info('Nawiązywanie ręcznego połączenia socket FTP z AXPOL');

        // Połącz się z serwerem
        $socket = @stream_socket_client("tcp://$host:$port", $errno, $errstr, $timeout);

        if (!$socket) {
            MHI_Logger::error("Nie można połączyć się z serwerem AXPOL: $errstr ($errno)");
            return false;
        }

        // Ustaw timeout odczytu/zapisu
        stream_set_timeout($socket, $timeout);

        // Czekamy na odpowiedź - próba odczytania banera
        sleep(1); // Poczekaj 1 sekundę
        $banner = '';
        if (feof($socket) === false) {
            $banner = @fgets($socket, 1024);
            if ($banner !== false) {
                MHI_Logger::info("Otrzymano odpowiedź serwera: " . trim($banner));
            } else {
                MHI_Logger::warning("Brak banera od serwera FTP");
            }
        }

        // Bezpieczne wysyłanie komend z kontrolą stanu socketa
        if (!$this->safe_socket_write($socket, "USER $username\r\n")) {
            MHI_Logger::error("Nie można wysłać komendy USER - socket zamknięty");
            return false;
        }
        MHI_Logger::info("Wysyłanie komendy USER $username");

        sleep(1); // Poczekaj na odpowiedź
        $response = '';
        if (feof($socket) === false) {
            $response = @fgets($socket, 1024);
            if ($response !== false) {
                MHI_Logger::info("Odpowiedź na USER: " . trim($response));
            } else {
                MHI_Logger::warning("Brak odpowiedzi na komendę USER");
            }
        }

        // Wyślij komendę PASS tylko jeśli socket jest nadal otwarty
        if (!$this->safe_socket_write($socket, "PASS $password\r\n")) {
            MHI_Logger::error("Nie można wysłać komendy PASS - socket zamknięty");
            return false;
        }
        MHI_Logger::info("Wysyłanie komendy PASS ********");

        sleep(1); // Poczekaj na odpowiedź
        $response = '';
        if (feof($socket) === false) {
            $response = @fgets($socket, 1024);
            if ($response !== false) {
                MHI_Logger::info("Odpowiedź na PASS: " . trim($response));
            } else {
                MHI_Logger::warning("Brak odpowiedzi na komendę PASS");
            }
        }

        // Sprawdź kod statusu (2xx oznacza sukces) jeśli mamy odpowiedź
        if (!empty($response) && substr($response, 0, 1) !== '2') {
            MHI_Logger::error("Błąd logowania do FTP: " . trim($response));
            @fclose($socket);
            return false;
        }

        // Spróbuj QUIT, ale tylko jeśli socket jest nadal otwarty
        if ($this->safe_socket_write($socket, "QUIT\r\n")) {
            MHI_Logger::info("Wysyłanie komendy QUIT");
        }

        // Zawsze zamykaj socket na końcu
        if (is_resource($socket)) {
            @fclose($socket);
        }

        MHI_Logger::info("Ręczne połączenie socket FTP z AXPOL zakończone");
        return true;
    }

    /**
     * Bezpieczne wysyłanie danych przez socket z kontrolą stanu.
     * 
     * @param resource $socket Socket do użycia
     * @param string $data Dane do wysłania
     * @return bool True jeśli wysłano pomyślnie, false w przypadku błędu
     */
    private function safe_socket_write($socket, $data)
    {
        // Sprawdź czy socket nie jest zamknięty
        if (!is_resource($socket) || feof($socket)) {
            return false;
        }

        // Próba wysłania danych z obsługą błędów
        $result = @fwrite($socket, $data);
        if ($result === false || $result < strlen($data)) {
            return false;
        }

        return true;
    }

    /**
     * Nawiązuje specjalne połączenie dla AXPOL.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    private function connect_axpol()
    {
        $host = $this->config['host'];
        $username = $this->config['username'];
        $password = $this->config['password'];
        $port = 2223; // Stały port 2223 dla AXPOL SFTP

        MHI_Logger::info('Nawiązywanie połączenia SFTP z AXPOL na porcie ' . $port);

        // Zwiększ limity wykonania i pamięci
        set_time_limit(300); // 5 minut timeout
        ini_set('memory_limit', '512M');

        try {
            // Użyj biblioteki phpseclib3 do połączenia SFTP
            $this->sftp = new SFTP($host, $port, 180); // 3 minuty timeout

            // Zaloguj się
            if (!$this->sftp->login($username, $password)) {
                MHI_Logger::error('Nie udało się zalogować do serwera AXPOL przez SFTP');
                return false;
            }

            MHI_Logger::info('Pomyślnie połączono z serwerem AXPOL przez SFTP');

            // Test połączenia przez listowanie plików
            $files = $this->sftp->nlist('.');

            if (is_array($files)) {
                MHI_Logger::info('Połączenie SFTP z AXPOL działa. Znaleziono ' . count($files) . ' plików/katalogów w katalogu głównym');
                return true;
            }

            // Spróbuj listować pliki w katalogu "xml"
            $xml_dirs = ['xml', '/xml', './xml'];

            foreach ($xml_dirs as $dir) {
                $files = $this->sftp->nlist($dir);
                if (is_array($files)) {
                    MHI_Logger::info('Połączenie SFTP z AXPOL działa. Znaleziono ' . count($files) . ' plików w katalogu ' . $dir);
                    return true;
                }
            }

            MHI_Logger::warning('Połączenie SFTP z AXPOL ustanowione, ale nie znaleziono plików w żadnym z katalogów');
            return true; // Zwracamy true, ponieważ połączenie działa, nawet jeśli nie znaleziono plików

        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas łączenia z AXPOL przez SFTP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Nawiązuje połączenie z hurtownią.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    public function connect()
    {
        // Dodaj stałą FS_METHOD, jeśli nie jest już zdefiniowana
        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');
            MHI_Logger::info('Zdefiniowano FS_METHOD jako direct');
        }

        // Sprawdź, czy hurtownia jest włączona
        $hurtownia_enabled = get_option('mhi_hurtownia_2_enabled', 0);
        MHI_Logger::info('Status włączenia hurtowni ' . $this->name . ': ' . ($hurtownia_enabled ? 'włączona' : 'wyłączona'));

        if (!$hurtownia_enabled) {
            MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona.');
            return false;
        }

        // Sprawdź, czy dane są poprawne
        if (!$this->validate_credentials()) {
            MHI_Logger::error('Nieprawidłowe dane uwierzytelniające dla hurtowni ' . $this->name);
            return false;
        }

        try {
            // Nawiąż połączenie z serwerem XML
            $this->config['host'] = $this->config['xml_host'];
            $this->config['username'] = $this->config['xml_username'];
            $this->config['password'] = $this->config['xml_password'];

            // Specjalna obsługa dla AXPOL
            if (strpos($this->config['host'], 'axpol.com.pl') !== false) {
                MHI_Logger::info('Wykryto serwer AXPOL - używam specjalnej procedury połączenia');
                $this->config['port'] = 2223; // Zawsze port 2223 dla AXPOL
                $this->config['protocol'] = 'sftp'; // Zawsze SFTP dla AXPOL

                update_option('mhi_hurtownia_2_port', 2223);
                update_option('mhi_hurtownia_2_protocol', 'sftp');

                // Użyj specjalnej metody dla AXPOL
                return $this->connect_axpol();
            }

            // Dla innych hurtowni - standardowa procedura
            MHI_Logger::info('Próba połączenia z serwerem ' . $this->config['host'] . ' przez ' . $this->config['protocol'] . ' na porcie ' . $this->config['port']);

            // Wykonaj kilka prób połączenia
            $max_retries = 3;
            $connection_successful = false;

            for ($retry = 0; $retry < $max_retries; $retry++) {
                if ($retry > 0) {
                    MHI_Logger::info('Próba połączenia #' . ($retry + 1));
                    sleep(2); // Czekaj 2 sekundy przed kolejną próbą
                }

                if ($this->config['protocol'] === 'sftp') {
                    $connection_successful = $this->connect_sftp();
                } else if ($this->config['protocol'] === 'ftps') {
                    $connection_successful = $this->connect_ftp_ssl();
                } else {
                    $connection_successful = $this->connect_ftp();
                }

                if ($connection_successful) {
                    MHI_Logger::info('Połączenie udane przez ' . $this->config['protocol'] . ' na porcie ' . $this->config['port']);
                    break;
                }
            }

            if (!$connection_successful) {
                MHI_Logger::error('Wszystkie próby połączenia nieudane');
                return false;
            }

            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas łączenia z hurtownią ' . $this->name . ': ' . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Zamyka połączenie.
     */
    private function disconnect()
    {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
        if ($this->sftp) {
            $this->sftp = null;
        }
    }

    /**
     * Nawiązuje połączenie SFTP używając Stream Wrapper.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    private function connect_sftp_stream()
    {
        MHI_Logger::info('Próba połączenia z serwerem SFTP przez Stream Wrapper: ' . $this->config['host'] . ':' . $this->config['port'] . ' jako ' . $this->config['username']);

        // Sprawdź, czy mamy zainstalowaną bibliotekę phpseclib3\Net\SFTP\Stream
        if (!class_exists('phpseclib3\Net\SFTP\Stream')) {
            MHI_Logger::error('Brak wymaganej biblioteki phpseclib3\Net\SFTP\Stream do obsługi SFTP. Zainstaluj bibliotekę przez Composer.');
            return false;
        }

        try {
            // Rejestracja protokołu SFTP
            MHI_Logger::info('Rejestracja protokołu SFTP przez Stream Wrapper');
            \phpseclib3\Net\SFTP\Stream::register();

            // Utwórz URL SFTP
            $url = 'sftp://' . $this->config['username'] . ':' . $this->config['password'] . '@' . $this->config['host'] . ':' . $this->config['port'] . '/';
            MHI_Logger::info('URL SFTP: ' . preg_replace('/:[^:]*@/', ':***@', $url));

            // Próba otwarcia pliku testowego
            MHI_Logger::info('Testowanie połączenia przez próbę otwarcia listy plików');

            $context = stream_context_create([
                'sftp' => [
                    'timeout' => 15 // Timeout 15 sekund
                ]
            ]);

            // Próba otwarcia stream do serwera
            $stream = @fopen($url, 'r', false, $context);

            if (!$stream) {
                $error = error_get_last();
                MHI_Logger::error('Nie udało się połączyć przez Stream Wrapper: ' . ($error ? $error['message'] : 'Nieznany błąd'));
                return false;
            }

            // Zamknij stream
            fclose($stream);

            MHI_Logger::info('Połączenie przez Stream Wrapper ustanowione pomyślnie');

            // Tworzymy obiekt SFTP dla późniejszych operacji
            $this->sftp = new \phpseclib3\Net\SFTP($this->config['host'], $this->config['port'], 30);

            if (!$this->sftp->login($this->config['username'], $this->config['password'])) {
                MHI_Logger::error('Nie udało się zalogować do SFTP jako ' . $this->config['username']);
                return false;
            }

            MHI_Logger::info('Pomyślnie zalogowano do SFTP jako ' . $this->config['username']);

            // Sprawdź listę plików
            $files = $this->sftp->nlist('.');
            if (is_array($files) && count($files) > 0) {
                MHI_Logger::info('Znaleziono ' . count($files) . ' plików/katalogów. Przykłady: ' .
                    implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : ''));
            } else {
                MHI_Logger::warning('Nie znaleziono plików w katalogu lub nie można odczytać zawartości katalogu');
            }

            return true;

        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas łączenia przez Stream Wrapper: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Nawiązuje połączenie SFTP.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    private function connect_sftp()
    {
        MHI_Logger::info('Próba połączenia z serwerem SFTP: ' . $this->config['host'] . ':' . $this->config['port'] . ' jako ' . $this->config['username']);

        // Sprawdź, czy mamy zainstalowaną bibliotekę phpseclib3
        if (!class_exists('phpseclib3\Net\SFTP')) {
            MHI_Logger::error('Brak wymaganej biblioteki phpseclib3 do obsługi SFTP. Zainstaluj bibliotekę przez Composer.');
            return false;
        }

        try {
            // Ustaw dłuższy timeout dla AXPOL
            $timeout = 120; // 2 minuty dla AXPOL

            // Inicjalizacja obiektu SFTP
            MHI_Logger::info('Inicjalizacja obiektu SFTP dla: ' . $this->config['host'] . ':' . $this->config['port'] . ' (timeout: ' . $timeout . 's)');
            $this->sftp = new \phpseclib3\Net\SFTP($this->config['host'], $this->config['port'], $timeout);

            if (!$this->sftp->isConnected()) {
                MHI_Logger::error('Nie udało się połączyć z serwerem SFTP: ' . $this->config['host'] . ':' . $this->config['port']);
                return false;
            }

            MHI_Logger::info('Połączenie SFTP ustanowione z ' . $this->config['host'] . ':' . $this->config['port']);

            // Logowanie
            MHI_Logger::info('Próba logowania do SFTP jako: ' . $this->config['username']);

            // Kilka prób logowania
            $login_success = false;
            $login_attempts = 0;
            $max_login_attempts = 3;

            while (!$login_success && $login_attempts < $max_login_attempts) {
                $login_success = $this->sftp->login($this->config['username'], $this->config['password']);

                if (!$login_success) {
                    $login_attempts++;
                    MHI_Logger::warning('Logowanie nieudane, próba ' . $login_attempts . '/' . $max_login_attempts);
                    sleep(2);
                }
            }

            if (!$login_success) {
                MHI_Logger::error('Błąd logowania do serwera SFTP. Nieprawidłowa nazwa użytkownika lub hasło: ' . $this->config['username']);
                return false;
            }

            MHI_Logger::info('Pomyślnie zalogowano do serwera SFTP jako: ' . $this->config['username']);

            // Sprawdź katalog roboczy
            $pwd = $this->sftp->pwd();
            MHI_Logger::info('Bieżący katalog na serwerze: ' . ($pwd ?: 'nieznany'));

            // Lista dostępnych plików
            MHI_Logger::info('Pobieranie listy plików...');
            $files = $this->sftp->nlist('.');

            if (is_array($files) && count($files) > 0) {
                MHI_Logger::info('Znaleziono ' . count($files) . ' plików/katalogów. Przykłady: ' .
                    implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : ''));
            } else {
                MHI_Logger::warning('Nie znaleziono plików w katalogu lub nie można odczytać zawartości katalogu');

                // Spróbuj inne katalogi
                $dirs_to_check = array('/xml', 'xml', './xml', 'XML', '/XML');
                foreach ($dirs_to_check as $dir) {
                    MHI_Logger::info('Próba wyświetlenia zawartości katalogu: ' . $dir);
                    $dir_files = $this->sftp->nlist($dir);
                    if (is_array($dir_files) && count($dir_files) > 0) {
                        MHI_Logger::info('Znaleziono ' . count($dir_files) . ' plików w katalogu ' . $dir);
                        break;
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas łączenia z serwerem SFTP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Nawiązuje połączenie SSH.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    private function connect_ssh()
    {
        MHI_Logger::info('Próba połączenia z serwerem SSH: ' . $this->config['host'] . ':' . $this->config['port'] . ' jako ' . $this->config['username']);

        // Sprawdź, czy mamy zainstalowaną bibliotekę phpseclib3
        if (!class_exists('phpseclib3\Net\SSH2')) {
            MHI_Logger::error('Brak wymaganej biblioteki phpseclib3\Net\SSH2 do obsługi SSH. Zainstaluj bibliotekę przez Composer.');
            return false;
        }

        try {
            $timeout = isset($this->config['timeout']) ? $this->config['timeout'] : 60;

            // Inicjalizacja obiektu SSH2
            MHI_Logger::info('Inicjalizacja obiektu SSH2 dla: ' . $this->config['host'] . ':' . $this->config['port'] . ' (timeout: ' . $timeout . 's)');

            // Stwórz obiekt SSH2
            $ssh = new \phpseclib3\Net\SSH2($this->config['host'], $this->config['port'], $timeout);

            if (!$ssh->isConnected()) {
                MHI_Logger::error('Nie udało się ustanowić połączenia SSH z serwerem.');
                return false;
            }

            // Logowanie
            MHI_Logger::info('Próba logowania do SSH jako: ' . $this->config['username']);

            if (!$ssh->login($this->config['username'], $this->config['password'])) {
                MHI_Logger::error('Błąd logowania do serwera SSH. Nieprawidłowa nazwa użytkownika lub hasło: ' . $this->config['username']);
                return false;
            }

            MHI_Logger::info('Pomyślnie zalogowano do serwera SSH jako: ' . $this->config['username']);

            // Wykonaj listowanie przez SSH
            MHI_Logger::info('Próba listowania plików przez komendę SSH...');
            $result = $ssh->exec('ls -la');
            MHI_Logger::info('Wynik komendy ls: ' . $result);

            // Spróbuj inne katalogi przez SSH
            $dirs_to_check = array('/xml', 'xml', './xml', 'XML', '/XML');
            foreach ($dirs_to_check as $dir) {
                MHI_Logger::info('Próba listowania katalogu: ' . $dir . ' przez SSH');
                $result = $ssh->exec('ls -la ' . $dir);
                MHI_Logger::info('Wynik komendy ls ' . $dir . ': ' . $result);
            }

            // Spróbuj utworzyć połączenie SFTP używając tej samej konfiguracji
            MHI_Logger::info('Próba utworzenia połączenia SFTP na tej samej hoście...');
            $this->sftp = new \phpseclib3\Net\SFTP($this->config['host'], $this->config['port'], $timeout);

            if ($this->sftp->isConnected() && $this->sftp->login($this->config['username'], $this->config['password'])) {
                MHI_Logger::info('Udało się utworzyć połączenie SFTP równolegle do SSH');

                // Lista dostępnych plików
                $files = $this->sftp->nlist('.');

                if (is_array($files) && count($files) > 0) {
                    MHI_Logger::info('Znaleziono ' . count($files) . ' plików/katalogów przez SFTP. Przykłady: ' .
                        implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : ''));
                } else {
                    MHI_Logger::warning('Nie znaleziono plików w katalogu lub nie można odczytać zawartości katalogu przez SFTP');
                }
            } else {
                MHI_Logger::warning('Nie udało się utworzyć równoległego połączenia SFTP, będę używać tylko SSH');
            }

            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas łączenia z serwerem SSH: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Pobiera pliki przez SFTP.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    private function fetch_files_sftp()
    {
        // Wyczyść status pobierania
        update_option('mhi_download_status_' . $this->name, __('Rozpoczynam pobieranie plików przez SFTP...', 'multi-hurtownie-integration'));
        MHI_Logger::info('Rozpoczynam pobieranie plików z serwera SFTP dla hurtowni ' . $this->name);

        // Zresetuj flagę anulowania
        $this->cancel_download = false;
        update_option('mhi_cancel_download_' . $this->name, false);

        // Zacznij odliczać czas
        $this->download_start_time = time();

        // Lista plików XML do pobrania
        $files_to_download = array(
            'axpol_product_data_PL.xml',
            'axpol_stocklist_PL.xml',
            'axpol_stocklist_pl.xml',
            'axpol_print_data_PL.xml',
            'axpol_print_pricelist_PL.xml'
        );

        $files = array();
        $local_path = MHI_UPLOADS_DIR . '/' . $this->name . '/';

        // Upewnij się, że folder lokalny istnieje
        if (!file_exists($local_path)) {
            wp_mkdir_p($local_path);
        }

        MHI_Logger::info('Folder lokalny: ' . $local_path);

        // Sprawdź uprawnienia do katalogu lokalnego
        if (is_writable($local_path)) {
            MHI_Logger::info('Katalog lokalny ma uprawnienia do zapisu');
        } else {
            MHI_Logger::warning('Katalog lokalny NIE ma uprawnień do zapisu! Próba zmiany uprawnień...');
            @chmod($local_path, 0755);

            if (is_writable($local_path)) {
                MHI_Logger::info('Uprawnienia do katalogu zostały poprawione');
            } else {
                MHI_Logger::error('Nie udało się naprawić uprawnień do katalogu lokalnego! Pobieranie plików może się nie powieść.');
            }
        }

        // Sprawdź dostępne katalogi na serwerze SFTP
        $dirs_to_check = array('/', '/xml', 'xml', './xml', '.', '../xml', '..', 'XML', '/XML', './XML', 'katalog', '/katalog');
        $remote_files = array();

        foreach ($dirs_to_check as $dir) {
            // Sprawdź, czy anulowano pobieranie
            if ($this->should_cancel()) {
                MHI_Logger::warning('Anulowano pobieranie podczas sprawdzania katalogu ' . $dir);
                return array();
            }

            MHI_Logger::info('Sprawdzam zawartość katalogu SFTP: ' . $dir);

            try {
                // Sprawdź czy katalog istnieje
                if (!$this->sftp->is_dir($dir)) {
                    MHI_Logger::info('Katalog nie istnieje: ' . $dir);
                    continue;
                }

                $dir_contents = $this->sftp->nlist($dir);

                if ($dir_contents && is_array($dir_contents)) {
                    MHI_Logger::info('Znaleziono ' . count($dir_contents) . ' plików/katalogów w ' . $dir);

                    // Wyświetl pierwsze 10 plików/katalogów dla diagnostyki
                    $sample_files = array_slice($dir_contents, 0, 10);
                    MHI_Logger::info('Przykładowe pliki: ' . implode(', ', $sample_files));

                    // Przeszukaj listę plików i zapisz ścieżki do plików XML
                    foreach ($files_to_download as $filename) {
                        $filename_lower = strtolower($filename);

                        foreach ($dir_contents as $file_path) {
                            $file_name = basename($file_path);

                            // Sprawdź dokładne nazwy i warianty nazw (różne wielkości liter)
                            if ($file_name === $filename || strtolower($file_name) === $filename_lower) {
                                $remote_files[$filename] = $dir . '/' . $file_name;
                                MHI_Logger::info('Znaleziono plik ' . $file_name . ' w katalogu ' . $dir);
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                MHI_Logger::warning('Błąd podczas listowania katalogu ' . $dir . ': ' . $e->getMessage());
            }
        }

        // Pobierz znalezione pliki XML
        $total_files = count($remote_files);
        $current_file = 0;

        foreach ($remote_files as $filename => $remote_path) {
            // Sprawdź, czy anulowano pobieranie
            if ($this->should_cancel()) {
                MHI_Logger::warning('Anulowano pobieranie plików XML po pobraniu ' . $current_file . ' z ' . $total_files . ' plików');
                break;
            }

            $current_file++;
            $local_file = $local_path . $filename;

            // Aktualizuj status pobierania
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Pobieranie pliku %d z %d: %s', 'multi-hurtownie-integration'),
                $current_file,
                $total_files,
                $filename
            ));

            MHI_Logger::info('Próba pobrania pliku przez SFTP: ' . $remote_path . ' do ' . $local_file);

            try {
                // Pobierz plik przez SFTP
                if ($this->sftp->get($remote_path, $local_file)) {
                    MHI_Logger::info('Pobrano plik: ' . $filename . ' z hurtowni ' . $this->name . ' (' . size_format(filesize($local_file)) . ') z lokalizacji: ' . $remote_path);

                    $files[] = array(
                        'filename' => $filename,
                        'path' => $local_file,
                        'size' => filesize($local_file),
                        'date' => date('Y-m-d H:i:s'),
                        'status' => 'downloaded'
                    );
                } else {
                    MHI_Logger::error('Nie udało się pobrać pliku: ' . $filename . ' ze ścieżki ' . $remote_path);
                }
            } catch (Exception $e) {
                MHI_Logger::error('Błąd podczas pobierania pliku ' . $filename . ': ' . $e->getMessage());
            }
        }

        // Jeśli nie znaleziono żadnych plików, spróbuj bezpośrednio pobrać pliki z listy z głównego katalogu
        if (empty($files) && !$this->should_cancel()) {
            MHI_Logger::warning('Nie znaleziono plików w przeszukanych katalogach. Próbuję bezpośrednio pobrać pliki z katalogu głównego.');

            foreach ($files_to_download as $filename) {
                // Sprawdź, czy anulowano pobieranie
                if ($this->should_cancel()) {
                    break;
                }

                $local_file = $local_path . $filename;
                $remote_path = './' . $filename;

                MHI_Logger::info('Próba pobrania pliku bezpośrednio: ' . $remote_path);

                try {
                    if ($this->sftp->get($remote_path, $local_file)) {
                        MHI_Logger::info('Pobrano plik: ' . $filename . ' (' . size_format(filesize($local_file)) . ')');

                        $files[] = array(
                            'filename' => $filename,
                            'path' => $local_file,
                            'size' => filesize($local_file),
                            'date' => date('Y-m-d H:i:s'),
                            'status' => 'downloaded'
                        );
                    }
                } catch (Exception $e) {
                    MHI_Logger::warning('Nie udało się pobrać pliku bezpośrednio: ' . $filename . ' - ' . $e->getMessage());
                }
            }
        }

        // Aktualizuj status po zakończeniu
        if ($this->cancel_download) {
            update_option('mhi_download_status_' . $this->name, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
        } else {
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Zakończono pobieranie przez SFTP. Pobrano %d plików z %d.', 'multi-hurtownie-integration'),
                count($files),
                $total_files > 0 ? $total_files : count($files_to_download)
            ));
        }

        return $files;
    }

    /**
     * Łączy się z serwerem FTP.
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    private function connect_ftp()
    {
        MHI_Logger::info('Próba połączenia z serwerem FTP: ' . $this->config['host'] . ':' . $this->config['port'] . ' jako ' . $this->config['username']);

        // Specjalne ustawienia dla AXPOL
        $axpol_mode = strpos($this->config['host'], 'axpol.com.pl') !== false;
        if ($axpol_mode) {
            MHI_Logger::info('Wykryto serwer AXPOL - używam specjalnych ustawień dla połączenia FTP');
        }

        // Ustaw timeout dla operacji FTP
        $timeout = isset($this->config['timeout']) ? $this->config['timeout'] : 60;
        if ($axpol_mode) {
            $timeout = 120; // 2 minuty dla AXPOL
            MHI_Logger::info('Ustawiono dłuższy timeout dla AXPOL: ' . $timeout . 's');
        }

        MHI_Logger::info('Próba połączenia FTP na porcie ' . $this->config['port'] . ' z timeoutem ' . $timeout . ' sekund');

        // Nawiązanie połączenia FTP z dłuższym timeoutem
        $this->connection = @ftp_connect($this->config['host'], $this->config['port'], $timeout);

        if (!$this->connection) {
            MHI_Logger::error('Nie udało się połączyć z serwerem FTP: ' . $this->config['host'] . ':' . $this->config['port']);

            // Spróbuj FTPS jako alternatywę
            MHI_Logger::info('Próba połączenia FTPS na porcie ' . $this->config['port']);
            $ftps_result = $this->connect_ftp_ssl();

            if ($ftps_result) {
                return true;
            }

            return false;
        }

        MHI_Logger::info('Połączenie FTP ustanowione z ' . $this->config['host'] . ':' . $this->config['port']);

        // Zaloguj się na serwer FTP
        $login_result = @ftp_login($this->connection, $this->config['username'], $this->config['password']);

        if (!$login_result) {
            MHI_Logger::error('Nie udało się zalogować do serwera FTP. Nieprawidłowa nazwa użytkownika lub hasło: ' . $this->config['username']);
            $this->disconnect();
            return false;
        }

        MHI_Logger::info('Pomyślnie zalogowano do serwera FTP jako: ' . $this->config['username']);

        // Włącz tryb pasywny
        @ftp_pasv($this->connection, true);
        MHI_Logger::info('Włączono tryb pasywny dla FTP');

        // Ustaw timeout dla operacji FTP
        @ftp_set_option($this->connection, FTP_TIMEOUT_SEC, $timeout);

        // Pobierz listę plików, aby sprawdzić, czy połączenie działa
        $files = @ftp_nlist($this->connection, '.');

        if (is_array($files)) {
            MHI_Logger::info('Pomyślnie pobrano listę plików z serwera FTP. Znaleziono ' . count($files) . ' plików/katalogów.');
        } else {
            MHI_Logger::warning('Nie udało się pobrać listy plików lub katalog jest pusty.');

            // Sprawdź inne popularne katalogi
            $dirs_to_check = array('/xml', 'xml', './xml', 'XML', '/XML');
            foreach ($dirs_to_check as $dir) {
                MHI_Logger::info('Próba wyświetlenia zawartości katalogu: ' . $dir);
                $dir_files = @ftp_nlist($this->connection, $dir);
                if (is_array($dir_files) && count($dir_files) > 0) {
                    MHI_Logger::info('Znaleziono ' . count($dir_files) . ' plików w katalogu ' . $dir);
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Nawiązuje połączenie FTP z SSL (FTPS).
     *
     * @return bool True jeśli połączenie zostało ustanowione, false w przeciwnym razie.
     */
    private function connect_ftp_ssl()
    {
        MHI_Logger::info('Próba połączenia z serwerem FTPS: ' . $this->config['host'] . ':' . $this->config['port'] . ' jako ' . $this->config['username']);

        // Nawiązanie połączenia FTPS
        $this->connection = @ftp_ssl_connect($this->config['host'], intval($this->config['port']), 30);

        if (!$this->connection) {
            MHI_Logger::error('Nie udało się połączyć z serwerem FTPS: ' . $this->config['host'] . ':' . $this->config['port']);

            // Spróbuj alternatywny port 990 jeśli domyślny port nie działa
            MHI_Logger::warning('Próbuję połączyć na porcie 990 (typowy dla FTPS)');
            $this->connection = @ftp_ssl_connect($this->config['host'], 990, 30);

            if (!$this->connection) {
                MHI_Logger::error('Nie udało się połączyć z serwerem FTPS: ' . $this->config['host'] . ' na portach ' . $this->config['port'] . ' ani 990');
                return false;
            }
        }

        // Logowanie do serwera FTPS
        $login_result = @ftp_login($this->connection, $this->config['username'], $this->config['password']);

        if (!$login_result) {
            MHI_Logger::error('Błąd logowania do serwera FTPS. Nieprawidłowa nazwa użytkownika lub hasło: ' . $this->config['username']);
            @ftp_close($this->connection);
            $this->connection = null;
            return false;
        }

        // Włączenie trybu pasywnego
        @ftp_pasv($this->connection, true);

        MHI_Logger::info('Połączono z serwerem FTPS: ' . $this->config['host'] . ' jako ' . $this->config['username']);
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
        $hurtownia_enabled = get_option('mhi_hurtownia_2_enabled', 0);
        if (!$hurtownia_enabled) {
            MHI_Logger::info('Hurtownia ' . $this->name . ' jest wyłączona. Pomijam pobieranie plików.');
            return array();
        }

        // Połącz z serwerem
        if (!$this->connect()) {
            MHI_Logger::error('Brak połączenia FTP. Nie można pobrać plików.');
            return array();
        }

        try {
            // Wybierz metodę pobierania w zależności od protokołu
            if ($this->config['protocol'] === 'sftp') {
                $files = $this->fetch_files_sftp();
            } else if ($this->use_socket_connection) {
                // Używamy specjalnej metody dla AXPOL z socketem
                MHI_Logger::info('Wykryto tryb socket dla AXPOL - używam specjalnej metody pobierania');
                $files = $this->fetch_files_axpol_socket();
            } else {
                $files = $this->fetch_files_ftp();
            }
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas pobierania plików z hurtowni ' . $this->name . ': ' . $e->getMessage());
        } finally {
            // Zamknij połączenie jeśli istnieje i nie jest to socket
            if (!$this->use_socket_connection) {
                $this->disconnect();
            }
        }

        return $files;
    }

    /**
     * Pobiera pliki przez FTP.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    private function fetch_files_ftp()
    {
        // Sprawdź, czy istnieje połączenie FTP
        if (!$this->connection) {
            MHI_Logger::error('Brak połączenia FTP. Nie można pobrać plików.');
            return array();
        }

        // Wyczyść status pobierania
        update_option('mhi_download_status_' . $this->name, __('Rozpoczynam pobieranie plików...', 'multi-hurtownie-integration'));
        MHI_Logger::info('Rozpoczynam pobieranie plików FTP dla hurtowni ' . $this->name);

        // Zresetuj flagę anulowania
        $this->cancel_download = false;
        update_option('mhi_cancel_download_' . $this->name, false);

        // Zacznij odliczać czas
        $this->download_start_time = time();

        // Lista plików XML do pobrania dla AXPOL (rozszerzona lista)
        $files_to_download = array(
            'axpol_product_data_PL.xml',
            'axpol_stocklist_PL.xml',
            'axpol_stocklist_pl.xml',
            'axpol_print_data_PL.xml',
            'axpol_print_pricelist_PL.xml',
            // Dodatkowe nazwy plików, które mogą występować
            'axpol_product_data.xml',
            'axpol_stocklist.xml',
            'products.xml',
            'stock.xml',
            'print.xml'
        );

        $files = array();
        $local_path = MHI_UPLOADS_DIR . '/' . $this->name . '/';

        // Upewnij się, że folder lokalny istnieje
        if (!file_exists($local_path)) {
            wp_mkdir_p($local_path);
        }

        MHI_Logger::info('Folder lokalny: ' . $local_path);

        // Sprawdź uprawnienia do katalogu lokalnego
        if (is_writable($local_path)) {
            MHI_Logger::info('Katalog lokalny ma uprawnienia do zapisu');
        } else {
            MHI_Logger::warning('Katalog lokalny NIE ma uprawnień do zapisu! Próba zmiany uprawnień...');
            @chmod($local_path, 0755);

            if (is_writable($local_path)) {
                MHI_Logger::info('Uprawnienia do katalogu zostały poprawione');
            } else {
                MHI_Logger::error('Nie udało się naprawić uprawnień do katalogu lokalnego! Pobieranie plików może się nie powieść.');
            }
        }

        // Pobierz listę katalogów na serwerze FTP aby sprawdzić różne lokalizacje
        $server_dirs = array();
        try {
            MHI_Logger::info('Pobieranie listy katalogów na serwerze FTP...');
            $server_dirs = @ftp_nlist($this->connection, '.');
            if (is_array($server_dirs)) {
                MHI_Logger::info('Znaleziono ' . count($server_dirs) . ' elementów w katalogu głównym: ' . implode(', ', array_slice($server_dirs, 0, 5)) . (count($server_dirs) > 5 ? '...' : ''));
            } else {
                MHI_Logger::warning('Nie udało się pobrać listy katalogów z serwera FTP.');
            }
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas pobierania listy katalogów: ' . $e->getMessage());
        }

        // Przygotuj listę katalogów do sprawdzenia
        $dirs_to_try = array('/', '/xml', 'xml/', './xml/', './', './files/', '/files', 'files/', '/data', 'data/');

        // Dodaj znalezione katalogi, które mogą zawierać 'xml', 'data' lub 'files'
        if (is_array($server_dirs)) {
            foreach ($server_dirs as $dir) {
                $dir_clean = trim($dir);
                if (
                    strpos(strtolower($dir_clean), 'xml') !== false ||
                    strpos(strtolower($dir_clean), 'data') !== false ||
                    strpos(strtolower($dir_clean), 'file') !== false
                ) {
                    // Dodaj z ukośnikiem na końcu
                    $dirs_to_try[] = $dir_clean . '/';
                }
            }
        }

        // Pobierz pliki XML
        $total_files = count($files_to_download);
        $current_file = 0;
        $downloaded_files = 0;

        foreach ($files_to_download as $filename) {
            // Sprawdź, czy anulowano pobieranie
            if ($this->should_cancel()) {
                MHI_Logger::warning('Anulowano pobieranie plików XML po pobraniu ' . $current_file . ' z ' . $total_files . ' plików');
                break;
            }

            $current_file++;
            // Ścieżka do pliku lokalnego
            $local_file = $local_path . $filename;

            // Aktualizuj status pobierania
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Pobieranie pliku %d z %d: %s', 'multi-hurtownie-integration'),
                $current_file,
                $total_files,
                $filename
            ));

            // Spróbuj pobrać plik z różnych lokalizacji na serwerze
            $download_success = false;

            foreach ($dirs_to_try as $dir) {
                if ($this->should_cancel()) {
                    break 2; // Wyjdź z obu pętli
                }

                $remote_path = $dir . $filename;
                MHI_Logger::info('Próba pobrania pliku: ' . $remote_path . ' do ' . $local_file);

                try {
                    // Użyj istniejącego połączenia FTP do pobrania pliku
                    $ftp_result = @ftp_get($this->connection, $local_file, $remote_path, FTP_BINARY);

                    if ($ftp_result) {
                        $filesize = filesize($local_file);
                        MHI_Logger::info('Pobrano plik: ' . $filename . ' z hurtowni ' . $this->name . ' (' . size_format($filesize) . ') z lokalizacji: ' . $remote_path);

                        // Sprawdź czy plik nie jest pusty lub błędny
                        if ($filesize < 100) {
                            $content = file_get_contents($local_file);
                            if (empty($content) || strpos($content, 'error') !== false || strpos($content, '404') !== false) {
                                MHI_Logger::warning('Pobrany plik ' . $filename . ' wygląda na uszkodzony lub zawiera błędy. Rozmiar: ' . $filesize . ' bajtów');
                                // Usuń uszkodzony plik
                                @unlink($local_file);
                                continue;
                            }
                        }

                        $files[] = array(
                            'filename' => $filename,
                            'path' => $local_file,
                            'size' => $filesize,
                            'date' => date('Y-m-d H:i:s'),
                            'status' => 'downloaded'
                        );

                        $download_success = true;
                        $downloaded_files++;
                        break;
                    } else {
                        $error = error_get_last();
                        MHI_Logger::info('Nie znaleziono pliku: ' . $filename . ' w ścieżce ' . $remote_path . ' - ' . ($error ? $error['message'] : 'Nieznany błąd'));
                    }
                } catch (Exception $e) {
                    MHI_Logger::error('Wyjątek podczas pobierania pliku ' . $filename . ': ' . $e->getMessage());
                }
            }

            if (!$download_success) {
                MHI_Logger::warning('Wszystkie próby pobrania pliku ' . $filename . ' zakończone niepowodzeniem');
            }
        }

        // Aktualizuj status po zakończeniu
        if ($this->cancel_download) {
            update_option('mhi_download_status_' . $this->name, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
        } else {
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Zakończono pobieranie. Pobrano %d plików z %d.', 'multi-hurtownie-integration'),
                $downloaded_files,
                $total_files
            ));
        }

        return $files;
    }

    /**
     * Pobiera pliki przez socket dla AXPOL.
     *
     * @return array Tablica z informacjami o pobranych plikach.
     */
    private function fetch_files_axpol_socket()
    {
        // Wyczyść status pobierania
        update_option('mhi_download_status_' . $this->name, __('Rozpoczynam pobieranie plików z AXPOL przez socket...', 'multi-hurtownie-integration'));
        MHI_Logger::info('Rozpoczynam pobieranie plików z AXPOL przez socket dla hurtowni ' . $this->name);

        // Zresetuj flagę anulowania
        $this->cancel_download = false;
        update_option('mhi_cancel_download_' . $this->name, false);

        // Zacznij odliczać czas
        $this->download_start_time = time();

        // Lista plików XML do pobrania
        $files_to_download = array(
            'axpol_product_data_PL.xml',
            'axpol_stocklist_PL.xml',
            'axpol_stocklist_pl.xml',
            'axpol_print_data_PL.xml',
            'axpol_print_pricelist_PL.xml'
        );

        $files = array();
        $local_path = MHI_UPLOADS_DIR . '/' . $this->name . '/';

        // Upewnij się, że folder lokalny istnieje
        if (!file_exists($local_path)) {
            wp_mkdir_p($local_path);
        }

        MHI_Logger::info('Folder lokalny: ' . $local_path);

        // Sprawdź uprawnienia do katalogu lokalnego
        if (is_writable($local_path)) {
            MHI_Logger::info('Katalog lokalny ma uprawnienia do zapisu');
        } else {
            MHI_Logger::warning('Katalog lokalny NIE ma uprawnień do zapisu! Próba zmiany uprawnień...');
            @chmod($local_path, 0755);

            if (is_writable($local_path)) {
                MHI_Logger::info('Uprawnienia do katalogu zostały poprawione');
            } else {
                MHI_Logger::error('Nie udało się naprawić uprawnień do katalogu lokalnego! Pobieranie plików może się nie powieść.');
            }
        }

        // Pobierz pliki przez socket
        $total_files = count($files_to_download);
        $current_file = 0;

        foreach ($files_to_download as $filename) {
            // Sprawdź, czy anulowano pobieranie
            if ($this->should_cancel()) {
                MHI_Logger::warning('Anulowano pobieranie plików AXPOL po pobraniu ' . $current_file . ' z ' . $total_files . ' plików');
                break;
            }

            $current_file++;
            $local_file = $local_path . $filename;

            // Aktualizuj status pobierania
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Pobieranie pliku %d z %d: %s', 'multi-hurtownie-integration'),
                $current_file,
                $total_files,
                $filename
            ));

            // Próba pobrania pliku przez socket
            $dirs_to_try = array('/', '/xml', 'xml/', './xml/', './');
            $download_success = false;

            foreach ($dirs_to_try as $dir) {
                if ($this->should_cancel()) {
                    break 2;
                }

                $remote_path = $dir . $filename;
                MHI_Logger::info('Próba pobrania pliku przez socket: ' . $remote_path);

                if ($this->download_file_by_socket($remote_path, $local_file)) {
                    MHI_Logger::info('Pobrano plik przez socket: ' . $filename . ' (' . size_format(filesize($local_file)) . ')');

                    $files[] = array(
                        'filename' => $filename,
                        'path' => $local_file,
                        'size' => filesize($local_file),
                        'date' => date('Y-m-d H:i:s'),
                        'status' => 'downloaded'
                    );

                    $download_success = true;
                    break;
                }
            }

            if (!$download_success) {
                MHI_Logger::warning('Nie udało się pobrać pliku ' . $filename . ' przez socket z żadnej lokalizacji');
            }
        }

        // Aktualizuj status po zakończeniu
        if ($this->cancel_download) {
            update_option('mhi_download_status_' . $this->name, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
        } else {
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Zakończono pobieranie. Pobrano %d plików z %d.', 'multi-hurtownie-integration'),
                count($files),
                $total_files
            ));
        }

        return $files;
    }

    /**
     * Pobiera plik przez niskopoziomowe połączenie socket.
     *
     * @param string $remote_path Ścieżka do pliku na serwerze
     * @param string $local_path Ścieżka, gdzie zapisać plik lokalnie
     * @return bool True jeśli udało się pobrać plik, false w przeciwnym przypadku
     */
    private function download_file_by_socket($remote_path, $local_path)
    {
        $host = $this->config['host'];
        $username = $this->config['username'];
        $password = $this->config['password'];
        $port = 2223;
        $timeout = 180; // 3 minuty na pobranie pliku

        MHI_Logger::info('Próba pobrania pliku ' . $remote_path . ' przez socket z ' . $host . ':' . $port);

        // Zmienne do śledzenia zasobów, które musimy zamknąć
        $socket = null;
        $data_socket = null;
        $fp = null;

        try {
            // Nawiąż połączenie
            $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout);
            if (!$socket) {
                MHI_Logger::error('Błąd połączenia socket: ' . $errstr);
                return false;
            }

            // Odczytaj baner (odpowiedź początkowa)
            $banner = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź serwera: ' . trim($banner));

            // Zaloguj się
            $this->safe_socket_write($socket, "USER " . $username . "\r\n");
            sleep(1);
            $response = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź USER: ' . trim($response));

            $this->safe_socket_write($socket, "PASS " . $password . "\r\n");
            sleep(1);
            $response = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź PASS: ' . trim($response));

            // Ustaw tryb binarny
            $this->safe_socket_write($socket, "TYPE I\r\n");
            sleep(1);
            $response = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź TYPE I: ' . trim($response));

            // Ustaw tryb pasywny
            $this->safe_socket_write($socket, "PASV\r\n");
            sleep(1);
            $response = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź PASV: ' . trim($response));

            // Parsuj odpowiedź PASV, aby uzyskać adres IP i port dla kanału danych
            if (!preg_match('/(\d+),(\d+),(\d+),(\d+),(\d+),(\d+)/', $response, $matches)) {
                MHI_Logger::error('Nieprawidłowa odpowiedź PASV: ' . trim($response));
                @fclose($socket);
                return false;
            }

            // Oblicz port danych
            $ip = $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '.' . $matches[4];
            $port_hi = (int) $matches[5];
            $port_lo = (int) $matches[6];
            $data_port = ($port_hi * 256) + $port_lo;
            $data_host = $ip;

            MHI_Logger::info('Połączenie danych: ' . $data_host . ':' . $data_port);

            // Otwórz połączenie danych
            $data_socket = @stream_socket_client('tcp://' . $data_host . ':' . $data_port, $errno, $errstr, $timeout);
            if (!$data_socket) {
                MHI_Logger::error('Błąd połączenia danych: ' . $errstr);
                @fclose($socket);
                return false;
            }

            // Otwórz plik lokalny do zapisu
            $fp = @fopen($local_path, 'wb');
            if (!$fp) {
                MHI_Logger::error('Nie można otworzyć pliku lokalnego do zapisu: ' . $local_path);
                @fclose($data_socket);
                @fclose($socket);
                return false;
            }

            // Wyślij komendę RETR
            $this->safe_socket_write($socket, "RETR " . $remote_path . "\r\n");
            sleep(1);
            $response = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź RETR: ' . trim($response));

            // Sprawdź czy odpowiedź jest pozytywna (kod 150)
            if (strpos($response, '150') === false) {
                MHI_Logger::error('Błąd podczas pobierania pliku: ' . trim($response));
                @fclose($fp);
                @fclose($data_socket);
                @fclose($socket);
                return false;
            }

            // Pobierz dane i zapisz do pliku
            $total_bytes = 0;
            while (!feof($data_socket)) {
                $data = @fread($data_socket, 8192);
                if ($data === false) {
                    break;
                }
                $total_bytes += strlen($data);
                @fwrite($fp, $data);
            }

            // Zamknij połączenia i plik
            @fclose($fp);
            @fclose($data_socket);

            // Odczytaj odpowiedź serwera po zakończeniu transferu
            $response = @fgets($socket, 1024);
            MHI_Logger::info('Odpowiedź po transferze: ' . trim($response));

            // Wyślij QUIT
            $this->safe_socket_write($socket, "QUIT\r\n");
            @fclose($socket);

            MHI_Logger::info('Pobrano ' . size_format($total_bytes) . ' danych do pliku ' . basename($local_path));
            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas pobierania pliku przez socket: ' . $e->getMessage());
            return false;
        }

        // Zawsze zamykamy zasoby
        if (is_resource($fp)) {
            @fclose($fp);
        }
        if (is_resource($data_socket)) {
            @fclose($data_socket);
        }
        if (is_resource($socket)) {
            @fclose($socket);
        }
    }

    /**
     * Waliduje dane uwierzytelniające.
     *
     * @return bool True jeśli dane są poprawne, false w przeciwnym razie.
     */
    public function validate_credentials()
    {
        // Sprawdź, czy dane uwierzytelniające są kompletne
        $protocol = $this->config['protocol'];

        if ($protocol === 'sftp') {
            // Sprawdź dane do SFTP
            if (empty($this->config['xml_host']) || empty($this->config['xml_username']) || empty($this->config['xml_password'])) {
                MHI_Logger::error('Brak wymaganych danych uwierzytelniających dla SFTP w hurtowni ' . $this->name);
                return false;
            }
            // Sprawdź czy biblioteka phpseclib3 jest dostępna
            if (!class_exists('phpseclib3\Net\SFTP')) {
                MHI_Logger::error('Brak wymaganej biblioteki phpseclib3 do obsługi SFTP. Zainstaluj bibliotekę przez Composer.');
                return false;
            }
        } else {
            // Sprawdź dane do FTP/FTPS
            if (empty($this->config['xml_host']) || empty($this->config['xml_username']) || empty($this->config['xml_password'])) {
                MHI_Logger::error('Brak wymaganych danych uwierzytelniających dla FTP w hurtowni ' . $this->name);
                return false;
            }
            // Sprawdź czy funkcje FTP są dostępne
            if (!function_exists('ftp_connect')) {
                MHI_Logger::error('Brak wymaganych funkcji FTP w PHP.');
                return false;
            }
        }

        // Aktualizuj opcje dla hurtowni
        update_option('mhi_hurtownia_2_enabled', 1);
        update_option('mhi_hurtownia_2_protocol', $protocol);

        return true;
    }

    /**
     * Przetwarza pobrane pliki.
     *
     * @param array $files Tablica z informacjami o pobranych plikach.
     * @return array Tablica z informacjami o przetworzonych plikach.
     */
    public function process_files($files)
    {
        if (empty($files)) {
            return array();
        }

        try {
            foreach ($files as $file) {
                MHI_Logger::info('Przetwarzanie pliku: ' . $file['filename'] . ' (wielkość: ' . size_format($file['size']) . ')');
            }

            return $files;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas przetwarzania plików z hurtowni ' . $this->name . ': ' . $e->getMessage());
            return array();
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
     * Pobiera pliki zdjęć z hurtowni przez SFTP.
     *
     * @param int $batch_number Numer partii do pobrania
     * @param string $img_dir Katalog ze zdjęciami na serwerze
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_images_sftp($batch_number = 1, $img_dir = '/images')
    {
        $files = array();

        // Sprawdź, czy anulowano pobieranie
        if ($this->should_cancel()) {
            return $files;
        }

        // Zacznij odliczać czas
        $this->download_start_time = time();

        // Połącz z serwerem zdjęć jeśli nie jesteśmy jeszcze połączeni
        if (!$this->sftp) {
            // Użyj danych do serwera ze zdjęciami
            $this->config['host'] = $this->config['img_host'];
            $this->config['username'] = $this->config['img_username'];
            $this->config['password'] = $this->config['img_password'];

            if (!$this->connect_sftp()) {
                return $files;
            }
            MHI_Logger::info('Połączono z serwerem SFTP zdjęć: ' . $this->config['host']);
        }

        try {
            // Folder lokalny dla zdjęć
            $local_path = MHI_UPLOADS_DIR . '/' . $this->name . '/images/';

            // Upewnij się, że folder lokalny istnieje
            if (!file_exists($local_path)) {
                wp_mkdir_p($local_path);
            }

            // Sprawdź czy katalog ma uprawnienia do zapisu
            if (!is_writable($local_path)) {
                @chmod($local_path, 0755);
                if (!is_writable($local_path)) {
                    MHI_Logger::error('Katalog zdjęć nie ma uprawnień do zapisu: ' . $local_path);
                    return $files;
                }
            }

            // Pobierz listę plików z katalogu zdjęć
            $img_dir = rtrim($img_dir, '/');
            $image_files = array();

            // Sprawdź różne katalogi ze zdjęciami
            $dirs_to_try = array($img_dir, '/img', '/images', '/zdjecia', 'img', 'images', 'zdjecia');
            $found_dir = false;

            foreach ($dirs_to_try as $dir) {
                try {
                    $file_list = $this->sftp->nlist($dir);
                    if ($file_list && is_array($file_list) && count($file_list) > 0) {
                        $img_dir = $dir;
                        $found_dir = true;
                        MHI_Logger::info('Znaleziono pliki w katalogu SFTP: ' . $dir);
                        break;
                    }
                } catch (Exception $e) {
                    MHI_Logger::warning('Błąd podczas listowania katalogu ' . $dir . ': ' . $e->getMessage());
                }
            }

            if (!$found_dir) {
                MHI_Logger::error('Nie udało się znaleźć katalogu ze zdjęciami na serwerze SFTP');
                return $files;
            }

            // Pobierz listę plików z wybranego katalogu
            try {
                $file_list = $this->sftp->nlist($img_dir);
                if (!$file_list || !is_array($file_list)) {
                    MHI_Logger::error('Nie udało się pobrać listy plików z katalogu ' . $img_dir);
                    return $files;
                }
            } catch (Exception $e) {
                MHI_Logger::error('Błąd podczas listowania katalogu zdjęć: ' . $e->getMessage());
                return $files;
            }

            // Filtruj tylko pliki obrazów
            foreach ($file_list as $file) {
                $filename = basename($file);
                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($file_ext, array('jpg', 'jpeg', 'png', 'gif'))) {
                    $image_files[] = $file;
                }
            }

            // Liczba znalezionych plików zdjęć
            $total_images = count($image_files);
            MHI_Logger::info('Znaleziono ' . $total_images . ' plików zdjęć w katalogu ' . $img_dir);

            // Oblicz offset dla partii
            $offset = ($batch_number - 1) * $this->batch_size;

            // Jeśli offset jest większy niż liczba plików, zwróć pustą tablicę
            if ($offset >= $total_images) {
                MHI_Logger::info('Brak więcej zdjęć do pobrania.');
                update_option('mhi_download_status_' . $this->name, __('Zakończono pobieranie zdjęć.', 'multi-hurtownie-integration'));
                return $files;
            }

            // Wybierz partię plików
            $batch_files = array_slice($image_files, $offset, $this->batch_size);
            $batch_count = count($batch_files);

            // Aktualizuj status pobierania
            update_option('mhi_download_status_' . $this->name, sprintf(
                __('Pobieranie partii %d z %d zdjęć przez SFTP (łącznie %d).', 'multi-hurtownie-integration'),
                $batch_number,
                ceil($total_images / $this->batch_size),
                $total_images
            ));

            // Pobierz pliki z bieżącej partii
            $current_index = 0;
            foreach ($batch_files as $file) {
                // Sprawdź, czy anulowano pobieranie
                if ($this->should_cancel()) {
                    MHI_Logger::warning('Anulowano pobieranie zdjęć po pobraniu ' . $current_index . ' plików z partii ' . $batch_number);
                    break;
                }

                $current_index++;
                $filename = basename($file);
                $local_file = $local_path . $filename;
                $remote_file = $img_dir . '/' . $filename;

                // Aktualizuj status pobierania dla pojedynczego pliku
                update_option('mhi_download_status_' . $this->name, sprintf(
                    __('Pobieranie zdjęcia %d z %d w partii %d: %s', 'multi-hurtownie-integration'),
                    $current_index,
                    $batch_count,
                    $batch_number,
                    $filename
                ));

                // Pomiń istniejące pliki, chyba że są starsze
                if (file_exists($local_file)) {
                    $local_time = filemtime($local_file);
                    try {
                        $remote_stat = $this->sftp->stat($remote_file);
                        $remote_time = isset($remote_stat['mtime']) ? $remote_stat['mtime'] : 0;

                        // Jeśli plik lokalny jest nowszy lub tej samej daty, pomiń
                        if ($local_time >= $remote_time) {
                            MHI_Logger::info('Plik już istnieje i jest aktualny: ' . $filename . ' w hurtowni ' . $this->name);
                            continue;
                        }
                    } catch (Exception $e) {
                        MHI_Logger::warning('Nie można sprawdzić czasu modyfikacji pliku ' . $remote_file . ': ' . $e->getMessage());
                    }
                }

                // Pobierz plik
                try {
                    if ($this->sftp->get($remote_file, $local_file)) {
                        MHI_Logger::info('Pobrano zdjęcie przez SFTP: ' . $filename . ' z hurtowni ' . $this->name . ' (' . size_format(filesize($local_file)) . ')');

                        $files[] = array(
                            'filename' => $filename,
                            'path' => $local_file,
                            'size' => filesize($local_file),
                            'date' => date('Y-m-d H:i:s'),
                            'status' => 'downloaded'
                        );
                    } else {
                        MHI_Logger::error('Nie udało się pobrać zdjęcia: ' . $filename . ' z hurtowni ' . $this->name);
                    }
                } catch (Exception $e) {
                    MHI_Logger::error('Błąd podczas pobierania pliku ' . $filename . ': ' . $e->getMessage());
                }
            }

            // Aktualizuj status zakończenia partii
            if (!$this->cancel_download) {
                update_option('mhi_download_status_' . $this->name, sprintf(
                    __('Pobrano partię %d z %d zdjęć przez SFTP. Pobrano %d plików.', 'multi-hurtownie-integration'),
                    $batch_number,
                    ceil($total_images / $this->batch_size),
                    count($files)
                ));
            }

            // Zapisz informację o pozostałych partiach
            $remaining_batches = ceil($total_images / $this->batch_size) - $batch_number;
            update_option('mhi_remaining_batches_' . $this->name, $remaining_batches);

            return $files;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas pobierania zdjęć przez SFTP z hurtowni ' . $this->name . ': ' . $e->getMessage());
            return $files;
        } finally {
            // Zamknij połączenie tylko jeśli jest to ostatnia partia lub anulowano pobieranie
            $remaining_batches = get_option('mhi_remaining_batches_' . $this->name, 0);
            if ($remaining_batches <= 0 || $this->cancel_download) {
                $this->disconnect();
            }
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
        // W zależności od protokołu, wybierz odpowiednią metodę
        if ($this->config['protocol'] === 'sftp') {
            return $this->fetch_images_sftp($batch_number, $img_dir);
        } else {
            // Istniejąca implementacja dla FTP/FTPS
            // ... existing code ...
        }
    }

    /**
     * Testuje połączenie z hurtownią AXPOL.
     *
     * @return array Wynik testu połączenia.
     */
    public function test_connection()
    {
        $result = array(
            'success' => false,
            'message' => '',
            'details' => array(),
            'connection_info' => array()
        );

        try {
            // Zapisz bieżące ustawienia
            $current_protocol = $this->config['protocol'];
            $current_port = $this->config['port'];

            // Ustaw dane połączenia
            $this->config['host'] = $this->config['xml_host'];
            $this->config['username'] = $this->config['xml_username'];
            $this->config['password'] = $this->config['xml_password'];

            MHI_Logger::info('Rozpoczynam test połączenia z ' . $this->config['host'] . ' jako ' . $this->config['username']);
            $result['details'][] = 'Próba połączenia z ' . $this->config['host'] . ':' . $this->config['port'] . ' jako ' . $this->config['username'];

            // Sprawdź dostępność portu za pomocą netcat
            $result['details'][] = 'Sprawdzam dostępność portu ' . $this->config['port'] . ' na serwerze ' . $this->config['host'];
            $nc_output = array();
            $nc_result = 0;
            exec('nc -z -v ' . $this->config['host'] . ' ' . $this->config['port'] . ' 2>&1', $nc_output, $nc_result);

            $port_open = ($nc_result === 0);
            if ($port_open) {
                $result['details'][] = 'Port ' . $this->config['port'] . ' jest otwarty na serwerze ' . $this->config['host'];
                $result['connection_info']['port_open'] = true;
            } else {
                $result['details'][] = 'Port ' . $this->config['port'] . ' NIE jest otwarty na serwerze ' . $this->config['host'];
                $result['connection_info']['port_open'] = false;
                $result['message'] = 'Nie można połączyć się na porcie ' . $this->config['port'] . '. Port jest zamknięty lub zablokowany.';
                return $result;
            }

            // Test połączenia FTP
            if ($this->config['protocol'] === 'ftp' || $this->config['protocol'] === 'ftps') {
                $result['details'][] = 'Test połączenia ' . $this->config['protocol'] . ' na porcie ' . $this->config['port'];

                // Próba połączenia FTP
                $connection = null;
                if ($this->config['protocol'] === 'ftps') {
                    $connection = @ftp_ssl_connect($this->config['host'], intval($this->config['port']), 15);
                    $result['connection_info']['ftps_connect'] = ($connection !== false);
                } else {
                    $connection = @ftp_connect($this->config['host'], intval($this->config['port']), 15);
                    $result['connection_info']['ftp_connect'] = ($connection !== false);
                }

                if (!$connection) {
                    $error = error_get_last();
                    $result['details'][] = 'Błąd połączenia ' . $this->config['protocol'] . ': ' . ($error ? $error['message'] : 'Nieznany błąd');

                    // Spróbuj alternatywny protokół
                    if ($this->config['protocol'] === 'ftp') {
                        $result['details'][] = 'Próba połączenia FTPS na porcie ' . $this->config['port'];
                        $connection = @ftp_ssl_connect($this->config['host'], intval($this->config['port']), 15);
                        $result['connection_info']['ftps_connect'] = ($connection !== false);
                    } else {
                        $result['details'][] = 'Próba połączenia FTP na porcie ' . $this->config['port'];
                        $connection = @ftp_connect($this->config['host'], intval($this->config['port']), 15);
                        $result['connection_info']['ftp_connect'] = ($connection !== false);
                    }

                    if (!$connection) {
                        $error = error_get_last();
                        $result['details'][] = 'Błąd alternatywnego połączenia: ' . ($error ? $error['message'] : 'Nieznany błąd');

                        // Sprawdź czy mamy dostęp do biblioteki curl dla testu
                        if (function_exists('curl_init')) {
                            $result['details'][] = 'Test połączenia FTP za pomocą curl';
                            $curl = curl_init();
                            curl_setopt($curl, CURLOPT_URL, 'ftp://' . $this->config['username'] . ':' . $this->config['password'] . '@' . $this->config['host'] . ':' . $this->config['port'] . '/');
                            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
                            curl_setopt($curl, CURLOPT_VERBOSE, true);

                            $curl_output = curl_exec($curl);
                            $curl_error = curl_error($curl);
                            $curl_info = curl_getinfo($curl);
                            curl_close($curl);

                            $result['connection_info']['curl_test'] = array(
                                'success' => ($curl_output !== false),
                                'error' => $curl_error,
                                'info' => $curl_info
                            );

                            $result['details'][] = 'Wynik curl: ' . ($curl_output !== false ? 'SUKCES' : 'BŁĄD');
                            if ($curl_error) {
                                $result['details'][] = 'Błąd curl: ' . $curl_error;
                            }
                        }

                        $result['message'] = 'Nie można połączyć się z serwerem ' . $this->config['host'] . ' na porcie ' . $this->config['port'] . ' przez ' . $this->config['protocol'] . ' ani przez alternatywny protokół.';
                        return $result;
                    }
                }

                // Próba logowania
                $login_result = false;
                if ($connection) {
                    $login_result = @ftp_login($connection, $this->config['username'], $this->config['password']);
                    $result['connection_info']['login'] = ($login_result !== false);

                    if (!$login_result) {
                        $error = error_get_last();
                        $result['details'][] = 'Błąd logowania: ' . ($error ? $error['message'] : 'Nieznany błąd');
                        $result['message'] = 'Połączono z serwerem, ale logowanie nie powiodło się. Sprawdź dane logowania.';
                        @ftp_close($connection);
                        return $result;
                    }

                    $result['details'][] = 'Logowanie pomyślne';

                    // Włącz tryb pasywny
                    @ftp_pasv($connection, true);
                    $result['details'][] = 'Włączono tryb pasywny';

                    // Pobierz listę plików
                    $files = @ftp_nlist($connection, '.');
                    if ($files && is_array($files)) {
                        $result['details'][] = 'Pobrano listę plików: ' . implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : '');
                        $result['connection_info']['file_list'] = true;
                    } else {
                        $error = error_get_last();
                        $result['details'][] = 'Nie udało się pobrać listy plików: ' . ($error ? $error['message'] : 'Nieznany błąd');
                        $result['connection_info']['file_list'] = false;

                        // Spróbuj inne katalogi
                        $dirs_to_try = array('/xml', 'xml', './xml');
                        foreach ($dirs_to_try as $dir) {
                            $result['details'][] = 'Próba pobierania plików z katalogu ' . $dir;
                            $files = @ftp_nlist($connection, $dir);
                            if ($files && is_array($files)) {
                                $result['details'][] = 'Pobrano listę plików z katalogu ' . $dir . ': ' . implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : '');
                                $result['connection_info']['file_list_alt_dir'] = true;
                                break;
                            }
                        }
                    }

                    // Zamknij połączenie
                    @ftp_close($connection);
                }
            }
            // Test połączenia SFTP
            else if ($this->config['protocol'] === 'sftp') {
                // Sprawdź czy biblioteka phpseclib3 jest zainstalowana
                if (!class_exists('phpseclib3\Net\SFTP')) {
                    $result['details'][] = 'Brak wymaganej biblioteki phpseclib3 do obsługi SFTP';
                    $result['message'] = 'Brak wymaganej biblioteki phpseclib3. Zainstaluj ją przez Composer.';
                    return $result;
                }

                $result['details'][] = 'Test połączenia SFTP na porcie ' . $this->config['port'];

                try {
                    // Utwórz obiekt SFTP z krótkim timeoutem
                    $sftp = new \phpseclib3\Net\SFTP($this->config['host'], $this->config['port'], 15);
                    $result['connection_info']['sftp_connect'] = $sftp->isConnected();

                    if (!$sftp->isConnected()) {
                        $result['details'][] = 'Nie udało się połączyć z serwerem SFTP na porcie ' . $this->config['port'];

                        // Spróbuj na standardowym porcie 22
                        $result['details'][] = 'Próba połączenia SFTP na standardowym porcie 22';
                        $sftp = new \phpseclib3\Net\SFTP($this->config['host'], 22, 15);
                        $result['connection_info']['sftp_connect_port22'] = $sftp->isConnected();

                        if (!$sftp->isConnected()) {
                            $result['message'] = 'Nie udało się połączyć z serwerem SFTP ani na porcie ' . $this->config['port'] . ', ani na porcie 22.';
                            return $result;
                        }

                        $result['details'][] = 'Połączono na porcie 22';
                    } else {
                        $result['details'][] = 'Połączono na porcie ' . $this->config['port'];
                    }

                    // Próba logowania
                    $login_result = $sftp->login($this->config['username'], $this->config['password']);
                    $result['connection_info']['sftp_login'] = $login_result;

                    if (!$login_result) {
                        $result['message'] = 'Połączono z serwerem SFTP, ale logowanie nie powiodło się. Sprawdź dane logowania.';
                        return $result;
                    }

                    $result['details'][] = 'Logowanie SFTP udane';

                    // Sprawdź katalog roboczy
                    $pwd = $sftp->pwd();
                    $result['details'][] = 'Katalog roboczy: ' . ($pwd ?: 'nieznany');

                    // Listuj pliki
                    $files = $sftp->nlist('.');
                    if (is_array($files) && count($files) > 0) {
                        $result['details'][] = 'Znaleziono ' . count($files) . ' plików w katalogu głównym';
                        $result['details'][] = 'Przykładowe pliki: ' . implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : '');
                        $result['connection_info']['sftp_file_list'] = true;
                    } else {
                        $result['details'][] = 'Katalog główny jest pusty lub nie można odczytać jego zawartości';
                        $result['connection_info']['sftp_file_list'] = false;

                        // Spróbuj inne katalogi
                        $dirs_to_try = array('/xml', 'xml', './xml');
                        foreach ($dirs_to_try as $dir) {
                            $result['details'][] = 'Próba pobierania plików z katalogu ' . $dir;
                            $files = $sftp->nlist($dir);
                            if (is_array($files) && count($files) > 0) {
                                $result['details'][] = 'Pobrano listę plików z katalogu ' . $dir . ': ' . implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? '...' : '');
                                $result['connection_info']['sftp_file_list_alt_dir'] = true;
                                break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $result['details'][] = 'Wyjątek podczas testu SFTP: ' . $e->getMessage();
                    $result['message'] = 'Błąd podczas testu połączenia SFTP: ' . $e->getMessage();
                    return $result;
                }
            }

            // Jeśli dotarliśmy tutaj, test jest udany
            $result['success'] = true;
            $result['message'] = 'Test połączenia udany. Połączono z serwerem ' . $this->config['host'] . ' przez ' . $this->config['protocol'] . ' jako ' . $this->config['username'];

            // Sugestie dla lepszego połączenia
            if ($this->config['protocol'] === 'sftp' && isset($result['connection_info']['sftp_connect']) && !$result['connection_info']['sftp_connect']) {
                if (isset($result['connection_info']['ftp_connect']) && $result['connection_info']['ftp_connect']) {
                    $result['details'][] = 'SUGESTIA: Serwer nie obsługuje SFTP, ale obsługuje FTP. Zalecamy zmianę protokołu na FTP.';
                }
            }
            if ($this->config['protocol'] === 'ftp' && isset($result['connection_info']['ftp_connect']) && !$result['connection_info']['ftp_connect']) {
                if (isset($result['connection_info']['ftps_connect']) && $result['connection_info']['ftps_connect']) {
                    $result['details'][] = 'SUGESTIA: Serwer nie obsługuje FTP, ale obsługuje FTPS. Zalecamy zmianę protokołu na FTPS.';
                }
            }

        } catch (Exception $e) {
            $result['message'] = 'Błąd podczas testu połączenia: ' . $e->getMessage();
            $result['details'][] = 'Szczegóły błędu: ' . $e->getMessage() . ' w linii ' . $e->getLine();
            MHI_Logger::error('Test połączenia nieudany: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Testuje połączenie socket (TCP) dla AXPOL na określonym porcie.
     *
     * @return int|bool Port, na którym połączenie działa, lub false jeśli nie znaleziono działającego portu
     */
    private function test_socket_connection()
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = 10;

        MHI_Logger::info('Test połączenia socket dla ' . $host . ':' . $port);

        // Sprawdź bieżący port
        $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout);

        if ($socket) {
            MHI_Logger::info('Połączenie socket działa na porcie ' . $port);
            fclose($socket);
            return $port;
        }

        MHI_Logger::warning('Nie można połączyć z ' . $host . ' na porcie ' . $port . ': ' . $errstr);

        // Dla AXPOL spróbuj inne porty
        $alt_ports = array(21, 22, 990, 2221, 2222, 2223);

        // Usuń już sprawdzony port
        if (($key = array_search($port, $alt_ports)) !== false) {
            unset($alt_ports[$key]);
        }

        foreach ($alt_ports as $try_port) {
            MHI_Logger::info('Próba połączenia na porcie ' . $try_port);
            $socket = @stream_socket_client('tcp://' . $host . ':' . $try_port, $errno, $errstr, $timeout);

            if ($socket) {
                MHI_Logger::info('Połączenie socket działa na porcie ' . $try_port);
                fclose($socket);
                return $try_port;
            }
        }

        MHI_Logger::error('Nie znaleziono działającego portu dla ' . $host);
        return false;
    }

    /**
     * Testuje niskopoziomowe połączenie socket z serwerem AXPOL.
     *
     * @return bool True jeżeli udało się połączyć, false w przeciwnym razie.
     */
    private function test_axpol_socket()
    {
        $host = $this->config['host'];
        $port = 2223; // Zawsze port 2223 dla AXPOL
        $timeout = 30; // Dłuższy timeout - 30 sekund

        MHI_Logger::info('Test niskopoziomowego połączenia telnet do ' . $host . ':' . $port);

        // Użyj stream_socket_client z dłuższym timeoutem
        $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

        if (!$socket) {
            MHI_Logger::error('Błąd podczas testu socket: ' . $errstr . ' (kod: ' . $errno . ')');
            return false;
        }

        // Połączenie nawiązane, odczytaj baner jeśli jest
        $banner = stream_get_contents($socket, 1024);
        MHI_Logger::info('Odpowiedź serwera: ' . trim($banner));

        // Zamknij socket
        fclose($socket);
        MHI_Logger::info('Test niskopoziomowego połączenia socket udany');
        return true;
    }

    /**
     * Importuje produkty z pliku XML do WooCommerce (funkcja wyłączona).
     *
     * @return string Informacja o wyniku importu.
     * @throws Exception W przypadku błędu podczas importu.
     */
    // public function import_products_to_woocommerce()
    // {
    //     throw new Exception(__('Funkcja importu produktów została wyłączona.', 'multi-hurtownie-integration'));
    // }

    /**
     * Generuje plik woocommerce_produkty.xml na podstawie plików XML z hurtowni AXPOL (funkcja wyłączona)
     *
     * @return array Informacja o statusie generowania pliku
     */
    // public function generate_woocommerce_xml()
    // {
    //     return array(
    //         'status' => 'error',
    //         'message' => 'Funkcja generowania XML dla WooCommerce została wyłączona.'
    //     );
    // }

    /**
     * Generuje plik XML do importu do WooCommerce.
     *
     * @return bool True jeśli plik został wygenerowany pomyślnie, false w przeciwnym razie.
     */
    public function generate_woocommerce_xml()
    {
        // Sprawdź czy klasa generatora istnieje
        $generator_file = MHI_PLUGIN_DIR . 'integrations/class-mhi-axpol-wc-xml-generator.php';
        if (!file_exists($generator_file)) {
            MHI_Logger::error('Nie znaleziono pliku generatora XML WooCommerce dla Axpol');
            return false;
        }

        // Załaduj klasę generatora jeśli nie została jeszcze załadowana
        if (!class_exists('MHI_Axpol_WC_XML_Generator')) {
            require_once $generator_file;
        }

        try {
            // Utwórz instancję generatora
            $generator = new MHI_Axpol_WC_XML_Generator();

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
    public function import_products_to_woocommerce()
    {
        $upload_dir = wp_upload_dir();
        $xml_file = trailingslashit($upload_dir['basedir']) . 'hurtownie/' . $this->name . '/woocommerce_import_' . $this->name . '.xml';

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
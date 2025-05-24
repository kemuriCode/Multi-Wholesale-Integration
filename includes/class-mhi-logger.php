<?php
/**
 * Klasa do logowania operacji wtyczki
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Logger
 *
 * Obsługuje zapisywanie logów do pliku i/lub bazy danych.
 */
class MHI_Logger
{
    /**
     * Ścieżka do pliku logów.
     *
     * @var string
     */
    private static $log_file = '';

    /**
     * Inicjalizuje klasę logowania.
     *
     * @return void
     */
    public static function init()
    {
        // Ustaw ścieżkę do pliku logów w folderze uploads
        $upload_dir = wp_upload_dir();
        $logs_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/logs';

        // Stwórz katalog logów, jeśli nie istnieje
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }

        // Ustaw ścieżkę do pliku logów
        self::$log_file = $logs_dir . '/mhi_log_' . date('Y-m-d') . '.log';

        // Dodatkowy log dla debugowania
        error_log('MHI_Logger::init() - Plik logów: ' . self::$log_file);

        // Zabezpiecz katalog logów przed bezpośrednim dostępem z przeglądarki
        $htaccess_file = $logs_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }

    /**
     * Zapisuje informację do loga.
     *
     * @param string $message Treść wiadomości.
     * @param array  $context Kontekst wiadomości (opcjonalnie).
     * @return void
     */
    public static function info($message, $context = array())
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Zapisuje ostrzeżenie do loga.
     *
     * @param string $message Treść wiadomości.
     * @param array  $context Kontekst wiadomości (opcjonalnie).
     * @return void
     */
    public static function warning($message, $context = array())
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Zapisuje błąd do loga.
     *
     * @param string $message Treść wiadomości.
     * @param array  $context Kontekst wiadomości (opcjonalnie).
     * @return void
     */
    public static function error($message, $context = array())
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Zapisuje wpis do loga.
     *
     * @param string $level   Poziom logowania (INFO, WARNING, ERROR).
     * @param string $message Treść wiadomości.
     * @param array  $context Kontekst wiadomości (opcjonalnie).
     * @return void
     */
    private static function log($level, $message, $context = array())
    {
        // Inicjalizuj system logowania, jeśli nie został zainicjalizowany
        if (empty(self::$log_file)) {
            self::init();
        }

        // Format wiadomości: [2023-01-01 12:00:00] [INFO] Treść wiadomości
        $timestamp = date('Y-m-d H:i:s');
        $log_message = sprintf("[%s] [%s] %s", $timestamp, $level, $message);

        // Dodaj kontekst, jeśli istnieje
        if (!empty($context)) {
            $log_message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        // Zapisz do pliku
        if (!empty(self::$log_file)) {
            // Dodajemy do pliku error_log (debug.log) dla pewności
            error_log('MHI ' . $level . ': ' . $message);

            try {
                file_put_contents(self::$log_file, $log_message . PHP_EOL, FILE_APPEND);
            } catch (Exception $e) {
                error_log('MHI Logger error: ' . $e->getMessage());
            }
        } else {
            // Awaryjnie zapisz do error_log WordPressa
            error_log('MHI ' . $level . ': ' . $message);
        }
    }

    /**
     * Tworzy tabelę logów w bazie danych.
     *
     * @return void
     */
    public static function create_logs_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhi_logs';

        // Sprawdź, czy tabela już istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                log_time datetime NOT NULL,
                level varchar(10) NOT NULL,
                message text NOT NULL,
                context text,
                PRIMARY KEY  (id),
                KEY level (level),
                KEY log_time (log_time)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Czyści stare logi z bazy danych.
     *
     * @param int $days Liczba dni, po których logi powinny zostać usunięte.
     * @return int Liczba usuniętych wpisów.
     */
    public static function cleanup_old_logs($days = 30)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhi_logs';

        // Sprawdź, czy tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }

        // Usuń stare logi
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE log_time < %s", $date));

        return $result;
    }

    /**
     * Pobiera logi z bazy danych.
     *
     * @param array $args Argumenty filtrowaina logów.
     * @return array Tablica z logami.
     */
    public static function get_logs($args = array())
    {
        global $wpdb;

        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'level' => '',
            'order' => 'DESC',
            'from_date' => '',
            'to_date' => ''
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'mhi_logs';

        // Sprawdź, czy tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }

        // Buduj zapytanie
        $query = "SELECT * FROM $table_name WHERE 1=1";

        // Filtruj po poziomie
        if (!empty($args['level'])) {
            $query .= $wpdb->prepare(" AND level = %s", $args['level']);
        }

        // Filtruj po dacie
        if (!empty($args['from_date'])) {
            $query .= $wpdb->prepare(" AND log_time >= %s", $args['from_date']);
        }

        if (!empty($args['to_date'])) {
            $query .= $wpdb->prepare(" AND log_time <= %s", $args['to_date']);
        }

        // Sortowanie
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY log_time $order";

        // Limit i offset
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);

        // Wykonaj zapytanie
        $results = $wpdb->get_results($query);

        return $results;
    }
}
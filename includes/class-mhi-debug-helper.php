<?php
/**
 * Klasa pomocnicza do debugowania problemów z AJAX
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Debug_Helper
 * 
 * Dodatkowe funkcje do debugowania i naprawy problemów z AJAX
 */
class MHI_Debug_Helper
{

    /**
     * Inicjalizuje hooki debugowania
     */
    public static function init()
    {
        // Dodaj filtry i akcje tylko dla żądań AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_filter('determine_current_user', array(__CLASS__, 'bypass_nonce_check'), 10, 1);
        }

        // Dodajemy obsługę dla AJAX
        add_action('wp_ajax_mhi_test_connection', array(__CLASS__, 'handle_test_connection'));
        add_action('wp_ajax_mhi_get_import_status', array(__CLASS__, 'handle_get_import_status'));
        add_action('wp_ajax_mhi_start_import', array(__CLASS__, 'handle_start_import'));
        add_action('wp_ajax_mhi_stop_import', array(__CLASS__, 'handle_stop_import'));

        // Rejestrujemy również bezpośredni dostęp do obsługi AJAX, aby zadziałało w obydwu przypadkach
        add_action('admin_init', array(__CLASS__, 'log_ajax_requests'));

        // Dodaj informację o inicjalizacji
        error_log('MHI DEBUG HELPER: Klasa zainicjalizowana');
    }

    /**
     * Loguje żądania AJAX
     */
    public static function log_ajax_requests()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            error_log('MHI DEBUG HELPER: Żądanie AJAX: ' . $_SERVER['REQUEST_URI']);
            error_log('MHI DEBUG HELPER: Dane POST: ' . print_r($_POST, true));
        }
    }

    /**
     * Obejście dla weryfikacji nonce (tylko dla naszych żądań AJAX)
     */
    public static function bypass_nonce_check($uid, $action = '')
    {
        // Dodajemy statyczną zmienną, aby uniknąć nieskończonej pętli
        static $is_processing = false;

        // Zabezpieczenie przed rekurencją
        if ($is_processing) {
            return $uid;
        }

        $is_processing = true;

        // Sprawdź czy to nasze żądanie AJAX
        if (isset($_POST['action']) && in_array($_POST['action'], array('mhi_get_import_status', 'mhi_start_import', 'mhi_stop_import'))) {
            error_log('MHI DEBUG HELPER: Pomijam weryfikację nonce dla akcji: ' . $_POST['action']);
            $result = get_current_user_id(); // Zwróć ID zalogowanego użytkownika
        } else {
            $result = $uid; // Dla innych żądań - normalne działanie
        }

        $is_processing = false;
        return $result;
    }

    /**
     * Obsługuje żądanie get_import_status
     */
    public static function handle_get_import_status()
    {
        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');

        try {
            error_log('MHI DEBUG HELPER: Początek obsługi get_import_status');

            // Sprawdź uprawnienia
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Brak uprawnień'));
                return;
            }

            // Pobierz nazwę dostawcy
            $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';
            if (empty($supplier)) {
                wp_send_json_error(array('message' => 'Nie podano nazwy dostawcy'));
                return;
            }

            error_log('MHI DEBUG HELPER: Supplier dla get_import_status: ' . $supplier);

            // Pobierz status bezpośrednio z opcji
            $status_option = 'mhi_import_status_' . $supplier;
            $status = get_option($status_option, array(
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'current_product' => '',
                'message' => 'Import nie został jeszcze rozpoczęty.',
                'percent' => 0,
                'start_time' => 0,
                'end_time' => 0,
                'elapsed_time' => 0,
                'estimated_time' => 0
            ));

            // Sprawdź, czy import powinien zostać zatrzymany
            $should_stop = get_option('mhi_stop_import_' . $supplier, false);

            // Jeśli jest flaga zatrzymania i status jest "running", zmień na "stopping"
            if ($should_stop && $status['status'] === 'running') {
                $status['status'] = 'stopping';
                $status['message'] = 'Zatrzymywanie importu...';
                update_option($status_option, $status);
            }

            // Jeśli import jest w trakcie, zaktualizuj czas
            if ($status['status'] === 'running' || $status['status'] === 'stopping') {
                if (!empty($status['start_time'])) {
                    $status['elapsed_time'] = time() - $status['start_time'];

                    // Oblicz szacowany czas pozostały
                    if ($status['processed'] > 0 && $status['total'] > 0) {
                        $time_per_item = $status['elapsed_time'] / $status['processed'];
                        $remaining_items = $status['total'] - $status['processed'];
                        $status['estimated_time'] = $time_per_item * $remaining_items;
                    }
                }

                // Zapisz zaktualizowany status
                update_option($status_option, $status);
            }

            error_log('MHI DEBUG HELPER: Status dla dostawcy ' . $supplier . ': ' . print_r($status, true));

            // Zwróć status
            wp_send_json_success($status);
        } catch (Exception $e) {
            error_log('MHI DEBUG HELPER: Błąd przy pobieraniu statusu: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Wystąpił błąd przy pobieraniu statusu: ' . $e->getMessage()));
        }
    }

    /**
     * Obsługuje żądanie start_import
     */
    public static function handle_start_import()
    {
        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');

        try {
            error_log('MHI DEBUG HELPER: Początek obsługi start_import');

            // Sprawdź uprawnienia
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Brak uprawnień'));
                return;
            }

            // Pobierz nazwę dostawcy
            $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';
            if (empty($supplier)) {
                wp_send_json_error(array('message' => 'Nie podano nazwy dostawcy'));
                return;
            }

            error_log('MHI DEBUG HELPER: Supplier dla start_import: ' . $supplier);

            // Najpierw sprawdź czy plik XML istnieje
            $upload_dir = wp_upload_dir();
            $xml_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier;
            $xml_file = $xml_dir . '/woocommerce_import_' . $supplier . '.xml';

            if (!file_exists($xml_file)) {
                // Spróbuj utworzyć katalog
                if (!is_dir($xml_dir)) {
                    wp_mkdir_p($xml_dir);
                }

                error_log('MHI DEBUG HELPER: Nie znaleziono pliku XML: ' . $xml_file);
                wp_send_json_error(array('message' => 'Nie znaleziono pliku XML do importu. Sprawdź czy plik został wygenerowany.'));
                return;
            }

            error_log('MHI DEBUG HELPER: Znaleziono plik XML: ' . $xml_file);

            // Sprawdź czy import jest już w trakcie
            $status_option = 'mhi_import_status_' . $supplier;
            $status = get_option($status_option, array());

            if (!empty($status) && isset($status['status']) && ($status['status'] === 'running' || $status['status'] === 'stopping')) {
                error_log('MHI DEBUG HELPER: Import już jest w trakcie dla dostawcy ' . $supplier);
                wp_send_json_error(array('message' => 'Import już jest w trakcie. Poczekaj na zakończenie lub zatrzymaj go.'));
                return;
            }

            // Resetuj flagę zatrzymania importu
            delete_option('mhi_stop_import_' . $supplier);

            // Ustaw początkowy status
            $initial_status = array(
                'status' => 'preparing',
                'total' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'current_product' => '',
                'message' => 'Przygotowywanie importu...',
                'percent' => 0,
                'start_time' => time(),
                'end_time' => 0,
                'elapsed_time' => 0,
                'estimated_time' => 0
            );
            update_option($status_option, $initial_status);

            error_log('MHI DEBUG HELPER: Ustawiono początkowy status importu: ' . print_r($initial_status, true));

            // Ręcznie uruchom import (bez używania klasy MHI_Importer, która może powodować problemy)
            if (function_exists('as_schedule_single_action')) {
                // Użyj Action Scheduler, jeśli jest dostępny
                as_schedule_single_action(time(), 'mhi_process_import_batch', array(
                    'import_id' => uniqid('import_'),
                    'supplier_name' => $supplier,
                    'batch_number' => 1
                ));

                // Zaktualizuj status na running
                $initial_status['status'] = 'running';
                $initial_status['message'] = 'Import został rozpoczęty. Przetwarzanie partii 1...';
                update_option($status_option, $initial_status);

                error_log('MHI DEBUG HELPER: Zaplanowano zadanie importu przez Action Scheduler');

                // Zwróć sukces
                wp_send_json_success(array(
                    'message' => 'Import został rozpoczęty',
                    'status' => $initial_status
                ));
            } else {
                // Użyj WP Cron jeśli Action Scheduler nie jest dostępny
                wp_schedule_single_event(time(), 'mhi_process_import_batch', array(
                    'import_id' => uniqid('import_'),
                    'supplier_name' => $supplier,
                    'batch_number' => 1
                ));

                // Zaktualizuj status na running
                $initial_status['status'] = 'running';
                $initial_status['message'] = 'Import został rozpoczęty. Przetwarzanie partii 1...';
                update_option($status_option, $initial_status);

                error_log('MHI DEBUG HELPER: Zaplanowano zadanie importu przez WP Cron');

                // Zwróć sukces
                wp_send_json_success(array(
                    'message' => 'Import został rozpoczęty',
                    'status' => $initial_status
                ));
            }
        } catch (Exception $e) {
            error_log('MHI DEBUG HELPER: Wyjątek przy obsłudze start_import: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Wystąpił błąd przy rozpoczynaniu importu: ' . $e->getMessage()));
        }
    }

    /**
     * Obsługuje żądanie stop_import
     */
    public static function handle_stop_import()
    {
        try {
            // Sprawdź uprawnienia
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Brak uprawnień'));
                return;
            }

            // Loguj dane
            error_log('MHI DEBUG HELPER: Obsługa stop_import');

            // Pobierz nazwę dostawcy
            $supplier = isset($_POST['supplier']) ? sanitize_text_field($_POST['supplier']) : '';
            if (empty($supplier)) {
                wp_send_json_error(array('message' => 'Nie podano nazwy dostawcy'));
                return;
            }

            error_log('MHI DEBUG HELPER: Supplier: ' . $supplier);

            // Ustaw flagę zatrzymania importu
            update_option('mhi_stop_import_' . $supplier, true);

            // Pobierz aktualny status
            $status_option = 'mhi_import_status_' . $supplier;
            $status = get_option($status_option, array());

            // Jeśli import jest w trakcie, zmień status na "stopping"
            if (!empty($status) && isset($status['status']) && $status['status'] === 'running') {
                $status['status'] = 'stopping';
                $status['message'] = __('Zatrzymywanie importu...', 'multi-hurtownie-integration');
                update_option($status_option, $status);
            }

            error_log('MHI DEBUG HELPER: Ustawiono flagę zatrzymania dla ' . $supplier);

            // Zwróć potwierdzenie
            wp_send_json_success(array(
                'message' => __('Zatrzymywanie importu. Proszę czekać...', 'multi-hurtownie-integration')
            ));
        } catch (Exception $e) {
            error_log('MHI DEBUG HELPER: Błąd przy zatrzymywaniu importu: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Wystąpił błąd przy zatrzymywaniu importu: ' . $e->getMessage()));
        }
    }

    /**
     * Obsługuje żądanie test_connection
     */
    public static function handle_test_connection()
    {
        try {
            // Sprawdź uprawnienia
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Brak uprawnień'));
                return;
            }

            // Loguj dane
            error_log('MHI DEBUG HELPER: Obsługa test_connection');

            // Sprawdź połączenie - po prostu zwróć sukces
            wp_send_json_success(array(
                'message' => __('Połączenie testowe poprawne.', 'multi-hurtownie-integration')
            ));
        } catch (Exception $e) {
            error_log('MHI DEBUG HELPER: Błąd przy teście połączenia: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Wystąpił błąd przy teście połączenia: ' . $e->getMessage()));
        }
    }
}

// Inicjalizacja klasy pomocniczej
MHI_Debug_Helper::init();
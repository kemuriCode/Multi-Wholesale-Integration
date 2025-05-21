<?php
/**
 * Klasa obsługująca procesy w tle poprzez WP Cron lub Action Scheduler
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Dodaj funkcje zastępcze dla Action Scheduler, jeśli nie są dostępne
if (!function_exists('as_next_scheduled_action')) {
    function as_next_scheduled_action($hook)
    {
        return wp_next_scheduled($hook);
    }
}

if (!function_exists('as_has_scheduled_action')) {
    function as_has_scheduled_action($hook, $args = null, $group = null)
    {
        return wp_next_scheduled($hook, $args);
    }
}

if (!function_exists('as_schedule_single_action')) {
    function as_schedule_single_action($timestamp, $hook, $args = array())
    {
        return wp_schedule_single_event($timestamp, $hook, $args);
    }
}

/**
 * Klasa MHI_Background_Process
 * 
 * Zapewnia obsługę procesów w tle dla importera produktów
 */
class MHI_Background_Process
{
    /**
     * Konstruktor
     */
    public function __construct()
    {
        // Sprawdź czy Action Scheduler jest dostępny
        $this->check_action_scheduler();

        // Dodaj hook dla obsługi partii importu
        add_action('mhi_process_import_batch', array($this, 'process_import_batch'), 10, 3);

        // Hook dla inicjalizacji procesów
        add_action('init', array($this, 'maybe_handle_batch_runner'), 999);
    }

    /**
     * Sprawdza czy Action Scheduler jest dostępny
     * Jeśli nie, wyświetla komunikat o błędzie w panelu admina
     */
    public function check_action_scheduler()
    {
        // Sprawdź czy Action Scheduler jest dostępny
        if (!function_exists('as_schedule_single_action') && !function_exists('as_has_scheduled_action')) {
            // Sprawdź czy WooCommerce jest aktywny
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', 'mhi_action_scheduler_missing_notice');
                MHI_Logger::error('WooCommerce nie jest aktywny. Action Scheduler nie jest dostępny.');
                return false;
            }

            MHI_Logger::warning('Action Scheduler nie został znaleziony, mimo że WooCommerce jest zainstalowany.');
            return false;
        }

        return true;
    }

    /**
     * Uruchamia procesy w tle, jeśli są zaplanowane
     */
    public function maybe_handle_batch_runner()
    {
        if (!$this->check_action_scheduler()) {
            return;
        }

        // Sprawdź, czy są jakieś zaplanowane zadania dla importu
        if (function_exists('as_next_scheduled_action')) {
            $next_batch = as_next_scheduled_action('mhi_process_import_batch');
            if ($next_batch && (time() > $next_batch - 300)) {
                // Zadanie jest zaplanowane w ciągu najbliższych 5 minut, więc nie dodawaj dodatkowych zadań
                return;
            }
        }

        // Sprawdź, czy są aktywne importy do wznowienia
        $active_imports = $this->get_active_imports();
        if (!empty($active_imports)) {
            foreach ($active_imports as $supplier => $status) {
                // Sprawdź czy import jest w trakcie i nie jest zaplanowany do wykonania
                if ($status['status'] === 'running' && !$this->is_batch_scheduled($supplier)) {
                    // Zaplanuj kolejne przetwarzanie partii
                    $last_processed = isset($status['processed']) ? $status['processed'] : 0;
                    $batch_number = floor($last_processed / 10) + 1; // Zakładamy batch_size = 10

                    $this->schedule_next_batch('resume_' . $supplier . '_' . time(), $supplier, $batch_number);

                    MHI_Logger::info(sprintf(
                        'Wznowiono import dla %s od partii %d',
                        $supplier,
                        $batch_number
                    ));
                }
            }
        }
    }

    /**
     * Pobiera listę aktywnych importów
     * 
     * @return array Lista aktywnych importów
     */
    private function get_active_imports()
    {
        global $wpdb;

        // Pobierz wszystkie opcje związane ze statusami importów
        $option_names = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mhi_import_status_%'"
        );

        $active_imports = array();
        foreach ($option_names as $option_name) {
            $supplier = str_replace('mhi_import_status_', '', $option_name);
            $status = get_option($option_name, array());

            if (!empty($status) && isset($status['status']) && $status['status'] === 'running') {
                $active_imports[$supplier] = $status;
            }
        }

        return $active_imports;
    }

    /**
     * Sprawdza czy zadanie jest już zaplanowane dla dostawcy
     * 
     * @param string $supplier Nazwa dostawcy
     * @return bool True jeśli zadanie jest zaplanowane
     */
    private function is_batch_scheduled($supplier)
    {
        if (!function_exists('as_has_scheduled_action')) {
            return false;
        }

        // Sprawdź czy istnieje zaplanowane zadanie dla tego dostawcy
        $scheduled = as_has_scheduled_action('mhi_process_import_batch', null, null);

        return (bool) $scheduled;
    }

    /**
     * Planuje następną partię importu
     * 
     * @param string $import_id Identyfikator importu
     * @param string $supplier_name Nazwa dostawcy
     * @param int $batch_number Numer partii
     * @param int $delay Opóźnienie w sekundach
     * @return bool True jeśli zaplanowano partię
     */
    public function schedule_next_batch($import_id, $supplier_name, $batch_number, $delay = 5)
    {
        $args = array(
            'import_id' => $import_id,
            'supplier_name' => $supplier_name,
            'batch_number' => $batch_number
        );

        // Sprawdź czy Action Scheduler jest dostępny
        if (function_exists('as_schedule_single_action')) {
            $scheduled = as_schedule_single_action(time() + $delay, 'mhi_process_import_batch', $args);

            if ($scheduled) {
                MHI_Logger::info(sprintf(
                    'Zaplanowano partię %d dla importu %s (dostawca: %s)',
                    $batch_number,
                    $import_id,
                    $supplier_name
                ));
                return true;
            } else {
                MHI_Logger::error(sprintf(
                    'Nie udało się zaplanować partii %d dla importu %s (dostawca: %s)',
                    $batch_number,
                    $import_id,
                    $supplier_name
                ));
                return false;
            }
        } else {
            // Fallback na WP Cron
            $scheduled = wp_schedule_single_event(time() + $delay, 'mhi_process_import_batch', $args);

            if ($scheduled) {
                MHI_Logger::info(sprintf(
                    'Zaplanowano partię %d dla importu %s (dostawca: %s) przez WP Cron',
                    $batch_number,
                    $import_id,
                    $supplier_name
                ));
                return true;
            } else {
                MHI_Logger::error(sprintf(
                    'Nie udało się zaplanować partii %d dla importu %s (dostawca: %s) przez WP Cron',
                    $batch_number,
                    $import_id,
                    $supplier_name
                ));
                return false;
            }
        }
    }

    /**
     * Przetwarza partię importu produktów
     * 
     * @param string $import_id Identyfikator importu
     * @param string $supplier_name Nazwa dostawcy
     * @param int $batch_number Numer partii
     * @return bool True jeżeli partia została przetworzona pomyślnie
     */
    public function process_import_batch($import_id, $supplier_name, $batch_number)
    {
        MHI_Logger::info(sprintf(
            'Przetwarzanie partii %d dla importu %s dostawcy %s',
            $batch_number,
            $import_id,
            $supplier_name
        ));

        try {
            // Zwiększ limity
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Załaduj klasę importera
            require_once plugin_dir_path(__FILE__) . 'class-mhi-importer.php';

            // Inicjalizuj importer
            $importer = new MHI_Importer($supplier_name);

            // Sprawdź czy import powinien zostać zatrzymany
            if ($importer->should_stop()) {
                MHI_Logger::warning(sprintf(
                    'Import %s został zatrzymany przed przetworzeniem partii %d',
                    $import_id,
                    $batch_number
                ));
                // Użyj publicznej metody update_status, aby zatrzymać import
                $importer->update_status([
                    'status' => 'stopped',
                    'message' => 'Import został zatrzymany ręcznie.'
                ]);
                return false;
            }

            // Przetwórz partię
            $result = $importer->process_batch($batch_number);

            // Sprawdź wynik
            if ($result) {
                MHI_Logger::info(sprintf(
                    'Partia %d dla importu %s została przetworzona pomyślnie',
                    $batch_number,
                    $import_id
                ));
            } else {
                MHI_Logger::error(sprintf(
                    'Błąd podczas przetwarzania partii %d dla importu %s',
                    $batch_number,
                    $import_id
                ));
            }

            return $result;
        } catch (Exception $e) {
            MHI_Logger::error(sprintf(
                'Wyjątek podczas przetwarzania partii %d dla importu %s: %s',
                $batch_number,
                $import_id,
                $e->getMessage()
            ));

            // Aktualizuj status na error
            $status = get_option('mhi_import_status_' . $supplier_name, []);
            if (!empty($status)) {
                $status['status'] = 'error';
                $status['message'] = 'Błąd: ' . $e->getMessage();
                update_option('mhi_import_status_' . $supplier_name, $status);
            }

            return false;
        }
    }
}
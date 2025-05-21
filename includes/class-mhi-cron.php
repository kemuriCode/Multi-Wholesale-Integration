<?php
/**
 * Klasa obsługująca zadania cron
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Cron
 * 
 * Zarządza wszystkimi zadaniami cron dla integracji z hurtowniami.
 */
class MHI_Cron
{
    /**
     * Inicjalizuje zadania cron
     */
    public function init()
    {
        // Dodanie akcji dla głównego crona
        add_action('mhi_cron_fetch_all', array($this, 'fetch_all_wholesalers'));

        // Dodanie akcji dla poszczególnych hurtowni
        add_action('mhi_cron_fetch_hurtownia_1', array($this, 'fetch_hurtownia_1'));
        add_action('mhi_cron_fetch_hurtownia_2', array($this, 'fetch_hurtownia_2'));
        add_action('mhi_cron_fetch_hurtownia_3', array($this, 'fetch_hurtownia_3'));
        add_action('mhi_cron_fetch_hurtownia_4', array($this, 'fetch_hurtownia_4'));
        add_action('mhi_cron_fetch_hurtownia_5', array($this, 'fetch_hurtownia_5'));

        // Rejestracja zadań cron
        add_action('mhi_cron_fetch_files', array($this, 'fetch_files'));
        add_action('mhi_single_download_task', array($this, 'single_download_task'));

        // Rejestracja zadania dla anulowania pobierania
        add_action('mhi_cancel_download_task', array($this, 'cancel_download_task'));

        // Inicjalizacja zaplanowanych zadań
        $this->schedule_tasks();
    }

    /**
     * Planuje zadania cron na podstawie ustawień
     */
    private function schedule_tasks()
    {
        // Sprawdzenie, czy główne zadanie cron już jest zaplanowane
        if (!wp_next_scheduled('mhi_cron_fetch_all')) {
            // Zaplanowanie głównego zadania na podstawie ustawień
            $interval = get_option('mhi_cron_interval', 'daily');
            wp_schedule_event(time(), $interval, 'mhi_cron_fetch_all');
        }

        // Sprawdzenie i zaplanowanie zadań dla poszczególnych hurtowni
        $this->schedule_individual_tasks();
    }

    /**
     * Planuje indywidualne zadania dla poszczególnych hurtowni
     */
    private function schedule_individual_tasks()
    {
        $hurtownie = array(
            'hurtownia_1',
            'hurtownia_2',
            'hurtownia_3',
            'hurtownia_4',
            'hurtownia_5'
        );

        foreach ($hurtownie as $hurtownia) {
            $enabled = get_option("mhi_{$hurtownia}_enabled", 0);

            if ($enabled) {
                $interval = get_option("mhi_{$hurtownia}_interval", 'daily');
                $hook_name = "mhi_cron_fetch_" . $hurtownia;

                // Jeśli zadanie nie jest jeszcze zaplanowane, zaplanuj je
                if (!wp_next_scheduled($hook_name)) {
                    wp_schedule_event(time(), $interval, $hook_name);
                }
            } else {
                // Jeśli hurtownia jest wyłączona, usuń zadanie
                $hook_name = "mhi_cron_fetch_" . $hurtownia;
                wp_clear_scheduled_hook($hook_name);
            }
        }
    }

    /**
     * Pobiera dane ze wszystkich hurtowni
     */
    public function fetch_all_wholesalers()
    {
        $this->fetch_hurtownia_1();
        $this->fetch_hurtownia_2();
        $this->fetch_hurtownia_3();
        $this->fetch_hurtownia_4();
        $this->fetch_hurtownia_5();
    }

    /**
     * Pobiera dane z Hurtowni 1
     */
    public function fetch_hurtownia_1()
    {
        // Sprawdzenie, czy integracja jest włączona
        $enabled = get_option('mhi_hurtownia_1_enabled', 0);
        if (!$enabled) {
            return;
        }

        // Uruchomienie integracji
        $hurtownia = new MHI_Hurtownia_1();
        $hurtownia->fetch_files();
    }

    /**
     * Pobiera dane z Hurtowni 2
     */
    public function fetch_hurtownia_2()
    {
        // Sprawdzenie, czy integracja jest włączona
        $enabled = get_option('mhi_hurtownia_2_enabled', 0);
        if (!$enabled) {
            return;
        }

        // Uruchomienie integracji
        $hurtownia = new MHI_Hurtownia_2();
        $hurtownia->fetch_files();
    }

    /**
     * Pobiera dane z Hurtowni 3
     */
    public function fetch_hurtownia_3()
    {
        // Sprawdzenie, czy integracja jest włączona
        $enabled = get_option('mhi_hurtownia_3_enabled', 0);
        if (!$enabled) {
            return;
        }

        // Uruchomienie integracji
        $hurtownia = new MHI_Hurtownia_3();
        $hurtownia->fetch_files();
    }

    /**
     * Pobiera dane z Hurtowni 4
     */
    public function fetch_hurtownia_4()
    {
        // Sprawdzenie, czy integracja jest włączona
        $enabled = get_option('mhi_hurtownia_4_enabled', 0);
        if (!$enabled) {
            return;
        }

        // Uruchomienie integracji
        $hurtownia = new MHI_Hurtownia_4();
        $hurtownia->fetch_files();
    }

    /**
     * Pobiera dane z Hurtowni 5
     */
    public function fetch_hurtownia_5()
    {
        // Sprawdzenie, czy integracja jest włączona
        $enabled = get_option('mhi_hurtownia_5_enabled', 0);
        if (!$enabled) {
            return;
        }

        // Uruchomienie integracji
        $hurtownia = new MHI_Hurtownia_5();
        $hurtownia->fetch_files();
    }

    /**
     * Obsługuje zadanie pobierania plików dla pojedynczej hurtowni.
     *
     * @param string $hurtownia_id Identyfikator hurtowni.
     */
    public function single_download_task($hurtownia_id)
    {
        $integration = $this->get_integration($hurtownia_id);
        if (!$integration) {
            MHI_Logger::error('Nie znaleziono hurtowni o ID: ' . $hurtownia_id);
            return;
        }

        // Sprawdź, czy nie anulowano pobierania
        if (get_option('mhi_cancel_download_' . $hurtownia_id, false)) {
            MHI_Logger::info('Anulowano pobieranie dla hurtowni: ' . $hurtownia_id);
            update_option('mhi_download_status_' . $hurtownia_id, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
            return;
        }

        try {
            // Pobierz pliki
            $files = $integration->fetch_files();

            // Przetwórz pobrane pliki
            if (!empty($files)) {
                $processed_files = $integration->process_files($files);
                update_option('mhi_download_status_' . $hurtownia_id, sprintf(
                    __('Zakończono pobieranie. Pobrano %d plików.', 'multi-hurtownie-integration'),
                    count($processed_files)
                ));
            } else {
                update_option('mhi_download_status_' . $hurtownia_id, __('Nie znaleziono plików do pobrania.', 'multi-hurtownie-integration'));
            }
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas pobierania plików dla hurtowni ' . $hurtownia_id . ': ' . $e->getMessage());
            update_option('mhi_download_status_' . $hurtownia_id, __('Błąd podczas pobierania plików.', 'multi-hurtownie-integration'));
        }
    }

    /**
     * Anuluje zadanie pobierania dla hurtowni.
     *
     * @param string $hurtownia_id Identyfikator hurtowni.
     */
    public function cancel_download_task($hurtownia_id)
    {
        $integration = $this->get_integration($hurtownia_id);
        if (!$integration || !method_exists($integration, 'cancel_download')) {
            MHI_Logger::warning('Nie można anulować pobierania dla hurtowni: ' . $hurtownia_id);
            return;
        }

        // Anuluj pobieranie
        $integration->cancel_download();
        update_option('mhi_download_status_' . $hurtownia_id, __('Anulowano pobieranie.', 'multi-hurtownie-integration'));
        MHI_Logger::info('Anulowano pobieranie dla hurtowni: ' . $hurtownia_id);
    }

    /**
     * Pobiera instancję integracji dla danej hurtowni.
     *
     * @param string $hurtownia_id Identyfikator hurtowni.
     * @return MHI_Integration_Interface|null Instancja integracji lub null.
     */
    private function get_integration($hurtownia_id)
    {
        // Automatycznie identyfikuj i ładuj klasy integracji
        $class_name = 'MHI_' . ucfirst(str_replace('hurtownia_', 'Hurtownia_', $hurtownia_id));

        if (class_exists($class_name)) {
            $integration = new $class_name();
            if ($integration instanceof MHI_Integration_Interface) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Tworzy zadanie cron do automatycznego czyszczenia pustych mediów
     */
    public function schedule_media_cleanup()
    {
        if (!wp_next_scheduled('mhi_media_cleanup_event')) {
            // Zaplanuj czyszczenie mediów raz dziennie
            wp_schedule_event(time(), 'daily', 'mhi_media_cleanup_event');
        }
    }

    /**
     * Wykonuje czyszczenie pustych mediów
     */
    public function cleanup_empty_media()
    {
        // Limit czasu wykonania skryptu (300 sekund = 5 minut)
        set_time_limit(300);

        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');

        // Inicjalizuj licznik
        $deleted_count = 0;

        // Log do pliku
        $log_file = MHI_PLUGIN_DIR . 'logs/media-cleanup-' . date('Y-m-d') . '.log';
        $log_dir = dirname($log_file);

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log = fopen($log_file, 'a');
        fwrite($log, date('[Y-m-d H:i:s]') . " Rozpoczęcie automatycznego czyszczenia pustych mediów\n");

        // Pobieraj media w porcjach
        $paged = 1;
        $per_page = 100;

        do {
            $attachments = get_posts([
                'post_type' => 'attachment',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'post_status' => 'any'
            ]);

            if (empty($attachments)) {
                break;
            }

            // Sprawdź każdy załącznik w bieżącej porcji
            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);

                // Jeśli plik nie istnieje fizycznie
                if (!file_exists($file_path) || empty($file_path)) {
                    // Zapisz log
                    fwrite($log, date('[Y-m-d H:i:s]') . " Usuwanie pustego wpisu: ID={$attachment->ID}, Tytuł={$attachment->post_title}, Ścieżka={$file_path}\n");

                    // Usuń załącznik i powiązane metadane
                    wp_delete_attachment($attachment->ID, true);
                    $deleted_count++;
                }
            }

            // Wyczyść pamięć
            wp_reset_postdata();

            // Przejdź do następnej strony
            $paged++;

        } while (!empty($attachments));

        // Zapisz podsumowanie
        fwrite($log, date('[Y-m-d H:i:s]') . " Zakończono czyszczenie. Usunięto {$deleted_count} pustych wpisów medialnych.\n");
        fclose($log);
    }
}
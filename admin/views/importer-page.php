<?php
/**
 * Szablon strony importera produktów bez użycia AJAX
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Zwiększenie limitów
ini_set('memory_limit', '512M');
set_time_limit(300);

// Obsługa akcji
if (isset($_POST['mhi_importer_action'])) {
    // Weryfikacja nonce
    if (!isset($_POST['mhi_importer_nonce']) || !wp_verify_nonce($_POST['mhi_importer_nonce'], 'mhi_importer_action')) {
        echo '<div class="notice notice-error"><p>Błąd weryfikacji bezpieczeństwa. Proszę spróbować ponownie.</p></div>';
    } else {
        $action = sanitize_text_field($_POST['mhi_importer_action']);
        $supplier = isset($_POST['mhi_supplier']) ? sanitize_text_field($_POST['mhi_supplier']) : '';

        if (!empty($supplier)) {
            // Obsługa akcji
            switch ($action) {
                case 'start':
                    // Resetuj flagę zatrzymania
                    delete_option('mhi_stop_import_' . $supplier);

                    // Uruchom import
                    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/class-mhi-importer.php';
                    $importer = new MHI_Importer($supplier);
                    $result = $importer->start_import();

                    if ($result === true) {
                        echo '<div class="notice notice-success"><p>Import produktów z hurtowni <strong>' . esc_html($supplier) . '</strong> został rozpoczęty.</p></div>';
                    } else {
                        $error_message = is_wp_error($result) ? $result->get_error_message() : 'Nieznany błąd podczas rozpoczynania importu';
                        echo '<div class="notice notice-error"><p>Błąd: ' . esc_html($error_message) . '</p></div>';
                    }
                    break;

                case 'stop':
                    // Ustaw flagę zatrzymania
                    update_option('mhi_stop_import_' . $supplier, true);

                    // Ustaw status na "stopping"
                    $status_option = 'mhi_import_status_' . $supplier;
                    $status = get_option($status_option, []);
                    if (!empty($status) && isset($status['status']) && $status['status'] === 'running') {
                        $status['status'] = 'stopping';
                        $status['message'] = 'Zatrzymywanie importu...';
                        update_option($status_option, $status);
                    }

                    echo '<div class="notice notice-warning"><p>Zatrzymywanie importu z hurtowni <strong>' . esc_html($supplier) . '</strong>. Poczekaj na zakończenie bieżącej partii.</p></div>';
                    break;

                case 'resume':
                    // Sprawdź czy import był wcześniej zatrzymany
                    $status_option = 'mhi_import_status_' . $supplier;
                    $status = get_option($status_option, []);

                    if (!empty($status) && isset($status['status']) && ($status['status'] === 'stopped' || $status['status'] === 'error')) {
                        // Resetuj flagę zatrzymania
                        delete_option('mhi_stop_import_' . $supplier);

                        // Kontynuuj import od ostatniego przetworzonego produktu
                        $last_processed = isset($status['processed']) ? $status['processed'] : 0;
                        $batch_number = floor($last_processed / 10) + 1; // Zakładamy batch_size = 10

                        // Ustaw status na "running"
                        $status['status'] = 'running';
                        $status['message'] = 'Wznawianie importu od partii ' . $batch_number;
                        update_option($status_option, $status);

                        // Uruchom proces importu
                        if (function_exists('as_schedule_single_action')) {
                            as_schedule_single_action(time(), 'mhi_process_import_batch', [
                                'import_id' => 'resume_' . $supplier . '_' . time(),
                                'supplier_name' => $supplier,
                                'batch_number' => $batch_number
                            ]);
                            echo '<div class="notice notice-success"><p>Wznowiono import z hurtowni <strong>' . esc_html($supplier) . '</strong> od partii ' . $batch_number . '.</p></div>';
                        } else {
                            echo '<div class="notice notice-error"><p>Nie można wznowić importu - Action Scheduler nie jest dostępny.</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>Nie można wznowić importu. Import nie był wcześniej zatrzymany lub jest w trakcie.</p></div>';
                    }
                    break;

                default:
                    echo '<div class="notice notice-error"><p>Nieznana akcja.</p></div>';
                    break;
            }
        } else {
            echo '<div class="notice notice-error"><p>Nie podano dostawcy.</p></div>';
        }
    }
}

// Pobierz dostawców
$suppliers = [
    'malfini' => 'Malfini',
    'axpol' => 'Axpol',
    'inspirion' => 'Inspirion',
    'macma' => 'Macma',
    'par' => 'Par'
];

// Sprawdź statusy importów dla wszystkich dostawców
$import_statuses = [];
foreach ($suppliers as $supplier_id => $supplier_name) {
    $status = get_option('mhi_import_status_' . $supplier_id, []);
    $import_statuses[$supplier_id] = !empty($status) ? $status : [
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
    ];
}

?>
<div class="wrap">
    <h1>Importer produktów z hurtowni</h1>

    <p>Ta strona umożliwia import produktów z różnych hurtowni do WooCommerce. Importy działają w tle i można je
        kontrolować przy pomocy przycisków poniżej.</p>

    <div id="mhi-importers">
        <?php foreach ($suppliers as $supplier_id => $supplier_name):
            $status = $import_statuses[$supplier_id];
            $is_running = ($status['status'] === 'running');
            $is_stopping = ($status['status'] === 'stopping');
            $is_completed = ($status['status'] === 'completed');
            $is_stopped = ($status['status'] === 'stopped');
            $is_error = ($status['status'] === 'error');
            $is_idle = ($status['status'] === 'idle' || empty($status['status']));

            // Sprawdź czy plik XML istnieje
            $upload_dir = wp_upload_dir();
            $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier_id . '/woocommerce_import_' . $supplier_id . '.xml';
            $xml_exists = file_exists($xml_file);

            // Przygotuj klasy CSS
            $section_class = 'mhi-import-section';
            if ($is_running)
                $section_class .= ' is-running';
            if ($is_completed)
                $section_class .= ' is-completed';
            if ($is_stopped)
                $section_class .= ' is-stopped';
            if ($is_error)
                $section_class .= ' is-error';
            ?>

            <div class="<?php echo esc_attr($section_class); ?>"
                style="background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php echo esc_html($supplier_name); ?></h2>

                <?php if (!$xml_exists): ?>
                    <div class="notice notice-warning inline">
                        <p>Nie znaleziono pliku XML dla hurtowni <?php echo esc_html($supplier_name); ?>. Najpierw wygeneruj
                            plik XML.</p>
                    </div>
                <?php endif; ?>

                <div class="mhi-import-status">
                    <div class="mhi-import-status-info" style="margin-bottom: 10px; font-size: 14px;">
                        <?php echo esc_html($status['message']); ?>
                    </div>

                    <?php if ($is_running || $is_stopping || $is_completed || $is_stopped || $is_error): ?>
                        <div class="mhi-import-progress" style="margin-bottom: 15px;">
                            <div class="mhi-progress-bar"
                                style="background: #f0f0f0; border-radius: 3px; height: 20px; width: 100%;">
                                <div
                                    style="background: <?php echo $is_error ? '#dc3232' : ($is_completed ? '#46b450' : '#0073aa'); ?>; height: 100%; width: <?php echo esc_attr($status['percent']); ?>%; border-radius: 3px; transition: width 0.3s ease;">
                                </div>
                            </div>
                            <div class="mhi-progress-stats" style="display: flex; margin-top: 5px; font-size: 12px;">
                                <div style="flex: 1;"><?php echo esc_html($status['processed']); ?> z
                                    <?php echo esc_html($status['total']); ?> (<?php echo esc_html($status['percent']); ?>%)
                                </div>
                                <div>
                                    Utworzono: <?php echo esc_html($status['created']); ?> |
                                    Zaktualizowano: <?php echo esc_html($status['updated']); ?> |
                                    Pominięto: <?php echo esc_html($status['skipped']); ?> |
                                    Błędy: <?php echo esc_html($status['failed']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mhi-import-controls">
                        <form method="post" style="display: inline-block; margin-right: 5px;">
                            <?php wp_nonce_field('mhi_importer_action', 'mhi_importer_nonce'); ?>
                            <input type="hidden" name="mhi_supplier" value="<?php echo esc_attr($supplier_id); ?>">

                            <?php if ($is_idle || $is_completed || $is_stopped || $is_error): ?>
                                <input type="hidden" name="mhi_importer_action" value="start">
                                <button type="submit" class="button button-primary" <?php echo !$xml_exists ? 'disabled' : ''; ?>>
                                    <?php echo $is_completed || $is_stopped || $is_error ? 'Rozpocznij ponownie' : 'Rozpocznij import'; ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($is_running): ?>
                                <input type="hidden" name="mhi_importer_action" value="stop">
                                <button type="submit" class="button button-secondary">Zatrzymaj import</button>
                            <?php endif; ?>

                            <?php if ($is_stopped || $is_error): ?>
                                <input type="hidden" name="mhi_importer_action" value="resume">
                                <button type="submit" class="button button-secondary" <?php echo !$xml_exists ? 'disabled' : ''; ?>>Wznów import</button>
                            <?php endif; ?>
                        </form>

                        <?php if ($xml_exists): ?>
                            <a href="<?php echo esc_url(plugins_url('../../../import.php', __FILE__) . '?admin_key=mhi_import_access&supplier=' . $supplier_id); ?>"
                                target="_blank" class="button button-secondary">Test importera</a>
                        <?php endif; ?>

                        <!-- Link do generowania XML -->
                        <a href="#" class="button button-secondary">Generuj XML</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mhi-import-info"
        style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
        <h3>Informacje o procesie importu</h3>
        <p>Import produktów odbywa się w tle i jest podzielony na partie. Proces ten może trwać od kilku minut do kilku
            godzin, w zależności od ilości produktów.</p>
        <p>Możesz zamknąć tę stronę, a import będzie kontynuowany w tle. Możesz wrócić tutaj w dowolnym momencie, aby
            sprawdzić status importu lub go zatrzymać.</p>
        <p><strong>Uwaga:</strong> Zatrzymanie importu spowoduje przerwanie procesu po zakończeniu bieżącej partii.
            Możesz później wznowić import od miejsca, w którym został zatrzymany.</p>
    </div>

    <script>
        // Odświeżanie strony co 30 sekund dla aktualizacji statusu
        setTimeout(function () {
            window.location.reload();
        }, 30000);
    </script>
</div>
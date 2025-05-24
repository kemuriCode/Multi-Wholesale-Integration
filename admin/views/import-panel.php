<?php
/**
 * Panel importu produktów
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mhi-admin-panel">
    <h1><?php _e('Panel importu produktów', 'multi-hurtownie-integration'); ?></h1>

    <?php
    // Obsługa żądania importu
    if (isset($_POST['mhi_start_import']) && isset($_POST['supplier']) && check_admin_referer('mhi_start_import_action', 'mhi_import_nonce')) {
        $supplier = sanitize_text_field($_POST['supplier']);

        // Sprawdź uprawnienia
        if (current_user_can('manage_woocommerce')) {
            // Wykorzystaj nowy importer bezpośredni
            require_once(MHI_PLUGIN_DIR . 'includes/class-mhi-direct-importer.php');

            $importer = new MHI_Direct_Importer($supplier);
            $result = $importer->import();

            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . __('Nie masz wystarczających uprawnień do wykonania tej operacji.', 'multi-hurtownie-integration') . '</p></div>';
        }
    }

    // Pobierz listę aktywnych hurtowni
    $active_suppliers = get_option('mhi_active_suppliers', array());

    // Pobierz statusy importów dla każdej hurtowni
    $import_statuses = array();
    foreach ($active_suppliers as $supplier) {
        $status = get_option('mhi_import_status_' . $supplier, array());
        if (!empty($status)) {
            $import_statuses[$supplier] = $status;
        }
    }
    ?>

    <div class="mhi-importers">
        <h2><?php _e('Dostępne hurtownie', 'multi-hurtownie-integration'); ?></h2>

        <?php if (empty($active_suppliers)): ?>
            <p><?php _e('Brak aktywnych hurtowni. Aktywuj integracje w ustawieniach.', 'multi-hurtownie-integration'); ?>
            </p>
        <?php else: ?>
            <div class="mhi-supplier-list">
                <?php foreach ($active_suppliers as $supplier): ?>
                    <?php
                    // Sprawdź czy plik XML istnieje
                    $upload_dir = wp_upload_dir();
                    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';
                    $xml_exists = file_exists($xml_file);

                    // Pobierz status importu
                    $status = isset($import_statuses[$supplier]) ? $import_statuses[$supplier] : array();
                    $is_running = isset($status['status']) && $status['status'] === 'running';
                    $is_completed = isset($status['status']) && $status['status'] === 'completed';
                    $is_error = isset($status['status']) && $status['status'] === 'error';

                    // Formatuj czas
                    $elapsed_time = '';
                    if (isset($status['elapsed_time'])) {
                        $seconds = $status['elapsed_time'];
                        if ($seconds < 60) {
                            $elapsed_time = $seconds . ' ' . __('sekund', 'multi-hurtownie-integration');
                        } elseif ($seconds < 3600) {
                            $minutes = floor($seconds / 60);
                            $secs = $seconds % 60;
                            $elapsed_time = $minutes . ' ' . __('minut', 'multi-hurtownie-integration') .
                                ($secs > 0 ? ', ' . $secs . ' ' . __('sekund', 'multi-hurtownie-integration') : '');
                        } else {
                            $hours = floor($seconds / 3600);
                            $minutes = floor(($seconds % 3600) / 60);
                            $elapsed_time = $hours . ' ' . __('godzin', 'multi-hurtownie-integration') .
                                ($minutes > 0 ? ', ' . $minutes . ' ' . __('minut', 'multi-hurtownie-integration') : '');
                        }
                    }

                    // Formatuj szacowany czas pozostały
                    $estimated_time = '';
                    if (isset($status['estimated_time']) && $status['estimated_time'] > 0) {
                        $seconds = $status['estimated_time'];
                        if ($seconds < 60) {
                            $estimated_time = $seconds . ' ' . __('sekund', 'multi-hurtownie-integration');
                        } elseif ($seconds < 3600) {
                            $minutes = floor($seconds / 60);
                            $secs = $seconds % 60;
                            $estimated_time = $minutes . ' ' . __('minut', 'multi-hurtownie-integration') .
                                ($secs > 0 ? ', ' . $secs . ' ' . __('sekund', 'multi-hurtownie-integration') : '');
                        } else {
                            $hours = floor($seconds / 3600);
                            $minutes = floor(($seconds % 3600) / 60);
                            $estimated_time = $hours . ' ' . __('godzin', 'multi-hurtownie-integration') .
                                ($minutes > 0 ? ', ' . $minutes . ' ' . __('minut', 'multi-hurtownie-integration') : '');
                        }
                    }
                    ?>

                    <div class="mhi-supplier-card">
                        <h3><?php echo esc_html(ucfirst($supplier)); ?></h3>

                        <?php if ($xml_exists): ?>
                            <div class="mhi-supplier-info">
                                <p><strong><?php _e('Plik XML:', 'multi-hurtownie-integration'); ?></strong>
                                    <?php _e('Dostępny', 'multi-hurtownie-integration'); ?></p>
                                <p><strong><?php _e('Ostatnia modyfikacja:', 'multi-hurtownie-integration'); ?></strong>
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)); ?>
                                </p>

                                <?php if (!empty($status)): ?>
                                    <div class="mhi-import-status">
                                        <h4><?php _e('Status importu', 'multi-hurtownie-integration'); ?></h4>

                                        <?php if ($is_running): ?>
                                            <div class="mhi-status-running">
                                                <p><strong><?php _e('Status:', 'multi-hurtownie-integration'); ?></strong>
                                                    <?php _e('W trakcie', 'multi-hurtownie-integration'); ?></p>

                                                <?php if (isset($status['percent'])): ?>
                                                    <div class="mhi-progress-bar">
                                                        <div class="mhi-progress" style="width: <?php echo esc_attr($status['percent']); ?>%">
                                                        </div>
                                                    </div>
                                                    <p><strong><?php _e('Postęp:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['percent']); ?>%</p>
                                                <?php endif; ?>

                                                <?php if (isset($status['processed']) && isset($status['total'])): ?>
                                                    <p><strong><?php _e('Przetworzono:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['processed']); ?> /
                                                        <?php echo esc_html($status['total']); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($elapsed_time)): ?>
                                                    <p><strong><?php _e('Czas trwania:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($elapsed_time); ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($estimated_time)): ?>
                                                    <p><strong><?php _e('Pozostało:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($estimated_time); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($is_completed): ?>
                                            <div class="mhi-status-completed">
                                                <p><strong><?php _e('Status:', 'multi-hurtownie-integration'); ?></strong>
                                                    <?php _e('Zakończony', 'multi-hurtownie-integration'); ?></p>

                                                <?php if (isset($status['processed'])): ?>
                                                    <p><strong><?php _e('Przetworzono:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['processed']); ?>
                                                        <?php _e('produktów', 'multi-hurtownie-integration'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (isset($status['created'])): ?>
                                                    <p><strong><?php _e('Utworzono:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['created']); ?>
                                                        <?php _e('produktów', 'multi-hurtownie-integration'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (isset($status['updated'])): ?>
                                                    <p><strong><?php _e('Zaktualizowano:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['updated']); ?>
                                                        <?php _e('produktów', 'multi-hurtownie-integration'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (isset($status['skipped'])): ?>
                                                    <p><strong><?php _e('Pominięto:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['skipped']); ?>
                                                        <?php _e('produktów', 'multi-hurtownie-integration'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (isset($status['failed'])): ?>
                                                    <p><strong><?php _e('Błędy:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['failed']); ?>
                                                        <?php _e('produktów', 'multi-hurtownie-integration'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($elapsed_time)): ?>
                                                    <p><strong><?php _e('Czas trwania:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($elapsed_time); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($is_error): ?>
                                            <div class="mhi-status-error">
                                                <p><strong><?php _e('Status:', 'multi-hurtownie-integration'); ?></strong>
                                                    <?php _e('Błąd', 'multi-hurtownie-integration'); ?></p>
                                                <?php if (isset($status['message'])): ?>
                                                    <p><strong><?php _e('Komunikat:', 'multi-hurtownie-integration'); ?></strong>
                                                        <?php echo esc_html($status['message']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="mhi-status-idle">
                                                <p><strong><?php _e('Status:', 'multi-hurtownie-integration'); ?></strong>
                                                    <?php _e('Oczekuje', 'multi-hurtownie-integration'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" class="mhi-import-form">
                                    <?php wp_nonce_field('mhi_start_import_action', 'mhi_import_nonce'); ?>
                                    <input type="hidden" name="supplier" value="<?php echo esc_attr($supplier); ?>">

                                    <?php if ($is_running): ?>
                                        <p><?php _e('Import w trakcie. Poczekaj na zakończenie.', 'multi-hurtownie-integration'); ?></p>
                                        <button type="button" class="button"
                                            disabled><?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?></button>
                                    <?php else: ?>
                                        <p class="mhi-import-note">
                                            <?php _e('Kliknij przycisk poniżej, aby rozpocząć import produktów. Proces może trwać dłuższy czas, zależnie od ilości produktów.', 'multi-hurtownie-integration'); ?>
                                        </p>
                                        <button type="submit" name="mhi_start_import"
                                            class="button button-primary"><?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?></button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="mhi-supplier-info mhi-no-xml">
                                <p><?php _e('Brak pliku XML do importu. Wykonaj najpierw pobranie danych.', 'multi-hurtownie-integration'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .mhi-supplier-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 3px;
    }

    .mhi-supplier-card h3 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .mhi-progress-bar {
        background-color: #f0f0f0;
        border-radius: 3px;
        height: 20px;
        margin: 10px 0;
        overflow: hidden;
    }

    .mhi-progress {
        background-color: #0073aa;
        height: 100%;
        transition: width 0.3s ease;
    }

    .mhi-status-running {
        background-color: #f0f8ff;
        padding: 10px;
        border-left: 4px solid #0073aa;
        margin-bottom: 15px;
    }

    .mhi-status-completed {
        background-color: #f0fff0;
        padding: 10px;
        border-left: 4px solid #46b450;
        margin-bottom: 15px;
    }

    .mhi-status-error {
        background-color: #fff0f0;
        padding: 10px;
        border-left: 4px solid #dc3232;
        margin-bottom: 15px;
    }

    .mhi-status-idle {
        background-color: #f7f7f7;
        padding: 10px;
        border-left: 4px solid #cccccc;
        margin-bottom: 15px;
    }

    .mhi-import-form {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    .mhi-import-note {
        font-style: italic;
        color: #666;
    }

    .mhi-supplier-info.mhi-no-xml {
        background-color: #fef7f1;
        padding: 10px;
        border-left: 4px solid #ffb900;
    }
</style>
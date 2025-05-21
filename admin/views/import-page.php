<?php
/**
 * Strona wyboru metody importu
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mhi-admin-panel">
    <h1><?php _e('Import produktów', 'multi-hurtownie-integration'); ?></h1>

    <div class="notice notice-info mhi-notice">
        <p><?php _e('Wybierz hurtownię i metodę importu produktów. Zalecamy używanie prostego importera dla najlepszej wydajności.', 'multi-hurtownie-integration'); ?>
        </p>
    </div>

    <?php
    // Pobierz listę aktywnych hurtowni
    $active_suppliers = get_option('mhi_active_suppliers', array());
    ?>

    <div class="mhi-importers-container">
        <?php if (empty($active_suppliers)): ?>
            <div class="mhi-empty-state">
                <div class="mhi-empty-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <h2><?php _e('Brak aktywnych hurtowni', 'multi-hurtownie-integration'); ?></h2>
                <p><?php _e('Aby rozpocząć import produktów, najpierw aktywuj integracje z hurtowniami w ustawieniach.', 'multi-hurtownie-integration'); ?>
                </p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhi-settings')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Przejdź do ustawień', 'multi-hurtownie-integration'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="mhi-section-header">
                <h2><?php _e('Wybierz hurtownię do importu', 'multi-hurtownie-integration'); ?></h2>
                <p class="description">
                    <?php _e('Poniżej znajduje się lista aktywnych hurtowni, z których możesz importować produkty', 'multi-hurtownie-integration'); ?>
                </p>
            </div>

            <div class="mhi-suppliers-grid">
                <?php foreach ($active_suppliers as $supplier): ?>
                    <?php
                    // Sprawdź czy plik XML istnieje
                    $upload_dir = wp_upload_dir();
                    $xml_file = trailingslashit($upload_dir['basedir']) . 'hurtownie/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';
                    $xml_exists = file_exists($xml_file);
                    $xml_date = $xml_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)) : '';

                    // Określ ikonę dla danej hurtowni
                    $supplier_icon = 'dashicons-store';
                    switch ($supplier) {
                        case 'axpol':
                            $supplier_icon = 'dashicons-cart';
                            break;
                        case 'inspirion':
                            $supplier_icon = 'dashicons-art';
                            break;
                        case 'macma':
                            $supplier_icon = 'dashicons-image-filter';
                            break;
                        case 'malfini':
                            $supplier_icon = 'dashicons-admin-customizer';
                            break;
                        case 'par':
                            $supplier_icon = 'dashicons-products';
                            break;
                    }
                    ?>
                    <div class="mhi-supplier-card <?php echo $xml_exists ? 'has-xml' : 'no-xml'; ?>">
                        <div class="mhi-card-header">
                            <span class="mhi-supplier-icon dashicons <?php echo esc_attr($supplier_icon); ?>"></span>
                            <h3><?php echo esc_html(ucfirst($supplier)); ?></h3>
                        </div>

                        <?php if ($xml_exists): ?>
                            <div class="mhi-supplier-info">
                                <div class="mhi-info-item">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <span><?php _e('Plik XML dostępny', 'multi-hurtownie-integration'); ?></span>
                                </div>
                                <div class="mhi-info-item">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <span><?php echo esc_html($xml_date); ?></span>
                                </div>
                                <?php
                                // Sprawdź ile produktów jest w pliku XML
                                if (file_exists($xml_file)) {
                                    libxml_use_internal_errors(true);
                                    $xml = simplexml_load_file($xml_file);
                                    if ($xml) {
                                        $products_count = count($xml->children());
                                        echo '<div class="mhi-info-item">
                                            <span class="dashicons dashicons-tag"></span>
                                            <span>' . sprintf(_n('%s produkt', '%s produktów', $products_count, 'multi-hurtownie-integration'), number_format_i18n($products_count)) . '</span>
                                        </div>';
                                    }
                                    libxml_clear_errors();
                                }
                                ?>
                            </div>

                            <div class="mhi-import-options">
                                <h4><?php _e('Wybierz metodę importu', 'multi-hurtownie-integration'); ?></h4>

                                <div class="mhi-buttons-container">
                                    <a href="<?php echo esc_url(plugins_url('/import.php?supplier=' . $supplier, dirname(dirname(__FILE__)))); ?>"
                                        class="mhi-import-button mhi-simple-import">
                                        <div class="mhi-button-icon">
                                            <span class="dashicons dashicons-performance"></span>
                                        </div>
                                        <div class="mhi-button-content">
                                            <span
                                                class="mhi-button-title"><?php _e('Prosty Import', 'multi-hurtownie-integration'); ?></span>
                                            <span
                                                class="mhi-button-desc"><?php _e('Szybki i wydajny import bezpośrednio z pliku XML', 'multi-hurtownie-integration'); ?></span>
                                        </div>
                                        <div class="mhi-features">
                                            <span class="mhi-feature-tag"><?php _e('WebP', 'multi-hurtownie-integration'); ?></span>
                                            <span
                                                class="mhi-feature-tag"><?php _e('Optymalizacja', 'multi-hurtownie-integration'); ?></span>
                                        </div>
                                    </a>

                                    <a href="<?php echo esc_url(admin_url('admin.php?page=mhi-import&panel=import&supplier=' . $supplier)); ?>"
                                        class="mhi-import-button mhi-advanced-import">
                                        <div class="mhi-button-icon">
                                            <span class="dashicons dashicons-admin-tools"></span>
                                        </div>
                                        <div class="mhi-button-content">
                                            <span
                                                class="mhi-button-title"><?php _e('Panel Zaawansowany', 'multi-hurtownie-integration'); ?></span>
                                            <span
                                                class="mhi-button-desc"><?php _e('Import z dodatkowymi opcjami (może działać wolniej)', 'multi-hurtownie-integration'); ?></span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mhi-supplier-info mhi-no-xml">
                                <div class="mhi-info-item">
                                    <span class="dashicons dashicons-warning"></span>
                                    <span><?php _e('Brak pliku XML do importu', 'multi-hurtownie-integration'); ?></span>
                                </div>
                                <p><?php _e('Najpierw wykonaj pobranie danych z hurtowni.', 'multi-hurtownie-integration'); ?></p>
                            </div>

                            <a href="<?php echo esc_url(admin_url('admin.php?page=mhi-settings&tab=' . $supplier)); ?>"
                                class="mhi-download-button">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Pobierz dane', 'multi-hurtownie-integration'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .mhi-notice {
        border-left-width: 4px;
        padding: 12px 15px;
        background: #f8f8f8;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        margin: 20px 0;
    }

    .mhi-section-header {
        margin: 25px 0 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .mhi-section-header h2 {
        margin: 0 0 5px;
        font-weight: 500;
        color: #23282d;
    }

    .mhi-section-header .description {
        margin: 5px 0 0;
        color: #666;
        font-style: normal;
    }

    .mhi-empty-state {
        text-align: center;
        padding: 40px 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        margin: 25px 0;
    }

    .mhi-empty-icon {
        margin-bottom: 20px;
    }

    .mhi-empty-icon .dashicons {
        font-size: 48px;
        width: 48px;
        height: 48px;
        color: #e5e5e5;
    }

    .mhi-empty-state h2 {
        margin: 0 0 15px;
        color: #23282d;
    }

    .mhi-empty-state p {
        margin: 0 0 20px;
        color: #666;
    }

    .mhi-suppliers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .mhi-supplier-card {
        background: #fff;
        border: 1px solid #e5e5e5;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        border-radius: 8px;
        padding: 0;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .mhi-supplier-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .mhi-supplier-card.has-xml {
        border-top: 3px solid #2271b1;
    }

    .mhi-supplier-card.no-xml {
        border-top: 3px solid #f0c33c;
    }

    .mhi-card-header {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .mhi-supplier-icon {
        background: #f7f7f7;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        font-size: 20px;
        color: #2271b1;
    }

    .no-xml .mhi-supplier-icon {
        color: #f0c33c;
    }

    .mhi-card-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 500;
        color: #23282d;
    }

    .mhi-supplier-info {
        margin: 0;
        padding: 15px 20px;
        background: #fafafa;
    }

    .mhi-info-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .mhi-info-item:last-child {
        margin-bottom: 0;
    }

    .mhi-info-item .dashicons {
        color: #2271b1;
        margin-right: 8px;
        font-size: 16px;
    }

    .mhi-no-xml {
        background-color: #fef9e8;
    }

    .mhi-no-xml .dashicons {
        color: #f0c33c;
    }

    .mhi-import-options {
        padding: 15px 20px;
    }

    .mhi-import-options h4 {
        margin: 0 0 10px;
        font-size: 14px;
        color: #23282d;
    }

    .mhi-buttons-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .mhi-import-button {
        display: flex;
        align-items: center;
        text-decoration: none;
        border-radius: 4px;
        padding: 10px 12px;
        transition: all 0.2s;
        border: 1px solid #e5e5e5;
        background: #f8f8f8;
    }

    .mhi-simple-import {
        border-color: #2271b1;
        background: #f0f6fc;
    }

    .mhi-simple-import:hover {
        background: #2271b1;
        color: white;
    }

    .mhi-simple-import:hover .mhi-button-icon .dashicons,
    .mhi-simple-import:hover .mhi-button-title,
    .mhi-simple-import:hover .mhi-button-desc {
        color: white;
    }

    .mhi-advanced-import:hover {
        background: #f0f0f0;
    }

    .mhi-button-icon {
        margin-right: 10px;
    }

    .mhi-button-icon .dashicons {
        color: #2271b1;
        font-size: 18px;
    }

    .mhi-advanced-import .mhi-button-icon .dashicons {
        color: #666;
    }

    .mhi-button-content {
        flex: 1;
    }

    .mhi-button-title {
        display: block;
        font-weight: 500;
        color: #23282d;
        margin-bottom: 2px;
    }

    .mhi-button-desc {
        display: block;
        font-size: 12px;
        color: #666;
    }

    .mhi-download-button {
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border-radius: 4px;
        margin: 15px 20px;
        padding: 10px;
        color: #2271b1;
        background: #f0f6fc;
        border: 1px solid #2271b1;
        font-weight: 500;
        transition: all 0.2s;
    }

    .mhi-download-button:hover {
        background: #2271b1;
        color: white;
    }

    .mhi-download-button .dashicons {
        margin-right: 5px;
    }

    @media (max-width: 782px) {
        .mhi-suppliers-grid {
            grid-template-columns: 1fr;
        }
    }

    .mhi-features {
        display: flex;
        gap: 5px;
        margin-left: 5px;
    }

    .mhi-feature-tag {
        display: inline-block;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 3px;
        background: #2271b1;
        color: white;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .mhi-simple-import:hover .mhi-feature-tag {
        background: white;
        color: #2271b1;
    }
</style>
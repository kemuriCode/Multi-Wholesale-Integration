<?php
/**
 * Manager Mapowania Kategorii
 * 
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdź czy WordPress jest załadowany
if (!function_exists('wp_create_nonce')) {
    return;
}

// Obsługa akcji formularza
if (isset($_POST['mhi_category_mapping_action'])) {
    $action = sanitize_text_field($_POST['mhi_category_mapping_action']);

    if (wp_verify_nonce($_POST['mhi_category_mapping_nonce'], 'mhi_category_mapping_action')) {
        switch ($action) {
            case 'save_mapping':
                MHI_Category_Mapping_Manager::save_mapping();
                break;
            case 'apply_mapping':
                MHI_Category_Mapping_Manager::apply_mapping();
                break;
            case 'create_backup':
                MHI_Category_Mapping_Manager::create_backup();
                break;
            case 'restore_backup':
                MHI_Category_Mapping_Manager::restore_backup();
                break;
            case 'delete_empty_categories':
                MHI_Category_Mapping_Manager::delete_empty_categories();
                break;
        }
    }
}

// Pobieranie danych
$categories_data = MHI_Category_Mapping_Manager::get_categories_data();
$mapping_data = MHI_Category_Mapping_Manager::get_mapping_data();
$backups = MHI_Category_Mapping_Manager::get_backups();
?>

<div class="wrap mhi-category-mapping">
    <h1><span class="dashicons dashicons-category"></span>
        <?php _e('Mapowanie Kategorii - Manager Hurtowni', 'multi-hurtownie-integration'); ?>
    </h1>

    <!-- Instrukcja -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-info"></span>
                <?php _e('📋 Instrukcja użytkowania', 'multi-hurtownie-integration'); ?>
            </h2>
        </div>
        <div class="inside">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <h4 style="margin-top: 0; color: #1976d2;">🎯 Cel systemu:</h4>
                <p><?php _e('System mapowania kategorii pozwala na ujednolicenie kategorii z różnych hurtowni, eliminację duplikatów i stworzenie spójnej struktury kategorii w sklepie.', 'multi-hurtownie-integration'); ?>
                </p>

                <h4 style="color: #1976d2;">📋 Jak to działa:</h4>
                <ol style="margin-left: 20px;">
                    <li><strong><?php _e('Analiza kategorii:', 'multi-hurtownie-integration'); ?></strong>
                        <?php _e('System skanuje wszystkie kategorie z różnych hurtowni i wyświetla je w tabeli.', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><strong><?php _e('Mapowanie:', 'multi-hurtownie-integration'); ?></strong>
                        <?php _e('Wybierz kategorie które chcesz połączyć i określ główną kategorię docelową.', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><strong><?php _e('Zastosowanie:', 'multi-hurtownie-integration'); ?></strong>
                        <?php _e('System przeniesie wszystkie produkty z mapowanych kategorii do kategorii docelowej.', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><strong><?php _e('Czyszczenie:', 'multi-hurtownie-integration'); ?></strong>
                        <?php _e('Puste kategorie zostaną automatycznie usunięte.', 'multi-hurtownie-integration'); ?>
                    </li>
                </ol>

                <h4 style="color: #d32f2f;">⚠️ Ważne informacje:</h4>
                <ul style="margin-left: 20px; color: #d32f2f;">
                    <li><?php _e('• Zawsze rób kopię zapasową przed rozpoczęciem mapowania!', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('• Operacja jest nieodwracalna - używaj ostrożnie!', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('• Mapowanie działa tylko na kategoriach z produktami', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('• System automatycznie wykrywa źródło kategorii (hurtownię)', 'multi-hurtownie-integration'); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Statystyki -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-chart-bar"></span>
                <?php _e('📊 Statystyki kategorii', 'multi-hurtownie-integration'); ?>
            </h2>
        </div>
        <div class="inside">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0; color: #1976d2;"><?php echo count($categories_data['all_categories']); ?></h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Wszystkie kategorie', 'multi-hurtownie-integration'); ?></p>
                </div>
                <div style="background: #f3e5f5; padding: 15px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0; color: #7b1fa2;">
                        <?php echo count($categories_data['categories_with_products']); ?>
                    </h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Kategorie z produktami', 'multi-hurtownie-integration'); ?>
                    </p>
                </div>
                <div style="background: #fff3e0; padding: 15px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0; color: #f57c00;"><?php echo count($categories_data['empty_categories']); ?>
                    </h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Puste kategorie', 'multi-hurtownie-integration'); ?></p>
                </div>
                <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0; color: #388e3c;"><?php echo count($mapping_data); ?></h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Zapisane mapowania', 'multi-hurtownie-integration'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kopie zapasowe -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-backup"></span>
                <?php _e('💾 Kopie zapasowe', 'multi-hurtownie-integration'); ?>
            </h2>
        </div>
        <div class="inside">
            <form method="post" action="">
                <?php if (function_exists('wp_nonce_field')): ?>
                    <?php wp_nonce_field('mhi_category_mapping_action', 'mhi_category_mapping_nonce'); ?>
                <?php endif; ?>
                <input type="hidden" name="mhi_category_mapping_action" value="create_backup">

                <p><?php _e('Zawsze rób kopię zapasową przed rozpoczęciem mapowania kategorii!', 'multi-hurtownie-integration'); ?>
                </p>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="submit" class="button button-primary"
                        value="<?php _e('📦 Utwórz kopię zapasową', 'multi-hurtownie-integration'); ?>">

                    <?php if (!empty($backups)): ?>
                        <select name="backup_to_restore" style="min-width: 200px;">
                            <option value="">
                                <?php _e('-- Wybierz kopię do przywrócenia --', 'multi-hurtownie-integration'); ?>
                            </option>
                            <?php foreach ($backups as $backup): ?>
                                <option value="<?php echo esc_attr($backup['file']); ?>">
                                    <?php echo esc_html($backup['name']); ?>
                                    (<?php echo esc_html($backup['date']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" name="mhi_category_mapping_action" value="restore_backup"
                            class="button button-secondary"
                            onclick="return confirm('<?php echo esc_js(__('Czy na pewno chcesz przywrócić kopię zapasową? Obecne dane zostaną nadpisane!', 'multi-hurtownie-integration')); ?>');">
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($backups)): ?>
                <div style="margin-top: 15px;">
                    <h4><?php _e('Dostępne kopie zapasowe:', 'multi-hurtownie-integration'); ?></h4>
                    <ul style="margin-left: 20px;">
                        <?php foreach ($backups as $backup): ?>
                            <li>
                                <strong><?php echo esc_html($backup['name']); ?></strong>
                                (<?php echo esc_html($backup['date']); ?>) -
                                <?php echo esc_html($backup['size']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mapowanie kategorii -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-admin-links"></span>
                <?php _e('🔗 Mapowanie kategorii', 'multi-hurtownie-integration'); ?>
            </h2>
        </div>
        <div class="inside">
            <form method="post" action="" id="category-mapping-form">
                <?php if (function_exists('wp_nonce_field')): ?>
                    <?php wp_nonce_field('mhi_category_mapping_action', 'mhi_category_mapping_nonce'); ?>
                <?php endif; ?>
                <input type="hidden" name="mhi_category_mapping_action" value="save_mapping">

                <div style="margin-bottom: 20px;">
                    <h4><?php _e('Wybierz kategorie do mapowania:', 'multi-hurtownie-integration'); ?></h4>
                    <p class="description">
                        <?php _e('Zaznacz kategorie które chcesz połączyć. Produkty z zaznaczonych kategorii zostaną przeniesione do głównej kategorii docelowej.', 'multi-hurtownie-integration'); ?>
                    </p>
                </div>

                <div
                    style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="select-all-categories">
                                </th>
                                <th style="width: 200px;"><?php _e('Nazwa kategorii', 'multi-hurtownie-integration'); ?>
                                </th>
                                <th style="width: 100px;"><?php _e('Hurtownia', 'multi-hurtownie-integration'); ?></th>
                                <th style="width: 80px;"><?php _e('Produkty', 'multi-hurtownie-integration'); ?></th>
                                <th style="width: 150px;">
                                    <?php _e('Kategoria docelowa', 'multi-hurtownie-integration'); ?>
                                </th>
                                <th style="width: 100px;"><?php _e('Status', 'multi-hurtownie-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories_data['categories_with_products'] as $category): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_categories[]"
                                            value="<?php echo esc_attr($category['id']); ?>" class="category-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($category['name']); ?></strong>
                                        <br><small style="color: #666;">ID: <?php echo esc_html($category['id']); ?></small>
                                    </td>
                                    <td>
                                        <span
                                            class="supplier-badge supplier-<?php echo esc_attr($category['supplier']); ?>">
                                            <?php echo esc_html($category['supplier']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="product-count"><?php echo esc_html($category['product_count']); ?></span>
                                    </td>
                                    <td>
                                        <select name="target_category[<?php echo esc_attr($category['id']); ?>]"
                                            class="target-category-select">
                                            <option value="">
                                                <?php _e('-- Wybierz kategorię --', 'multi-hurtownie-integration'); ?>
                                            </option>
                                            <?php foreach ($categories_data['all_categories'] as $target_cat): ?>
                                                <?php if ($target_cat['id'] != $category['id']): ?>
                                                    <option value="<?php echo esc_attr($target_cat['id']); ?>" <?php echo (isset($mapping_data[$category['id']]) && $mapping_data[$category['id']] == $target_cat['id']) ? 'selected' : ''; ?>>
                                                        <?php echo esc_html($target_cat['name']); ?>
                                                        (<?php echo esc_html($target_cat['supplier']); ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if (isset($mapping_data[$category['id']])): ?>
                                            <span class="mapped-badge"
                                                style="background: #4caf50; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">
                                                <?php _e('Zmapowana', 'multi-hurtownie-integration'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="unmapped-badge"
                                                style="background: #ff9800; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">
                                                <?php _e('Nie zmapowana', 'multi-hurtownie-integration'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                    <input type="submit" class="button button-primary"
                        value="<?php _e('💾 Zapisz mapowanie', 'multi-hurtownie-integration'); ?>">

                    <input type="submit" name="mhi_category_mapping_action" value="apply_mapping"
                        class="button button-secondary"
                        onclick="return confirm('<?php echo esc_js(__('Czy na pewno chcesz zastosować mapowanie? Produkty zostaną przeniesione do nowych kategorii!', 'multi-hurtownie-integration')); ?>');">
                </div>
            </form>
        </div>
    </div>

    <!-- Czyszczenie pustych kategorii -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-trash"></span>
                <?php _e('🧹 Czyszczenie pustych kategorii', 'multi-hurtownie-integration'); ?>
            </h2>
        </div>
        <div class="inside">
            <form method="post" action="">
                <?php if (function_exists('wp_nonce_field')): ?>
                    <?php wp_nonce_field('mhi_category_mapping_action', 'mhi_category_mapping_nonce'); ?>
                <?php endif; ?>
                <input type="hidden" name="mhi_category_mapping_action" value="delete_empty_categories">

                <p><?php _e('Po zastosowaniu mapowania możesz usunąć puste kategorie, które nie zawierają już żadnych produktów.', 'multi-hurtownie-integration'); ?>
                </p>

                <?php if (!empty($categories_data['empty_categories'])): ?>
                    <div
                        style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <h4 style="margin-top: 0; color: #856404;">
                            <?php printf(__('Znaleziono %d pustych kategorii:', 'multi-hurtownie-integration'), count($categories_data['empty_categories'])); ?>
                        </h4>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <ul style="margin-left: 20px;">
                                <?php foreach ($categories_data['empty_categories'] as $empty_cat): ?>
                                    <li>
                                        <strong><?php echo esc_html($empty_cat['name']); ?></strong>
                                        (<?php echo esc_html($empty_cat['supplier']); ?>) -
                                        ID: <?php echo esc_html($empty_cat['id']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <input type="submit" class="button button-secondary"
                        value="<?php _e('🗑️ Usuń puste kategorie', 'multi-hurtownie-integration'); ?>"
                        onclick="return confirm('<?php echo esc_js(__('Czy na pewno chcesz usunąć wszystkie puste kategorie? Ta operacja jest nieodwracalna!', 'multi-hurtownie-integration')); ?>');">
                <?php else: ?>
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;">
                        <p style="margin: 0; color: #155724;">
                            ✅ <?php _e('Brak pustych kategorii do usunięcia!', 'multi-hurtownie-integration'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<style>
    .mhi-category-mapping .supplier-badge {
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .supplier-malfini {
        background: #e3f2fd;
        color: #1976d2;
    }

    .supplier-axpol {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .supplier-par {
        background: #fff3e0;
        color: #f57c00;
    }

    .supplier-inspirion {
        background: #e8f5e8;
        color: #388e3c;
    }

    .supplier-macma {
        background: #fce4ec;
        color: #c2185b;
    }

    .supplier-anda {
        background: #f1f8e9;
        color: #689f38;
    }

    .mhi-category-mapping .product-count {
        font-weight: bold;
        color: #1976d2;
    }

    .mhi-category-mapping .target-category-select {
        width: 100%;
        max-width: 200px;
    }

    .mhi-category-mapping .mapped-badge {
        background: #4caf50 !important;
        color: white !important;
    }

    .mhi-category-mapping .unmapped-badge {
        background: #ff9800 !important;
        color: white !important;
    }
</style>

<script type="text/javascript">
    jQuery(document).ready(function ($) {
        // Obsługa zaznaczania wszystkich kategorii
        $('#select-all-categories').on('change', function () {
            $('.category-checkbox').prop('checked', $(this).is(':checked'));
        });

        // Automatyczne odznaczanie "zaznacz wszystkie" jeśli któryś checkbox zostanie odznaczony
        $('.category-checkbox').on('change', function () {
            if (!$(this).is(':checked')) {
                $('#select-all-categories').prop('checked', false);
            }

            // Sprawdź czy wszystkie są zaznaczone
            var allChecked = true;
            $('.category-checkbox').each(function () {
                if (!$(this).is(':checked')) {
                    allChecked = false;
                    return false;
                }
            });

            if (allChecked) {
                $('#select-all-categories').prop('checked', true);
            }
        });

        // Walidacja formularza mapowania
        $('#category-mapping-form').on('submit', function (e) {
            var selectedCategories = $('.category-checkbox:checked');
            var hasTargetCategories = false;

            selectedCategories.each(function () {
                var categoryId = $(this).val();
                var targetSelect = $('select[name="target_category[' + categoryId + ']"]');
                if (targetSelect.val() !== '') {
                    hasTargetCategories = true;
                    return false;
                }
            });

            if (selectedCategories.length === 0) {
                alert('<?php echo esc_js(__('Wybierz co najmniej jedną kategorię do mapowania.', 'multi-hurtownie-integration')); ?>');
                e.preventDefault();
                return false;
            }

            if (!hasTargetCategories) {
                alert('<?php echo esc_js(__('Dla każdej zaznaczonej kategorii musisz wybrać kategorię docelową.', 'multi-hurtownie-integration')); ?>');
                e.preventDefault();
                return false;
            }

            return true;
        });
    });
</script>
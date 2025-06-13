<?php
/**
 * Szablon strony ustawień wtyczki
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Pobieranie aktywnej zakładki
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap mhi-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=multi-hurtownie-integration&tab=general"
            class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('Ogólne', 'multi-hurtownie-integration'); ?>
        </a>
        <a href="?page=multi-hurtownie-integration&tab=hurtownia-1"
            class="nav-tab <?php echo $active_tab === 'hurtownia-1' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-customizer"></span>
            <?php _e('Malfini', 'multi-hurtownie-integration'); ?>
        </a>
        <a href="?page=multi-hurtownie-integration&tab=axpol"
            class="nav-tab <?php echo $active_tab === 'axpol' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-cart"></span>
            <?php _e('Axpol', 'multi-hurtownie-integration'); ?>
        </a>
        <a href="?page=multi-hurtownie-integration&tab=par"
            class="nav-tab <?php echo $active_tab === 'par' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-products"></span>
            <?php _e('PAR', 'multi-hurtownie-integration'); ?>
        </a>
        <a href="?page=multi-hurtownie-integration&tab=inspirion"
            class="nav-tab <?php echo $active_tab === 'inspirion' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-art"></span>
            <?php _e('Inspirion', 'multi-hurtownie-integration'); ?>
        </a>
        <a href="?page=multi-hurtownie-integration&tab=macma"
            class="nav-tab <?php echo $active_tab === 'macma' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-image-filter"></span>
            <?php _e('Macma', 'multi-hurtownie-integration'); ?>
        </a>
    </h2>

    <div class="mhi-admin-content">
        <?php if ($active_tab === 'general'): ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Ustawienia ogólne', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('mhi_general_settings');
                        do_settings_sections('multi-hurtownie-integration-general');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-update"></span>
                        <?php _e('Ręczne uruchomienie', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('mhi_manual_run', 'mhi_manual_run_nonce'); ?>
                        <p><?php _e('Użyj przycisku poniżej, aby ręcznie uruchomić pobieranie danych ze wszystkich aktywnych hurtowni.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <input type="submit" name="mhi_manual_run" class="button button-primary"
                            value="<?php _e('Uruchom pobieranie', 'multi-hurtownie-integration'); ?>">
                    </form>
                </div>
            </div>

            <!-- Sekcja: Czyszczenie danych -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-trash"></span>
                        <?php _e('Czyszczenie danych', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="" id="mhi-cleanup-form">
                        <?php wp_nonce_field('mhi_cleanup_action', 'mhi_cleanup_nonce'); ?>

                        <div class="mhi-cleanup-warning"
                            style="background-color: #fcf0f0; border-left: 4px solid #dc3232; padding: 10px; margin-bottom: 15px;">
                            <h3 style="color: #dc3232; margin-top: 0;">
                                <?php _e('UWAGA! Operacja nieodwracalna!', 'multi-hurtownie-integration'); ?>
                            </h3>
                            <p><?php _e('Ta operacja <strong>całkowicie i nieodwracalnie usunie</strong> wybrane elementy z bazy danych. Zalecane jest wykonanie kopii zapasowej przed kontynuacją.', 'multi-hurtownie-integration'); ?>
                            </p>
                        </div>

                        <p><?php _e('Wybierz elementy, które chcesz usunąć:', 'multi-hurtownie-integration'); ?></p>

                        <div class="mhi-cleanup-options" style="margin-bottom: 15px;">
                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_products" value="1">
                                    <?php _e('Wszystkie produkty w WooCommerce', 'multi-hurtownie-integration'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_categories" value="1">
                                    <?php _e('Wszystkie kategorie produktów', 'multi-hurtownie-integration'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_attributes" value="1">
                                    <?php _e('Wszystkie atrybuty produktów', 'multi-hurtownie-integration'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_images" value="1">
                                    <?php _e('Wszystkie zdjęcia produktów', 'multi-hurtownie-integration'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_brands" value="1">
                                    <?php _e('Wszystkie marki produktów', 'multi-hurtownie-integration'); ?>
                                </label>
                            </p>

                            <!-- Nowa sekcja: Usuwanie mediów według użytkownika -->
                            <div class="mhi-cleanup-user-media"
                                style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                                <h4 style="margin-top: 0;">
                                    <?php _e('Usuwanie mediów produktów według użytkownika', 'multi-hurtownie-integration'); ?>
                                </h4>
                                <div class="notice notice-info inline" style="margin: 10px 0;">
                                    <p><strong><?php _e('BEZPIECZEŃSTWO:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php _e('Ta opcja usuwa TYLKO media związane z produktami WooCommerce (główne zdjęcia, galerie, załączniki produktów). Inne media użytkownika (np. zdjęcia w postach, stronach) pozostają nietknięte.', 'multi-hurtownie-integration'); ?>
                                    </p>
                                </div>
                                <p>
                                    <label>
                                        <input type="checkbox" name="mhi_cleanup_user_media" value="1"
                                            id="mhi-cleanup-user-media">
                                        <?php _e('Usuń media produktów dodane przez konkretnego użytkownika', 'multi-hurtownie-integration'); ?>
                                    </label>
                                </p>
                                <div id="mhi-user-selection" style="margin-left: 25px; display: none;">
                                    <p>
                                        <label
                                            for="mhi_cleanup_user_id"><?php _e('Wybierz użytkownika:', 'multi-hurtownie-integration'); ?></label>
                                        <select name="mhi_cleanup_user_id" id="mhi_cleanup_user_id">
                                            <option value="">
                                                <?php _e('-- Wybierz użytkownika --', 'multi-hurtownie-integration'); ?>
                                            </option>
                                            <?php
                                            // Pobierz użytkowników którzy dodali media
                                            $users_with_media = get_users([
                                                'meta_query' => [
                                                    [
                                                        'key' => 'wp_user_level',
                                                        'compare' => 'EXISTS'
                                                    ]
                                                ],
                                                'orderby' => 'display_name'
                                            ]);

                                            $marcin_user_shown = false;

                                            foreach ($users_with_media as $user) {
                                                // Sprawdź czy użytkownik ma jakieś media
                                                $media_count = count_user_posts($user->ID, 'attachment');
                                                if ($media_count > 0) {
                                                    $is_marcin = ($user->user_login === 'marcindymek');
                                                    if ($is_marcin) {
                                                        $marcin_user_shown = true;
                                                    }

                                                    echo '<option value="' . esc_attr($user->ID) . '"' . ($is_marcin ? ' style="background: #e8f5e8;"' : '') . '>';
                                                    echo esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ') - ' . $media_count . ' mediów';
                                                    if ($is_marcin) {
                                                        echo ' [FALLBACK]';
                                                    }
                                                    echo '</option>';
                                                }
                                            }

                                            // Dodaj opcję dla marcindymek jako fallback tylko jeśli nie został już pokazany
                                            if (!$marcin_user_shown) {
                                                $marcin_user = get_user_by('login', 'marcindymek');
                                                if ($marcin_user) {
                                                    $marcin_media_count = count_user_posts($marcin_user->ID, 'attachment');
                                                    echo '<option value="' . esc_attr($marcin_user->ID) . '" style="background: #e8f5e8;">';
                                                    echo esc_html($marcin_user->display_name) . ' (marcindymek) - ' . $marcin_media_count . ' mediów [FALLBACK]';
                                                    echo '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </p>
                                    <p class="description">
                                        <?php _e('Zostaną usunięte TYLKO media związane z produktami WooCommerce (główne zdjęcia, galerie, załączniki) dodane przez wybranego użytkownika. Inne media (posty, strony) pozostają bezpieczne.', 'multi-hurtownie-integration'); ?>
                                    </p>
                                    <ul class="description" style="margin-left: 20px;">
                                        <li><?php _e('✅ Główne zdjęcia produktów', 'multi-hurtownie-integration'); ?></li>
                                        <li><?php _e('✅ Zdjęcia w galeriach produktów', 'multi-hurtownie-integration'); ?>
                                        </li>
                                        <li><?php _e('✅ Załączniki przypisane do produktów', 'multi-hurtownie-integration'); ?>
                                        </li>
                                        <li><?php _e('✅ Media zaimportowane przez plugin (z meta _mhi_source_url)', 'multi-hurtownie-integration'); ?>
                                        </li>
                                        <li style="color: #d63638;">
                                            <?php _e('❌ Media w postach/stronach - pozostają nietknięte', 'multi-hurtownie-integration'); ?>
                                        </li>
                                    </ul>
                                    <p style="margin-top: 15px;">
                                        <button type="button" id="mhi-preview-user-media" class="button button-secondary">
                                            <?php _e('🔍 Podgląd mediów do usunięcia', 'multi-hurtownie-integration'); ?>
                                        </button>
                                    </p>
                                    <div id="mhi-media-preview"
                                        style="display: none; margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                                        <!-- Tutaj będzie wyświetlany podgląd -->
                                    </div>
                                </div>
                            </div>

                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_all" value="1" id="mhi-cleanup-all">
                                    <strong><?php _e('WSZYSTKO POWYŻEJ', 'multi-hurtownie-integration'); ?></strong>
                                </label>
                            </p>
                        </div>

                        <div class="mhi-cleanup-confirm">
                            <p>
                                <label>
                                    <input type="checkbox" name="mhi_cleanup_confirm" value="1" required>
                                    <?php _e('Rozumiem, że ta operacja jest nieodwracalna i wykonałem kopię zapasową.', 'multi-hurtownie-integration'); ?>
                                </label>
                            </p>
                        </div>

                        <p>
                            <input type="submit" name="mhi_cleanup_submit" class="button button-primary"
                                style="background-color: #dc3232; border-color: #dc3232;"
                                value="<?php _e('Wykonaj czyszczenie', 'multi-hurtownie-integration'); ?>"
                                onclick="return confirm('<?php echo esc_js(__('Czy na pewno chcesz usunąć wybrane elementy? Ta operacja jest NIEODWRACALNA!', 'multi-hurtownie-integration')); ?>');">
                        </p>
                    </form>

                    <script type="text/javascript">
                        jQuery(document).ready(function ($) {
                            // Obsługa zaznaczenia "WSZYSTKO POWYŻEJ"
                            $('#mhi-cleanup-all').on('change', function () {
                                if ($(this).is(':checked')) {
                                    $('input[name^="mhi_cleanup_"]:not([name="mhi_cleanup_all"]):not([name="mhi_cleanup_confirm"]):not([name="mhi_cleanup_user_media"])').prop('checked', true);
                                }
                            });

                            // Automatycznie odznacz "WSZYSTKO" jeśli któryś z pojedynczych checkboxów zostanie odznaczony
                            $('input[name^="mhi_cleanup_"]:not([name="mhi_cleanup_all"]):not([name="mhi_cleanup_confirm"]):not([name="mhi_cleanup_user_media"])').on('change', function () {
                                if (!$(this).is(':checked')) {
                                    $('#mhi-cleanup-all').prop('checked', false);
                                }

                                // Sprawdź czy wszystkie są zaznaczone i wtedy zaznacz "WSZYSTKO"
                                var allChecked = true;
                                $('input[name^="mhi_cleanup_"]:not([name="mhi_cleanup_all"]):not([name="mhi_cleanup_confirm"]):not([name="mhi_cleanup_user_media"])').each(function () {
                                    if (!$(this).is(':checked')) {
                                        allChecked = false;
                                        return false;
                                    }
                                });

                                if (allChecked) {
                                    $('#mhi-cleanup-all').prop('checked', true);
                                }
                            });

                            // Obsługa pokazywania/ukrywania sekcji wyboru użytkownika
                            $('#mhi-cleanup-user-media').on('change', function () {
                                if ($(this).is(':checked')) {
                                    $('#mhi-user-selection').show();
                                } else {
                                    $('#mhi-user-selection').hide();
                                    $('#mhi_cleanup_user_id').val('');
                                    $('#mhi-media-preview').hide();
                                }
                            });

                            // Obsługa podglądu mediów do usunięcia
                            $('#mhi-preview-user-media').on('click', function () {
                                var userId = $('#mhi_cleanup_user_id').val();
                                if (!userId) {
                                    alert('<?php echo esc_js(__('Najpierw wybierz użytkownika.', 'multi-hurtownie-integration')); ?>');
                                    return;
                                }

                                var $button = $(this);
                                var $preview = $('#mhi-media-preview');

                                $button.prop('disabled', true).text('<?php echo esc_js(__('Ładowanie...', 'multi-hurtownie-integration')); ?>');

                                // AJAX request do podglądu mediów
                                $.post(ajaxurl, {
                                    action: 'mhi_preview_user_media',
                                    user_id: userId,
                                    nonce: '<?php echo wp_create_nonce('mhi_preview_user_media'); ?>'
                                }, function (response) {
                                    if (response.success) {
                                        var data = response.data;
                                        var html = '<h4><?php echo esc_js(__('Podgląd mediów do usunięcia:', 'multi-hurtownie-integration')); ?></h4>';

                                        if (data.total_count === 0) {
                                            html += '<p style="color: #d63638;"><?php echo esc_js(__('Nie znaleziono mediów produktów dla tego użytkownika.', 'multi-hurtownie-integration')); ?></p>';
                                        } else {
                                            html += '<p><strong><?php echo esc_js(__('Łącznie do usunięcia:', 'multi-hurtownie-integration')); ?> ' + data.total_count + ' <?php echo esc_js(__('mediów', 'multi-hurtownie-integration')); ?></strong></p>';

                                            html += '<div style="margin: 10px 0;">';
                                            html += '<span style="margin-right: 15px;">📸 <?php echo esc_js(__('Główne zdjęcia:', 'multi-hurtownie-integration')); ?> ' + data.categories.featured_images + '</span>';
                                            html += '<span style="margin-right: 15px;">🖼️ <?php echo esc_js(__('Galerie:', 'multi-hurtownie-integration')); ?> ' + data.categories.gallery_images + '</span>';
                                            html += '<span style="margin-right: 15px;">📎 <?php echo esc_js(__('Załączniki:', 'multi-hurtownie-integration')); ?> ' + data.categories.attached_images + '</span>';
                                            html += '<span>🔗 <?php echo esc_js(__('Zaimportowane:', 'multi-hurtownie-integration')); ?> ' + data.categories.mhi_imported + '</span>';
                                            html += '</div>';

                                            if (data.media_details.length > 0) {
                                                html += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px;">';
                                                data.media_details.forEach(function (media) {
                                                    html += '<div style="display: flex; align-items: center; margin-bottom: 10px; padding: 5px; border-bottom: 1px solid #eee;">';
                                                    html += '<img src="' + media.url + '" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;" />';
                                                    html += '<div>';
                                                    html += '<strong>' + media.title + '</strong><br>';
                                                    html += '<small>ID: ' + media.id + ' | ' + media.file_size + ' | ' + media.mime_type + '</small><br>';
                                                    html += '<small style="color: #666;">';
                                                    if (media.is_featured) html += '📸 <?php echo esc_js(__('Główne', 'multi-hurtownie-integration')); ?> ';
                                                    if (media.is_gallery) html += '🖼️ <?php echo esc_js(__('Galeria', 'multi-hurtownie-integration')); ?> ';
                                                    if (media.is_attached) html += '📎 <?php echo esc_js(__('Załącznik', 'multi-hurtownie-integration')); ?> ';
                                                    if (media.is_mhi_imported) html += '🔗 <?php echo esc_js(__('Import', 'multi-hurtownie-integration')); ?> ';
                                                    html += '</small>';
                                                    html += '</div>';
                                                    html += '</div>';
                                                });
                                                html += '</div>';
                                            }
                                        }

                                        $preview.html(html).show();
                                    } else {
                                        alert('<?php echo esc_js(__('Błąd podczas ładowania podglądu:', 'multi-hurtownie-integration')); ?> ' + (response.data || '<?php echo esc_js(__('Nieznany błąd', 'multi-hurtownie-integration')); ?>'));
                                    }
                                }).fail(function () {
                                    alert('<?php echo esc_js(__('Błąd połączenia z serwerem.', 'multi-hurtownie-integration')); ?>');
                                }).always(function () {
                                    $button.prop('disabled', false).text('<?php echo esc_js(__('🔍 Podgląd mediów do usunięcia', 'multi-hurtownie-integration')); ?>');
                                });
                            });

                            // Walidacja formularza
                            $('#mhi-cleanup-form').on('submit', function (e) {
                                var atLeastOneChecked = false;
                                $('input[name^="mhi_cleanup_"]:not([name="mhi_cleanup_confirm"])').each(function () {
                                    if ($(this).is(':checked')) {
                                        atLeastOneChecked = true;
                                        return false;
                                    }
                                });

                                if (!atLeastOneChecked) {
                                    alert('<?php echo esc_js(__('Wybierz co najmniej jeden element do usunięcia.', 'multi-hurtownie-integration')); ?>');
                                    e.preventDefault();
                                    return false;
                                }

                                // Sprawdź czy wybrano usuwanie mediów użytkownika ale nie wybrano użytkownika
                                if ($('#mhi-cleanup-user-media').is(':checked') && $('#mhi_cleanup_user_id').val() === '') {
                                    alert('<?php echo esc_js(__('Wybierz użytkownika, którego media chcesz usunąć.', 'multi-hurtownie-integration')); ?>');
                                    e.preventDefault();
                                    return false;
                                }

                                if (!$('input[name="mhi_cleanup_confirm"]').is(':checked')) {
                                    alert('<?php echo esc_js(__('Musisz potwierdzić, że rozumiesz konsekwencje tej operacji.', 'multi-hurtownie-integration')); ?>');
                                    e.preventDefault();
                                    return false;
                                }

                                return true;
                            });
                        });
                    </script>
                </div>
            </div>
        <?php elseif ($active_tab === 'hurtownia-1'): ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-admin-customizer"></span>
                        <?php _e('Ustawienia Malfini', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <div class="notice notice-success">
                        <p><strong><?php _e('Informacja:', 'multi-hurtownie-integration'); ?></strong>
                            <?php _e('Malfini używa REST API v4. Dane dostępowe zostały już skonfigurowane:', 'multi-hurtownie-integration'); ?>
                        </p>
                        <ul>
                            <li><?php _e('• API URL: https://api.malfini.com/api/v4/', 'multi-hurtownie-integration'); ?>
                            </li>
                            <li><?php _e('• Login: dmurawski@promo-mix.pl', 'multi-hurtownie-integration'); ?></li>
                            <li><?php _e('• Hasło: mul4eQ', 'multi-hurtownie-integration'); ?></li>
                        </ul>
                        <p><strong><?php _e('Dostępne endpointy:', 'multi-hurtownie-integration'); ?></strong></p>
                        <ul>
                            <li><?php _e('• /product - Lista produktów', 'multi-hurtownie-integration'); ?></li>
                            <li><?php _e('• /product/availabilities - Dostępność produktów', 'multi-hurtownie-integration'); ?>
                            </li>
                            <li><?php _e('• /product/prices - Ceny produktów', 'multi-hurtownie-integration'); ?></li>
                        </ul>
                    </div>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('mhi_hurtownia_1_settings');
                        do_settings_sections('multi-hurtownie-integration-hurtownia-1');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <!-- Sekcja: Pobieranie danych Malfini -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-update"></span>
                        <?php _e('Operacje Malfini', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <!-- Pobieranie danych z serwera -->
                    <div class="mhi-action-section">
                        <h3><?php _e('Pobieranie danych z serwera', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Pobiera najnowsze pliki z danymi produktów z serwera Malfini.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_malfini_fetch_files', 'mhi_malfini_fetch_files_nonce'); ?>
                            <input type="submit" name="mhi_malfini_fetch_files" class="button button-primary"
                                value="<?php _e('Pobierz pliki', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Generowanie pliku XML -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Generowanie pliku XML', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Generuje plik XML z produktami Malfini do późniejszego importu.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_malfini_generate_xml', 'mhi_malfini_generate_xml_nonce'); ?>
                            <input type="submit" name="mhi_malfini_generate_xml" class="button button-primary"
                                value="<?php _e('Generuj plik XML', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Import produktów -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Import produktów', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Importuje produkty z wygenerowanego pliku XML do WooCommerce.', 'multi-hurtownie-integration'); ?>
                        </p>

                        <?php
                        // Sprawdź czy plik XML istnieje
                        $upload_dir = wp_upload_dir();
                        // Poprawna ścieżka dla Malfini
                        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/malfini/woocommerce_import_malfini.xml';
                        $xml_exists = file_exists($xml_file);
                        $xml_date = $xml_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)) : '';
                        ?>

                        <?php if ($xml_exists): ?>
                            <div class="notice notice-info inline">
                                <p><?php printf(__('Plik XML został wygenerowany: %s', 'multi-hurtownie-integration'), $xml_date); ?>
                                </p>

                                <?php
                                // Dodatkowe informacje o pliku
                                $file_size = size_format(filesize($xml_file));
                                $file_name = basename($xml_file);
                                ?>
                                <div class="mhi-file-info">
                                    <p><strong><?php _e('Nazwa pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_name); ?></p>
                                    <p><strong><?php _e('Rozmiar pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_size); ?></p>
                                    <p><strong><?php _e('Data wygenerowania:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($xml_date); ?></p>
                                </div>
                            </div>
                            <form method="post" action="">
                                <?php wp_nonce_field('mhi_malfini_import_products', 'mhi_malfini_import_products_nonce'); ?>
                                <div class="mhi-button-group">
                                    <input type="submit" name="mhi_malfini_import_products" class="button button-primary"
                                        value="<?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?>">

                                    <!-- Przycisk do przekierowania na import.php -->
                                    <a href="<?php echo esc_url(plugins_url('/import.php?supplier=malfini', dirname(dirname(__FILE__)))); ?>"
                                        class="button button-secondary">
                                        <?php _e('Importuj przez przeglądarkę', 'multi-hurtownie-integration'); ?>
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Brak wygenerowanego pliku XML. Najpierw wygeneruj plik XML.', 'multi-hurtownie-integration'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($active_tab === 'axpol'): ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-cart"></span>
                        <?php _e('Ustawienia AXPOL Trading', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('mhi_axpol_settings');
                        do_settings_sections('multi-hurtownie-integration-axpol');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <!-- Sekcja: Pobieranie danych Axpol -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-update"></span>
                        <?php _e('Operacje AXPOL', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <!-- Pobieranie danych z serwera -->
                    <div class="mhi-action-section">
                        <h3><?php _e('Pobieranie danych z serwera FTP', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Pobiera najnowsze pliki XML z produktami z serwera FTP AXPOL.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_axpol_fetch_files', 'mhi_axpol_fetch_files_nonce'); ?>
                            <input type="submit" name="mhi_axpol_fetch_files" class="button button-primary"
                                value="<?php _e('Pobierz pliki FTP', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Generowanie pliku XML -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Generowanie pliku XML', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Generuje plik XML z produktami AXPOL do późniejszego importu.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_axpol_generate_xml', 'mhi_axpol_generate_xml_nonce'); ?>
                            <input type="submit" name="mhi_axpol_generate_xml" class="button button-primary"
                                value="<?php _e('Generuj plik XML', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Import produktów -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Import produktów', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Importuje produkty z wygenerowanego pliku XML do WooCommerce.', 'multi-hurtownie-integration'); ?>
                        </p>

                        <?php
                        // Sprawdź czy plik XML istnieje
                        $upload_dir = wp_upload_dir();
                        // Poprawna ścieżka dla Axpol
                        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/axpol/woocommerce_import_axpol.xml';
                        $xml_exists = file_exists($xml_file);
                        $xml_date = $xml_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)) : '';
                        ?>

                        <?php if ($xml_exists): ?>
                            <div class="notice notice-info inline">
                                <p><?php printf(__('Plik XML został wygenerowany: %s', 'multi-hurtownie-integration'), $xml_date); ?>
                                </p>

                                <?php
                                // Dodatkowe informacje o pliku
                                $file_size = size_format(filesize($xml_file));
                                $file_name = basename($xml_file);
                                ?>
                                <div class="mhi-file-info">
                                    <p><strong><?php _e('Nazwa pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_name); ?></p>
                                    <p><strong><?php _e('Rozmiar pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_size); ?></p>
                                    <p><strong><?php _e('Data wygenerowania:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($xml_date); ?></p>
                                </div>
                            </div>
                            <form method="post" action="">
                                <?php wp_nonce_field('mhi_axpol_import_products', 'mhi_axpol_import_products_nonce'); ?>
                                <div class="mhi-button-group">
                                    <input type="submit" name="mhi_axpol_import_products" class="button button-primary"
                                        value="<?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?>">

                                    <!-- Przycisk do przekierowania na import.php -->
                                    <a href="<?php echo esc_url(plugins_url('/import.php?supplier=axpol', dirname(dirname(__FILE__)))); ?>"
                                        class="button button-secondary">
                                        <?php _e('Importuj przez przeglądarkę', 'multi-hurtownie-integration'); ?>
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Brak wygenerowanego pliku XML. Najpierw wygeneruj plik XML.', 'multi-hurtownie-integration'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($active_tab === 'par'): ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-products"></span>
                        <?php _e('Ustawienia PAR', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <div class="notice notice-warning">
                        <p><strong><?php _e('Ważne:', 'multi-hurtownie-integration'); ?></strong>
                            <?php _e('PAR wymaga dodania Twojego adresu IP do białej listy. Skontaktuj się z PAR aby:', 'multi-hurtownie-integration'); ?>
                        </p>
                        <ul>
                            <li><?php _e('• Otrzymać login i hasło do API', 'multi-hurtownie-integration'); ?></li>
                            <li><?php _e('• Dodać Twój adres IP do białej listy', 'multi-hurtownie-integration'); ?></li>
                        </ul>
                        <p><strong><?php _e('Twój aktualny adres IP:', 'multi-hurtownie-integration'); ?></strong>
                            <code><?php
                            if (class_exists('MHI_Utils')) {
                                echo esc_html(MHI_Utils::get_external_ip());
                            } else {
                                echo esc_html($_SERVER['SERVER_ADDR'] ?? 'Nieznany');
                            }
                            ?></code>
                        </p>
                    </div>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('mhi_par_settings');
                        do_settings_sections('multi-hurtownie-integration-par');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <!-- Sekcja: Pobieranie danych PAR -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-update"></span>
                        <?php _e('Operacje PAR', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <!-- Pobieranie danych z serwera -->
                    <div class="mhi-action-section">
                        <h3><?php _e('Pobieranie danych z serwera', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Pobiera najnowsze pliki z danymi produktów z serwera PAR.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_par_fetch_files', 'mhi_par_fetch_files_nonce'); ?>
                            <input type="submit" name="mhi_par_fetch_files" class="button button-primary"
                                value="<?php _e('Pobierz pliki', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Generowanie pliku XML -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Generowanie pliku XML', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Generuje plik XML z produktami PAR do późniejszego importu.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_par_generate_xml', 'mhi_par_generate_xml_nonce'); ?>
                            <input type="submit" name="mhi_par_generate_xml" class="button button-primary"
                                value="<?php _e('Generuj plik XML', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Import produktów -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Import produktów', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Importuje produkty z wygenerowanego pliku XML do WooCommerce.', 'multi-hurtownie-integration'); ?>
                        </p>

                        <?php
                        // Sprawdź czy plik XML istnieje
                        $upload_dir = wp_upload_dir();
                        // Poprawna ścieżka dla PAR
                        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/par/woocommerce_import_par.xml';
                        $xml_exists = file_exists($xml_file);
                        $xml_date = $xml_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)) : '';
                        ?>

                        <?php if ($xml_exists): ?>
                            <div class="notice notice-info inline">
                                <p><?php printf(__('Plik XML został wygenerowany: %s', 'multi-hurtownie-integration'), $xml_date); ?>
                                </p>

                                <?php
                                // Dodatkowe informacje o pliku
                                $file_size = size_format(filesize($xml_file));
                                $file_name = basename($xml_file);
                                ?>
                                <div class="mhi-file-info">
                                    <p><strong><?php _e('Nazwa pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_name); ?></p>
                                    <p><strong><?php _e('Rozmiar pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_size); ?></p>
                                    <p><strong><?php _e('Data wygenerowania:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($xml_date); ?></p>
                                </div>
                            </div>
                            <form method="post" action="">
                                <?php wp_nonce_field('mhi_par_import_products', 'mhi_par_import_products_nonce'); ?>
                                <div class="mhi-button-group">
                                    <input type="submit" name="mhi_par_import_products" class="button button-primary"
                                        value="<?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?>">

                                    <!-- Przycisk do przekierowania na import.php -->
                                    <a href="<?php echo esc_url(plugins_url('/import.php?supplier=par', dirname(dirname(__FILE__)))); ?>"
                                        class="button button-secondary">
                                        <?php _e('Importuj przez przeglądarkę', 'multi-hurtownie-integration'); ?>
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Brak wygenerowanego pliku XML. Najpierw wygeneruj plik XML.', 'multi-hurtownie-integration'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($active_tab === 'inspirion'): ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-art"></span>
                        <?php _e('Ustawienia Inspirion', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('mhi_inspirion_settings');
                        do_settings_sections('multi-hurtownie-integration-inspirion');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <!-- Sekcja: Pobieranie danych Inspirion -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-update"></span>
                        <?php _e('Operacje Inspirion', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <!-- Pobieranie danych z serwera -->
                    <div class="mhi-action-section">
                        <h3><?php _e('Pobieranie danych z serwera', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Pobiera najnowsze pliki z danymi produktów z serwera Inspirion.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_inspirion_fetch_files', 'mhi_inspirion_fetch_files_nonce'); ?>
                            <input type="submit" name="mhi_inspirion_fetch_files" class="button button-primary"
                                value="<?php _e('Pobierz pliki', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Generowanie pliku XML -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Generowanie pliku XML', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Generuje plik XML z produktami Inspirion do późniejszego importu.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_inspirion_generate_xml', 'mhi_inspirion_generate_xml_nonce'); ?>
                            <input type="submit" name="mhi_inspirion_generate_xml" class="button button-primary"
                                value="<?php _e('Generuj plik XML', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Import produktów -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Import produktów', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Importuje produkty z wygenerowanego pliku XML do WooCommerce.', 'multi-hurtownie-integration'); ?>
                        </p>

                        <?php
                        // Sprawdź czy plik XML istnieje
                        $upload_dir = wp_upload_dir();
                        // Poprawna ścieżka dla Inspirion
                        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/inspirion/woocommerce_import_inspirion.xml';
                        $xml_exists = file_exists($xml_file);
                        $xml_date = $xml_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)) : '';
                        ?>

                        <?php if ($xml_exists): ?>
                            <div class="notice notice-info inline">
                                <p><?php printf(__('Plik XML został wygenerowany: %s', 'multi-hurtownie-integration'), $xml_date); ?>
                                </p>

                                <?php
                                // Dodatkowe informacje o pliku
                                $file_size = size_format(filesize($xml_file));
                                $file_name = basename($xml_file);
                                ?>
                                <div class="mhi-file-info">
                                    <p><strong><?php _e('Nazwa pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_name); ?></p>
                                    <p><strong><?php _e('Rozmiar pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_size); ?></p>
                                    <p><strong><?php _e('Data wygenerowania:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($xml_date); ?></p>
                                </div>
                            </div>
                            <form method="post" action="">
                                <?php wp_nonce_field('mhi_inspirion_import_products', 'mhi_inspirion_import_products_nonce'); ?>
                                <div class="mhi-button-group">
                                    <input type="submit" name="mhi_inspirion_import_products" class="button button-primary"
                                        value="<?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?>">

                                    <!-- Przycisk do przekierowania na import.php -->
                                    <a href="<?php echo esc_url(plugins_url('/import.php?supplier=inspirion', dirname(dirname(__FILE__)))); ?>"
                                        class="button button-secondary">
                                        <?php _e('Importuj przez przeglądarkę', 'multi-hurtownie-integration'); ?>
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Brak wygenerowanego pliku XML. Najpierw wygeneruj plik XML.', 'multi-hurtownie-integration'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($active_tab === 'macma'): ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-image-filter"></span>
                        <?php _e('Ustawienia Macma', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('mhi_hurtownia_3_settings');
                        do_settings_sections('multi-hurtownie-integration-macma');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <!-- Sekcja: Pobieranie danych Macma -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2><span class="dashicons dashicons-update"></span>
                        <?php _e('Operacje Macma', 'multi-hurtownie-integration'); ?></h2>
                </div>
                <div class="inside">
                    <!-- Pobieranie danych z serwera -->
                    <div class="mhi-action-section">
                        <h3><?php _e('Pobieranie danych z serwera FTP', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Pobiera najnowsze pliki z danymi produktów z serwera FTP Macma.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_macma_fetch_files', 'mhi_macma_fetch_files_nonce'); ?>
                            <input type="submit" name="mhi_macma_fetch_files" class="button button-primary"
                                value="<?php _e('Pobierz pliki FTP', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Generowanie pliku XML -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Generowanie pliku XML', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Generuje plik XML z produktami Macma do późniejszego importu.', 'multi-hurtownie-integration'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('mhi_macma_generate_xml', 'mhi_macma_generate_xml_nonce'); ?>
                            <input type="submit" name="mhi_macma_generate_xml" class="button button-primary"
                                value="<?php _e('Generuj plik XML', 'multi-hurtownie-integration'); ?>">
                        </form>
                    </div>

                    <!-- Import produktów -->
                    <div class="mhi-action-section"
                        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                        <h3><?php _e('Import produktów', 'multi-hurtownie-integration'); ?></h3>
                        <p><?php _e('Importuje produkty z wygenerowanego pliku XML do WooCommerce.', 'multi-hurtownie-integration'); ?>
                        </p>

                        <?php
                        // Sprawdź czy plik XML istnieje
                        $upload_dir = wp_upload_dir();
                        // Poprawna ścieżka dla Macma
                        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/macma/woocommerce_import_macma.xml';
                        $xml_exists = file_exists($xml_file);
                        $xml_date = $xml_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($xml_file)) : '';
                        ?>

                        <?php if ($xml_exists): ?>
                            <div class="notice notice-info inline">
                                <p><?php printf(__('Plik XML został wygenerowany: %s', 'multi-hurtownie-integration'), $xml_date); ?>
                                </p>

                                <?php
                                // Dodatkowe informacje o pliku
                                $file_size = size_format(filesize($xml_file));
                                $file_name = basename($xml_file);
                                ?>
                                <div class="mhi-file-info">
                                    <p><strong><?php _e('Nazwa pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_name); ?></p>
                                    <p><strong><?php _e('Rozmiar pliku:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($file_size); ?></p>
                                    <p><strong><?php _e('Data wygenerowania:', 'multi-hurtownie-integration'); ?></strong>
                                        <?php echo esc_html($xml_date); ?></p>
                                </div>
                            </div>
                            <form method="post" action="">
                                <?php wp_nonce_field('mhi_macma_import_products', 'mhi_macma_import_products_nonce'); ?>
                                <div class="mhi-button-group">
                                    <input type="submit" name="mhi_macma_import_products" class="button button-primary"
                                        value="<?php _e('Importuj produkty', 'multi-hurtownie-integration'); ?>">

                                    <!-- Przycisk do przekierowania na import.php -->
                                    <a href="<?php echo esc_url(plugins_url('/import.php?supplier=macma', dirname(dirname(__FILE__)))); ?>"
                                        class="button button-secondary">
                                        <?php _e('Importuj przez przeglądarkę', 'multi-hurtownie-integration'); ?>
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Brak wygenerowanego pliku XML. Najpierw wygeneruj plik XML.', 'multi-hurtownie-integration'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
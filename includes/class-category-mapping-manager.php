<?php
/**
 * Manager Mapowania Kategorii
 * 
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

class MHI_Category_Mapping_Manager
{

    /**
     * Pobiera dane kategorii z różnych hurtowni
     */
    public static function get_categories_data()
    {
        global $wpdb;

        $data = [
            'all_categories' => [],
            'categories_with_products' => [],
            'empty_categories' => []
        ];

        // Pobierz wszystkie kategorie
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        if (is_wp_error($categories)) {
            return $data;
        }

        foreach ($categories as $category) {
            $category_data = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count,
                'supplier' => self::detect_supplier($category)
            ];

            $data['all_categories'][] = $category_data;

            if ($category->count > 0) {
                $data['categories_with_products'][] = $category_data;
            } else {
                $data['empty_categories'][] = $category_data;
            }
        }

        return $data;
    }

    /**
     * Wykrywa hurtownię na podstawie metadanych kategorii
     */
    private static function detect_supplier($category)
    {
        $supplier = get_term_meta($category->term_id, '_mhi_supplier', true);

        if ($supplier) {
            return $supplier;
        }

        // Sprawdź produkty w kategorii aby określić hurtownię
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 1,
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category->term_id
                ]
            ]
        ]);

        if (!empty($products)) {
            $product_supplier = get_post_meta($products[0]->ID, '_mhi_supplier', true);
            if ($product_supplier) {
                // Zapisz hurtownię w metadanych kategorii
                update_term_meta($category->term_id, '_mhi_supplier', $product_supplier);
                return $product_supplier;
            }
        }

        return 'unknown';
    }

    /**
     * Pobiera zapisane mapowania
     */
    public static function get_mapping_data()
    {
        return get_option('mhi_category_mapping', []);
    }

    /**
     * Zapisuje mapowanie kategorii
     */
    public static function save_mapping()
    {
        if (!isset($_POST['selected_categories']) || !isset($_POST['target_category'])) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_mapping_error',
                __('Błąd: Brak danych do zapisania.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        $selected_categories = array_map('intval', $_POST['selected_categories']);
        $target_categories = array_map('intval', $_POST['target_category']);

        $mapping = [];
        foreach ($selected_categories as $category_id) {
            if (isset($target_categories[$category_id]) && $target_categories[$category_id] > 0) {
                $mapping[$category_id] = $target_categories[$category_id];
            }
        }

        update_option('mhi_category_mapping', $mapping);

        add_settings_error(
            'mhi_category_mapping',
            'mhi_mapping_success',
            sprintf(__('Zapisano %d mapowań kategorii.', 'multi-hurtownie-integration'), count($mapping)),
            'success'
        );
    }

    /**
     * Aplikuje mapowanie - przenosi produkty między kategoriami
     */
    public static function apply_mapping()
    {
        $mapping = self::get_mapping_data();

        if (empty($mapping)) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_mapping_error',
                __('Brak mapowań do zastosowania.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        $moved_products = 0;
        $errors = [];

        foreach ($mapping as $source_category_id => $target_category_id) {
            // Sprawdź czy kategorie istnieją
            $source_category = get_term($source_category_id, 'product_cat');
            $target_category = get_term($target_category_id, 'product_cat');

            if (!$source_category || !$target_category) {
                $errors[] = sprintf(__('Kategoria ID %d lub %d nie istnieje.', 'multi-hurtownie-integration'), $source_category_id, $target_category_id);
                continue;
            }

            // Pobierz produkty z kategorii źródłowej
            $products = get_posts([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $source_category_id
                    ]
                ]
            ]);

            foreach ($products as $product) {
                // Przenieś produkt do nowej kategorii
                $result = wp_set_object_terms($product->ID, $target_category_id, 'product_cat', true);

                if (!is_wp_error($result)) {
                    $moved_products++;

                    // Usuń produkt ze starej kategorii
                    wp_remove_object_terms($product->ID, $source_category_id, 'product_cat');

                    // Zaktualizuj hurtownię produktu jeśli jest inna
                    $target_supplier = get_term_meta($target_category_id, '_mhi_supplier', true);
                    if ($target_supplier) {
                        update_post_meta($product->ID, '_mhi_supplier', $target_supplier);
                    }
                } else {
                    $errors[] = sprintf(__('Błąd przenoszenia produktu %d: %s', 'multi-hurtownie-integration'), $product->ID, $result->get_error_message());
                }
            }
        }

        // Wyczyść mapowanie po zastosowaniu
        delete_option('mhi_category_mapping');

        // Wyświetl wyniki
        if ($moved_products > 0) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_mapping_success',
                sprintf(__('Pomyślnie przeniesiono %d produktów między kategoriami.', 'multi-hurtownie-integration'), $moved_products),
                'success'
            );
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error(
                    'mhi_category_mapping',
                    'mhi_mapping_error',
                    $error,
                    'error'
                );
            }
        }
    }

    /**
     * Tworzy kopię zapasową kategorii
     */
    public static function create_backup()
    {
        $backup_data = [
            'categories' => self::get_categories_data(),
            'mapping' => self::get_mapping_data(),
            'timestamp' => current_time('timestamp'),
            'date' => current_time('Y-m-d H:i:s')
        ];

        $backup_dir = wp_upload_dir()['basedir'] . '/mhi-backups/';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        $backup_file = $backup_dir . 'category-mapping-backup-' . date('Y-m-d-H-i-s') . '.json';
        $backup_content = json_encode($backup_data, JSON_PRETTY_PRINT);

        if (file_put_contents($backup_file, $backup_content)) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_backup_success',
                sprintf(__('Kopia zapasowa została utworzona: %s', 'multi-hurtownie-integration'), basename($backup_file)),
                'success'
            );
        } else {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_backup_error',
                __('Błąd podczas tworzenia kopii zapasowej.', 'multi-hurtownie-integration'),
                'error'
            );
        }
    }

    /**
     * Przywraca kopię zapasową
     */
    public static function restore_backup()
    {
        if (!isset($_POST['backup_to_restore']) || empty($_POST['backup_to_restore'])) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_restore_error',
                __('Nie wybrano kopii zapasowej do przywrócenia.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        $backup_file = sanitize_text_field($_POST['backup_to_restore']);
        $backup_path = wp_upload_dir()['basedir'] . '/mhi-backups/' . $backup_file;

        if (!file_exists($backup_path)) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_restore_error',
                __('Plik kopii zapasowej nie istnieje.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        $backup_content = file_get_contents($backup_path);
        $backup_data = json_decode($backup_content, true);

        if (!$backup_data) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_restore_error',
                __('Błąd podczas odczytywania kopii zapasowej.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        // Przywróć mapowanie
        if (isset($backup_data['mapping'])) {
            update_option('mhi_category_mapping', $backup_data['mapping']);
        }

        add_settings_error(
            'mhi_category_mapping',
            'mhi_restore_success',
            sprintf(__('Kopia zapasowa została przywrócona: %s', 'multi-hurtownie-integration'), $backup_file),
            'success'
        );
    }

    /**
     * Pobiera listę dostępnych kopii zapasowych
     */
    public static function get_backups()
    {
        $backup_dir = wp_upload_dir()['basedir'] . '/mhi-backups/';
        $backups = [];

        if (!file_exists($backup_dir)) {
            return $backups;
        }

        $files = glob($backup_dir . 'category-mapping-backup-*.json');

        foreach ($files as $file) {
            $filename = basename($file);
            $filetime = filemtime($file);

            $backups[] = [
                'file' => $filename,
                'name' => str_replace(['category-mapping-backup-', '.json'], '', $filename),
                'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $filetime),
                'size' => size_format(filesize($file)),
                'timestamp' => $filetime
            ];
        }

        // Sortuj po dacie (najnowsze pierwsze)
        usort($backups, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    /**
     * Usuwa puste kategorie
     */
    public static function delete_empty_categories()
    {
        $categories_data = self::get_categories_data();
        $deleted_count = 0;
        $errors = [];

        foreach ($categories_data['empty_categories'] as $category) {
            $term = get_term($category['id'], 'product_cat');

            if ($term && $term->count == 0) {
                $result = wp_delete_term($category['id'], 'product_cat');

                if (is_wp_error($result)) {
                    $errors[] = sprintf(__('Błąd usuwania kategorii %s: %s', 'multi-hurtownie-integration'), $category['name'], $result->get_error_message());
                } else {
                    $deleted_count++;
                }
            }
        }

        if ($deleted_count > 0) {
            add_settings_error(
                'mhi_category_mapping',
                'mhi_delete_success',
                sprintf(__('Usunięto %d pustych kategorii.', 'multi-hurtownie-integration'), $deleted_count),
                'success'
            );
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error(
                    'mhi_category_mapping',
                    'mhi_delete_error',
                    $error,
                    'error'
                );
            }
        }
    }
}
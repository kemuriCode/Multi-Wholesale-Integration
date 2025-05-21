<?php
/**
 * Klasa obsługująca czyszczenie danych
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Cleanup
 * 
 * Obsługuje usuwanie produktów, kategorii, atrybutów i zdjęć zaimportowanych przez plugin.
 */
class MHI_Cleanup
{

    /**
     * Inicjalizuje klasę
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'process_cleanup_request'));
    }

    /**
     * Przetwarza żądanie czyszczenia danych
     */
    public static function process_cleanup_request()
    {
        // Sprawdź czy formularz został wysłany
        if (!isset($_POST['mhi_cleanup_submit'])) {
            return;
        }

        // Sprawdź nonce
        if (!isset($_POST['mhi_cleanup_nonce']) || !wp_verify_nonce($_POST['mhi_cleanup_nonce'], 'mhi_cleanup_action')) {
            wp_die(__('Błąd bezpieczeństwa. Spróbuj ponownie.', 'multi-hurtownie-integration'));
        }

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz uprawnień do wykonania tej operacji.', 'multi-hurtownie-integration'));
        }

        // Sprawdź czy potwierdzono operację
        if (!isset($_POST['mhi_cleanup_confirm']) || $_POST['mhi_cleanup_confirm'] != '1') {
            add_settings_error(
                'mhi_cleanup',
                'mhi_cleanup_error',
                __('Musisz potwierdzić, że rozumiesz konsekwencje tej operacji.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        // Sprawdź czy wybrano cokolwiek do usunięcia
        $cleanup_all = isset($_POST['mhi_cleanup_all']) && $_POST['mhi_cleanup_all'] == '1';
        $cleanup_products = $cleanup_all || (isset($_POST['mhi_cleanup_products']) && $_POST['mhi_cleanup_products'] == '1');
        $cleanup_categories = $cleanup_all || (isset($_POST['mhi_cleanup_categories']) && $_POST['mhi_cleanup_categories'] == '1');
        $cleanup_attributes = $cleanup_all || (isset($_POST['mhi_cleanup_attributes']) && $_POST['mhi_cleanup_attributes'] == '1');
        $cleanup_images = $cleanup_all || (isset($_POST['mhi_cleanup_images']) && $_POST['mhi_cleanup_images'] == '1');
        $cleanup_brands = $cleanup_all || (isset($_POST['mhi_cleanup_brands']) && $_POST['mhi_cleanup_brands'] == '1');

        if (!$cleanup_products && !$cleanup_categories && !$cleanup_attributes && !$cleanup_images && !$cleanup_brands) {
            add_settings_error(
                'mhi_cleanup',
                'mhi_cleanup_error',
                __('Nie wybrano żadnych elementów do usunięcia.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        // Zacznij czyszczenie
        $results = array();

        // Zwiększ limity wykonania skryptu
        set_time_limit(600); // 10 minut
        ini_set('memory_limit', '1024M'); // 1GB pamięci

        // Usuń produkty
        if ($cleanup_products) {
            $products_count = self::cleanup_products();
            $results[] = sprintf(__('Usunięto %d produktów.', 'multi-hurtownie-integration'), $products_count);
        }

        // Usuń kategorie
        if ($cleanup_categories) {
            $categories_count = self::cleanup_categories();
            $results[] = sprintf(__('Usunięto %d kategorii produktów.', 'multi-hurtownie-integration'), $categories_count);
        }

        // Usuń atrybuty
        if ($cleanup_attributes) {
            $attributes_count = self::cleanup_attributes();
            $results[] = sprintf(__('Usunięto %d atrybutów produktów.', 'multi-hurtownie-integration'), $attributes_count);
        }

        // Usuń zdjęcia
        if ($cleanup_images) {
            $images_count = self::cleanup_images();
            $results[] = sprintf(__('Usunięto %d zdjęć produktów.', 'multi-hurtownie-integration'), $images_count);
        }

        // Usuń marki
        if ($cleanup_brands) {
            $brands_count = self::cleanup_brands();
            $results[] = sprintf(__('Usunięto %d marek produktów.', 'multi-hurtownie-integration'), $brands_count);
        }

        // Wyczyść cache
        wp_cache_flush();

        // Pokaż komunikat o sukcesie
        add_settings_error(
            'mhi_cleanup',
            'mhi_cleanup_success',
            __('Czyszczenie danych zakończone pomyślnie:', 'multi-hurtownie-integration') . '<br>• ' . implode('<br>• ', $results),
            'success'
        );
    }

    /**
     * Czyści produkty zaimportowane przez plugin
     * 
     * @return int Liczba usuniętych produktów
     */
    private static function cleanup_products()
    {
        global $wpdb;
        $count = 0;

        // Ustawiam wysokie limity wykonania
        set_time_limit(0); // Bez limitu czasu
        ini_set('memory_limit', '2048M'); // 2GB pamięci

        // Pobierz wszystkie produkty naraz
        $product_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type IN ('product', 'product_variation')
        ");

        if (!empty($product_ids)) {
            // Usuń wszystkie produkty
            foreach ($product_ids as $product_id) {
                wp_delete_post($product_id, true);
                $count++;

                // Co 50 produktów, zwolnij pamięć
                if ($count % 50 === 0) {
                    wp_cache_flush();
                    if (function_exists('wc_delete_product_transients')) {
                        wc_delete_product_transients();
                    }
                }
            }

            // Zwolnij pamięć na końcu
            wp_cache_flush();
        }

        return $count;
    }

    /**
     * Czyści kategorie produktów stworzone przez plugin
     * 
     * @return int Liczba usuniętych kategorii
     */
    private static function cleanup_categories()
    {
        $count = 0;

        // Zwiększ limity wykonania
        set_time_limit(0); // Bez limitu czasu
        ini_set('memory_limit', '2048M'); // 2GB pamięci

        // Znajdź wszystkie kategorie produktów
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'fields' => 'ids',
            'number' => 0, // wszystkie
        ));

        // Usuń znalezione kategorie
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term($term_id, 'product_cat');
                $count++;
            }

            // Zwolnij pamięć
            wp_cache_flush();
        }

        return $count;
    }

    /**
     * Czyści atrybuty produktów stworzone przez plugin
     * 
     * @return int Liczba usuniętych atrybutów
     */
    private static function cleanup_attributes()
    {
        global $wpdb;
        $count = 0;

        // Zwiększ limity wykonania
        set_time_limit(0); // Bez limitu czasu
        ini_set('memory_limit', '2048M'); // 2GB pamięci

        // Pobierz wszystkie atrybuty produktów
        $attributes = wc_get_attribute_taxonomies();

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);

                // Usuń wszystkie terminy dla danej taksonomii
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                ));

                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        wp_delete_term($term->term_id, $taxonomy);
                    }
                }

                // Usuń taksonomię
                $wpdb->delete(
                    $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                    array('attribute_id' => $attribute->attribute_id),
                    array('%d')
                );

                // Usuń taksonomię z cache
                delete_transient('wc_attribute_taxonomies');

                $count++;
            }
        }

        return $count;
    }

    /**
     * Czyści zdjęcia produktów i folder hurtownie
     * 
     * @return int Liczba usuniętych zdjęć
     */
    private static function cleanup_images()
    {
        global $wpdb;
        $count = 0;

        // Zwiększ limity wykonania
        set_time_limit(0); // Bez limitu czasu
        ini_set('memory_limit', '2048M'); // 2GB pamięci

        // Pobierz wszystkie załączniki naraz
        $attachment_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
        ");

        if (!empty($attachment_ids)) {
            // Usuń wszystkie załączniki
            foreach ($attachment_ids as $attachment_id) {
                // Usuń załącznik, true powoduje również usunięcie pliku
                wp_delete_attachment($attachment_id, true);
                $count++;

                // Co 100 załączników, zwolnij pamięć
                if ($count % 100 === 0) {
                    wp_cache_flush();
                }
            }
        }

        // Usuń fizycznie folder hurtownie
        $upload_dir = wp_upload_dir();
        $hurtownie_dir = trailingslashit($upload_dir['basedir']) . 'hurtownie';

        if (file_exists($hurtownie_dir) && is_dir($hurtownie_dir)) {
            self::delete_directory_recursively($hurtownie_dir);

            // Odtwórz pusty folder hurtownie
            if (!file_exists($hurtownie_dir)) {
                wp_mkdir_p($hurtownie_dir);
            }
        }

        // Dodatkowo usuń wszystkie wpisy metadanych obrazków, które mogły pozostać
        $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key = '_wp_attachment_metadata'
            OR meta_key = '_wp_attached_file'
            OR meta_key = '_thumbnail_id'
            OR meta_key = '_product_image_gallery'
        ");

        return $count;
    }

    /**
     * Rekurencyjnie usuwa katalog i jego zawartość
     * 
     * @param string $dir Ścieżka do katalogu
     * @return bool True jeśli usunięto pomyślnie
     */
    private static function delete_directory_recursively($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::delete_directory_recursively($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * Czyści marki produktów
     * 
     * @return int Liczba usuniętych marek
     */
    private static function cleanup_brands()
    {
        $count = 0;

        // Zwiększ limity wykonania
        set_time_limit(0); // Bez limitu czasu
        ini_set('memory_limit', '2048M'); // 2GB pamięci

        // Sprawdź czy taksonomia product_brand istnieje
        if (!taxonomy_exists('product_brand')) {
            return 0;
        }

        // Znajdź wszystkie marki produktów
        $terms = get_terms(array(
            'taxonomy' => 'product_brand',
            'hide_empty' => false,
            'fields' => 'ids',
            'number' => 0, // wszystkie
        ));

        // Usuń znalezione marki
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term($term_id, 'product_brand');
                $count++;
            }

            // Zwolnij pamięć
            wp_cache_flush();
        }

        return $count;
    }
}

// Inicjalizacja klasy
MHI_Cleanup::init();
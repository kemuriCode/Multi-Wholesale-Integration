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
        add_action('wp_ajax_mhi_preview_user_media', array(__CLASS__, 'ajax_preview_user_media'));
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
        $cleanup_user_media = isset($_POST['mhi_cleanup_user_media']) && $_POST['mhi_cleanup_user_media'] == '1';
        $cleanup_user_id = isset($_POST['mhi_cleanup_user_id']) ? intval($_POST['mhi_cleanup_user_id']) : 0;

        if (!$cleanup_products && !$cleanup_categories && !$cleanup_attributes && !$cleanup_images && !$cleanup_brands && !$cleanup_user_media) {
            add_settings_error(
                'mhi_cleanup',
                'mhi_cleanup_error',
                __('Nie wybrano żadnych elementów do usunięcia.', 'multi-hurtownie-integration'),
                'error'
            );
            return;
        }

        // Sprawdź czy wybrano usuwanie mediów użytkownika ale nie podano ID użytkownika
        if ($cleanup_user_media && $cleanup_user_id <= 0) {
            // Spróbuj znaleźć marcindymek jako fallback
            $marcin_user = get_user_by('login', 'marcindymek');
            if ($marcin_user) {
                $cleanup_user_id = $marcin_user->ID;
                add_settings_error(
                    'mhi_cleanup',
                    'mhi_cleanup_warning',
                    __('Nie wybrano użytkownika - używam marcindymek jako fallback.', 'multi-hurtownie-integration'),
                    'warning'
                );
            } else {
                add_settings_error(
                    'mhi_cleanup',
                    'mhi_cleanup_error',
                    __('Nie wybrano użytkownika do usunięcia mediów i nie znaleziono fallback użytkownika marcindymek.', 'multi-hurtownie-integration'),
                    'error'
                );
                return;
            }
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

        // Usuń media użytkownika
        if ($cleanup_user_media && $cleanup_user_id > 0) {
            $user_media_count = self::cleanup_user_media($cleanup_user_id);
            $user_info = get_userdata($cleanup_user_id);
            $username = $user_info ? $user_info->display_name . ' (' . $user_info->user_login . ')' : 'ID: ' . $cleanup_user_id;
            $results[] = sprintf(__('Usunięto %d mediów użytkownika %s.', 'multi-hurtownie-integration'), $user_media_count, $username);
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
     * Czyści zdjęcia produktów i folder wholesale
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
        $hurtownie_dir = trailingslashit($upload_dir['basedir']) . 'wholesale';

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

    /**
     * Czyści media użytkownika TYLKO ZWIĄZANE Z PRODUKTAMI WOOCOMMERCE
     * BEZPIECZNA WERSJA - nie usuwa innych mediów użytkownika
     * 
     * @param int $user_id ID użytkownika
     * @return int Liczba usuniętych mediów
     */
    private static function cleanup_user_media($user_id)
    {
        global $wpdb;
        $count = 0;

        // Zwiększ limity wykonania
        set_time_limit(0); // Bez limitu czasu
        ini_set('memory_limit', '2048M'); // 2GB pamięci

        // BEZPIECZEŃSTWO: Pobierz tylko media związane z produktami WooCommerce
        // 1. Media które są głównymi obrazami produktów
        $featured_image_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->posts} a ON pm.meta_value = a.ID
            WHERE pm.meta_key = '_thumbnail_id'
            AND p.post_type IN ('product', 'product_variation')
            AND a.post_author = %d
            AND a.post_type = 'attachment'
        ", $user_id));

        // 2. Media które są w galeriach produktów
        $gallery_image_ids = [];
        $gallery_metas = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_product_image_gallery'
            AND p.post_type IN ('product', 'product_variation')
            AND pm.meta_value != ''
        "));

        foreach ($gallery_metas as $meta) {
            if (!empty($meta->meta_value)) {
                $gallery_ids = explode(',', $meta->meta_value);
                foreach ($gallery_ids as $gallery_id) {
                    $gallery_id = intval(trim($gallery_id));
                    if ($gallery_id > 0) {
                        // Sprawdź czy to media tego użytkownika
                        $author_check = $wpdb->get_var($wpdb->prepare("
                            SELECT post_author FROM {$wpdb->posts} 
                            WHERE ID = %d AND post_type = 'attachment' AND post_author = %d
                        ", $gallery_id, $user_id));

                        if ($author_check) {
                            $gallery_image_ids[] = $gallery_id;
                        }
                    }
                }
            }
        }

        // 3. Media które są przypisane do produktów (post_parent)
        $attached_image_ids = $wpdb->get_col($wpdb->prepare("
            SELECT a.ID 
            FROM {$wpdb->posts} a
            INNER JOIN {$wpdb->posts} p ON a.post_parent = p.ID
            WHERE a.post_type = 'attachment'
            AND a.post_author = %d
            AND p.post_type IN ('product', 'product_variation')
        ", $user_id));

        // 4. Media z meta '_mhi_imported' (dodane przez nasz plugin)
        $mhi_imported_ids = $wpdb->get_col($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_author = %d
            AND pm.meta_key = '_mhi_source_url'
        ", $user_id));

        // Połącz wszystkie ID i usuń duplikaty
        $all_product_media_ids = array_unique(array_merge(
            $featured_image_ids ?: [],
            $gallery_image_ids ?: [],
            $attached_image_ids ?: [],
            $mhi_imported_ids ?: []
        ));

        // Filtruj tylko obrazy (dla bezpieczeństwa)
        $safe_media_ids = [];
        foreach ($all_product_media_ids as $media_id) {
            $mime_type = get_post_mime_type($media_id);
            if ($mime_type && strpos($mime_type, 'image/') === 0) {
                $safe_media_ids[] = $media_id;
            }
        }

        if (!empty($safe_media_ids)) {
            // Usuń tylko bezpieczne media związane z produktami
            foreach ($safe_media_ids as $media_id) {
                // Dodatkowa weryfikacja - sprawdź czy to rzeczywiście media tego użytkownika
                $media_author = get_post_field('post_author', $media_id);
                if ($media_author == $user_id) {
                    // Usuń załącznik, true powoduje również usunięcie pliku
                    $deleted = wp_delete_attachment($media_id, true);
                    if ($deleted) {
                        $count++;
                    }

                    // Co 50 załączników, zwolnij pamięć
                    if ($count % 50 === 0) {
                        wp_cache_flush();
                    }
                }
            }
        }

        // Zwolnij pamięć na końcu
        wp_cache_flush();

        return $count;
    }

    /**
     * Podgląd mediów użytkownika które zostaną usunięte (bez usuwania)
     * Funkcja pomocnicza do sprawdzenia co zostanie usunięte
     * 
     * @param int $user_id ID użytkownika
     * @return array Informacje o mediach do usunięcia
     */
    public static function preview_user_media_cleanup($user_id)
    {
        global $wpdb;

        // Użyj tej samej logiki co w cleanup_user_media ale bez usuwania
        $featured_image_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->posts} a ON pm.meta_value = a.ID
            WHERE pm.meta_key = '_thumbnail_id'
            AND p.post_type IN ('product', 'product_variation')
            AND a.post_author = %d
            AND a.post_type = 'attachment'
        ", $user_id));

        $gallery_image_ids = [];
        $gallery_metas = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_product_image_gallery'
            AND p.post_type IN ('product', 'product_variation')
            AND pm.meta_value != ''
        "));

        foreach ($gallery_metas as $meta) {
            if (!empty($meta->meta_value)) {
                $gallery_ids = explode(',', $meta->meta_value);
                foreach ($gallery_ids as $gallery_id) {
                    $gallery_id = intval(trim($gallery_id));
                    if ($gallery_id > 0) {
                        $author_check = $wpdb->get_var($wpdb->prepare("
                            SELECT post_author FROM {$wpdb->posts} 
                            WHERE ID = %d AND post_type = 'attachment' AND post_author = %d
                        ", $gallery_id, $user_id));

                        if ($author_check) {
                            $gallery_image_ids[] = $gallery_id;
                        }
                    }
                }
            }
        }

        $attached_image_ids = $wpdb->get_col($wpdb->prepare("
            SELECT a.ID 
            FROM {$wpdb->posts} a
            INNER JOIN {$wpdb->posts} p ON a.post_parent = p.ID
            WHERE a.post_type = 'attachment'
            AND a.post_author = %d
            AND p.post_type IN ('product', 'product_variation')
        ", $user_id));

        $mhi_imported_ids = $wpdb->get_col($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_author = %d
            AND pm.meta_key = '_mhi_source_url'
        ", $user_id));

        $all_product_media_ids = array_unique(array_merge(
            $featured_image_ids ?: [],
            $gallery_image_ids ?: [],
            $attached_image_ids ?: [],
            $mhi_imported_ids ?: []
        ));

        $safe_media_ids = [];
        $media_details = [];

        foreach ($all_product_media_ids as $media_id) {
            $mime_type = get_post_mime_type($media_id);
            if ($mime_type && strpos($mime_type, 'image/') === 0) {
                $media_author = get_post_field('post_author', $media_id);
                if ($media_author == $user_id) {
                    $safe_media_ids[] = $media_id;
                    $media_details[] = [
                        'id' => $media_id,
                        'title' => get_the_title($media_id),
                        'url' => wp_get_attachment_url($media_id),
                        'mime_type' => $mime_type,
                        'file_size' => size_format(filesize(get_attached_file($media_id))),
                        'is_featured' => in_array($media_id, $featured_image_ids ?: []),
                        'is_gallery' => in_array($media_id, $gallery_image_ids ?: []),
                        'is_attached' => in_array($media_id, $attached_image_ids ?: []),
                        'is_mhi_imported' => in_array($media_id, $mhi_imported_ids ?: [])
                    ];
                }
            }
        }

        return [
            'total_count' => count($safe_media_ids),
            'media_details' => $media_details,
            'categories' => [
                'featured_images' => count($featured_image_ids ?: []),
                'gallery_images' => count($gallery_image_ids ?: []),
                'attached_images' => count($attached_image_ids ?: []),
                'mhi_imported' => count($mhi_imported_ids ?: [])
            ]
        ];
    }

    /**
     * Obsługa AJAX dla podglądu mediów użytkownika
     */
    public static function ajax_preview_user_media()
    {
        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mhi_preview_user_media')) {
            wp_send_json_error(__('Błąd bezpieczeństwa.', 'multi-hurtownie-integration'));
        }

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nie masz uprawnień do wykonania tej operacji.', 'multi-hurtownie-integration'));
        }

        // Pobierz ID użytkownika
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($user_id <= 0) {
            wp_send_json_error(__('Nieprawidłowy ID użytkownika.', 'multi-hurtownie-integration'));
        }

        // Sprawdź czy użytkownik istnieje
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(__('Użytkownik nie istnieje.', 'multi-hurtownie-integration'));
        }

        try {
            // Pobierz podgląd mediów
            $preview_data = self::preview_user_media_cleanup($user_id);
            wp_send_json_success($preview_data);
        } catch (Exception $e) {
            wp_send_json_error(__('Błąd podczas pobierania podglądu: ', 'multi-hurtownie-integration') . $e->getMessage());
        }
    }
}

// Inicjalizacja klasy
MHI_Cleanup::init();
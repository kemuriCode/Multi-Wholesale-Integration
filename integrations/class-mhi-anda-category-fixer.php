<?php
/**
 * Klasa naprawy kategorii produktów ANDA
 * Wyszukuje produkty-rodzice po SKU i przypisuje ich kategorie
 * 
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_ANDA_Category_Fixer
 * 
 * Naprawia kategorie produktów ANDA na podstawie SKU rodziców
 */
class MHI_ANDA_Category_Fixer
{
    /**
     * Konstruktor.
     */
    public function __construct()
    {
        // Hooks
        add_action('wp_ajax_mhi_fix_anda_categories', array($this, 'ajax_fix_categories'));
        add_action('wp_ajax_mhi_search_anda_parents', array($this, 'ajax_search_parents'));
    }

    /**
     * Główna funkcja naprawy kategorii.
     */
    public function fix_categories($batch_size = 50, $offset = 0, $force_update = false)
    {
        $results = array(
            'processed' => 0,
            'fixed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'logs' => array()
        );

        try {
            // Pobierz produkty ANDA z bazy
            $products = $this->get_anda_products($batch_size, $offset);

            if (empty($products)) {
                $results['logs'][] = 'Nie znaleziono produktów ANDA do naprawy.';
                return $results;
            }

            $results['logs'][] = 'Znaleziono ' . count($products) . ' produktów ANDA do sprawdzenia.';

            foreach ($products as $product) {
                $result = $this->fix_single_product_categories($product, $force_update);

                $results['processed']++;

                switch ($result['status']) {
                    case 'fixed':
                        $results['fixed']++;
                        break;
                    case 'skipped':
                        $results['skipped']++;
                        break;
                    case 'error':
                        $results['errors']++;
                        break;
                }

                $results['logs'][] = $result['message'];
            }

        } catch (Exception $e) {
            $results['errors']++;
            $results['logs'][] = 'Błąd: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Naprawia kategorie pojedynczego produktu.
     */
    private function fix_single_product_categories($product, $force_update = false)
    {
        $product_id = $product->get_id();
        $sku = $product->get_sku();

        if (empty($sku)) {
            return array(
                'status' => 'error',
                'message' => "Produkt ID {$product_id}: Brak SKU"
            );
        }

        // Sprawdź czy produkt ma już kategorie
        $current_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

        if (!empty($current_categories) && !$force_update) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: Ma już kategorie, pomijam"
            );
        }

        // Znajdź produkt-rodzica
        $parent_sku = $this->extract_parent_sku($sku);

        if ($parent_sku === $sku) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: To jest produkt główny (bez rodzica)"
            );
        }

        $parent_product_id = wc_get_product_id_by_sku($parent_sku);

        if (!$parent_product_id) {
            return array(
                'status' => 'error',
                'message' => "Produkt {$sku}: Nie znaleziono rodzica {$parent_sku}"
            );
        }

        $parent_product = wc_get_product($parent_product_id);
        if (!$parent_product) {
            return array(
                'status' => 'error',
                'message' => "Produkt {$sku}: Nie można załadować rodzica {$parent_sku}"
            );
        }

        // Pobierz kategorie rodzica
        $parent_categories = wp_get_post_terms($parent_product_id, 'product_cat', array('fields' => 'ids'));

        if (empty($parent_categories)) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: Rodzic {$parent_sku} nie ma kategorii"
            );
        }

        // Przypisz kategorie rodzica do produktu
        $result = wp_set_post_terms($product_id, $parent_categories, 'product_cat');

        if (is_wp_error($result)) {
            return array(
                'status' => 'error',
                'message' => "Produkt {$sku}: Błąd przypisywania kategorii: " . $result->get_error_message()
            );
        }

        // Pobierz nazwy kategorii do logu
        $category_names = array();
        foreach ($parent_categories as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $category_names[] = $term->name;
            }
        }

        return array(
            'status' => 'fixed',
            'message' => "Produkt {$sku}: Przypisano kategorie od rodzica {$parent_sku}: " . implode(', ', $category_names)
        );
    }

    /**
     * Wyciąga SKU rodzica z SKU produktu.
     */
    private function extract_parent_sku($sku)
    {
        // Pattern 1: BASE-XX (kolor) - AP4135-01 -> AP4135
        if (preg_match('/^(.+)-(\d{2})$/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 2: BASE_SIZE - AP4135_S -> AP4135
        if (preg_match('/^(.+)_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3})$/i', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 3: BASE-XX_SIZE - AP4135-01_S -> AP4135
        if (preg_match('/^(.+)-(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3})$/i', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 4: BASE_XX_SIZE - AP4135_01_S -> AP4135
        if (preg_match('/^(.+)_(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3})$/i', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 5: BASE-XX-YY - AP4135-01-02 -> AP4135
        if (preg_match('/^(.+)-(\d{2})-(\d{2})$/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 6: BASE_XX_YY - AP4135_01_02 -> AP4135
        if (preg_match('/^(.+)_(\d{2})_(\d{2})$/', $sku, $matches)) {
            return $matches[1];
        }

        // Jeśli nie pasuje do żadnego patternu, zwróć oryginalny SKU
        return $sku;
    }

    /**
     * Pobiera produkty ANDA z bazy.
     */
    private function get_anda_products($batch_size = 50, $offset = 0)
    {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'meta_query' => array(
                array(
                    'key' => '_mhi_supplier',
                    'value' => 'anda',
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = $product;
                }
            }
        }

        wp_reset_postdata();
        return $products;
    }

    /**
     * Wyszukuje produkty-rodzice w bazie.
     */
    public function search_parent_products($search_term = '', $limit = 20)
    {
        $results = array();

        // Wyszukaj produkty z SKU podobnym do wzorca
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_sku',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_mhi_supplier',
                    'value' => 'anda',
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $sku = $product->get_sku();
                    $name = $product->get_name();
                    $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));

                    $results[] = array(
                        'id' => $product->get_id(),
                        'sku' => $sku,
                        'name' => $name,
                        'categories' => $categories,
                        'category_count' => count($categories)
                    );
                }
            }
        }

        wp_reset_postdata();
        return $results;
    }

    /**
     * AJAX handler dla naprawy kategorii.
     */
    public function ajax_fix_categories()
    {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mhi_fix_anda_categories')) {
            wp_die('Błąd bezpieczeństwa');
        }

        $batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 50;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';

        $results = $this->fix_categories($batch_size, $offset, $force_update);

        wp_send_json_success($results);
    }

    /**
     * AJAX handler dla wyszukiwania rodziców.
     */
    public function ajax_search_parents()
    {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mhi_search_anda_parents')) {
            wp_die('Błąd bezpieczeństwa');
        }

        $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 20;

        $results = $this->search_parent_products($search_term, $limit);

        wp_send_json_success($results);
    }

    /**
     * Pobiera statystyki produktów ANDA.
     */
    public function get_anda_stats()
    {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_mhi_supplier',
                    'value' => 'anda',
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($args);
        $total_products = $query->found_posts;

        // Licz produkty bez kategorii
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key' => '_product_cat',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_product_cat',
                'value' => '',
                'compare' => '='
            )
        );

        $query_no_categories = new WP_Query($args);
        $products_without_categories = $query_no_categories->found_posts;

        return array(
            'total_products' => $total_products,
            'products_without_categories' => $products_without_categories,
            'products_with_categories' => $total_products - $products_without_categories
        );
    }

    /**
     * Czyści kategorie z długimi łańcuchami zawierającymi '>'
     */
    public function clean_long_categories($batch_size = 50, $offset = 0)
    {
        $results = array(
            'processed' => 0,
            'cleaned' => 0,
            'skipped' => 0,
            'errors' => 0,
            'logs' => array()
        );

        try {
            // Pobierz produkty ANDA z bazy
            $products = $this->get_anda_products($batch_size, $offset);

            if (empty($products)) {
                $results['logs'][] = 'Nie znaleziono produktów ANDA do czyszczenia.';
                return $results;
            }

            $results['logs'][] = 'Znaleziono ' . count($products) . ' produktów ANDA do sprawdzenia.';

            foreach ($products as $product) {
                $result = $this->clean_single_product_categories($product);
                $results['processed']++;

                switch ($result['status']) {
                    case 'cleaned':
                        $results['cleaned']++;
                        break;
                    case 'skipped':
                        $results['skipped']++;
                        break;
                    case 'error':
                        $results['errors']++;
                        break;
                }

                $results['logs'][] = $result['message'];
            }

        } catch (Exception $e) {
            $results['errors']++;
            $results['logs'][] = 'Błąd: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Czyści kategorie pojedynczego produktu z długimi łańcuchami.
     */
    private function clean_single_product_categories($product)
    {
        $product_id = $product->get_id();
        $sku = $product->get_sku();

        if (empty($sku)) {
            return array(
                'status' => 'error',
                'message' => "Produkt ID {$product_id}: Brak SKU"
            );
        }

        // Pobierz aktualne kategorie
        $current_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));

        if (empty($current_categories)) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: Brak kategorii do czyszczenia"
            );
        }

        $cleaned_categories = array();
        $removed_categories = array();

        foreach ($current_categories as $category) {
            // Sprawdź czy kategoria zawiera długi łańcuch z '>'
            if (strpos($category->name, '>') !== false) {
                $removed_categories[] = $category->name;
                continue; // Pomiń tę kategorię
            }

            $cleaned_categories[] = $category->term_id;
        }

        // Jeśli nie ma kategorii do usunięcia
        if (empty($removed_categories)) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: Brak długich kategorii do usunięcia"
            );
        }

        // Zaktualizuj kategorie produktu
        $result = wp_set_post_terms($product_id, $cleaned_categories, 'product_cat');

        if (is_wp_error($result)) {
            return array(
                'status' => 'error',
                'message' => "Produkt {$sku}: Błąd aktualizacji kategorii: " . $result->get_error_message()
            );
        }

        return array(
            'status' => 'cleaned',
            'message' => "Produkt {$sku}: Usunięto długie kategorie: " . implode(', ', $removed_categories)
        );
    }

    /**
     * Sprawdza produkty z długimi kategoriami (tylko podgląd).
     */
    public function check_long_categories($batch_size = 50, $offset = 0)
    {
        $results = array(
            'processed' => 0,
            'found' => 0,
            'skipped' => 0,
            'errors' => 0,
            'logs' => array(),
            'products_with_long_categories' => array()
        );

        try {
            // Pobierz produkty ANDA z bazy
            $products = $this->get_anda_products($batch_size, $offset);

            if (empty($products)) {
                $results['logs'][] = 'Nie znaleziono produktów ANDA do sprawdzenia.';
                return $results;
            }

            $results['logs'][] = 'Znaleziono ' . count($products) . ' produktów ANDA do sprawdzenia.';

            foreach ($products as $product) {
                $result = $this->check_single_product_long_categories($product);
                $results['processed']++;

                switch ($result['status']) {
                    case 'found':
                        $results['found']++;
                        $results['products_with_long_categories'][] = $result['product_info'];
                        break;
                    case 'skipped':
                        $results['skipped']++;
                        break;
                    case 'error':
                        $results['errors']++;
                        break;
                }

                $results['logs'][] = $result['message'];
            }

        } catch (Exception $e) {
            $results['errors']++;
            $results['logs'][] = 'Błąd: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Sprawdza długie kategorie pojedynczego produktu (tylko podgląd).
     */
    private function check_single_product_long_categories($product)
    {
        $product_id = $product->get_id();
        $sku = $product->get_sku();

        if (empty($sku)) {
            return array(
                'status' => 'error',
                'message' => "Produkt ID {$product_id}: Brak SKU"
            );
        }

        // Pobierz aktualne kategorie
        $current_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));

        if (empty($current_categories)) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: Brak kategorii do sprawdzenia"
            );
        }

        $long_categories = array();
        $normal_categories = array();

        foreach ($current_categories as $category) {
            // Sprawdź czy kategoria zawiera długi łańcuch z '>'
            if (strpos($category->name, '>') !== false) {
                $long_categories[] = $category->name;
            } else {
                $normal_categories[] = $category->name;
            }
        }

        // Jeśli nie ma długich kategorii
        if (empty($long_categories)) {
            return array(
                'status' => 'skipped',
                'message' => "Produkt {$sku}: Brak długich kategorii"
            );
        }

        return array(
            'status' => 'found',
            'message' => "Produkt {$sku}: Znaleziono długie kategorie: " . implode(', ', $long_categories) . " | Normalne: " . implode(', ', $normal_categories),
            'product_info' => array(
                'id' => $product_id,
                'sku' => $sku,
                'name' => $product->get_name(),
                'long_categories' => $long_categories,
                'normal_categories' => $normal_categories
            )
        );
    }
}

// Inicjalizacja klasy
new MHI_ANDA_Category_Fixer();
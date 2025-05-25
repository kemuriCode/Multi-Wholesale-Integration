<?php
/**
 * Klasa obsługująca bezpośredni import produktów do WooCommerce
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Direct_Importer
 * 
 * Bezpośredni importer produktów - import synchroniczny bez Action Schedulera
 */
class MHI_Direct_Importer
{
    /**
     * Nazwa hurtowni
     *
     * @var string
     */
    private $supplier_name;

    /**
     * Ścieżka do pliku XML
     *
     * @var string
     */
    private $xml_file;

    /**
     * Licznik produktów
     *
     * @var array
     */
    private $counter = array(
        'total' => 0,
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
    );

    /**
     * Czas rozpoczęcia importu
     *
     * @var int
     */
    private $start_time;

    /**
     * Konstruktor
     *
     * @param string $supplier_name Nazwa hurtowni
     */
    public function __construct($supplier_name)
    {
        $this->supplier_name = sanitize_text_field($supplier_name);

        // Pobierz ścieżkę do pliku XML
        $upload_dir = wp_upload_dir();
        $this->xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->supplier_name . '/woocommerce_import_' . $this->supplier_name . '.xml';
    }

    /**
     * Rozpoczyna import produktów - metoda główna
     * 
     * @return array Wynik importu
     */
    public function import()
    {
        // Sprawdź czy WooCommerce jest dostępny
        if (!class_exists('WooCommerce')) {
            return array(
                'success' => false,
                'message' => 'WooCommerce nie jest aktywny. Import produktów jest niemożliwy.'
            );
        }

        // Sprawdź czy plik XML istnieje
        if (!file_exists($this->xml_file)) {
            return array(
                'success' => false,
                'message' => 'Nie znaleziono pliku XML do importu: ' . $this->xml_file
            );
        }

        // Zwiększ limit pamięci i czasu wykonania
        ini_set('memory_limit', '1024M');
        set_time_limit(3600); // 1 godzina

        $this->start_time = time();
        $this->counter = array(
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        );

        // Aktualizuj status
        update_option('mhi_import_status_' . $this->supplier_name, array(
            'status' => 'running',
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'current_product' => '',
            'message' => 'Rozpoczynam import z hurtowni ' . $this->supplier_name,
            'percent' => 0,
            'start_time' => $this->start_time,
            'elapsed_time' => 0,
            'estimated_time' => 0,
        ));

        // Wyłącz automatyczne zapisywanie wersji dla produktów
        add_filter('wp_revisions_to_keep', function ($num, $post) {
            if ($post->post_type === 'product') {
                return 0;
            }
            return $num;
        }, 10, 2);

        try {
            // Wyłącz cache obiektów WP
            wp_defer_term_counting(true);
            wp_defer_comment_counting(true);
            wp_suspend_cache_invalidation(true);

            // Rozpocznij transakcję w bazie danych, jeśli to możliwe
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            // Procesuj import
            $result = $this->process_import();

            // Zakończ transakcję
            $wpdb->query('COMMIT');

            // Włącz cache obiektów WP
            wp_suspend_cache_invalidation(false);
            wp_defer_term_counting(false);
            wp_defer_comment_counting(false);

            return $result;
        } catch (Exception $e) {
            // W przypadku błędu, cofnij transakcję
            global $wpdb;
            $wpdb->query('ROLLBACK');

            // Włącz cache obiektów WP
            wp_suspend_cache_invalidation(false);
            wp_defer_term_counting(false);
            wp_defer_comment_counting(false);

            // Aktualizuj status
            update_option('mhi_import_status_' . $this->supplier_name, array(
                'status' => 'error',
                'message' => 'Błąd importu: ' . $e->getMessage(),
                'end_time' => time(),
                'elapsed_time' => time() - $this->start_time
            ));

            return array(
                'success' => false,
                'message' => 'Wystąpił błąd podczas importu: ' . $e->getMessage(),
                'counter' => $this->counter
            );
        }
    }

    /**
     * Procesuje import produktów
     * 
     * @return array Wynik importu
     */
    private function process_import()
    {
        // Załaduj plik XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($this->xml_file);

        if (!$xml) {
            $error_message = "Błędy parsowania XML:\n";
            foreach (libxml_get_errors() as $error) {
                $error_message .= "Linia " . $error->line . ": " . $error->message . "\n";
            }
            libxml_clear_errors();

            throw new Exception('Nie można przetworzyć pliku XML: ' . $error_message);
        }

        // Pobierz produkty
        $products = $xml->children();
        $this->counter['total'] = count($products);

        // Aktualizuj status
        $status = get_option('mhi_import_status_' . $this->supplier_name, array());
        $status['total'] = $this->counter['total'];
        $status['message'] = 'Rozpoczynam import ' . $this->counter['total'] . ' produktów';
        update_option('mhi_import_status_' . $this->supplier_name, $status);

        // Importuj produkty
        foreach ($products as $index => $product) {
            // Pobierz SKU produktu
            $sku = (string) $product->sku;
            if (empty($sku)) {
                $sku = (string) $product->id;
            }

            // Aktualizuj licznik i status
            $this->counter['processed']++;
            $current_percent = round(($this->counter['processed'] / $this->counter['total']) * 100);

            $elapsed_time = time() - $this->start_time;
            $estimated_time = 0;

            if ($this->counter['processed'] > 0) {
                $time_per_product = $elapsed_time / $this->counter['processed'];
                $remaining_products = $this->counter['total'] - $this->counter['processed'];
                $estimated_time = round($time_per_product * $remaining_products);
            }

            // Aktualizuj status co 10 produktów lub co 1%
            if ($this->counter['processed'] % 10 === 0 || $current_percent > (int) $status['percent']) {
                $status = array(
                    'status' => 'running',
                    'total' => $this->counter['total'],
                    'processed' => $this->counter['processed'],
                    'created' => $this->counter['created'],
                    'updated' => $this->counter['updated'],
                    'skipped' => $this->counter['skipped'],
                    'failed' => $this->counter['failed'],
                    'current_product' => $sku,
                    'message' => 'Przetwarzanie produktów: ' . $this->counter['processed'] . ' z ' . $this->counter['total'],
                    'percent' => $current_percent,
                    'start_time' => $this->start_time,
                    'elapsed_time' => $elapsed_time,
                    'estimated_time' => $estimated_time,
                );
                update_option('mhi_import_status_' . $this->supplier_name, $status);
            }

            // Importuj produkt
            try {
                $result = $this->import_product($product);

                // Aktualizuj liczniki
                if ($result === 'created') {
                    $this->counter['created']++;
                } elseif ($result === 'updated') {
                    $this->counter['updated']++;
                } elseif ($result === 'skipped') {
                    $this->counter['skipped']++;
                } else {
                    $this->counter['failed']++;
                }
            } catch (Exception $e) {
                // Loguj błąd, ale kontynuuj import
                error_log('MHI ERROR: Import produktu ' . $sku . ' nie powiódł się: ' . $e->getMessage());
                $this->counter['failed']++;
            }

            // Wyczyść pamięć podręczną co 100 produktów
            if ($this->counter['processed'] % 100 === 0) {
                wp_cache_flush();
                $this->free_memory();
            }
        }

        // Zakończ import i aktualizuj status
        $end_time = time();
        $elapsed_time = $end_time - $this->start_time;

        $status = array(
            'status' => 'completed',
            'total' => $this->counter['total'],
            'processed' => $this->counter['processed'],
            'created' => $this->counter['created'],
            'updated' => $this->counter['updated'],
            'skipped' => $this->counter['skipped'],
            'failed' => $this->counter['failed'],
            'current_product' => '',
            'message' => 'Import zakończony. Przetworzono ' . $this->counter['processed'] . ' produktów w ' . $this->format_time($elapsed_time),
            'percent' => 100,
            'start_time' => $this->start_time,
            'end_time' => $end_time,
            'elapsed_time' => $elapsed_time,
            'estimated_time' => 0,
        );
        update_option('mhi_import_status_' . $this->supplier_name, $status);

        return array(
            'success' => true,
            'message' => 'Import zakończony pomyślnie. Przetworzono ' . $this->counter['processed'] . ' produktów w ' . $this->format_time($elapsed_time),
            'counter' => $this->counter,
            'time' => $elapsed_time
        );
    }

    /**
     * Importuje pojedynczy produkt
     * 
     * @param SimpleXMLElement $product_data Dane produktu z XML
     * @return string Status importu (created, updated, skipped, failed)
     */
    private function import_product($product_data)
    {
        // Pobierz SKU produktu
        $sku = (string) $product_data->sku;
        if (empty($sku)) {
            $sku = (string) $product_data->id;
        }

        if (empty($sku)) {
            throw new Exception('Nie można zaimportować produktu bez SKU/ID');
        }

        // Pobierz nazwę produktu
        $product_name = (string) $product_data->name;
        if (empty($product_name)) {
            $product_name = (string) $product_data->n;
        }

        if (empty($product_name)) {
            $product_name = 'Produkt ' . $sku;
        }

        // Sprawdź czy produkt już istnieje
        $product_id = wc_get_product_id_by_sku($sku);
        $product = null;

        if ($product_id) {
            $product = wc_get_product($product_id);

            // Sprawdź czy dane produktu się zmieniły
            if ($this->product_unchanged($product, $product_data)) {
                return 'skipped';
            }
        }

        // Przygotuj dane produktu
        $product_args = array(
            'name' => $product_name,
            'description' => (string) $product_data->description,
            'short_description' => (string) $product_data->short_description,
            'status' => 'publish',
            'featured' => (string) $product_data->featured === 'yes',
            'catalog_visibility' => (string) $product_data->visibility ?: 'visible',
            'sku' => $sku,
            'regular_price' => (string) $product_data->regular_price,
            'sale_price' => (string) $product_data->sale_price,
            'virtual' => (string) $product_data->virtual === 'yes',
            'downloadable' => (string) $product_data->downloadable === 'yes',
            'manage_stock' => isset($product_data->manage_stock) ? ((string) $product_data->manage_stock === 'yes') : true,
            'stock_quantity' => (int) $product_data->stock_quantity,
            'stock_status' => (string) $product_data->stock_status ?: 'instock',
            'backorders' => (string) $product_data->backorders ?: 'no',
            'sold_individually' => (string) $product_data->sold_individually === 'yes',
            'weight' => (string) $product_data->weight,
            'length' => (string) $product_data->length,
            'width' => (string) $product_data->width,
            'height' => (string) $product_data->height,
            'reviews_allowed' => (string) $product_data->reviews_allowed === 'yes',
            'purchase_note' => (string) $product_data->purchase_note,
            'menu_order' => (int) $product_data->menu_order,
        );

        // Tworzenie lub aktualizacja produktu
        if (!$product_id) {
            // Nowy produkt
            $product = new WC_Product();
            $product->set_sku($sku);
            $this->update_product_data($product, $product_args);
            $product_id = $product->save();

            // Dodaj kategorie, atrybuty i obrazki
            $this->add_product_categories($product_id, $product_data);
            $this->add_product_attributes($product_id, $product_data);
            $this->add_product_images($product_id, $product_data);

            return 'created';
        } else {
            // Aktualizacja istniejącego produktu
            $this->update_product_data($product, $product_args);
            $product->save();

            // Aktualizuj kategorie, atrybuty i obrazki
            $this->add_product_categories($product_id, $product_data);
            $this->add_product_attributes($product_id, $product_data);
            $this->add_product_images($product_id, $product_data);

            return 'updated';
        }
    }

    /**
     * Aktualizuje dane produktu
     * 
     * @param WC_Product $product Obiekt produktu
     * @param array $data Dane produktu
     */
    private function update_product_data($product, $data)
    {
        // Ustaw właściwości produktu
        if (isset($data['name']))
            $product->set_name($data['name']);
        if (isset($data['description']))
            $product->set_description($data['description']);
        if (isset($data['short_description']))
            $product->set_short_description($data['short_description']);
        if (isset($data['status']))
            $product->set_status($data['status']);
        if (isset($data['featured']))
            $product->set_featured($data['featured']);
        if (isset($data['catalog_visibility']))
            $product->set_catalog_visibility($data['catalog_visibility']);
        if (isset($data['regular_price']))
            $product->set_regular_price($data['regular_price']);
        if (isset($data['sale_price']))
            $product->set_sale_price($data['sale_price']);
        if (isset($data['virtual']))
            $product->set_virtual($data['virtual']);
        if (isset($data['downloadable']))
            $product->set_downloadable($data['downloadable']);
        if (isset($data['manage_stock']))
            $product->set_manage_stock($data['manage_stock']);
        if (isset($data['stock_quantity']))
            $product->set_stock_quantity($data['stock_quantity']);
        if (isset($data['stock_status']))
            $product->set_stock_status($data['stock_status']);
        if (isset($data['backorders']))
            $product->set_backorders($data['backorders']);
        if (isset($data['sold_individually']))
            $product->set_sold_individually($data['sold_individually']);
        if (isset($data['weight']))
            $product->set_weight($data['weight']);
        if (isset($data['length']))
            $product->set_length($data['length']);
        if (isset($data['width']))
            $product->set_width($data['width']);
        if (isset($data['height']))
            $product->set_height($data['height']);
        if (isset($data['reviews_allowed']))
            $product->set_reviews_allowed($data['reviews_allowed']);
        if (isset($data['purchase_note']))
            $product->set_purchase_note($data['purchase_note']);
        if (isset($data['menu_order']))
            $product->set_menu_order($data['menu_order']);
    }

    /**
     * Dodaje kategorie do produktu
     * 
     * @param int $product_id ID produktu
     * @param SimpleXMLElement $product_data Dane produktu z XML
     */
    private function add_product_categories($product_id, $product_data)
    {
        if (!isset($product_data->categories) || !$product_data->categories->category) {
            return;
        }

        $categories = array();

        // Pobierz listę kategorii
        foreach ($product_data->categories->category as $category) {
            $category_name = (string) $category;
            if (empty($category_name)) {
                continue;
            }

            // Sprawdź czy są podkategorie (kategorie rozdzielone ">")
            if (strpos($category_name, '>') !== false) {
                $category_path = array_map('trim', explode('>', $category_name));
                $current_parent_id = 0;

                foreach ($category_path as $cat_name) {
                    $term = term_exists($cat_name, 'product_cat', $current_parent_id);

                    if (!$term) {
                        // Utwórz kategorię, jeśli nie istnieje
                        $term = wp_insert_term($cat_name, 'product_cat', array('parent' => $current_parent_id));
                    }

                    if (!is_wp_error($term)) {
                        $current_parent_id = $term['term_id'];
                    }
                }

                // Dodaj ostatni ID kategorii do listy
                if (!is_wp_error($term) && isset($term['term_id'])) {
                    $categories[] = $term['term_id'];
                }
            } else {
                // Pojedyncza kategoria
                $term = term_exists($category_name, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($category_name, 'product_cat');
                }

                if (!is_wp_error($term) && isset($term['term_id'])) {
                    $categories[] = $term['term_id'];
                }
            }
        }

        // Przypisz kategorie do produktu
        if (!empty($categories)) {
            wp_set_object_terms($product_id, $categories, 'product_cat');
        }
    }

    /**
     * Dodaje atrybuty do produktu
     * 
     * @param int $product_id ID produktu
     * @param SimpleXMLElement $product_data Dane produktu z XML
     */
    private function add_product_attributes($product_id, $product_data)
    {
        if (!isset($product_data->attributes) || !$product_data->attributes->attribute) {
            return;
        }

        $attributes = array();

        // Pobierz listę atrybutów
        foreach ($product_data->attributes->attribute as $attribute) {
            $name = (string) $attribute->name;
            if (empty($name)) {
                continue;
            }

            $values = array();

            // Pobierz wartości atrybutu
            if (isset($attribute->value) && (string) $attribute->value !== '') {
                $values[] = (string) $attribute->value;
            } elseif (isset($attribute->values) && $attribute->values->value) {
                foreach ($attribute->values->value as $value) {
                    if ((string) $value !== '') {
                        $values[] = (string) $value;
                    }
                }
            }

            if (!empty($values)) {
                $taxonomy = wc_attribute_taxonomy_name($name);
                $attr_id = wc_attribute_taxonomy_id_by_name($name);

                // Sprawdź czy taksonimia istnieje, jeśli nie - utwórz
                if (!$attr_id) {
                    // Zarejestruj nową taksonomię atrybutu
                    $slug = wc_sanitize_taxonomy_name($name);

                    $attribute_id = wc_create_attribute(array(
                        'name' => $name,
                        'slug' => $slug,
                        'type' => 'select',
                        'order_by' => 'menu_order',
                        'has_archives' => false
                    ));

                    if (!is_wp_error($attribute_id)) {
                        $taxonomy = wc_attribute_taxonomy_name($slug);

                        // Zarejestruj taksonomię
                        register_taxonomy(
                            $taxonomy,
                            'product',
                            array(
                                'hierarchical' => false,
                                'show_ui' => true,
                                'query_var' => true,
                                'rewrite' => array('slug' => $slug),
                            )
                        );
                    }
                }

                // Upewnij się, że wartości atrybutu istnieją w taksonomii
                $term_ids = array();
                foreach ($values as $value) {
                    $term = term_exists($value, $taxonomy);
                    if (!$term) {
                        $term = wp_insert_term($value, $taxonomy);
                    }
                    if (!is_wp_error($term) && isset($term['term_id'])) {
                        $term_ids[] = $term['term_id'];
                    }
                }

                // Przypisz wartości do produktu
                if (!empty($term_ids)) {
                    wp_set_object_terms($product_id, $term_ids, $taxonomy);
                }

                // Dodaj atrybut do tablicy atrybutów
                $attributes[$taxonomy] = array(
                    'name' => $name,
                    'value' => '',
                    'position' => isset($attribute->position) ? (int) $attribute->position : 0,
                    'is_visible' => isset($attribute->visible) ? ((string) $attribute->visible === 'yes') : true,
                    'is_variation' => isset($attribute->variation) ? ((string) $attribute->variation === 'yes') : false,
                    'is_taxonomy' => true
                );
            }
        }

        // Zapisz atrybuty
        if (!empty($attributes)) {
            update_post_meta($product_id, '_product_attributes', $attributes);
        }
    }

    /**
     * Dodaje obrazki do produktu
     * 
     * @param int $product_id ID produktu
     * @param SimpleXMLElement $product_data Dane produktu z XML
     */
    private function add_product_images($product_id, $product_data)
    {
        if (!isset($product_data->images) || !$product_data->images->image) {
            return;
        }

        $image_ids = array();
        $main_image_set = false;

        // Pobierz wszystkie obrazki
        foreach ($product_data->images->image as $image) {
            $image_url = (string) $image;
            if (empty($image_url)) {
                continue;
            }

            $alt_text = isset($image['alt']) ? (string) $image['alt'] : '';

            // Pobierz obrazek i dodaj go do biblioteki mediów
            $attachment_id = $this->create_image_from_url($image_url, $alt_text);

            if ($attachment_id) {
                $image_ids[] = $attachment_id;

                // Ustaw pierwszy obrazek jako główny
                if (!$main_image_set) {
                    set_post_thumbnail($product_id, $attachment_id);
                    $main_image_set = true;
                }
            }
        }

        // Dodaj pozostałe obrazki jako galeria produktu
        if (count($image_ids) > 1) {
            // Usuń obrazek główny z galerii
            $gallery_ids = array_slice($image_ids, 1);
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
    }

    /**
     * Tworzy obrazek z URL i dodaje go do biblioteki mediów
     * 
     * @param string $image_url URL obrazka
     * @param string $alt_text Tekst alternatywny
     * @return int|false ID załącznika lub false w przypadku błędu
     */
    private function create_image_from_url($image_url, $alt_text = '')
    {
        // Sprawdź, czy obrazek o takim URL już istnieje
        $attachment_id = $this->get_attachment_id_by_url($image_url);
        if ($attachment_id) {
            return $attachment_id;
        }

        // Pobierz obrazek
        $response = wp_safe_remote_get($image_url, array(
            'timeout' => 30
        ));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return false;
        }

        // Przygotuj nazwę pliku
        $filename = basename(parse_url($image_url, PHP_URL_PATH));

        // Generuj losową datę z ostatnich 18 miesięcy dla lepszej organizacji folderów
        $months_back = rand(1, 18); // losowo 1-18 miesięcy wstecz
        $random_timestamp = strtotime("-{$months_back} months");

        // Dodatkowo losuj dzień w miesiącu
        $year = (int) date('Y', $random_timestamp);
        $month = (int) date('m', $random_timestamp);
        $day = rand(1, 28); // maksymalnie 28, żeby być bezpiecznym dla lutego
        $hour = rand(8, 18); // godziny robocze
        $minute = rand(0, 59);
        $second = rand(0, 59);

        $final_timestamp = mktime($hour, $minute, $second, $month, $day, $year);

        // Użyj konkretnej daty dla wp_upload_dir - WordPress automatycznie utworzy folder roczno-miesięczny
        $upload_dir = wp_upload_dir(date('Y/m', $final_timestamp));
        $upload_path = $upload_dir['path'] . '/' . sanitize_file_name($filename);

        // Zapisz plik
        file_put_contents($upload_path, $image_data);

        // Przygotuj dane załącznika z odpowiednią datą publikacji
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_date' => date('Y-m-d H:i:s', $final_timestamp),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $final_timestamp),
            'post_modified' => date('Y-m-d H:i:s', $final_timestamp),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s', $final_timestamp)
        );

        // Wstaw załącznik
        $attachment_id = wp_insert_attachment($attachment, $upload_path);

        // Wygeneruj metadane
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Ustaw tekst alternatywny
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }

        // Zapisz informacje o losowej dacie i folderze
        update_post_meta($attachment_id, '_mhi_random_date', date('Y-m-d H:i:s', $final_timestamp));
        update_post_meta($attachment_id, '_mhi_folder_path', date('Y/m', $final_timestamp));

        return $attachment_id;
    }

    /**
     * Pobiera ID załącznika na podstawie URL
     * 
     * @param string $url URL obrazka
     * @return int|false ID załącznika lub false jeśli nie znaleziono
     */
    private function get_attachment_id_by_url($url)
    {
        global $wpdb;

        // Pobierz bazowy URL
        $upload_dir = wp_upload_dir();
        $upload_base_url = $upload_dir['baseurl'];

        // Jeśli URL jest względny, dodaj domenę
        if (strpos($url, 'http') !== 0) {
            if (strpos($url, '/') === 0) {
                $url = home_url($url);
            } else {
                $url = $upload_base_url . '/' . $url;
            }
        }

        // Szukaj załącznika w bazie danych
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE guid='%s';", $url));

        return !empty($attachment[0]) ? $attachment[0] : false;
    }

    /**
     * Sprawdza, czy dane produktu uległy zmianie
     * 
     * @param WC_Product $product Produkt WooCommerce
     * @param SimpleXMLElement $product_data Dane produktu z XML
     * @return bool True jeśli produkt nie uległ zmianie
     */
    private function product_unchanged($product, $product_data)
    {
        // Porównaj podstawowe dane
        if ($product->get_name() !== (string) $product_data->name) {
            return false;
        }

        // Porównaj cenę
        if ($product->get_regular_price() !== (string) $product_data->regular_price) {
            return false;
        }

        // Porównaj stan magazynowy
        if ($product->get_stock_quantity() !== (int) $product_data->stock_quantity) {
            return false;
        }

        // Produkt nie uległ zmianie
        return true;
    }

    /**
     * Formatuje czas w sekundach do postaci czytelnej dla człowieka
     * 
     * @param int $seconds Czas w sekundach
     * @return string Sformatowany czas
     */
    private function format_time($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' sekund';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . ' minut' . ($secs > 0 ? ', ' . $secs . ' sekund' : '');
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . ' godzin' . ($minutes > 0 ? ', ' . $minutes . ' minut' : '');
        }
    }

    /**
     * Zwalnia pamięć
     */
    private function free_memory()
    {
        global $wpdb, $wp_object_cache;

        // Czyści tymczasowe dane
        $wpdb->queries = array();

        if (is_object($wp_object_cache)) {
            $wp_object_cache->flush();
        }

        // Garbage collector
        gc_collect_cycles();
    }
}
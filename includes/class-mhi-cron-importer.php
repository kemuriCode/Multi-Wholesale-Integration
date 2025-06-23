<?php
/**
 * Klasa obsługująca automatyczny import produktów przez cron
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Cron_Importer
 * 
 * Obsługuje automatyczny import produktów w tle za pomocą WordPress cron
 */
class MHI_Cron_Importer
{
    /**
     * Nazwa hooka cron dla importu
     */
    const CRON_HOOK = 'mhi_auto_import_products';

    /**
     * Nazwa opcji przechowującej ustawienia cron
     */
    const SETTINGS_OPTION = 'mhi_cron_import_settings';

    /**
     * Nazwa opcji przechowującej status ostatniego importu
     */
    const STATUS_OPTION = 'mhi_cron_import_status';

    /**
     * Inicjalizacja klasy
     */
    public static function init()
    {
        // Rejestracja hooka cron
        add_action(self::CRON_HOOK, [__CLASS__, 'run_import']);

        // Rejestracja niestandardowych interwałów cron
        add_filter('cron_schedules', [__CLASS__, 'add_custom_cron_intervals']);

        // Hook aktywacji/dezaktywacji
        register_activation_hook(MHI_PLUGIN_DIR . 'multi-wholesale-integration.php', [__CLASS__, 'schedule_import']);
        register_deactivation_hook(MHI_PLUGIN_DIR . 'multi-wholesale-integration.php', [__CLASS__, 'unschedule_import']);

        // Dodaj menu w panelu administracyjnym
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // Obsługa AJAX
        add_action('wp_ajax_mhi_update_cron_settings', [__CLASS__, 'ajax_update_settings']);
        add_action('wp_ajax_mhi_run_manual_import', [__CLASS__, 'ajax_run_manual_import']);
        add_action('wp_ajax_mhi_get_import_status', [__CLASS__, 'ajax_get_import_status']);
    }

    /**
     * Dodaje niestandardowe interwały cron
     */
    public static function add_custom_cron_intervals($schedules)
    {
        $schedules['every_15_minutes'] = [
            'interval' => 15 * 60,
            'display' => __('Co 15 minut', 'multi-wholesale-integration')
        ];

        $schedules['every_30_minutes'] = [
            'interval' => 30 * 60,
            'display' => __('Co 30 minut', 'multi-wholesale-integration')
        ];

        $schedules['every_2_hours'] = [
            'interval' => 2 * 60 * 60,
            'display' => __('Co 2 godziny', 'multi-wholesale-integration')
        ];

        $schedules['every_6_hours'] = [
            'interval' => 6 * 60 * 60,
            'display' => __('Co 6 godzin', 'multi-wholesale-integration')
        ];

        $schedules['every_12_hours'] = [
            'interval' => 12 * 60 * 60,
            'display' => __('Co 12 godzin', 'multi-wholesale-integration')
        ];

        return $schedules;
    }

    /**
     * Planuje automatyczny import
     */
    public static function schedule_import()
    {
        $settings = self::get_settings();

        if ($settings['enabled'] && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $settings['interval'], self::CRON_HOOK);
            self::log('Zaplanowano automatyczny import z interwałem: ' . $settings['interval']);
        }
    }

    /**
     * Usuwa zaplanowany import
     */
    public static function unschedule_import()
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            self::log('Usunięto zaplanowany automatyczny import');
        }
    }

    /**
     * Uruchamia import produktów
     */
    public static function run_import()
    {
        $settings = self::get_settings();

        if (!$settings['enabled']) {
            self::log('Import wyłączony w ustawieniach');
            return;
        }

        self::update_status('running', 'Rozpoczynanie importu...');
        self::log('🚀 Rozpoczynanie automatycznego importu produktów');

        $suppliers = $settings['suppliers'];
        $total_imported = 0;
        $total_errors = 0;

        foreach ($suppliers as $supplier) {
            if (!$supplier['enabled']) {
                continue;
            }

            self::log("📦 Importowanie produktów z hurtowni: {$supplier['name']}");

            try {
                $result = self::import_supplier_products($supplier['slug']);

                if ($result['success']) {
                    $total_imported += $result['imported'];
                    self::log("✅ {$supplier['name']}: {$result['imported']} produktów zaimportowanych");
                } else {
                    $total_errors++;
                    self::log("❌ {$supplier['name']}: {$result['error']}", 'error');
                }

            } catch (Exception $e) {
                $total_errors++;
                self::log("❌ Błąd importu {$supplier['name']}: " . $e->getMessage(), 'error');
            }
        }

        $status_message = "Import zakończony. Zaimportowano: {$total_imported} produktów";
        if ($total_errors > 0) {
            $status_message .= ", błędy: {$total_errors}";
        }

        self::update_status('completed', $status_message);
        self::log("🎉 " . $status_message);
    }

    /**
     * Importuje produkty z konkretnej hurtowni
     */
    private static function import_supplier_products($supplier)
    {
        // Specjalna obsługa dla ANDA - nowy kompleksowy XML
        if ($supplier === 'anda') {
            return self::import_anda_products();
        }

        // Standardowa obsługa dla innych hurtowni
        $upload_dir = wp_upload_dir();
        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

        if (!file_exists($xml_file)) {
            return [
                'success' => false,
                'error' => 'Plik XML nie istnieje: ' . basename($xml_file)
            ];
        }

        // Parsuj XML
        $xml = simplexml_load_file($xml_file);
        if (!$xml) {
            return [
                'success' => false,
                'error' => 'Błąd parsowania pliku XML'
            ];
        }

        $products = $xml->children();
        $total = count($products);
        $imported = 0;
        $errors = 0;

        // Zwiększ limity
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        // Wyłącz cache dla wydajności
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        wp_suspend_cache_invalidation(true);

        foreach ($products as $product_xml) {
            try {
                $result = self::import_single_product($product_xml);
                if ($result) {
                    $imported++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                self::log("Błąd importu produktu: " . $e->getMessage(), 'error');
            }

            // Aktualizuj status co 10 produktów
            if (($imported + $errors) % 10 === 0) {
                $progress = round((($imported + $errors) / $total) * 100);
                self::update_status('running', "Importowanie {$supplier}: {$progress}% ({$imported}/{$total})");
            }
        }

        // Przywróć cache
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
        wp_suspend_cache_invalidation(false);

        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'total' => $total
        ];
    }

    /**
     * Specjalny import dla hurtowni ANDA z kompleksowym XML
     */
    private static function import_anda_products()
    {
        self::log('🔥 Rozpoczynanie importu ANDA z kompleksowym XML');

        // Znajdź nowy plik ANDA
        $upload_dir = wp_upload_dir();
        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/anda/anda_complete_import.xml';

        if (!file_exists($xml_file)) {
            return [
                'success' => false,
                'error' => 'Kompleksowy plik XML ANDA nie istnieje: anda_complete_woocommerce.xml'
            ];
        }

        // Parsuj XML
        $xml = simplexml_load_file($xml_file);
        if (!$xml) {
            return [
                'success' => false,
                'error' => 'Błąd parsowania kompleksowego pliku XML ANDA'
            ];
        }

        // Sprawdź strukturę - nowy XML ma <products><product>...
        if (!isset($xml->products) && !isset($xml->products->product)) {
            return [
                'success' => false,
                'error' => 'Nieprawidłowa struktura kompleksowego XML ANDA'
            ];
        }

        $products = $xml->products->product;
        $total = count($products);
        $imported = 0;
        $errors = 0;

        self::log("📦 Znaleziono {$total} produktów ANDA do importu");

        // Zwiększ limity - ANDA ma dużo danych
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        // Wyłącz cache dla wydajności
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        wp_suspend_cache_invalidation(true);

        foreach ($products as $product_xml) {
            try {
                $result = self::import_anda_single_product($product_xml);
                if ($result) {
                    $imported++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                self::log("Błąd importu produktu ANDA: " . $e->getMessage(), 'error');
            }

            // Aktualizuj status co 5 produktów (częściej bo ANDA ma więcej danych)
            if (($imported + $errors) % 5 === 0) {
                $progress = round((($imported + $errors) / $total) * 100);
                self::update_status('running', "Import ANDA: {$progress}% ({$imported}/{$total})");
            }
        }

        // Przywróć cache
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
        wp_suspend_cache_invalidation(false);

        self::log("✅ Import ANDA zakończony: {$imported} importów, {$errors} błędów");

        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'total' => $total
        ];
    }

    /**
     * Importuje pojedynczy produkt
     */
    private static function import_single_product($product_xml)
    {
        // SKU i nazwa produktu
        $sku = trim((string) $product_xml->sku);
        if (empty($sku)) {
            $sku = trim((string) $product_xml->id);
        }

        $name = trim((string) $product_xml->name);
        if (empty($name)) {
            $name = 'Produkt ' . $sku;
        }

        // Sprawdź czy produkt istnieje
        $product_id = wc_get_product_id_by_sku($sku);
        $is_update = (bool) $product_id;

        if ($is_update) {
            $product = wc_get_product($product_id);
        } else {
            $product = new WC_Product();
        }

        // USTAWIANIE PODSTAWOWYCH DANYCH
        $product->set_name($name);
        $product->set_description((string) $product_xml->description);
        $product->set_short_description((string) $product_xml->short_description);
        $product->set_sku($sku);
        $product->set_status('publish');

        // CENY z walidacją
        $regular_price = trim((string) $product_xml->regular_price);
        if (!empty($regular_price)) {
            $regular_price = str_replace(',', '.', $regular_price);
            if (is_numeric($regular_price) && floatval($regular_price) > 0) {
                $product->set_regular_price($regular_price);
            }
        }

        $sale_price = trim((string) $product_xml->sale_price);
        if (!empty($sale_price)) {
            $sale_price = str_replace(',', '.', $sale_price);
            if (is_numeric($sale_price) && floatval($sale_price) > 0) {
                $product->set_sale_price($sale_price);
            }
        }

        // STOCK
        $stock_qty = trim((string) $product_xml->stock_quantity);
        if (!empty($stock_qty) && is_numeric($stock_qty)) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $stock_qty);
            $product->set_stock_status('instock');
        }

        // WYMIARY
        if (!empty((string) $product_xml->weight)) {
            $product->set_weight((string) $product_xml->weight);
        }
        if (!empty((string) $product_xml->length)) {
            $product->set_length((string) $product_xml->length);
        }
        if (!empty((string) $product_xml->width)) {
            $product->set_width((string) $product_xml->width);
        }
        if (!empty((string) $product_xml->height)) {
            $product->set_height((string) $product_xml->height);
        }

        // ZAPISZ PRODUKT
        $product_id = $product->save();

        if (!$product_id) {
            throw new Exception("Nie można zapisać produktu: {$name}");
        }

        // KATEGORIE
        if (isset($product_xml->categories)) {
            $categories_text = trim((string) $product_xml->categories);
            if (!empty($categories_text)) {
                $categories_text = html_entity_decode($categories_text, ENT_QUOTES, 'UTF-8');
                $category_ids = self::process_product_categories($categories_text);
                if (!empty($category_ids)) {
                    wp_set_object_terms($product_id, $category_ids, 'product_cat');
                }
            }
        }

        // ATRYBUTY
        if (isset($product_xml->attributes) && $product_xml->attributes->attribute) {
            $attributes = $product_xml->attributes->attribute;
            if (!is_array($attributes)) {
                $attributes = [$attributes];
            }

            $wc_attributes = [];
            foreach ($attributes as $attr) {
                $attr_name = trim((string) $attr->name);
                $attr_value = trim((string) $attr->value);

                if (!empty($attr_name) && !empty($attr_value)) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name($attr_name);
                    $attribute->set_options([$attr_value]);
                    $attribute->set_visible(true);
                    $attribute->set_variation(false);
                    $wc_attributes[] = $attribute;
                }
            }

            if (!empty($wc_attributes)) {
                $product = wc_get_product($product_id);
                $product->set_attributes($wc_attributes);
                $product->save();
            }
        }

        // OBRAZY (uproszczona wersja dla cron)
        if (isset($product_xml->images) && $product_xml->images->image) {
            $images = $product_xml->images->image;
            if (!is_array($images)) {
                $images = [$images];
            }

            // Importuj tylko pierwszy obraz jako główny (dla wydajności)
            if (!empty($images[0])) {
                $image_url = trim((string) $images[0]);
                if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    self::import_product_image($image_url, $product_id, true);
                }
            }
        }

        return true;
    }

    /**
     * Importuje pojedynczy produkt ANDA z kompleksowymi danymi
     */
    private static function import_anda_single_product($product_xml)
    {
        // SKU i nazwa produktu
        $sku = trim((string) $product_xml->sku);
        if (empty($sku)) {
            throw new Exception("Brak SKU dla produktu ANDA");
        }

        $name = trim((string) $product_xml->name);
        if (empty($name)) {
            $name = 'Produkt ANDA ' . $sku;
        }

        self::log("🔧 Importuję produkt ANDA: {$sku} - {$name}");

        // Sprawdź czy produkt istnieje
        $product_id = wc_get_product_id_by_sku($sku);
        $is_update = (bool) $product_id;

        if ($is_update) {
            $product = wc_get_product($product_id);
            self::log("📝 Aktualizuję istniejący produkt ANDA: {$sku}");
        } else {
            $product = new WC_Product();
            self::log("✨ Tworzę nowy produkt ANDA: {$sku}");
        }

        // === PODSTAWOWE DANE ===
        $product->set_name($name);
        $product->set_description((string) $product_xml->description);
        $product->set_short_description((string) $product_xml->short_description);
        $product->set_sku($sku);
        $product->set_status('publish');

        // === CENY z kompleksowych danych ANDA ===
        $regular_price = trim((string) $product_xml->price);
        if (!empty($regular_price)) {
            $regular_price = str_replace(',', '.', $regular_price);
            if (is_numeric($regular_price) && floatval($regular_price) > 0) {
                $product->set_regular_price($regular_price);
                self::log("💰 Ustawiam cenę ANDA: {$regular_price}");
            }
        }

        // Cena promocyjna jeśli dostępna
        $sale_price = trim((string) $product_xml->sale_price);
        if (!empty($sale_price)) {
            $sale_price = str_replace(',', '.', $sale_price);
            if (is_numeric($sale_price) && floatval($sale_price) > 0) {
                $product->set_sale_price($sale_price);
            }
        }

        // === STAN MAGAZYNOWY ===
        $stock_qty = trim((string) $product_xml->stock_quantity);
        if (!empty($stock_qty) && is_numeric($stock_qty)) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $stock_qty);
            $product->set_stock_status('instock');
        }

        // === WYMIARY I WAGA ===
        if (!empty((string) $product_xml->weight)) {
            $product->set_weight((string) $product_xml->weight);
        }
        if (!empty((string) $product_xml->dimensions->length)) {
            $product->set_length((string) $product_xml->dimensions->length);
        }
        if (!empty((string) $product_xml->dimensions->width)) {
            $product->set_width((string) $product_xml->dimensions->width);
        }
        if (!empty((string) $product_xml->dimensions->height)) {
            $product->set_height((string) $product_xml->dimensions->height);
        }

        // ZAPISZ PRODUKT
        $product_id = $product->save();

        if (!$product_id) {
            throw new Exception("Nie można zapisać produktu ANDA: {$name}");
        }

        // === KATEGORIE HIERARCHICZNE ===
        self::import_anda_categories($product_xml, $product_id);

        // === KOMPLETNE ATRYBUTY ===
        self::import_anda_attributes($product_xml, $product_id);

        // === TECHNOLOGIE DRUKU ===
        self::import_anda_printing_technologies($product_xml, $product_id);

        // === DANE ZNAKOWANIA (LABELING) ===
        self::import_anda_labeling_data($product_xml, $product_id);

        // === META DATA ANDA ===
        self::import_anda_meta_data($product_xml, $product_id);

        // === OBRAZY ===
        self::import_anda_images($product_xml, $product_id);

        self::log("✅ Pomyślnie zaimportowano produkt ANDA: {$sku}");
        return true;
    }

    /**
     * Importuje hierarchiczne kategorie ANDA
     */
    private static function import_anda_categories($product_xml, $product_id)
    {
        if (!isset($product_xml->categories) || !isset($product_xml->categories->category)) {
            return;
        }

        $categories = $product_xml->categories->category;
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $category_ids = [];

        foreach ($categories as $category_xml) {
            $category_path = trim((string) $category_xml->path);
            if (!empty($category_path)) {
                $category_id = self::create_hierarchical_category($category_path);
                if ($category_id) {
                    $category_ids[] = $category_id;
                }
            }
        }

        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
            self::log("📂 Przypisano " . count($category_ids) . " kategorii ANDA do produktu");
        }
    }

    /**
     * Tworzy hierarchiczną strukturę kategorii
     */
    private static function create_hierarchical_category($category_path)
    {
        $categories = explode(' > ', $category_path);
        $categories = array_map('trim', $categories);
        $categories = array_filter($categories);

        $parent_id = 0;
        $category_id = 0;

        foreach ($categories as $category_name) {
            $term = get_term_by('name', $category_name, 'product_cat');

            if (!$term) {
                $result = wp_insert_term($category_name, 'product_cat', ['parent' => $parent_id]);
                if (!is_wp_error($result)) {
                    $category_id = $result['term_id'];
                    $parent_id = $result['term_id'];
                }
            } else {
                $category_id = $term->term_id;
                $parent_id = $term->term_id;
            }
        }

        return $category_id;
    }

    /**
     * Importuje kompletne atrybuty ANDA
     */
    private static function import_anda_attributes($product_xml, $product_id)
    {
        if (!isset($product_xml->attributes) || !isset($product_xml->attributes->attribute)) {
            return;
        }

        $attributes = $product_xml->attributes->attribute;
        if (!is_array($attributes)) {
            $attributes = [$attributes];
        }

        $wc_attributes = [];
        $imported_count = 0;

        foreach ($attributes as $attr_xml) {
            $attr_name = trim((string) $attr_xml->name);
            $attr_value = trim((string) $attr_xml->value);

            if (!empty($attr_name) && !empty($attr_value)) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($attr_name);
                $attribute->set_options([$attr_value]);
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $wc_attributes[] = $attribute;
                $imported_count++;
            }
        }

        if (!empty($wc_attributes)) {
            $product = wc_get_product($product_id);
            $product->set_attributes($wc_attributes);
            $product->save();
            self::log("🏷️ Zaimportowano {$imported_count} atrybutów ANDA");
        }
    }

    /**
     * Importuje technologie druku ANDA
     */
    private static function import_anda_printing_technologies($product_xml, $product_id)
    {
        if (!isset($product_xml->printing_technologies) || !isset($product_xml->printing_technologies->technology)) {
            return;
        }

        $technologies = $product_xml->printing_technologies->technology;
        if (!is_array($technologies)) {
            $technologies = [$technologies];
        }

        $tech_data = [];
        $tech_names = [];

        foreach ($technologies as $tech_xml) {
            $tech_code = trim((string) $tech_xml->code);
            $tech_name = trim((string) $tech_xml->name);

            if (!empty($tech_code) && !empty($tech_name)) {
                $tech_names[] = $tech_name;

                // Zapisz cenniki jeśli dostępne
                if (isset($tech_xml->price_ranges) && isset($tech_xml->price_ranges->range)) {
                    $ranges = $tech_xml->price_ranges->range;
                    if (!is_array($ranges)) {
                        $ranges = [$ranges];
                    }

                    $price_ranges = [];
                    foreach ($ranges as $range_xml) {
                        $price_ranges[] = [
                            'colors' => (string) $range_xml->colors,
                            'qty_from' => (string) $range_xml->qty_from,
                            'qty_to' => (string) $range_xml->qty_to,
                            'unit_price' => (string) $range_xml->unit_price,
                            'setup_cost' => (string) $range_xml->setup_cost
                        ];
                    }

                    $tech_data[$tech_code] = [
                        'name' => $tech_name,
                        'price_ranges' => $price_ranges
                    ];
                }
            }
        }

        // Zapisz technologie jako meta data
        if (!empty($tech_data)) {
            update_post_meta($product_id, '_anda_printing_technologies', $tech_data);
        }

        // Zapisz nazwy technologii jako atrybut
        if (!empty($tech_names)) {
            update_post_meta($product_id, '_anda_available_printing_technologies', implode(', ', $tech_names));
            self::log("🖨️ Zaimportowano " . count($tech_names) . " technologii druku ANDA");
        }
    }

    /**
     * Importuje dane znakowania ANDA
     */
    private static function import_anda_labeling_data($product_xml, $product_id)
    {
        if (!isset($product_xml->labeling_info)) {
            return;
        }

        $labeling = $product_xml->labeling_info;
        $labeling_data = [];

        // Przejdź przez wszystkie elementy znakowania
        foreach ($labeling->children() as $key => $value) {
            $labeling_data[$key] = trim((string) $value);
        }

        if (!empty($labeling_data)) {
            update_post_meta($product_id, '_anda_labeling_data', $labeling_data);
            self::log("🏷️ Zaimportowano dane znakowania ANDA: " . count($labeling_data) . " elementów");
        }
    }

    /**
     * Importuje meta data ANDA
     */
    private static function import_anda_meta_data($product_xml, $product_id)
    {
        if (!isset($product_xml->meta_data) || !isset($product_xml->meta_data->meta)) {
            return;
        }

        $meta_elements = $product_xml->meta_data->meta;
        if (!is_array($meta_elements)) {
            $meta_elements = [$meta_elements];
        }

        $imported_count = 0;

        foreach ($meta_elements as $meta_xml) {
            $meta_key = trim((string) $meta_xml->key);
            $meta_value = trim((string) $meta_xml->value);

            if (!empty($meta_key) && !empty($meta_value)) {
                update_post_meta($product_id, $meta_key, $meta_value);
                $imported_count++;
            }
        }

        if ($imported_count > 0) {
            self::log("📊 Zaimportowano {$imported_count} meta danych ANDA");
        }
    }

    /**
     * Importuje obrazy ANDA
     */
    private static function import_anda_images($product_xml, $product_id)
    {
        if (!isset($product_xml->images) || !isset($product_xml->images->image)) {
            return;
        }

        $images = $product_xml->images->image;
        if (!is_array($images)) {
            $images = [$images];
        }

        $imported_count = 0;
        $gallery_ids = [];

        foreach ($images as $i => $image_xml) {
            $image_url = trim((string) $image_xml['src']);
            $image_type = trim((string) $image_xml['type']);

            if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $is_featured = ($image_type === 'primary' || $i === 0);

                $attachment_id = self::import_product_image($image_url, $product_id, $is_featured);
                if ($attachment_id && !$is_featured) {
                    $gallery_ids[] = $attachment_id;
                }

                if ($attachment_id) {
                    $imported_count++;
                }
            }
        }

        // Ustaw galerię zdjęć
        if (!empty($gallery_ids)) {
            $product = wc_get_product($product_id);
            $product->set_gallery_image_ids($gallery_ids);
            $product->save();
        }

        if ($imported_count > 0) {
            self::log("📷 Zaimportowano {$imported_count} zdjęć ANDA");
        }
    }

    /**
     * Przetwarza kategorie produktu
     */
    private static function process_product_categories($categories_text)
    {
        $categories = explode('>', $categories_text);
        $categories = array_map('trim', $categories);
        $categories = array_filter($categories);

        $category_ids = [];
        $parent_id = 0;

        foreach ($categories as $category_name) {
            $term = get_term_by('name', $category_name, 'product_cat');

            if (!$term) {
                $result = wp_insert_term($category_name, 'product_cat', ['parent' => $parent_id]);
                if (!is_wp_error($result)) {
                    $category_ids[] = $result['term_id'];
                    $parent_id = $result['term_id'];
                }
            } else {
                $category_ids[] = $term->term_id;
                $parent_id = $term->term_id;
            }
        }

        return $category_ids;
    }

    /**
     * Importuje obraz produktu (uproszczona wersja)
     */
    private static function import_product_image($image_url, $product_id, $is_featured = false)
    {
        // Sprawdź czy obraz już istnieje
        $existing_id = self::get_attachment_id_by_url($image_url);
        if ($existing_id) {
            if ($is_featured) {
                set_post_thumbnail($product_id, $existing_id);
            }
            return $existing_id;
        }

        // Pobierz obraz
        $image_data = wp_remote_get($image_url, ['timeout' => 30]);
        if (is_wp_error($image_data)) {
            return false;
        }

        $image_content = wp_remote_retrieve_body($image_data);
        if (empty($image_content)) {
            return false;
        }

        // Przygotuj nazwę pliku
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        if (empty($filename) || strpos($filename, '.') === false) {
            $filename = 'product-image-' . $product_id . '.jpg';
        }

        // Zapisz plik
        $upload = wp_upload_bits($filename, null, $image_content);
        if ($upload['error']) {
            return false;
        }

        // Utwórz załącznik
        $attachment = [
            'post_mime_type' => wp_check_filetype($upload['file'])['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $product_id
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
        if (!$attachment_id) {
            return false;
        }

        // Generuj metadane
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Ustaw jako główny obraz
        if ($is_featured) {
            set_post_thumbnail($product_id, $attachment_id);
        }

        return $attachment_id;
    }

    /**
     * Pobiera ID załącznika na podstawie URL
     */
    private static function get_attachment_id_by_url($url)
    {
        global $wpdb;

        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid='%s';",
            $url
        ));

        return !empty($attachment) ? $attachment[0] : false;
    }

    /**
     * Pobiera ustawienia cron
     */
    public static function get_settings()
    {
        $defaults = [
            'enabled' => false,
            'interval' => 'daily',
            'suppliers' => [
                ['slug' => 'malfini', 'name' => 'Malfini', 'enabled' => true],
                ['slug' => 'axpol', 'name' => 'Axpol', 'enabled' => false],
                ['slug' => 'macma', 'name' => 'Macma', 'enabled' => false],
                ['slug' => 'par', 'name' => 'Par', 'enabled' => false],
            ]
        ];

        return wp_parse_args(get_option(self::SETTINGS_OPTION, []), $defaults);
    }

    /**
     * Zapisuje ustawienia cron
     */
    public static function save_settings($settings)
    {
        update_option(self::SETTINGS_OPTION, $settings);

        // Zaktualizuj harmonogram
        self::unschedule_import();
        if ($settings['enabled']) {
            self::schedule_import();
        }
    }

    /**
     * Aktualizuje status importu
     */
    private static function update_status($status, $message = '')
    {
        $status_data = [
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'next_run' => wp_next_scheduled(self::CRON_HOOK) ? date('Y-m-d H:i:s', wp_next_scheduled(self::CRON_HOOK)) : null
        ];

        update_option(self::STATUS_OPTION, $status_data);
    }

    /**
     * Pobiera status importu
     */
    public static function get_status()
    {
        $defaults = [
            'status' => 'idle',
            'message' => 'Brak aktywności',
            'timestamp' => null,
            'next_run' => null
        ];

        return wp_parse_args(get_option(self::STATUS_OPTION, []), $defaults);
    }

    /**
     * Loguje wiadomość
     */
    private static function log($message, $level = 'info')
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::$level($message);
        } else {
            error_log("MHI Cron Import [{$level}]: {$message}");
        }
    }

    /**
     * Dodaje menu w panelu administracyjnym
     */
    public static function add_admin_menu()
    {
        add_submenu_page(
            'multi-wholesale-integration',
            'Automatyczny Import',
            'Auto Import',
            'manage_options',
            'mhi-cron-import',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Renderuje stronę administracyjną
     */
    public static function render_admin_page()
    {
        $settings = self::get_settings();
        $status = self::get_status();
        $next_run = wp_next_scheduled(self::CRON_HOOK);

        ?>
        <div class="wrap">
            <h1>🤖 Automatyczny Import Produktów</h1>

            <div class="notice notice-info">
                <p><strong>Status:</strong> <?php echo esc_html($status['message']); ?></p>
                <?php if ($next_run): ?>
                    <p><strong>Następny import:</strong> <?php echo date('Y-m-d H:i:s', $next_run); ?></p>
                <?php endif; ?>
            </div>

            <form method="post" action="" id="mhi-cron-settings-form">
                <?php wp_nonce_field('mhi_cron_settings', 'mhi_cron_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Włącz automatyczny import</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                Automatycznie importuj produkty
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Częstotliwość importu</th>
                        <td>
                            <select name="interval">
                                <option value="every_15_minutes" <?php selected($settings['interval'], 'every_15_minutes'); ?>>
                                    Co 15 minut</option>
                                <option value="every_30_minutes" <?php selected($settings['interval'], 'every_30_minutes'); ?>>
                                    Co 30 minut</option>
                                <option value="hourly" <?php selected($settings['interval'], 'hourly'); ?>>Co godzinę</option>
                                <option value="every_2_hours" <?php selected($settings['interval'], 'every_2_hours'); ?>>Co 2
                                    godziny</option>
                                <option value="every_6_hours" <?php selected($settings['interval'], 'every_6_hours'); ?>>Co 6
                                    godzin</option>
                                <option value="every_12_hours" <?php selected($settings['interval'], 'every_12_hours'); ?>>Co 12
                                    godzin</option>
                                <option value="daily" <?php selected($settings['interval'], 'daily'); ?>>Codziennie</option>
                                <option value="weekly" <?php selected($settings['interval'], 'weekly'); ?>>Co tydzień</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Hurtownie do importu</th>
                        <td>
                            <?php foreach ($settings['suppliers'] as $index => $supplier): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="suppliers[<?php echo $index; ?>][enabled]" value="1" <?php checked($supplier['enabled']); ?>>
                                    <?php echo esc_html($supplier['name']); ?>
                                    <input type="hidden" name="suppliers[<?php echo $index; ?>][slug]"
                                        value="<?php echo esc_attr($supplier['slug']); ?>">
                                    <input type="hidden" name="suppliers[<?php echo $index; ?>][name]"
                                        value="<?php echo esc_attr($supplier['name']); ?>">
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="save_settings" class="button-primary" value="Zapisz ustawienia">
                    <button type="button" id="run-manual-import" class="button">Uruchom import teraz</button>
                </p>
            </form>

            <div id="import-status" style="margin-top: 20px;"></div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Obsługa zapisywania ustawień
                $('#mhi-cron-settings-form').on('submit', function (e) {
                    e.preventDefault();

                    var formData = $(this).serialize();
                    formData += '&action=mhi_update_cron_settings';

                    $.post(ajaxurl, formData, function (response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });

                // Obsługa ręcznego importu
                $('#run-manual-import').on('click', function () {
                    $(this).prop('disabled', true).text('Uruchamianie...');

                    $.post(ajaxurl, {
                        action: 'mhi_run_manual_import',
                        nonce: '<?php echo wp_create_nonce('mhi_manual_import'); ?>'
                    }, function (response) {
                        if (response.success) {
                            $('#import-status').html('<div class="notice notice-success"><p>Import rozpoczęty!</p></div>');
                            // Sprawdzaj status co 5 sekund
                            var statusInterval = setInterval(function () {
                                $.post(ajaxurl, { action: 'mhi_get_import_status' }, function (statusResponse) {
                                    if (statusResponse.success) {
                                        var status = statusResponse.data;
                                        $('#import-status').html('<div class="notice notice-info"><p>' + status.message + '</p></div>');

                                        if (status.status === 'completed' || status.status === 'idle') {
                                            clearInterval(statusInterval);
                                            $('#run-manual-import').prop('disabled', false).text('Uruchom import teraz');
                                        }
                                    }
                                });
                            }, 5000);
                        } else {
                            alert('Błąd: ' + response.data);
                            $('#run-manual-import').prop('disabled', false).text('Uruchom import teraz');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX: Aktualizacja ustawień
     */
    public static function ajax_update_settings()
    {
        if (!wp_verify_nonce($_POST['mhi_cron_nonce'], 'mhi_cron_settings')) {
            wp_send_json_error('Nieprawidłowy nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $settings = [
            'enabled' => isset($_POST['enabled']),
            'interval' => sanitize_text_field($_POST['interval']),
            'suppliers' => []
        ];

        if (isset($_POST['suppliers']) && is_array($_POST['suppliers'])) {
            foreach ($_POST['suppliers'] as $supplier) {
                $settings['suppliers'][] = [
                    'slug' => sanitize_text_field($supplier['slug']),
                    'name' => sanitize_text_field($supplier['name']),
                    'enabled' => isset($supplier['enabled'])
                ];
            }
        }

        self::save_settings($settings);
        wp_send_json_success('Ustawienia zapisane');
    }

    /**
     * AJAX: Ręczny import
     */
    public static function ajax_run_manual_import()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'mhi_manual_import')) {
            wp_send_json_error('Nieprawidłowy nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        // Uruchom import w tle
        wp_schedule_single_event(time(), self::CRON_HOOK);
        wp_send_json_success('Import zaplanowany');
    }

    /**
     * AJAX: Status importu
     */
    public static function ajax_get_import_status()
    {
        wp_send_json_success(self::get_status());
    }
}
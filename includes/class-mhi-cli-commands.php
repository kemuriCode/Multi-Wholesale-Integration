<?php
/**
 * Komendy WP-CLI dla Multi Wholesale Integration
 *
 * @package MHI
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUndefinedConstantInspection
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Guard - sprawdź czy WP-CLI jest dostępne
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Klasa MHI_CLI_Commands
 * 
 * Obsługuje komendy WP-CLI dla importu produktów
 */
class MHI_CLI_Commands
{
    /**
     * Inicjalizacja komend CLI
     */
    public static function init()
    {
        // Sprawdź czy WP-CLI jest dostępne
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        /** @noinspection PhpUndefinedClassInspection */
        WP_CLI::add_command('mhi import', [__CLASS__, 'import_products']);
        /** @noinspection PhpUndefinedClassInspection */
        WP_CLI::add_command('mhi status', [__CLASS__, 'import_status']);
        /** @noinspection PhpUndefinedClassInspection */
        WP_CLI::add_command('mhi schedule', [__CLASS__, 'manage_schedule']);
    }

    /**
     * Importuje produkty z określonej hurtowni
     *
     * ## OPTIONS
     *
     * <supplier>
     * : Nazwa hurtowni (malfini, axpol, macma, par)
     *
     * [--dry-run]
     * : Uruchom w trybie testowym (bez zapisywania)
     *
     * [--limit=<number>]
     * : Ogranicz liczbę importowanych produktów
     *
     * [--images]
     * : Importuj również obrazy produktów
     *
     * ## EXAMPLES
     *
     *     wp mhi import malfini
     *     wp mhi import malfini --limit=100
     *     wp mhi import malfini --dry-run
     *     wp mhi import malfini --images
     *
     * @param array $args Argumenty pozycyjne
     * @param array $assoc_args Argumenty nazwane
     */
    public static function import_products($args, $assoc_args)
    {
        // Sprawdź WooCommerce
        if (!class_exists('WooCommerce')) {
            WP_CLI::error('WooCommerce nie jest aktywne!');
        }

        $supplier = $args[0] ?? '';
        $valid_suppliers = ['malfini', 'axpol', 'macma', 'par'];

        if (!in_array($supplier, $valid_suppliers)) {
            WP_CLI::error('Nieprawidłowy dostawca! Dostępne: ' . implode(', ', $valid_suppliers));
        }

        $dry_run = isset($assoc_args['dry-run']);
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0;
        $import_images = isset($assoc_args['images']);

        WP_CLI::line("🚀 Rozpoczynanie importu produktów: " . strtoupper($supplier));

        if ($dry_run) {
            WP_CLI::line("⚠️  TRYB TESTOWY - produkty nie będą zapisywane");
        }

        if ($limit > 0) {
            WP_CLI::line("📊 Limit produktów: {$limit}");
        }

        if ($import_images) {
            WP_CLI::line("🖼️  Import obrazów: WŁĄCZONY");
        }

        WP_CLI::line(str_repeat("=", 60));

        // Znajdź plik XML
        $upload_dir = wp_upload_dir();
        $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

        if (!file_exists($xml_file)) {
            WP_CLI::error("Plik XML nie istnieje: {$xml_file}");
        }

        WP_CLI::line("📄 Plik XML: " . basename($xml_file));
        WP_CLI::line("📏 Rozmiar: " . self::format_bytes(filesize($xml_file)));

        // Parsuj XML
        $xml = simplexml_load_file($xml_file);
        if (!$xml) {
            WP_CLI::error("Błąd parsowania XML!");
        }

        $products = $xml->children();
        $total = count($products);

        if ($limit > 0 && $limit < $total) {
            $total = $limit;
            WP_CLI::line("📊 Ograniczono do {$limit} produktów z {$total} dostępnych");
        }

        WP_CLI::line("✅ Znaleziono {$total} produktów do importu");
        WP_CLI::line(str_repeat("-", 60));

        if (!$dry_run) {
            // Zwiększ limity
            ini_set('memory_limit', '2048M');
            set_time_limit(0);

            // Wyłącz cache dla wydajności
            wp_defer_term_counting(true);
            wp_defer_comment_counting(true);
            wp_suspend_cache_invalidation(true);
        }

        // Statystyki
        $stats = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'images' => 0
        ];

        $start_time = microtime(true);
        $progress = WP_CLI\Utils\make_progress_bar('Importowanie produktów', $total);

        // GŁÓWNA PĘTLA IMPORTU
        $processed = 0;
        foreach ($products as $product_xml) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $processed++;

            try {
                $result = self::import_single_product($product_xml, $dry_run, $import_images);

                if ($result['success']) {
                    if ($result['created']) {
                        $stats['created']++;
                    } else {
                        $stats['updated']++;
                    }

                    if ($result['images_imported']) {
                        $stats['images'] += $result['images_imported'];
                    }
                } else {
                    $stats['failed']++;
                }

            } catch (Exception $e) {
                $stats['failed']++;
                WP_CLI::debug("Błąd importu produktu: " . $e->getMessage());
            }

            $progress->tick();
        }

        $progress->finish();

        if (!$dry_run) {
            // Przywróć cache
            wp_defer_term_counting(false);
            wp_defer_comment_counting(false);
            wp_suspend_cache_invalidation(false);
        }

        $elapsed = round(microtime(true) - $start_time, 2);

        // Podsumowanie
        WP_CLI::line(str_repeat("=", 60));
        WP_CLI::success("Import zakończony w {$elapsed}s");
        WP_CLI::line("📊 STATYSTYKI:");
        WP_CLI::line("  ➕ Utworzone: {$stats['created']}");
        WP_CLI::line("  📝 Zaktualizowane: {$stats['updated']}");
        WP_CLI::line("  ❌ Błędy: {$stats['failed']}");

        if ($import_images) {
            WP_CLI::line("  🖼️  Obrazy: {$stats['images']}");
        }

        WP_CLI::line("  📈 Łącznie: " . ($stats['created'] + $stats['updated']));
    }

    /**
     * Pokazuje status automatycznego importu
     *
     * ## EXAMPLES
     *
     *     wp mhi status
     *
     * @param array $args Argumenty pozycyjne
     * @param array $assoc_args Argumenty nazwane
     */
    public static function import_status($args, $assoc_args)
    {
        if (!class_exists('MHI_Cron_Importer')) {
            WP_CLI::error('Klasa MHI_Cron_Importer nie jest dostępna');
        }

        $settings = MHI_Cron_Importer::get_settings();
        $status = MHI_Cron_Importer::get_status();
        $next_run = wp_next_scheduled(MHI_Cron_Importer::CRON_HOOK);

        WP_CLI::line("🤖 STATUS AUTOMATYCZNEGO IMPORTU");
        WP_CLI::line(str_repeat("=", 50));

        WP_CLI::line("Status: " . ($settings['enabled'] ? '✅ WŁĄCZONY' : '❌ WYŁĄCZONY'));
        WP_CLI::line("Częstotliwość: " . $settings['interval']);

        if ($next_run) {
            WP_CLI::line("Następny import: " . date('Y-m-d H:i:s', $next_run));
        } else {
            WP_CLI::line("Następny import: Nie zaplanowany");
        }

        WP_CLI::line("Ostatni status: " . $status['message']);

        if ($status['timestamp']) {
            WP_CLI::line("Ostatnia aktywność: " . $status['timestamp']);
        }

        WP_CLI::line(str_repeat("-", 50));
        WP_CLI::line("HURTOWNIE:");

        foreach ($settings['suppliers'] as $supplier) {
            $status_icon = $supplier['enabled'] ? '✅' : '❌';
            WP_CLI::line("  {$status_icon} {$supplier['name']} ({$supplier['slug']})");
        }
    }

    /**
     * Zarządza harmonogramem automatycznego importu
     *
     * ## OPTIONS
     *
     * <action>
     * : Akcja do wykonania (enable, disable, run)
     *
     * [--interval=<interval>]
     * : Interwał dla enable (hourly, daily, weekly, etc.)
     *
     * ## EXAMPLES
     *
     *     wp mhi schedule enable --interval=daily
     *     wp mhi schedule disable
     *     wp mhi schedule run
     *
     * @param array $args Argumenty pozycyjne
     * @param array $assoc_args Argumenty nazwane
     */
    public static function manage_schedule($args, $assoc_args)
    {
        if (!class_exists('MHI_Cron_Importer')) {
            WP_CLI::error('Klasa MHI_Cron_Importer nie jest dostępna');
        }

        $action = $args[0] ?? '';
        $valid_actions = ['enable', 'disable', 'run'];

        if (!in_array($action, $valid_actions)) {
            WP_CLI::error('Nieprawidłowa akcja! Dostępne: ' . implode(', ', $valid_actions));
        }

        switch ($action) {
            case 'enable':
                $interval = $assoc_args['interval'] ?? 'daily';
                $settings = MHI_Cron_Importer::get_settings();
                $settings['enabled'] = true;
                $settings['interval'] = $interval;

                MHI_Cron_Importer::save_settings($settings);
                WP_CLI::success("Automatyczny import włączony z interwałem: {$interval}");
                break;

            case 'disable':
                $settings = MHI_Cron_Importer::get_settings();
                $settings['enabled'] = false;

                MHI_Cron_Importer::save_settings($settings);
                WP_CLI::success("Automatyczny import wyłączony");
                break;

            case 'run':
                WP_CLI::line("🚀 Uruchamianie ręcznego importu...");
                wp_schedule_single_event(time(), MHI_Cron_Importer::CRON_HOOK);
                WP_CLI::success("Import zaplanowany do natychmiastowego wykonania");
                break;
        }
    }

    /**
     * Importuje pojedynczy produkt
     */
    private static function import_single_product($product_xml, $dry_run = false, $import_images = false)
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
        $created = false;

        if ($dry_run) {
            return [
                'success' => true,
                'created' => !$is_update,
                'images_imported' => 0
            ];
        }

        if ($is_update) {
            $product = wc_get_product($product_id);
        } else {
            $product = new WC_Product();
            $created = true;
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

        $images_imported = 0;

        // OBRAZY
        if ($import_images && isset($product_xml->images) && $product_xml->images->image) {
            $images = $product_xml->images->image;
            if (!is_array($images)) {
                $images = [$images];
            }

            foreach ($images as $index => $image) {
                $image_url = trim((string) $image);
                if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $is_featured = ($index === 0);
                    $attachment_id = self::import_product_image($image_url, $product_id, $is_featured);
                    if ($attachment_id) {
                        $images_imported++;
                    }
                }
            }
        }

        return [
            'success' => true,
            'created' => $created,
            'images_imported' => $images_imported
        ];
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
     * Importuje obraz produktu
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
     * Formatuje rozmiar pliku
     */
    private static function format_bytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }
}
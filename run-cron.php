<?php
/**
 * 🚀 CLI RUNNER dla cronów importu
 * Uruchamianie cronów z linii poleceń bez przeglądarki
 * 
 * UŻYCIE:
 * php run-cron.php --supplier=malfini --stage=1 --batch=50
 * php run-cron.php --supplier=malfini --stage=all --batch=100
 * php run-cron.php --help
 */

declare(strict_types=1);

// Sprawdź czy uruchomione z CLI
if (php_sapi_name() !== 'cli') {
    die("Ten skrypt można uruchamiać tylko z linii poleceń!\n");
}

// Załaduj WordPress
$wp_load_path = dirname(__FILE__, 4) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Nie można znaleźć wp-load.php!\n");
}

require_once($wp_load_path);

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    die("WooCommerce nie jest aktywne!\n");
}

// Parsuj argumenty
$options = getopt('', [
    'supplier:',
    'stage:',
    'batch:',
    'offset:',
    'auto-continue',
    'max-products:',
    'help'
]);

// Pokaż pomoc
if (isset($options['help']) || empty($options)) {
    show_help();
    exit(0);
}

// Walidacja argumentów
if (!isset($options['supplier'])) {
    die("❌ Błąd: Brak parametru --supplier\n");
}

if (!isset($options['stage'])) {
    die("❌ Błąd: Brak parametru --stage\n");
}

$supplier = $options['supplier'];
$stage = $options['stage'];
$batch_size = isset($options['batch']) ? (int) $options['batch'] : 50;
$offset = isset($options['offset']) ? (int) $options['offset'] : 0;
$auto_continue = isset($options['auto-continue']);
$max_products = isset($options['max-products']) ? (int) $options['max-products'] : 0; // 0 = bez limitu

echo "🚀 ROZPOCZYNAM CRON IMPORT CLI\n";
echo "📦 Hurtownia: $supplier\n";
echo "🎯 Stage: $stage\n";
echo "📊 Batch: $batch_size\n";
echo "⚡ Offset: $offset\n";
if ($auto_continue) {
    echo "🔄 Auto-continue: WŁĄCZONY\n";
    if ($max_products > 0) {
        echo "🎯 Max produktów: $max_products\n";
    }
}
echo str_repeat("=", 50) . "\n";

// Sprawdź plik XML
$upload_dir = wp_upload_dir();
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

if (!file_exists($xml_file)) {
    die("❌ Plik XML nie istnieje: $xml_file\n");
}

$start_time = microtime(true);

// Uruchom odpowiedni stage lub wszystkie
if ($stage === 'all') {
    echo "🔄 Uruchamiam wszystkie 3 stage'y sekwencyjnie...\n\n";

    $results = [];
    for ($s = 1; $s <= 3; $s++) {
        echo "🎯 === STAGE $s ===\n";

        if ($auto_continue) {
            $stage_results = run_stage_with_auto_continue($supplier, $s, $batch_size, $xml_file, $max_products);
            $results[$s] = $stage_results;
        } else {
            $result = run_stage_cli($supplier, $s, $batch_size, $offset, $xml_file);
            $results[$s] = $result;
        }

        echo "✅ Stage $s: {$results[$s]['success']} sukces, {$results[$s]['failed']} błędów, {$results[$s]['skipped']} pominiętych\n\n";

        // Krótka przerwa między stage'ami
        sleep(2);
    }

    // Podsumowanie wszystkich stage'ów
    echo "🎉 PODSUMOWANIE WSZYSTKICH STAGE'ÓW:\n";
    $total_success = $total_failed = $total_skipped = 0;

    foreach ($results as $stage_num => $result) {
        echo "  Stage $stage_num: {$result['success']} ✅ | {$result['failed']} ❌ | {$result['skipped']} ⏭️\n";
        $total_success += $result['success'];
        $total_failed += $result['failed'];
        $total_skipped += $result['skipped'];
    }

    echo str_repeat("-", 50) . "\n";
    echo "📊 ŁĄCZNIE: $total_success ✅ | $total_failed ❌ | $total_skipped ⏭️\n";

} else {
    // Pojedynczy stage
    if (!in_array($stage, ['1', '2', '3'])) {
        die("❌ Stage musi być 1, 2, 3 lub 'all'\n");
    }

    $stage_num = (int) $stage;
    echo "🎯 Uruchamiam Stage $stage_num...\n\n";

    if ($auto_continue) {
        $result = run_stage_with_auto_continue($supplier, $stage_num, $batch_size, $xml_file, $max_products);
    } else {
        $result = run_stage_cli($supplier, $stage_num, $batch_size, $offset, $xml_file);
    }

    echo "\n🎉 STAGE $stage_num ZAKOŃCZONY!\n";
    echo "📊 Wyniki: {$result['success']} sukces, {$result['failed']} błędów, {$result['skipped']} pominiętych\n";
}

$duration = round(microtime(true) - $start_time, 2);
echo "⏱️ Czas wykonania: {$duration}s\n";

/**
 * Uruchamia stage z automatycznym kontynuowaniem batch'ów
 */
function run_stage_with_auto_continue($supplier, $stage, $batch_size, $xml_file, $max_products = 0)
{
    echo "🔄 Auto-continue: Uruchamiam wszystkie batch'e dla Stage $stage\n";

    // Załaduj XML żeby sprawdzić całkowitą liczbę produktów
    $xml = simplexml_load_file($xml_file);
    if (!$xml) {
        die("❌ Błąd parsowania XML!\n");
    }

    $total_products = count($xml->children());
    $processed_total = 0;
    $current_offset = 0;

    // Łączne statystyki
    $total_stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    echo "📊 Całkowicie produktów w XML: $total_products\n";

    // Sprawdź limit produktów
    $products_to_process = $max_products > 0 ? min($max_products, $total_products) : $total_products;
    echo "🎯 Produktów do przetworzenia: $products_to_process\n\n";

    $batch_number = 1;

    while ($current_offset < $products_to_process) {
        $remaining = $products_to_process - $current_offset;
        $current_batch_size = min($batch_size, $remaining);

        echo "🚀 === BATCH $batch_number (offset: $current_offset, size: $current_batch_size) ===\n";

        $batch_stats = run_stage_cli($supplier, $stage, $current_batch_size, $current_offset, $xml_file);

        // Dodaj do łącznych statystyk
        $total_stats['success'] += $batch_stats['success'];
        $total_stats['failed'] += $batch_stats['failed'];
        $total_stats['skipped'] += $batch_stats['skipped'];

        $processed_total += $current_batch_size;
        $current_offset += $current_batch_size;

        echo "✅ Batch $batch_number: {$batch_stats['success']} ✅ | {$batch_stats['failed']} ❌ | {$batch_stats['skipped']} ⏭️\n";
        echo "📊 Postęp: $processed_total/$products_to_process (" . round(($processed_total / $products_to_process) * 100, 1) . "%)\n\n";

        $batch_number++;

        // Przerwa między batch'ami
        if ($current_offset < $products_to_process) {
            echo "⏳ Przerwa 3 sekundy przed kolejnym batch'em...\n\n";
            sleep(3);
        }
    }

    echo "🎉 AUTO-CONTINUE ZAKOŃCZONY dla Stage $stage!\n";
    echo "📈 ŁĄCZNE STATYSTYKI: {$total_stats['success']} ✅ | {$total_stats['failed']} ❌ | {$total_stats['skipped']} ⏭️\n";
    echo "📊 Przetworzono: $processed_total/$total_products produktów\n\n";

    return $total_stats;
}

/**
 * Uruchamia pojedynczy stage z CLI
 */
function run_stage_cli($supplier, $stage, $batch_size, $offset, $xml_file)
{
    echo "📄 Ładowanie XML: " . basename($xml_file) . "\n";

    $xml = simplexml_load_file($xml_file);
    if (!$xml) {
        die("❌ Błąd parsowania XML!\n");
    }

    $products = $xml->children();
    $total = count($products);
    $end_offset = min($offset + $batch_size, $total);

    echo "📊 XML: $total produktów | Przetwarzanie: $offset - $end_offset\n";

    // Wyłącz cache
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);
    wp_suspend_cache_invalidation(true);

    $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    // Przetwarzaj produkty
    for ($i = $offset; $i < $end_offset; $i++) {
        if (!isset($products[$i]))
            break;

        $product_xml = $products[$i];
        $progress = $i - $offset + 1;

        $sku = trim((string) $product_xml->sku);
        if (empty($sku))
            $sku = trim((string) $product_xml->id);

        $name = trim((string) $product_xml->name);
        if (empty($name))
            $name = 'Produkt ' . $sku;

        echo "🔄 [$progress/$batch_size] $name (SKU: $sku) ... ";

        try {
            $result = false;

            if ($stage == 1) {
                $result = process_stage_1_cli($product_xml, $sku, $name, $supplier);
            } elseif ($stage == 2) {
                $result = process_stage_2_cli($product_xml, $sku, $name);
            } elseif ($stage == 3) {
                $result = process_stage_3_cli($product_xml, $sku, $name);
            }

            if ($result === 'skipped') {
                $stats['skipped']++;
                echo "⏭️ POMINIĘTO\n";
            } elseif ($result) {
                $stats['success']++;
                echo "✅ SUKCES\n";
            } else {
                $stats['failed']++;
                echo "❌ BŁĄD\n";
            }

        } catch (Exception $e) {
            $stats['failed']++;
            echo "💥 WYJĄTEK: " . $e->getMessage() . "\n";
        }

        // Krótka przerwa
        usleep(50000); // 0.05s
    }

    // Włącz cache
    wp_suspend_cache_invalidation(false);
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);

    return $stats;
}

/**
 * Pomocnicze funkcje stage'ów - uproszczone wersje bez logowania do przeglądarki
 */

function process_stage_1_cli($product_xml, $sku, $name, $supplier)
{
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id && get_post_meta($product_id, '_mhi_stage_1_done', true) === 'yes') {
        return 'skipped';
    }

    $is_update = (bool) $product_id;

    // Wykryj typ produktu
    $has_variations = false;
    if (isset($product_xml->type) && trim((string) $product_xml->type) === 'variable') {
        $has_variations = true;
    } elseif (isset($product_xml->attributes->attribute)) {
        foreach ($product_xml->attributes->attribute as $attr) {
            if (trim((string) $attr->variation) === 'yes') {
                $has_variations = true;
                break;
            }
        }
    }

    // Utwórz/aktualizuj produkt
    if ($is_update) {
        $product = wc_get_product($product_id);
        if ($has_variations && $product->get_type() !== 'variable') {
            wp_set_object_terms($product_id, 'variable', 'product_type');
            $product = wc_get_product($product_id);
        }
    } else {
        $product = $has_variations ? new WC_Product_Variable() : new WC_Product();
    }

    // Podstawowe dane
    $product->set_name($name);
    $product->set_description((string) $product_xml->description);
    $product->set_short_description((string) $product_xml->short_description);
    $product->set_sku($sku);
    $product->set_status('publish');

    // Ceny
    $regular_price = str_replace(',', '.', trim((string) $product_xml->regular_price));
    if (is_numeric($regular_price) && floatval($regular_price) > 0) {
        $product->set_regular_price($regular_price);
    }

    $sale_price = str_replace(',', '.', trim((string) $product_xml->sale_price));
    if (is_numeric($sale_price) && floatval($sale_price) > 0) {
        $product->set_sale_price($sale_price);
    }

    // Stock
    $stock_qty = trim((string) $product_xml->stock_quantity);
    if (is_numeric($stock_qty)) {
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int) $stock_qty);
        $product->set_stock_status('instock');
    }

    // Wymiary
    if (!empty((string) $product_xml->weight))
        $product->set_weight((string) $product_xml->weight);
    if (!empty((string) $product_xml->length))
        $product->set_length((string) $product_xml->length);
    if (!empty((string) $product_xml->width))
        $product->set_width((string) $product_xml->width);
    if (!empty((string) $product_xml->height))
        $product->set_height((string) $product_xml->height);

    $product_id = $product->save();
    if (!$product_id)
        return false;

    // Kategorie
    if (isset($product_xml->categories)) {
        $categories_text = html_entity_decode(trim((string) $product_xml->categories), ENT_QUOTES, 'UTF-8');
        if (!empty($categories_text)) {
            $category_ids = process_product_categories_cli($categories_text);
            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'product_cat');
            }
        }
    }

    // Marki
    $brand_name = find_brand_in_xml_cli($product_xml);
    if (!empty($brand_name)) {
        process_product_brand_cli($brand_name, $product_id);
    }

    // Oznacz Stage 1 jako ukończony
    update_post_meta($product_id, '_mhi_stage_1_done', 'yes');
    update_post_meta($product_id, '_mhi_supplier', $supplier);
    update_post_meta($product_id, '_mhi_imported', 'yes');

    return true;
}

function process_stage_2_cli($product_xml, $sku, $name)
{
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id)
        return false;

    if (get_post_meta($product_id, '_mhi_stage_1_done', true) !== 'yes') {
        return 'skipped';
    }

    if (get_post_meta($product_id, '_mhi_stage_2_done', true) === 'yes') {
        return 'skipped';
    }

    $product = wc_get_product($product_id);
    if (!$product)
        return false;

    // Przetwarzaj atrybuty (uproszczona wersja)
    if (isset($product_xml->attributes->attribute)) {
        $wc_attributes = [];
        $attributes_to_assign = [];

        foreach ($product_xml->attributes->attribute as $attribute_xml) {
            $attr_name = trim((string) $attribute_xml->name);
            $attr_value = trim((string) $attribute_xml->value);

            if (empty($attr_name) || empty($attr_value))
                continue;

            $values = array_map('trim', explode(',', $attr_value));
            $values = array_filter($values);
            if (empty($values))
                continue;

            // Utwórz atrybut globalny (skrócona wersja)
            $attr_slug = wc_sanitize_taxonomy_name($attr_name);
            $taxonomy = wc_attribute_taxonomy_name($attr_slug);
            $attribute_id = wc_attribute_taxonomy_id_by_name($attr_slug);

            if (!$attribute_id) {
                $attribute_id = wc_create_attribute([
                    'name' => $attr_name,
                    'slug' => $attr_slug,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false
                ]);

                if (is_wp_error($attribute_id))
                    continue;
            }

            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy($taxonomy, 'product', [
                    'hierarchical' => false,
                    'show_ui' => false,
                    'query_var' => true,
                    'rewrite' => false,
                    'public' => false,
                ]);
            }

            // Utwórz terminy
            $term_ids = [];
            foreach ($values as $value) {
                $term = get_term_by('name', $value, $taxonomy);
                if (!$term) {
                    $term = wp_insert_term($value, $taxonomy);
                    if (!is_wp_error($term)) {
                        $term_ids[] = $term['term_id'];
                    }
                } else {
                    $term_ids[] = $term->term_id;
                }
            }

            if (!empty($term_ids)) {
                $is_variation = trim((string) $attribute_xml->variation) === 'yes';

                $wc_attribute = new WC_Product_Attribute();
                $wc_attribute->set_id($attribute_id);
                $wc_attribute->set_name($taxonomy);
                $wc_attribute->set_options($term_ids);
                $wc_attribute->set_visible(true);
                $wc_attribute->set_variation($is_variation);
                $wc_attributes[] = $wc_attribute;

                $attributes_to_assign[] = [
                    'taxonomy' => $taxonomy,
                    'term_ids' => $term_ids
                ];
            }
        }

        if (!empty($wc_attributes)) {
            $product->set_attributes($wc_attributes);
            $product->save();

            // Przypisz terminy
            foreach ($attributes_to_assign as $attr_info) {
                wp_set_object_terms($product_id, $attr_info['term_ids'], $attr_info['taxonomy']);
            }
        }
    }

    update_post_meta($product_id, '_mhi_stage_2_done', 'yes');
    return true;
}

function process_stage_3_cli($product_xml, $sku, $name)
{
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id)
        return false;

    if (get_post_meta($product_id, '_mhi_stage_2_done', true) !== 'yes') {
        return 'skipped';
    }

    if (get_post_meta($product_id, '_mhi_stage_3_done', true) === 'yes') {
        return 'skipped';
    }

    // Przetwarzaj obrazy (bardzo uproszczona wersja)
    if (isset($product_xml->images->image)) {
        $images = $product_xml->images->image;

        // Konwertuj do tablicy
        if (is_object($images) && get_class($images) === 'SimpleXMLElement') {
            $images_array = [];
            foreach ($images as $image) {
                $images_array[] = $image;
            }
            if (empty($images_array)) {
                $images_array = [$images];
            }
            $images = $images_array;
        } elseif (!is_array($images)) {
            $images = [$images];
        }

        $imported_count = 0;
        foreach ($images as $index => $image) {
            $image_url = '';
            $attributes = $image->attributes();

            if (isset($attributes['src'])) {
                $image_url = trim((string) $attributes['src']);
            } elseif (isset($image->src)) {
                $image_url = trim((string) $image->src);
            } else {
                $image_url = trim((string) $image);
            }

            if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $is_featured = ($index === 0);
            if (import_product_image_cli($image_url, $product_id, $is_featured)) {
                $imported_count++;
            }
        }

        if ($imported_count === 0) {
            return false;
        }
    }

    update_post_meta($product_id, '_mhi_stage_3_done', 'yes');
    return true;
}

// Pomocnicze funkcje CLI
function process_product_categories_cli($categories_text)
{
    $category_ids = [];
    if (strpos($categories_text, '>') !== false) {
        $parts = array_map('trim', explode('>', $categories_text));
        $parent_id = 0;
        foreach ($parts as $part) {
            if (empty($part))
                continue;
            $term = get_term_by('name', $part, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($part, 'product_cat', ['parent' => $parent_id]);
                if (!is_wp_error($term)) {
                    $parent_id = $term['term_id'];
                    $category_ids[] = $parent_id;
                }
            } else {
                $parent_id = $term->term_id;
                $category_ids[] = $parent_id;
            }
        }
    } else {
        $term = get_term_by('name', $categories_text, 'product_cat');
        if (!$term) {
            $term = wp_insert_term($categories_text, 'product_cat');
            if (!is_wp_error($term)) {
                $category_ids[] = $term['term_id'];
            }
        } else {
            $category_ids[] = $term->term_id;
        }
    }
    return array_unique($category_ids);
}

function find_brand_in_xml_cli($product_xml)
{
    if (isset($product_xml->attributes->attribute)) {
        foreach ($product_xml->attributes->attribute as $attr) {
            $attr_name = strtolower(trim((string) $attr->name));
            if (in_array($attr_name, ['marka', 'brand', 'manufacturer', 'producent', 'firma'])) {
                return trim((string) $attr->value);
            }
        }
    }
    if (isset($product_xml->brand))
        return trim((string) $product_xml->brand);
    if (isset($product_xml->manufacturer))
        return trim((string) $product_xml->manufacturer);
    return '';
}

function process_product_brand_cli($brand_name, $product_id)
{
    $brand_taxonomies = ['product_brand', 'pwb-brand', 'yith_product_brand'];
    $brand_taxonomy = null;

    foreach ($brand_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $brand_taxonomy = $taxonomy;
            break;
        }
    }

    if (!$brand_taxonomy) {
        register_taxonomy('product_brand', 'product', [
            'label' => 'Marki',
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'marka'],
        ]);
        $brand_taxonomy = 'product_brand';
    }

    $term = get_term_by('name', $brand_name, $brand_taxonomy);
    if (!$term) {
        $term = wp_insert_term($brand_name, $brand_taxonomy);
        if (is_wp_error($term))
            return false;
        $term_id = $term['term_id'];
    } else {
        $term_id = $term->term_id;
    }

    wp_set_object_terms($product_id, [$term_id], $brand_taxonomy);
    return true;
}

function import_product_image_cli($image_url, $product_id, $is_featured = false)
{
    // Sprawdź duplikaty
    $existing = get_posts([
        'post_type' => 'attachment',
        'meta_query' => [['key' => '_mhi_source_url', 'value' => $image_url]],
        'posts_per_page' => 1
    ]);

    if ($existing) {
        $attach_id = $existing[0]->ID;
        if ($is_featured)
            set_post_thumbnail($product_id, $attach_id);
        return $attach_id;
    }

    // Pobierz obraz
    $response = wp_remote_get($image_url, [
        'timeout' => 30,
        'sslverify' => false,
        'user-agent' => 'Mozilla/5.0 (compatible; WordPressBot/1.0)'
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data))
        return false;

    // Zapisz obraz
    $upload_dir = wp_upload_dir();
    $filename = time() . '_' . sanitize_file_name(basename($image_url));
    $file_path = $upload_dir['path'] . '/' . $filename;

    if (!file_put_contents($file_path, $image_data)) {
        return false;
    }

    // Dodaj do biblioteki
    $filetype = wp_check_filetype($filename);
    $attachment = [
        'guid' => $upload_dir['url'] . '/' . $filename,
        'post_mime_type' => $filetype['type'],
        'post_title' => pathinfo($filename, PATHINFO_FILENAME),
        'post_content' => '',
        'post_status' => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $file_path, $product_id);
    if (!$attach_id) {
        @unlink($file_path);
        return false;
    }

    update_post_meta($attach_id, '_mhi_source_url', $image_url);

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $metadata = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $metadata);

    if ($is_featured) {
        set_post_thumbnail($product_id, $attach_id);
    }

    return $attach_id;
}

function show_help()
{
    echo "🚀 CLI RUNNER dla cronów importu produktów\n\n";
    echo "UŻYCIE:\n";
    echo "  php run-cron.php --supplier=HURTOWNIA --stage=NUMER [OPCJE]\n\n";
    echo "PARAMETRY:\n";
    echo "  --supplier        Nazwa hurtowni (np. malfini, axpol, macma)\n";
    echo "  --stage           Numer stage'u (1, 2, 3) lub 'all' dla wszystkich\n";
    echo "  --batch           Ilość produktów na raz (domyślnie: 50)\n";
    echo "  --offset          Rozpocznij od produktu o numerze (domyślnie: 0)\n";
    echo "  --auto-continue   🔄 Automatycznie kontynuuj kolejne batch'e\n";
    echo "  --max-products    🎯 Maksymalna liczba produktów (0 = bez limitu)\n";
    echo "  --help            Pokaż tę pomoc\n\n";
    echo "STAGE'Y:\n";
    echo "  1                 📦 Podstawowe dane (nazwa, ceny, kategorie, opisy)\n";
    echo "  2                 🏷️ Atrybuty i warianty produktów\n";
    echo "  3                 📷 Galeria obrazów z konwersją WebP\n";
    echo "  all               🚀 Wszystkie stage'y sekwencyjnie\n\n";
    echo "TRYBY:\n";
    echo "  PODSTAWOWY        Pojedynczy batch z określonym offset\n";
    echo "  AUTO-CONTINUE     Automatyczne przetwarzanie wszystkich produktów\n\n";
    echo "PRZYKŁADY:\n";
    echo "  # Podstawowy - pojedynczy batch\n";
    echo "  php run-cron.php --supplier=malfini --stage=1 --batch=100\n";
    echo "  php run-cron.php --supplier=axpol --stage=3 --batch=25 --offset=100\n\n";
    echo "  # Auto-continue - wszystkie produkty automatycznie\n";
    echo "  php run-cron.php --supplier=malfini --stage=all --auto-continue --batch=50\n";
    echo "  php run-cron.php --supplier=axpol --stage=2 --auto-continue --batch=100 --max-products=500\n\n";
}

?>
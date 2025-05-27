<?php
/**
 * WYDAJNY CRON IMPORTER PRODUKTÓW
 * Importuje produkty bezpośrednio przez cron bez interfejsu wizualnego
 * 
 * Sposób użycia: 
 * php /path/to/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini
 * 
 * Dostępne opcje:
 * - supplier=nazwa_hurtowni (wymagane)
 * - replace_images=1 (zastąp istniejące obrazy galerii przy aktualizacji)
 * - test_xml=1 (użyj test_gallery.xml zamiast głównego pliku)
 * - log_level=info|warning|error (poziom logowania, domyślnie: info)
 * - max_products=100 (limit produktów do przetworzenia, domyślnie: bez limitu)
 * 
 * Funkcjonalność:
 * ✅ Identyczne mapowanie pól jak w import.php
 * ✅ Pełna obsługa atrybutów, kategorii, marek
 * ✅ Kompletna obsługa galerii obrazów z WebP
 * ✅ Custom fields (meta_data)
 * ✅ Wydajne logowanie do pliku
 * ✅ Statystyki i raporty
 * ✅ Obsługa błędów i recovery
 */

declare(strict_types=1);

// Sprawdź czy uruchamiany z CLI lub przez cron
if (php_sapi_name() !== 'cli' && !defined('DOING_CRON')) {
    // Sprawdź klucz dostępu dla HTTP
    if (!isset($_GET['admin_key']) || $_GET['admin_key'] !== 'mhi_cron_access') {
        http_response_code(403);
        die('Brak uprawnień do importu cron!');
    }
}

// Zwiększ limity wykonania
ini_set('memory_limit', '2048M');
set_time_limit(0);
ignore_user_abort(true);

// Załaduj WordPress
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    die('WooCommerce nie jest aktywne!');
}

// Parsuj parametry z CLI lub HTTP
$params = [];
if (php_sapi_name() === 'cli') {
    // CLI - parsuj argumenty
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            list($key, $value) = explode('=', $arg, 2);
            $params[$key] = $value;
        }
    }
} else {
    // HTTP - użyj $_GET
    $params = $_GET;
}

// Sprawdź parametr supplier
if (!isset($params['supplier'])) {
    die('Brak parametru supplier! Użyj: supplier=malfini');
}

$supplier = sanitize_text_field($params['supplier']);
$replace_images = isset($params['replace_images']) ? (bool) $params['replace_images'] : false;
$test_xml = isset($params['test_xml']) ? (bool) $params['test_xml'] : false;
$log_level = isset($params['log_level']) ? $params['log_level'] : 'info';
$max_products = isset($params['max_products']) ? (int) $params['max_products'] : 0;

// Konfiguracja logowania
$log_levels = ['error' => 1, 'warning' => 2, 'info' => 3, 'debug' => 4];
$current_log_level = $log_levels[$log_level] ?? 3;

// Znajdź plik XML
$upload_dir = wp_upload_dir();
if ($test_xml) {
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/test_gallery.xml';
} else {
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';
}

if (!file_exists($xml_file)) {
    die('Plik XML nie istnieje: ' . $xml_file);
}

// Konfiguracja logów
$log_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/logs/';
if (!file_exists($log_dir)) {
    wp_mkdir_p($log_dir);
}
$log_file = $log_dir . 'cron_import_' . $supplier . '_' . date('Y-m-d') . '.log';

// Funkcja logowania
function cron_log($message, $type = 'info')
{
    global $log_file, $current_log_level, $log_levels;

    if ($log_levels[$type] <= $current_log_level) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Wyświetl także w CLI
        if (php_sapi_name() === 'cli') {
            echo $log_entry;
        }
    }
}

// ROZPOCZNIJ IMPORT
$start_time = microtime(true);
cron_log("🚀 ROZPOCZĘCIE IMPORTU CRON - Dostawca: {$supplier}", 'info');
cron_log("📄 Plik XML: " . basename($xml_file), 'info');
cron_log("⚙️ Parametry: replace_images={$replace_images}, test_xml={$test_xml}, max_products={$max_products}", 'info');

// Parsuj XML
$xml = simplexml_load_file($xml_file);
if (!$xml) {
    cron_log("❌ Błąd parsowania XML!", 'error');
    exit(1);
}

$products = $xml->children();
$total = count($products);

// Zastosuj limit jeśli ustawiony
if ($max_products > 0 && $total > $max_products) {
    $products = array_slice(iterator_to_array($products), 0, $max_products);
    $total = $max_products;
    cron_log("⚠️ Zastosowano limit: {$max_products} produktów z {$total}", 'warning');
}

cron_log("✅ Znaleziono {$total} produktów do importu", 'info');

// Wyłącz cache dla wydajności
wp_defer_term_counting(true);
wp_defer_comment_counting(true);
wp_suspend_cache_invalidation(true);

// Statystyki
$stats = [
    'total' => $total,
    'created' => 0,
    'updated' => 0,
    'failed' => 0,
    'images' => 0,
    'start_time' => $start_time
];

// GŁÓWNA PĘTLA IMPORTU
$processed = 0;
foreach ($products as $product_xml) {
    $processed++;

    // SKU i nazwa produktu
    $sku = trim((string) $product_xml->sku);
    if (empty($sku))
        $sku = trim((string) $product_xml->id);

    $name = trim((string) $product_xml->name);
    if (empty($name))
        $name = 'Produkt ' . $sku;

    cron_log("🔄 [{$processed}/{$total}] Przetwarzanie: {$name} (SKU: {$sku})", 'info');

    try {
        // Sprawdź czy produkt istnieje
        $product_id = wc_get_product_id_by_sku($sku);
        $is_update = (bool) $product_id;

        if ($is_update) {
            $product = wc_get_product($product_id);
            cron_log("📝 Aktualizacja istniejącego produktu ID: {$product_id}", 'debug');
        } else {
            $product = new WC_Product();
            cron_log("➕ Tworzenie nowego produktu", 'debug');
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
                cron_log("💰 Cena: {$regular_price} PLN", 'debug');
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
            cron_log("📦 Stan: {$stock_qty} szt.", 'debug');
        }

        // WYMIARY
        if (!empty((string) $product_xml->weight))
            $product->set_weight((string) $product_xml->weight);
        if (!empty((string) $product_xml->length))
            $product->set_length((string) $product_xml->length);
        if (!empty((string) $product_xml->width))
            $product->set_width((string) $product_xml->width);
        if (!empty((string) $product_xml->height))
            $product->set_height((string) $product_xml->height);

        // ATRYBUTY - identyczna logika jak w import.php
        $product_attributes = [];
        $wc_attributes = [];
        $attributes_to_assign = [];

        if (isset($product_xml->attributes) && isset($product_xml->attributes->attribute)) {
            cron_log("🏷️ Przetwarzam atrybuty produktu...", 'debug');

            $attributes_processed = 0;
            foreach ($product_xml->attributes->attribute as $attribute_xml) {
                $attr_name = trim((string) $attribute_xml->name);
                $attr_value = trim((string) $attribute_xml->value);

                if (empty($attr_name) || empty($attr_value))
                    continue;

                $values = array_map('trim', explode(',', $attr_value));
                $values = array_filter($values);

                if (empty($values))
                    continue;

                cron_log("🔹 Atrybut: {$attr_name} = " . implode(', ', $values), 'debug');

                $product_attributes[] = ['name' => $attr_name, 'value' => implode(', ', $values)];

                $attr_slug = wc_sanitize_taxonomy_name($attr_name);
                $taxonomy = wc_attribute_taxonomy_name($attr_slug);

                $attribute_id = wc_attribute_taxonomy_id_by_name($attr_slug);

                if (!$attribute_id) {
                    $attribute_id = wc_create_attribute(array(
                        'name' => $attr_name,
                        'slug' => $attr_slug,
                        'type' => 'select',
                        'order_by' => 'menu_order',
                        'has_archives' => false
                    ));

                    if (!is_wp_error($attribute_id)) {
                        cron_log("✅ Utworzono atrybut globalny: {$attr_name} (ID: {$attribute_id})", 'debug');
                        delete_transient('wc_attribute_taxonomies');
                        if (function_exists('wc_create_attribute_taxonomies')) {
                            wc_create_attribute_taxonomies();
                        }
                    } else {
                        cron_log("❌ Błąd tworzenia atrybutu: {$attr_name}", 'error');
                        continue;
                    }
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

                $term_ids = array();
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
                    $wc_attribute = new WC_Product_Attribute();
                    $wc_attribute->set_id($attribute_id);
                    $wc_attribute->set_name($taxonomy);
                    $wc_attribute->set_options($term_ids);
                    $wc_attribute->set_visible(true);
                    $wc_attribute->set_variation(false);
                    $wc_attributes[] = $wc_attribute;

                    $attributes_to_assign[] = [
                        'taxonomy' => $taxonomy,
                        'term_ids' => $term_ids,
                        'name' => $attr_name
                    ];

                    $attributes_processed++;
                }
            }

            if ($attributes_processed > 0) {
                cron_log("✅ Przetworzono {$attributes_processed} atrybutów", 'debug');
            }
        }

        // Ustaw atrybuty na produkcie
        if (!empty($wc_attributes)) {
            $product->set_attributes($wc_attributes);
        }

        // ZAPISZ PRODUKT
        $saved_product_id = $product->save();

        if (!$saved_product_id) {
            throw new Exception("Nie można zapisać produktu");
        }

        $final_product_id = $is_update ? $product_id : $saved_product_id;
        if (!$is_update)
            $product_id = $saved_product_id;

        // PRZYPISZ TERMINY ATRYBUTÓW
        if (!empty($attributes_to_assign)) {
            foreach ($attributes_to_assign as $attr_info) {
                if (taxonomy_exists($attr_info['taxonomy'])) {
                    $result = wp_set_object_terms($final_product_id, $attr_info['term_ids'], $attr_info['taxonomy']);
                    if (is_wp_error($result)) {
                        cron_log("❌ Błąd przypisania atrybutu {$attr_info['name']}: " . $result->get_error_message(), 'error');
                    }
                }
            }
        }

        // KATEGORIE
        if (isset($product_xml->categories)) {
            $categories_text = trim((string) $product_xml->categories);
            if (!empty($categories_text)) {
                $categories_text = html_entity_decode($categories_text, ENT_QUOTES, 'UTF-8');
                cron_log("📁 Kategorie: {$categories_text}", 'debug');

                $category_ids = cron_process_product_categories($categories_text);
                if (!empty($category_ids)) {
                    wp_set_object_terms($final_product_id, $category_ids, 'product_cat');
                    cron_log("✅ Przypisano " . count($category_ids) . " kategorii", 'debug');
                }
            }
        }

        // MARKI
        $brand_name = '';

        if (isset($product_xml->attributes) && isset($product_xml->attributes->attribute)) {
            foreach ($product_xml->attributes->attribute as $attribute_xml) {
                $attr_name = trim((string) $attribute_xml->name);
                $attr_value = trim((string) $attribute_xml->value);

                $brand_attribute_names = ['marka', 'brand', 'manufacturer', 'producent', 'firma'];

                if (in_array(strtolower($attr_name), $brand_attribute_names) && !empty($attr_value)) {
                    $brand_name = $attr_value;
                    break;
                }
            }
        }

        if (empty($brand_name)) {
            if (isset($product_xml->brand) && !empty(trim((string) $product_xml->brand))) {
                $brand_name = trim((string) $product_xml->brand);
            } elseif (isset($product_xml->manufacturer) && !empty(trim((string) $product_xml->manufacturer))) {
                $brand_name = trim((string) $product_xml->manufacturer);
            }
        }

        if (!empty($brand_name)) {
            $brand_result = cron_process_product_brand($brand_name, $final_product_id);
            if ($brand_result['success']) {
                cron_log("✅ " . $brand_result['message'], 'debug');
            } else {
                cron_log("⚠️ " . $brand_result['message'], 'warning');
            }
        }

        // OBRAZY
        if (isset($product_xml->images) && $product_xml->images->image) {
            $images = $product_xml->images->image;

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

            cron_log("📷 Znaleziono " . count($images) . " obrazków w XML", 'debug');

            if ($is_update && $replace_images) {
                cron_log("🧹 Aktualizacja: Czyszczenie starej galerii...", 'debug');
                $clean_result = cron_clean_product_gallery($final_product_id, false);
                if ($clean_result['removed_count'] > 0) {
                    cron_log("✅ Usunięto " . $clean_result['removed_count'] . " starych obrazów galerii", 'debug');
                }
            }

            $gallery_result = cron_import_product_gallery($images, $final_product_id);

            if ($gallery_result['success']) {
                $stats['images'] += $gallery_result['imported_count'];
                cron_log("🖼️ Galeria produktu: " . $gallery_result['message'], 'debug');
            } else {
                cron_log("❌ Błąd galerii: " . $gallery_result['message'], 'error');
            }
        }

        // CUSTOM FIELDS (META_DATA)
        if (isset($product_xml->meta_data) && $product_xml->meta_data->meta) {
            $meta_count = 0;
            foreach ($product_xml->meta_data->meta as $meta_xml) {
                $meta_key = trim((string) $meta_xml->key);
                $meta_value = trim((string) $meta_xml->value);

                if (empty($meta_key))
                    continue;

                update_post_meta($final_product_id, $meta_key, $meta_value);
                $meta_count++;
            }

            if ($meta_count > 0) {
                cron_log("✅ Dodano {$meta_count} custom fields", 'debug');
            }
        }

        // Oznacz jako importowany
        update_post_meta($final_product_id, '_mhi_imported', 'yes');
        update_post_meta($final_product_id, '_mhi_supplier', $supplier);
        update_post_meta($final_product_id, '_mhi_import_date', current_time('mysql'));

        // Statystyki
        if ($is_update) {
            $stats['updated']++;
            cron_log("✅ Zaktualizowano produkt ID: {$final_product_id}", 'info');
        } else {
            $stats['created']++;
            cron_log("✅ Utworzono produkt ID: {$final_product_id}", 'info');
        }

    } catch (Exception $e) {
        $stats['failed']++;
        cron_log("❌ Błąd: " . $e->getMessage(), 'error');
    }

    // Krótka przerwa żeby nie przeciążyć serwera
    if ($processed % 10 == 0) {
        usleep(100000); // 0.1 sekundy co 10 produktów
    }
}

// Włącz z powrotem cache
wp_suspend_cache_invalidation(false);
wp_defer_term_counting(false);
wp_defer_comment_counting(false);

$end_time = microtime(true);
$duration = round($end_time - $start_time, 2);

// PODSUMOWANIE
cron_log("🎉 IMPORT ZAKOŃCZONY!", 'info');
cron_log("⏱️ Czas: {$duration} sekund", 'info');
cron_log("📊 Utworzono: {$stats['created']}, Zaktualizowano: {$stats['updated']}, Błędów: {$stats['failed']}, Obrazów: {$stats['images']}", 'info');

// Zapisz statystyki do meta
$stats_meta = [
    'supplier' => $supplier,
    'total_products' => $stats['total'],
    'created' => $stats['created'],
    'updated' => $stats['updated'],
    'failed' => $stats['failed'],
    'images' => $stats['images'],
    'duration' => $duration,
    'timestamp' => current_time('mysql'),
    'xml_file' => basename($xml_file),
    'log_file' => basename($log_file)
];

update_option('mhi_last_cron_import_' . $supplier, $stats_meta);

// Wynik dla HTTP
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Import zakończony pomyślnie',
        'stats' => $stats_meta
    ]);
}

exit($stats['failed'] > 0 ? 1 : 0);

// ============================================================================
// FUNKCJE POMOCNICZE - identyczne z import.php ale bez logowania wizualnego
// ============================================================================

/**
 * Przetwarza kategorie produktu
 */
function cron_process_product_categories($categories_text)
{
    $category_ids = [];

    if (strpos($categories_text, '>') !== false) {
        // Hierarchia kategorii
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
                    cron_log("➕ Utworzono kategorię: {$part} (ID: {$parent_id})", 'debug');
                }
            } else {
                $parent_id = $term->term_id;
                $category_ids[] = $parent_id;
                cron_log("✓ Znaleziono kategorię: {$part} (ID: {$parent_id})", 'debug');
            }
        }
    } else {
        // Pojedyncza kategoria
        $term = get_term_by('name', $categories_text, 'product_cat');
        if (!$term) {
            $term = wp_insert_term($categories_text, 'product_cat');
            if (!is_wp_error($term)) {
                $category_ids[] = $term['term_id'];
                cron_log("➕ Utworzono kategorię: {$categories_text} (ID: {$term['term_id']})", 'debug');
            }
        } else {
            $category_ids[] = $term->term_id;
            cron_log("✓ Znaleziono kategorię: {$categories_text} (ID: {$term->term_id})", 'debug');
        }
    }

    return array_unique($category_ids);
}

/**
 * Przetwarza markę produktu
 */
function cron_process_product_brand($brand_name, $product_id)
{
    $possible_brand_taxonomies = [
        'product_brand',
        'pwb-brand',
        'yith_product_brand',
        'product_brands',
        'brands',
        'pa_brand',
        'pa_marka'
    ];

    $brand_taxonomy = null;

    foreach ($possible_brand_taxonomies as $taxonomy) {
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
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'marka'],
            'query_var' => true,
        ]);

        $brand_taxonomy = 'product_brand';
        cron_log("✅ Utworzono taksonomię marek: product_brand", 'debug');
    }

    $existing_term = get_term_by('name', $brand_name, $brand_taxonomy);

    if (!$existing_term) {
        $term_result = wp_insert_term($brand_name, $brand_taxonomy, [
            'description' => "Marka: {$brand_name}",
            'slug' => sanitize_title($brand_name)
        ]);

        if (is_wp_error($term_result)) {
            return [
                'success' => false,
                'message' => "Błąd tworzenia marki {$brand_name}: " . $term_result->get_error_message(),
                'taxonomy' => $brand_taxonomy
            ];
        }

        $brand_term_id = $term_result['term_id'];
        cron_log("➕ Utworzono markę: {$brand_name} (ID: {$brand_term_id})", 'debug');
    } else {
        $brand_term_id = $existing_term->term_id;
        cron_log("✓ Marka istnieje: {$brand_name} (ID: {$brand_term_id})", 'debug');
    }

    $assign_result = wp_set_object_terms($product_id, [$brand_term_id], $brand_taxonomy);

    if (is_wp_error($assign_result)) {
        return [
            'success' => false,
            'message' => "Błąd przypisania marki {$brand_name}: " . $assign_result->get_error_message(),
            'taxonomy' => $brand_taxonomy
        ];
    }

    return [
        'success' => true,
        'message' => "Przypisano markę: {$brand_name} (taksonomia: {$brand_taxonomy})",
        'taxonomy' => $brand_taxonomy,
        'term_id' => $brand_term_id,
        'brand_name' => $brand_name
    ];
}

/**
 * Czyści starą galerię produktu
 */
function cron_clean_product_gallery($product_id, $remove_featured = false)
{
    cron_log("🧹 Czyszczenie galerii produktu ID: {$product_id}", 'debug');

    $removed_count = 0;
    $errors = [];

    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    if (!empty($gallery_ids)) {
        $gallery_ids = explode(',', $gallery_ids);
        $gallery_ids = array_filter($gallery_ids);

        foreach ($gallery_ids as $attachment_id) {
            if (wp_delete_attachment($attachment_id, true)) {
                $removed_count++;
                cron_log("🗑️ Usunięto obraz galerii ID: {$attachment_id}", 'debug');
            } else {
                $errors[] = $attachment_id;
                cron_log("❌ Nie można usunąć obrazu galerii ID: {$attachment_id}", 'warning');
            }
        }

        delete_post_meta($product_id, '_product_image_gallery');
    }

    if ($remove_featured) {
        $featured_id = get_post_thumbnail_id($product_id);
        if ($featured_id) {
            if (wp_delete_attachment($featured_id, true)) {
                delete_post_thumbnail($product_id);
                $removed_count++;
                cron_log("🗑️ Usunięto główny obraz ID: {$featured_id}", 'debug');
            } else {
                $errors[] = $featured_id;
                cron_log("❌ Nie można usunąć głównego obrazu ID: {$featured_id}", 'warning');
            }
        }
    }

    return [
        'removed_count' => $removed_count,
        'errors' => $errors
    ];
}

/**
 * Importuje galerię obrazów dla produktu
 */
function cron_import_product_gallery($images, $product_id)
{
    cron_log("🎨 Rozpoczęcie importu galerii dla produktu ID: {$product_id}", 'debug');

    $product = wc_get_product($product_id);
    if (!$product) {
        cron_log("❌ Nie można załadować produktu ID: {$product_id}", 'error');
        return [
            'success' => false,
            'message' => "Produkt nie istnieje",
            'imported_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0
        ];
    }

    $image_ids = [];
    $imported_count = 0;
    $failed_count = 0;
    $skipped_count = 0;

    foreach ($images as $index => $image) {
        $image_url = '';
        $img_number = $index + 1;

        // Sprawdź różne formaty XML dla obrazów
        $attributes = $image->attributes();

        if (isset($attributes['src'])) {
            $image_url = trim((string) $attributes['src']);
        } elseif (isset($image->src)) {
            $image_url = trim((string) $image->src);
        } else {
            $image_url = trim((string) $image);
        }

        // Walidacja URL
        if (empty($image_url)) {
            cron_log("⚠️ Obraz {$img_number}: Pusty URL - pomijam", 'warning');
            $skipped_count++;
            continue;
        }

        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            cron_log("⚠️ Obraz {$img_number}: Nieprawidłowy URL ({$image_url}) - pomijam", 'warning');
            $skipped_count++;
            continue;
        }

        // Określ czy to główny obraz (pierwszy)
        $is_featured = ($index === 0);
        $image_type = $is_featured ? "GŁÓWNY" : "GALERIA";

        cron_log("📥 Obraz {$img_number} ({$image_type}): Pobieram {$image_url}", 'debug');

        // Importuj obraz
        $attachment_id = cron_import_product_image($image_url, $product_id, $is_featured);

        if ($attachment_id) {
            $image_ids[] = $attachment_id;
            $imported_count++;
            cron_log("✅ Obraz {$img_number} ({$image_type}) dodany - ID: {$attachment_id}", 'debug');
        } else {
            $failed_count++;
            cron_log("❌ Obraz {$img_number} ({$image_type}): Błąd importu", 'error');
        }
    }

    // Konfiguracja galerii WooCommerce
    if ($imported_count > 0) {
        $featured_id = get_post_thumbnail_id($product_id);

        // Przygotuj galerię (wszystkie zaimportowane obrazy oprócz głównego)
        $new_gallery_ids = array_filter($image_ids, function ($id) use ($featured_id) {
            return $id != $featured_id;
        });

        // Sprawdź czy istnieją już obrazy w galerii (przy aktualizacji)
        $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $existing_gallery_ids = [];

        if (!empty($existing_gallery)) {
            $existing_gallery_ids = explode(',', $existing_gallery);
            $existing_gallery_ids = array_filter($existing_gallery_ids);
        }

        // Określ finalną galerię
        $final_gallery_ids = [];

        if (!empty($existing_gallery_ids) && !empty($new_gallery_ids)) {
            $final_gallery_ids = array_unique(array_merge($existing_gallery_ids, $new_gallery_ids));
        } elseif (!empty($new_gallery_ids)) {
            $final_gallery_ids = $new_gallery_ids;
        } elseif (!empty($existing_gallery_ids)) {
            $final_gallery_ids = $existing_gallery_ids;
        }

        // Ustaw galerię w WooCommerce
        if (!empty($final_gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $final_gallery_ids));

            $product_fresh = wc_get_product($product_id);
            if ($product_fresh) {
                wp_cache_delete($product_id, 'posts');
                wp_cache_delete($product_id, 'post_meta');

                $product_fresh->set_gallery_image_ids($final_gallery_ids);
                $save_result = $product_fresh->save();

                if ($save_result) {
                    cron_log("🖼️ Galeria WooCommerce: Ustawiono " . count($final_gallery_ids) . " obrazów w galerii", 'debug');
                } else {
                    cron_log("❌ Nie udało się zapisać galerii produktu", 'error');
                }
            }

            $message = "Główny obraz + galeria z " . count($final_gallery_ids) . " obrazami (zaimportowano: {$imported_count})";
        } else {
            $message = "Tylko główny obraz (zaimportowano: {$imported_count})";
        }

        // Dodatkowe meta dla śledzenia
        update_post_meta($product_id, '_mhi_gallery_count', count($final_gallery_ids ?? []));
        update_post_meta($product_id, '_mhi_total_images', $imported_count);
        update_post_meta($product_id, '_mhi_gallery_updated', current_time('mysql'));

        return [
            'success' => true,
            'message' => $message,
            'imported_count' => $imported_count,
            'failed_count' => $failed_count,
            'skipped_count' => $skipped_count,
            'featured_id' => $featured_id,
            'gallery_ids' => $final_gallery_ids ?? [],
            'total_images' => count($image_ids)
        ];
    } else {
        return [
            'success' => false,
            'message' => "Nie udało się zaimportować żadnego obrazu ({$failed_count} błędów, {$skipped_count} pominiętych)",
            'imported_count' => 0,
            'failed_count' => $failed_count,
            'skipped_count' => $skipped_count
        ];
    }
}

/**
 * Importuje pojedynczy obraz produktu
 */
function cron_import_product_image($image_url, $product_id, $is_featured = false)
{
    cron_log("🚀 Import obrazu - URL: " . $image_url, 'debug');

    // Sprawdź czy obraz już istnieje
    $existing = get_posts([
        'post_type' => 'attachment',
        'meta_query' => [
            [
                'key' => '_mhi_source_url',
                'value' => $image_url
            ]
        ],
        'posts_per_page' => 1
    ]);

    if ($existing) {
        $attach_id = $existing[0]->ID;
        if ($is_featured) {
            set_post_thumbnail($product_id, $attach_id);
        }
        cron_log("♻️ Użyto istniejący obraz (ID: {$attach_id})", 'debug');
        return $attach_id;
    }

    // Generuj losową datę z ostatnich 18 miesięcy
    $months_back = rand(1, 18);
    $random_timestamp = strtotime("-{$months_back} months");

    $year = (int) date('Y', $random_timestamp);
    $month = (int) date('m', $random_timestamp);
    $day = rand(1, 28);
    $hour = rand(8, 18);
    $minute = rand(0, 59);
    $second = rand(0, 59);

    $final_timestamp = mktime($hour, $minute, $second, $month, $day, $year);

    $upload_dir = wp_upload_dir(date('Y/m', $final_timestamp));

    if (isset($upload_dir['error']) && $upload_dir['error']) {
        cron_log("❌ Błąd wp_upload_dir: " . $upload_dir['error'], 'error');
        return false;
    }

    if (!file_exists($upload_dir['path'])) {
        $created = wp_mkdir_p($upload_dir['path']);
        if (!$created) {
            cron_log("❌ Nie udało się utworzyć folderu: " . $upload_dir['path'], 'error');
            return false;
        }
    }

    if (!is_writable($upload_dir['path'])) {
        cron_log("❌ Brak praw zapisu do folderu: " . $upload_dir['path'], 'error');
        return false;
    }

    // Pobierz obraz
    $response = wp_remote_get($image_url, [
        'timeout' => 60,
        'sslverify' => false,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'headers' => [
            'Accept' => 'image/*,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate'
        ]
    ]);

    if (is_wp_error($response)) {
        cron_log("❌ Błąd pobierania obrazu: " . $response->get_error_message(), 'error');
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        cron_log("❌ HTTP błąd {$response_code} dla obrazu: {$image_url}", 'error');
        return false;
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data)) {
        cron_log("❌ Puste dane obrazu z URL: {$image_url}", 'error');
        return false;
    }

    // Sprawdź czy dane to rzeczywiście obraz
    $image_info = @getimagesizefromstring($image_data);
    if (!$image_info) {
        cron_log("❌ Nieprawidłowe dane obrazu z URL: {$image_url}", 'error');
        return false;
    }

    // Przygotuj nazwę pliku
    $original_filename = basename($image_url);
    $original_filename = sanitize_file_name($original_filename);
    $original_filename = preg_replace('/\?.*$/', '', $original_filename);

    $filename_base = pathinfo($original_filename, PATHINFO_FILENAME);
    $original_extension = pathinfo($original_filename, PATHINFO_EXTENSION);

    // Zapisz tymczasowo oryginalny plik
    $temp_filename = time() . '_' . $filename_base . '.' . $original_extension;
    $temp_file_path = $upload_dir['path'] . '/' . $temp_filename;

    $bytes_written = file_put_contents($temp_file_path, $image_data);
    if ($bytes_written === false) {
        cron_log("❌ Nie udało się zapisać tymczasowego pliku: {$temp_file_path}", 'error');
        return false;
    }

    // Konwertuj do WebP jeśli możliwe
    $final_filename = $filename_base . '_' . time() . '.webp';
    $final_file_path = $upload_dir['path'] . '/' . $final_filename;

    $webp_converted = false;

    if (function_exists('imagewebp') && function_exists('imagecreatefromstring')) {
        $source_image = @imagecreatefromstring($image_data);

        if ($source_image !== false) {
            // Optymalizuj obraz - ustaw maksymalną szerokość
            $max_width = 1200;
            $original_width = imagesx($source_image);
            $original_height = imagesy($source_image);

            if ($original_width > $max_width) {
                $ratio = $max_width / $original_width;
                $new_width = $max_width;
                $new_height = intval($original_height * $ratio);

                $resized_image = imagecreatetruecolor($new_width, $new_height);

                // Zachowaj przezroczystość dla PNG
                if ($image_info[2] == IMAGETYPE_PNG) {
                    imagealphablending($resized_image, false);
                    imagesavealpha($resized_image, true);
                    $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
                    imagefill($resized_image, 0, 0, $transparent);
                }

                imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
                imagedestroy($source_image);
                $source_image = $resized_image;

                cron_log("🖼️ Zmieniono rozmiar obrazu do {$new_width}x{$new_height}px", 'debug');
            }

            // Konwertuj do WebP
            if (@imagewebp($source_image, $final_file_path, 85)) {
                $webp_converted = true;
                cron_log("✅ Skonwertowano do WebP: {$final_filename}", 'debug');
            } else {
                cron_log("⚠️ Nie udało się skonwertować do WebP, używam oryginalnego formatu", 'warning');
            }

            imagedestroy($source_image);
        }
    } else {
        cron_log("⚠️ GD nie obsługuje WebP, używam oryginalnego formatu", 'warning');
    }

    // Jeśli konwersja WebP się nie udała, użyj oryginalnego pliku
    if (!$webp_converted) {
        $final_filename = $temp_filename;
        $final_file_path = $temp_file_path;
    } else {
        @unlink($temp_file_path);
    }

    // Dodaj do biblioteki mediów
    $filetype = wp_check_filetype($final_filename, null);
    $attachment = [
        'guid' => $upload_dir['url'] . '/' . $final_filename,
        'post_mime_type' => $filetype['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', $filename_base),
        'post_content' => '',
        'post_status' => 'inherit',
        'post_date' => date('Y-m-d H:i:s', $final_timestamp),
        'post_date_gmt' => gmdate('Y-m-d H:i:s', $final_timestamp),
        'post_modified' => date('Y-m-d H:i:s', $final_timestamp),
        'post_modified_gmt' => gmdate('Y-m-d H:i:s', $final_timestamp)
    ];

    $attach_id = wp_insert_attachment($attachment, $final_file_path, $product_id);

    if (!$attach_id) {
        cron_log("❌ Nie udało się utworzyć załącznika w WordPress", 'error');
        @unlink($final_file_path);
        return false;
    }

    // Zapisz URL źródłowy i informacje o konwersji
    update_post_meta($attach_id, '_mhi_source_url', $image_url);
    update_post_meta($attach_id, '_mhi_webp_converted', $webp_converted ? 'yes' : 'no');
    update_post_meta($attach_id, '_mhi_original_format', $original_extension);
    update_post_meta($attach_id, '_mhi_random_date', date('Y-m-d H:i:s', $final_timestamp));
    update_post_meta($attach_id, '_mhi_folder_path', date('Y/m', $final_timestamp));

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $final_file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Ustaw jako główny obraz
    if ($is_featured) {
        $thumbnail_result = set_post_thumbnail($product_id, $attach_id);
        if ($thumbnail_result) {
            cron_log("🌟 Ustawiono jako główny obraz produktu (ID: {$attach_id})", 'debug');
        } else {
            cron_log("❌ Nie udało się ustawić głównego obrazu produktu", 'error');
        }
    }

    $format_info = $webp_converted ? " (WebP)" : " ({$original_extension})";
    $folder_info = date('Y/m', $final_timestamp);
    cron_log("📸 Dodano obraz: {$final_filename}{$format_info} → {$folder_info}/", 'debug');

    return $attach_id;
}
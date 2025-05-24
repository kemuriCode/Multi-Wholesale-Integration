<?php
/**
 * CLI IMPORT PRODUKTÓW MALFINI
 * Uruchamianie przez terminal: php cli-import.php malfini
 */

declare(strict_types=1);

// Sprawdź czy uruchamiany z CLI
if (php_sapi_name() !== 'cli') {
    die("Ten skrypt można uruchomić tylko z linii poleceń!\n");
}

// Załaduj WordPress
echo "🔧 Ładowanie WordPress...\n";
define('WP_USE_THEMES', false);
require_once dirname(__FILE__, 4) . '/wp-load.php';

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    die("❌ WooCommerce nie jest aktywne!\n");
}

// Sprawdź parametr
$supplier = $argv[1] ?? 'malfini';
if (!in_array($supplier, ['malfini', 'axpol', 'macma', 'par'])) {
    die("❌ Nieprawidłowy dostawca! Użyj: malfini, axpol, macma lub par\n");
}

echo "🚀 ROZPOCZYNAM IMPORT PRODUKTÓW: " . strtoupper($supplier) . "\n";
echo str_repeat("=", 60) . "\n";

// Znajdź plik XML
$upload_dir = wp_upload_dir();
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

if (!file_exists($xml_file)) {
    die("❌ Plik XML nie istnieje: $xml_file\n");
}

echo "📄 Plik XML: " . basename($xml_file) . "\n";
echo "📏 Rozmiar: " . round(filesize($xml_file) / 1024 / 1024, 2) . " MB\n";

// Parsuj XML
$xml = simplexml_load_file($xml_file);
if (!$xml) {
    die("❌ Błąd parsowania XML!\n");
}

$products = $xml->children();
$total = count($products);

echo "✅ Znaleziono {$total} produktów do importu\n";
echo str_repeat("-", 60) . "\n";

// Zwiększ limity
ini_set('memory_limit', '2048M');
set_time_limit(0);

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
    'images' => 0
];

$start_time = microtime(true);

// GŁÓWNA PĘTLA IMPORTU
foreach ($products as $index => $product_xml) {
    $processed = $index + 1;

    // SKU i nazwa
    $sku = trim((string) $product_xml->sku);
    if (empty($sku))
        $sku = trim((string) $product_xml->id);

    $name = trim((string) $product_xml->name);
    if (empty($name))
        $name = 'Produkt ' . $sku;

    echo "[{$processed}/{$total}] {$name} (SKU: {$sku})";

    try {
        // Sprawdź czy produkt istnieje
        $product_id = wc_get_product_id_by_sku($sku);
        $is_update = (bool) $product_id;

        if ($is_update) {
            $product = wc_get_product($product_id);
            echo " [AKTUALIZACJA]";
        } else {
            $product = new WC_Product();
            echo " [NOWY]";
        }

        // USTAWIANIE PODSTAWOWYCH DANYCH
        $product->set_name($name);
        $product->set_description((string) $product_xml->description);
        $product->set_short_description((string) $product_xml->short_description);
        $product->set_sku($sku);
        $product->set_status('publish');

        // CENY
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
        if (!empty((string) $product_xml->weight))
            $product->set_weight((string) $product_xml->weight);
        if (!empty((string) $product_xml->length))
            $product->set_length((string) $product_xml->length);
        if (!empty((string) $product_xml->width))
            $product->set_width((string) $product_xml->width);
        if (!empty((string) $product_xml->height))
            $product->set_height((string) $product_xml->height);

        // ZAPISZ PRODUKT
        $product_id = $product->save();

        if (!$product_id) {
            throw new Exception("Nie można zapisać produktu");
        }

        // KATEGORIE
        if (isset($product_xml->categories)) {
            $categories_text = trim((string) $product_xml->categories);
            if (!empty($categories_text)) {
                $categories_text = html_entity_decode($categories_text, ENT_QUOTES, 'UTF-8');
                $category_ids = process_product_categories($categories_text);
                if (!empty($category_ids)) {
                    wp_set_object_terms($product_id, $category_ids, 'product_cat');
                }
            }
        }

        // ATRYBUTY
        if (isset($product_xml->attributes) && $product_xml->attributes->attribute) {
            $attributes = $product_xml->attributes->attribute;
            if (!is_array($attributes))
                $attributes = [$attributes];

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

        // OBRAZY
        if (isset($product_xml->images) && $product_xml->images->image) {
            $images = $product_xml->images->image;
            if (!is_array($images))
                $images = [$images];

            $image_ids = [];
            foreach ($images as $img_index => $image) {
                $image_url = '';
                if (isset($image->src)) {
                    $image_url = trim((string) $image->src);
                } else {
                    $image_url = trim((string) $image);
                }

                if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $attachment_id = import_product_image($image_url, $product_id, $img_index === 0);
                    if ($attachment_id) {
                        $image_ids[] = $attachment_id;
                        $stats['images']++;
                    }
                }
            }

            // Galeria
            if (count($image_ids) > 1) {
                $featured_id = get_post_thumbnail_id($product_id);
                $gallery_ids = array_filter($image_ids, function ($id) use ($featured_id) {
                    return $id != $featured_id;
                });

                if (!empty($gallery_ids)) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                    $product = wc_get_product($product_id);
                    $product->set_gallery_image_ids($gallery_ids);
                    $product->save();
                }
            }
        }

        // Oznacz jako importowany
        update_post_meta($product_id, '_mhi_imported', 'yes');
        update_post_meta($product_id, '_mhi_supplier', $supplier);
        update_post_meta($product_id, '_mhi_import_date', current_time('mysql'));

        // Statystyki
        if ($is_update) {
            $stats['updated']++;
            echo " ✅";
        } else {
            $stats['created']++;
            echo " ✅";
        }

    } catch (Exception $e) {
        $stats['failed']++;
        echo " ❌ " . $e->getMessage();
    }

    echo "\n";

    // Postęp co 50 produktów
    if ($processed % 50 === 0) {
        $elapsed = round(microtime(true) - $start_time, 2);
        $progress = round(($processed / $total) * 100, 1);
        echo "📊 Postęp: {$progress}% | Czas: {$elapsed}s | Utworzono: {$stats['created']} | Zaktualizowano: {$stats['updated']} | Błędy: {$stats['failed']}\n";
    }
}

// Włącz z powrotem cache
wp_suspend_cache_invalidation(false);
wp_defer_term_counting(false);
wp_defer_comment_counting(false);

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo str_repeat("=", 60) . "\n";
echo "🎉 IMPORT ZAKOŃCZONY!\n";
echo "⏱️  Czas wykonania: {$execution_time} sekund\n";
echo "📦 Produkty łącznie: {$stats['total']}\n";
echo "➕ Utworzono: {$stats['created']}\n";
echo "📝 Zaktualizowano: {$stats['updated']}\n";
echo "❌ Błędy: {$stats['failed']}\n";
echo "🖼️ Obrazy: {$stats['images']}\n";

/**
 * Przetwarzanie kategorii z hierarchią
 */
function process_product_categories($categories_text)
{
    $category_ids = [];
    $categories = explode('>', $categories_text);

    $parent_id = 0;
    foreach ($categories as $cat_name) {
        $cat_name = trim($cat_name);
        if (empty($cat_name))
            continue;

        // Sprawdź czy kategoria istnieje
        $existing_term = get_term_by('name', $cat_name, 'product_cat', ARRAY_A);

        if ($existing_term) {
            $category_ids[] = $existing_term['term_id'];
            $parent_id = $existing_term['term_id'];
        } else {
            // Utwórz nową kategorię
            $result = wp_insert_term($cat_name, 'product_cat', array('parent' => $parent_id));
            if (!is_wp_error($result)) {
                $category_ids[] = $result['term_id'];
                $parent_id = $result['term_id'];
            }
        }
    }

    return $category_ids;
}

/**
 * Import obrazu produktu
 */
function import_product_image($image_url, $product_id, $is_featured = false)
{
    // Sprawdź czy obraz już istnieje
    $existing_attachment = attachment_url_to_postid($image_url);
    if ($existing_attachment) {
        if ($is_featured) {
            set_post_thumbnail($product_id, $existing_attachment);
        }
        return $existing_attachment;
    }

    // Pobierz obraz
    $upload_dir = wp_upload_dir();
    $filename = basename($image_url);

    // Unikalna nazwa pliku
    $file_path = $upload_dir['path'] . '/' . $filename;
    $counter = 1;
    while (file_exists($file_path)) {
        $info = pathinfo($filename);
        $file_path = $upload_dir['path'] . '/' . $info['filename'] . '_' . $counter . '.' . $info['extension'];
        $counter++;
    }

    $image_data = @file_get_contents($image_url);
    if (!$image_data) {
        return false;
    }

    file_put_contents($file_path, $image_data);

    // Utwórz attachment
    $attachment = array(
        'post_mime_type' => wp_check_filetype($file_path)['type'],
        'post_title' => sanitize_file_name(pathinfo($file_path, PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);

    if (!is_wp_error($attachment_id)) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        if ($is_featured) {
            set_post_thumbnail($product_id, $attachment_id);
        }

        return $attachment_id;
    }

    return false;
}
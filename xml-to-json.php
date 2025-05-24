<?php
/**
 * XML TO JSON CONVERTER
 * Konwertuje XML z produktami Malfini na JSON gotowy do importu
 */

declare(strict_types=1);

$supplier = $argv[1] ?? 'malfini';

echo "🔧 XML TO JSON CONVERTER - " . strtoupper($supplier) . "\n";
echo str_repeat("=", 60) . "\n";

// Ścieżka do pliku XML
$xml_file = dirname(__FILE__, 3) . "/uploads/wholesale/{$supplier}/woocommerce_import_{$supplier}.xml";

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

echo "✅ Znaleziono {$total} produktów\n";
echo "🔄 Konwersja na JSON...\n";

$json_products = [];
$stats = ['total' => $total, 'processed' => 0, 'images' => 0, 'categories' => []];

$processed = 0;
foreach ($products as $product_xml) {
    $processed++;

    // SKU i nazwa
    $sku = trim((string) $product_xml->sku);
    if (empty($sku))
        $sku = trim((string) $product_xml->id);

    $name = trim((string) $product_xml->name);
    if (empty($name))
        $name = 'Produkt ' . $sku;

    // Podstawowe dane
    $product_data = [
        'sku' => $sku,
        'name' => $name,
        'description' => trim((string) $product_xml->description),
        'short_description' => trim((string) $product_xml->short_description),
        'status' => 'publish'
    ];

    // CENY
    $regular_price = trim((string) $product_xml->regular_price);
    if (!empty($regular_price)) {
        $regular_price = str_replace(',', '.', $regular_price);
        if (is_numeric($regular_price) && floatval($regular_price) > 0) {
            $product_data['regular_price'] = floatval($regular_price);
        }
    }

    $sale_price = trim((string) $product_xml->sale_price);
    if (!empty($sale_price)) {
        $sale_price = str_replace(',', '.', $sale_price);
        if (is_numeric($sale_price) && floatval($sale_price) > 0) {
            $product_data['sale_price'] = floatval($sale_price);
        }
    }

    // STOCK
    $stock_qty = trim((string) $product_xml->stock_quantity);
    if (!empty($stock_qty) && is_numeric($stock_qty)) {
        $product_data['manage_stock'] = true;
        $product_data['stock_quantity'] = (int) $stock_qty;
        $product_data['stock_status'] = 'instock';
    }

    // WYMIARY
    if (!empty((string) $product_xml->weight))
        $product_data['weight'] = (string) $product_xml->weight;
    if (!empty((string) $product_xml->length))
        $product_data['length'] = (string) $product_xml->length;
    if (!empty((string) $product_xml->width))
        $product_data['width'] = (string) $product_xml->width;
    if (!empty((string) $product_xml->height))
        $product_data['height'] = (string) $product_xml->height;

    // KATEGORIE
    if (isset($product_xml->categories)) {
        $categories = [];
        if (isset($product_xml->categories->category)) {
            // Jeśli kategorie są w elementach <category>
            $cats = $product_xml->categories->category;
            if (!is_array($cats))
                $cats = [$cats];

            foreach ($cats as $cat) {
                $cat_name = trim((string) $cat);
                $cat_name = html_entity_decode($cat_name, ENT_QUOTES, 'UTF-8');
                if (!empty($cat_name)) {
                    $categories[] = $cat_name;
                }
            }
        } else {
            // Jeśli kategorie są jako tekst
            $categories_text = trim((string) $product_xml->categories);
            if (!empty($categories_text)) {
                $categories_text = html_entity_decode($categories_text, ENT_QUOTES, 'UTF-8');
                $categories = array_map('trim', explode('>', $categories_text));
                $categories = array_filter($categories);
            }
        }

        if (!empty($categories)) {
            $product_data['categories'] = $categories;

            // Zbierz wszystkie kategorie dla statystyk
            foreach ($categories as $cat) {
                if (!in_array($cat, $stats['categories'])) {
                    $stats['categories'][] = $cat;
                }
            }
        }
    }

    // ATRYBUTY
    if (isset($product_xml->attributes) && $product_xml->attributes->attribute) {
        $attributes = $product_xml->attributes->attribute;
        if (!is_array($attributes))
            $attributes = [$attributes];

        $product_attributes = [];
        foreach ($attributes as $attr) {
            $attr_name = trim((string) $attr->name);
            $attr_value = trim((string) $attr->value);

            if (!empty($attr_name) && !empty($attr_value)) {
                $product_attributes[] = [
                    'name' => $attr_name,
                    'value' => $attr_value,
                    'visible' => true,
                    'variation' => false
                ];
            }
        }

        if (!empty($product_attributes)) {
            $product_data['attributes'] = $product_attributes;
        }
    }

    // OBRAZY
    if (isset($product_xml->images) && $product_xml->images->image) {
        $images = $product_xml->images->image;
        if (!is_array($images))
            $images = [$images];

        $product_images = [];
        $img_counter = 0;
        foreach ($images as $image) {
            $image_url = '';

            // Sprawdź atrybut src
            $attributes = $image->attributes();
            if (isset($attributes['src'])) {
                $image_url = trim((string) $attributes['src']);
            } else {
                $image_url = trim((string) $image);
            }

            if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $product_images[] = [
                    'src' => $image_url,
                    'name' => basename($image_url),
                    'alt' => $name,
                    'featured' => $img_counter === 0
                ];
                $stats['images']++;
                $img_counter++;
            }
        }

        if (!empty($product_images)) {
            $product_data['images'] = $product_images;
        }
    }

    // Meta data
    $product_data['meta_data'] = [
        '_mhi_imported' => 'yes',
        '_mhi_supplier' => $supplier,
        '_mhi_import_date' => date('Y-m-d H:i:s')
    ];

    $json_products[] = $product_data;
    $stats['processed']++;

    // Postęp co 50 produktów
    if ($processed % 50 === 0) {
        $progress = round(($processed / $total) * 100, 1);
        echo "📊 Postęp: {$progress}% ({$processed}/{$total})\n";
    }
}

// Zapisz JSON
$json_file = dirname(__FILE__, 3) . "/uploads/wholesale/{$supplier}/products_import_{$supplier}.json";
$json_data = [
    'info' => [
        'supplier' => $supplier,
        'generated' => date('Y-m-d H:i:s'),
        'source_file' => basename($xml_file),
        'stats' => $stats
    ],
    'products' => $json_products
];

file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo str_repeat("=", 60) . "\n";
echo "🎉 KONWERSJA ZAKOŃCZONA!\n";
echo "📦 Przetworzonych produktów: {$stats['processed']}\n";
echo "🖼️ Obrazów: {$stats['images']}\n";
echo "📁 Kategorii: " . count($stats['categories']) . "\n";
echo "💾 Plik JSON: " . basename($json_file) . "\n";
echo "📏 Rozmiar JSON: " . round(filesize($json_file) / 1024 / 1024, 2) . " MB\n";

echo "\n✅ GOTOWE! Możesz teraz zaimportować produkty z pliku JSON gdy WordPress będzie dostępny.\n";

// Podgląd pierwszych 3 produktów
echo "\n📋 PODGLĄD PIERWSZYCH 3 PRODUKTÓW:\n";
echo str_repeat("-", 60) . "\n";

for ($i = 0; $i < min(3, count($json_products)); $i++) {
    $p = $json_products[$i];
    echo ($i + 1) . ". {$p['name']} (SKU: {$p['sku']})\n";
    if (isset($p['regular_price']))
        echo "   💰 Cena: {$p['regular_price']} PLN\n";
    if (isset($p['categories']))
        echo "   📁 Kategorie: " . implode(' > ', $p['categories']) . "\n";
    if (isset($p['attributes']))
        echo "   🏷️ Atrybuty: " . count($p['attributes']) . "\n";
    if (isset($p['images']))
        echo "   🖼️ Obrazy: " . count($p['images']) . "\n";
    echo "\n";
}

// Lista wszystkich kategorii
if (!empty($stats['categories'])) {
    echo "📁 WSZYSTKIE KATEGORIE:\n";
    echo str_repeat("-", 60) . "\n";
    foreach (array_slice($stats['categories'], 0, 20) as $i => $cat) {
        echo ($i + 1) . ". {$cat}\n";
    }
    if (count($stats['categories']) > 20) {
        echo "... i " . (count($stats['categories']) - 20) . " więcej\n";
    }
}
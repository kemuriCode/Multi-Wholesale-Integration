<?php
/**
 * PROSTY TEST PARSOWANIA XML
 * Sprawdza czy plik XML jest poprawny i można go parsować
 */

declare(strict_types=1);

echo "🔧 Test parsowania XML Malfini\n";

// Ścieżka do pliku XML
$xml_file = dirname(__FILE__, 3) . '/uploads/wholesale/malfini/woocommerce_import_malfini.xml';

echo "📁 Plik XML: " . $xml_file . "\n";

if (!file_exists($xml_file)) {
    echo "❌ Plik XML nie istnieje!\n";
    exit(1);
}

echo "✅ Plik XML istnieje, rozmiar: " . round(filesize($xml_file) / 1024 / 1024, 2) . " MB\n";

// Test parsowania XML
echo "🔄 Parsowanie XML...\n";

$xml = simplexml_load_file($xml_file);
if (!$xml) {
    echo "❌ Błąd parsowania XML!\n";
    exit(1);
}

$products = $xml->children();
$total = count($products);

echo "✅ XML parsowany poprawnie!\n";
echo "📦 Znaleziono produktów: " . $total . "\n";

// Sprawdź pierwszy produkt
if ($total > 0) {
    $first_product = $products[0];
    echo "\n🔍 Pierwszy produkt:\n";
    echo "  - SKU: " . (string) $first_product->sku . "\n";
    echo "  - Nazwa: " . (string) $first_product->name . "\n";
    echo "  - Cena: " . (string) $first_product->regular_price . "\n";
    echo "  - Kategorie: " . (string) $first_product->categories . "\n";

    if (isset($first_product->attributes)) {
        echo "  - Atrybuty: " . count($first_product->attributes->attribute) . "\n";
    }

    if (isset($first_product->images)) {
        echo "  - Obrazy: " . count($first_product->images->image) . "\n";
    }
}

echo "\n🎉 Test zakończony pomyślnie!\n";
echo "💡 Plik XML jest gotowy do importu w WordPress!\n";
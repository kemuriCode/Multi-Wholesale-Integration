<?php
/**
 * Skrypt testowy dla generatora XML ANDA z obsługą wariantów.
 * 
 * Uruchom w przeglądarce: /wp-content/plugins/multi-wholesale-integration/test-anda-variants.php
 */

// Ładuj WordPress
require_once('../../../wp-load.php');

// Sprawdź czy pliki istnieją
$upload_dir = wp_upload_dir();
$anda_dir = $upload_dir['basedir'] . '/wholesale/anda';

if (!is_dir($anda_dir)) {
    die('❌ Katalog ANDA nie istnieje: ' . $anda_dir);
}

// Sprawdź czy istnieją pliki XML
$required_files = ['products.xml', 'prices.xml', 'inventories.xml', 'categories.xml'];
foreach ($required_files as $file) {
    if (!file_exists($anda_dir . '/' . $file)) {
        die('❌ Brakuje pliku: ' . $file);
    }
}

echo '<h1>🧪 Test generatora XML ANDA z wariantami</h1>';

echo '<h2>📁 Sprawdzanie plików źródłowych</h2>';
echo '<ul>';
foreach ($required_files as $file) {
    $file_path = $anda_dir . '/' . $file;
    $file_size = filesize($file_path);
    echo '<li>✅ ' . $file . ' - ' . formatBytes($file_size) . '</li>';
}
echo '</ul>';

// Załaduj klasę generatora
require_once('integrations/class-mhi-anda-wc-xml-generator.php');

echo '<h2>🔧 Inicjalizacja generatora</h2>';
$generator = new MHI_ANDA_WC_XML_Generator();

echo '<h2>🧪 Generowanie XML testowego (25 produktów)</h2>';
$start_time = microtime(true);

$result = $generator->generate_test_xml(25);

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo '<h3>📊 Wyniki:</h3>';
if ($result['success']) {
    echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px;">';
    echo '<h4>✅ Sukces!</h4>';
    echo '<p><strong>Wiadomość:</strong> ' . $result['message'] . '</p>';
    echo '<p><strong>Czas wykonania:</strong> ' . $execution_time . ' sekund</p>';

    if (isset($result['stats'])) {
        echo '<h4>📈 Statystyki:</h4>';
        echo '<ul>';
        echo '<li>Główne produkty (bez wariantów): ' . $result['stats']['main_products'] . '</li>';
        echo '<li>Produkty wariantowe: ' . $result['stats']['variable_products'] . '</li>';
        echo '<li>Łączna liczba wariantów: ' . $result['stats']['total_variants'] . '</li>';
        echo '</ul>';
    }

    if (isset($result['results'])) {
        echo '<h4>📄 Wygenerowane pliki:</h4>';
        echo '<ul>';
        foreach ($result['results'] as $type => $file_result) {
            if ($file_result['success']) {
                echo '<li>✅ ' . $type . ': ' . $file_result['file'] . ' (' . $file_result['count'] . ' elementów)</li>';
            } else {
                echo '<li>❌ ' . $type . ': BŁĄD</li>';
            }
        }
        echo '</ul>';
    }
    echo '</div>';
} else {
    echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px;">';
    echo '<h4>❌ Błąd!</h4>';
    echo '<p>' . $result['message'] . '</p>';
    echo '</div>';
}

echo '<h2>📋 Informacje o grupowaniu produktów</h2>';
$grouping_info = $generator->get_grouping_info();

echo '<h3>🔢 Ogólne statystyki:</h3>';
echo '<ul>';
echo '<li>Główne produkty: ' . $grouping_info['main_products'] . '</li>';
echo '<li>Produkty wariantowe: ' . $grouping_info['variable_products'] . '</li>';
echo '<li>Łączna liczba wariantów: ' . $grouping_info['total_variants'] . '</li>';
echo '</ul>';

if (!empty($grouping_info['examples'])) {
    echo '<h3>📝 Przykłady grupowania:</h3>';
    echo '<table style="border-collapse: collapse; width: 100%;">';
    echo '<tr style="background: #f8f9fa;"><th style="border: 1px solid #dee2e6; padding: 8px;">Bazowy SKU</th><th style="border: 1px solid #dee2e6; padding: 8px;">Główny produkt</th><th style="border: 1px solid #dee2e6; padding: 8px;">Warianty</th><th style="border: 1px solid #dee2e6; padding: 8px;">Liczba wariantów</th></tr>';

    foreach ($grouping_info['examples'] as $example) {
        echo '<tr>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px;"><strong>' . $example['base_sku'] . '</strong></td>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px;">' . $example['main_product'] . '</td>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px;">' . implode(', ', $example['variants']) . '</td>';
        echo '<td style="border: 1px solid #dee2e6; padding: 8px;">' . $example['variant_count'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

echo '<h2>📁 Sprawdzenie wygenerowanych plików</h2>';
$generated_file = $anda_dir . '/woocommerce_import_anda.xml';
if (file_exists($generated_file)) {
    $file_size = filesize($generated_file);
    echo '<p>✅ <strong>Główny plik XML:</strong> woocommerce_import_anda.xml (' . formatBytes($file_size) . ')</p>';
    echo '<p>📍 <strong>Ścieżka:</strong> ' . $generated_file . '</p>';
    echo '<p>📅 <strong>Ostatnia modyfikacja:</strong> ' . date('Y-m-d H:i:s', filemtime($generated_file)) . '</p>';
} else {
    echo '<p>❌ Plik woocommerce_import_anda.xml nie został wygenerowany</p>';
}

echo '<h2>🔍 Podgląd pierwszych 10 linii XML</h2>';
if (file_exists($generated_file)) {
    $lines = file($generated_file, FILE_IGNORE_NEW_LINES);
    echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto;">';
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]) . "\n";
    }
    if (count($lines) > 10) {
        echo '... (i ' . (count($lines) - 10) . ' więcej linii)';
    }
    echo '</pre>';
}

echo '<hr>';
echo '<p><strong>🚀 Gotowe do testowania!</strong></p>';
echo '<p>Plik XML został wygenerowany i jest gotowy do importu w WooCommerce.</p>';

/**
 * Formatuje rozmiar pliku.
 */
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}
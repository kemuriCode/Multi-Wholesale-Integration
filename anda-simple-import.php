<?php
/**
 * ANDA Prosty Importer
 * Import tylko ceny, stocku i kategorii dla istniejących produktów ANDA
 * 
 * URL: /wp-content/plugins/multi-wholesale-integration/anda-simple-import.php
 */

// Bezpieczeństwo - sprawdź czy to WordPress
if (!defined('ABSPATH')) {
    // Ładuj WordPress jeśli uruchamiany bezpośrednio
    require_once('../../../wp-load.php');
}

// Sprawdź uprawnienia
if (!current_user_can('manage_options')) {
    wp_die('Brak uprawnień do importu!');
}

// Pobierz parametry
$batch_size = isset($_GET['batch_size']) ? (int) $_GET['batch_size'] : 50;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$auto_continue = isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1';
$test_mode = isset($_GET['test_mode']) && $_GET['test_mode'] === '1';

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('WooCommerce nie jest aktywne!');
}

// Zwiększ limity
ini_set('memory_limit', '1024M');
set_time_limit(0);
ignore_user_abort(true);

// Znajdź plik XML ANDA
$upload_dir = wp_upload_dir();
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/anda/woocommerce_import_anda.xml';

if (!file_exists($xml_file)) {
    wp_die('Plik XML ANDA nie istnieje: ' . basename($xml_file));
}

// Parsuj XML
$xml = simplexml_load_file($xml_file);
if (!$xml) {
    wp_die('Błąd parsowania pliku XML ANDA');
}

$products = $xml->children();
$total = count($products);
$end_offset = min($offset + $batch_size, $total);

$start_time = microtime(true);

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 ANDA Simple Import</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 20px;
            background: #f1f1f1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 8px;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }

        .log-container {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            background: #f9f9f9;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .log-entry {
            margin: 2px 0;
            padding: 3px 8px;
            border-radius: 3px;
        }

        .log-info {
            color: #2196F3;
        }

        .log-success {
            color: #28a745;
            font-weight: bold;
        }

        .log-warning {
            color: #FF9800;
        }

        .log-error {
            color: #dc3545;
            font-weight: bold;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #28a745;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }

        .controls {
            margin: 20px 0;
            text-align: center;
        }

        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #007cba;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: black;
        }

        .focus-items {
            background: #e8f5e8;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .focus-items h3 {
            color: #28a745;
            margin-top: 0;
        }

        .focus-items ul {
            list-style: none;
            padding: 0;
        }

        .focus-items li {
            padding: 8px 0;
            border-bottom: 1px solid #c3e6cb;
        }

        .focus-items li:last-child {
            border-bottom: none;
        }

        .focus-items li::before {
            content: "✅ ";
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🎯 ANDA Simple Import</h1>
            <p>Batch: <?php echo $offset + 1; ?>-<?php echo $end_offset; ?> z <?php echo $total; ?></p>
            <p>✨ Tylko ceny, stock i kategorie - bez wariantów!</p>
        </div>

        <div class="focus-items">
            <h3>🎯 Co importujemy:</h3>
            <ul>
                <li>Ceny z meta _anda_price_listPrice</li>
                <li>Stan magazynowy (stock)</li>
                <li>Kategorie produktów</li>
            </ul>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($offset / $total) * 100; ?>%"></div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="processed">0</div>
                <div>Przetworzonych</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="updated">0</div>
                <div>Zaktualizowanych</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="skipped">0</div>
                <div>Pominiętych</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="errors">0</div>
                <div>Błędów</div>
            </div>
        </div>

        <div class="log-container" id="log-container">
            <div class="log-entry log-info">🚀 Rozpoczynam import ANDA Simple...</div>
        </div>

        <div class="controls">
            <?php if ($offset + $batch_size < $total): ?>
                <a href="?offset=<?php echo $offset + $batch_size; ?>&batch_size=<?php echo $batch_size; ?>&auto_continue=1<?php echo $test_mode ? '&test_mode=1' : ''; ?>"
                    class="btn btn-primary">Następna porcja</a>
            <?php endif; ?>
            <a href="?offset=0&batch_size=<?php echo $batch_size; ?>" class="btn btn-success">Restart</a>
            <a href="?offset=0&batch_size=<?php echo $batch_size; ?>&test_mode=1" class="btn btn-warning">Tryb
                testowy</a>
        </div>
    </div>

    <script>
        let processed = 0;
        let updated = 0;
        let skipped = 0;
        let errors = 0;

        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('log-container');
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            logEntry.textContent = new Date().toLocaleTimeString() + ' - ' + message;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function updateStats() {
            document.getElementById('processed').textContent = processed;
            document.getElementById('updated').textContent = updated;
            document.getElementById('skipped').textContent = skipped;
            document.getElementById('errors').textContent = errors;
        }

        function updateProgress(current, total) {
            const percentage = (current / total) * 100;
            document.querySelector('.progress-fill').style.width = percentage + '%';
        }

        // Auto-scroll i auto-continue
        <?php if ($auto_continue && $offset + $batch_size < $total): ?>
            setTimeout(() => {
                window.location.href = '?offset=<?php echo $offset + $batch_size; ?>&batch_size=<?php echo $batch_size; ?>&auto_continue=1<?php echo $test_mode ? '&test_mode=1' : ''; ?>';
            }, 2000);
        <?php endif; ?>
    </script>
</body>

</html>

<?php
// Uruchom import
$processed_count = 0;
$updated_count = 0;
$skipped_count = 0;
$error_count = 0;

echo "<script>addLog('🔍 Analizuję produkty ANDA...', 'info');</script>";
flush();

// Przetwarzaj produkty
for ($i = $offset; $i < $end_offset; $i++) {
    $product_xml = $products[$i];

    if (!$product_xml) {
        continue;
    }

    // Pobierz SKU
    $sku = (string) $product_xml->sku;

    if (empty($sku)) {
        echo "<script>addLog('❌ Brak SKU dla produktu #{$i}', 'error'); errors++; updateStats();</script>";
        flush();
        $error_count++;
        continue;
    }

    // Znajdź produkt w WooCommerce
    $product_id = wc_get_product_id_by_sku($sku);

    if (!$product_id) {
        echo "<script>addLog('⚠️ Produkt nie istnieje: $sku', 'warning'); skipped++; updateStats();</script>";
        flush();
        $skipped_count++;
        $processed_count++;
        continue;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        echo "<script>addLog('❌ Nie można załadować produktu: $sku', 'error'); errors++; updateStats();</script>";
        flush();
        $error_count++;
        continue;
    }

    echo "<script>addLog('🔄 Aktualizuję produkt: $sku', 'info');</script>";
    flush();

    $changes_made = false;

    // 1. CENA - sprawdź meta _anda_price_listPrice
    $price_updated = anda_simple_update_price($product, $product_xml, $sku, $test_mode);
    if ($price_updated) {
        $changes_made = true;
    }

    // 2. STOCK - aktualizuj stan magazynowy
    $stock_updated = anda_simple_update_stock($product, $product_xml, $sku, $test_mode);
    if ($stock_updated) {
        $changes_made = true;
    }

    // 3. KATEGORIE - aktualizuj kategorie
    $categories_updated = anda_simple_update_categories($product, $product_xml, $sku, $test_mode);
    if ($categories_updated) {
        $changes_made = true;
    }

    if ($changes_made) {
        echo "<script>addLog('✅ Zaktualizowano: $sku', 'success'); updated++; updateStats();</script>";
        flush();
        $updated_count++;
    } else {
        echo "<script>addLog('ℹ️ Brak zmian: $sku', 'info'); skipped++; updateStats();</script>";
        flush();
        $skipped_count++;
    }

    $processed_count++;

    // Aktualizuj progress
    echo "<script>updateProgress($processed_count, $batch_size);</script>";
    flush();

    // Krótka pauza
    usleep(100000); // 0.1 sekundy
}

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo "<script>addLog('🎉 Ukończono batch! Czas: {$execution_time}s', 'success');</script>";
flush();

/**
 * Aktualizuje cenę produktu na podstawie meta _anda_price_listPrice
 */
function anda_simple_update_price($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Szukaj meta _anda_price_listPrice
    if (isset($product_xml->meta_data)) {
        foreach ($product_xml->meta_data->meta as $meta) {
            $key = (string) $meta->key;
            $value = (string) $meta->value;

            if ($key === '_anda_price_listPrice' && !empty($value)) {
                $new_price = floatval($value);
                $current_price = floatval($product->get_regular_price());

                if ($new_price !== $current_price) {
                    echo "<script>addLog('💰 Cena: $current_price → $new_price PLN ($sku)', 'info');</script>";
                    flush();

                    if (!$test_mode) {
                        $product->set_regular_price($new_price);
                        $product->set_price($new_price);
                        $product->save();
                    }

                    $changes_made = true;
                } else {
                    echo "<script>addLog('💰 Cena bez zmian: $current_price PLN ($sku)', 'info');</script>";
                    flush();
                }
                break;
            }
        }
    }

    return $changes_made;
}

/**
 * Aktualizuje stock produktu
 */
function anda_simple_update_stock($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Pobierz stock z XML
    $new_stock = isset($product_xml->stock_quantity) ? (int) $product_xml->stock_quantity : null;

    if ($new_stock !== null) {
        $current_stock = (int) $product->get_stock_quantity();

        if ($new_stock !== $current_stock) {
            echo "<script>addLog('📦 Stock: $current_stock → $new_stock szt. ($sku)', 'info');</script>";
            flush();

            if (!$test_mode) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity($new_stock);
                $product->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
                $product->save();
            }

            $changes_made = true;
        } else {
            echo "<script>addLog('📦 Stock bez zmian: $current_stock szt. ($sku)', 'info');</script>";
            flush();
        }
    }

    return $changes_made;
}

/**
 * Aktualizuje kategorie produktu
 */
function anda_simple_update_categories($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Pobierz kategorie z XML
    if (isset($product_xml->categories)) {
        $xml_categories = [];

        foreach ($product_xml->categories->category as $category) {
            $category_name = trim((string) $category);
            if (!empty($category_name)) {
                $xml_categories[] = $category_name;
            }
        }

        if (!empty($xml_categories)) {
            // Pobierz istniejące kategorie produktu
            $current_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));

            // Porównaj kategorie
            $categories_different = array_diff($xml_categories, $current_categories) || array_diff($current_categories, $xml_categories);

            if ($categories_different) {
                echo "<script>addLog('📁 Kategorie: " . implode(', ', $xml_categories) . " ($sku)', 'info');</script>";
                flush();

                if (!$test_mode) {
                    $category_ids = [];

                    foreach ($xml_categories as $category_name) {
                        $category_id = anda_simple_get_or_create_category($category_name);
                        if ($category_id) {
                            $category_ids[] = $category_id;
                        }
                    }

                    if (!empty($category_ids)) {
                        wp_set_post_terms($product->get_id(), $category_ids, 'product_cat');
                    }
                }

                $changes_made = true;
            } else {
                echo "<script>addLog('📁 Kategorie bez zmian: " . implode(', ', $current_categories) . " ($sku)', 'info');</script>";
                flush();
            }
        }
    }

    return $changes_made;
}

/**
 * Pobiera lub tworzy kategorię
 */
function anda_simple_get_or_create_category($category_name)
{
    $category_name = sanitize_text_field($category_name);

    // Sprawdź czy kategoria istnieje
    $existing_category = get_term_by('name', $category_name, 'product_cat');

    if ($existing_category) {
        return $existing_category->term_id;
    }

    // Utwórz nową kategorię
    $result = wp_insert_term($category_name, 'product_cat');

    if (!is_wp_error($result)) {
        return $result['term_id'];
    }

    return false;
}

?>
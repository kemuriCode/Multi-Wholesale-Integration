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
                <li>Nazwy produktów (name)</li>
                <li>URL/slugi produktów</li>
                <li>Ceny (regular_price)</li>
                <li>Stan magazynowy (stock)</li>
                <li>Kategorie produktów</li>
                <li>Atrybuty produktów</li>
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

    // 1. NAZWA - aktualizuj nazwę produktu
    $name_updated = anda_simple_update_name($product, $product_xml, $sku, $test_mode);
    if ($name_updated) {
        $changes_made = true;
    }

    // 2. URL - aktualizuj slug/URL
    $url_updated = anda_simple_update_url($product, $product_xml, $sku, $test_mode);
    if ($url_updated) {
        $changes_made = true;
    }

    // 3. CENA - aktualizuj cenę
    $price_updated = anda_simple_update_price($product, $product_xml, $sku, $test_mode);
    if ($price_updated) {
        $changes_made = true;
    }

    // 4. STOCK - aktualizuj stan magazynowy
    $stock_updated = anda_simple_update_stock($product, $product_xml, $sku, $test_mode);
    if ($stock_updated) {
        $changes_made = true;
    }

    // 5. KATEGORIE - aktualizuj kategorie
    $categories_updated = anda_simple_update_categories($product, $product_xml, $sku, $test_mode);
    if ($categories_updated) {
        $changes_made = true;
    }

    // 6. ATRYBUTY - aktualizuj atrybuty produktu
    $attributes_updated = anda_simple_update_attributes($product, $product_xml, $sku, $test_mode);
    if ($attributes_updated) {
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
 * Aktualizuje nazwę produktu
 */
function anda_simple_update_name($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Pobierz nazwę z XML
    $new_name = isset($product_xml->name) ? trim((string) $product_xml->name) : '';

    if (!empty($new_name)) {
        $current_name = $product->get_name();

        if ($new_name !== $current_name) {
            echo "<script>addLog('📝 Nazwa: \"$current_name\" → \"$new_name\" ($sku)', 'info');</script>";
            flush();

            if (!$test_mode) {
                $product->set_name($new_name);
                $product->save();
            }

            $changes_made = true;
        } else {
            echo "<script>addLog('📝 Nazwa bez zmian: \"$current_name\" ($sku)', 'info');</script>";
            flush();
        }
    }

    return $changes_made;
}

/**
 * Aktualizuje URL/slug produktu
 */
function anda_simple_update_url($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Pobierz nazwę z XML do wygenerowania slug
    $new_name = isset($product_xml->name) ? trim((string) $product_xml->name) : '';

    if (!empty($new_name)) {
        $new_slug = sanitize_title($new_name);
        $current_slug = $product->get_slug();

        if ($new_slug !== $current_slug) {
            echo "<script>addLog('🔗 URL: \"$current_slug\" → \"$new_slug\" ($sku)', 'info');</script>";
            flush();

            if (!$test_mode) {
                $product->set_slug($new_slug);
                $product->save();
            }

            $changes_made = true;
        } else {
            echo "<script>addLog('🔗 URL bez zmian: \"$current_slug\" ($sku)', 'info');</script>";
            flush();
        }
    }

    return $changes_made;
}

/**
 * Aktualizuje cenę produktu - poprawiona wersja
 */
function anda_simple_update_price($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Najpierw sprawdź regular_price z XML
    $new_price = null;
    
    if (isset($product_xml->regular_price) && !empty($product_xml->regular_price)) {
        $new_price = floatval($product_xml->regular_price);
    } elseif (isset($product_xml->meta_data)) {
        // Szukaj meta _anda_price_listPrice jako fallback
        foreach ($product_xml->meta_data->meta as $meta) {
            $key = (string) $meta->key;
            $value = (string) $meta->value;

            if ($key === '_anda_price_listPrice' && !empty($value)) {
                $new_price = floatval($value);
                break;
            }
        }
    }

    if ($new_price !== null) {
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
    }

    return $changes_made;
}

/**
 * Aktualizuje atrybuty produktu
 */
function anda_simple_update_attributes($product, $product_xml, $sku, $test_mode = false)
{
    $changes_made = false;

    // Pobierz atrybuty z XML
    if (isset($product_xml->attributes)) {
        $xml_attributes = [];

        foreach ($product_xml->attributes->attribute as $attribute) {
            $name = isset($attribute->name) ? trim((string) $attribute->name) : '';
            $value = isset($attribute->value) ? trim((string) $attribute->value) : '';

            if (!empty($name) && !empty($value)) {
                $xml_attributes[$name] = $value;
            }
        }

        if (!empty($xml_attributes)) {
            $current_attributes = $product->get_attributes();
            $attributes_changed = false;

            // Sprawdź czy atrybuty się zmieniły
            foreach ($xml_attributes as $attr_name => $attr_value) {
                $attr_exists = false;
                $attr_changed = false;

                foreach ($current_attributes as $current_attr) {
                    if ($current_attr->get_name() === $attr_name) {
                        $attr_exists = true;
                        $current_values = $current_attr->get_options();
                        $current_value = is_array($current_values) ? implode(', ', $current_values) : $current_values;

                        if ($current_value !== $attr_value) {
                            $attr_changed = true;
                            echo "<script>addLog('🏷️ Atrybut \"$attr_name\": \"$current_value\" → \"$attr_value\" ($sku)', 'info');</script>";
                            flush();
                        }
                        break;
                    }
                }

                if (!$attr_exists) {
                    $attr_changed = true;
                    echo "<script>addLog('🏷️ Nowy atrybut \"$attr_name\": \"$attr_value\" ($sku)', 'info');</script>";
                    flush();
                }

                if ($attr_changed) {
                    $attributes_changed = true;
                }
            }

            if ($attributes_changed && !$test_mode) {
                // Aktualizuj atrybuty
                foreach ($xml_attributes as $attr_name => $attr_value) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name($attr_name);
                    $attribute->set_options(array($attr_value));
                    $attribute->set_position(0);
                    $attribute->set_visible(true);
                    $attribute->set_variation(false);

                    $current_attributes[] = $attribute;
                }

                $product->set_attributes($current_attributes);
                $product->save();
                $changes_made = true;
            } elseif (!$attributes_changed) {
                echo "<script>addLog('🏷️ Atrybuty bez zmian ($sku)', 'info');</script>";
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
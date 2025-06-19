<?php
/**
 * 🚀 WYDAJNY SYSTEM CRONÓW IMPORTU PRODUKTÓW
 * Podzielony na 3 etapy dla maksymalnej wydajności!
 * 
 * SPOSÓB UŻYCIA:
 * Stage 1: /wp-content/plugins/multi-wholesale-integration/cron-import.php?supplier=malfini&stage=1
 * Stage 2: /wp-content/plugins/multi-wholesale-integration/cron-import.php?supplier=malfini&stage=2  
 * Stage 3: /wp-content/plugins/multi-wholesale-integration/cron-import.php?supplier=malfini&stage=3
 * 
 * ETAPY:
 * Stage 1: 📦 Podstawowe dane produktu (SKU, nazwa, opisy, ceny, stock, kategorie)
 * Stage 2: 🏷️ Atrybuty i warianty produktu
 * Stage 3: 📷 Galeria obrazów z konwersją WebP
 * 
 * DODATKOWE PARAMETRY:
 * - batch_size=50 (ilość produktów na raz, domyślnie 50)
 * - admin_key=mhi_import_access (alternatywa dla uprawnień)
 * - test_xml=1 (użyj test_gallery.xml zamiast głównego pliku)
 * - replace_images=1 (zastąp istniejące obrazy galerii - tylko stage 3)
 * - offset=0 (rozpocznij od konkretnego produktu)
 * - auto_continue=1 (automatycznie kontynuuj następny batch)
 * - max_products=500 (maksymalna liczba produktów, 0 = bez limitu)
 */

declare(strict_types=1);

// Zwiększ limity wykonania
ini_set('memory_limit', '1024M');
set_time_limit(300); // 5 minut na stage
ignore_user_abort(true);

// Wyświetlaj błędy
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Załaduj WordPress
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Sprawdź uprawnienia
if (!current_user_can('manage_options') && (!isset($_GET['admin_key']) || $_GET['admin_key'] !== 'mhi_import_access')) {
    wp_die('Brak uprawnień do importu produktów!');
}

// Sprawdź parametry
if (!isset($_GET['supplier']) || !isset($_GET['stage'])) {
    wp_die('Wymagane parametry: supplier i stage!<br>Przykład: ?supplier=malfini&stage=1');
}

$supplier = sanitize_text_field($_GET['supplier']);
$stage = (int) $_GET['stage'];
$batch_size = isset($_GET['batch_size']) ? (int) $_GET['batch_size'] : 50;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$auto_continue = isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1';
$max_products = isset($_GET['max_products']) ? (int) $_GET['max_products'] : 0;

// Logowanie parametrów
error_log("MHI Import: supplier=$supplier, stage=$stage, batch_size=$batch_size, offset=$offset, auto_continue=" . ($auto_continue ? 'TRUE' : 'FALSE'));

if (!in_array($stage, [1, 2, 3])) {
    wp_die('Stage musi być 1, 2 lub 3!');
}

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('WooCommerce nie jest aktywne!');
}

// Znajdź plik XML
$upload_dir = wp_upload_dir();
if (isset($_GET['test_xml']) && $_GET['test_xml'] === '1') {
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/test_gallery.xml';
} else {
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';
}

if (!file_exists($xml_file)) {
    wp_die('Plik XML nie istnieje: ' . $xml_file);
}

$start_time = microtime(true);

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 CRON STAGE <?php echo $stage; ?> -
        <?php echo strtoupper($supplier); ?>
    </title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 2.2em;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stage-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
        }

        .progress-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
        }

        .progress {
            background: #e9ecef;
            height: 25px;
            border-radius: 15px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(45deg, #28a745, #20c997);
            height: 100%;
            text-align: center;
            line-height: 25px;
            color: white;
            font-weight: bold;
            width: 0%;
            transition: width 0.5s ease;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .stat {
            background: white;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .stat.stage1 .stat-value {
            color: #007bff;
        }

        .stat.stage2 .stat-value {
            color: #6f42c1;
        }

        .stat.stage3 .stat-value {
            color: #28a745;
        }

        .stat.failed .stat-value {
            color: #dc3545;
        }

        .log-container {
            background: #2c3e50;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }

        .log {
            background: #34495e;
            border-radius: 8px;
            padding: 15px;
            height: 350px;
            overflow-y: auto;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 13px;
            line-height: 1.4;
            color: #ecf0f1;
        }

        .log-entry {
            margin-bottom: 8px;
            padding: 5px 8px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }

        .log-entry.success {
            background: rgba(39, 174, 96, 0.1);
            border-left-color: #27ae60;
            color: #2ecc71;
        }

        .log-entry.error {
            background: rgba(231, 76, 60, 0.1);
            border-left-color: #e74c3c;
            color: #e74c3c;
        }

        .log-entry.warning {
            background: rgba(241, 196, 15, 0.1);
            border-left-color: #f1c40f;
            color: #f39c12;
        }

        .log-entry.info {
            background: rgba(52, 152, 219, 0.1);
            border-left-color: #3498db;
            color: #74b9ff;
        }

        .next-stage {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin: 10px 5px;
        }

        .back-link {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🚀 CRON STAGE
            <?php echo $stage; ?> -
            <?php echo strtoupper($supplier); ?>
        </h1>

        <div class="stage-info">
            <?php if ($stage == 1): ?>
                <strong>📦 STAGE 1: PODSTAWOWE DANE PRODUKTU</strong><br>
                Tworzenie produktów, przypisywanie kategorii, cen, stanu magazynowego i opisów
            <?php elseif ($stage == 2): ?>
                <strong>🏷️ STAGE 2: ATRYBUTY I WARIANTY</strong><br>
                Dodawanie atrybutów do produktów i generowanie wariantów
            <?php else: ?>
                <strong>📷 STAGE 3: GALERIA OBRAZÓW</strong><br>
                Importowanie i konwersja obrazów do WebP z optymalizacją
            <?php endif; ?>
            <br><small>Przetwarzanie:
                <?php echo $batch_size; ?> produktów na raz, offset:
                <?php echo $offset; ?>
                <?php if ($auto_continue): ?>
                    | 🔄 <strong>Auto-continue AKTYWNY</strong>
                    <?php if ($max_products > 0): ?>
                        (max:
                        <?php echo $max_products; ?>)
                    <?php endif; ?>
                <?php endif; ?></small>
        </div>

        <div class="progress-container">
            <div class="progress">
                <div class="progress-bar" id="progressBar">0%</div>
            </div>
        </div>

        <div class="stats">
            <div class="stat stage<?php echo $stage; ?>">
                <div class="stat-value" id="processedCount">0</div>
                <div class="stat-label">Przetworzonych</div>
            </div>
            <div class=" stat stage<?php echo $stage; ?>">
                <div class="stat-value" id="successCount">0</div>
                <div class="stat-label">Udanych</div>
            </div>
            <div class=" stat failed">
                <div class="stat-value" id="failedCount">0</div>
                <div class="stat-label">Błędów</div>
            </div>
            <div class="stat stage<?php echo $stage; ?>">
                <div class="stat-value" id="skippedCount">0</div>
                <div class="stat-label">Pominiętych</div>
            </div>
        </div>

        <div class=" log-container">
            <div class="log" id="logContainer"></div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <?php if ($stage < 3): ?>
                <a href="?supplier=<?php echo $supplier; ?>&stage=
            <?php echo ($stage + 1); ?>&batch_size=
            <?php echo $batch_size; ?>" class="next-stage">
                    Przejdź do Stage <?php echo ($stage + 1); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=mhi-import'); ?>" class="back-link">Wróć do
                panelu</a>
        </div>
    </div>

    <script>
        let stats = { processed: 0, success: 0, failed: 0, skipped: 0 };

        function addLog(message, type = 'info') {
            const log = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.textContent = new Date().toLocaleTimeString() + ' - ' + message;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
        }

        function updateProgress(processed, total) {
            const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = percent + '%';
            progressBar.textContent = `${percent}% (${processed}/${total})`;
        }

        function updateStats() {
            document.getElementById('processedCount').textContent = stats.processed;
            document.getElementById('successCount').textContent = stats.success;
            document.getElementById('failedCount').textContent = stats.failed;
            document.getElementById('skippedCount').textContent = stats.skipped;
        }

        addLog('🔧 System gotowy do przetwarzania Stage <?php echo $stage; ?>', 'info');
    </script>

    <?php
    flush();

    // Ładuj XML
    addLog("📄 Ładowanie pliku XML: " . basename($xml_file));
    $xml = simplexml_load_file($xml_file);
    if (!$xml) {
        addLog("❌ Błąd parsowania XML!", "error");
        exit;
    }

    $products = $xml->children();
    $total = count($products);
    $end_offset = min($offset + $batch_size, $total);

    addLog("📊 Całkowity XML: {$total} produktów");
    addLog("🎯 Przetwarzanie: produkty {$offset} - {$end_offset}");

    // Wyłącz cache
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);
    wp_suspend_cache_invalidation(true);

    $stats = ['processed' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];

    // DODATKOWY DEBUG dla Stage 3
    if ($stage == 3) {
        addLog("🔍 Stage 3 DEBUG: Sprawdzam produkty gotowe do importu obrazów...", "info");
        $ready_count = 0;
        $missing_stage2_count = 0;
        $already_done_count = 0;

        for ($check_i = $offset; $check_i < min($offset + $batch_size, $total); $check_i++) {
            if (!isset($products[$check_i]))
                continue;

            $check_sku = trim((string) $products[$check_i]->sku);
            if (empty($check_sku))
                $check_sku = trim((string) $products[$check_i]->id);

            $check_product_id = wc_get_product_id_by_sku($check_sku);
            if ($check_product_id) {
                $s2_done = get_post_meta($check_product_id, '_mhi_stage_2_done', true);
                $s3_done = get_post_meta($check_product_id, '_mhi_stage_3_done', true);

                if ($s3_done === 'yes') {
                    $already_done_count++;
                } elseif ($s2_done === 'yes') {
                    $ready_count++;
                } else {
                    $missing_stage2_count++;
                }
            }
        }

        addLog("📊 W tym batch'u: Gotowe=$ready_count | Brak Stage2=$missing_stage2_count | Już zrobione=$already_done_count", "info");
    }

    // Przetwarzaj batch produktów
    for ($i = $offset; $i < $end_offset; $i++) {
        if (!isset($products[$i]))
            break;

        $product_xml = $products[$i];
        $stats['processed']++;

        $sku = trim((string) $product_xml->sku);
        if (empty($sku))
            $sku = trim((string) $product_xml->id);

        $name = trim((string) $product_xml->name);
        if (empty($name))
            $name = 'Produkt ' . $sku;

        addLog("🔄 [{$stats['processed']}/{$batch_size}] {$name} (SKU: {$sku})");

        try {
            $result = false;

            if ($stage == 1) {
                $result = process_stage_1($product_xml, $sku, $name, $supplier);
            } elseif ($stage == 2) {
                $result = process_stage_2($product_xml, $sku, $name);
            } elseif ($stage == 3) {
                $result = process_stage_3($product_xml, $sku, $name);
            }

            if ($result === 'skipped') {
                $stats['skipped']++;
                addLog("⏭️ Pominięto - już przetworzony", "info");
            } elseif ($result) {
                $stats['success']++;
                addLog("✅ Sukces Stage {$stage}", "success");
            } else {
                $stats['failed']++;
                addLog("❌ Błąd Stage {$stage}", "error");
            }

        } catch (Exception $e) {
            $stats['failed']++;
            addLog("❌ Wyjątek: " . $e->getMessage(), "error");
        }

        // Aktualizuj interfejs
        echo '<script>stats = ' . json_encode($stats) . '; updateStats(); updateProgress(' . $stats['processed'] . ', ' . $batch_size . ');</script>';
        flush();

        usleep(50000); // 0.05s przerwy
    }

    // Włącz cache
    wp_suspend_cache_invalidation(false);
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);

    $duration = round(microtime(true) - $start_time, 2);
    addLog("🎉 STAGE {$stage} ZAKOŃCZONY w {$duration}s!", "success");
    addLog("📊 Sukces: {$stats['success']}, Błędy: {$stats['failed']}, Pominięte: {$stats['skipped']}", "info");
    // AUTO-CONTINUE - sprawdź czy są jeszcze produkty do przetworzenia
    if ($auto_continue) {
        addLog("🔄 AUTO-CONTINUE: Sprawdzam warunki kontynuacji...", "info");

        $next_offset = $offset + $batch_size;
        $products_to_process = $max_products > 0 ? $max_products : $total;
        $current_processed = $offset + $stats['processed'];

        addLog("📊 Offset: $offset → $next_offset | Produktów: $current_processed/$products_to_process | Total XML: $total", "info");

        // Sprawdź różne warunki zakończenia
        $no_more_products = $next_offset >= $total;
        $reached_limit = $max_products > 0 && $current_processed >= $max_products;

        // Specjalna logika dla każdego stage'a
        if ($stage == 3) {
            // Stage 3: Kontynuuj nawet jeśli wszystko pomijane (może Stage 1/2 nie zostały wykonane)
            $no_success_in_batch = false;
            addLog("🖼️ Stage 3: Kontynuuję nawet przy samych pominięciach (może Stage 1/2 nie ukończone)", "info");
        } else {
            // Stage 1/2: Nie przerywaj jeśli są jeszcze produkty w XML
            $no_success_in_batch = $stats['success'] == 0 && $stats['processed'] > 0 && $next_offset >= $total;
        }

        addLog("🔍 Warunki: końc_XML=" . ($no_more_products ? 'TAK' : 'NIE') . " | limit=" . ($reached_limit ? 'TAK' : 'NIE') . " | brak_sukcesów=" . ($no_success_in_batch ? 'TAK' : 'NIE'), "info");

        if ($no_more_products || $reached_limit || $no_success_in_batch) {
            // ZAKOŃCZENIE AUTO-CONTINUE
            addLog("🎉 AUTO-CONTINUE ZAKOŃCZONY!", "success");

            if ($no_more_products) {
                addLog("✅ Przyczyna: Wszystkie produkty z XML zostały przetworzone", "success");
                addLog("📊 Przetworzono: {$current_processed}/{$total} produktów z XML", "info");
            } elseif ($reached_limit) {
                addLog("🎯 Przyczyna: Osiągnięto limit {$max_products} produktów", "success");
                addLog("📊 Przetworzono: {$current_processed}/{$max_products} (limit) z {$total} dostępnych", "info");
            } elseif ($no_success_in_batch) {
                addLog("⏭️ Przyczyna: Wszystkie produkty już przetworzone (tylko pominięcia)", "info");
                addLog("📊 Stage {$stage} ukończony dla wszystkich dostępnych produktów", "info");
            }

            // Podsumowanie końcowe
            addLog("🏁 PODSUMOWANIE KOŃCOWE AUTO-CONTINUE:", "success");
            addLog("   📦 Hurtownia: " . strtoupper($supplier), "info");
            addLog("   🎯 Stage: {$stage}", "info");
            addLog("   📊 Łącznie przetworzono: {$current_processed} produktów", "info");
            addLog("   ⏱️ Łączny czas: " . round($duration, 1) . "s", "info");

            // Sugestie następnego kroku
            if ($stage < 3) {
                $next_stage = $stage + 1;
                addLog("💡 Sugestia: Przejdź do Stage {$next_stage}", "warning");
                echo '<script>
                    setTimeout(function() {
                        if (confirm("Auto-continue zakończony!\\n\\nCzy chcesz przejść do Stage ' . $next_stage . '?")) {
                            var nextUrl = "?supplier=' . $supplier . '&stage=' . $next_stage . '&batch_size=' . $batch_size . '&auto_continue=1";
                            ' . ($max_products > 0 ? 'nextUrl += "&max_products=' . $max_products . '";' : '') . '
                            window.location.href = nextUrl;
                        }
                    }, 3000);
                </script>';
            } else {
                addLog("🎉 WSZYSTKIE STAGE'Y UKOŃCZONE! Import produktów zakończony.", "success");
                echo '<script>
                    setTimeout(function() {
                        addLog("🔗 Możesz teraz wrócić do managera cronów", "info");
                    }, 2000);
                </script>';
            }
        } else {
            // KONTYNUACJA
            addLog("🚀 KONTYNUACJA AUTO-CONTINUE!", "success");
            $remaining = min($products_to_process - $next_offset, $total - $next_offset);
            addLog("🔄 Auto-continue: Pozostało {$remaining} produktów", "info");
            addLog("📊 Postęp: {$current_processed}/{$products_to_process} (" . round(($current_processed / $products_to_process) * 100, 1) . "%)", "info");
            addLog("⏳ Przekierowanie za 5 sekund do następnego batch'a...", "warning");

            $next_url = "?supplier={$supplier}&stage={$stage}&batch_size={$batch_size}&offset={$next_offset}&auto_continue=1";
            if ($max_products > 0) {
                $next_url .= "&max_products={$max_products}";
            }

            addLog("🔗 Następny URL: " . $next_url, "info");

            echo '<script>
                setTimeout(function() {
                    addLog("🚀 Przekierowanie do batch\'a " + Math.ceil(' . $next_offset . '/' . $batch_size . ') + "...", "info");
                    window.location.href = "' . $next_url . '";
                }, 5000);
            </script>';
        }
    } else {
        addLog("❌ Auto-continue WYŁĄCZONY (parametr auto_continue nie jest = 1)", "warning");
    }

    // FUNKCJE PRZETWARZANIA STAGE'ÓW
    
    function process_stage_1($product_xml, $sku, $name, $supplier)
    {
        // Sprawdź czy produkt już ma Stage 1
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

        // Kategorie - obsługa zarówno Malfini (string) jak i Axpol (XML lista)
        if (isset($product_xml->categories)) {
            $categories_data = $product_xml->categories;

            // Sprawdź czy to string (Malfini) czy XML object (Axpol)
            if (isset($categories_data->category)) {
                // AXPOL format - lista kategorii
                $category_ids = process_product_categories($categories_data);
            } else {
                // MALFINI format - jeden string
                $categories_text = html_entity_decode(trim((string) $categories_data), ENT_QUOTES, 'UTF-8');
                $category_ids = process_product_categories($categories_text);
            }

            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'product_cat');
                addLog("   📂 Kategorie: " . count($category_ids) . " kategorii", "info");
            }
        }

        // Marki
        $brand_name = find_brand_in_xml($product_xml);
        if (!empty($brand_name)) {
            process_product_brand($brand_name, $product_id);
        }

        // Przetwórz meta_data z XML (Axpol ma dodatkowe dane)
        if (isset($product_xml->meta_data)) {
            foreach ($product_xml->meta_data as $meta) {
                $key = trim((string) $meta->key);
                $value = trim((string) $meta->value);

                if (!empty($key) && !empty($value)) {
                    update_post_meta($product_id, $key, $value);
                    addLog("   📝 Meta: $key = $value", "info");
                }
            }
        }

        // Oznacz Stage 1 jako ukończony
        update_post_meta($product_id, '_mhi_stage_1_done', 'yes');
        update_post_meta($product_id, '_mhi_supplier', $supplier);
        update_post_meta($product_id, '_mhi_imported', 'yes');

        return true;
    }

    function process_stage_2($product_xml, $sku, $name)
    {
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id)
            return false;

        // Sprawdź czy Stage 1 został ukończony
        if (get_post_meta($product_id, '_mhi_stage_1_done', true) !== 'yes') {
            addLog("⚠️ Stage 1 nie został ukończony - pomijam", "warning");
            return 'skipped';
        }

        // Sprawdź czy Stage 2 już ukończony
        if (get_post_meta($product_id, '_mhi_stage_2_done', true) === 'yes') {
            return 'skipped';
        }

        $product = wc_get_product($product_id);
        if (!$product)
            return false;

        // Przetwarzaj atrybuty
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

                // Utwórz atrybut globalny
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

                    delete_transient('wc_attribute_taxonomies');
                    if (function_exists('wc_create_attribute_taxonomies')) {
                        wc_create_attribute_taxonomies();
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
                    // Obsługa różnych formatów: Malfini vs Axpol
                    if (isset($attribute_xml->variation)) {
                        // MALFINI format - ma pole variation
                        $is_variation = trim((string) $attribute_xml->variation) === 'yes';
                    } else {
                        // AXPOL format - sprawdź czy może być wariantem
                        $attr_name_lower = strtolower($attr_name);
                        $has_multiple_values = strpos($attr_value, ',') !== false;

                        // Typowe nazwy wariantów
                        $variant_names = ['kolor', 'rozmiar', 'wielkość', 'size', 'color', 'colour'];
                        $is_potential_variant = $has_multiple_values || in_array($attr_name_lower, $variant_names);

                        // Dla Axpol domyślnie nie tworzymy wariantów
                        $is_variation = false;

                        addLog("   🏷️ AXPOL: $attr_name = $attr_value" . ($is_potential_variant ? ' (potencjał wariantu)' : ''), "info");
                    }

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

                // Generuj warianty jeśli to variable product
                if ($product->get_type() === 'variable') {
                    $variation_attributes = array_filter($wc_attributes, function ($attr) {
                        return $attr->get_variation();
                    });

                    if (!empty($variation_attributes)) {
                        generate_product_variations($product_id, $variation_attributes, $product_xml);
                    }
                }
            }
        }

        // Oznacz Stage 2 jako ukończony
        update_post_meta($product_id, '_mhi_stage_2_done', 'yes');
        return true;
    }

    function process_stage_3($product_xml, $sku, $name)
    {
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            addLog("❌ Stage 3: Produkt SKU $sku nie znaleziony", "error");
            return false;
        }

        // Debug: sprawdź statusy stage'ów
        $stage_1_done = get_post_meta($product_id, '_mhi_stage_1_done', true);
        $stage_2_done = get_post_meta($product_id, '_mhi_stage_2_done', true);
        $stage_3_done = get_post_meta($product_id, '_mhi_stage_3_done', true);

        addLog("🔍 Status dla $sku: Stage1=$stage_1_done | Stage2=$stage_2_done | Stage3=$stage_3_done", "info");

        // Sprawdź czy Stage 2 został ukończony
        if ($stage_2_done !== 'yes') {
            addLog("⚠️ Stage 2 nie został ukończony dla $sku (wartość: '$stage_2_done') - pomijam", "warning");
            return 'skipped';
        }

        // Sprawdź czy Stage 3 już ukończony
        if ($stage_3_done === 'yes') {
            addLog("⏭️ Stage 3 już ukończony dla $sku", "info");
            return 'skipped';
        }

        addLog("🖼️ Stage 3: Rozpoczynam import obrazów dla $sku", "info");

        // Przetwarzaj obrazy
        if (isset($product_xml->images->image)) {
            $images = $product_xml->images->image;
            addLog("📷 Znaleziono sekcję images->image", "info");

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

            addLog("📸 Liczba obrazów do przetworzenia: " . count($images), "info");

            if (isset($_GET['replace_images']) && $_GET['replace_images'] === '1') {
                clean_product_gallery($product_id, false);
                addLog("🗑️ Wyczyszczono istniejące obrazy", "info");
            }

            $gallery_result = import_product_gallery($images, $product_id);
            if (!$gallery_result['success']) {
                addLog("❌ Błąd importu galerii: " . $gallery_result['message'], "error");
                return false;
            } else {
                addLog("✅ Import galerii zakończony: " . $gallery_result['message'], "success");
            }
        } else {
            addLog("⚠️ Brak sekcji images->image w XML dla $sku", "warning");
            // Jeśli nie ma obrazów, to i tak oznaczamy jako ukończone
        }

        // Oznacz Stage 3 jako ukończony
        update_post_meta($product_id, '_mhi_stage_3_done', 'yes');
        return true;
    }

    // FUNKCJE POMOCNICZE (skrócone wersje z oryginalnego import.php)
    
    function process_product_categories($categories_text)
    {
        if (empty($categories_text)) {
            return [];
        }

        $category_ids = [];

        // Sprawdź czy categories_text to string czy XML object
        if (is_string($categories_text)) {
            // MALFINI format - jeden string z > jako separatorem
            $categories = explode('>', $categories_text);
        } else {
            // AXPOL format - lista kategorii w XML
            $categories = [];
            if (isset($categories_text->category)) {
                foreach ($categories_text->category as $category) {
                    $cat_name = trim((string) $category);
                    if (!empty($cat_name)) {
                        // Sprawdź czy ma > separator (podkategoria)
                        if (strpos($cat_name, ' > ') !== false) {
                            $subcats = explode(' > ', $cat_name);
                            foreach ($subcats as $subcat) {
                                $categories[] = trim($subcat);
                            }
                        } else {
                            $categories[] = $cat_name;
                        }
                    }
                }
            }
        }

        // Usuń duplikaty i puste elementy
        $categories = array_unique(array_filter(array_map('trim', $categories)));

        $parent_id = 0;
        foreach ($categories as $category_name) {
            if (empty($category_name))
                continue;

            // Sprawdź czy kategoria już istnieje
            $existing_term = get_term_by('name', $category_name, 'product_cat');
            if ($existing_term) {
                $category_ids[] = $existing_term->term_id;
                $parent_id = $existing_term->term_id; // Następna będzie podkategorią
            } else {
                // Utwórz nową kategorię
                $term_data = wp_insert_term(
                    $category_name,
                    'product_cat',
                    array('parent' => $parent_id)
                );

                if (!is_wp_error($term_data)) {
                    $category_ids[] = $term_data['term_id'];
                    $parent_id = $term_data['term_id']; // Następna będzie podkategorią
                }
            }
        }

        return $category_ids;
    }

    function find_brand_in_xml($product_xml)
    {
        // Szukaj w atrybutach
        if (isset($product_xml->attributes->attribute)) {
            foreach ($product_xml->attributes->attribute as $attr) {
                $attr_name = strtolower(trim((string) $attr->name));
                if (in_array($attr_name, ['marka', 'brand', 'manufacturer', 'producent', 'firma'])) {
                    return trim((string) $attr->value);
                }
            }
        }
        // Szukaj w bezpośrednich polach
        if (isset($product_xml->brand))
            return trim((string) $product_xml->brand);
        if (isset($product_xml->manufacturer))
            return trim((string) $product_xml->manufacturer);
        return '';
    }

    function process_product_brand($brand_name, $product_id)
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

    function clean_product_gallery($product_id, $remove_featured = false)
    {
        $removed_count = 0;
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery_ids)) {
            $gallery_ids = explode(',', $gallery_ids);
            foreach ($gallery_ids as $attachment_id) {
                if (wp_delete_attachment($attachment_id, true)) {
                    $removed_count++;
                }
            }
            delete_post_meta($product_id, '_product_image_gallery');
        }

        if ($remove_featured) {
            $featured_id = get_post_thumbnail_id($product_id);
            if ($featured_id && wp_delete_attachment($featured_id, true)) {
                delete_post_thumbnail($product_id);
                $removed_count++;
            }
        }

        return ['removed_count' => $removed_count];
    }

    function import_product_gallery($images, $product_id)
    {
        $image_ids = [];
        $imported_count = 0;

        addLog("🖼️ Rozpoczynam import galerii dla produktu ID: $product_id", "info");

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
                addLog("⚠️ Nieprawidłowy URL obrazu [$index]: '$image_url'", "warning");
                continue;
            }

            addLog("📥 Importuję obraz [$index]: $image_url", "info");
            $is_featured = ($index === 0);
            $attachment_id = import_product_image($image_url, $product_id, $is_featured);

            if ($attachment_id) {
                $image_ids[] = $attachment_id;
                $imported_count++;
                addLog("✅ Obraz [$index] zaimportowany: ID $attachment_id", "success");
            } else {
                addLog("❌ Błąd importu obrazu [$index]: $image_url", "error");
            }
        }

        if ($imported_count > 0) {
            addLog("✅ Zaimportowano $imported_count obrazów", "success");

            $featured_id = get_post_thumbnail_id($product_id);
            $gallery_ids = array_filter($image_ids, function ($id) use ($featured_id) {
                return $id != $featured_id;
            });

            if (!empty($gallery_ids)) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                $product = wc_get_product($product_id);
                if ($product) {
                    $product->set_gallery_image_ids($gallery_ids);
                    $product->save();
                }
                addLog("📷 Ustawiono galerię: " . count($gallery_ids) . " obrazów", "info");
            }

            return ['success' => true, 'message' => "Zaimportowano $imported_count obrazów", 'imported_count' => $imported_count];
        }

        addLog("❌ Nie udało się zaimportować żadnego obrazu!", "error");
        return ['success' => false, 'message' => "Nie udało się zaimportować żadnego obrazu", 'imported_count' => 0];
    }

    function import_product_image($image_url, $product_id, $is_featured = false)
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
        addLog("🌐 Pobieram obraz z: $image_url", "info");
        $response = wp_remote_get($image_url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPressBot/1.0)'
        ]);

        if (is_wp_error($response)) {
            addLog("❌ Błąd wp_remote_get: " . $response->get_error_message(), "error");
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            addLog("❌ HTTP kod: $response_code dla URL: $image_url", "error");
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            addLog("❌ Pusta odpowiedź serwera dla: $image_url", "error");
            return false;
        }

        addLog("✅ Pobrano " . strlen($image_data) . " bajtów", "info");

        // Generuj losową datę
        $months_back = rand(1, 18);
        $timestamp = strtotime("-{$months_back} months");
        $upload_dir = wp_upload_dir(date('Y/m', $timestamp));

        if (!wp_mkdir_p($upload_dir['path'])) {
            addLog("❌ Nie można utworzyć folderu: " . $upload_dir['path'], "error");
            return false;
        }

        addLog("📁 Folder uploads: " . $upload_dir['path'], "info");

        // Przygotuj plik
        $filename = time() . '_' . basename($image_url);
        $filename = sanitize_file_name($filename);
        $filename = preg_replace('/\?.*$/', '', $filename);

        // Konwertuj do WebP jeśli możliwe
        $webp_filename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
        $file_path = $upload_dir['path'] . '/' . $webp_filename;

        if (function_exists('imagewebp') && function_exists('imagecreatefromstring')) {
            $source = @imagecreatefromstring($image_data);
            if ($source !== false) {
                // Zmień rozmiar jeśli za duży
                $width = imagesx($source);
                $height = imagesy($source);

                if ($width > 1200) {
                    $new_width = 1200;
                    $new_height = intval($height * (1200 / $width));
                    $resized = imagecreatetruecolor($new_width, $new_height);
                    imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagedestroy($source);
                    $source = $resized;
                }

                if (@imagewebp($source, $file_path, 85)) {
                    imagedestroy($source);
                    $filename = $webp_filename;
                } else {
                    imagedestroy($source);
                    $file_path = $upload_dir['path'] . '/' . $filename;
                    file_put_contents($file_path, $image_data);
                }
            } else {
                $file_path = $upload_dir['path'] . '/' . $filename;
                file_put_contents($file_path, $image_data);
            }
        } else {
            $file_path = $upload_dir['path'] . '/' . $filename;
            file_put_contents($file_path, $image_data);
        }

        // Dodaj do biblioteki
        $filetype = wp_check_filetype($filename);
        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => pathinfo($filename, PATHINFO_FILENAME),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_date' => date('Y-m-d H:i:s', $timestamp),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp)
        ];

        addLog("💾 Zapisuję attachment do WP...", "info");
        $attach_id = wp_insert_attachment($attachment, $file_path, $product_id);
        if (!$attach_id) {
            addLog("❌ Błąd wp_insert_attachment", "error");
            @unlink($file_path);
            return false;
        }

        addLog("✅ Attachment utworzony: ID $attach_id", "success");

        update_post_meta($attach_id, '_mhi_source_url', $image_url);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $metadata = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $metadata);

        if ($is_featured) {
            set_post_thumbnail($product_id, $attach_id);
        }

        return $attach_id;
    }

    function generate_product_variations($product_id, $variation_attributes, $product_xml)
    {
        // Pobierz dane bazowe z XML
        $base_data = [
            'regular_price' => str_replace(',', '.', trim((string) $product_xml->regular_price)),
            'sale_price' => str_replace(',', '.', trim((string) $product_xml->sale_price)),
            'weight' => trim((string) $product_xml->weight),
            'stock_quantity' => trim((string) $product_xml->stock_quantity)
        ];

        // Przygotuj kombinacje
        $combinations = [];
        $attribute_combinations = [];

        foreach ($variation_attributes as $attr) {
            $taxonomy = $attr->get_name();
            $term_ids = $attr->get_options();
            $terms = [];
            foreach ($term_ids as $term_id) {
                $term = get_term($term_id);
                if ($term && !is_wp_error($term)) {
                    $terms[] = $term->slug;
                }
            }
            if (!empty($terms)) {
                $attribute_combinations[$taxonomy] = $terms;
            }
        }

        if (empty($attribute_combinations))
            return false;

        // Generuj kombinacje
        $combinations = [[]];
        foreach ($attribute_combinations as $taxonomy => $values) {
            $new_combinations = [];
            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $new_combination = $combination;
                    $new_combination[$taxonomy] = $value;
                    $new_combinations[] = $new_combination;
                }
            }
            $combinations = $new_combinations;
        }

        // Utwórz warianty
        foreach ($combinations as $combination) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes($combination);

            if (is_numeric($base_data['regular_price']) && floatval($base_data['regular_price']) > 0) {
                $variation->set_regular_price($base_data['regular_price']);
            }
            if (is_numeric($base_data['sale_price']) && floatval($base_data['sale_price']) > 0) {
                $variation->set_sale_price($base_data['sale_price']);
            }
            if (!empty($base_data['weight'])) {
                $variation->set_weight($base_data['weight']);
            }
            if (is_numeric($base_data['stock_quantity'])) {
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity((int) $base_data['stock_quantity']);
            }

            $variation->set_status('publish');
            $variation->save();
        }

        WC_Product_Variable::sync($product_id);
        return true;
    }

    function addLog($message, $type = "info")
    {
        echo '<script>addLog(' . json_encode($message) . ', "' . $type . '");</script>';
        flush();
    }

    ?>
    </div>
</body>

</html>
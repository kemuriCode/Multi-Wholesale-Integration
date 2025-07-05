<?php
/**
 * ANDA Dedykowany Importer
 * Wydajny import produktów ANDA z obsługą wariantów (kolory + rozmiary)
 * 
 * URL: /wp-content/plugins/multi-wholesale-integration/anda-import.php
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
$stage = isset($_GET['stage']) ? (int) $_GET['stage'] : 1;
$batch_size = isset($_GET['batch_size']) ? (int) $_GET['batch_size'] : 50;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$auto_continue = isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1';
$auto_stage = isset($_GET['auto_stage']) && $_GET['auto_stage'] === '1'; // NOWY: automatyczne przejście między stage'ami
$force_update = isset($_GET['force_update']) && $_GET['force_update'] === '1';
$max_products = isset($_GET['max_products']) ? (int) $_GET['max_products'] : 0;

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('WooCommerce nie jest aktywne!');
}

// Zwiększ limity dla ANDA
ini_set('memory_limit', '2048M');
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
    <title>🔥 ANDA Import - Stage <?php echo $stage; ?></title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(90deg, #4CAF50, #45a049);
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
            color: #4CAF50;
            font-weight: bold;
        }

        .log-warning {
            color: #FF9800;
        }

        .log-error {
            color: #f44336;
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
            border-left: 4px solid #007cba;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007cba;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔥 ANDA Dedykowany Importer</h1>
            <p>Stage <?php echo $stage; ?> | Batch: <?php echo $offset + 1; ?>-<?php echo $end_offset; ?> z
                <?php echo $total; ?>
            </p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div>Produktów w XML</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $batch_size; ?></div>
                <div>Rozmiar batcha</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stage; ?></div>
                <div>Aktualny Stage</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="progress-percent">0%</div>
                <div>Postęp</div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
        </div>

        <div class="log-container" id="log-container"></div>

        <div class="controls">
            <a href="?stage=1&batch_size=<?php echo $batch_size; ?>&auto_continue=1" class="btn btn-primary">📦 Stage 1
                Auto</a>
            <a href="?stage=2&batch_size=<?php echo $batch_size; ?>&auto_continue=1" class="btn btn-warning">🎯 Stage 2
                Auto</a>
            <a href="?stage=3&batch_size=<?php echo $batch_size; ?>&auto_continue=1" class="btn btn-success">📷 Stage 3
                Auto</a>
        </div>
    </div>

    <script>
        function addLog(message, type = 'info') {
            const container = document.getElementById('log-container');
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
        }

        function updateProgress(current, total) {
            const percent = Math.round((current / total) * 100);
            document.getElementById('progress-percent').textContent = percent + '%';
            document.getElementById('progress-fill').style.width = percent + '%';
        }
    </script>

    <?php

    // Główna logika importu
    addLog("🔥 ANDA Import: Rozpoczynanie Stage $stage dla produktów $offset-$end_offset", "info");

    // Wyłącz cache dla wydajności
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);
    wp_suspend_cache_invalidation(true);

    $processed = 0;
    $imported = 0;
    $errors = 0;
    $skipped = 0;

    if ($stage == 1) {
        // STAGE 1: Filtrowanie na czyste SKU + podstawowe dane
        addLog("📦 Stage 1: Filtrowanie produktów ANDA na czyste SKU...", "info");

        // Pierwszy batch - filtruj wszystkie produkty
        if ($offset == 0) {
            $filtered_products = anda_filter_clean_products($products);
            // Zapisz przefiltrowane produkty w sesji
            $_SESSION['anda_filtered_products'] = $filtered_products;
            $_SESSION['anda_filtered_total'] = count($filtered_products);
        } else {
            // Kolejne batche - użyj z sesji
            $filtered_products = $_SESSION['anda_filtered_products'] ?? [];
        }

        $total_after_filter = $_SESSION['anda_filtered_total'] ?? count($filtered_products);

        addLog("✅ Przefiltrowano: $total produktów → $total_after_filter głównych produktów", "success");

        // Przetwarzaj batch z przefiltrowanych produktów
        $batch_products = array_slice($filtered_products, $offset, $batch_size);

        foreach ($batch_products as $product_xml) {
            $result = anda_process_stage_1($product_xml);

            if ($result === 'imported') {
                $imported++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }
            $processed++;

            updateProgress($processed, count($batch_products));
        }

        // Aktualizuj total dla auto-continue
        $total = $total_after_filter;

    } elseif ($stage == 2) {
        // STAGE 2: Tworzenie wariantów
        addLog("🎯 Stage 2: Tworzenie wariantów ANDA...", "info");

        for ($i = $offset; $i < $end_offset; $i++) {
            if (!isset($products[$i]))
                continue;

            $product_xml = $products[$i];
            $sku = trim((string) $product_xml->sku);

            // Sprawdź czy to czysty SKU (bez - i _)
            if (anda_is_clean_sku($sku)) {
                $result = anda_process_stage_2($sku);

                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }
            } else {
                $skipped++;
            }

            $processed++;
            updateProgress($processed, $batch_size);
        }

    } elseif ($stage == 3) {
        // STAGE 3: Import obrazów
        addLog("📷 Stage 3: Import obrazów ANDA...", "info");

        for ($i = $offset; $i < $end_offset; $i++) {
            if (!isset($products[$i]))
                continue;

            $product_xml = $products[$i];
            $sku = trim((string) $product_xml->sku);

            // Sprawdź czy to czysty SKU
            if (anda_is_clean_sku($sku)) {
                $result = anda_process_stage_3($product_xml, $sku);

                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }
            } else {
                $skipped++;
            }

            $processed++;
            updateProgress($processed, $batch_size);
        }
    }

    // Przywróć cache
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);
    wp_suspend_cache_invalidation(false);

    $duration = round(microtime(true) - $start_time, 2);

    addLog("✅ ANDA Import zakończony!", "success");
    addLog("📊 Statystyki: Przetworzono=$processed | Import=$imported | Pominięto=$skipped | Błędy=$errors", "info");
    addLog("⏱️ Czas wykonania: {$duration}s", "info");

    // Auto-continue logic - POPRAWIONA WERSJA Z AUTO-STAGE
    if ($auto_continue && $end_offset < $total) {
        $next_offset = $end_offset;
        $next_url = "?stage=$stage&batch_size=$batch_size&offset=$next_offset&auto_continue=1";

        if ($auto_stage) {
            $next_url .= "&auto_stage=1";
        }
        if ($force_update) {
            $next_url .= "&force_update=1";
        }
        if ($max_products > 0) {
            $next_url .= "&max_products=$max_products";
            if ($next_offset >= $max_products) {
                addLog("🛑 Osiągnięto limit max_products: $max_products", "warning");
                anda_auto_stage_progression($stage, $batch_size, $auto_stage, $force_update, $max_products);
            } else {
                addLog("🔄 Auto-continue: Następny batch za 1 sekundę... ($next_offset/$total)", "info");
                echo '<script>setTimeout(function() { window.location.href = "' . $next_url . '"; }, 1000);</script>';
            }
        } else {
            addLog("🔄 Auto-continue: Następny batch za 1 sekundę... ($next_offset/$total)", "info");
            echo '<script>setTimeout(function() { window.location.href = "' . $next_url . '"; }, 1000);</script>';
        }
    } else if ($auto_continue && $end_offset >= $total) {
        addLog("✅ Stage $stage ukończony - wszystkie batche przetworzone!", "success");
        anda_auto_stage_progression($stage, $batch_size, $auto_stage, $force_update, $max_products);
    }

    /**
     * Filtruje produkty ANDA - zostawia tylko czyste SKU
     */
    function anda_filter_clean_products($products)
    {
        $clean_products = [];
        $processed_skus = [];
        $variant_images = [];

        // Patterny dla wariantów ANDA
        $color_pattern = '/-(\d{2})$/';
        $size_pattern = '/_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?)$/';
        $combined_pattern = '/-(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?)$/';

        foreach ($products as $product) {
            $sku = trim((string) $product->sku);
            $is_variant = false;
            $base_sku = '';

            // Sprawdź czy to wariant
            if (preg_match($combined_pattern, $sku, $matches)) {
                $base_sku = preg_replace($combined_pattern, '', $sku);
                $is_variant = true;
            } elseif (preg_match($color_pattern, $sku, $matches)) {
                $base_sku = preg_replace($color_pattern, '', $sku);
                $is_variant = true;
            } elseif (preg_match($size_pattern, $sku, $matches)) {
                $base_sku = preg_replace($size_pattern, '', $sku);
                $is_variant = true;
            }

            if ($is_variant) {
                // Zbierz zdjęcia wariantu
                if (isset($product->images) && $product->images->image) {
                    if (!isset($variant_images[$base_sku])) {
                        $variant_images[$base_sku] = [];
                    }

                    foreach ($product->images->image as $image) {
                        $image_url = trim((string) $image);
                        if (!empty($image_url) && !in_array($image_url, $variant_images[$base_sku])) {
                            $variant_images[$base_sku][] = $image_url;
                        }
                    }
                }

                // Sprawdź czy mamy główny produkt
                if (!isset($processed_skus[$base_sku])) {
                    // Znajdź główny produkt lub stwórz z wariantu
                    $main_product = null;
                    foreach ($products as $check_product) {
                        if (trim((string) $check_product->sku) === $base_sku) {
                            $main_product = $check_product;
                            break;
                        }
                    }

                    if (!$main_product) {
                        // Stwórz główny z wariantu
                        $main_product = clone $product;
                        $main_product->sku = $base_sku;
                    }

                    $clean_products[] = $main_product;
                    $processed_skus[$base_sku] = true;
                }
            } else {
                // Czysty SKU
                if (!isset($processed_skus[$sku])) {
                    $clean_products[] = $product;
                    $processed_skus[$sku] = true;
                }
            }
        }

        // Dodaj zdjęcia wariantów do głównych produktów
        foreach ($clean_products as $main_product) {
            $main_sku = trim((string) $main_product->sku);

            if (isset($variant_images[$main_sku]) && !empty($variant_images[$main_sku])) {
                if (!isset($main_product->images)) {
                    $main_product->addChild('images', '');
                }

                $existing_images = [];
                if (isset($main_product->images->image)) {
                    foreach ($main_product->images->image as $img) {
                        $existing_images[] = trim((string) $img);
                    }
                }

                foreach ($variant_images[$main_sku] as $variant_image) {
                    if (!in_array($variant_image, $existing_images)) {
                        $main_product->images->addChild('image', $variant_image);
                    }
                }
            }
        }

        return $clean_products;
    }

    /**
     * Sprawdza czy SKU jest czysty (bez - i _)
     */
    function anda_is_clean_sku($sku)
    {
        return !preg_match('/-\d{2}$/', $sku) && !preg_match('/_[A-Z0-9]+$/i', $sku);
    }

    /**
     * STAGE 1: Tworzy podstawowy produkt ANDA - POPRAWIONA WERSJA
     */
    function anda_process_stage_1($product_xml)
    {
        global $force_update;

        $sku = trim((string) $product_xml->sku);
        $name = trim((string) $product_xml->name);

        // Sprawdź czy już istnieje
        $product_id = wc_get_product_id_by_sku($sku);

        // ZAWSZE NADPISUJ jeśli force_update lub jeśli to pierwszy raz
        if ($product_id && get_post_meta($product_id, '_mhi_stage_1_done', true) === 'yes' && !$force_update) {
            addLog("⏭️ Stage 1: $sku - już przetworzony", "info");
            return 'skipped';
        }

        // Usuń złe produkty ANDA bez czystych SKU
        anda_clean_bad_anda_products($sku);

        try {
            $is_update = (bool) $product_id;
            $product = $is_update ? wc_get_product($product_id) : new WC_Product();

            // Podstawowe dane
            $product->set_name($name);
            $product->set_description((string) $product_xml->description);
            $product->set_short_description((string) $product_xml->short_description);
            $product->set_sku($sku);
            $product->set_status('publish');

            // Ceny ANDA
            $regular_price = null;
            if (isset($product_xml->meta_data->meta)) {
                foreach ($product_xml->meta_data->meta as $meta) {
                    $key = trim((string) $meta->key);
                    $value = trim((string) $meta->value);
                    if ($key === '_anda_price_listPrice' && !empty($value)) {
                        $regular_price = str_replace(',', '.', $value);
                        break;
                    }
                }
            }

            if (empty($regular_price)) {
                $regular_price = str_replace(',', '.', trim((string) $product_xml->regular_price));
            }

            if (is_numeric($regular_price) && floatval($regular_price) > 0) {
                $product->set_regular_price($regular_price);
            }

            // Cena promocyjna
            $sale_price = null;
            if (isset($product_xml->meta_data->meta)) {
                foreach ($product_xml->meta_data->meta as $meta) {
                    $key = trim((string) $meta->key);
                    $value = trim((string) $meta->value);
                    if ($key === '_anda_price_discountPrice' && !empty($value)) {
                        $sale_price = str_replace(',', '.', $value);
                        break;
                    }
                }
            }

            if (empty($sale_price)) {
                $sale_price = str_replace(',', '.', trim((string) $product_xml->sale_price));
            }

            if (is_numeric($sale_price) && floatval($sale_price) > 0) {
                $product->set_sale_price($sale_price);
            }

            // Stan magazynowy
            $stock_qty = trim((string) $product_xml->stock_quantity);
            if (is_numeric($stock_qty)) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity((int) $stock_qty);
                $product->set_stock_status($stock_qty > 0 ? 'instock' : 'outofstock');
            }

            // Wymiary
            if (!empty((string) $product_xml->weight)) {
                $product->set_weight((string) $product_xml->weight);
            }

            $product_id = $product->save();
            if (!$product_id) {
                throw new Exception("Nie można zapisać produktu: $name");
            }

            // Kategorie ANDA
            if (isset($product_xml->categories)) {
                $category_ids = anda_process_categories($product_xml->categories);
                if (!empty($category_ids)) {
                    wp_set_object_terms($product_id, $category_ids, 'product_cat');
                }
            }

            // Meta data
            if (isset($product_xml->meta_data->meta)) {
                foreach ($product_xml->meta_data->meta as $meta) {
                    $key = trim((string) $meta->key);
                    $value = trim((string) $meta->value);
                    if (!empty($key) && !empty($value)) {
                        update_post_meta($product_id, $key, $value);
                    }
                }
            }

            // Oznacz jako ukończony
            update_post_meta($product_id, '_mhi_stage_1_done', 'yes');

            addLog("✅ Stage 1: $sku - $name", "success");
            return 'imported';

        } catch (Exception $e) {
            addLog("❌ Stage 1 błąd: $sku - " . $e->getMessage(), "error");
            return 'error';
        }
    }

    /**
     * STAGE 2: Tworzy warianty ANDA - POPRAWIONA WERSJA
     */
    function anda_process_stage_2($base_sku)
    {
        global $force_update, $xml;

        $product_id = wc_get_product_id_by_sku($base_sku);
        if (!$product_id) {
            addLog("⚠️ Stage 2: Nie znaleziono produktu dla SKU: $base_sku", "warning");
            return 'skipped';
        }

        // Sprawdź Stage 1
        if (get_post_meta($product_id, '_mhi_stage_1_done', true) !== 'yes') {
            addLog("⚠️ Stage 2: $base_sku - brak Stage 1", "warning");
            return 'skipped';
        }

        // Sprawdź Stage 2 - NADPISUJ jeśli force_update
        if (get_post_meta($product_id, '_mhi_stage_2_done', true) === 'yes' && !$force_update) {
            addLog("⏭️ Stage 2: $base_sku - już przetworzony", "info");
            return 'skipped';
        }

        // Usuń istniejące warianty jeśli force_update
        if ($force_update) {
            anda_remove_existing_variations($product_id);
        }

        try {
            $variants = anda_find_variants($base_sku, $xml);

            if (empty($variants)) {
                // Oznacz jako ukończony nawet bez wariantów
                update_post_meta($product_id, '_mhi_stage_2_done', 'yes');
                return 'skipped';
            }

            // Ustaw jako variable
            $product = wc_get_product($product_id);
            if ($product->get_type() !== 'variable') {
                wp_set_object_terms($product_id, 'variable', 'product_type');
                $product = new WC_Product_Variable($product_id);
            }

            // Stwórz atrybuty
            $colors = [];
            $sizes = [];

            foreach ($variants as $variant_data) {
                if (!empty($variant_data['color'])) {
                    $colors[$variant_data['color']] = $variant_data['color'];
                }
                if (!empty($variant_data['size'])) {
                    $sizes[$variant_data['size']] = $variant_data['size'];
                }
            }

            $wc_attributes = [];

            if (!empty($colors)) {
                $color_attr = anda_create_color_attribute($colors, $product_id);
                if ($color_attr)
                    $wc_attributes[] = $color_attr;
            }

            if (!empty($sizes)) {
                $size_attr = anda_create_size_attribute($sizes, $product_id);
                if ($size_attr)
                    $wc_attributes[] = $size_attr;
            }

            // Przypisz atrybuty
            if (!empty($wc_attributes)) {
                $existing_attributes = $product->get_attributes();
                $all_attributes = array_merge($existing_attributes, $wc_attributes);
                $product->set_attributes($all_attributes);
                $product->save();
            }

            // Utwórz warianty
            $created = 0;
            foreach ($variants as $variant_sku => $variant_data) {
                if (anda_create_variation($product_id, $variant_sku, $variant_data)) {
                    $created++;
                }
            }

            // Synchronizuj
            if ($created > 0) {
                WC_Product_Variable::sync($product_id);
                wc_delete_product_transients($product_id);
            }

            update_post_meta($product_id, '_mhi_stage_2_done', 'yes');

            addLog("✅ Stage 2: $base_sku - utworzono $created wariantów", "success");
            return 'imported';

        } catch (Exception $e) {
            addLog("❌ Stage 2 błąd: $base_sku - " . $e->getMessage(), "error");
            return 'error';
        }
    }

    /**
     * STAGE 3: Import obrazów - POPRAWIONA WERSJA
     */
    function anda_process_stage_3($product_xml, $sku)
    {
        global $force_update;

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            addLog("⚠️ Stage 3: Nie znaleziono produktu dla SKU: $sku", "warning");
            return 'skipped';
        }

        // NIE wymagaj Stage 2 - może nie mieć wariantów
        // if (get_post_meta($product_id, '_mhi_stage_2_done', true) !== 'yes') {
        //     addLog("⚠️ Stage 3: $sku - brak Stage 2", "warning");
        //     return 'skipped';
        // }
    
        // Sprawdź Stage 3 - NADPISUJ jeśli force_update
        if (get_post_meta($product_id, '_mhi_stage_3_done', true) === 'yes' && !$force_update) {
            addLog("⏭️ Stage 3: $sku - już przetworzony", "info");
            return 'skipped';
        }

        try {
            $imported_images = 0;

            if (isset($product_xml->images->image)) {
                $images = $product_xml->images->image;

                // Wyczyść galerię
                anda_clean_gallery($product_id);

                $gallery_ids = [];
                $main_image_set = false;

                foreach ($images as $image_url) {
                    $url = trim((string) $image_url);
                    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $attachment_id = anda_import_image($url, $product_id, !$main_image_set);
                    if ($attachment_id) {
                        if (!$main_image_set) {
                            $main_image_set = true;
                        } else {
                            $gallery_ids[] = $attachment_id;
                        }
                        $imported_images++;
                    }
                }

                // Ustaw galerię
                if (!empty($gallery_ids)) {
                    $product = wc_get_product($product_id);
                    $product->set_gallery_image_ids($gallery_ids);
                    $product->save();
                }
            }

            update_post_meta($product_id, '_mhi_stage_3_done', 'yes');

            addLog("✅ Stage 3: $sku - zaimportowano $imported_images obrazów", "success");
            return 'imported';

        } catch (Exception $e) {
            addLog("❌ Stage 3 błąd: $sku - " . $e->getMessage(), "error");
            return 'error';
        }
    }

    // Skrócone funkcje pomocnicze z powodu limitu długości
    function anda_find_variants($base_sku, $xml)
    {
        $variants = [];
        $color_pattern = '/^' . preg_quote($base_sku, '/') . '-(\d{2})$/';
        $size_pattern = '/^' . preg_quote($base_sku, '/') . '_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?)$/';
        $combined_pattern = '/^' . preg_quote($base_sku, '/') . '-(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?)$/';

        foreach ($xml->children() as $product_xml) {
            $variant_sku = trim((string) $product_xml->sku);

            if (preg_match($combined_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'combined',
                    'color' => $matches[1],
                    'size' => $matches[2],
                    'xml' => $product_xml
                ];
            } elseif (preg_match($color_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'color',
                    'color' => $matches[1],
                    'xml' => $product_xml
                ];
            } elseif (preg_match($size_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'size',
                    'size' => $matches[1],
                    'xml' => $product_xml
                ];
            }
        }

        return $variants;
    }

    function anda_create_color_attribute($colors, $product_id)
    {
        $taxonomy = 'pa_kolor';
        $attribute_id = wc_attribute_taxonomy_id_by_name('kolor');

        if (!$attribute_id) {
            $attribute_id = wc_create_attribute([
                'name' => 'Kolor',
                'slug' => 'kolor',
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false
            ]);
        }

        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, 'product');
        }

        $term_ids = [];
        foreach ($colors as $color_code) {
            $color_name = "Kolor $color_code";
            $term = get_term_by('slug', $color_code, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($color_name, $taxonomy, ['slug' => $color_code]);
                if (!is_wp_error($term)) {
                    $term_ids[] = $term['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($product_id, $term_ids, $taxonomy);

            $wc_attribute = new WC_Product_Attribute();
            $wc_attribute->set_id($attribute_id);
            $wc_attribute->set_name($taxonomy);
            $wc_attribute->set_options($term_ids);
            $wc_attribute->set_visible(true);
            $wc_attribute->set_variation(true);

            return $wc_attribute;
        }

        return null;
    }

    function anda_create_size_attribute($sizes, $product_id)
    {
        $taxonomy = 'pa_rozmiar';
        $attribute_id = wc_attribute_taxonomy_id_by_name('rozmiar');

        if (!$attribute_id) {
            $attribute_id = wc_create_attribute([
                'name' => 'Rozmiar',
                'slug' => 'rozmiar',
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false
            ]);
        }

        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, 'product');
        }

        $term_ids = [];
        foreach ($sizes as $size_code) {
            $term = get_term_by('slug', strtolower($size_code), $taxonomy);
            if (!$term) {
                $term = wp_insert_term($size_code, $taxonomy, ['slug' => strtolower($size_code)]);
                if (!is_wp_error($term)) {
                    $term_ids[] = $term['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($product_id, $term_ids, $taxonomy);

            $wc_attribute = new WC_Product_Attribute();
            $wc_attribute->set_id($attribute_id);
            $wc_attribute->set_name($taxonomy);
            $wc_attribute->set_options($term_ids);
            $wc_attribute->set_visible(true);
            $wc_attribute->set_variation(true);

            return $wc_attribute;
        }

        return null;
    }

    function anda_create_variation($product_id, $variant_sku, $variant_data)
    {
        $existing_id = wc_get_product_id_by_sku($variant_sku);
        if ($existing_id) {
            $variation = wc_get_product($existing_id);
            if ($variation && $variation->get_parent_id() == $product_id) {
                return true;
            }
        }

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_sku($variant_sku);

        $attributes = [];
        if (!empty($variant_data['color'])) {
            $attributes['pa_kolor'] = $variant_data['color'];
        }
        if (!empty($variant_data['size'])) {
            $attributes['pa_rozmiar'] = strtolower($variant_data['size']);
        }
        $variation->set_attributes($attributes);

        $variant_xml = $variant_data['xml'];

        // Ceny
        $regular_price = null;
        if (isset($variant_xml->meta_data->meta)) {
            foreach ($variant_xml->meta_data->meta as $meta) {
                $key = trim((string) $meta->key);
                $value = trim((string) $meta->value);
                if ($key === '_anda_price_listPrice' && !empty($value)) {
                    $regular_price = str_replace(',', '.', $value);
                    break;
                }
            }
        }
        if (empty($regular_price)) {
            $regular_price = str_replace(',', '.', trim((string) $variant_xml->regular_price));
        }
        if (is_numeric($regular_price) && floatval($regular_price) > 0) {
            $variation->set_regular_price($regular_price);
        }

        $stock_qty = trim((string) $variant_xml->stock_quantity);
        if (is_numeric($stock_qty)) {
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity((int) $stock_qty);
            $variation->set_stock_status($stock_qty > 0 ? 'instock' : 'outofstock');
        }

        $variation->set_status('publish');
        return $variation->save() ? true : false;
    }

    function anda_process_categories($categories_data)
    {
        if (empty($categories_data) || !isset($categories_data->category)) {
            return [];
        }

        $category_ids = [];

        foreach ($categories_data->category as $category) {
            $cat_name = trim((string) $category);
            if (empty($cat_name))
                continue;

            if (strpos($cat_name, ' > ') !== false) {
                $path_categories = preg_split('/\s*>\s*/', $cat_name);
                $parent_id = 0;

                foreach ($path_categories as $path_cat_name) {
                    $path_cat_name = trim($path_cat_name);
                    if (empty($path_cat_name))
                        continue;

                    $existing_term = get_term_by('name', $path_cat_name, 'product_cat');
                    if ($existing_term) {
                        $current_cat_id = $existing_term->term_id;
                    } else {
                        $term_data = wp_insert_term($path_cat_name, 'product_cat', ['parent' => $parent_id]);
                        if (!is_wp_error($term_data)) {
                            $current_cat_id = $term_data['term_id'];
                        } else {
                            continue;
                        }
                    }

                    $parent_id = $current_cat_id;
                }

                if (!empty($current_cat_id)) {
                    $category_ids[] = $current_cat_id;
                }
            } else {
                $existing_term = get_term_by('name', $cat_name, 'product_cat');
                if ($existing_term) {
                    $category_ids[] = $existing_term->term_id;
                } else {
                    $term_data = wp_insert_term($cat_name, 'product_cat');
                    if (!is_wp_error($term_data)) {
                        $category_ids[] = $term_data['term_id'];
                    }
                }
            }
        }

        return array_unique($category_ids);
    }

    function anda_clean_gallery($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product)
            return;

        // TYLKO usuń galerie - nie główne zdjęcie
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $attachment_id) {
            // Sprawdź czy to obraz ANDA przed usunięciem
            $source_url = get_post_meta($attachment_id, '_anda_source_url', true);
            if (!empty($source_url)) {
                wp_delete_attachment($attachment_id, true);
                addLog("🗑️ Usunięto stary obraz ANDA z galerii", "info");
            }
        }

        // Wyczyść główne zdjęcie tylko jeśli to ANDA
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            $source_url = get_post_meta($thumbnail_id, '_anda_source_url', true);
            if (!empty($source_url)) {
                wp_delete_attachment($thumbnail_id, true);
                delete_post_thumbnail($product_id);
                addLog("🗑️ Usunięto stare główne zdjęcie ANDA", "info");
            }
        }

        $product->set_gallery_image_ids([]);
        $product->save();
    }



    /**
     * Usuwa złe produkty ANDA (z wariantami w SKU) - NOWA FUNKCJA
     */
    function anda_clean_bad_anda_products($base_sku)
    {
        global $wpdb;

        // Znajdź wszystkie produkty z podobnym SKU (warianty)
        $bad_skus = $wpdb->get_results($wpdb->prepare("
             SELECT p.ID, pm.meta_value as sku 
             FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_sku' 
             AND pm.meta_value LIKE %s
             AND p.post_type IN ('product', 'product_variation')
             AND pm.meta_value != %s
         ", $base_sku . '%', $base_sku));

        $deleted = 0;
        foreach ($bad_skus as $bad_product) {
            $bad_sku = $bad_product->sku;

            // Sprawdź czy to wariant ANDA (zawiera - lub _)
            if (preg_match('/-\d{2}$/', $bad_sku) || preg_match('/_[A-Z0-9]+$/i', $bad_sku)) {
                wp_delete_post($bad_product->ID, true);
                $deleted++;
                addLog("🗑️ Usunięto zły produkt ANDA: $bad_sku", "warning");
            }
        }

        if ($deleted > 0) {
            addLog("✅ Wyczyszczono $deleted złych produktów ANDA dla SKU: $base_sku", "success");
        }
    }

    /**
     * Usuwa istniejące warianty produktu
     */
    function anda_remove_existing_variations($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'variable') {
            return;
        }

        $variations = $product->get_children();
        $removed = 0;

        foreach ($variations as $variation_id) {
            wp_delete_post($variation_id, true);
            $removed++;
        }

        if ($removed > 0) {
            addLog("🗑️ Usunięto $removed istniejących wariantów", "info");
        }
    }

    /**
     * Automatyczne przejście między stage'ami - NOWA FUNKCJA
     */
    function anda_auto_stage_progression($current_stage, $batch_size, $auto_stage, $force_update, $max_products)
    {
        if (!$auto_stage) {
            // Bez auto_stage - wróć do managera
            addLog("✅ Import zakończony - powrót do managera!", "success");
            echo '<script>setTimeout(function(){ window.location.href = "anda-manager.php"; }, 3000);</script>';
            return;
        }

        $next_stage = $current_stage + 1;

        if ($next_stage > 3) {
            // Wszystkie stage'y ukończone
            addLog("🎉 WSZYSTKIE STAGE'Y UKOŃCZONE! Cały import ANDA zakończony!", "success");
            echo '<script>setTimeout(function(){ window.location.href = "anda-manager.php"; }, 5000);</script>';
            return;
        }

        // Przejdź do następnego stage'a
        $next_url = "?stage=$next_stage&batch_size=$batch_size&offset=0&auto_continue=1&auto_stage=1";

        if ($force_update) {
            $next_url .= "&force_update=1";
        }
        if ($max_products > 0) {
            $next_url .= "&max_products=$max_products";
        }

        addLog("🚀 Automatyczne przejście do Stage $next_stage za 2 sekundy...", "success");
        echo '<script>setTimeout(function() { window.location.href = "' . $next_url . '"; }, 2000);</script>';
    }

    /**
     * Import obrazów - POPRAWIONA WERSJA z cache
     */
    function anda_import_image($image_url, $product_id, $is_featured = false)
    {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        // Cache URL - sprawdź czy już istnieje
        $existing_attachment = anda_find_existing_image($image_url);
        if ($existing_attachment) {
            if ($is_featured) {
                set_post_thumbnail($product_id, $existing_attachment);
            }
            return $existing_attachment;
        }

        // Pobierz i zaimportuj
        $tmp = download_url($image_url, 30); // timeout 30s
        if (is_wp_error($tmp)) {
            addLog("❌ Błąd pobierania obrazu: " . $tmp->get_error_message(), "error");
            return false;
        }

        $file_array = [
            'name' => sanitize_file_name(basename(parse_url($image_url, PHP_URL_PATH))),
            'tmp_name' => $tmp
        ];

        // Dodaj meta z URL dla cache
        $attachment_id = media_handle_sideload($file_array, $product_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            addLog("❌ Błąd importu obrazu: " . $attachment_id->get_error_message(), "error");
            return false;
        }

        // Zapisz URL w meta dla cache
        update_post_meta($attachment_id, '_anda_source_url', $image_url);

        if ($is_featured) {
            set_post_thumbnail($product_id, $attachment_id);
        }

        addLog("📷 Zaimportowano obraz: " . basename($image_url), "success");
        return $attachment_id;
    }

    /**
     * Znajdź istniejący obraz po URL
     */
    function anda_find_existing_image($image_url)
    {
        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_anda_source_url' 
            AND meta_value = %s 
            LIMIT 1
        ", $image_url));

        if ($attachment_id && get_post($attachment_id)) {
            return (int) $attachment_id;
        }

        return false;
    }

    function addLog($message, $type = "info")
    {
        echo '<script>addLog(' . json_encode($message) . ', "' . $type . '");</script>';
        flush();
    }

    function updateProgress($current, $total)
    {
        echo '<script>updateProgress(' . $current . ', ' . $total . ');</script>';
        flush();
    }

    ?>

</body>

</html>
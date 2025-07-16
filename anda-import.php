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
$force_update = isset($_GET['force_update']) && $_GET['force_update'] === '1';
$anda_size_variants = isset($_GET['anda_size_variants']) && $_GET['anda_size_variants'] === '1';
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
$original_total = count($products);
// $total i $end_offset będą aktualizowane w każdym stage osobno

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
            <p>Stage <?php echo $stage; ?> | Batch:
                <?php echo $offset + 1; ?>-<?php echo min($offset + $batch_size, $original_total); ?> z
                <?php echo $original_total; ?> (XML)
            </p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $original_total; ?></div>
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
            <a href="?stage=1&batch_size=<?php echo $batch_size; ?>&auto_continue=1&anda_size_variants=1"
                class="btn btn-primary">📦 Stage 1
                Auto</a>
            <a href="?stage=2&batch_size=<?php echo $batch_size; ?>&auto_continue=1&anda_size_variants=1"
                class="btn btn-warning">🎯 Stage 2
                Auto</a>
            <a href="?stage=3&batch_size=<?php echo $batch_size; ?>&auto_continue=1&anda_size_variants=1"
                class="btn btn-success">📷 Stage 3
                Auto</a>
        </div>

        <div
            style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px; border-left: 4px solid #2196F3;">
            <h4>🔥 ANDA Parametry:</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>force_update:</strong> <?php echo $force_update ? '✅ AKTYWNY' : '❌ Wyłączony'; ?> -
                    Nadpisuje istniejące produkty</li>
                <li><strong>anda_size_variants:</strong>
                    <?php echo $anda_size_variants ? '✅ AKTYWNY' : '❌ Wyłączony'; ?> - Obsługa wariantów rozmiarów</li>
                <li><strong>auto_continue:</strong> <?php echo $auto_continue ? '✅ AKTYWNY' : '❌ Wyłączony'; ?> -
                    Automatyczne kontynuowanie batch'ów</li>
                <li><strong>Obsługiwane rozmiary:</strong> S, M, L, XL, XXL, XXXL, XS, XXS + <strong>LICZBY (38, 39, 40,
                        16GB itp.)</strong></li>
            </ul>
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
    addLog("🔥 ANDA Import: Rozpoczynanie Stage $stage dla produktów (offset: $offset, batch: $batch_size)", "info");

    // Wyłącz cache dla wydajności
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);
    wp_suspend_cache_invalidation(true);

    $processed = 0;
    $imported = 0;
    $errors = 0;
    $skipped = 0;

    // Domyślny total dla auto-continue
    $total = $original_total;

    if ($stage == 1) {
        // STAGE 1: Filtrowanie na czyste SKU + podstawowe dane
        addLog("📦 Stage 1: Filtrowanie produktów ANDA na czyste SKU...", "info");

        // Uruchom filtrowanie tylko raz - przy pierwszym batch'u
        if ($offset == 0) {
            $filtered_products = anda_filter_clean_products($products);
            $total_after_filter = count($filtered_products);
            addLog("✅ Przefiltrowano: $total produktów → $total_after_filter głównych produktów", "success");

            // Zapisz w sesji dla kolejnych batch'ów
            if (!isset($_SESSION)) {
                session_start();
            }
            $_SESSION['anda_filtered_products'] = $filtered_products;
            $_SESSION['anda_filtered_total'] = $total_after_filter;
        } else {
            // Kolejne batche - użyj z sesji
            if (!isset($_SESSION)) {
                session_start();
            }
            $filtered_products = $_SESSION['anda_filtered_products'] ?? [];
            $total_after_filter = $_SESSION['anda_filtered_total'] ?? count($filtered_products);
            addLog("📋 Stage 1: Używam przefiltrowanych produktów z sesji ($total_after_filter produktów)", "info");
        }

        // Aktualizuj total dla auto-continue PRZED przetwarzaniem
        $total = $total_after_filter;
        $end_offset = min($offset + $batch_size, $total);

        addLog("📊 Stage 1: Przetwarzam produkty $offset-$end_offset z $total przefiltrowanych", "info");

        // Sprawdź czy są jeszcze produkty do przetworzenia
        if ($offset >= $total) {
            addLog("⏭️ Stage 1: Wszystkie produkty przefiltrowane już przetworzone", "info");
            $processed = 0; // Brak produktów do przetworzenia
        } else {
            // Przetwarzaj batch z przefiltrowanych produktów
            $batch_products = array_slice($filtered_products, $offset, $batch_size);
            addLog("🔄 Stage 1: Batch zawiera " . count($batch_products) . " produktów do przetworzenia", "info");

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
        }

    } elseif ($stage == 2) {
        // STAGE 2: Tworzenie wariantów
        addLog("🎯 Stage 2: Tworzenie wariantów ANDA...", "info");

        // Użyj przefiltrowanych produktów z Stage 1
        if (!isset($_SESSION)) {
            session_start();
        }

        $filtered_products = $_SESSION['anda_filtered_products'] ?? [];
        $total_after_filter = $_SESSION['anda_filtered_total'] ?? 0;

        if (empty($filtered_products)) {
            addLog("⚠️ Stage 2: Brak przefiltrowanych produktów! Uruchom najpierw Stage 1.", "warning");
            $processed = 0;
        } else {
            // Aktualizuj total dla auto-continue
            $total = $total_after_filter;
            $end_offset = min($offset + $batch_size, $total);

            addLog("📊 Stage 2: Przetwarzam produkty $offset-$end_offset z $total przefiltrowanych", "info");

            // Sprawdź czy są jeszcze produkty do przetworzenia
            if ($offset >= $total) {
                addLog("⏭️ Stage 2: Wszystkie produkty już przetworzone", "info");
                $processed = 0;
            } else {
                $batch_products = array_slice($filtered_products, $offset, $batch_size);
                addLog("🔄 Stage 2: Batch zawiera " . count($batch_products) . " produktów do przetworzenia", "info");

                foreach ($batch_products as $product_xml) {
                    $sku = trim((string) $product_xml->sku);
                    $result = anda_process_stage_2($sku);

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
            }
        }

    } elseif ($stage == 3) {
        // STAGE 3: Import obrazów
        addLog("📷 Stage 3: Import obrazów ANDA...", "info");

        // Użyj przefiltrowanych produktów z Stage 1
        if (!isset($_SESSION)) {
            session_start();
        }

        $filtered_products = $_SESSION['anda_filtered_products'] ?? [];
        $total_after_filter = $_SESSION['anda_filtered_total'] ?? 0;

        if (empty($filtered_products)) {
            addLog("⚠️ Stage 3: Brak przefiltrowanych produktów! Uruchom najpierw Stage 1.", "warning");
            $processed = 0;
        } else {
            // Aktualizuj total dla auto-continue
            $total = $total_after_filter;
            $end_offset = min($offset + $batch_size, $total);

            addLog("📊 Stage 3: Przetwarzam produkty $offset-$end_offset z $total przefiltrowanych", "info");

            // Sprawdź czy są jeszcze produkty do przetworzenia
            if ($offset >= $total) {
                addLog("⏭️ Stage 3: Wszystkie produkty już przetworzone", "info");
                $processed = 0;
            } else {
                $batch_products = array_slice($filtered_products, $offset, $batch_size);
                addLog("🔄 Stage 3: Batch zawiera " . count($batch_products) . " produktów do przetworzenia", "info");

                foreach ($batch_products as $product_xml) {
                    $sku = trim((string) $product_xml->sku);
                    $result = anda_process_stage_3($product_xml, $sku);

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
            }
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

    // POPRAWIONA LOGIKA AUTO-CONTINUE dla ANDA
    if ($auto_continue) {
        addLog("🔄 AUTO-CONTINUE: Sprawdzam warunki kontynuacji...", "info");

        $next_offset = $offset + $batch_size;
        $products_to_process = $max_products > 0 ? $max_products : $total;
        $current_processed = $offset + $processed;

        addLog("📊 Offset: $offset → $next_offset | Produktów: $current_processed/$products_to_process | Total XML: $total", "info");

        // Sprawdź różne warunki zakończenia
        $no_more_products = $next_offset >= $total;
        $reached_limit = $max_products > 0 && $current_processed >= $max_products;

        // Specjalna logika dla każdego stage'a
        if ($stage == 3) {
            // Stage 3: Kontynuuj nawet jeśli wszystko pomijane
            $no_success_in_batch = false;
            addLog("🖼️ Stage 3: Kontynuuję nawet przy samych pominięciach", "info");
        } else {
            // Stage 1/2: Nie przerywaj jeśli są jeszcze produkty w XML
            $no_success_in_batch = $imported == 0 && $processed > 0 && $next_offset >= $total;
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
            addLog("   📦 Hurtownia: ANDA", "info");
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
                            var nextUrl = "?stage=' . $next_stage . '&batch_size=' . $batch_size . '&auto_continue=1&anda_size_variants=1";
                            ' . ($max_products > 0 ? 'nextUrl += "&max_products=' . $max_products . '";' : '') . '
                            ' . ($force_update ? 'nextUrl += "&force_update=1";' : '') . '
                            window.location.href = nextUrl;
                        }
                    }, 3000);
                </script>';
            } else {
                addLog("🎉 WSZYSTKIE STAGE'Y UKOŃCZONE! Import produktów ANDA zakończony.", "success");
                echo '<script>
                    setTimeout(function() {
                        addLog("🔗 Możesz teraz wrócić do ANDA managera", "info");
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

            $next_url = "?stage={$stage}&batch_size={$batch_size}&offset={$next_offset}&auto_continue=1&anda_size_variants=1";
            if ($max_products > 0) {
                $next_url .= "&max_products={$max_products}";
            }
            if ($force_update) {
                $next_url .= "&force_update=1";
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

    /**
     * POPRAWIONA FUNKCJA - przetwarza WSZYSTKIE produkty ANDA
     * Grupuje warianty i tworzy główne produkty
     */
    function anda_filter_clean_products($products)
    {
        $clean_products = [];
        $processed_base_skus = [];
        $variant_images = [];
        $variant_count = 0;
        $main_count = 0;

        addLog("🔍 ANDA: Rozpoczynam analizę " . count($products) . " produktów...", "info");

        // POPRAWIONE PATTERNY dla wariantów ANDA - obsługa rozmiarów liczbowych
        $color_pattern = '/-(\d{2})$/';
        $size_pattern = '/_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3})$/i';
        $combined_pattern = '/-(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3})$/i';

        addLog("🧪 ANDA DEBUG: Patterny regex:", "info");
        addLog("   🎨 Kolor: $color_pattern", "info");
        addLog("   👕 Rozmiar: $size_pattern", "info");
        addLog("   🎯 Kombinowany: $combined_pattern", "info");

        // FAZA 1: Znajdź wszystkie base SKU i zbierz warianty
        $base_skus_found = [];
        $clean_products_found = [];

        $counter = 0;
        foreach ($products as $product) {
            $sku = trim((string) $product->sku);
            $is_variant = false;
            $base_sku = '';
            $counter++;

            // DEBUG: Pokaż pierwsze 10 SKU
            if ($counter <= 10) {
                addLog("🔍 ANDA DEBUG [$counter]: Analizuję SKU: '$sku'", "info");
            }

            // Sprawdź czy to wariant
            if (preg_match($combined_pattern, $sku, $matches)) {
                $base_sku = preg_replace($combined_pattern, '', $sku);
                $is_variant = true;
                $variant_count++;
                if ($counter <= 10) {
                    addLog("   🎨👕 Wariant kombinowany: $sku → base: $base_sku", "info");
                }
            } elseif (preg_match($color_pattern, $sku, $matches)) {
                $base_sku = preg_replace($color_pattern, '', $sku);
                $is_variant = true;
                $variant_count++;
                if ($counter <= 10) {
                    addLog("   🎨 Wariant koloru: $sku → base: $base_sku", "info");
                }
            } elseif (preg_match($size_pattern, $sku, $matches)) {
                $base_sku = preg_replace($size_pattern, '', $sku);
                $is_variant = true;
                $variant_count++;
                if ($counter <= 10) {
                    addLog("   👕 Wariant rozmiaru: $sku → base: $base_sku", "info");
                }
            } else {
                if ($counter <= 10) {
                    addLog("   ❓ NIE jest wariantem: $sku", "warning");
                }
            }

            if ($is_variant) {
                // Zbierz informacje o base SKU
                if (!isset($base_skus_found[$base_sku])) {
                    $base_skus_found[$base_sku] = [
                        'variants' => [],
                        'images' => [],
                        'has_main' => false,
                        'main_product' => null
                    ];
                }

                $base_skus_found[$base_sku]['variants'][] = $sku;

                // Zbierz zdjęcia wariantu
                if (isset($product->images) && $product->images->image) {
                    foreach ($product->images->image as $image) {
                        $image_url = trim((string) $image);
                        if (!empty($image_url) && !in_array($image_url, $base_skus_found[$base_sku]['images'])) {
                            $base_skus_found[$base_sku]['images'][] = $image_url;
                        }
                    }
                }
            } else {
                // Czysty SKU - produkt główny (NIE pasuje do patterny wariantów)
                $clean_products_found[$sku] = $product;
                $main_count++;
                if ($counter <= 10) {
                    addLog("   ✅ Produkt główny: $sku", "success");
                }
            }
        }

        addLog("📊 ANDA: Znaleziono $variant_count wariantów i $main_count produktów głównych", "info");
        addLog("📊 ANDA: Wykryto " . count($base_skus_found) . " różnych base SKU z wariantami", "info");

        // KRYTYCZNA POPRAWKA: Jeśli mamy mało głównych produktów, być może patterny są za restrykcyjne
        if ($main_count < 100 && count($products) > 1000) {
            addLog("⚠️ ANDA: Mało głównych produktów ($main_count z " . count($products) . ") - może patterny za restrykcyjne?", "warning");
            addLog("💡 ANDA: Dodaję WSZYSTKIE produkty bez wariantów jako główne...", "info");

            // Dodaj wszystkie produkty które nie są w base_skus_found jako główne
            foreach ($products as $product) {
                $sku = trim((string) $product->sku);
                if (!isset($clean_products_found[$sku]) && !isset($base_skus_found[$sku])) {
                    // Sprawdź czy to nie jest wariant już przypisany do jakiegoś base
                    $is_assigned_variant = false;
                    foreach ($base_skus_found as $base_sku => $info) {
                        if (in_array($sku, $info['variants'])) {
                            $is_assigned_variant = true;
                            break;
                        }
                    }

                    if (!$is_assigned_variant) {
                        $clean_products_found[$sku] = $product;
                        $main_count++;
                    }
                }
            }

            addLog("✅ ANDA: Po dodaniu wszystkich - głównych produktów: $main_count", "success");
        }

        // FAZA 2: Sprawdź które base SKU mają główne produkty
        foreach ($base_skus_found as $base_sku => $info) {
            if (isset($clean_products_found[$base_sku])) {
                $base_skus_found[$base_sku]['has_main'] = true;
                $base_skus_found[$base_sku]['main_product'] = $clean_products_found[$base_sku];
                addLog("   ✅ Base SKU $base_sku ma główny produkt", "success");
            } else {
                addLog("   ⚠️ Base SKU $base_sku NIE MA głównego produktu - stworzę z wariantu", "warning");
            }
        }

        // FAZA 3: Utwórz finalne produkty główne
        foreach ($base_skus_found as $base_sku => $info) {
            if ($info['has_main']) {
                // Użyj istniejącego głównego produktu
                $main_product = $info['main_product'];
            } else {
                // Stwórz główny produkt z pierwszego wariantu
                $first_variant_sku = $info['variants'][0];

                // Znajdź pierwszy wariant w XML
                $first_variant_product = null;
                foreach ($products as $product) {
                    if (trim((string) $product->sku) === $first_variant_sku) {
                        $first_variant_product = $product;
                        break;
                    }
                }

                if ($first_variant_product) {
                    $main_product = clone $first_variant_product;
                    $main_product->sku = $base_sku;
                    addLog("   🔧 Utworzono główny produkt $base_sku z wariantu $first_variant_sku", "success");
                } else {
                    addLog("   ❌ Nie można znaleźć wariantu $first_variant_sku dla $base_sku", "error");
                    continue;
                }
            }

            // Dodaj zebrane zdjęcia wariantów
            if (!empty($info['images'])) {
                if (!isset($main_product->images)) {
                    $main_product->addChild('images', '');
                }

                $existing_images = [];
                if (isset($main_product->images->image)) {
                    foreach ($main_product->images->image as $img) {
                        $existing_images[] = trim((string) $img);
                    }
                }

                $added_images = 0;
                foreach ($info['images'] as $variant_image) {
                    if (!in_array($variant_image, $existing_images)) {
                        $main_product->images->addChild('image', $variant_image);
                        $added_images++;
                    }
                }

                if ($added_images > 0) {
                    addLog("   📷 Dodano $added_images obrazów z wariantów do $base_sku", "info");
                }
            }

            $clean_products[] = $main_product;
            $processed_base_skus[$base_sku] = true;
        }

        // FAZA 4: Dodaj produkty główne które nie mają wariantów
        foreach ($clean_products_found as $sku => $product) {
            if (!isset($processed_base_skus[$sku])) {
                $clean_products[] = $product;
                addLog("   ✅ Dodano produkt bez wariantów: $sku", "info");
            }
        }

        addLog("🎉 ANDA: Filtrowanie zakończone!", "success");
        addLog("📊 ANDA: Z " . count($products) . " produktów utworzono " . count($clean_products) . " głównych produktów", "success");

        return $clean_products;
    }

    /**
     * Sprawdza czy SKU jest czysty (bez - i _)
     */
    function anda_is_clean_sku($sku)
    {
        // POPRAWIONA FUNKCJA - obsługa rozmiarów liczbowych
        return !preg_match('/-\d{2}$/', $sku) && !preg_match('/_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3})$/i', $sku);
    }

    /**
     * STAGE 1: Tworzy podstawowy produkt ANDA
     */
    function anda_process_stage_1($product_xml)
    {
        global $force_update;

        $sku = trim((string) $product_xml->sku);
        $name = trim((string) $product_xml->name);

        // Sprawdź czy już istnieje
        $product_id = wc_get_product_id_by_sku($sku);
        if ($product_id && get_post_meta($product_id, '_mhi_stage_1_done', true) === 'yes' && !$force_update) {
            return 'skipped';
        }

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
     * STAGE 2: KOMPLEKSOWE TWORZENIE WARIANTÓW ANDA
     * Konwertuje produkty główne na variable i dodaje WSZYSTKIE pasujące warianty
     */
    function anda_process_stage_2($base_sku)
    {
        global $force_update, $xml;

        $product_id = wc_get_product_id_by_sku($base_sku);
        if (!$product_id) {
            addLog("❌ Stage 2: Produkt $base_sku nie znaleziony", "error");
            return 'skipped';
        }

        // Sprawdź Stage 1
        if (get_post_meta($product_id, '_mhi_stage_1_done', true) !== 'yes') {
            addLog("⚠️ Stage 2: Stage 1 nie ukończony dla $base_sku", "warning");
            return 'skipped';
        }

        // Sprawdź Stage 2
        if (get_post_meta($product_id, '_mhi_stage_2_done', true) === 'yes' && !$force_update) {
            addLog("⏭️ Stage 2: Już ukończony dla $base_sku", "info");
            return 'skipped';
        }

        try {
            // ZNAJDŹ WSZYSTKIE KURWA WARIANTY dla tego base SKU
            $variants = anda_find_all_variants($base_sku, $xml);

            addLog("🔍 Stage 2: Znaleziono " . count($variants) . " wariantów dla $base_sku", "info");

            if (empty($variants)) {
                // Oznacz jako ukończony nawet bez wariantów
                update_post_meta($product_id, '_mhi_stage_2_done', 'yes');
                addLog("ℹ️ Stage 2: Brak wariantów dla $base_sku - pozostaje jako simple product", "info");
                return 'skipped';
            }

            // KURWA WYMUŚ KONWERSJĘ NA VARIABLE PRODUCT
            addLog("🔄 Stage 2: Konwertuję $base_sku na variable product...", "info");

            // FORCE CHANGE TYPE
            wp_set_object_terms($product_id, 'variable', 'product_type');

            // Usuń istniejące warianty jeśli force_update lub jeśli były błędne
            $product = wc_get_product($product_id);
            if ($force_update || $product->get_type() !== 'variable') {
                $existing_variations = $product->get_children();
                if (!empty($existing_variations)) {
                    foreach ($existing_variations as $variation_id) {
                        wp_delete_post($variation_id, true);
                    }
                    addLog("🗑️ ANDA: Usunięto " . count($existing_variations) . " istniejących wariantów", "info");
                }
            }

            // STWÓRZ NOWY VARIABLE PRODUCT
            $product = new WC_Product_Variable($product_id);

            // Wyczyść atrybuty (zaczynamy od nowa)
            $product->set_attributes([]);
            $product->save();

            // ZBIERZ WSZYSTKIE KOLORY I ROZMIARY z wariantów
            $colors = [];
            $sizes = [];

            addLog("🎨 Stage 2: Analizuję atrybuty wariantów...", "info");

            foreach ($variants as $variant_sku => $variant_data) {
                // KOLOR
                if (!empty($variant_data['color'])) {
                    $color_code = $variant_data['color'];
                    $colors[$color_code] = "Kolor $color_code";
                    addLog("   🎨 Znaleziono kolor: $color_code", "info");
                }

                // ROZMIAR
                if (!empty($variant_data['size'])) {
                    $size_code = $variant_data['size'];
                    $sizes[$size_code] = $size_code; // Rozmiar bez zmian
                    addLog("   👕 Znaleziono rozmiar: $size_code", "info");
                }
            }

            addLog("📊 Stage 2: Kolory: " . count($colors) . ", Rozmiary: " . count($sizes), "success");

            // STWÓRZ KURWA ATRYBUTY
            $wc_attributes = [];

            if (!empty($colors)) {
                $color_attr = anda_create_color_attribute($colors, $product_id);
                if ($color_attr) {
                    $wc_attributes['pa_kolor'] = $color_attr;
                    addLog("   ✅ ANDA: Utworzono atrybut koloru z " . count($colors) . " wartościami", "success");
                } else {
                    addLog("   ❌ ANDA: Błąd tworzenia atrybutu koloru", "error");
                }
            }

            if (!empty($sizes)) {
                $size_attr = anda_create_size_attribute($sizes, $product_id);
                if ($size_attr) {
                    $wc_attributes['pa_rozmiar'] = $size_attr;
                    addLog("   ✅ ANDA: Utworzono atrybut rozmiaru z " . count($sizes) . " wartościami", "success");
                } else {
                    addLog("   ❌ ANDA: Błąd tworzenia atrybutu rozmiaru", "error");
                }
            }

            // USTAW ATRYBUTY NA PRODUKCIE
            if (!empty($wc_attributes)) {
                $product->set_attributes($wc_attributes);
                $product->save();
                addLog("🏷️ ANDA: Ustawiono " . count($wc_attributes) . " atrybutów na produkcie", "success");
            } else {
                addLog("⚠️ ANDA: Brak atrybutów do ustawienia", "warning");
            }

            // TERAZ KURWA UTWÓRZ WSZYSTKIE WARIANTY
            $created = 0;
            $errors = 0;

            addLog("🚀 Stage 2: Tworzę warianty...", "info");

            foreach ($variants as $variant_sku => $variant_data) {
                addLog("   🔄 Tworzę wariant: $variant_sku", "info");

                if (anda_create_variation_complete($product_id, $variant_sku, $variant_data, $force_update)) {
                    $created++;
                    addLog("   ✅ Utworzono wariant: $variant_sku", "success");
                } else {
                    $errors++;
                    addLog("   ❌ Błąd wariantu: $variant_sku", "error");
                }
            }

            // SYNCHRONIZUJ KURWA WSZYSTKO
            addLog("🔄 Stage 2: Synchronizuję variable product...", "info");

            // Wyczyść cache produktu
            wc_delete_product_transients($product_id);
            wp_cache_delete($product_id, 'products');

            // Synchronizuj variable product (to ustawi ceny min/max itp.)
            WC_Product_Variable::sync($product_id);

            // Przeładuj produkt
            $product = new WC_Product_Variable($product_id);
            $product->save();

            // Oznacz jako ukończony
            update_post_meta($product_id, '_mhi_stage_2_done', 'yes');

            addLog("🎉 Stage 2: $base_sku - KOMPLETNIE UKOŃCZONY!", "success");
            addLog("   📊 Utworzono: $created wariantów | Błędów: $errors", "info");

            return $created > 0 ? 'imported' : 'skipped';

        } catch (Exception $e) {
            addLog("❌ Stage 2 KRYTYCZNY BŁĄD: $base_sku - " . $e->getMessage(), "error");
            addLog("   📍 Linia: " . $e->getLine() . " | Plik: " . basename($e->getFile()), "error");
            return 'error';
        }
    }

    /**
     * STAGE 3: Import obrazów
     */
    function anda_process_stage_3($product_xml, $sku)
    {
        global $force_update;

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            return 'skipped';
        }

        // Sprawdź Stage 2
        if (get_post_meta($product_id, '_mhi_stage_2_done', true) !== 'yes') {
            return 'skipped';
        }

        // Sprawdź Stage 3
        if (get_post_meta($product_id, '_mhi_stage_3_done', true) === 'yes' && !$force_update) {
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

    /**
     * KOMPLEKSOWE ZNAJDOWANIE WSZYSTKICH WARIANTÓW
     * Znajduje WSZYSTKIE pasujące SKU dla base SKU
     */
    function anda_find_all_variants($base_sku, $xml)
    {
        $variants = [];

        // ROZSZERZONE PATTERNY - obsługa wszystkich możliwych kombinacji
        $color_pattern = '/^' . preg_quote($base_sku, '/') . '-(\d{2})$/';
        $size_pattern = '/^' . preg_quote($base_sku, '/') . '_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3}|\w+)$/i';
        $combined_pattern = '/^' . preg_quote($base_sku, '/') . '-(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3}|\w+)$/i';

        // DODATKOWE PATTERNY dla nietypowych formatów
        $alt_color_pattern = '/^' . preg_quote($base_sku, '/') . '_(\d{2})$/';
        $alt_combined_pattern = '/^' . preg_quote($base_sku, '/') . '_(\d{2})_(S|M|L|XL|XXL|XXXL|XS|XXS|XXXS|XXXXS|\d+[Gg][Bb]?|\d{2,3}|\w+)$/i';

        addLog("🔍 ANDA ADVANCED: Szukam WSZYSTKICH wariantów dla base SKU: $base_sku", "info");
        addLog("   📋 Patterny: kolor(-\\d{2}), rozmiar(_\\w+), kombinowany(-\\d{2}_\\w+)", "info");

        $found_skus = [];

        foreach ($xml->children() as $product_xml) {
            $variant_sku = trim((string) $product_xml->sku);

            // Pomiń ten sam SKU co base
            if ($variant_sku === $base_sku) {
                continue;
            }

            $matched = false;

            // 1. KOMBINOWANY: BASE-XX_SIZE (np. ABC123-01_M)
            if (preg_match($combined_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'combined',
                    'color' => $matches[1],
                    'size' => $matches[2],
                    'xml' => $product_xml
                ];
                $found_skus[] = $variant_sku;
                $matched = true;
                addLog("   🎨👕 KOMBINOWANY: $variant_sku (kolor: {$matches[1]}, rozmiar: {$matches[2]})", "success");
            }
            // 2. ALTERNATYWNY KOMBINOWANY: BASE_XX_SIZE (np. ABC123_01_M)
            elseif (preg_match($alt_combined_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'combined',
                    'color' => $matches[1],
                    'size' => $matches[2],
                    'xml' => $product_xml
                ];
                $found_skus[] = $variant_sku;
                $matched = true;
                addLog("   🎨👕 ALT-KOMBINOWANY: $variant_sku (kolor: {$matches[1]}, rozmiar: {$matches[2]})", "success");
            }
            // 3. KOLOR: BASE-XX (np. ABC123-01)
            elseif (preg_match($color_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'color',
                    'color' => $matches[1],
                    'size' => '',
                    'xml' => $product_xml
                ];
                $found_skus[] = $variant_sku;
                $matched = true;
                addLog("   🎨 KOLOR: $variant_sku (kolor: {$matches[1]})", "success");
            }
            // 4. ALTERNATYWNY KOLOR: BASE_XX (np. ABC123_01)
            elseif (preg_match($alt_color_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'color',
                    'color' => $matches[1],
                    'size' => '',
                    'xml' => $product_xml
                ];
                $found_skus[] = $variant_sku;
                $matched = true;
                addLog("   🎨 ALT-KOLOR: $variant_sku (kolor: {$matches[1]})", "success");
            }
            // 5. ROZMIAR: BASE_SIZE (np. ABC123_M, ABC123_38)
            elseif (preg_match($size_pattern, $variant_sku, $matches)) {
                $variants[$variant_sku] = [
                    'type' => 'size',
                    'color' => '',
                    'size' => $matches[1],
                    'xml' => $product_xml
                ];
                $found_skus[] = $variant_sku;
                $matched = true;
                addLog("   👕 ROZMIAR: $variant_sku (rozmiar: {$matches[1]})", "success");
            }

            // 6. SPRAWDŹ czy to po prostu zaczyna się od base SKU (fallback)
            if (!$matched && strpos($variant_sku, $base_sku) === 0 && strlen($variant_sku) > strlen($base_sku)) {
                $suffix = substr($variant_sku, strlen($base_sku));

                // Analizuj suffix
                if (preg_match('/^[-_](.+)$/', $suffix, $matches)) {
                    $variant_part = $matches[1];

                    // Sprawdź czy to wygląda na kolor+rozmiar
                    if (preg_match('/^(\d{2})[-_](\w+)$/', $variant_part, $sub_matches)) {
                        $variants[$variant_sku] = [
                            'type' => 'fallback_combined',
                            'color' => $sub_matches[1],
                            'size' => $sub_matches[2],
                            'xml' => $product_xml
                        ];
                        $found_skus[] = $variant_sku;
                        addLog("   🔄 FALLBACK-KOMBINOWANY: $variant_sku (kolor: {$sub_matches[1]}, rozmiar: {$sub_matches[2]})", "info");
                    }
                    // Sprawdź czy to tylko liczba (kolor)
                    elseif (preg_match('/^\d{2}$/', $variant_part)) {
                        $variants[$variant_sku] = [
                            'type' => 'fallback_color',
                            'color' => $variant_part,
                            'size' => '',
                            'xml' => $product_xml
                        ];
                        $found_skus[] = $variant_sku;
                        addLog("   🔄 FALLBACK-KOLOR: $variant_sku (kolor: $variant_part)", "info");
                    }
                    // Sprawdź czy to rozmiar
                    elseif (preg_match('/^(S|M|L|XL|XXL|XXXL|XS|XXS|\d{2,3}|\w+)$/i', $variant_part)) {
                        $variants[$variant_sku] = [
                            'type' => 'fallback_size',
                            'color' => '',
                            'size' => $variant_part,
                            'xml' => $product_xml
                        ];
                        $found_skus[] = $variant_sku;
                        addLog("   🔄 FALLBACK-ROZMIAR: $variant_sku (rozmiar: $variant_part)", "info");
                    }
                }
            }
        }

        addLog("🎯 ANDA ADVANCED: Znaleziono " . count($variants) . " wariantów dla $base_sku", "success");
        if (!empty($found_skus)) {
            addLog("   📋 SKU wariantów: " . implode(', ', array_slice($found_skus, 0, 10)) . (count($found_skus) > 10 ? '...' : ''), "info");
        }

        return $variants;
    }

    // Stara funkcja dla kompatybilności
    function anda_find_variants($base_sku, $xml)
    {
        return anda_find_all_variants($base_sku, $xml);
    }

    function anda_create_color_attribute($colors, $product_id)
    {
        $attr_name = 'Kolor';
        $attr_slug = 'kolor';
        $taxonomy = 'pa_kolor';

        // Stwórz globalny atrybut jeśli nie istnieje
        $attribute_id = wc_attribute_taxonomy_id_by_name($attr_slug);
        if (!$attribute_id) {
            $attribute_id = wc_create_attribute([
                'name' => $attr_name,
                'slug' => $attr_slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false
            ]);

            if (is_wp_error($attribute_id)) {
                addLog("❌ ANDA: Błąd tworzenia atrybutu koloru: " . $attribute_id->get_error_message(), "error");
                return null;
            }

            delete_transient('wc_attribute_taxonomies');
            addLog("✅ ANDA: Utworzono globalny atrybut koloru (ID: $attribute_id)", "success");
        }

        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, 'product', [
                'hierarchical' => false,
                'show_ui' => false,
                'query_var' => true,
                'rewrite' => false,
                'public' => false,
            ]);
            addLog("📝 ANDA: Zarejestrowano taksonomię: $taxonomy", "info");
        }

        // Utwórz terminy kolorów z WŁAŚCIWYMI NAZWAMI i SLUGAMI
        $term_ids = [];
        foreach ($colors as $color_code => $color_name) {
            // Użyj kodu koloru jako slug, nazwy jako display
            $term_slug = (string) $color_code; // np. "01", "02", "03"
            $term_name = (string) $color_name; // np. "Kolor 01", "Kolor 02"
    
            $term = get_term_by('slug', $term_slug, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($term_name, $taxonomy, ['slug' => $term_slug]);
                if (!is_wp_error($term)) {
                    $term_ids[] = $term['term_id'];
                    addLog("   🎨 Utworzono termin koloru: $term_name (slug: $term_slug)", "info");
                } else {
                    addLog("   ❌ Błąd tworzenia terminu koloru: " . $term->get_error_message(), "error");
                }
            } else {
                $term_ids[] = $term->term_id;
                addLog("   🎨 Znaleziono istniejący termin koloru: $term_name", "info");
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

            addLog("🏷️ ANDA: Atrybut koloru gotowy z " . count($term_ids) . " terminami", "success");
            return $wc_attribute;
        }

        addLog("❌ ANDA: Brak terminów koloru do utworzenia", "error");
        return null;
    }

    function anda_create_size_attribute($sizes, $product_id)
    {
        $attr_name = 'Rozmiar';
        $attr_slug = 'rozmiar';
        $taxonomy = 'pa_rozmiar';

        // Stwórz globalny atrybut jeśli nie istnieje
        $attribute_id = wc_attribute_taxonomy_id_by_name($attr_slug);
        if (!$attribute_id) {
            $attribute_id = wc_create_attribute([
                'name' => $attr_name,
                'slug' => $attr_slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false
            ]);

            if (is_wp_error($attribute_id)) {
                addLog("❌ ANDA: Błąd tworzenia atrybutu rozmiaru: " . $attribute_id->get_error_message(), "error");
                return null;
            }

            delete_transient('wc_attribute_taxonomies');
            addLog("✅ ANDA: Utworzono globalny atrybut rozmiaru (ID: $attribute_id)", "success");
        }

        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, 'product', [
                'hierarchical' => false,
                'show_ui' => false,
                'query_var' => true,
                'rewrite' => false,
                'public' => false,
            ]);
            addLog("📝 ANDA: Zarejestrowano taksonomię: $taxonomy", "info");
        }

        // Utwórz terminy rozmiarów z WŁAŚCIWYMI SLUGAMI (obsługa 38, 39 itp.)
        $term_ids = [];
        foreach ($sizes as $size_code) {
            $term_slug = strtolower((string) $size_code); // S -> s, M -> m, 38 -> 38, 16GB -> 16gb
            $term_name = (string) $size_code; // Zachowaj oryginalne wielkości liter w nazwie
    
            $term = get_term_by('slug', $term_slug, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($term_name, $taxonomy, ['slug' => $term_slug]);
                if (!is_wp_error($term)) {
                    $term_ids[] = $term['term_id'];
                    addLog("   👕 Utworzono termin rozmiaru: $term_name (slug: $term_slug)", "info");
                } else {
                    addLog("   ❌ Błąd tworzenia terminu rozmiaru: " . $term->get_error_message(), "error");
                }
            } else {
                $term_ids[] = $term->term_id;
                addLog("   👕 Znaleziono istniejący termin rozmiaru: $term_name", "info");
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

            addLog("🏷️ ANDA: Atrybut rozmiaru gotowy z " . count($term_ids) . " terminami", "success");
            return $wc_attribute;
        }

        addLog("❌ ANDA: Brak terminów rozmiaru do utworzenia", "error");
        return null;
    }

    /**
     * KOMPLETNE TWORZENIE WARIANTU - SOLIDNA WERSJA
     * Tworzy wariant z kompletnym mapowaniem danych i error handling
     */
    function anda_create_variation_complete($product_id, $variant_sku, $variant_data, $force_update = false)
    {
        try {
            // SPRAWDŹ czy wariant już istnieje
            $existing_id = wc_get_product_id_by_sku($variant_sku);
            if ($existing_id && !$force_update) {
                $variation = wc_get_product($existing_id);
                if ($variation && $variation->get_parent_id() == $product_id) {
                    addLog("     ⏭️ Wariant już istnieje i ma prawidłowy parent: $variant_sku", "info");
                    return true;
                } else {
                    addLog("     ⚠️ Wariant istnieje ale ma błędny parent - usuwam: $variant_sku", "warning");
                    wp_delete_post($existing_id, true);
                }
            }

            // USUŃ stary wariant jeśli force_update
            if ($existing_id && $force_update) {
                wp_delete_post($existing_id, true);
                addLog("     🗑️ Usunięto istniejący wariant (force_update): $variant_sku", "info");
            }

            // POBIERZ XML z danymi wariantu
            $variant_xml = $variant_data['xml'];
            if (!$variant_xml) {
                addLog("     ❌ Brak danych XML dla wariantu: $variant_sku", "error");
                return false;
            }

            // STWÓRZ NOWY WARIANT
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_sku($variant_sku);
            $variation->set_status('publish');

            // USTAW ATRYBUTY wariantu na podstawie typu
            $attributes = [];

            if (!empty($variant_data['color'])) {
                $color_slug = (string) $variant_data['color']; // Kod koloru jak jest
                $attributes['pa_kolor'] = $color_slug;
                addLog("       🎨 Atrybut kolor: $color_slug", "info");
            }

            if (!empty($variant_data['size'])) {
                $size_slug = strtolower((string) $variant_data['size']); // Rozmiar lowercase
                $attributes['pa_rozmiar'] = $size_slug;
                addLog("       👕 Atrybut rozmiar: $size_slug", "info");
            }

            if (!empty($attributes)) {
                $variation->set_attributes($attributes);
                addLog("       🏷️ Ustawiono " . count($attributes) . " atrybutów", "success");
            } else {
                addLog("       ⚠️ Brak atrybutów do ustawienia", "warning");
            }

            // CENA REGULARNA - hierarchia źródeł
            $regular_price = anda_extract_price($variant_xml, '_anda_price_listPrice', 'regular_price');
            if ($regular_price !== null) {
                $variation->set_regular_price($regular_price);
                addLog("       💰 Cena regularna: $regular_price PLN", "success");
            } else {
                addLog("       ⚠️ Brak ceny regularnej", "warning");
            }

            // CENA PROMOCYJNA
            $sale_price = anda_extract_price($variant_xml, '_anda_price_discountPrice', 'sale_price');
            if ($sale_price !== null) {
                $variation->set_sale_price($sale_price);
                addLog("       🔥 Cena promocyjna: $sale_price PLN", "success");
            }

            // STAN MAGAZYNOWY z kompletną obsługą
            $stock_result = anda_extract_stock($variant_xml);
            if ($stock_result['manage_stock']) {
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity($stock_result['quantity']);
                $variation->set_stock_status($stock_result['status']);
                addLog("       📦 Stock: {$stock_result['quantity']}, Status: {$stock_result['status']}", "success");
            } else {
                $variation->set_manage_stock(false);
                $variation->set_stock_status('outofstock');
                addLog("       📦 Brak zarządzania stockiem", "info");
            }

            // WYMIARY
            $dimensions = anda_extract_dimensions($variant_xml);
            if (!empty($dimensions)) {
                foreach ($dimensions as $dimension => $value) {
                    $setter = "set_$dimension";
                    if (method_exists($variation, $setter)) {
                        $variation->$setter($value);
                    }
                }
                addLog("       📏 Wymiary: " . json_encode($dimensions), "info");
            }

            // OPISY
            $name = trim((string) $variant_xml->name);
            if (!empty($name)) {
                $variation->set_name($name);
            }

            $description = trim((string) $variant_xml->description);
            if (!empty($description)) {
                $variation->set_description($description);
            }

            $short_description = trim((string) $variant_xml->short_description);
            if (!empty($short_description)) {
                $variation->set_short_description($short_description);
            }

            // ZAPISZ WARIANT
            $variation_id = $variation->save();

            if (!$variation_id) {
                addLog("     ❌ Błąd zapisu wariantu: $variant_sku", "error");
                return false;
            }

            // ZAPISZ META DATA z XML
            $meta_count = anda_save_variant_meta($variation_id, $variant_xml, $variant_sku);
            addLog("       📋 Zapisano $meta_count meta fields", "info");

            // OZNACZ pochodzenie i status
            update_post_meta($variation_id, '_mhi_supplier', 'anda');
            update_post_meta($variation_id, '_mhi_original_sku', $variant_sku);
            update_post_meta($variation_id, '_mhi_imported', 'yes');
            update_post_meta($variation_id, '_mhi_import_date', current_time('mysql'));

            addLog("     ✅ KOMPLETNIE utworzono wariant: $variant_sku (ID: $variation_id)", "success");
            return true;

        } catch (Exception $e) {
            addLog("     ❌ BŁĄD tworzenia wariantu $variant_sku: " . $e->getMessage(), "error");
            return false;
        }
    }

    // Stara funkcja dla kompatybilności
    function anda_create_variation($product_id, $variant_sku, $variant_data, $force_update = false)
    {
        return anda_create_variation_complete($product_id, $variant_sku, $variant_data, $force_update);
    }

    /**
     * POMOCNICZE FUNKCJE dla ekstraktowania danych
     */
    function anda_extract_price($xml, $meta_key, $fallback_field)
    {
        // Priorytet 1: meta_data
        if (isset($xml->meta_data->meta)) {
            foreach ($xml->meta_data->meta as $meta) {
                $key = trim((string) $meta->key);
                $value = trim((string) $meta->value);
                if ($key === $meta_key && !empty($value)) {
                    $price = str_replace(',', '.', $value);
                    if (is_numeric($price) && floatval($price) > 0) {
                        return floatval($price);
                    }
                }
            }
        }

        // Priorytet 2: bezpośrednie pole
        $fallback_value = str_replace(',', '.', trim((string) $xml->$fallback_field));
        if (is_numeric($fallback_value) && floatval($fallback_value) > 0) {
            return floatval($fallback_value);
        }

        return null;
    }

    function anda_extract_stock($xml)
    {
        $result = [
            'manage_stock' => false,
            'quantity' => 0,
            'status' => 'outofstock'
        ];

        $stock_qty = trim((string) $xml->stock_quantity);
        $stock_status = trim((string) $xml->stock_status);

        if (is_numeric($stock_qty)) {
            $result['manage_stock'] = true;
            $result['quantity'] = (int) $stock_qty;

            // Użyj stock_status z XML jeśli dostępny, inaczej oblicz
            if (!empty($stock_status)) {
                $result['status'] = $stock_status;
            } else {
                $result['status'] = $stock_qty > 0 ? 'instock' : 'outofstock';
            }
        }

        return $result;
    }

    function anda_extract_dimensions($xml)
    {
        $dimensions = [];

        $fields = ['weight', 'length', 'width', 'height'];
        foreach ($fields as $field) {
            $value = trim((string) $xml->$field);
            if (!empty($value) && is_numeric($value)) {
                $dimensions[$field] = $value;
            }
        }

        return $dimensions;
    }

    function anda_save_variant_meta($variation_id, $xml, $variant_sku)
    {
        $count = 0;

        if (isset($xml->meta_data->meta)) {
            foreach ($xml->meta_data->meta as $meta) {
                $key = trim((string) $meta->key);
                $value = trim((string) $meta->value);
                if (!empty($key) && !empty($value)) {
                    update_post_meta($variation_id, $key, $value);
                    $count++;
                }
            }
        }

        return $count;
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

        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }

        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            wp_delete_attachment($thumbnail_id, true);
            delete_post_thumbnail($product_id);
        }

        $product->set_gallery_image_ids([]);
        $product->save();
    }

    function anda_import_image($image_url, $product_id, $is_featured = false)
    {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }

        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp
        ];

        $attachment_id = media_handle_sideload($file_array, $product_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return false;
        }

        if ($is_featured) {
            set_post_thumbnail($product_id, $attachment_id);
        }

        return $attachment_id;
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

</html>
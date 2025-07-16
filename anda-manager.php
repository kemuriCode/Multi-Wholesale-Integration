<?php
/**
 * ANDA Manager - Panel zarządzania importem ANDA
 * 
 * URL: /wp-content/plugins/multi-wholesale-integration/anda-manager.php
 */

// Bezpieczeństwo
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

if (!current_user_can('manage_options')) {
    wp_die('Brak uprawnień!');
}

// Sprawdź czy XML istnieje
$upload_dir = wp_upload_dir();
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/anda/woocommerce_import_anda.xml';
$xml_exists = file_exists($xml_file);

// Policz produkty
$total_products = 0;
$clean_products = 0;
if ($xml_exists) {
    $xml = simplexml_load_file($xml_file);
    if ($xml) {
        $total_products = count($xml->children());

        // Policz czyste SKU
        $clean_count = 0;
        foreach ($xml->children() as $product) {
            $sku = trim((string) $product->sku);
            if (!preg_match('/-\d{2}$/', $sku) && !preg_match('/_[A-Z0-9]+$/i', $sku)) {
                $clean_count++;
            }
        }
        $clean_products = $clean_count;
    }
}

// Statystyki Stage'ów
global $wpdb;
$stage_1_count = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->postmeta} 
    WHERE meta_key = '_mhi_stage_1_done' 
    AND meta_value = 'yes'
");

$stage_2_count = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->postmeta} 
    WHERE meta_key = '_mhi_stage_2_done' 
    AND meta_value = 'yes'
");

$stage_3_count = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->postmeta} 
    WHERE meta_key = '_mhi_stage_3_done' 
    AND meta_value = 'yes'
");

// Policz złe produkty ANDA
$bad_anda_count = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->posts} p 
    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
    WHERE pm.meta_key = '_sku' 
    AND p.post_type IN ('product', 'product_variation')
    AND (pm.meta_value REGEXP '-[0-9]{2}$' OR pm.meta_value REGEXP '_[A-Z0-9]+$')
");

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 ANDA Manager</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 20px;
            background: #f1f1f1;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border-radius: 12px;
        }

        .header h1 {
            margin: 0;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .action-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid #007cba;
        }

        .action-card h3 {
            margin-top: 0;
            color: #333;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 5px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007cba;
            color: white;
        }

        .btn-primary:hover {
            background: #005a8b;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: #ffc107;
            color: black;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .status-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .status-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .status-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .progress-overview {
            background: #e9ecef;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .progress-bar {
            height: 25px;
            background: #dee2e6;
            border-radius: 15px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.5s ease;
        }

        .info-panel {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid #2196f3;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔥 ANDA Manager</h1>
            <p>Wydajny import produktów ANDA z obsługą wariantów</p>
        </div>

        <?php if (!$xml_exists): ?>
            <div class="status status-error">
                <strong>❌ Błąd:</strong> Plik XML ANDA nie istnieje!<br>
                <small>Oczekiwana lokalizacja: /wp-content/uploads/wholesale/anda/woocommerce_import_anda.xml</small>
            </div>
        <?php else: ?>
            <div class="status status-success">
                <strong>✅ XML znaleziony:</strong> <?php echo number_format($total_products); ?> produktów w pliku<br>
                <small>Produktów głównych (czyste SKU): <?php echo number_format($clean_products); ?></small>
            </div>
        <?php endif; ?>

        <?php if ($bad_anda_count > 0): ?>
            <div class="status status-warning">
                <strong>⚠️ Ostrzeżenie:</strong> Znaleziono <?php echo number_format($bad_anda_count); ?> złych produktów
                ANDA z wariantami w SKU!<br>
                <small>Te produkty powinny być usunięte przed uruchomieniem importu. Użyj przycisku "Wyczyść złe
                    ANDA".</small>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_products); ?></div>
                <div class="stat-label">Produktów w XML</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($clean_products); ?></div>
                <div class="stat-label">Produktów głównych</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stage_1_count); ?></div>
                <div class="stat-label">Stage 1 ukończone</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stage_2_count); ?></div>
                <div class="stat-label">Stage 2 ukończone</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stage_3_count); ?></div>
                <div class="stat-label">Stage 3 ukończone</div>
            </div>
            <?php if ($bad_anda_count > 0): ?>
                <div class="stat-card" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                    <div class="stat-number"><?php echo number_format($bad_anda_count); ?></div>
                    <div class="stat-label">Złe produkty ANDA</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="progress-overview">
            <h3>📊 Postęp importu</h3>

            <div>
                <strong>Stage 1 (Produkty):</strong>
                <div class="progress-bar">
                    <div class="progress-fill"
                        style="width: <?php echo $clean_products > 0 ? round(($stage_1_count / $clean_products) * 100) : 0; ?>%">
                    </div>
                </div>
                <small><?php echo $stage_1_count; ?> / <?php echo $clean_products; ?>
                    (<?php echo $clean_products > 0 ? round(($stage_1_count / $clean_products) * 100) : 0; ?>%)</small>
            </div>

            <div>
                <strong>Stage 2 (Warianty):</strong>
                <div class="progress-bar">
                    <div class="progress-fill"
                        style="width: <?php echo $clean_products > 0 ? round(($stage_2_count / $clean_products) * 100) : 0; ?>%">
                    </div>
                </div>
                <small><?php echo $stage_2_count; ?> / <?php echo $clean_products; ?>
                    (<?php echo $clean_products > 0 ? round(($stage_2_count / $clean_products) * 100) : 0; ?>%)</small>
            </div>

            <div>
                <strong>Stage 3 (Obrazy):</strong>
                <div class="progress-bar">
                    <div class="progress-fill"
                        style="width: <?php echo $clean_products > 0 ? round(($stage_3_count / $clean_products) * 100) : 0; ?>%">
                    </div>
                </div>
                <small><?php echo $stage_3_count; ?> / <?php echo $clean_products; ?>
                    (<?php echo $clean_products > 0 ? round(($stage_3_count / $clean_products) * 100) : 0; ?>%)</small>
            </div>
        </div>

        <?php if ($xml_exists): ?>
            <div class="actions">
                <div class="action-card" style="border-left: 5px solid #ff6b6b;">
                    <h3>🔥 PEŁNY AUTO-IMPORT</h3>
                    <p><strong>Automatycznie przejdzie przez wszystkie 3 stage'y!</strong> Stage 1 → Stage 2 → Stage 3</p>
                    <a href="anda-import.php?stage=1&batch_size=25&auto_continue=1&auto_stage=1" class="btn btn-danger"
                        style="font-size: 16px; padding: 15px 30px;">
                        🚀 START PEŁNEGO AUTO-IMPORTU
                    </a>
                    <a href="anda-import.php?stage=1&batch_size=25&auto_continue=1&auto_stage=1&force_update=1"
                        class="btn btn-danger">
                        🔄 PEŁNY AUTO + FORCE UPDATE
                    </a>
                </div>

                <div class="action-card">
                    <h3>📦 Stage 1 - Produkty</h3>
                    <p>Importuje podstawowe dane produktów z czystymi SKU. Filtruje warianty i zbiera zdjęcia.</p>
                    <a href="anda-import.php?stage=1&batch_size=25&auto_continue=1&anda_size_variants=1"
                        class="btn btn-primary">
                        🚀 Start Stage 1 Auto
                    </a>
                    <a href="anda-import.php?stage=1&batch_size=25&anda_size_variants=1" class="btn btn-primary">
                        📦 Stage 1 Manual
                    </a>
                </div>

                <div class="action-card">
                    <h3>🎯 Stage 2 - Warianty</h3>
                    <p>Tworzy warianty produktów z atrybutami kolor i rozmiar (w tym 38, 39 itp.). Mapuje ceny i stany
                        magazynowe z oryginalnych SKU.</p>
                    <a href="anda-import.php?stage=2&batch_size=25&auto_continue=1&anda_size_variants=1"
                        class="btn btn-warning">
                        🚀 Start Stage 2 Auto
                    </a>
                    <a href="anda-import.php?stage=2&batch_size=25&anda_size_variants=1" class="btn btn-warning">
                        🎯 Stage 2 Manual
                    </a>
                </div>

                <div class="action-card">
                    <h3>📷 Stage 3 - Obrazy</h3>
                    <p>Importuje zdjęcia produktów i tworzy galerie z obrazów głównych i wariantów. Ustawia główne zdjęcia.
                    </p>
                    <a href="anda-import.php?stage=3&batch_size=15&auto_continue=1&anda_size_variants=1"
                        class="btn btn-success">
                        🚀 Start Stage 3 Auto
                    </a>
                    <a href="anda-import.php?stage=3&batch_size=15&anda_size_variants=1" class="btn btn-success">
                        📷 Stage 3 Manual
                    </a>
                </div>

                <div class="action-card">
                    <h3>🔄 Operacje zaawansowane</h3>
                    <p>Narzędzia do debugowania i zarządzania importem. Force Update nadpisuje istniejące produkty i
                        warianty.</p>
                    <a href="anda-import.php?stage=1&batch_size=25&auto_continue=1&force_update=1&anda_size_variants=1"
                        class="btn btn-danger">
                        🔄 Force Update Stage 1
                    </a>
                    <a href="anda-import.php?stage=2&batch_size=25&auto_continue=1&force_update=1&anda_size_variants=1"
                        class="btn btn-danger">
                        🔄 Force Update Stage 2
                    </a>
                    <a href="anda-import.php?stage=3&batch_size=15&auto_continue=1&force_update=1&anda_size_variants=1"
                        class="btn btn-danger">
                        🔄 Force Update Stage 3
                    </a>
                    <a href="?clean_bad_anda=1" class="btn btn-warning"
                        onclick="return confirm('Czy na pewno chcesz usunąć wszystkie złe produkty ANDA z wariantami w SKU?')">
                        🧹 Wyczyść złe ANDA
                    </a>
                    <a href="?reset_anda=1" class="btn btn-danger"
                        onclick="return confirm('Czy na pewno chcesz zresetować wszystkie stage\'y ANDA?')">
                        🗑️ Reset Stages
                    </a>
                </div>

                <div class="action-card">
                    <h3>⚡ Import szybki</h3>
                    <p>Mniejsze batche dla szybszego importu i lepszej kontroli.</p>
                    <a href="anda-import.php?stage=1&batch_size=10&auto_continue=1&auto_stage=1" class="btn btn-success">
                        ⚡ PEŁNY AUTO (batch 10)
                    </a>
                    <a href="anda-import.php?stage=1&batch_size=5&auto_continue=1&auto_stage=1" class="btn btn-success">
                        ⚡ PEŁNY AUTO (batch 5)
                    </a>
                    <br><br>
                    <a href="anda-import.php?stage=1&batch_size=10&auto_continue=1" class="btn btn-primary">
                        🚀 Stage 1 (batch 10)
                    </a>
                    <a href="anda-import.php?stage=2&batch_size=10&auto_continue=1" class="btn btn-warning">
                        🎯 Stage 2 (batch 10)
                    </a>
                    <a href="anda-import.php?stage=3&batch_size=5&auto_continue=1" class="btn btn-primary">
                        📷 Stage 3 (batch 5)
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="info-panel">
            <h3>💡 Jak używać ANDA Importera</h3>
            <ol>
                <li><strong>Stage 1:</strong> Uruchom pierwszy - importuje produkty główne z czystymi SKU, filtruje
                    warianty i zbiera zdjęcia z wszystkich wariantów</li>
                <li><strong>Stage 2:</strong> KOMPLEKSOWO konwertuje produkty na variable i tworzy WSZYSTKIE warianty z
                    atrybutami kolor/rozmiar</li>
                <li><strong>Stage 3:</strong> Importuje wszystkie zdjęcia z głównych produktów i wariantów, tworzy
                    galerie</li>
            </ol>
            <p><strong>Auto mode:</strong> Automatycznie przechodzi przez wszystkie produkty w batches.</p>
            <p><strong>Manual mode:</strong> Pozwala kontrolować każdy batch osobno.</p>
<<<<<<< HEAD
            <p><strong>Force Update:</strong> Nadpisuje istniejące produkty i warianty z aktualnymi danymi z XML.</p>
            <br>
            <div class="status status-success">
                <strong>🔥 NOWY KOMPLEKSOWY SYSTEM ANDA:</strong><br>
                ✅ <strong>Automatyczna konwersja na variable products</strong> - produkty z wariantami są automatycznie
                konwertowane<br>
                ✅ <strong>Zaawansowane znajdowanie wariantów</strong> - obsługuje BASE-01, BASE_M, BASE-01_M, BASE_01_38
                i inne formaty<br>
                ✅ <strong>Kompletne mapowanie danych</strong> - ceny, stock, wymiary z oryginalnych SKU wariantów<br>
                ✅ <strong>Rozmiary liczbowe</strong> - pełna obsługa 38, 39, 16GB itp.<br>
                ✅ <strong>Error handling</strong> - solidne obsługiwanie błędów i logowanie<br>
                ✅ <strong>Metadane ANDA</strong> - właściwe ceny z _anda_price_listPrice i _anda_price_discountPrice
            </div>
=======
            <p><strong>Force Update:</strong> Nadpisuje istniejące produkty i usuwa złe dane.</p>
        </div>

        <div class="info-panel" style="background: #e8f5e8; border-left-color: #4CAF50;">
            <h3>🔥 NOWA FUNKCJA: Pełny Auto-Import!</h3>
            <ul>
                <li><strong>🚀 Pełny auto-import:</strong> Jeden klik → wszystkie 3 stage'y automatycznie!</li>
                <li><strong>⚡ Szybkie tempo:</strong> Przejście między stage'ami co 1-2 sekundy</li>
                <li><strong>🔄 Smart progression:</strong> Stage 1 → Stage 2 → Stage 3 → Koniec</li>
                <li><strong>💪 Bez klikania:</strong> Idealny dla 17k produktów!</li>
            </ul>
        </div>

        <div class="info-panel" style="background: #e8f5e8; border-left-color: #4CAF50;">
            <h3>✅ Wszystkie poprawki</h3>
            <ul>
                <li><strong>Naprawiony autocontinue:</strong> Teraz automatycznie przechodzi przez wszystkie batche</li>
                <li><strong>Force update:</strong> Właściwie nadpisuje produkty i usuwa istniejące warianty</li>
                <li><strong>Czyszczenie złych produktów:</strong> Automatycznie usuwa produkty ANDA z wariantami w SKU
                </li>
                <li><strong>Cache obrazów:</strong> Nie pobiera ponownie tych samych zdjęć</li>
                <li><strong>Lepsze logowanie:</strong> Więcej informacji o postępie i błędach</li>
            </ul>
>>>>>>> 6dd7423178823c6d1e25348889dccf38624db34a
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="cron-manager.php" class="btn btn-primary">🔙 Wróć do Cron Manager</a>
            <a href="<?php echo admin_url('admin.php?page=mhi-import'); ?>" class="btn btn-primary">🏠 Panel główny</a>
        </div>

        <?php
        // Obsługa reset
        if (isset($_GET['reset_anda']) && $_GET['reset_anda'] === '1') {
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mhi_stage_%_done'");
            echo '<div class="status status-success"><strong>✅ Reset ukończony!</strong> Wszystkie stage\'y zostały zresetowane.</div>';
            echo '<script>setTimeout(function(){ window.location.href = "anda-manager.php"; }, 2000);</script>';
        }

        // Obsługa czyszczenia złych produktów ANDA
        if (isset($_GET['clean_bad_anda']) && $_GET['clean_bad_anda'] === '1') {
            $bad_products = $wpdb->get_results("
                SELECT p.ID, pm.meta_value as sku 
                FROM {$wpdb->posts} p 
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE pm.meta_key = '_sku' 
                AND p.post_type IN ('product', 'product_variation')
                AND (pm.meta_value REGEXP '-[0-9]{2}$' OR pm.meta_value REGEXP '_[A-Z0-9]+$')
            ");

            $deleted = 0;
            foreach ($bad_products as $bad_product) {
                wp_delete_post($bad_product->ID, true);
                $deleted++;
            }

            echo '<div class="status status-success"><strong>✅ Czyszczenie ukończone!</strong> Usunięto ' . $deleted . ' złych produktów ANDA z wariantami w SKU.</div>';
            echo '<script>setTimeout(function(){ window.location.href = "anda-manager.php"; }, 3000);</script>';
        }
        ?>
    </div>

    <script>
        // Auto-refresh co 30 sekund jeśli jesteśmy na stronie
        setInterval(function () {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    </script>
</body>

</html>
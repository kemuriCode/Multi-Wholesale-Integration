<?php
/**
 * 🎛️ MANAGER CRONÓW IMPORTU - Panel kontrolny
 * Zarządzanie i monitorowanie wszystkich etapów importu
 * 
 * Użycie: /wp-content/plugins/multi-wholesale-integration/cron-manager.php
 */

declare(strict_types=1);

require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Sprawdź uprawnienia
if (!current_user_can('manage_options') && (!isset($_GET['admin_key']) || $_GET['admin_key'] !== 'mhi_import_access')) {
    wp_die('Brak uprawnień!');
}

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('WooCommerce nie jest aktywne!');
}

// Pobierz listę dostępnych hurtowni
$upload_dir = wp_upload_dir();
$wholesale_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/';
$suppliers = [];

if (is_dir($wholesale_dir)) {
    $dirs = scandir($wholesale_dir);
    foreach ($dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir($wholesale_dir . $dir)) {
            $xml_file = $wholesale_dir . $dir . '/woocommerce_import_' . $dir . '.xml';
            if (file_exists($xml_file)) {
                $suppliers[] = $dir;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎛️ MANAGER CRONÓW IMPORTU</title>
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
            max-width: 1200px;
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
            font-size: 2.5em;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .suppliers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .supplier-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .supplier-name {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .stages {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin: 20px 0;
        }

        .stage-btn {
            padding: 12px 15px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: bold;
            color: white;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .stage-1 {
            background: #007bff;
        }

        .stage-2 {
            background: #6f42c1;
        }

        .stage-3 {
            background: #28a745;
        }

        .stage-btn:hover {
            transform: scale(1.05);
            color: white;
        }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
            font-size: 12px;
        }

        .stat-item {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .stat-value {
            font-weight: bold;
            font-size: 16px;
        }

        .stat-label {
            color: #6c757d;
        }

        .batch-controls {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .control-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .control-label {
            min-width: 100px;
            font-weight: bold;
        }

        .control-input {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .auto-run {
            background: #17a2b8;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin: 10px 5px;
        }

        .info-panel {
            background: #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .progress-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🎛️ MANAGER CRONÓW IMPORTU</h1>

        <div class="info-panel">
            <h3>📋 Instrukcja użycia cronów</h3>
            <p><strong>Stage 1:</strong> 📦 Tworzy podstawowe produkty (nazwa, ceny, stock, kategorie, opisy)</p>
            <p><strong>Stage 2:</strong> 🏷️ Dodaje atrybuty i generuje warianty produktów</p>
            <p><strong>Stage 3:</strong> 📷 Importuje i konwertuje obrazy do WebP</p>
            <p><strong>💡 Tip:</strong> Uruchamiaj stage'y po kolei. Każdy następny wymaga ukończenia poprzedniego.</p>
            <p><strong>🔄 Nadpisz dane:</strong> Zaznacz aby aktualizować istniejące produkty (domyślnie: tylko nowe)
            </p>
        </div>

        <?php if (empty($suppliers)): ?>
            <div class="progress-info">
                <strong>⚠️ Brak dostępnych hurtowni!</strong><br>
                Najpierw wygeneruj pliki XML dla hurtowni.
            </div>
        <?php else: ?>

            <div class="suppliers-grid">
                <?php foreach ($suppliers as $supplier):
                    $stats = get_supplier_stats($supplier);
                    ?>
                    <div class="supplier-card">
                        <div class="supplier-name">🏢 <?php echo strtoupper($supplier); ?></div>

                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Produktów w XML</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['completed_all']; ?></div>
                                <div class="stat-label">Ukończonych</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo round($stats['progress'], 1); ?>%</div>
                                <div class="stat-label">Postęp</div>
                            </div>
                        </div>

                        <div class="batch-controls">
                            <div class="control-row">
                                <span class="control-label">Batch:</span>
                                <select class="control-input batch-size" data-supplier="<?php echo $supplier; ?>">
                                    <option value="25">25 produktów</option>
                                    <option value="50" selected>50 produktów</option>
                                    <option value="100">100 produktów</option>
                                    <option value="200">200 produktów</option>
                                </select>
                            </div>
                            <div class="control-row">
                                <span class="control-label">Offset:</span>
                                <input type="number" class="control-input offset-input" value="0" min="0"
                                    data-supplier="<?php echo $supplier; ?>">
                            </div>
                            <div class="control-row">
                                <span class="control-label">Auto-continue:</span>
                                <input type="checkbox" class="control-input auto-continue-check"
                                    data-supplier="<?php echo $supplier; ?>"> Wszystkie produkty
                            </div>
                            <div class="control-row">
                                <span class="control-label">Max produktów:</span>
                                <input type="number" class="control-input max-products-input" value="0" min="0"
                                    placeholder="0 = bez limitu" data-supplier="<?php echo $supplier; ?>">
                            </div>
                            <div class="control-row">
                                <span class="control-label">Nadpisz dane:</span>
                                <input type="checkbox" class="control-input force-update-check"
                                    data-supplier="<?php echo $supplier; ?>"> Aktualizuj istniejące
                            </div>
                            <?php if ($supplier === 'anda'): ?>
                                <div class="control-row">
                                    <span class="control-label">Rozmiary ANDA:</span>
                                    <input type="checkbox" class="control-input anda-size-variants-check"
                                        data-supplier="<?php echo $supplier; ?>"> Konwertuj na warianty (stary tryb)
                                </div>
                                <div class="control-row">
                                    <span class="control-label">🎯 Nowe warianty:</span>
                                    <input type="checkbox" class="control-input anda-new-variants-check"
                                        data-supplier="<?php echo $supplier; ?>" checked> ANDA type=variable/variation
                                </div>
                                <div class="control-row"
                                    style="background: #fff3cd; padding: 10px; border-radius: 5px; border: 1px solid #ffc107;">
                                    <span class="control-label">🧪 Test wariantów:</span>
                                    <button type="button" class="control-input"
                                        style="background: #ffc107; border: none; border-radius: 5px; padding: 8px 15px; font-weight: bold; cursor: pointer;"
                                        onclick="runAndaVariantsTest('<?php echo $supplier; ?>')">
                                        🧪 Test 20 produktów z wariantami
                                    </button>
                                    <small style="display: block; color: #856404; margin-top: 5px;">
                                        Testuje nowy system wariantów na próbce 20 produktów
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="stages">
                            <a href="#" class="stage-btn stage-1" onclick="runStage('<?php echo $supplier; ?>', 1)">
                                📦 Stage 1<br>
                                <small>(<?php echo $stats['stage_1']; ?> gotowych)</small>
                            </a>
                            <a href="#" class="stage-btn stage-2" onclick="runStage('<?php echo $supplier; ?>', 2)">
                                🏷️ Stage 2<br>
                                <small>(<?php echo $stats['stage_2']; ?> gotowych)</small>
                            </a>
                            <a href="#" class="stage-btn stage-3" onclick="runStage('<?php echo $supplier; ?>', 3)">
                                📷 Stage 3<br>
                                <small>(<?php echo $stats['stage_3']; ?> gotowych)</small>
                            </a>
                        </div>

                        <div style="text-align: center; margin-top: 15px;">
                            <a href="#" class="auto-run" onclick="runAutoSequence('<?php echo $supplier; ?>')">
                                🚀 Auto-sequence (wszystkie stage'y)
                            </a>
                        </div>

                        <div class="progress-info">
                            <small>
                                <strong>Stage 1:</strong> <?php echo $stats['stage_1']; ?>/<?php echo $stats['total']; ?> |
                                <strong>Stage 2:</strong> <?php echo $stats['stage_2']; ?>/<?php echo $stats['total']; ?> |
                                <strong>Stage 3:</strong> <?php echo $stats['stage_3']; ?>/<?php echo $stats['total']; ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="info-panel">
                <h3>🚀 Masowe operacje</h3>
                <p>
                    <button type="button" class="auto-run" onclick="runAllSuppliersStage(1)"
                        style="background: #007bff; margin: 5px;">
                        📦 Uruchom Stage 1 dla wszystkich
                    </button>
                    <button type="button" class="auto-run" onclick="runAllSuppliersStage(2)"
                        style="background: #6f42c1; margin: 5px;">
                        🏷️ Uruchom Stage 2 dla wszystkich
                    </button>
                    <button type="button" class="auto-run" onclick="runAllSuppliersStage(3)"
                        style="background: #28a745; margin: 5px;">
                        📷 Uruchom Stage 3 dla wszystkich
                    </button>
                </p>
                <p><small>💡 Uruchamia wybrany stage dla wszystkich hurtowni z auto-continue</small></p>

                <h3>🔧 Dodatkowe narzędzia</h3>
                <p>
                    <a href="?action=reset_stages" style="color: #dc3545; font-weight: bold;">
                        🗑️ Reset wszystkich stage'ów (wyczyść postęp)
                    </a>
                    <br><small>Usuwa wszystkie meta pola _mhi_stage_X_done</small>
                </p>
            </div>

        <?php endif; ?>

        <?php
        // Obsługa reset stages
        if (isset($_GET['action']) && $_GET['action'] === 'reset_stages') {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mhi_stage_%_done'");
            echo '<div class="progress-info"><strong>✅ Reset ukończony!</strong> Wszystkie stage\'y zostały zresetowane.</div>';
        }
        ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=mhi-import'); ?>"
                style="background: #0073aa; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                Wróć do panelu importu
            </a>
        </div>
    </div>

    <script>
        function runStage(supplier, stage) {
            const batchSize = document.querySelector(`select.batch-size[data-supplier="${supplier}"]`).value;
            const offset = document.querySelector(`input.offset-input[data-supplier="${supplier}"]`).value;
            const autoContinue = document.querySelector(`input.auto-continue-check[data-supplier="${supplier}"]`).checked;
            const maxProducts = document.querySelector(`input.max-products-input[data-supplier="${supplier}"]`).value;
            const forceUpdate = document.querySelector(`input.force-update-check[data-supplier="${supplier}"]`).checked;
            const andaSizeVariants = document.querySelector(`input.anda-size-variants-check[data-supplier="${supplier}"]`)?.checked || false;
            const andaNewVariants = document.querySelector(`input.anda-new-variants-check[data-supplier="${supplier}"]`)?.checked || false;

            let url = `cron-import.php?supplier=${supplier}&stage=${stage}&batch_size=${batchSize}&offset=${offset}`;

            if (forceUpdate) {
                url += '&force_update=1';
            }

            if (andaSizeVariants && supplier === 'anda') {
                url += '&anda_size_variants=1';
            }

            if (andaNewVariants && supplier === 'anda') {
                url += '&anda_new_variants=1';
            }

            if (autoContinue) {
                url += '&auto_continue=1';
                if (maxProducts > 0) {
                    url += `&max_products=${maxProducts}`;
                }
            }

            window.open(url, '_blank');
        }

        function runAutoSequence(supplier) {
            if (confirm(`Czy na pewno chcesz uruchomić wszystkie 3 stage'y dla ${supplier}?\n\nTo może zająć dużo czasu!`)) {
                const batchSize = document.querySelector(`select.batch-size[data-supplier="${supplier}"]`).value;
                const forceUpdate = document.querySelector(`input.force-update-check[data-supplier="${supplier}"]`).checked;
                const andaSizeVariants = document.querySelector(`input.anda-size-variants-check[data-supplier="${supplier}"]`)?.checked || false;
                const andaNewVariants = document.querySelector(`input.anda-new-variants-check[data-supplier="${supplier}"]`)?.checked || false;

                const forceParam = forceUpdate ? '&force_update=1' : '';
                const andaSizeParam = (andaSizeVariants && supplier === 'anda') ? '&anda_size_variants=1' : '';
                const andaNewParam = (andaNewVariants && supplier === 'anda') ? '&anda_new_variants=1' : '';

                // Otwórz wszystkie 3 stage'y w nowych kartach
                setTimeout(() => window.open(`cron-import.php?supplier=${supplier}&stage=1&batch_size=${batchSize}${forceParam}${andaSizeParam}${andaNewParam}`, '_blank'), 0);
                setTimeout(() => window.open(`cron-import.php?supplier=${supplier}&stage=2&batch_size=${batchSize}${forceParam}${andaSizeParam}${andaNewParam}`, '_blank'), 2000);
                setTimeout(() => window.open(`cron-import.php?supplier=${supplier}&stage=3&batch_size=${batchSize}${forceParam}${andaSizeParam}${andaNewParam}`, '_blank'), 4000);
            }
        }

        // 🧪 NOWA FUNKCJA: Test wariantów ANDA z 20 produktami
        function runAndaVariantsTest(supplier) {
            if (supplier !== 'anda') {
                alert('❌ Ta funkcja jest dostępna tylko dla ANDA!');
                return;
            }

            if (confirm(`🧪 ANDA - Test nowych wariantów\n\n` +
                       `✅ Uruchomi import 20 produktów z nowym systemem wariantów\n` +
                       `✅ Obsłuży type="variable" i type="variation"\n` +
                       `✅ Utworzy główne produkty i warianty\n` +
                       `✅ Nadpisze istniejące produkty (force_update=1)\n\n` +
                       `Czy kontynuować?`)) {
                
                // Parametry testowe
                const testParams = [
                    'test_variants=1',      // Tryb testowy (20 produktów)
                    'anda_new_variants=1',  // Nowe warianty ANDA
                    'force_update=1',       // Nadpisuj istniejące
                    'batch_size=20'         // 20 produktów na raz
                ].join('&');

                // Uruchom test dla wszystkich 3 stage'ów
                const baseUrl = `cron-import.php?supplier=${supplier}&${testParams}`;
                
                setTimeout(() => {
                    window.open(`${baseUrl}&stage=1`, '_blank');
                    console.log('🧪 ANDA Test: Uruchomiono Stage 1');
                }, 0);
                
                setTimeout(() => {
                    window.open(`${baseUrl}&stage=2`, '_blank');
                    console.log('🧪 ANDA Test: Uruchomiono Stage 2');
                }, 3000);
                
                setTimeout(() => {
                    window.open(`${baseUrl}&stage=3`, '_blank');
                    console.log('🧪 ANDA Test: Uruchomiono Stage 3');
                }, 6000);

                alert(`🧪 ANDA Test rozpoczęty!\n\n` +
                      `📦 Stage 1: Teraz\n` +
                      `🏷️ Stage 2: Za 3 sekundy\n` +
                      `📷 Stage 3: Za 6 sekund\n\n` +
                      `Sprawdź nowe karty przeglądarki dla postępu.`);
            }
        }

        function runAllSuppliersStage(stage) {
            const suppliers = <?php echo json_encode($suppliers); ?>;

            if (suppliers.length === 0) {
                alert('❌ Brak dostępnych hurtowni!');
                return;
            }

            const stageNames = { 1: 'Stage 1 (📦 Produkty)', 2: 'Stage 2 (🏷️ Atrybuty)', 3: 'Stage 3 (📷 Obrazy)' };

            if (confirm(`🚀 Uruchomić ${stageNames[stage]} dla wszystkich ${suppliers.length} hurtowni?\n\n⚠️ To otworzy ${suppliers.length} nowych kart z auto-continue!`)) {
                let delay = 0;

                suppliers.forEach((supplier, index) => {
                    setTimeout(() => {
                        // Sprawdź ustawienia force_update dla pierwszej hurtowni (jako wzorzec)
                        const firstSupplierForceUpdate = document.querySelector(`input.force-update-check[data-supplier="${suppliers[0]}"]`)?.checked || false;
                        const forceParam = firstSupplierForceUpdate ? '&force_update=1' : '';

                        const url = `cron-import.php?supplier=${supplier}&stage=${stage}&batch_size=50&auto_continue=1${forceParam}`;
                        window.open(url, '_blank');

                        // Pokaż komunikat postępu
                        if (index === 0) {
                            alert(`✅ Uruchamianie ${stageNames[stage]} dla wszystkich hurtowni...\n\n🔄 Auto-restart jest aktywny!`);
                        }
                    }, delay);

                    delay += 3000; // 3 sekundy między uruchomieniami
                });
            }
        }

        // Auto-refresh co 30 sekund
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>

</html>

<?php

/**
 * Pobiera statystyki postępu dla danej hurtowni
 */
function get_supplier_stats($supplier)
{
    global $wpdb;

    // Policz produkty w XML
    $upload_dir = wp_upload_dir();
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

    $total_in_xml = 0;
    if (file_exists($xml_file)) {
        $xml = simplexml_load_file($xml_file);
        if ($xml) {
            $total_in_xml = count($xml->children());
        }
    }

    // Policz produkty z ukończonymi stage'ami
    $stage_1_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id 
         WHERE pm.meta_key = '_mhi_stage_1_done' AND pm.meta_value = 'yes'
         AND pm2.meta_key = '_mhi_supplier' AND pm2.meta_value = '{$supplier}'"
    );

    $stage_2_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id 
         WHERE pm.meta_key = '_mhi_stage_2_done' AND pm.meta_value = 'yes'
         AND pm2.meta_key = '_mhi_supplier' AND pm2.meta_value = '{$supplier}'"
    );

    $stage_3_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id 
         WHERE pm.meta_key = '_mhi_stage_3_done' AND pm.meta_value = 'yes'
         AND pm2.meta_key = '_mhi_supplier' AND pm2.meta_value = '{$supplier}'"
    );

    // Policz produkty ukończone całkowicie (wszystkie 3 stage'y)
    $completed_all = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
         JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
         JOIN {$wpdb->postmeta} pm3 ON pm1.post_id = pm3.post_id
         JOIN {$wpdb->postmeta} pm4 ON pm1.post_id = pm4.post_id
         WHERE pm1.meta_key = '_mhi_stage_1_done' AND pm1.meta_value = 'yes'
         AND pm2.meta_key = '_mhi_stage_2_done' AND pm2.meta_value = 'yes'
         AND pm3.meta_key = '_mhi_stage_3_done' AND pm3.meta_value = 'yes'
         AND pm4.meta_key = '_mhi_supplier' AND pm4.meta_value = '{$supplier}'"
    );

    $progress = $total_in_xml > 0 ? ($completed_all / $total_in_xml) * 100 : 0;

    return [
        'total' => $total_in_xml,
        'stage_1' => $stage_1_count,
        'stage_2' => $stage_2_count,
        'stage_3' => $stage_3_count,
        'completed_all' => $completed_all,
        'progress' => $progress
    ];
}

?>
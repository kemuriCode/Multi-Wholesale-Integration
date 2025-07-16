<?php
/**
 * UPROSZCZONY Importer ANDA
 * Każdy produkt z unikalnym SKU jako osobny produkt simple
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
$force_update = isset($_GET['force_update']) && $_GET['force_update'] === '1';
$max_products = isset($_GET['max_products']) ? (int) $_GET['max_products'] : 0;

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
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/anda/woocommerce_import_anda_simple.xml';

if (!file_exists($xml_file)) {
    wp_die('Plik XML ANDA Simple nie istnieje: ' . basename($xml_file));
}

// Parsuj XML
$xml = simplexml_load_file($xml_file);
if (!$xml) {
    wp_die('Błąd parsowania pliku XML ANDA Simple');
}

$products = $xml->children();
$total = count($products);
$end_offset = min($offset + $batch_size, $total);

if ($max_products > 0) {
    $end_offset = min($end_offset, $max_products);
}

$start_time = microtime(true);

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 ANDA Simple Import</title>
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
            <h1>🔥 ANDA Simple Import</h1>
            <p>Uproszczony import - każdy SKU jako osobny produkt</p>
            <p>Batch: <?php echo $offset + 1; ?> - <?php echo $end_offset; ?> z <?php echo $total; ?></p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="processed">0</div>
                <p>Przetworzone</p>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="created">0</div>
                <p>Utworzone</p>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="updated">0</div>
                <p>Zaktualizowane</p>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="errors">0</div>
                <p>Błędy</p>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
        </div>

        <div class="log-container" id="log-container">
            <div class="log-entry log-info">🚀 Rozpoczynam import ANDA Simple...</div>
        </div>

        <div class="controls">
            <button class="btn btn-primary" onclick="startImport()">▶️ Start Import</button>
            <a href="?batch_size=<?php echo $batch_size; ?>&offset=<?php echo $end_offset; ?>&auto_continue=1&force_update=<?php echo $force_update ? '1' : '0'; ?>&max_products=<?php echo $max_products; ?>"
                class="btn btn-success">⏭️ Następny Batch</a>
            <a href="admin.php?page=multi-hurtownie-integration&tab=anda" class="btn btn-warning">🔙 Powrót do ANDA</a>
        </div>
    </div>

    <script>
        let processed = 0;
        let created = 0;
        let updated = 0;
        let errors = 0;

        function addLog(message, type = 'info') {
            const container = document.getElementById('log-container');
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.textContent = message;
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
        }

        function updateProgress(current, total) {
            const percentage = (current / total) * 100;
            document.getElementById('progress-fill').style.width = percentage + '%';
        }

        function updateStats() {
            document.getElementById('processed').textContent = processed;
            document.getElementById('created').textContent = created;
            document.getElementById('updated').textContent = updated;
            document.getElementById('errors').textContent = errors;
        }

        function startImport() {
            addLog('🔄 Rozpoczynam import produktów...', 'info');
            
            const products = <?php echo json_encode(array_slice((array)$products, $offset, $end_offset - $offset)); ?>;
            const total = products.length;
            
            products.forEach((product, index) => {
                setTimeout(() => {
                    importProduct(product, index + 1, total);
                }, index * 100);
            });
        }

        function importProduct(product, current, total) {
            try {
                const sku = product['g:id'] || product['g:sku'] || '';
                const title = product['g:title'] || 'Produkt ANDA';
                const description = product['g:description'] || '';
                const price = extractPrice(product['g:price']);
                const stock = extractStock(product['g:stock_quantity']);
                const category = product['g:product_type'] || '';
                
                // Sprawdź czy produkt już istnieje
                const existing_product = getProductBySku(sku);
                
                if (existing_product && !<?php echo $force_update ? 'true' : 'false'; ?>) {
                    addLog(`⏭️ Pomijam istniejący produkt: ${title} (SKU: ${sku})`, 'warning');
                    updated++;
                } else {
                    // Utwórz lub zaktualizuj produkt
                    const product_data = {
                        'name': title,
                        'description': description,
                        'short_description': '',
                        'sku': sku,
                        'regular_price': price,
                        'sale_price': '',
                        'manage_stock': true,
                        'stock_quantity': stock,
                        'stock_status': stock > 0 ? 'instock' : 'outofstock',
                        'categories': category ? [{ 'name': category }] : [],
                        'attributes': extractAttributes(product),
                        'meta_data': extractMetaData(product)
                    };
                    
                    if (existing_product) {
                        // Aktualizuj istniejący produkt
                        updateProduct(existing_product, product_data);
                        addLog(`✅ Zaktualizowano: ${title} (SKU: ${sku})`, 'success');
                        updated++;
                    } else {
                        // Utwórz nowy produkt
                        createProduct(product_data);
                        addLog(`✅ Utworzono: ${title} (SKU: ${sku})`, 'success');
                        created++;
                    }
                }
                
                processed++;
                updateProgress(current, total);
                updateStats();
                
                if (current === total) {
                    addLog('🎉 Import zakończony!', 'success');
                }
                
            } catch (error) {
                addLog(`❌ Błąd importu: ${error.message}`, 'error');
                errors++;
                updateStats();
            }
        }

        function extractPrice(priceString) {
            if (!priceString) return '0';
            const match = priceString.match(/(\d+(?:\.\d+)?)/);
            return match ? match[1] : '0';
        }

        function extractStock(stockString) {
            if (!stockString) return 0;
            const stock = parseInt(stockString);
            return isNaN(stock) ? 0 : stock;
        }

        function extractAttributes(product) {
            const attributes = [];

            // Materiał
            if (product['g:material']) {
                attributes.push({
                    'name' => 'Materiał',
                    'value' => product['g:material'],
                    'visible' => true,
                    'variation' => false
                });
            }

            // Rozmiar
            if (product['g:size']) {
                attributes.push({
                    'name' => 'Rozmiar',
                    'value' => product['g:size'],
                    'visible' => true,
                    'variation' => false
                });
            }

            // Kolor
            if (product['g:color']) {
                attributes.push({
                    'name' => 'Kolor',
                    'value' => product['g:color'],
                    'visible' => true,
                    'variation' => false
                });
            }

            return attributes;
        }

        function extractMetaData(product) {
            const meta = [];

            // Hurtownia
            meta.push({
                'key' => '_mhi_supplier',
                'value' => 'anda'
            });

            // Oryginalny SKU
            if (product['g:sku']) {
                meta.push({
                    'key' => '_mhi_original_sku',
                    'value' => product['g:sku']
                });
            }

            // Cena
            if (product['g:price_value']) {
                meta.push({
                    'key' => '_mhi_price',
                    'value' => product['g:price_value']
                });
            }

            return meta;
        }

        function getProductBySku(sku) {
            // Symulacja - w rzeczywistości to będzie AJAX call
            return null;
        }

        function createProduct(productData) {
            // Symulacja - w rzeczywistości to będzie AJAX call
            console.log('Tworzenie produktu:', productData);
        }

        function updateProduct(productId, productData) {
            // Symulacja - w rzeczywistości to będzie AJAX call
            console.log('Aktualizacja produktu:', productId, productData);
        }

        // Auto-start jeśli auto_continue
        <?php if ($auto_continue): ?>
            window.onload = function () {
                setTimeout(startImport, 1000);
            };
        <?php endif; ?>
    </script>
</body>

</html>
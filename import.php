<?php
/**
 * PROSTY SYNCHRONICZNY IMPORTER PRODUKTÓW
 * Importuje produkty bezpośrednio, natychmiast i na żywo!
 * 
 * Sposób użycia: 
 * /wp-content/plugins/multi-wholesale-integration/import.php?supplier=malfini
 */

declare(strict_types=1);

// Zwiększ limity wykonania
ini_set('memory_limit', '2048M');
set_time_limit(0);
ignore_user_abort(true);

// Wyświetlaj wszystkie błędy
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Załaduj WordPress
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Sprawdź uprawnienia
if (!current_user_can('manage_options') && (!isset($_GET['admin_key']) || $_GET['admin_key'] !== 'mhi_import_access')) {
    wp_die('Brak uprawnień do importu produktów!');
}

// Sprawdź parametr supplier
if (!isset($_GET['supplier'])) {
    wp_die('Brak parametru supplier! Użyj: ?supplier=malfini');
}

$supplier = sanitize_text_field($_GET['supplier']);

// Sprawdź WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('WooCommerce nie jest aktywne!');
}

// Znajdź plik XML
$upload_dir = wp_upload_dir();
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

if (!file_exists($xml_file)) {
    wp_die('Plik XML nie istnieje: ' . $xml_file . '<br>Najpierw wygeneruj plik XML dla hurtowni: ' . $supplier);
}

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 IMPORT PRODUKTÓW - <?php echo strtoupper($supplier); ?></title>
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
            backdrop-filter: blur(10px);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 2.5em;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            position: relative;
        }

        .progress-bar {
            background: linear-gradient(45deg, #28a745, #20c997);
            height: 100%;
            border-radius: 15px;
            text-align: center;
            line-height: 25px;
            color: white;
            font-weight: bold;
            width: 0%;
            transition: width 0.5s ease;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .stat {
            background: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat.created .stat-value {
            color: #28a745;
        }

        .stat.updated .stat-value {
            color: #007bff;
        }

        .stat.failed .stat-value {
            color: #dc3545;
        }

        .stat.images .stat-value {
            color: #6f42c1;
        }

        .stat.total .stat-value {
            color: #495057;
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
            height: 400px;
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

        .current-product {
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }

        .current-product-name {
            font-size: 18px;
            font-weight: bold;
            color: #856404;
            margin-bottom: 5px;
        }

        .current-product-sku {
            font-size: 14px;
            color: #6c757d;
        }

        .time-info {
            text-align: center;
            margin: 20px 0;
            color: #6c757d;
            font-size: 14px;
        }

        .back-link {
            display: inline-block;
            background: #0073aa;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🚀 IMPORT PRODUKTÓW - <?php echo strtoupper($supplier); ?></h1>

        <div class="current-product" id="currentProduct" style="display: none;">
            <div class="current-product-name" id="currentProductName">Przygotowywanie...</div>
            <div class="current-product-sku" id="currentProductSku"></div>
        </div>

        <div class="progress-container">
            <div class="progress">
                <div class="progress-bar" id="progressBar">0%</div>
            </div>
        </div>

        <div class="stats">
            <div class="stat total">
                <div class="stat-value" id="totalCount">0</div>
                <div class="stat-label">Łącznie</div>
            </div>
            <div class="stat created">
                <div class="stat-value" id="createdCount">0</div>
                <div class="stat-label">Utworzone</div>
            </div>
            <div class="stat updated">
                <div class="stat-value" id="updatedCount">0</div>
                <div class="stat-label">Zaktualizowane</div>
            </div>
            <div class="stat failed">
                <div class="stat-value" id="failedCount">0</div>
                <div class="stat-label">Błędy</div>
            </div>
            <div class="stat images">
                <div class="stat-value" id="imagesCount">0</div>
                <div class="stat-label">Obrazy</div>
            </div>
        </div>

        <div class="time-info" id="timeInfo">
            Czas: 0s
        </div>

        <div class="log-container">
            <div class="log" id="logContainer"></div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=mhi-import'); ?>" class="back-link">Wróć do panelu importu</a>
        </div>
    </div>

    <script>
        let startTime = Date.now();
        let stats = { total: 0, created: 0, updated: 0, failed: 0, images: 0 };

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
            document.getElementById('totalCount').textContent = stats.total;
            document.getElementById('createdCount').textContent = stats.created;
            document.getElementById('updatedCount').textContent = stats.updated;
            document.getElementById('failedCount').textContent = stats.failed;
            document.getElementById('imagesCount').textContent = stats.images;
        }

        function updateCurrentProduct(name, sku) {
            const container = document.getElementById('currentProduct');
            const nameEl = document.getElementById('currentProductName');
            const skuEl = document.getElementById('currentProductSku');

            if (name && sku) {
                nameEl.textContent = name;
                skuEl.textContent = `SKU: ${sku}`;
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }

        function updateTime() {
            const elapsed = Math.round((Date.now() - startTime) / 1000);
            document.getElementById('timeInfo').textContent = `Czas: ${elapsed}s`;
        }

        setInterval(updateTime, 1000);

        addLog('🔧 System gotowy do importu', 'info');
    </script>

    <?php
    flush();

    // ROZPOCZNIJ IMPORT
    addLog("📄 Ładowanie pliku XML: " . basename($xml_file));
    $xml = simplexml_load_file($xml_file);
    if (!$xml) {
        addLog("❌ Błąd parsowania XML!", "error");
        exit;
    }

    $products = $xml->children();
    $total = count($products);
    addLog("✅ Znaleziono {$total} produktów do importu", "success");

    // Wyłącz cache dla wydajności
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);
    wp_suspend_cache_invalidation(true);

    // Statystyki
    $stats = [
        'total' => $total,
        'created' => 0,
        'updated' => 0,
        'failed' => 0,
        'images' => 0
    ];

    echo '<script>stats.total = ' . $total . '; updateStats();</script>';
    flush();

    // GŁÓWNA PĘTLA IMPORTU
    $processed = 0;
    foreach ($products as $product_xml) {
        $processed++;

        // SKU i nazwa produktu
        $sku = trim((string) $product_xml->sku);
        if (empty($sku))
            $sku = trim((string) $product_xml->id);

        $name = trim((string) $product_xml->name);
        if (empty($name))
            $name = 'Produkt ' . $sku;

        addLog("🔄 [{$processed}/{$total}] Przetwarzanie: {$name} (SKU: {$sku})");
        echo '<script>updateCurrentProduct(' . json_encode($name) . ', ' . json_encode($sku) . ');</script>';

        try {
            // Sprawdź czy produkt istnieje
            $product_id = wc_get_product_id_by_sku($sku);
            $is_update = (bool) $product_id;

            if ($is_update) {
                $product = wc_get_product($product_id);
                addLog("📝 Aktualizacja istniejącego produktu ID: {$product_id}");
            } else {
                $product = new WC_Product();
                addLog("➕ Tworzenie nowego produktu");
            }

            // USTAWIANIE PODSTAWOWYCH DANYCH
            $product->set_name($name);
            $product->set_description((string) $product_xml->description);
            $product->set_short_description((string) $product_xml->short_description);
            $product->set_sku($sku);
            $product->set_status('publish');

            // CENY z walidacją
            $regular_price = trim((string) $product_xml->regular_price);
            if (!empty($regular_price)) {
                $regular_price = str_replace(',', '.', $regular_price);
                if (is_numeric($regular_price) && floatval($regular_price) > 0) {
                    $product->set_regular_price($regular_price);
                    addLog("💰 Cena: {$regular_price} PLN", "success");
                }
            }

            $sale_price = trim((string) $product_xml->sale_price);
            if (!empty($sale_price)) {
                $sale_price = str_replace(',', '.', $sale_price);
                if (is_numeric($sale_price) && floatval($sale_price) > 0) {
                    $product->set_sale_price($sale_price);
                }
            }

            // STOCK
            $stock_qty = trim((string) $product_xml->stock_quantity);
            if (!empty($stock_qty) && is_numeric($stock_qty)) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity((int) $stock_qty);
                $product->set_stock_status('instock');
                addLog("📦 Stan: {$stock_qty} szt.", "success");
            }

            // WYMIARY
            if (!empty((string) $product_xml->weight))
                $product->set_weight((string) $product_xml->weight);
            if (!empty((string) $product_xml->length))
                $product->set_length((string) $product_xml->length);
            if (!empty((string) $product_xml->width))
                $product->set_width((string) $product_xml->width);
            if (!empty((string) $product_xml->height))
                $product->set_height((string) $product_xml->height);

            // ATRYBUTY - ze standardowej struktury WooCommerce XML
            $product_attributes = [];
            $wc_attributes = [];

            if (isset($product_xml->attributes) && $product_xml->attributes->attribute) {
                $attributes = $product_xml->attributes->attribute;
                if (!is_array($attributes))
                    $attributes = [$attributes];

                foreach ($attributes as $attr) {
                    $attr_name = trim((string) $attr->name);
                    $attr_value = trim((string) $attr->value);

                    if (!empty($attr_name) && !empty($attr_value)) {
                        $product_attributes[] = [
                            'name' => $attr_name,
                            'value' => $attr_value
                        ];

                        $attribute = new WC_Product_Attribute();
                        $attribute->set_name($attr_name);
                        $attribute->set_options([$attr_value]);
                        $attribute->set_visible(true);
                        $attribute->set_variation(false);
                        $wc_attributes[] = $attribute;
                    }
                }

                // Ustaw atrybuty na produkcie
                if (!empty($wc_attributes)) {
                    $product->set_attributes($wc_attributes);
                }
            }

            // KOLOR jako atrybut
            if (isset($product_xml->color) && !empty((string) $product_xml->color->name)) {
                $color_name = trim((string) $product_xml->color->name);
                $product_attributes[] = ['name' => 'Kolor', 'value' => $color_name];

                $attribute = new WC_Product_Attribute();
                $attribute->set_name('Kolor');
                $attribute->set_options([$color_name]);
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $wc_attributes[] = $attribute;
            }

            // ZAPISZ PRODUKT żeby uzyskać ID
            $product_id = $product->save();

            if (!$product_id) {
                throw new Exception("Nie można zapisać produktu");
            }

            // KATEGORIE z dekodowaniem HTML entities
            if (isset($product_xml->categories)) {
                $categories_text = trim((string) $product_xml->categories);
                if (!empty($categories_text)) {
                    // DEKODUJ &gt; -> >
                    $categories_text = html_entity_decode($categories_text, ENT_QUOTES, 'UTF-8');
                    addLog("📁 Kategorie: {$categories_text}");

                    $category_ids = process_product_categories($categories_text);
                    if (!empty($category_ids)) {
                        wp_set_object_terms($product_id, $category_ids, 'product_cat');
                        addLog("✅ Przypisano " . count($category_ids) . " kategorii", "success");
                    }
                }
            }

            // OBRAZY - obsługa <image src="URL"/>
            if (isset($product_xml->images) && $product_xml->images->image) {
                $images = $product_xml->images->image;
                if (!is_array($images))
                    $images = [$images];

                $image_ids = [];
                $img_counter = 0;

                addLog("📷 Szukam obrazków dla produktu...", "info");

                foreach ($images as $image) {
                    $image_url = '';

                    // Sprawdź atrybut src
                    $attributes = $image->attributes();
                    if (isset($attributes['src'])) {
                        $image_url = trim((string) $attributes['src']);
                    } else {
                        $image_url = trim((string) $image);
                    }

                    if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                        addLog("📥 Pobieram obraz " . ($img_counter + 1) . ": {$image_url}", "info");
                        $attachment_id = import_product_image($image_url, $product_id, $img_counter === 0);
                        if ($attachment_id) {
                            $image_ids[] = $attachment_id;
                            $stats['images']++;
                            addLog("✅ Obraz " . ($img_counter + 1) . " dodany (ID: {$attachment_id})", "success");
                        } else {
                            addLog("❌ Nie udało się dodać obrazu " . ($img_counter + 1), "error");
                        }
                    } else {
                        addLog("⚠️ Nieprawidłowy URL obrazu " . ($img_counter + 1) . ": {$image_url}", "warning");
                    }
                    $img_counter++;
                }

                addLog("📊 Znaleziono " . count($image_ids) . " obrazków", "info");

                // Ustaw galerię
                if (count($image_ids) > 1) {
                    $featured_id = get_post_thumbnail_id($product_id);
                    $gallery_ids = array_filter($image_ids, function ($id) use ($featured_id) {
                        return $id != $featured_id;
                    });

                    if (!empty($gallery_ids)) {
                        update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                        $product = wc_get_product($product_id);
                        $product->set_gallery_image_ids($gallery_ids);
                        $product->save();
                        addLog("🖼️ Ustawiono galerię z " . count($gallery_ids) . " obrazami", "success");
                    }
                }

                addLog("🖼️ Dodano " . count($image_ids) . " obrazów", "success");
            } else {
                addLog("⚠️ Brak sekcji <images> w XML", "warning");
            }

            // Oznacz jako importowany
            update_post_meta($product_id, '_mhi_imported', 'yes');
            update_post_meta($product_id, '_mhi_supplier', $supplier);
            update_post_meta($product_id, '_mhi_import_date', current_time('mysql'));

            // Statystyki
            if ($is_update) {
                $stats['updated']++;
                addLog("✅ Zaktualizowano produkt ID: {$product_id}", "success");
            } else {
                $stats['created']++;
                addLog("✅ Utworzono produkt ID: {$product_id}", "success");
            }

            // Log o atrybutach
            if (!empty($product_attributes)) {
                addLog("🏷️ Dodano " . count($product_attributes) . " atrybutów", "success");
            }

        } catch (Exception $e) {
            $stats['failed']++;
            addLog("❌ Błąd: " . $e->getMessage(), "error");
        }

        // Aktualizuj interfejs co 1 produkt
        echo '<script>updateProgress(' . $processed . ', ' . $total . '); stats.created = ' . $stats['created'] . '; stats.updated = ' . $stats['updated'] . '; stats.failed = ' . $stats['failed'] . '; stats.images = ' . $stats['images'] . '; updateStats();</script>';
        flush();

        // Krótka przerwa żeby nie przeciążyć serwera
        usleep(100000); // 0.1 sekundy
    }

    // Włącz z powrotem cache
    wp_suspend_cache_invalidation(false);
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);

    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 2);

    addLog("🎉 IMPORT ZAKOŃCZONY!", "success");
    addLog("⏱️ Czas: {$duration} sekund", "info");
    addLog("📊 Utworzono: {$stats['created']}, Zaktualizowano: {$stats['updated']}, Błędów: {$stats['failed']}, Obrazów: {$stats['images']}", "info");

    echo '<script>updateCurrentProduct("", ""); addLog("🎉 IMPORT ZAKOŃCZONY W ' . $duration . ' SEKUND!", "success");</script>';

    // FUNKCJE POMOCNICZE
    
    function addLog($message, $type = "info")
    {
        echo '<script>addLog(' . json_encode($message) . ', "' . $type . '");</script>';
        flush();
    }

    function process_product_categories($categories_text)
    {
        $category_ids = [];

        if (strpos($categories_text, '>') !== false) {
            // Hierarchia kategorii
            $parts = array_map('trim', explode('>', $categories_text));
            $parent_id = 0;

            foreach ($parts as $part) {
                if (empty($part))
                    continue;

                $term = get_term_by('name', $part, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($part, 'product_cat', ['parent' => $parent_id]);
                    if (!is_wp_error($term)) {
                        $parent_id = $term['term_id'];
                    }
                } else {
                    $parent_id = $term->term_id;
                }
            }

            if ($parent_id > 0) {
                $category_ids[] = $parent_id;
            }
        } else {
            // Pojedyncza kategoria
            $term = get_term_by('name', $categories_text, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($categories_text, 'product_cat');
                if (!is_wp_error($term)) {
                    $category_ids[] = $term['term_id'];
                }
            } else {
                $category_ids[] = $term->term_id;
            }
        }

        return $category_ids;
    }

    function import_product_image($image_url, $product_id, $is_featured = false)
    {
        // Sprawdź czy obraz już istnieje
        $existing = get_posts([
            'post_type' => 'attachment',
            'meta_query' => [
                [
                    'key' => '_mhi_source_url',
                    'value' => $image_url
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ($existing) {
            $attach_id = $existing[0]->ID;
            if ($is_featured) {
                set_post_thumbnail($product_id, $attach_id);
            }
            addLog("♻️ Użyto istniejący obraz (ID: {$attach_id})", "info");
            return $attach_id;
        }

        $upload_dir = wp_upload_dir();

        // Pobierz obraz
        $response = wp_remote_get($image_url, [
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            addLog("❌ Błąd pobierania obrazu: " . $response->get_error_message(), "error");
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            addLog("❌ Puste dane obrazu z URL: {$image_url}", "error");
            return false;
        }

        // Zapisz plik
        $filename = basename($image_url);
        $filename = sanitize_file_name($filename);

        // Dodaj timestamp żeby uniknąć duplikatów
        $filename = time() . '_' . $filename;

        $file_path = $upload_dir['path'] . '/' . $filename;

        if (file_put_contents($file_path, $image_data) === false) {
            addLog("❌ Nie udało się zapisać pliku: {$file_path}", "error");
            return false;
        }

        // Dodaj do biblioteki mediów
        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path, $product_id);

        if (!$attach_id) {
            addLog("❌ Nie udało się utworzyć załącznika w WordPress", "error");
            return false;
        }

        // Zapisz URL źródłowy
        update_post_meta($attach_id, '_mhi_source_url', $image_url);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Ustaw jako główny obraz
        if ($is_featured) {
            set_post_thumbnail($product_id, $attach_id);
            addLog("🌟 Ustawiono jako główny obraz produktu", "success");
        }

        return $attach_id;
    }

    ?>
    </div>
</body>

</html>
<?php
/**
 * PROSTY SYNCHRONICZNY IMPORTER PRODUKTÓW
 * Importuje produkty bezpośrednio, natychmiast i na żywo!
 * 
 * Sposób użycia: 
 * /wp-content/plugins/multi-wholesale-integration/import.php?supplier=malfini
 * 
 * Dostępne opcje URL:
 * - supplier=nazwa_hurtowni (wymagane)
 * - admin_key=mhi_import_access (alternatywa dla uprawnień)
 * - replace_images=1 (zastąp istniejące obrazy galerii przy aktualizacji)
 * - test_xml=1 (użyj test_gallery.xml zamiast głównego pliku)
 * - test_gallery=ID_PRODUKTU (testuj galerię konkretnego produktu)
 * - fix_gallery=ID_PRODUKTU (napraw galerię produktu z istniejących załączników)
 * - generate_variations=0 (wyłącz automatyczne generowanie wariantów, domyślnie włączone)
 * 
 * Funkcjonalność galerii:
 * ✅ Pierwszy obraz z XML staje się głównym zdjęciem produktu
 * ✅ Pozostałe obrazy trafiają do galerii WooCommerce
 * ✅ Automatyczne łączenie z istniejącą galerią przy aktualizacji
 * ✅ Opcja zastąpienia galerii parametrem replace_images=1
 * ✅ Konwersja do WebP i optymalizacja rozmiaru
 * ✅ Sprawdzanie duplikatów obrazów
 * ✅ Szczegółowe logi i raporty galerii
 * 
 * Funkcjonalność marek:
 * ✅ Automatyczne mapowanie marek z atrybutów XML (Marka, Brand, Manufacturer, Producent, Firma)
 * ✅ Wykrywanie istniejących taksonomii marek (product_brand, pwb-brand, yith_product_brand, itp.)
 * ✅ Tworzenie taksonomii marek jeśli nie istnieje
 * ✅ Przypisywanie marek do produktów z weryfikacją
 * ✅ Backup: sprawdzanie bezpośrednich pól XML (brand, manufacturer)
 * 
 * Funkcjonalność wariantów:
 * ✅ Automatyczne wykrywanie produktów z wariantami (type="variable" lub atrybuty z variation="yes")
 * ✅ Generowanie wszystkich kombinacji wariantów na podstawie atrybutów
 * ✅ Kopiowanie wszystkich parametrów z produktu głównego (ceny, wymiary, stan magazynowy)
 * ✅ Aktualizacja istniejących wariantów przy ponownym imporcie
 * ✅ Synchronizacja wariantów z produktem głównym
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

// Sprawdź czy to test galerii
if (isset($_GET['test_xml']) && $_GET['test_xml'] === '1') {
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/test_gallery.xml';
} else {
    $xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';
}

if (!file_exists($xml_file)) {
    wp_die('Plik XML nie istnieje: ' . $xml_file . '<br>Najpierw wygeneruj plik XML dla hurtowni: ' . $supplier);
}

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" conten t="width=device-width, initial-scale=
    1.0">
    <title>🚀 IMPORT PRODUKTÓW -
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


        <h1>🚀 IMPORT PRODUKTÓW -
            <?php echo strtoupper($supplier); ?>
        </h1>

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

            // WYKRYJ CZY PRODUKT MA WARIANTY
            $has_variations = false;
            $product_type = 'simple'; // domyślnie prosty produkt
    
            // Sprawdź pole <type> w XML
            if (isset($product_xml->type)) {
                $xml_type = trim((string) $product_xml->type);
                if ($xml_type === 'variable') {
                    $has_variations = true;
                    $product_type = 'variable';
                    addLog("🔄 XML określa typ produktu jako: variable", "info");
                }
            }

            // Sprawdź atrybuty z variation="yes" jako backup
            if (!$has_variations && isset($product_xml->attributes) && isset($product_xml->attributes->attribute)) {
                $variation_attributes_count = 0;
                foreach ($product_xml->attributes->attribute as $attribute_xml) {
                    $variation_flag = trim((string) $attribute_xml->variation);
                    if ($variation_flag === 'yes' || $variation_flag === '1') {
                        $variation_attributes_count++;
                        if (!$has_variations) {
                            $has_variations = true;
                            $product_type = 'variable';
                            addLog("🔄 Wykryto atrybut z variation='yes' - ustawiam typ na variable", "info");
                        }
                    }
                }
                if ($variation_attributes_count > 0) {
                    addLog("📊 Znaleziono {$variation_attributes_count} atrybutów oznaczonych jako variation", "info");
                }
            }

            if ($has_variations) {
                addLog("🎯 Produkt zostanie utworzony jako VARIABLE z możliwością automatycznego generowania wariantów", "success");
            } else {
                addLog("📦 Produkt zostanie utworzony jako SIMPLE (brak atrybutów variation)", "info");
            }

            if ($is_update) {
                $product = wc_get_product($product_id);
                addLog("📝 Aktualizacja istniejącego produktu ID: {$product_id} (typ: {$product_type})");

                // Sprawdź czy trzeba zmienić typ produktu
                if ($product && $has_variations && $product->get_type() !== 'variable') {
                    addLog("🔄 Zmieniam typ produktu z " . $product->get_type() . " na variable", "warning");
                    // Konwertuj na variable product
                    wp_set_object_terms($product_id, 'variable', 'product_type');
                    $product = wc_get_product($product_id); // Przeładuj produkt
                }
            } else {
                // Utwórz odpowiedni typ produktu
                if ($has_variations) {
                    if (class_exists('WC_Product_Variable')) {
                        $product = new WC_Product_Variable();
                        addLog("➕ Tworzenie nowego produktu z wariantami (WC_Product_Variable)");
                    } else {
                        $product = new WC_Product();
                        addLog("⚠️ WC_Product_Variable niedostępne - używam WC_Product", "warning");
                    }
                } else {
                    $product = new WC_Product();
                    addLog("➕ Tworzenie nowego prostego produktu (WC_Product)");
                }
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

            // Inicjalizacja tablic atrybutów
            $product_attributes = [];
            $wc_attributes = [];
            $attributes_to_assign = []; // Nowa tablica do przechowania atrybutów
    
            // ATRYBUTY z sekcji <attributes>
            // ✅ NAPRAWIONO: Używamy nazw atrybutów zamiast ID dla poprawnego wyświetlania
            if (isset($product_xml->attributes) && isset($product_xml->attributes->attribute)) {
                addLog("🏷️ Przetwarzam atrybuty produktu...", "info");

                $attributes_processed = 0;
                foreach ($product_xml->attributes->attribute as $attribute_xml) {
                    $attr_name = trim((string) $attribute_xml->name);
                    $attr_value = trim((string) $attribute_xml->value);

                    if (empty($attr_name) || empty($attr_value)) {
                        continue;
                    }

                    // Podziel wartości oddzielone przecinkami
                    $values = array_map('trim', explode(',', $attr_value));
                    $values = array_filter($values); // Usuń puste wartości
    
                    if (empty($values)) {
                        continue;
                    }

                    addLog("🔹 Atrybut: {$attr_name} = " . implode(', ', $values), "info");

                    // Dodaj do tablicy atrybutów
                    $product_attributes[] = ['name' => $attr_name, 'value' => implode(', ', $values)];

                    // Przygotuj nazwę taksonomii dla atrybutu
                    $attr_slug = wc_sanitize_taxonomy_name($attr_name);
                    $taxonomy = wc_attribute_taxonomy_name($attr_slug);

                    // Sprawdź czy atrybut globalny już istnieje
                    $attribute_id = wc_attribute_taxonomy_id_by_name($attr_slug);

                    if (!$attribute_id) {
                        // Utwórz nowy atrybut globalny
                        $attribute_id = wc_create_attribute(array(
                            'name' => $attr_name,
                            'slug' => $attr_slug,
                            'type' => 'select',
                            'order_by' => 'menu_order',
                            'has_archives' => false
                        ));

                        if (!is_wp_error($attribute_id)) {
                            addLog("✅ Utworzono atrybut globalny: {$attr_name} (ID: {$attribute_id})", "success");
                            // Odśwież taksonomie po utworzeniu nowego atrybutu
                            delete_transient('wc_attribute_taxonomies');
                            WC_Cache_Helper::get_transient_version('shipping', true);

                            // Wymuś rejestrację taksonomii
                            if (function_exists('wc_create_attribute_taxonomies')) {
                                wc_create_attribute_taxonomies();
                            }

                            // Sprawdź ponownie czy taksonomia została zarejestrowana
                            if (!taxonomy_exists($taxonomy)) {
                                addLog("⚠️ Próbuję ponownie zarejestrować taksonomię: {$taxonomy}", "warning");
                                // Ręczna rejestracja taksonomii
                                register_taxonomy($taxonomy, 'product', [
                                    'hierarchical' => false,
                                    'show_ui' => false,
                                    'query_var' => true,
                                    'rewrite' => false,
                                ]);
                            }
                        } else {
                            addLog("❌ Błąd tworzenia atrybutu: {$attr_name} - " . $attribute_id->get_error_message(), "error");
                            continue;
                        }
                    } else {
                        addLog("ℹ️ Atrybut globalny już istnieje: {$attr_name} (ID: {$attribute_id})", "info");
                    }

                    // Sprawdź czy taksonomia istnieje
                    if (!taxonomy_exists($taxonomy)) {
                        addLog("⚠️ Taksonomia {$taxonomy} nie istnieje - próbuję utworzyć ręcznie", "warning");

                        // Ręczna rejestracja taksonomii jako backup
                        register_taxonomy($taxonomy, 'product', [
                            'hierarchical' => false,
                            'show_ui' => false,
                            'query_var' => true,
                            'rewrite' => false,
                            'public' => false,
                        ]);

                        // Sprawdź ponownie
                        if (!taxonomy_exists($taxonomy)) {
                            addLog("❌ Nie udało się utworzyć taksonomii {$taxonomy} - pomijam atrybut", "error");
                            continue;
                        } else {
                            addLog("✅ Ręcznie utworzono taksonomię: {$taxonomy}", "success");
                        }
                    } else {
                        addLog("✅ Taksonomia {$taxonomy} istnieje", "info");
                    }

                    // Utworz terminy dla wartości atrybutu
                    $term_ids = array();
                    addLog("🔧 Tworzenie terminów dla atrybutu {$attr_name} w taksonomii {$taxonomy}", "info");

                    foreach ($values as $value) {
                        addLog("  🔍 Sprawdzanie terminu: '{$value}' w taksonomii: {$taxonomy}", "info");

                        $term = get_term_by('name', $value, $taxonomy);
                        if (!$term) {
                            addLog("  ➕ Tworzenie nowego terminu: {$value}", "info");
                            $term = wp_insert_term($value, $taxonomy);
                            if (!is_wp_error($term)) {
                                $term_ids[] = $term['term_id'];
                                addLog("  ✅ Utworzono wartość: {$value} (ID: {$term['term_id']})", "success");
                            } else {
                                addLog("  ❌ Błąd tworzenia wartości: {$value} - " . $term->get_error_message(), "error");
                                addLog("  🔍 DEBUG: Taksonomia istnieje? " . (taxonomy_exists($taxonomy) ? 'TAK' : 'NIE'), "error");
                            }
                        } else {
                            $term_ids[] = $term->term_id;
                            addLog("  ✓ Wartość istnieje: {$value} (ID: {$term->term_id})", "info");
                        }
                    }

                    addLog("📊 Zebrano " . count($term_ids) . " terminów dla atrybutu {$attr_name}: " . implode(',', $term_ids), "info");

                    // Utwórz atrybut WooCommerce i zachowaj informacje o terminach
                    if (!empty($term_ids)) {
                        // SPRAWDŹ CZY ATRYBUT MA BYĆ UŻYWANY DO WARIANTÓW
                        $is_variation_attribute = false;
                        if (isset($attribute_xml->variation)) {
                            $variation_flag = trim((string) $attribute_xml->variation);
                            $is_variation_attribute = ($variation_flag === 'yes' || $variation_flag === '1');
                        }

                        $wc_attribute = new WC_Product_Attribute();
                        $wc_attribute->set_id($attribute_id); // Ustaw ID atrybutu globalnego
                        $wc_attribute->set_name($taxonomy); // Dla atrybutów globalnych używaj nazwy taksonomii
                        $wc_attribute->set_options($term_ids);
                        $wc_attribute->set_visible(true);
                        $wc_attribute->set_variation($is_variation_attribute); // ✅ USTAWIENIE DLA WARIANTÓW
                        $wc_attributes[] = $wc_attribute;

                        if ($is_variation_attribute) {
                            addLog("  🔄 Atrybut '{$attr_name}' oznaczony jako dla wariantów", "success");
                        }

                        // Zachowaj informacje o terminach do przypisania po zapisaniu produktu
                        $attributes_to_assign[] = [
                            'taxonomy' => $taxonomy,
                            'term_ids' => $term_ids,
                            'name' => $attr_name
                        ];

                        $attributes_processed++;
                        addLog("  ✅ Przygotowano atrybut globalny: {$attr_name} (ID: {$attribute_id}, taksonomia: {$taxonomy}) z " . count($term_ids) . " wartościami", "success");
                    }
                }

                if ($attributes_processed > 0) {
                    addLog("✅ Przetworzono {$attributes_processed} atrybutów", "success");
                } else {
                    addLog("⚠️ Nie znaleziono poprawnych atrybutów do przetworzenia", "warning");
                }
            } else {
                addLog("⚠️ Brak sekcji <attributes> w XML", "warning");
            }

            // Ustaw wszystkie atrybuty na produkcie
            if (!empty($wc_attributes)) {
                addLog("🔧 Ustawianie " . count($wc_attributes) . " atrybutów na produkcie", "info");

                // Debug - pokaż szczegóły atrybutów
                foreach ($wc_attributes as $index => $wc_attr) {
                    addLog("  📋 Atrybut " . ($index + 1) . ": ID=" . $wc_attr->get_id() . ", Nazwa=" . $wc_attr->get_name() . ", Opcje=" . implode(',', $wc_attr->get_options()), "info");
                }

                $product->set_attributes($wc_attributes);
                addLog("🏷️ Ustawiono " . count($wc_attributes) . " atrybutów na produkcie", "success");

                // Weryfikacja - sprawdź czy atrybuty zostały ustawione
                $set_attributes = $product->get_attributes();
                addLog("✅ Weryfikacja: Produkt ma " . count($set_attributes) . " atrybutów", "info");
            } else {
                addLog("⚠️ Brak atrybutów WooCommerce do ustawienia", "warning");
            }

            // ZAPISZ PRODUKT żeby uzyskać ID
            $saved_product_id = $product->save();

            if (!$saved_product_id) {
                throw new Exception("Nie można zapisać produktu");
            }

            // Użyj odpowiedniego ID produktu
            if ($is_update) {
                // Dla aktualizacji użyj oryginalnego ID
                $final_product_id = $product_id;
            } else {
                // Dla nowego produktu użyj ID z save()
                $final_product_id = $saved_product_id;
                $product_id = $saved_product_id; // Ustaw także dla dalszego kodu
            }

            // PRZYPISZ TERMINY ATRYBUTÓW - to jest kluczowe dla poprawnego wyświetlania!
            if (!empty($attributes_to_assign)) {
                addLog("🔗 Przypisuję terminy atrybutów do produktu ID: {$final_product_id}", "info");
                addLog("🔍 DEBUG: Liczba atrybutów do przypisania: " . count($attributes_to_assign), "info");

                foreach ($attributes_to_assign as $attr_info) {
                    addLog("  🔧 Przypisywanie atrybutu: {$attr_info['name']} (taksonomia: {$attr_info['taxonomy']})", "info");
                    addLog("  📋 Terminy do przypisania: " . implode(',', $attr_info['term_ids']), "info");

                    // Sprawdź czy taksonomia nadal istnieje
                    if (!taxonomy_exists($attr_info['taxonomy'])) {
                        addLog("  ❌ Taksonomia {$attr_info['taxonomy']} nie istnieje podczas przypisywania!", "error");
                        continue;
                    }

                    $result = wp_set_object_terms($final_product_id, $attr_info['term_ids'], $attr_info['taxonomy']);
                    if (!is_wp_error($result)) {
                        addLog("  ✅ Przypisano " . count($attr_info['term_ids']) . " wartości dla atrybutu {$attr_info['name']}", "success");
                        addLog("  🔍 Wynik wp_set_object_terms: " . print_r($result, true), "info");

                        // Weryfikacja - sprawdź czy terminy zostały przypisane
                        $assigned_terms = wp_get_object_terms($final_product_id, $attr_info['taxonomy'], ['fields' => 'ids']);
                        if (!is_wp_error($assigned_terms)) {
                            addLog("  ✅ Weryfikacja: Przypisane terminy: " . implode(',', $assigned_terms), "info");
                        } else {
                            addLog("  ⚠️ Błąd weryfikacji: " . $assigned_terms->get_error_message(), "warning");
                        }
                    } else {
                        addLog("  ❌ Błąd przypisania atrybutu {$attr_info['name']}: " . $result->get_error_message(), "error");
                    }
                }
                addLog("🏷️ Zakończono przypisywanie atrybutów", "success");

                // Finalna weryfikacja - sprawdź atrybuty produktu po wszystkich operacjach
                addLog("🔍 FINALNA WERYFIKACJA ATRYBUTÓW dla produktu ID: {$final_product_id}", "info");
                $final_product = wc_get_product($final_product_id);
                if ($final_product) {
                    $final_attributes = $final_product->get_attributes();
                    addLog("📊 Produkt ma łącznie " . count($final_attributes) . " atrybutów", "info");

                    foreach ($final_attributes as $attr_name => $attr_obj) {
                        if ($attr_obj instanceof WC_Product_Attribute) {
                            $options = $attr_obj->get_options();
                            addLog("  🏷️ {$attr_name}: " . count($options) . " opcji (" . implode(',', $options) . ")", "info");
                        }
                    }
                } else {
                    addLog("❌ Nie można załadować produktu do weryfikacji", "error");
                }
            } else {
                addLog("⚠️ Brak atrybutów do przypisania", "warning");
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
                        wp_set_object_terms($final_product_id, $category_ids, 'product_cat');
                        addLog("✅ Przypisano " . count($category_ids) . " kategorii", "success");
                    }
                }
            }

            // MARKI - mapowanie z atrybutów XML do taksonomii WooCommerce
            $brand_name = '';

            // Szukaj marki w atrybutach (najczęściej "Marka", "Brand", "Manufacturer")
            if (isset($product_xml->attributes) && isset($product_xml->attributes->attribute)) {
                foreach ($product_xml->attributes->attribute as $attribute_xml) {
                    $attr_name = trim((string) $attribute_xml->name);
                    $attr_value = trim((string) $attribute_xml->value);

                    // Sprawdź czy to atrybut marki (różne możliwe nazwy)
                    $brand_attribute_names = ['marka', 'brand', 'manufacturer', 'producent', 'firma'];

                    if (in_array(strtolower($attr_name), $brand_attribute_names) && !empty($attr_value)) {
                        $brand_name = $attr_value;
                        addLog("🔍 Znaleziono markę w atrybucie '{$attr_name}': {$brand_name}", "info");
                        break; // Użyj pierwszej znalezionej marki
                    }
                }
            }

            // Jeśli nie znaleziono w atrybutach, sprawdź bezpośrednie pola XML (backup)
            if (empty($brand_name)) {
                if (isset($product_xml->brand) && !empty(trim((string) $product_xml->brand))) {
                    $brand_name = trim((string) $product_xml->brand);
                    addLog("🔍 Znaleziono markę w polu 'brand': {$brand_name}", "info");
                } elseif (isset($product_xml->manufacturer) && !empty(trim((string) $product_xml->manufacturer))) {
                    $brand_name = trim((string) $product_xml->manufacturer);
                    addLog("🔍 Znaleziono markę w polu 'manufacturer': {$brand_name}", "info");
                }
            }

            if (!empty($brand_name)) {
                addLog("🏷️ Przetwarzam markę: {$brand_name}", "info");

                $brand_result = process_product_brand($brand_name, $final_product_id);
                if ($brand_result['success']) {
                    addLog("✅ " . $brand_result['message'], "success");
                } else {
                    addLog("⚠️ " . $brand_result['message'], "warning");
                }
            } else {
                addLog("ℹ️ Brak marki w XML (sprawdzano atrybuty: marka, brand, manufacturer, producent, firma)", "info");
            }

            // OBRAZY - obsługa <image src="URL"/> z ulepszonym systemem galerii
            if (isset($product_xml->images) && $product_xml->images->image) {
                $images = $product_xml->images->image;
                addLog("🔍 DEBUG: Typ images przed konwersją: " . gettype($images), "info");
                addLog("🔍 DEBUG: Czy images jest obiektem SimpleXML: " . (is_object($images) ? 'TAK' : 'NIE'), "info");

                // Konwertuj SimpleXML do tablicy
                if (is_object($images) && get_class($images) === 'SimpleXMLElement') {
                    // Jeśli to pojedynczy element SimpleXML, sprawdź czy ma dzieci
                    $images_array = [];
                    foreach ($images as $image) {
                        $images_array[] = $image;
                    }
                    if (empty($images_array)) {
                        // Jeśli brak dzieci, to znaczy że to pojedynczy element
                        $images_array = [$images];
                    }
                    $images = $images_array;
                    addLog("🔄 Skonwertowano SimpleXML do tablicy: " . count($images) . " elementów", "info");
                } elseif (!is_array($images)) {
                    $images = [$images];
                    addLog("🔄 Skonwertowano do tablicy: " . count($images) . " elementów", "info");
                }

                addLog("📷 Znaleziono " . count($images) . " obrazków w XML", "info");
                addLog("🔍 DEBUG: Typ images po konwersji: " . gettype($images), "info");

                // Opcjonalnie wyczyść starą galerię przy aktualizacji
                if ($is_update) {
                    // Sprawdź czy chcemy zastąpić obrazy (można dodać parametr URL)
                    $replace_images = isset($_GET['replace_images']) ? (bool) $_GET['replace_images'] : false;

                    if ($replace_images) {
                        addLog("🧹 Aktualizacja: Czyszczenie starej galerii...", "info");
                        $clean_result = clean_product_gallery($final_product_id, false); // false = nie usuwaj głównego obrazu
                        if ($clean_result['removed_count'] > 0) {
                            addLog("✅ Usunięto " . $clean_result['removed_count'] . " starych obrazów galerii", "success");
                        }
                    } else {
                        addLog("ℹ️ Aktualizacja: Dodawanie obrazów do istniejącej galerii (użyj &replace_images=1 aby zastąpić)", "info");
                    }
                }

                // Użyj nowej funkcji do importu galerii
                addLog("🚀 WYWOŁUJĘ import_product_gallery z " . count($images) . " obrazami dla produktu ID: {$final_product_id}", "info");
                $gallery_result = import_product_gallery($images, $final_product_id);
                addLog("🏁 ZAKOŃCZONO import_product_gallery, wynik: " . ($gallery_result['success'] ? 'SUKCES' : 'BŁĄD'), "info");

                if ($gallery_result['success']) {
                    $stats['images'] += $gallery_result['imported_count'];
                    addLog("🖼️ Galeria produktu: " . $gallery_result['message'], "success");

                    // Pokaż raport galerii dla debugowania
                    log_product_gallery_report($final_product_id);
                } else {
                    addLog("❌ Błąd galerii: " . $gallery_result['message'], "error");
                }
            } else {
                addLog("⚠️ Brak sekcji <images> w XML", "warning");
            }

            // CUSTOM FIELDS (META_DATA) - obsługa <meta_data> z XML
            if (isset($product_xml->meta_data) && $product_xml->meta_data->meta) {
                addLog("🔧 Przetwarzam custom fields (meta_data)...", "info");

                $meta_count = 0;
                foreach ($product_xml->meta_data->meta as $meta_xml) {
                    $meta_key = trim((string) $meta_xml->key);
                    $meta_value = trim((string) $meta_xml->value);

                    if (empty($meta_key)) {
                        continue;
                    }

                    // Zapisz jako custom field
                    update_post_meta($final_product_id, $meta_key, $meta_value);
                    $meta_count++;

                    addLog("  🔹 Custom field: {$meta_key} = " . (strlen($meta_value) > 50 ? substr($meta_value, 0, 50) . '...' : $meta_value), "info");
                }

                if ($meta_count > 0) {
                    addLog("✅ Dodano {$meta_count} custom fields", "success");
                }
            } else {
                addLog("ℹ️ Brak sekcji <meta_data> w XML", "info");
            }

            // GENEROWANIE WARIANTÓW - nowa funkcjonalność!
            // Sprawdź czy generowanie wariantów jest włączone (domyślnie TAK)
            $generate_variations = !isset($_GET['generate_variations']) || $_GET['generate_variations'] !== '0';

            if ($has_variations && !empty($wc_attributes) && $generate_variations) {
                addLog("🔄 Rozpoczynam generowanie wariantów dla produktu z wariantami...", "info");

                // Sprawdź które atrybuty są oznaczone jako variation
                $variation_attributes = [];
                foreach ($wc_attributes as $wc_attr) {
                    if ($wc_attr->get_variation()) {
                        $variation_attributes[] = $wc_attr;
                        addLog("  🔄 Atrybut dla wariantów: " . $wc_attr->get_name() . " z " . count($wc_attr->get_options()) . " opcjami", "info");
                    }
                }

                if (!empty($variation_attributes)) {
                    $variations_result = generate_product_variations($final_product_id, $variation_attributes, $product_xml);
                    if ($variations_result['success']) {
                        addLog("✅ " . $variations_result['message'], "success");
                    } else {
                        addLog("⚠️ " . $variations_result['message'], "warning");
                    }
                } else {
                    addLog("ℹ️ Brak atrybutów oznaczonych jako variation - pomijam generowanie wariantów", "info");
                }
            } elseif ($has_variations && !$generate_variations) {
                addLog("ℹ️ Generowanie wariantów wyłączone parametrem generate_variations=0", "info");
            }

            // Oznacz jako importowany
            update_post_meta($final_product_id, '_mhi_imported', 'yes');
            update_post_meta($final_product_id, '_mhi_supplier', $supplier);
            update_post_meta($final_product_id, '_mhi_import_date', current_time('mysql'));

            // Statystyki
            if ($is_update) {
                $stats['updated']++;
                addLog("✅ Zaktualizowano produkt ID: {$final_product_id}", "success");
            } else {
                $stats['created']++;
                addLog("✅ Utworzono produkt ID: {$final_product_id}", "success");
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
    
    /**
     * Generuje warianty produktu na podstawie atrybutów variation
     * Wszystkie warianty będą miały takie same parametry jak produkt główny
     * 
     * @param int $product_id ID produktu głównego
     * @param array $variation_attributes Atrybuty oznaczone jako variation
     * @param SimpleXMLElement $product_xml XML produktu z danymi
     * @return array Wynik operacji
     */
    function generate_product_variations($product_id, $variation_attributes, $product_xml)
    {
        try {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== 'variable') {
                return ['success' => false, 'message' => 'Produkt nie jest typu variable'];
            }

            addLog("🔧 Generowanie wariantów dla produktu ID: {$product_id}", "info");

            // Pobierz dane z XML do skopiowania do wariantów
            $base_data = [
                'regular_price' => trim((string) $product_xml->regular_price),
                'sale_price' => trim((string) $product_xml->sale_price),
                'weight' => trim((string) $product_xml->weight),
                'length' => trim((string) $product_xml->length),
                'width' => trim((string) $product_xml->width),
                'height' => trim((string) $product_xml->height),
                'stock_quantity' => trim((string) $product_xml->stock_quantity),
                'description' => trim((string) $product_xml->description),
                'short_description' => trim((string) $product_xml->short_description)
            ];

            // Przygotuj kombinacje atrybutów
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
                    addLog("  📋 Atrybut {$taxonomy}: " . implode(', ', $terms), "info");
                }
            }

            if (empty($attribute_combinations)) {
                return ['success' => false, 'message' => 'Brak kombinacji atrybutów do wygenerowania'];
            }

            // Wygeneruj wszystkie możliwe kombinacje
            $combinations = generate_attribute_combinations($attribute_combinations);
            addLog("🔢 Wygenerowano " . count($combinations) . " kombinacji wariantów", "info");

            $created_variations = 0;
            $updated_variations = 0;

            foreach ($combinations as $combination) {
                // Sprawdź czy wariant już istnieje
                $existing_variation_id = find_matching_variation($product_id, $combination);

                if ($existing_variation_id) {
                    // Aktualizuj istniejący wariant
                    $variation = wc_get_product($existing_variation_id);
                    addLog("  📝 Aktualizuję istniejący wariant ID: {$existing_variation_id}", "info");
                    $updated_variations++;
                } else {
                    // Utwórz nowy wariant
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    addLog("  ➕ Tworzę nowy wariant", "info");
                    $created_variations++;
                }

                // Ustaw atrybuty wariantu
                $variation->set_attributes($combination);

                // Skopiuj wszystkie dane z produktu głównego
                if (!empty($base_data['regular_price']) && is_numeric(str_replace(',', '.', $base_data['regular_price']))) {
                    $variation->set_regular_price(str_replace(',', '.', $base_data['regular_price']));
                }

                if (!empty($base_data['sale_price']) && is_numeric(str_replace(',', '.', $base_data['sale_price']))) {
                    $variation->set_sale_price(str_replace(',', '.', $base_data['sale_price']));
                }

                if (!empty($base_data['weight'])) {
                    $variation->set_weight($base_data['weight']);
                }

                if (!empty($base_data['length'])) {
                    $variation->set_length($base_data['length']);
                }

                if (!empty($base_data['width'])) {
                    $variation->set_width($base_data['width']);
                }

                if (!empty($base_data['height'])) {
                    $variation->set_height($base_data['height']);
                }

                // Zarządzanie stanem magazynowym
                if (!empty($base_data['stock_quantity']) && is_numeric($base_data['stock_quantity'])) {
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity((int) $base_data['stock_quantity']);
                    $variation->set_stock_status('instock');
                } else {
                    $variation->set_manage_stock(false);
                    $variation->set_stock_status('instock');
                }

                // Ustaw status
                $variation->set_status('publish');

                // Zapisz wariant
                $variation_id = $variation->save();

                if ($variation_id) {
                    // Dodaj meta dane
                    update_post_meta($variation_id, '_mhi_imported', 'yes');
                    update_post_meta($variation_id, '_mhi_supplier', $_GET['supplier']);
                    update_post_meta($variation_id, '_mhi_import_date', current_time('mysql'));

                    $combination_text = [];
                    foreach ($combination as $attr => $value) {
                        $combination_text[] = str_replace('pa_', '', $attr) . ': ' . $value;
                    }
                    addLog("    ✅ Wariant: " . implode(', ', $combination_text), "success");
                } else {
                    addLog("    ❌ Błąd zapisywania wariantu", "error");
                }
            }

            // Synchronizuj warianty z produktem głównym
            WC_Product_Variable::sync($product_id);

            $total_variations = $created_variations + $updated_variations;
            $message = "Wygenerowano warianty: {$created_variations} nowych, {$updated_variations} zaktualizowanych (łącznie: {$total_variations})";

            return ['success' => true, 'message' => $message];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Błąd generowania wariantów: ' . $e->getMessage()];
        }
    }

    /**
     * Generuje wszystkie możliwe kombinacje atrybutów
     * 
     * @param array $attributes Tablica atrybutów [taxonomy => [values]]
     * @return array Kombinacje atrybutów
     */
    function generate_attribute_combinations($attributes)
    {
        $combinations = [[]];

        foreach ($attributes as $taxonomy => $values) {
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

        return $combinations;
    }

    /**
     * Znajduje istniejący wariant o podanych atrybutach
     * 
     * @param int $product_id ID produktu głównego
     * @param array $attributes Atrybuty do wyszukania
     * @return int|false ID wariantu lub false jeśli nie znaleziono
     */
    function find_matching_variation($product_id, $attributes)
    {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'variable') {
            return false;
        }

        $variations = $product->get_children();

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation)
                continue;

            $variation_attributes = $variation->get_attributes();

            // Sprawdź czy wszystkie atrybuty się zgadzają
            $match = true;
            foreach ($attributes as $taxonomy => $value) {
                $variation_value = isset($variation_attributes[$taxonomy]) ? $variation_attributes[$taxonomy] : '';
                if ($variation_value !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return $variation_id;
            }
        }

        return false;
    }

    function addLog($message, $type = "info")
    {
        echo '<script>addLog(' . json_encode($message) . ', "' . $type . '");</script>';
        flush();
    }

    function process_product_categories($categories_text)
    {
        $category_ids = [];

        if (strpos($categories_text, '>') !== false) {
            // Hierarchia kategorii - przypisz WSZYSTKIE kategorie z hierarchii
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
                        // Dodaj każdą kategorię do listy (główną i wszystkie podkategorie)
                        $category_ids[] = $parent_id;
                        addLog("  ➕ Utworzono kategorię: {$part} (ID: {$parent_id})", "info");
                    }
                } else {
                    $parent_id = $term->term_id;
                    // Dodaj każdą kategorię do listy (główną i wszystkie podkategorie)
                    $category_ids[] = $parent_id;
                    addLog("  ✓ Znaleziono kategorię: {$part} (ID: {$parent_id})", "info");
                }
            }
        } else {
            // Pojedyncza kategoria
            $term = get_term_by('name', $categories_text, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($categories_text, 'product_cat');
                if (!is_wp_error($term)) {
                    $category_ids[] = $term['term_id'];
                    addLog("  ➕ Utworzono kategorię: {$categories_text} (ID: {$term['term_id']})", "info");
                }
            } else {
                $category_ids[] = $term->term_id;
                addLog("  ✓ Znaleziono kategorię: {$categories_text} (ID: {$term->term_id})", "info");
            }
        }

        // Usuń duplikaty i zwróć unikalne ID kategorii
        $category_ids = array_unique($category_ids);
        addLog("📁 Finalne kategorie do przypisania: " . implode(', ', $category_ids), "success");

        return $category_ids;
    }

    /**
     * Przetwarza markę produktu i przypisuje ją do odpowiedniej taksonomii
     * Automatycznie wykrywa czy istnieje taksonomia marek i ją używa
     * 
     * @param string $brand_name Nazwa marki z XML
     * @param int $product_id ID produktu
     * @return array Wynik operacji z informacjami o sukcesie
     */
    function process_product_brand($brand_name, $product_id)
    {
        // Lista możliwych taksonomii marek w WooCommerce
        $possible_brand_taxonomies = [
            'product_brand',    // Najpopularniejsza
            'pwb-brand',       // Perfect WooCommerce Brands
            'yith_product_brand', // YITH WooCommerce Brands
            'product_brands',   // Alternatywna nazwa
            'brands',          // Prosta nazwa
            'pa_brand',        // Jako atrybut globalny
            'pa_marka'         // Polski atrybut globalny
        ];

        $brand_taxonomy = null;

        // Znajdź pierwszą istniejącą taksonomię marek
        foreach ($possible_brand_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $brand_taxonomy = $taxonomy;
                addLog("  🔍 Znaleziono taksonomię marek: {$taxonomy}", "info");
                break;
            }
        }

        // Jeśli nie ma żadnej taksonomii marek, utwórz prostą
        if (!$brand_taxonomy) {
            addLog("  ⚠️ Brak taksonomii marek - tworzę 'product_brand'", "warning");

            // Zarejestruj taksonomię marek
            register_taxonomy('product_brand', 'product', [
                'label' => 'Marki',
                'labels' => [
                    'name' => 'Marki',
                    'singular_name' => 'Marka',
                    'menu_name' => 'Marki',
                    'all_items' => 'Wszystkie marki',
                    'edit_item' => 'Edytuj markę',
                    'view_item' => 'Zobacz markę',
                    'update_item' => 'Aktualizuj markę',
                    'add_new_item' => 'Dodaj nową markę',
                    'new_item_name' => 'Nazwa nowej marki',
                    'search_items' => 'Szukaj marek',
                    'not_found' => 'Nie znaleziono marek'
                ],
                'hierarchical' => false,
                'public' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'marka'],
                'query_var' => true,
            ]);

            $brand_taxonomy = 'product_brand';
            addLog("  ✅ Utworzono taksonomię marek: product_brand", "success");
        }

        // Sprawdź czy marka już istnieje
        $existing_term = get_term_by('name', $brand_name, $brand_taxonomy);

        if (!$existing_term) {
            // Utwórz nową markę
            $term_result = wp_insert_term($brand_name, $brand_taxonomy, [
                'description' => "Marka: {$brand_name}",
                'slug' => sanitize_title($brand_name)
            ]);

            if (is_wp_error($term_result)) {
                return [
                    'success' => false,
                    'message' => "Błąd tworzenia marki {$brand_name}: " . $term_result->get_error_message(),
                    'taxonomy' => $brand_taxonomy
                ];
            }

            $brand_term_id = $term_result['term_id'];
            addLog("  ➕ Utworzono markę: {$brand_name} (ID: {$brand_term_id})", "info");
        } else {
            $brand_term_id = $existing_term->term_id;
            addLog("  ✓ Marka istnieje: {$brand_name} (ID: {$brand_term_id})", "info");
        }

        // Przypisz markę do produktu
        $assign_result = wp_set_object_terms($product_id, [$brand_term_id], $brand_taxonomy);

        if (is_wp_error($assign_result)) {
            return [
                'success' => false,
                'message' => "Błąd przypisania marki {$brand_name}: " . $assign_result->get_error_message(),
                'taxonomy' => $brand_taxonomy
            ];
        }

        // Weryfikacja - sprawdź czy marka została przypisana
        $assigned_brands = wp_get_object_terms($product_id, $brand_taxonomy, ['fields' => 'names']);
        if (!is_wp_error($assigned_brands) && in_array($brand_name, $assigned_brands)) {
            addLog("  ✅ Weryfikacja: Marka {$brand_name} przypisana do produktu", "info");
        }

        return [
            'success' => true,
            'message' => "Przypisano markę: {$brand_name} (taksonomia: {$brand_taxonomy})",
            'taxonomy' => $brand_taxonomy,
            'term_id' => $brand_term_id,
            'brand_name' => $brand_name
        ];
    }

    /**
     * Czyści starą galerię produktu (opcjonalnie)
     * Usuwa obrazy które nie są już potrzebne
     * 
     * @param int $product_id ID produktu
     * @param bool $remove_featured Czy usunąć także główny obraz
     * @return array Informacje o usuniętych obrazach
     */
    function clean_product_gallery($product_id, $remove_featured = false)
    {
        addLog("🧹 Czyszczenie galerii produktu ID: {$product_id}", "info");

        $removed_count = 0;
        $errors = [];

        // Pobierz obecne obrazy galerii
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery_ids)) {
            $gallery_ids = explode(',', $gallery_ids);
            $gallery_ids = array_filter($gallery_ids);

            foreach ($gallery_ids as $attachment_id) {
                if (wp_delete_attachment($attachment_id, true)) {
                    $removed_count++;
                    addLog("  🗑️ Usunięto obraz galerii ID: {$attachment_id}", "info");
                } else {
                    $errors[] = $attachment_id;
                    addLog("  ❌ Nie można usunąć obrazu galerii ID: {$attachment_id}", "warning");
                }
            }

            // Wyczyść meta galerii
            delete_post_meta($product_id, '_product_image_gallery');
        }

        // Usuń główny obraz jeśli wymagane
        if ($remove_featured) {
            $featured_id = get_post_thumbnail_id($product_id);
            if ($featured_id) {
                if (wp_delete_attachment($featured_id, true)) {
                    delete_post_thumbnail($product_id);
                    $removed_count++;
                    addLog("  🗑️ Usunięto główny obraz ID: {$featured_id}", "info");
                } else {
                    $errors[] = $featured_id;
                    addLog("  ❌ Nie można usunąć głównego obrazu ID: {$featured_id}", "warning");
                }
            }
        }

        return [
            'removed_count' => $removed_count,
            'errors' => $errors
        ];
    }

    /**
     * Importuje galerię obrazów dla produktu
     * Pierwszy obraz staje się głównym zdjęciem produktu, reszta idzie do galerii
     * 
     * @param array $images Tablica obrazów z XML
     * @param int $product_id ID produktu
     * @return array Wynik operacji z informacjami o sukcesie
     */
    function import_product_gallery($images, $product_id)
    {
        addLog("🎨 ROZPOCZĘCIE import_product_gallery dla produktu ID: {$product_id}", "info");
        addLog("🔍 DEBUG: Typ parametru images: " . gettype($images), "info");
        addLog("🔍 DEBUG: Liczba images: " . (is_array($images) ? count($images) : (is_object($images) ? 'obiekt' : 'nie-tablica')), "info");

        // Sprawdź czy produkt istnieje
        $product = wc_get_product($product_id);
        if (!$product) {
            addLog("❌ Nie można załadować produktu ID: {$product_id}", "error");
            return [
                'success' => false,
                'message' => "Produkt nie istnieje",
                'imported_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0
            ];
        }

        $image_ids = [];
        $imported_count = 0;
        $failed_count = 0;
        $skipped_count = 0;

        foreach ($images as $index => $image) {
            $image_url = '';
            $img_number = $index + 1;

            addLog("🔍 DEBUG: Przetwarzam obraz {$img_number}, typ: " . gettype($image), "info");
            if (is_object($image)) {
                addLog("🔍 DEBUG: Klasa obiektu: " . get_class($image), "info");
            }

            // Sprawdź różne formaty XML dla obrazów
            $attributes = $image->attributes();
            addLog("🔍 DEBUG: Atrybuty obrazu {$img_number}: " . (is_object($attributes) ? 'obiekt' : 'brak'), "info");

            if (isset($attributes['src'])) {
                // Format: <image src="URL"/>
                $image_url = trim((string) $attributes['src']);
                addLog("  📸 Obraz {$img_number} - Format src attr: {$image_url}", "info");
            } elseif (isset($image->src)) {
                // Format Macma: <image><src>URL</src></image>
                $image_url = trim((string) $image->src);
                addLog("  📸 Obraz {$img_number} - Format src element: {$image_url}", "info");
            } else {
                // Format standardowy: <image>URL</image>
                $image_url = trim((string) $image);
                addLog("  📸 Obraz {$img_number} - Format zawartość: {$image_url}", "info");
            }

            // Walidacja URL
            if (empty($image_url)) {
                addLog("  ⚠️ Obraz {$img_number}: Pusty URL - pomijam", "warning");
                $skipped_count++;
                continue;
            }

            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                addLog("  ⚠️ Obraz {$img_number}: Nieprawidłowy URL ({$image_url}) - pomijam", "warning");
                $skipped_count++;
                continue;
            }

            // Określ czy to główny obraz (pierwszy)
            $is_featured = ($index === 0);
            $image_type = $is_featured ? "GŁÓWNY" : "GALERIA";

            addLog("  📥 Obraz {$img_number} ({$image_type}): Pobieram {$image_url}", "info");

            // Importuj obraz
            $attachment_id = import_product_image($image_url, $product_id, $is_featured);

            if ($attachment_id) {
                $image_ids[] = $attachment_id;
                $imported_count++;
                addLog("  ✅ Obraz {$img_number} ({$image_type}) dodany - ID: {$attachment_id}", "success");
            } else {
                $failed_count++;
                addLog("  ❌ Obraz {$img_number} ({$image_type}): Błąd importu", "error");
            }
        }

        // Podsumowanie importu obrazów
        $total_processed = $imported_count + $failed_count + $skipped_count;
        addLog("📊 Podsumowanie obrazów: {$total_processed} przetworzonych, {$imported_count} zaimportowanych, {$failed_count} błędów, {$skipped_count} pominiętych", "info");

        // Konfiguracja galerii WooCommerce
        if ($imported_count > 0) {
            // Pobierz aktualny główny obraz
            $featured_id = get_post_thumbnail_id($product_id);
            addLog("🌟 Główny obraz produktu: ID {$featured_id}", "info");

            // Przygotuj galerię (wszystkie zaimportowane obrazy oprócz głównego)
            $new_gallery_ids = array_filter($image_ids, function ($id) use ($featured_id) {
                return $id != $featured_id;
            });

            addLog("🖼️ Nowe obrazy do galerii: " . count($new_gallery_ids) . " (" . implode(',', $new_gallery_ids) . ")", "info");

            // Sprawdź czy istnieją już obrazy w galerii (przy aktualizacji)
            $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
            $existing_gallery_ids = [];

            if (!empty($existing_gallery)) {
                $existing_gallery_ids = explode(',', $existing_gallery);
                $existing_gallery_ids = array_filter($existing_gallery_ids);
                addLog("📋 Istniejąca galeria: " . count($existing_gallery_ids) . " obrazów (" . implode(',', $existing_gallery_ids) . ")", "info");
            }

            // Określ finalną galerię
            $final_gallery_ids = [];

            if (!empty($existing_gallery_ids) && !empty($new_gallery_ids)) {
                // Połącz istniejące z nowymi, usuń duplikaty
                $final_gallery_ids = array_unique(array_merge($existing_gallery_ids, $new_gallery_ids));
                addLog("🔗 Łączenie galerii: " . count($existing_gallery_ids) . " istniejących + " . count($new_gallery_ids) . " nowych = " . count($final_gallery_ids) . " łącznie", "info");
            } elseif (!empty($new_gallery_ids)) {
                // Tylko nowe obrazy
                $final_gallery_ids = $new_gallery_ids;
                addLog("🆕 Nowa galeria: " . count($final_gallery_ids) . " obrazów", "info");
            } elseif (!empty($existing_gallery_ids)) {
                // Tylko istniejące obrazy (nie powinno się zdarzyć w tym kontekście)
                $final_gallery_ids = $existing_gallery_ids;
                addLog("📋 Zachowanie istniejącej galerii: " . count($final_gallery_ids) . " obrazów", "info");
            }

            // Ustaw galerię w WooCommerce
            if (!empty($final_gallery_ids)) {
                // Ustaw galerię w meta
                update_post_meta($product_id, '_product_image_gallery', implode(',', $final_gallery_ids));
                addLog("💾 Meta galerii zapisana: " . implode(',', $final_gallery_ids), "info");

                // Ustaw galerię przez WooCommerce API
                $product_fresh = wc_get_product($product_id); // Pobierz świeży obiekt produktu
                if ($product_fresh) {
                    // Wyczyść cache produktu
                    wp_cache_delete($product_id, 'posts');
                    wp_cache_delete($product_id, 'post_meta');

                    $product_fresh->set_gallery_image_ids($final_gallery_ids);
                    $save_result = $product_fresh->save();

                    if ($save_result) {
                        addLog("🖼️ Galeria WooCommerce: Ustawiono " . count($final_gallery_ids) . " obrazów w galerii", "success");

                        // Weryfikacja - sprawdź czy galeria została ustawiona
                        force_refresh_product_gallery($product_id);
                        $verification_product = wc_get_product($product_id);
                        $verification_gallery = $verification_product->get_gallery_image_ids();
                        addLog("✅ Weryfikacja galerii: " . count($verification_gallery) . " obrazów (" . implode(',', $verification_gallery) . ")", "info");

                        // Dodatkowa weryfikacja przez meta
                        $meta_gallery = get_post_meta($product_id, '_product_image_gallery', true);
                        addLog("🔍 Meta galerii: " . ($meta_gallery ?: 'brak'), "info");
                    } else {
                        addLog("❌ Nie udało się zapisać galerii produktu", "error");
                    }
                } else {
                    addLog("⚠️ Nie można załadować produktu WooCommerce ID: {$product_id}", "warning");
                }

                $message = "Główny obraz + galeria z " . count($final_gallery_ids) . " obrazami (zaimportowano: {$imported_count})";
            } else {
                $message = "Tylko główny obraz (zaimportowano: {$imported_count})";
                addLog("ℹ️ Brak obrazów do galerii - tylko główny obraz", "info");
            }

            // Dodatkowe meta dla śledzenia
            update_post_meta($product_id, '_mhi_gallery_count', count($final_gallery_ids ?? []));
            update_post_meta($product_id, '_mhi_total_images', $imported_count);
            update_post_meta($product_id, '_mhi_gallery_updated', current_time('mysql'));

            return [
                'success' => true,
                'message' => $message,
                'imported_count' => $imported_count,
                'failed_count' => $failed_count,
                'skipped_count' => $skipped_count,
                'featured_id' => $featured_id,
                'gallery_ids' => $final_gallery_ids ?? [],
                'total_images' => count($image_ids)
            ];
        } else {
            return [
                'success' => false,
                'message' => "Nie udało się zaimportować żadnego obrazu ({$failed_count} błędów, {$skipped_count} pominiętych)",
                'imported_count' => 0,
                'failed_count' => $failed_count,
                'skipped_count' => $skipped_count
            ];
        }
    }

    function import_product_image($image_url, $product_id, $is_featured = false)
    {
        addLog("🚀 ROZPOCZĘCIE import_product_image - URL: " . $image_url, "info");

        // Sprawdź czy obraz już istnieje
        addLog("🔍 Sprawdzanie czy obraz już istnieje...", "info");
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

        addLog("✅ Sprawdzenie zakończone. Znaleziono: " . count($existing) . " istniejących obrazów", "info");

        if ($existing) {
            $attach_id = $existing[0]->ID;
            if ($is_featured) {
                set_post_thumbnail($product_id, $attach_id);
            }
            addLog("♻️ Użyto istniejący obraz (ID: {$attach_id})", "info");
            return $attach_id;
        }

        // Generuj losową datę z ostatnich 18 miesięcy dla lepszej organizacji folderów
        $months_back = rand(1, 18); // losowo 1-18 miesięcy wstecz
        $random_timestamp = strtotime("-{$months_back} months");

        // Dodatkowo losuj dzień w miesiącu
        $year = (int) date('Y', $random_timestamp);
        $month = (int) date('m', $random_timestamp);
        $day = rand(1, 28); // maksymalnie 28, żeby być bezpiecznym dla lutego
        $hour = rand(8, 18); // godziny robocze
        $minute = rand(0, 59);
        $second = rand(0, 59);

        $final_timestamp = mktime($hour, $minute, $second, $month, $day, $year);

        addLog("📅 Używam daty publikacji: " . date('Y-m-d H:i:s', $final_timestamp) . " (folder: " . date('Y/m', $final_timestamp) . ")", "info");

        // Użyj konkretnej daty dla wp_upload_dir - WordPress automatycznie utworzy folder roczno-miesięczny
        $upload_dir = wp_upload_dir(date('Y/m', $final_timestamp));

        // Sprawdź czy wp_upload_dir zwróciło prawidłowe dane
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            addLog("❌ Błąd wp_upload_dir: " . $upload_dir['error'], "error");
            return false;
        }

        addLog("📂 Upload dir - path: " . $upload_dir['path'] . ", url: " . $upload_dir['url'], "info");

        // Sprawdź czy folder istnieje i utwórz go jeśli nie
        if (!file_exists($upload_dir['path'])) {
            addLog("📁 Tworzenie folderu: " . $upload_dir['path'], "info");
            $created = wp_mkdir_p($upload_dir['path']);
            if (!$created) {
                addLog("❌ Nie udało się utworzyć folderu: " . $upload_dir['path'], "error");
                addLog("🔍 Sprawdzanie praw: " . (is_writable(dirname($upload_dir['path'])) ? 'OK' : 'BRAK'), "error");
                return false;
            }
            addLog("✅ Folder utworzony pomyślnie: " . $upload_dir['path'], "success");
        } else {
            addLog("✅ Folder już istnieje: " . $upload_dir['path'], "info");
        }

        // Sprawdź prawa zapisu
        if (!is_writable($upload_dir['path'])) {
            addLog("❌ Brak praw zapisu do folderu: " . $upload_dir['path'], "error");
            return false;
        }

        // Pobierz obraz z lepszą obsługą błędów
        addLog("🌐 Rozpoczynam pobieranie obrazu: " . $image_url, "info");

        $response = wp_remote_get($image_url, [
            'timeout' => 60,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => [
                'Accept' => 'image/*,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate'
            ]
        ]);

        addLog("📡 Odpowiedź HTTP otrzymana", "info");

        if (is_wp_error($response)) {
            addLog("❌ Błąd pobierania obrazu: " . $response->get_error_message(), "error");
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            addLog("❌ HTTP błąd {$response_code} dla obrazu: {$image_url}", "error");
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            addLog("❌ Puste dane obrazu z URL: {$image_url}", "error");
            return false;
        }

        // Sprawdź czy dane to rzeczywiście obraz
        $image_info = @getimagesizefromstring($image_data);
        if (!$image_info) {
            addLog("❌ Nieprawidłowe dane obrazu z URL: {$image_url}", "error");
            return false;
        }

        // Przygotuj nazwę pliku
        $original_filename = basename($image_url);
        $original_filename = sanitize_file_name($original_filename);

        // Usuń parametry URL z nazwy pliku
        $original_filename = preg_replace('/\?.*$/', '', $original_filename);

        // Dodaj timestamp żeby uniknąć duplikatów
        $filename_base = pathinfo($original_filename, PATHINFO_FILENAME);
        $original_extension = pathinfo($original_filename, PATHINFO_EXTENSION);

        // Zapisz tymczasowo oryginalny plik
        $temp_filename = time() . '_' . $filename_base . '.' . $original_extension;
        $temp_file_path = $upload_dir['path'] . '/' . $temp_filename;

        addLog("💾 Zapisywanie pliku do: " . $temp_file_path, "info");
        addLog("📊 Rozmiar danych obrazu: " . size_format(strlen($image_data)), "info");

        $bytes_written = file_put_contents($temp_file_path, $image_data);
        if ($bytes_written === false) {
            addLog("❌ Nie udało się zapisać tymczasowego pliku: {$temp_file_path}", "error");
            return false;
        }

        addLog("✅ Zapisano " . size_format($bytes_written) . " do pliku: " . basename($temp_file_path), "success");

        // Konwertuj do WebP jeśli możliwe
        $final_filename = $filename_base . '_' . time() . '.webp';
        $final_file_path = $upload_dir['path'] . '/' . $final_filename;

        $webp_converted = false;

        // Sprawdź czy GD obsługuje WebP
        if (function_exists('imagewebp') && function_exists('imagecreatefromstring')) {
            $source_image = @imagecreatefromstring($image_data);

            if ($source_image !== false) {
                // Optymalizuj obraz - ustaw maksymalną szerokość
                $max_width = 1200;
                $original_width = imagesx($source_image);
                $original_height = imagesy($source_image);

                if ($original_width > $max_width) {
                    $ratio = $max_width / $original_width;
                    $new_width = $max_width;
                    $new_height = intval($original_height * $ratio);

                    $resized_image = imagecreatetruecolor($new_width, $new_height);

                    // Zachowaj przezroczystość dla PNG
                    if ($image_info[2] == IMAGETYPE_PNG) {
                        imagealphablending($resized_image, false);
                        imagesavealpha($resized_image, true);
                        $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
                        imagefill($resized_image, 0, 0, $transparent);
                    }

                    imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
                    imagedestroy($source_image);
                    $source_image = $resized_image;

                    addLog("🖼️ Zmieniono rozmiar obrazu do {$new_width}x{$new_height}px", "info");
                }

                // Konwertuj do WebP
                if (@imagewebp($source_image, $final_file_path, 85)) {
                    $webp_converted = true;
                    addLog("✅ Skonwertowano do WebP: {$final_filename}", "success");
                } else {
                    addLog("⚠️ Nie udało się skonwertować do WebP, używam oryginalnego formatu", "warning");
                }

                imagedestroy($source_image);
            }
        } else {
            addLog("⚠️ GD nie obsługuje WebP lub brak funkcji, używam oryginalnego formatu", "warning");
        }

        // Jeśli konwersja WebP się nie udała, użyj oryginalnego pliku
        if (!$webp_converted) {
            $final_filename = $temp_filename;
            $final_file_path = $temp_file_path;
        } else {
            // Usuń tymczasowy plik oryginalny
            @unlink($temp_file_path);
        }

        // Dodaj do biblioteki mediów z odpowiednią datą publikacji
        $filetype = wp_check_filetype($final_filename, null);
        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $final_filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename_base),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_date' => date('Y-m-d H:i:s', $final_timestamp),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $final_timestamp),
            'post_modified' => date('Y-m-d H:i:s', $final_timestamp),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s', $final_timestamp)
        ];

        $attach_id = wp_insert_attachment($attachment, $final_file_path, $product_id);

        if (!$attach_id) {
            addLog("❌ Nie udało się utworzyć załącznika w WordPress", "error");
            @unlink($final_file_path);
            return false;
        }

        // Zapisz URL źródłowy i informacje o konwersji
        update_post_meta($attach_id, '_mhi_source_url', $image_url);
        update_post_meta($attach_id, '_mhi_webp_converted', $webp_converted ? 'yes' : 'no');
        update_post_meta($attach_id, '_mhi_original_format', $original_extension);
        update_post_meta($attach_id, '_mhi_random_date', date('Y-m-d H:i:s', $final_timestamp));
        update_post_meta($attach_id, '_mhi_folder_path', date('Y/m', $final_timestamp));

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $final_file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Ustaw jako główny obraz
        if ($is_featured) {
            $thumbnail_result = set_post_thumbnail($product_id, $attach_id);
            if ($thumbnail_result) {
                addLog("🌟 Ustawiono jako główny obraz produktu (ID: {$attach_id})", "success");

                // Weryfikacja - sprawdź czy główny obraz został ustawiony
                $verification_featured = get_post_thumbnail_id($product_id);
                addLog("✅ Weryfikacja głównego obrazu: ID {$verification_featured}", "info");
            } else {
                addLog("❌ Nie udało się ustawić głównego obrazu produktu", "error");
            }
        }

        $format_info = $webp_converted ? " (WebP)" : " ({$original_extension})";
        $folder_info = date('Y/m', $final_timestamp);
        addLog("📸 Dodano obraz: {$final_filename}{$format_info} → {$folder_info}/", "success");

        return $attach_id;
    }

    /**
     * Wymusza odświeżenie galerii produktu
     * Czyści cache i przeładowuje dane galerii
     * 
     * @param int $product_id ID produktu
     * @return bool Sukces operacji
     */
    function force_refresh_product_gallery($product_id)
    {
        // Wyczyść wszystkie cache związane z produktem
        wp_cache_delete($product_id, 'posts');
        wp_cache_delete($product_id, 'post_meta');
        clean_post_cache($product_id);

        // Wyczyść cache WooCommerce
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }

        // Przeładuj produkt
        $product = wc_get_product($product_id);
        if ($product) {
            // Wymuś ponowne załadowanie danych
            $product->read_meta_data(true);
            return true;
        }

        return false;
    }

    /**
     * Sprawdza i raportuje stan galerii produktu
     * Pomocna funkcja do debugowania
     * 
     * @param int $product_id ID produktu
     * @return array Informacje o galerii
     */
    function get_product_gallery_info($product_id)
    {
        $info = [
            'product_id' => $product_id,
            'featured_image' => null,
            'gallery_images' => [],
            'total_images' => 0,
            'gallery_meta' => '',
            'wc_gallery_ids' => []
        ];

        // Główny obraz
        $featured_id = get_post_thumbnail_id($product_id);
        if ($featured_id) {
            $info['featured_image'] = [
                'id' => $featured_id,
                'url' => wp_get_attachment_url($featured_id),
                'title' => get_the_title($featured_id)
            ];
        }

        // Galeria z meta
        $gallery_meta = get_post_meta($product_id, '_product_image_gallery', true);
        $info['gallery_meta'] = $gallery_meta;

        if (!empty($gallery_meta)) {
            $gallery_ids = explode(',', $gallery_meta);
            $gallery_ids = array_filter($gallery_ids);

            foreach ($gallery_ids as $id) {
                $info['gallery_images'][] = [
                    'id' => $id,
                    'url' => wp_get_attachment_url($id),
                    'title' => get_the_title($id)
                ];
            }
        }

        // Galeria z WooCommerce
        $product = wc_get_product($product_id);
        if ($product) {
            $info['wc_gallery_ids'] = $product->get_gallery_image_ids();
        }

        $info['total_images'] = count($info['gallery_images']) + ($info['featured_image'] ? 1 : 0);

        return $info;
    }

    /**
     * Wyświetla raport galerii produktu w logach
     * 
     * @param int $product_id ID produktu
     */
    function log_product_gallery_report($product_id)
    {
        // Wymuś odświeżenie przed raportem
        force_refresh_product_gallery($product_id);

        $info = get_product_gallery_info($product_id);

        addLog("📊 RAPORT GALERII dla produktu ID: {$product_id}", "info");

        if ($info['featured_image']) {
            addLog("  🌟 Główny obraz: ID {$info['featured_image']['id']} - {$info['featured_image']['title']}", "info");
        } else {
            addLog("  ⚠️ Brak głównego obrazu", "warning");
        }

        if (!empty($info['gallery_images'])) {
            addLog("  🖼️ Galeria: " . count($info['gallery_images']) . " obrazów", "info");
            foreach ($info['gallery_images'] as $index => $img) {
                addLog("    " . ($index + 1) . ". ID {$img['id']} - {$img['title']}", "info");
            }
        } else {
            addLog("  📷 Brak obrazów w galerii", "info");
        }

        addLog("  📈 Łącznie obrazów: {$info['total_images']}", "info");
        addLog("  🔧 Meta galerii: " . ($info['gallery_meta'] ?: 'brak'), "info");
        addLog("  🛒 WC galeria IDs: " . (empty($info['wc_gallery_ids']) ? 'brak' : implode(',', $info['wc_gallery_ids'])), "info");
    }

    // TESTOWANIE GALERII - dodaj ?test_gallery=ID_PRODUKTU do URL
    if (isset($_GET['test_gallery']) && is_numeric($_GET['test_gallery'])) {
        $test_product_id = (int) $_GET['test_gallery'];
        echo "<div style='background: #f0f8ff; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
        echo "<h3>🧪 TEST GALERII dla produktu ID: {$test_product_id}</h3>";

        // Wymuś odświeżenie przed testem
        force_refresh_product_gallery($test_product_id);

        $info = get_product_gallery_info($test_product_id);

        echo "<p><strong>Główny obraz:</strong> ";
        if ($info['featured_image']) {
            echo "ID {$info['featured_image']['id']} - {$info['featured_image']['title']}<br>";
            echo "<img src='{$info['featured_image']['url']}' style='max-width: 150px; margin: 5px;'>";
        } else {
            echo "Brak";
        }
        echo "</p>";

        echo "<p><strong>Galeria ({$info['total_images']} obrazów):</strong></p>";
        if (!empty($info['gallery_images'])) {
            echo "<div style='display: flex; flex-wrap: wrap; gap: 10px;'>";
            foreach ($info['gallery_images'] as $img) {
                echo "<div style='text-align: center;'>";
                echo "<img src='{$img['url']}' style='max-width: 100px; height: 100px; object-fit: cover;'><br>";
                echo "<small>ID: {$img['id']}</small>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p>Brak obrazów w galerii</p>";
        }

        echo "<p><strong>Meta galerii:</strong> " . ($info['gallery_meta'] ?: 'brak') . "</p>";
        echo "<p><strong>WC galeria IDs:</strong> " . (empty($info['wc_gallery_ids']) ? 'brak' : implode(',', $info['wc_gallery_ids'])) . "</p>";

        // Dodatkowe debugowanie
        echo "<h4>🔧 Debugowanie:</h4>";
        $product = wc_get_product($test_product_id);
        if ($product) {
            echo "<p><strong>Typ produktu:</strong> " . $product->get_type() . "</p>";
            echo "<p><strong>Status:</strong> " . $product->get_status() . "</p>";
            $gallery_ids = $product->get_gallery_image_ids();
            echo "<p><strong>WC get_gallery_image_ids():</strong> " . (empty($gallery_ids) ? 'brak' : implode(',', $gallery_ids)) . "</p>";
        }

        echo "</div>";

        exit; // Zatrzymaj dalsze wykonywanie
    }

    // NAPRAW GALERIĘ - dodaj ?fix_gallery=ID_PRODUKTU do URL
    if (isset($_GET['fix_gallery']) && is_numeric($_GET['fix_gallery'])) {
        $fix_product_id = (int) $_GET['fix_gallery'];
        echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
        echo "<h3>🔧 NAPRAWA GALERII dla produktu ID: {$fix_product_id}</h3>";

        // Pobierz wszystkie załączniki produktu
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_parent' => $fix_product_id,
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        ]);

        if (!empty($attachments)) {
            echo "<p>Znaleziono " . count($attachments) . " załączników:</p>";

            $featured_id = get_post_thumbnail_id($fix_product_id);
            $gallery_ids = [];

            foreach ($attachments as $attachment) {
                $is_featured = ($attachment->ID == $featured_id);
                echo "<p>- ID {$attachment->ID}: {$attachment->post_title} " . ($is_featured ? "(GŁÓWNY)" : "") . "</p>";

                if (!$is_featured) {
                    $gallery_ids[] = $attachment->ID;
                }
            }

            if (!empty($gallery_ids)) {
                // Ustaw galerię
                update_post_meta($fix_product_id, '_product_image_gallery', implode(',', $gallery_ids));

                $product = wc_get_product($fix_product_id);
                if ($product) {
                    $product->set_gallery_image_ids($gallery_ids);
                    $product->save();
                    echo "<p style='color: green;'>✅ Naprawiono galerię: " . count($gallery_ids) . " obrazów</p>";
                }
            } else {
                echo "<p>Brak obrazów do galerii (tylko główny obraz)</p>";
            }
        } else {
            echo "<p>Brak załączników dla tego produktu</p>";
        }

        echo "</div>";
        exit;
    }

    ?>
    </div>
</body>

</html>
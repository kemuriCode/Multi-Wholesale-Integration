<?php
/**
 * PROSTY TEST IMPORTU
 * Symuluje import bez WordPress żeby sprawdzić czy kod działa
 */

declare(strict_types=1);

// HTML header
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 TEST IMPORTU PRODUKTÓW</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .log {
            background: #333;
            color: #0f0;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
            height: 400px;
            overflow-y: auto;
        }

        .success {
            color: #0f0;
        }

        .error {
            color: #f00;
        }

        .info {
            color: #0ff;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 20px 0;
        }

        .stat {
            background: white;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            border: 2px solid #ddd;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <h1>🧪 TEST IMPORTU PRODUKTÓW MALFINI</h1>

    <div class="stats">
        <div class="stat">
            <div class="stat-value" id="total">0</div>
            <div>Łącznie</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="processed">0</div>
            <div>Przetworzono</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="images">0</div>
            <div>Obrazy</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="errors">0</div>
            <div>Błędy</div>
        </div>
    </div>

    <div class="log" id="log"></div>

    <script>
        function addLog(message, type = 'info') {
            const log = document.getElementById('log');
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = type;
            entry.textContent = `[${time}] ${message}`;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
        }

        function updateStat(id, value) {
            document.getElementById(id).textContent = value;
        }
    </script>

    <?php
    flush();

    addLog("🔧 Rozpoczynam test importu...");

    // Ścieżka do pliku XML
    $xml_file = dirname(__FILE__, 3) . '/uploads/wholesale/malfini/woocommerce_import_malfini.xml';

    addLog("📁 Sprawdzam plik XML: " . basename($xml_file));

    if (!file_exists($xml_file)) {
        addLog("❌ BŁĄD: Plik XML nie istnieje!", "error");
        exit;
    }

    addLog("✅ Plik XML istnieje (" . round(filesize($xml_file) / 1024 / 1024, 2) . " MB)", "success");

    // Parsowanie XML
    addLog("🔄 Parsowanie XML...");
    $xml = simplexml_load_file($xml_file);

    if (!$xml) {
        addLog("❌ BŁĄD: Nie można sparsować XML!", "error");
        exit;
    }

    $products = $xml->children();
    $total = count($products);

    addLog("✅ XML sparsowany pomyślnie", "success");
    addLog("📦 Znaleziono produktów: " . $total, "info");

    echo '<script>updateStat("total", ' . $total . ');</script>';
    flush();

    // Statystyki
    $stats = [
        'processed' => 0,
        'images' => 0,
        'errors' => 0
    ];

    // Test przetwarzania pierwszych 10 produktów
    $limit = min(10, $total);
    addLog("🚀 Testuję przetwarzanie pierwszych {$limit} produktów...", "info");

    foreach (array_slice($products, 0, $limit) as $index => $product_xml) {
        $stats['processed']++;

        // Pobierz dane produktu
        $sku = trim((string) $product_xml->sku);
        $name = trim((string) $product_xml->name);
        $price = trim((string) $product_xml->regular_price);
        $categories = trim((string) $product_xml->categories);

        addLog("🔄 Produkt #{$stats['processed']}: {$name} (SKU: {$sku})");

        // Sprawdź ceny
        if (!empty($price)) {
            $price = str_replace(',', '.', $price);
            if (is_numeric($price) && floatval($price) > 0) {
                addLog("  💰 Cena: {$price} PLN", "success");
            } else {
                addLog("  ⚠️ Nieprawidłowa cena: {$price}", "error");
                $stats['errors']++;
            }
        } else {
            addLog("  ⚠️ Brak ceny dla produktu", "error");
            $stats['errors']++;
        }

        // Sprawdź kategorie
        if (!empty($categories)) {
            $categories_decoded = html_entity_decode($categories, ENT_QUOTES, 'UTF-8');
            addLog("  📁 Kategorie: {$categories_decoded}", "success");
        }

        // Sprawdź atrybuty
        if (isset($product_xml->attributes) && $product_xml->attributes->attribute) {
            $attrs_count = count($product_xml->attributes->attribute);
            addLog("  🏷️ Atrybuty: {$attrs_count}", "success");
        }

        // Sprawdź obrazy
        if (isset($product_xml->images) && $product_xml->images->image) {
            $images = $product_xml->images->image;
            if (!is_array($images))
                $images = [$images];

            foreach ($images as $img) {
                $img_url = isset($img->src) ? trim((string) $img->src) : trim((string) $img);

                if (filter_var($img_url, FILTER_VALIDATE_URL)) {
                    addLog("  🖼️ Obraz: " . basename($img_url), "success");
                    $stats['images']++;
                } else {
                    addLog("  ⚠️ Nieprawidłowy URL obrazu: {$img_url}", "error");
                    $stats['errors']++;
                }
            }
        }

        // Aktualizuj statystyki
        echo '<script>updateStat("processed", ' . $stats['processed'] . '); updateStat("images", ' . $stats['images'] . '); updateStat("errors", ' . $stats['errors'] . ');</script>';
        flush();

        usleep(200000); // 0.2 sekundy przerwy
    }

    addLog("🎉 TEST ZAKOŃCZONY!", "success");
    addLog("📊 Wyniki: {$stats['processed']} produktów, {$stats['images']} obrazów, {$stats['errors']} błędów", "info");

    if ($stats['errors'] === 0) {
        addLog("✅ SUKCES: Dane XML są prawidłowe i gotowe do importu!", "success");
    } else {
        addLog("⚠️ UWAGA: Wykryto {$stats['errors']} błędów w danych", "error");
    }

    function addLog($message, $type = 'info')
    {
        echo '<script>addLog(' . json_encode($message) . ', "' . $type . '");</script>';
        flush();
    }
    ?>

</body>

</html>
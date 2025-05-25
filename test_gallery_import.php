<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Galerii - Import</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }

        .button:hover {
            background: #005a87;
        }

        .info {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h1>🧪 Test Galerii Produktów</h1>

    <div class="info">
        <h3>Dostępne testy:</h3>
        <p><strong>Test XML:</strong> Importuje testowy produkt z 3 obrazami</p>
        <p><strong>Pełny import:</strong> Importuje wszystkie produkty z macma</p>
        <p><strong>Test galerii:</strong> Sprawdza galerię konkretnego produktu</p>
    </div>

    <h3>🚀 Uruchom testy:</h3>

    <a href="import.php?supplier=macma&test_xml=1&admin_key=mhi_import_access" class="button">
        📦 Test XML (1 produkt)
    </a>

    <a href="import.php?supplier=macma&admin_key=mhi_import_access" class="button">
        🔄 Pełny import
    </a>

    <a href="import.php?test_gallery=33864" class="button">
        🔍 Test galerii (ID: 33864)
    </a>

    <a href="import.php?fix_gallery=33864" class="button">
        🔧 Napraw galerię (ID: 33864)
    </a>

    <h3>📋 Instrukcje:</h3>
    <ol>
        <li>Najpierw uruchom <strong>Test XML</strong> aby zaimportować testowy produkt</li>
        <li>Sprawdź logi w przeglądarce</li>
        <li>Użyj <strong>Test galerii</strong> aby sprawdzić czy galeria została utworzona</li>
        <li>Jeśli galeria nie działa, użyj <strong>Napraw galerię</strong></li>
    </ol>

    <h3>🔍 Sprawdź produkty:</h3>
    <p>Po imporcie sprawdź produkty w WooCommerce admin:</p>
    <a href="/wp-admin/edit.php?post_type=product" class="button">📋 Lista produktów</a>

</body>

</html>
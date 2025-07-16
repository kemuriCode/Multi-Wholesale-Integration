<?php
/**
 * Test Generatora XML ANDA Simple
 * 
 * URL: /wp-content/plugins/multi-wholesale-integration/test-anda-simple-generator.php
 */

// Bezpieczeństwo - sprawdź czy to WordPress
if (!defined('ABSPATH')) {
    // Ładuj WordPress jeśli uruchamiany bezpośrednio
    require_once('../../../wp-load.php');
}

// Sprawdź uprawnienia
if (!current_user_can('manage_options')) {
    wp_die('Brak uprawnień!');
}

// Załaduj generator
require_once __DIR__ . '/integrations/class-mhi-anda-simple-xml-generator.php';

$generator = new MHI_ANDA_Simple_XML_Generator();

// Obsługa akcji
$action = isset($_GET['action']) ? $_GET['action'] : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;

if ($action === 'generate') {
    $result = $generator->generate_simple_xml($limit);
}

$file_info = $generator->get_generated_file_info();

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 ANDA Simple XML Generator</title>
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

        .info-box {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
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

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .file-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .file-info h3 {
            margin-top: 0;
            color: #495057;
        }

        .file-info p {
            margin: 10px 0;
            color: #6c757d;
        }

        .file-info strong {
            color: #495057;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔥 ANDA Simple XML Generator</h1>
            <p>Generuje uproszczony XML - każdy SKU jako osobny produkt</p>
        </div>

        <?php if ($action === 'generate' && isset($result)): ?>
            <?php if ($result['success']): ?>
                <div class="success-box">
                    <h3>✅ XML wygenerowany pomyślnie!</h3>
                    <p><strong>Plik:</strong> <?php echo $result['file']; ?></p>
                    <p><strong>Produkty:</strong> <?php echo $result['products_count']; ?></p>
                    <p><strong>Rozmiar:</strong> <?php echo formatBytes($result['file_size']); ?></p>
                </div>
            <?php else: ?>
                <div class="error-box">
                    <h3>❌ Błąd generowania XML</h3>
                    <p><strong>Błąd:</strong> <?php echo $result['error']; ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="info-box">
            <h3>📋 Informacje o generatorze</h3>
            <ul>
                <li><strong>Cel:</strong> Każdy produkt z unikalnym SKU jako osobny produkt simple</li>
                <li><strong>Bez wariantów:</strong> Nie grupuje produktów w warianty</li>
                <li><strong>Struktura:</strong> Prosty XML z podstawowymi danymi</li>
                <li><strong>Kategorie:</strong> Mapowanie kategorii ANDA</li>
                <li><strong>Atrybuty:</strong> Materiał, rozmiar, kolor jako atrybuty</li>
            </ul>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $file_info['exists'] ? '✅' : '❌'; ?></div>
                <p>Plik XML</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $file_info['exists'] ? formatBytes($file_info['size']) : '0 B'; ?>
                </div>
                <p>Rozmiar</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $file_info['exists'] ? date('H:i', strtotime($file_info['date'])) : '--:--'; ?></div>
                <p>Ostatnia modyfikacja</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $file_info['exists'] ? date('d.m.Y', strtotime($file_info['date'])) : '--.--.----'; ?>
                </div>
                <p>Data utworzenia</p>
            </div>
        </div>

        <?php if ($file_info['exists']): ?>
            <div class="file-info">
                <h3>📄 Informacje o pliku</h3>
                <p><strong>Nazwa:</strong> <?php echo $file_info['file']; ?></p>
                <p><strong>Rozmiar:</strong> <?php echo formatBytes($file_info['size']); ?></p>
                <p><strong>Data utworzenia:</strong> <?php echo $file_info['date']; ?></p>
                <p><strong>Ścieżka:</strong>
                    <?php echo wp_upload_dir()['basedir']; ?>/wholesale/anda/<?php echo $file_info['file']; ?></p>
            </div>
        <?php endif; ?>

        <div class="controls">
            <a href="?action=generate&limit=0" class="btn btn-primary">🔥 Generuj pełny XML</a>
            <a href="?action=generate&limit=100" class="btn btn-warning">🧪 Test (100 produktów)</a>
            <a href="?action=generate&limit=500" class="btn btn-warning">🧪 Test (500 produktów)</a>

            <?php if ($file_info['exists']): ?>
                <a href="anda-simple-import.php" class="btn btn-success">📥 Import XML</a>
                <a href="<?php echo wp_upload_dir()['baseurl']; ?>/wholesale/anda/<?php echo $file_info['file']; ?>"
                    class="btn btn-warning" target="_blank">👁️ Podgląd XML</a>
            <?php endif; ?>

            <a href="admin.php?page=multi-hurtownie-integration&tab=anda" class="btn btn-danger">🔙 Powrót do ANDA</a>
        </div>

        <div class="info-box">
            <h3>🔧 Jak używać</h3>
            <ol>
                <li><strong>Generuj XML:</strong> Kliknij "Generuj pełny XML" aby utworzyć plik z wszystkimi produktami
                </li>
                <li><strong>Test:</strong> Użyj opcji testowych aby sprawdzić generator na mniejszej liczbie produktów
                </li>
                <li><strong>Import:</strong> Po wygenerowaniu XML przejdź do importu</li>
                <li><strong>Podgląd:</strong> Sprawdź wygenerowany XML przed importem</li>
            </ol>
        </div>

        <div class="info-box">
            <h3>📊 Struktura XML</h3>
            <p>Każdy produkt w XML zawiera:</p>
            <ul>
                <li><strong>g:id</strong> - SKU produktu</li>
                <li><strong>g:title</strong> - Nazwa produktu</li>
                <li><strong>g:description</strong> - Opis produktu</li>
                <li><strong>g:price</strong> - Cena w PLN</li>
                <li><strong>g:stock_quantity</strong> - Ilość w magazynie</li>
                <li><strong>g:product_type</strong> - Kategoria produktu</li>
                <li><strong>g:material</strong> - Materiał (atrybut)</li>
                <li><strong>g:size</strong> - Rozmiar (atrybut)</li>
                <li><strong>g:color</strong> - Kolor (atrybut)</li>
                <li><strong>g:supplier</strong> - Hurtownia (ANDA)</li>
            </ul>
        </div>
    </div>
</body>

</html>

<?php
/**
 * Formatuje bajty na czytelną formę
 */
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
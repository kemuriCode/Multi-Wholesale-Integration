<?php
/**
 * ANDA Category Fixer - Interfejs Webowy
 * Naprawia kategorie produktów ANDA na podstawie SKU rodziców
 * 
 * URL: /wp-content/plugins/multi-wholesale-integration/anda-category-fixer.php
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

// Załaduj klasę naprawy kategorii
require_once __DIR__ . '/integrations/class-mhi-anda-category-fixer.php';

$fixer = new MHI_ANDA_Category_Fixer();
$stats = $fixer->get_anda_stats();

// Pobierz parametry
$batch_size = isset($_GET['batch_size']) ? (int) $_GET['batch_size'] : 50;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$force_update = isset($_GET['force_update']) && $_GET['force_update'] === '1';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Obsługa akcji
if ($action === 'fix_categories') {
    $results = $fixer->fix_categories($batch_size, $offset, $force_update);
} elseif ($action === 'clean_categories') {
    $results = $fixer->clean_long_categories($batch_size, $offset);
} elseif ($action === 'check_categories') {
    $results = $fixer->check_long_categories($batch_size, $offset);
}

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 ANDA Category Fixer</title>
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
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
            border-left: 4px solid #ff6b6b;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #ff6b6b;
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
            background: #ff6b6b;
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

        .search-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .search-results {
            margin-top: 15px;
        }

        .product-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 5px 0;
        }

        .product-sku {
            font-weight: bold;
            color: #495057;
        }

        .product-categories {
            color: #6c757d;
            font-size: 12px;
        }

        .form-group {
            margin: 10px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔧 ANDA Category Fixer</h1>
            <p>Naprawia kategorie produktów ANDA na podstawie SKU rodziców</p>
            <div style="margin-top: 15px; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px;">
                <h4 style="margin: 0 0 10px 0;">🧹 Nowa funkcjonalność - Czyszczenie długich kategorii</h4>
                <p style="margin: 0; font-size: 14px;">
                    Usuwa kategorie zawierające znak '>' (długie łańcuchy) jak "Torby i podróże > Akcesoria podróżne > Rozgałęźnik uniwersalny"
                </p>
            </div>
        </div>

        <?php if ($action === 'fix_categories' && isset($results)): ?>
            <div class="success-box">
                <h3>✅ Naprawa kategorii zakończona!</h3>
                <p><strong>Przetworzone:</strong> <?php echo $results['processed']; ?></p>
                <p><strong>Naprawione:</strong> <?php echo $results['fixed']; ?></p>
                <p><strong>Pominięte:</strong> <?php echo $results['skipped']; ?></p>
                <p><strong>Błędy:</strong> <?php echo $results['errors']; ?></p>
            </div>

            <div class="log-container">
                <?php foreach ($results['logs'] as $log): ?>
                    <div class="log-entry log-info"><?php echo esc_html($log); ?></div>
                <?php endforeach; ?>
            </div>

            <?php
            // Auto-continue logic
            $auto_continue = isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1';
            $total_products = $stats['total_products'];
            $next_offset = $offset + $batch_size;

            if ($auto_continue && $next_offset < $total_products): ?>
                <div class="warning-box">
                    <h3>🔄 Auto Continue - Następny batch</h3>
                    <p>Przetworzono: <?php echo $offset + $batch_size; ?> z <?php echo $total_products; ?> produktów</p>
                    <p>Następny batch za 3 sekundy...</p>
                </div>

                <div class="controls">
                    <a href="?action=fix_categories&batch_size=<?php echo $batch_size; ?>&offset=<?php echo $next_offset; ?>&force_update=<?php echo $force_update ? '1' : '0'; ?>&auto_continue=1"
                        class="btn btn-success">⏭️ Kontynuuj teraz</a>
                    <a href="anda-category-fixer.php" class="btn btn-danger">⏹️ Zatrzymaj Auto Continue</a>
                </div>

                <script>
                    setTimeout(function () {
                        window.location.href = '?action=fix_categories&batch_size=<?php echo $batch_size; ?>&offset=<?php echo $next_offset; ?>&force_update=<?php echo $force_update ? '1' : '0'; ?>&auto_continue=1';
                    }, 3000);
                </script>
            <?php elseif ($auto_continue && $next_offset >= $total_products): ?>
                <div class="success-box">
                    <h3>🎉 Auto Continue zakończone!</h3>
                    <p>Wszystkie <?php echo $total_products; ?> produktów zostały przetworzone.</p>
                </div>
            <?php endif; ?>
        <?php elseif ($action === 'clean_categories' && isset($results)): ?>
            <div class="success-box">
                <h3>✅ Czyszczenie kategorii zakończone!</h3>
                <p><strong>Przetworzone:</strong> <?php echo $results['processed']; ?></p>
                <p><strong>Wyczyszczone:</strong> <?php echo $results['cleaned']; ?></p>
                <p><strong>Pominięte:</strong> <?php echo $results['skipped']; ?></p>
                <p><strong>Błędy:</strong> <?php echo $results['errors']; ?></p>
            </div>

            <div class="log-container">
                <?php foreach ($results['logs'] as $log): ?>
                    <div class="log-entry log-info"><?php echo esc_html($log); ?></div>
                <?php endforeach; ?>
            </div>

            <?php
            // Auto-continue logic for cleaning
            $auto_continue = isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1';
            $total_products = $stats['total_products'];
            $next_offset = $offset + $batch_size;

            if ($auto_continue && $next_offset < $total_products): ?>
                <div class="warning-box">
                    <h3>🔄 Auto Continue - Czyszczenie kategorii</h3>
                    <p>Przetworzono: <?php echo $offset + $batch_size; ?> z <?php echo $total_products; ?> produktów</p>
                    <p>Następny batch za 3 sekundy...</p>
                </div>

                <div class="controls">
                    <a href="?action=clean_categories&batch_size=<?php echo $batch_size; ?>&offset=<?php echo $next_offset; ?>&auto_continue=1"
                        class="btn btn-success">⏭️ Kontynuuj teraz</a>
                    <a href="anda-category-fixer.php" class="btn btn-danger">⏹️ Zatrzymaj Auto Continue</a>
                </div>

                <script>
                    setTimeout(function () {
                        window.location.href = '?action=clean_categories&batch_size=<?php echo $batch_size; ?>&offset=<?php echo $next_offset; ?>&auto_continue=1';
                    }, 3000);
                </script>
            <?php elseif ($auto_continue && $next_offset >= $total_products): ?>
                <div class="success-box">
                    <h3>🎉 Auto Continue - Czyszczenie zakończone!</h3>
                    <p>Wszystkie <?php echo $total_products; ?> produktów zostały przetworzone.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="info-box">
            <h3>📋 Jak to działa</h3>
            <ul>
                <li><strong>Wyszukiwanie rodziców:</strong> Analizuje SKU produktu i wyciąga SKU rodzica</li>
                <li><strong>Patterny SKU:</strong> AP4135-01 → AP4135, AP4135_S → AP4135, itp.</li>
                <li><strong>Przypisywanie kategorii:</strong> Kopiuje kategorie z produktu-rodzica</li>
                <li><strong>Bezpieczeństwo:</strong> Nie nadpisuje istniejących kategorii (chyba że force update)</li>
            </ul>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                <p>Wszystkie produkty ANDA</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['products_with_categories']; ?></div>
                <p>Z kategoriami</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['products_without_categories']; ?></div>
                <p>Bez kategorii</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo round(($stats['products_without_categories'] / max($stats['total_products'], 1)) * 100, 1); ?>%
                </div>
                <p>Do naprawy</p>
            </div>
        </div>

        <?php if ($action === 'fix_categories' && isset($results)): ?>
            <div class="stats">
                <div class="stat-card" style="border-left-color: #28a745;">
                    <div class="stat-number"><?php echo $offset + $batch_size; ?> / <?php echo $stats['total_products']; ?>
                    </div>
                    <p>Przetworzone</p>
                </div>
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number">
                        <?php echo round((($offset + $batch_size) / max($stats['total_products'], 1)) * 100, 1); ?>%
                    </div>
                    <p>Postęp</p>
                </div>
                <div class="stat-card" style="border-left-color: #4CAF50;">
                    <div class="stat-number"><?php echo $results['fixed']; ?></div>
                    <p>Naprawione</p>
                </div>
                <div class="stat-card" style="border-left-color: #FF9800;">
                    <div class="stat-number"><?php echo $results['skipped']; ?></div>
                    <p>Pominięte</p>
                </div>
            </div>
        <?php elseif ($action === 'clean_categories' && isset($results)): ?>
            <div class="stats">
                <div class="stat-card" style="border-left-color: #28a745;">
                    <div class="stat-number"><?php echo $offset + $batch_size; ?> / <?php echo $stats['total_products']; ?>
                    </div>
                    <p>Przetworzone</p>
                </div>
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number">
                        <?php echo round((($offset + $batch_size) / max($stats['total_products'], 1)) * 100, 1); ?>%
                    </div>
                    <p>Postęp</p>
                </div>
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="stat-number"><?php echo $results['cleaned']; ?></div>
                    <p>Wyczyszczone</p>
                </div>
                <div class="stat-card" style="border-left-color: #FF9800;">
                    <div class="stat-number"><?php echo $results['skipped']; ?></div>
                    <p>Pominięte</p>
                </div>
            </div>
        <?php elseif ($action === 'check_categories' && isset($results)): ?>
            <div class="stats">
                <div class="stat-card" style="border-left-color: #28a745;">
                    <div class="stat-number"><?php echo $offset + $batch_size; ?> / <?php echo $stats['total_products']; ?>
                    </div>
                    <p>Przetworzone</p>
                </div>
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number">
                        <?php echo round((($offset + $batch_size) / max($stats['total_products'], 1)) * 100, 1); ?>%
                    </div>
                    <p>Postęp</p>
                </div>
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="stat-number"><?php echo $results['found']; ?></div>
                    <p>Znalezione</p>
                </div>
                <div class="stat-card" style="border-left-color: #FF9800;">
                    <div class="stat-number"><?php echo $results['skipped']; ?></div>
                    <p>Pominięte</p>
                </div>
            </div>
        <?php elseif ($action === 'check_categories' && isset($results)): ?>
            <div class="success-box">
                <h3>✅ Sprawdzanie kategorii zakończone!</h3>
                <p><strong>Przetworzone:</strong> <?php echo $results['processed']; ?></p>
                <p><strong>Znalezione:</strong> <?php echo $results['found']; ?></p>
                <p><strong>Pominięte:</strong> <?php echo $results['skipped']; ?></p>
                <p><strong>Błędy:</strong> <?php echo $results['errors']; ?></p>
            </div>

            <?php if (!empty($results['products_with_long_categories'])): ?>
                <div class="warning-box">
                    <h3>⚠️ Produkty z długimi kategoriami:</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($results['products_with_long_categories'] as $product): ?>
                            <div class="product-item" style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <div class="product-sku"><strong>SKU:</strong> <?php echo esc_html($product['sku']); ?></div>
                                <div><strong>Nazwa:</strong> <?php echo esc_html($product['name']); ?></div>
                                <div style="color: #dc3545;"><strong>Długie kategorie:</strong> <?php echo esc_html(implode(', ', $product['long_categories'])); ?></div>
                                <div style="color: #28a745;"><strong>Normalne kategorie:</strong> <?php echo esc_html(implode(', ', $product['normal_categories'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="log-container">
                <?php foreach ($results['logs'] as $log): ?>
                    <div class="log-entry log-info"><?php echo esc_html($log); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Sekcja naprawy kategorii -->
        <div class="search-section">
            <h3>🔧 Naprawa kategorii</h3>
            <p>Naprawia kategorie produktów ANDA na podstawie SKU rodziców.</p>

            <form method="get" action="">
                <input type="hidden" name="action" value="fix_categories">

                <div class="form-group">
                    <label for="batch_size">Batch Size:</label>
                    <select name="batch_size" id="batch_size">
                        <option value="10" <?php selected($batch_size, 10); ?>>10 produktów</option>
                        <option value="25" <?php selected($batch_size, 25); ?>>25 produktów</option>
                        <option value="50" <?php selected($batch_size, 50); ?>>50 produktów</option>
                        <option value="100" <?php selected($batch_size, 100); ?>>100 produktów</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="force_update" value="1" <?php checked($force_update); ?>>
                        Force Update (nadpisz istniejące kategorie)
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="auto_continue" value="1" <?php checked(isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1'); ?>>
                        Auto Continue (automatyczne przechodzenie przez wszystkie produkty)
                    </label>
                </div>

                <div class="controls">
                    <button type="submit" class="btn btn-primary">🔧 Napraw kategorie</button>
                    <a href="?action=fix_categories&batch_size=<?php echo $batch_size; ?>&force_update=1"
                        class="btn btn-warning">⚠️ Force Update</a>
                    <a href="?action=fix_categories&batch_size=<?php echo $batch_size; ?>&force_update=1&auto_continue=1"
                        class="btn btn-success">🚀 Auto Continue + Force Update</a>
                </div>
            </form>
        </div>

        <!-- Sekcja czyszczenia kategorii -->
        <div class="search-section">
            <h3>🧹 Czyszczenie długich kategorii</h3>
            <p>Usuwa kategorie zawierające znak '>' (długie łańcuchy kategorii).</p>

            <div class="info-box">
                <h4>📋 Przykłady kategorii do usunięcia:</h4>
                <ul>
                    <li><code>"Torby i podróże > Akcesoria podróżne > Rozgałęźnik uniwersalny"</code> → zostanie
                        usunięta</li>
                    <li><code>"Torby i podróże > Akcesoria podróżne"</code> → zostanie usunięta</li>
                    <li><code>"Torby i podróże"</code> → zostanie zachowana</li>
                    <li><code>"Torby termiczne"</code> → zostanie zachowana</li>
                </ul>
            </div>

            <form method="get" action="">
                <input type="hidden" name="action" value="clean_categories">

                <div class="form-group">
                    <label for="clean_batch_size">Batch Size:</label>
                    <select name="batch_size" id="clean_batch_size">
                        <option value="10" <?php selected($batch_size, 10); ?>>10 produktów</option>
                        <option value="25" <?php selected($batch_size, 25); ?>>25 produktów</option>
                        <option value="50" <?php selected($batch_size, 50); ?>>50 produktów</option>
                        <option value="100" <?php selected($batch_size, 100); ?>>100 produktów</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="auto_continue" value="1" <?php checked(isset($_GET['auto_continue']) && $_GET['auto_continue'] === '1'); ?>>
                        Auto Continue (automatyczne przechodzenie przez wszystkie produkty)
                    </label>
                </div>

                                            <div class="controls">
                    <button type="submit" class="btn btn-danger">🧹 Wyczyść długie kategorie</button>
                    <a href="?action=clean_categories&batch_size=<?php echo $batch_size; ?>&auto_continue=1"
                        class="btn btn-warning">🚀 Auto Continue - Czyszczenie</a>
                    <a href="?action=check_categories&batch_size=<?php echo $batch_size; ?>"
                        class="btn btn-info">🔍 Sprawdź przed czyszczeniem</a>
                </div>
        </form>

        <!-- Sekcja sprawdzania kategorii -->
        <div class="search-section">
            <h3>🔍 Sprawdzanie długich kategorii (podgląd)</h3>
            <p>Sprawdza produkty z długimi kategoriami bez ich usuwania.</p>

            <form method="get" action="">
                <input type="hidden" name="action" value="check_categories">

                <div class="form-group">
                    <label for="check_batch_size">Batch Size:</label>
                    <select name="batch_size" id="check_batch_size">
                        <option value="10" <?php selected($batch_size, 10); ?>>10 produktów</option>
                        <option value="25" <?php selected($batch_size, 25); ?>>25 produktów</option>
                        <option value="50" <?php selected($batch_size, 50); ?>>50 produktów</option>
                        <option value="100" <?php selected($batch_size, 100); ?>>100 produktów</option>
                    </select>
                </div>

                <div class="controls">
                    <button type="submit" class="btn btn-info">🔍 Sprawdź długie kategorie</button>
                </div>
            </form>
        </div>
    </div>

        <!-- Sekcja wyszukiwania rodziców -->
        <div class="search-section">
            <h3>🔍 Wyszukiwanie produktów-rodziców</h3>
            <p>Wyszukuje produkty w bazie po SKU i pokazuje ich kategorie.</p>

            <div class="form-group">
                <label for="search_term">Szukaj SKU:</label>
                <input type="text" id="search_term" placeholder="np. AP4135" style="width: 200px;">
                <button onclick="searchParents()" class="btn btn-success">🔍 Szukaj</button>
            </div>

            <div id="search_results" class="search-results"></div>
        </div>

        <!-- Sekcja patternów SKU -->
        <div class="info-box">
            <h3>📋 Obsługiwane patterny SKU</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Pattern</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Przykład</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Rodzic</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><code>BASE-XX</code></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135-01</td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><code>BASE_SIZE</code></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135_S</td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><code>BASE-XX_SIZE</code></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135-01_S</td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><code>BASE_XX_SIZE</code></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135_01_S</td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><code>BASE-XX-YY</code></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135-01-02</td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><code>BASE_XX_YY</code></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135_01_02</td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">AP4135</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="controls">
            <a href="admin.php?page=multi-hurtownie-integration&tab=anda" class="btn btn-danger">🔙 Powrót do ANDA</a>
        </div>
    </div>

    <script>
        function searchParents() {
            const searchTerm = document.getElementById('search_term').value;
            const resultsDiv = document.getElementById('search_results');

            if (!searchTerm) {
                alert('Wprowadź SKU do wyszukania');
                return;
            }

            resultsDiv.innerHTML = '<p>🔍 Wyszukiwanie...</p>';

            // AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                displaySearchResults(response.data);
                            } else {
                                resultsDiv.innerHTML = '<p class="log-error">❌ Błąd: ' + response.data + '</p>';
                            }
                        } catch (e) {
                            resultsDiv.innerHTML = '<p class="log-error">❌ Błąd parsowania odpowiedzi</p>';
                        }
                    } else {
                        resultsDiv.innerHTML = '<p class="log-error">❌ Błąd połączenia</p>';
                    }
                }
            };

            const data = 'action=mhi_search_anda_parents&nonce=<?php echo wp_create_nonce('mhi_search_anda_parents'); ?>&search_term=' + encodeURIComponent(searchTerm) + '&limit=20';
            xhr.send(data);
        }

        function displaySearchResults(products) {
            const resultsDiv = document.getElementById('search_results');

            if (products.length === 0) {
                resultsDiv.innerHTML = '<p>❌ Nie znaleziono produktów</p>';
                return;
            }

            let html = '<h4>🔍 Znalezione produkty:</h4>';

            products.forEach(product => {
                html += '<div class="product-item">';
                html += '<div class="product-sku">' + product.sku + '</div>';
                html += '<div>' + product.name + '</div>';
                html += '<div class="product-categories">';
                if (product.categories.length > 0) {
                    html += 'Kategorie: ' + product.categories.join(', ');
                } else {
                    html += '<span style="color: #dc3545;">Brak kategorii</span>';
                }
                html += '</div>';
                html += '</div>';
            });

            resultsDiv.innerHTML = html;
        }
    </script>
</body>

</html>
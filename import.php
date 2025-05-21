<?php
/**
 * Skrypt do importu produktów z XML do WooCommerce
 * 
 * Sposób użycia: 
 * 1. Wejdź na stronę: /wp-content/plugins/multi-wholesale-integration/import.php?supplier=NAZWA_HURTOWNI
 * 2. Skrypt automatycznie rozpocznie import produktów z pliku XML
 */

// Zwiększ limity wykonania
ini_set('memory_limit', '2048M');
set_time_limit(0); // Bez limitu czasu
ignore_user_abort(true); // Kontynuuj nawet po zamknięciu przeglądarki

// Wyświetlaj wszystkie błędy
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Załaduj WordPress
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Obsługa zatrzymywania importu
$upload_dir = wp_upload_dir();
$supplier = isset($_GET['supplier']) ? sanitize_text_field($_GET['supplier']) : '';
$resume = isset($_GET['resume']) && $_GET['resume'] === 'true';
$stop_file = trailingslashit($upload_dir['basedir']) . 'wholesale/stop_import_' . $supplier . '.flag';

// Sprawdź czy jest już zapisany status importu dla tego dostawcy
$import_status = get_option('mhi_import_status_' . $supplier, []);
$can_resume = false;
$last_processed = 0;

// Sprawdź czy można wznowić import
if (
    !empty($import_status) && isset($import_status['status']) &&
    ($import_status['status'] === 'stopped' || $import_status['status'] === 'error') &&
    $import_status['processed'] > 0 && $import_status['total'] > $import_status['processed']
) {
    $can_resume = true;
    $last_processed = $import_status['processed'];
}

// Sprawdź, czy użytkownik chce zatrzymać import
if (isset($_POST['stop_import'])) {
    file_put_contents($stop_file, date('Y-m-d H:i:s'));
    echo "Import zostanie zatrzymany w ciągu kilku sekund.";
    exit;
}

// Usuwamy flagę zatrzymania na początku importu
if (!empty($stop_file) && file_exists($stop_file)) {
    unlink($stop_file);
}

// Funkcja do logowania
function log_message($message)
{
    echo $message . "<br>";
    flush();
    ob_flush();
}

// Sprawdź czy użytkownik jest zalogowany jako admin lub ma klucz dostępu
if (!current_user_can('manage_options') && (!isset($_GET['admin_key']) || $_GET['admin_key'] !== 'mhi_import_access')) {
    log_message('Brak uprawnień administratora!');
    exit;
}

// Sprawdź czy podano nazwę hurtowni
if (!isset($_GET['supplier'])) {
    log_message('Brak parametru: supplier. Użyj: import.php?supplier=NAZWA_HURTOWNI');
    exit;
}

$supplier = sanitize_text_field($_GET['supplier']);
log_message("Rozpoczynam import produktów z hurtowni: $supplier");

// Ścieżka do pliku XML
$xml_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/woocommerce_import_' . $supplier . '.xml';

// Funkcje pomocnicze
function should_stop_import($stop_file)
{
    // Upewnij się, że ścieżka do pliku flagi jest poprawna
    if (empty($stop_file)) {
        return false;
    }

    // Sprawdź czy plik flagi istnieje
    $should_stop = file_exists($stop_file);

    // Dodaj log jeśli wykryto flagę zatrzymania
    if ($should_stop) {
        add_to_log('Wykryto flagę zatrzymania importu: ' . $stop_file);
    }

    return $should_stop;
}

// Sprawdź czy plik istnieje
if (!file_exists($xml_file)) {
    log_message("Plik XML nie istnieje: $xml_file");
    exit;
}

log_message("Znaleziono plik XML: $xml_file");

// Załaduj plik XML
libxml_use_internal_errors(true);
$xml = simplexml_load_file($xml_file);

if (!$xml) {
    $error_message = "Błędy parsowania XML:<br>";
    foreach (libxml_get_errors() as $error) {
        $error_message .= "Linia " . $error->line . ": " . $error->message . "<br>";
    }
    libxml_clear_errors();
    log_message($error_message);
    exit;
}

// Pobierz produkty
$products = $xml->children();
$total_products = count($products);
log_message("Znaleziono $total_products produktów do importu");

// Liczniki
$created = 0;
$updated = 0;
$skipped = 0;
$failed = 0;
$images_added = 0;
$attributes_added = 0;
$processed = 0;
$categories_added = 0;
$optimized_images = 0;

// Czas rozpoczęcia
$start_time = microtime(true);

// Wyłącz niektóre hooki i zachowaj ustawienia dla wydajności
wp_defer_term_counting(true);
wp_defer_comment_counting(true);
wp_suspend_cache_invalidation(true);

// Wyłącz automatyczne zapisywanie wersji dla produktów
add_filter('wp_revisions_to_keep', function ($num, $post) {
    if ($post->post_type === 'product') {
        return 0;
    }
    return $num;
}, 10, 2);

// Włącz progress bar i popraw interfejs
echo '<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import produktów - ' . $supplier . '</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
    <style>
        body {
            font-family: "Roboto", -apple-system, BlinkMacSystemFont, "Segoe UI", Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            background: #f7f7f7;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0073aa;
            margin-top: 0;
        }
        .progress {
            height: 25px;
            background-color: #e9ecef;
            border-radius: 3px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            margin: 20px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background-color: #0073aa;
            border-radius: 3px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
        }
        .stats {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        .stat-box {
            flex: 1 0 200px;
            margin: 10px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 3px;
            text-align: center;
        }
        .stat-box h3 {
            margin-top: 0;
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }
        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            color: #0073aa;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
        }
        .alert-info {
            background-color: #e5f5fa;
            border-left: 4px solid #00a0d2;
            color: #00a0d2;
        }
        #log-container {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 3px;
            height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            color: #555;
        }
        #log-container p {
            margin: 0 0 5px;
            line-height: 1.4;
            word-break: break-all;
        }
        #stop-import {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 20px;
        }
        #stop-import:hover {
            background-color: #c82333;
        }
        #stop-import:disabled {
            background-color: #f5f5f5;
            color: #aaa;
            cursor: not-allowed;
        }
        #resume-import {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 20px;
            margin-left: 10px;
        }
        #resume-import:hover {
            background-color: #218838;
        }
        .time-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Multi-Hurtownie Integration</h1>
        <h2>Import produktów z hurtowni: ' . $supplier . '</h2>';

if ($can_resume && !$resume) {
    // Pokaż informację o możliwości wznowienia importu
    echo '<div class="alert alert-info">
        <p>Wykryto przerwany import. Możesz wznowić import od produktu ' . ($last_processed + 1) . ' lub rozpocząć nowy import.</p>
        <div style="margin-top: 15px;">
            <a href="' . admin_url('admin.php?page=mhi-product-import&supplier=' . $supplier . '&resume=true') . '" class="button" style="background-color: #28a745; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;">Wznów import</a>
            <a href="' . admin_url('admin.php?page=mhi-product-import&supplier=' . $supplier) . '" class="button" style="padding: 8px 15px; text-decoration: none; border-radius: 3px;">Rozpocznij nowy import</a>
        </div>
    </div>';
    echo '</div></body></html>';
    exit;
}

echo '<div class="alert alert-info">
    <p>Trwa import produktów z pliku XML. Proszę nie zamykać tej strony, dopóki import nie zostanie zakończony.</p>
</div>

<div class="progress">
    <div class="progress-bar" id="progress-bar" style="width: 0%;">0%</div>
</div>

<div class="time-info">
    <span>Czas trwania: <span id="elapsed-time">00:00:00</span></span>
    <span>Szacowany czas pozostały: <span id="estimated-time">00:00:00</span></span>
</div>

<div class="stats">
    <div class="stat-box">
        <h3>Przetworzono</h3>
        <div class="value" id="processed-count">0</div>
    </div>
    <div class="stat-box">
        <h3>Utworzono</h3>
        <div class="value" id="created-count">0</div>
    </div>
    <div class="stat-box">
        <h3>Zaktualizowano</h3>
        <div class="value" id="updated-count">0</div>
    </div>
    <div class="stat-box">
        <h3>Błędy</h3>
        <div class="value" id="failed-count">0</div>
    </div>
    <div class="stat-box">
        <h3>Dodane zdjęcia</h3>
        <div class="value" id="images-count">0</div>
    </div>
    <div class="stat-box">
        <h3>Dodane kategorie</h3>
        <div class="value" id="categories-count">0</div>
    </div>
</div>

<button id="stop-import">Zatrzymaj import</button>

<div id="latest-product-container">
    <h3>Aktualnie przetwarzany produkt:</h3>
    <div id="latest-product"></div>
</div>

<div id="log-container">
    <p>Rozpoczynam import produktów...</p>
</div>

<script>
    // Skrypt JavaScript do obsługi zatrzymania importu
    document.getElementById("stop-import").addEventListener("click", function() {
        if (confirm("Czy na pewno chcesz zatrzymać import? Możesz go później wznowić.")) {
            this.disabled = true;
            this.innerText = "Zatrzymywanie...";
            
            // Wysyłamy zapytanie AJAX, aby ustawić flagę zatrzymania
            var xhr = new XMLHttpRequest();
            xhr.open("POST", window.location.href, true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // Dodaj komunikat do logów
                    var logContainer = document.getElementById("log-container");
                    var newLog = document.createElement("p");
                    newLog.textContent = "Żądanie zatrzymania zostało wysłane. Import zatrzyma się po zakończeniu bieżącego produktu.";
                    logContainer.appendChild(newLog);
                    logContainer.scrollTop = logContainer.scrollHeight;
                }
            };
            xhr.send("stop_import=1");
        }
    });
</script>
</div>
</body>
</html>';

// Rozpocznij transakcję SQL, jeśli to możliwe
global $wpdb;
$wpdb->query('START TRANSACTION');

/**
 * Przetwarza kategorie produktu, obsługując hierarchię
 * 
 * @param string $category_string String z kategoriami
 * @return array Tablica ID kategorii
 */
function process_categories($category_string)
{
    add_to_log("DEBUG - Przetwarzanie kategorii: " . $category_string);
    $category_ids = array(); // Inicjalizacja zmiennej jako pusta tablica

    // Jeśli kategoria jest pusta, zwróć pustą tablicę
    if (empty($category_string)) {
        add_to_log("DEBUG - Kategoria jest pusta");
        return $category_ids;
    }

    // Sprawdź, czy kategoria ma hierarchię (zawiera >)
    if (strpos($category_string, '>') !== false) {
        add_to_log("DEBUG - Wykryto hierarchię kategorii");
        // Podziel string na poszczególne kategorie w hierarchii
        $category_parts = array_map('trim', explode('>', $category_string));

        // Dodaj logowanie części
        add_to_log("DEBUG - Części kategorii: " . implode(', ', $category_parts));

        $parent_id = 0;
        $current_path = '';

        // Przetwórz każdy poziom hierarchii
        foreach ($category_parts as $part) {
            if (empty($part))
                continue;

            if (!empty($current_path)) {
                $current_path .= ' > ';
            }
            $current_path .= $part;

            // Dodaj kategorię na odpowiednim poziomie
            $term_id = get_or_create_category($part, $parent_id);
            if ($term_id) {
                $parent_id = $term_id;
                add_to_log("DEBUG - Dodano kategorię w hierarchii: " . $part . " (ID: " . $term_id . ", Parent: " . $parent_id . ")");
            } else {
                add_to_log("BŁĄD - Nie można utworzyć kategorii: " . $part);
                break;
            }
        }

        // Dodaj tylko najniższy poziom hierarchii do ID kategorii
        if ($parent_id > 0) {
            $category_ids[] = $parent_id;
            add_to_log("DEBUG - Dodano ID kategorii: " . $parent_id . " (pełna ścieżka: " . $current_path . ")");
        }
    } else {
        // Pojedyncza kategoria (bez hierarchii)
        $category_id = get_or_create_category($category_string);
        if ($category_id) {
            $category_ids[] = $category_id;
            add_to_log("DEBUG - Dodano pojedynczą kategorię: " . $category_string . " (ID: " . $category_id . ")");
        } else {
            add_to_log("BŁĄD - Nie można utworzyć pojedynczej kategorii: " . $category_string);
        }
    }

    return $category_ids;
}

// Funkcja do pobrania lub utworzenia kategorii z obsługą hierarchii
function get_or_create_category($category_name, $parent_id = 0)
{
    add_to_log("DEBUG get_or_create_category - Rozpoczęto dla: '" . $category_name . "', rodzic: " . $parent_id);

    // Przygotuj nazwę kategorii (trim i sanityzacja)
    $category_name = trim($category_name);

    // Najpierw szukamy dokładnie po nazwie i rodzicu
    $matching_terms = get_terms(array(
        'taxonomy' => 'product_cat',
        'name' => $category_name,
        'hide_empty' => false,
        'fields' => 'all',
        'parent' => $parent_id
    ));

    if (!empty($matching_terms) && !is_wp_error($matching_terms)) {
        $term = reset($matching_terms); // Pierwszy element
        add_to_log("DEBUG get_or_create_category - Znaleziono dokładne dopasowanie: " . $term->term_id);
        return $term->term_id;
    }

    // Alternatywne sprawdzenie po zignorowanej wielkości liter
    $matching_terms = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'fields' => 'all',
        'parent' => $parent_id
    ));

    if (!empty($matching_terms) && !is_wp_error($matching_terms)) {
        foreach ($matching_terms as $term) {
            if (strtolower($term->name) === strtolower($category_name)) {
                add_to_log("DEBUG get_or_create_category - Znaleziono dopasowanie ignorując wielkość liter: " . $term->term_id);
                return $term->term_id;
            }
        }
    }

    // Użyj term_exists jako ostatnia opcja
    $term = term_exists($category_name, 'product_cat', $parent_id);

    // Dodaj dodatkowe logowanie dla debugowania
    if ($term) {
        add_to_log("DEBUG get_or_create_category - Kategoria istnieje przez term_exists: " . print_r($term, true));
    } else {
        add_to_log("DEBUG get_or_create_category - Kategoria nie istnieje, tworzenie nowej");

        // Sprawdź czy nie istnieje już slug dla tej kategorii
        $slug = sanitize_title($category_name);
        $term_by_slug = get_term_by('slug', $slug, 'product_cat');

        if ($term_by_slug && $term_by_slug->parent == $parent_id) {
            add_to_log("DEBUG get_or_create_category - Znaleziono kategorię po slugu: " . $term_by_slug->term_id);
            return $term_by_slug->term_id;
        }

        // Jeśli nie istnieje, utwórz ją
        $term = wp_insert_term($category_name, 'product_cat', array('parent' => $parent_id));

        if (is_wp_error($term)) {
            add_to_log("BŁĄD get_or_create_category - Nie można utworzyć kategorii: " . $term->get_error_message());
            return false;
        } else {
            add_to_log("DEBUG get_or_create_category - Utworzono nową kategorię: " . print_r($term, true));
        }
    }

    // Zwróć ID kategorii lub false w przypadku błędu
    if (!is_wp_error($term) && isset($term['term_id'])) {
        return $term['term_id'];
    }
    return false;
}

/**
 * Pobiera lub tworzy atrybut WooCommerce
 * 
 * @param string $name Nazwa atrybutu
 * @return int|bool ID terminu atrybutu lub false w przypadku błędu
 */
function get_or_create_attribute($name)
{
    // Upewnij się, że nazwa atrybutu nie jest pusta
    if (empty($name)) {
        add_to_log("BŁĄD - Próba utworzenia atrybutu z pustą nazwą");
        return false;
    }

    // Przygotuj slug atrybutu
    $attribute_name = wc_sanitize_taxonomy_name(stripslashes($name));
    $attribute_slug = 'pa_' . $attribute_name;

    // Sprawdź czy atrybut już istnieje
    $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

    // Jeżeli atrybut nie istnieje, utwórz go
    if (!$attribute_id) {
        add_to_log("DEBUG - Tworzenie nowego atrybutu: " . $name);
        $attribute_id = wc_create_attribute(array(
            'name' => $name,
            'slug' => $attribute_name,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false
        ));

        if (is_wp_error($attribute_id)) {
            add_to_log("BŁĄD - Nie można utworzyć atrybutu: " . $attribute_id->get_error_message());
            return false;
        }

        add_to_log("INFO - Utworzono nowy atrybut: " . $name . " (ID: " . $attribute_id . ")");

        // Odświeżenie cache po utworzeniu nowego atrybutu
        $transient_name = 'wc_attribute_taxonomies';
        $attribute_taxonomies = get_transient($transient_name);
        if ($attribute_taxonomies) {
            delete_transient($transient_name);
        }
    } else {
        add_to_log("DEBUG - Znaleziono istniejący atrybut: " . $name . " (ID: " . $attribute_id . ")");
    }

    // Upewnij się, że taksonomia atrybutu jest zarejestrowana
    if (!taxonomy_exists($attribute_slug)) {
        $i = 0;
        // Czekaj na rejestrację taksonomii (maksymalnie 3 sekundy)
        while (!taxonomy_exists($attribute_slug) && $i < 30) {
            add_to_log("DEBUG - Czekam na rejestrację taksonomii atrybutu: " . $attribute_slug);
            usleep(100000); // 0.1 sekundy
            $i++;

            // Wymuszenie rejestracji taksonomii
            if ($i === 10) {
                do_action('woocommerce_attribute_added', $attribute_id, array(
                    'name' => $name,
                    'slug' => $attribute_name,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false
                ));
            }
        }

        // Jeśli taksonomia nie została zarejestrowana, spróbuj ręcznie
        if (!taxonomy_exists($attribute_slug)) {
            add_to_log("OSTRZEŻENIE - Ręczna rejestracja taksonomii atrybutu: " . $attribute_slug);
            register_taxonomy(
                $attribute_slug,
                array('product'),
                array(
                    'hierarchical' => false,
                    'show_ui' => false,
                    'query_var' => true,
                    'rewrite' => false,
                )
            );
        }
    }

    return $attribute_id;
}

// Funkcja do pobierania załącznika po URL
function get_attachment_id_by_url($url)
{
    global $wpdb;

    // Najpierw sprawdź, czy URL jest już znany
    $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE guid='%s';", $url));

    if (!empty($attachment[0])) {
        return (int) $attachment[0];
    }

    // Sprawdź, czy URL może zawierać obrazek z lokalnego serwera
    $upload_dir = wp_upload_dir();
    if (stripos($url, $upload_dir['baseurl']) !== false) {
        $file = basename($url);

        // Szukaj po nazwie pliku
        $query = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s OR guid LIKE %s";
        $attachment = $wpdb->get_col($wpdb->prepare($query, array(
            pathinfo($file, PATHINFO_FILENAME),
            '%/' . $file
        )));

        if (!empty($attachment[0])) {
            return (int) $attachment[0];
        }
    }

    return 0;
}

// Funkcja do przetwarzania i optymalizacji obrazów
function optimize_and_convert_image($file_path, $max_width = 1260, $max_height = 1400)
{
    global $optimized_images;

    // Sprawdź czy GD jest dostępna
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
        add_to_log("Błąd: Biblioteka GD nie jest dostępna. Optymalizacja zdjęć pominięta.");
        return $file_path;
    }

    // Pobierz informacje o obrazie
    $image_info = getimagesize($file_path);
    if ($image_info === false) {
        add_to_log("Błąd: Nie można odczytać informacji o obrazie: " . $file_path);
        return $file_path;
    }

    $mime_type = $image_info['mime'];
    $width = $image_info[0];
    $height = $image_info[1];

    // Pomiń jeśli to nie jest obraz PNG lub JPG
    if ($mime_type !== 'image/jpeg' && $mime_type !== 'image/png') {
        add_to_log("Pomijam optymalizację - nieobsługiwany format obrazu: " . $mime_type);
        return $file_path;
    }

    // Utwórz zasób obrazu
    $source_image = null;
    switch ($mime_type) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($file_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($file_path);
            break;
        default:
            add_to_log("Nieobsługiwany format obrazu: " . $mime_type);
            return $file_path;
    }

    if (!$source_image) {
        add_to_log("Błąd: Nie można utworzyć zasobu obrazu: " . $file_path);
        return $file_path;
    }

    // Oblicz nowe wymiary zachowując proporcje
    $new_width = $width;
    $new_height = $height;

    if ($width > $max_width || $height > $max_height) {
        $ratio_width = $max_width / $width;
        $ratio_height = $max_height / $height;
        $ratio = min($ratio_width, $ratio_height);

        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
    }

    // Utwórz nowy obraz ze skalowaniem
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // Zachowaj przezroczystość dla PNG
    if ($mime_type === 'image/png') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // Skaluj obraz
    imagecopyresampled(
        $new_image,
        $source_image,
        0,
        0,
        0,
        0,
        $new_width,
        $new_height,
        $width,
        $height
    );

    // Przygotuj ścieżkę dla WebP
    $dir_path = dirname($file_path);
    $file_name = pathinfo($file_path, PATHINFO_FILENAME);
    $webp_path = $dir_path . '/' . $file_name . '.webp';

    // Zapisz jako WebP
    $webp_quality = 85; // Balans między jakością a rozmiarem
    $success = false;

    if (function_exists('imagewebp')) {
        // Zapisz jako WebP
        $success = imagewebp($new_image, $webp_path, $webp_quality);
    } else {
        add_to_log("Funkcja imagewebp nie jest dostępna. Pomijam konwersję do WebP.");
        // Zapisz oryginalny format jako backup
        switch ($mime_type) {
            case 'image/jpeg':
                $success = imagejpeg($new_image, $file_path, 90);
                break;
            case 'image/png':
                $success = imagepng($new_image, $file_path, 9);
                break;
        }
        // Zwróć oryginalną ścieżkę, ponieważ WebP nie jest dostępny
        $webp_path = $file_path;
    }

    // Zwolnij pamięć
    imagedestroy($source_image);
    imagedestroy($new_image);

    if (!$success) {
        add_to_log("Błąd: Nie można zapisać zoptymalizowanego obrazu: " . $webp_path);
        return $file_path;
    }

    // Usuń oryginalny plik, chyba że konwersja nie powiodła się
    if ($success && file_exists($webp_path) && $webp_path !== $file_path) {
        $original_size = filesize($file_path);
        $optimized_size = filesize($webp_path);
        $saving = round(($original_size - $optimized_size) / $original_size * 100);

        unlink($file_path);
        $optimized_images++;

        add_to_log("Zoptymalizowano obraz: " . basename($file_path) . " → " . basename($webp_path) .
            " (" . round($optimized_size / 1024) . " KB, -" . $saving . "%)");
    }

    return $webp_path;
}

/**
 * Funkcja do pobierania i dodawania obrazka
 * 
 * @param string $image_url URL obrazka do pobrania
 * @param int $product_id ID produktu, do którego dodać obrazek
 * @param bool $is_featured Czy obrazek jest głównym zdjęciem produktu
 * @return int|false ID załącznika lub false w przypadku błędu
 */
function import_image($image_url, $product_id, $is_featured = false)
{
    global $optimized_images;
    $upload_dir = wp_upload_dir();
    $supplier_dir = isset($_GET['supplier']) ? sanitize_text_field($_GET['supplier']) : 'default';

    // Upewnij się, że katalog hurtowni istnieje
    $supplier_path = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier_dir;
    if (!file_exists($supplier_path)) {
        add_to_log("DEBUG - Tworzenie katalogu dla hurtowni: {$supplier_path}");
        wp_mkdir_p($supplier_path);
        // Sprawdź czy katalog został utworzony
        if (!file_exists($supplier_path)) {
            add_to_log("BŁĄD - Nie można utworzyć katalogu: {$supplier_path}");
            error_log("MHI ERROR: Nie można utworzyć katalogu: {$supplier_path}");
            return false;
        }
        // Ustaw odpowiednie uprawnienia do katalogu
        chmod($supplier_path, 0755);
    }

    // Log z informacją o ścieżce i URL obrazu
    add_to_log("DEBUG - URL obrazu: {$image_url}");
    error_log("MHI DEBUG: Importowanie obrazu: {$image_url}");

    // Sprawdź, czy obraz już istnieje (po URL)
    $existing_attachment_id = get_attachment_id_by_url($image_url);
    if ($existing_attachment_id) {
        add_to_log("Znaleziono istniejący obraz: ID {$existing_attachment_id}");
        error_log("MHI DEBUG: Znaleziono istniejący obraz: ID {$existing_attachment_id}");

        if ($is_featured) {
            set_post_thumbnail($product_id, $existing_attachment_id);
            add_to_log("Ustawiono istniejący obraz jako główny dla produktu ID: {$product_id}");
        }

        return $existing_attachment_id;
    }

    // Sprawdź czy URL jest prawidłowy
    if (filter_var($image_url, FILTER_VALIDATE_URL) === FALSE) {
        add_to_log("BŁĄD - Nieprawidłowy URL obrazu: {$image_url}");
        error_log("MHI ERROR: Nieprawidłowy URL obrazu: {$image_url}");
        return false;
    }

    // Pobierz plik obrazu
    $response = wp_remote_get($image_url, array(
        'timeout' => 60,
        'sslverify' => false,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ));

    if (is_wp_error($response)) {
        add_to_log("BŁĄD - Nie można pobrać obrazu: {$response->get_error_message()}");
        error_log("MHI ERROR: Nie można pobrać obrazu: {$response->get_error_message()}");
        return false;
    }

    $image_data = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);

    if ($http_code !== 200 || empty($image_data)) {
        add_to_log("BŁĄD - Nieprawidłowa odpowiedź HTTP ({$http_code}) lub puste dane obrazu");
        error_log("MHI ERROR: Nieprawidłowa odpowiedź HTTP ({$http_code}) lub puste dane obrazu z URL: {$image_url}");
        return false;
    }

    // Utwórz unikalną nazwę pliku
    $filename = wp_unique_filename($supplier_path, sanitize_file_name(basename($image_url)));
    $file_path = $supplier_path . '/' . $filename;

    add_to_log("DEBUG - Zapisywanie obrazu do: {$file_path}");
    error_log("MHI DEBUG: Zapisywanie obrazu do: {$file_path}");

    // Zapisz plik
    $saved = file_put_contents($file_path, $image_data);

    if (!$saved) {
        add_to_log("BŁĄD - Nie można zapisać obrazu do: {$file_path}");
        error_log("MHI ERROR: Nie można zapisać obrazu do: {$file_path}. Sprawdź uprawnienia.");
        return false;
    }

    // Sprawdź czy zapis się powiódł
    if (!file_exists($file_path)) {
        add_to_log("BŁĄD - Plik nie istnieje po zapisie: {$file_path}");
        error_log("MHI ERROR: Plik nie istnieje po zapisie: {$file_path}");
        return false;
    }

    // Optymalizuj obraz (konwersja do WebP jeśli możliwe)
    $optimized_path = optimize_and_convert_image($file_path);

    // Jeśli optymalizacja się powiodła, użyj zoptymalizowanej wersji
    if ($optimized_path && file_exists($optimized_path)) {
        add_to_log("Obraz zoptymalizowany: {$optimized_path}");
        error_log("MHI DEBUG: Obraz zoptymalizowany: {$optimized_path}");
        $optimized_images++;
        $file_path = $optimized_path;
    }

    // Pobierz informacje o typie pliku
    $filetype = wp_check_filetype(basename($file_path), null);

    // Ścieżka względna do pliku (względem wp-content/uploads)
    $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

    add_to_log("DEBUG - Względna ścieżka pliku: {$relative_path}");
    error_log("MHI DEBUG: Względna ścieżka pliku: {$relative_path}");

    // Przygotuj dane załącznika
    $attachment = array(
        'guid' => $upload_dir['baseurl'] . '/' . $relative_path,
        'post_mime_type' => $filetype['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', basename($file_path)),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    // Wstaw załącznik do biblioteki mediów
    $attach_id = wp_insert_attachment($attachment, $relative_path, $product_id);

    if (!$attach_id) {
        add_to_log("BŁĄD - Nie można wstawić obrazu do biblioteki mediów");
        error_log("MHI ERROR: Nie można wstawić obrazu do biblioteki mediów: {$relative_path}");
        return false;
    }

    // Make sure required files for media handling are loaded
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Generuj metadane
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

    if (empty($attach_data)) {
        add_to_log("OSTRZEŻENIE - Nie można wygenerować metadanych dla obrazu");
        error_log("MHI WARNING: Nie można wygenerować metadanych dla obrazu: {$file_path}");
    } else {
        // Aktualizuj metadane załącznika
        wp_update_attachment_metadata($attach_id, $attach_data);
    }

    // Zapisz oryginalny URL obrazu jako meta dane
    update_post_meta($attach_id, '_mhi_original_url', $image_url);
    update_post_meta($attach_id, '_mhi_supplier', $supplier_dir);
    update_post_meta($attach_id, '_mhi_import_date', current_time('mysql'));

    // Ustaw jako główny obrazek produktu, jeśli wymagane
    if ($is_featured) {
        $result = set_post_thumbnail($product_id, $attach_id);
        if ($result) {
            add_to_log("Ustawiono główny obrazek dla produktu ID: {$product_id}");
            error_log("MHI DEBUG: Ustawiono główny obrazek dla produktu ID: {$product_id}, attachment ID: {$attach_id}");
        } else {
            add_to_log("BŁĄD - Nie można ustawić głównego obrazka dla produktu ID: {$product_id}");
            error_log("MHI ERROR: Nie można ustawić głównego obrazka dla produktu ID: {$product_id}");
        }
    }

    add_to_log("Obrazek dodany pomyślnie, ID: {$attach_id}");
    return $attach_id;
}

// Funkcja do logowania do kontenera logów
function add_to_log($message)
{
    // Zabezpiecz znaki specjalne w JavaScript
    if (!is_string($message)) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        } else {
            $message = strval($message);
        }
    }

    $escaped_message = addslashes(str_replace(array("\r", "\n"), array('\\r', '\\n'), $message));

    echo '<script>
        var logContainer = document.getElementById("log-container");
        var newLog = document.createElement("p");
        newLog.textContent = `' . $escaped_message . '`;
        logContainer.appendChild(newLog);
        logContainer.scrollTop = logContainer.scrollHeight;
    </script>';
    flush();
}

// Funkcja do aktualizacji informacji o produkcie
function update_product_info($product_name, $sku)
{
    // Zabezpiecz znaki specjalne w JavaScript
    if (!is_string($product_name)) {
        $product_name = strval($product_name);
    }
    if (!is_string($sku)) {
        $sku = strval($sku);
    }

    $escaped_product_name = addslashes(str_replace(array("\r", "\n"), array('\\r', '\\n'), $product_name));
    $escaped_sku = addslashes(str_replace(array("\r", "\n"), array('\\r', '\\n'), $sku));

    echo '<script>
        var latestProduct = document.getElementById("latest-product");
        if (latestProduct) {
            latestProduct.innerHTML = `<p><strong>Nazwa:</strong> ' . $escaped_product_name . '</p><p><strong>SKU:</strong> ' . $escaped_sku . '</p>`;
        }
    </script>';
    flush();
}

// Funkcja do aktualizacji wszystkich statystyk
function update_stats($processed, $total_products, $created, $updated, $skipped, $failed, $images_added, $attributes_added, $categories_added, $optimized_images, $start_time)
{
    $percent = round(($processed / $total_products) * 100);
    $elapsed = microtime(true) - $start_time;
    $estimated = ($elapsed / $processed) * ($total_products - $processed);

    // Użyj intval() aby jawnie przekonwertować wartości float do int, unikając ostrzeżeń deprecation
    $elapsed_seconds = intval($elapsed);
    $estimated_seconds = intval($estimated);

    $elapsed_formatted = gmdate("H:i:s", $elapsed_seconds);
    $estimated_formatted = gmdate("H:i:s", $estimated_seconds);

    echo '<script>
        document.getElementById("progress-bar").style.width = `' . $percent . '%`;
        document.getElementById("progress-bar").innerText = `' . $percent . '%`;
        document.getElementById("processed-count").innerText = `' . $processed . ' / ' . $total_products . '`;
        document.getElementById("created-count").innerText = `' . $created . '`;
        document.getElementById("updated-count").innerText = `' . $updated . '`;
        document.getElementById("failed-count").innerText = `' . $failed . '`;
        document.getElementById("images-count").innerText = `' . $images_added . ' (' . $optimized_images . ' WebP)`;
        document.getElementById("categories-count").innerText = `' . $categories_added . '`;
        document.getElementById("elapsed-time").innerText = `' . $elapsed_formatted . '`;
        document.getElementById("estimated-time").innerText = `' . $estimated_formatted . '`;
    </script>';
    flush();
}

// Funkcja do określenia, które zdjęcie powinno być główne dla danej hurtowni
function get_main_image_index($supplier, $images_count)
{
    if ($images_count === 0) {
        return -1; // Brak zdjęć
    }

    // Konfiguracja dla różnych hurtowni
    switch (strtolower($supplier)) {
        case 'axpol':
            // Axpol używa ostatniego zdjęcia jako głównego
            return $images_count - 1;
        case 'inspirion':
        case 'macma':
            // Te wholesale używają pierwszego zdjęcia jako głównego
            return 0;
        case 'malfini':
            // Malfini używa drugiego zdjęcia jako głównego (jeśli dostępne)
            return ($images_count > 1) ? 1 : 0;
        case 'par':
            // Par używa trzeciego zdjęcia jako głównego (jeśli dostępne)
            return ($images_count > 2) ? 2 : 0;
        default:
            // Domyślnie używamy pierwszego zdjęcia
            return 0;
    }
}

// Funkcja importu - dodajemy parametr $start_from do wznowienia importu
function run_import($xml_file, $supplier, $memory_limit = '2048M', $start_from = 0)
{
    global $wpdb; // Dodaję globalną zmienną $wpdb

    // Obsługa zatrzymywania importu
    $upload_dir = wp_upload_dir();
    $stop_file = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $supplier . '/stop_import.flag';

    // ... existing code ...

    try {
        // Ładowanie pliku XML
        $xml = simplexml_load_file($xml_file);
        if (!$xml) {
            add_to_log("Błąd: Nie można załadować pliku XML.");
            exit;
        }

        $products = $xml->children();
        $total_products = count($products);
        add_to_log("Znaleziono {$total_products} produktów do importu");

        // Inicjalizacja statystyk
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $images_added = 0;
        $attributes_added = 0;
        $categories_added = 0;
        $optimized_images = 0;

        // Początek pomiaru czasu
        $start_time = microtime(true);

        // Iteracja po produktach - zmodyfikowana, aby uwzględnić wznowienie
        $i = 0;
        foreach ($products as $product) {
            $i++;

            // Pomijamy już przetworzone produkty przy wznowieniu
            if ($i <= $start_from) {
                continue;
            }

            // Sprawdź czy import powinien zostać zatrzymany
            if (should_stop_import($stop_file)) {
                add_to_log("Import został zatrzymany przez użytkownika");
                echo '<script>
                    var stopBtn = document.getElementById("stop-import");
                    stopBtn.innerText = `Import zatrzymany`;
                    stopBtn.disabled = true;
                </script>';
                break;
            }

            $processed++;

            // Pobierz SKU produktu
            $sku = (string) $product->sku;
            if (empty($sku)) {
                $sku = (string) $product->id;
            }

            if (empty($sku)) {
                $failed++;
                add_to_log("Pominięto produkt bez SKU");
                update_stats($processed, $total_products, $created, $updated, $skipped, $failed, $images_added, $attributes_added, $categories_added, $optimized_images, $start_time);
                continue; // Pomiń produkty bez SKU
            }

            // Pobierz nazwę produktu
            $product_name = (string) $product->name;
            if (empty($product_name)) {
                $product_name = (string) $product->n;
            }

            if (empty($product_name)) {
                $product_name = 'Produkt ' . $sku;
            }

            // Aktualizuj informacje o przetwarzanym produkcie
            update_product_info($product_name, $sku);
            add_to_log("Przetwarzanie produktu: " . $product_name . " (SKU: " . $sku . ")");

            // Sprawdź czy produkt już istnieje
            $product_id = wc_get_product_id_by_sku($sku);
            $product = null;

            if ($product_id) {
                $product = wc_get_product($product_id);
                add_to_log("DEBUG - Produkt istnieje, ID: " . $product_id);
            } else {
                add_to_log("DEBUG - Produkt nie istnieje, będzie utworzony nowy");
            }

            // Dane produktu
            $product_args = array(
                'name' => $product_name,
                'description' => (string) $product->description,
                'short_description' => (string) $product->short_description,
                'status' => 'publish',
                'featured' => ((string) $product->featured === 'yes'),
                'catalog_visibility' => (string) $product->visibility ?: 'visible',
                'sku' => $sku,
                'regular_price' => (string) $product->regular_price,
                'sale_price' => (string) $product->sale_price,
                'virtual' => ((string) $product->virtual === 'yes'),
                'downloadable' => ((string) $product->downloadable === 'yes'),
                'manage_stock' => isset($product->manage_stock) ? ((string) $product->manage_stock === 'yes') : true,
                'stock_quantity' => (int) $product->stock_quantity,
                'stock_status' => (string) $product->stock_status ?: 'instock',
                'backorders' => (string) $product->backorders ?: 'no',
                'sold_individually' => ((string) $product->sold_individually === 'yes'),
                'weight' => (string) $product->weight,
                'length' => (string) $product->length,
                'width' => (string) $product->width,
                'height' => (string) $product->height,
            );

            // Aktualizacja lub utworzenie produktu
            if ($product) {
                add_to_log("DEBUG - Sprawdzanie czy produkt wymaga aktualizacji: " . $product_name);

                // Aktualizuj produkt
                if (isset($product_args['name']))
                    $product->set_name($product_args['name']);
                if (isset($product_args['description']))
                    $product->set_description($product_args['description']);
                if (isset($product_args['short_description']))
                    $product->set_short_description($product_args['short_description']);
                if (isset($product_args['status']))
                    $product->set_status($product_args['status']);
                if (isset($product_args['featured']))
                    $product->set_featured($product_args['featured']);
                if (isset($product_args['catalog_visibility']))
                    $product->set_catalog_visibility($product_args['catalog_visibility']);
                if (isset($product_args['regular_price']))
                    $product->set_regular_price($product_args['regular_price']);
                if (isset($product_args['sale_price']))
                    $product->set_sale_price($product_args['sale_price']);
                if (isset($product_args['virtual']))
                    $product->set_virtual($product_args['virtual']);
                if (isset($product_args['downloadable']))
                    $product->set_downloadable($product_args['downloadable']);
                if (isset($product_args['manage_stock']))
                    $product->set_manage_stock($product_args['manage_stock']);
                if (isset($product_args['stock_quantity']))
                    $product->set_stock_quantity($product_args['stock_quantity']);
                if (isset($product_args['stock_status']))
                    $product->set_stock_status($product_args['stock_status']);
                if (isset($product_args['backorders']))
                    $product->set_backorders($product_args['backorders']);
                if (isset($product_args['sold_individually']))
                    $product->set_sold_individually($product_args['sold_individually']);
                if (isset($product_args['weight']))
                    $product->set_weight($product_args['weight']);
                if (isset($product_args['length']))
                    $product->set_length($product_args['length']);
                if (isset($product_args['width']))
                    $product->set_width($product_args['width']);
                if (isset($product_args['height']))
                    $product->set_height($product_args['height']);

                $product->save();
                add_to_log("DEBUG - Zapisano aktualizację podstawowych danych produktu");

                // Dodaj kategorie z obsługą hierarchii
                if (isset($product->categories)) {
                    $category_ids = array();

                    add_to_log("DEBUG - Przetwarzanie kategorii dla produktu: " . $product_name);

                    // Sprawdzam czy categories->category to tablica czy pojedynczy obiekt
                    $categories = isset($product->categories->category) ? $product->categories->category : array();
                    if (!is_array($categories)) {
                        $categories = array($categories); // Konwersja pojedynczego obiektu do tablicy
                    }

                    foreach ($categories as $category) {
                        $cat_string = (string) $category;
                        add_to_log("DEBUG - Kategoria z XML: '" . $cat_string . "'");

                        if (!empty($cat_string)) {
                            $new_categories = process_categories($cat_string);
                            add_to_log("DEBUG - Znaleziono ID kategorii: " . implode(", ", $new_categories));

                            $category_ids = array_merge($category_ids, $new_categories);
                            $categories_added += count($new_categories);
                        }
                    }

                    if (!empty($category_ids)) {
                        add_to_log("DEBUG - Przypisywanie kategorii do produktu: " . implode(", ", array_unique($category_ids)));
                        $result = wp_set_object_terms($product_id, array_unique($category_ids), 'product_cat');
                        if (is_wp_error($result)) {
                            add_to_log("BŁĄD - Nie można przypisać kategorii: " . $result->get_error_message());
                        } else {
                            add_to_log("DEBUG - Kategorie przypisane pomyślnie");
                        }
                    } else {
                        add_to_log("DEBUG - Brak kategorii do przypisania");
                    }
                }

                // Przetwarzanie atrybutów produktu
                if (isset($product->attributes) && $product->attributes->attribute) {
                    $product_attributes = array();

                    add_to_log("DEBUG - Przetwarzanie atrybutów dla produktu: " . $product_name);

                    // Sprawdzam czy attributes->attribute to tablica czy pojedynczy obiekt
                    $attributes = $product->attributes->attribute;
                    if (!is_array($attributes)) {
                        $attributes = array($attributes); // Konwersja pojedynczego obiektu do tablicy
                    }

                    foreach ($attributes as $attribute) {
                        $attr_name = '';
                        $attr_value = '';

                        // Obsługa różnych formatów atrybutów
                        if (isset($attribute['name']) && isset($attribute['value'])) {
                            // Format: <attribute name="Color" value="Red" />
                            $attr_name = (string) $attribute['name'];
                            $attr_value = (string) $attribute['value'];
                            add_to_log("DEBUG - Znaleziono atrybut w formacie z atrybutami: " . $attr_name . " = " . $attr_value);
                        } else if (isset($attribute->name) && isset($attribute->value)) {
                            // Format: <attribute><name>Color</name><value>Red</value></attribute>
                            $attr_name = (string) $attribute->name;
                            $attr_value = (string) $attribute->value;
                            add_to_log("DEBUG - Znaleziono atrybut w formacie z zagnieżdżonymi elementami: " . $attr_name . " = " . $attr_value);
                        } else {
                            // Prosty format tekstowy (możliwa konieczność parsowania)
                            $attr_text = (string) $attribute;
                            if (strpos($attr_text, ':') !== false) {
                                list($attr_name, $attr_value) = array_map('trim', explode(':', $attr_text, 2));
                            } else if (strpos($attr_text, '=') !== false) {
                                list($attr_name, $attr_value) = array_map('trim', explode('=', $attr_text, 2));
                            } else {
                                $attr_name = "Cecha";
                                $attr_value = $attr_text;
                            }
                            add_to_log("DEBUG - Parsowanie atrybutu z tekstu: " . $attr_text . " -> " . $attr_name . " = " . $attr_value);
                        }

                        // Pomiń puste atrybuty
                        if (empty($attr_name) || empty($attr_value)) {
                            add_to_log("DEBUG - Pominięto pusty atrybut");
                            continue;
                        }

                        // Usuń niepotrzebne białe znaki
                        $attr_name = trim($attr_name);
                        $attr_value = trim($attr_value);

                        $taxonomy_name = get_or_create_attribute($attr_name);
                        if (!$taxonomy_name) {
                            add_to_log("BŁĄD - Nie można utworzyć atrybutu: " . $attr_name);
                            continue;
                        }

                        // Przygotuj slug taksonomii
                        $taxonomy = 'pa_' . wc_sanitize_taxonomy_name($attr_name);

                        // Upewnij się, że taksonomia istnieje
                        if (!taxonomy_exists($taxonomy)) {
                            add_to_log("OSTRZEŻENIE - Taksonomia nadal nie istnieje: " . $taxonomy);

                            // Odśwież listę taksonomii atrybutów
                            delete_transient('wc_attribute_taxonomies');
                            WC()->attributes->init(); // Zainicjuj ponownie atrybuty

                            if (!taxonomy_exists($taxonomy)) {
                                add_to_log("BŁĄD - Nie można utworzyć taksonomii: " . $taxonomy);
                                continue;
                            }
                        }

                        // Przygotuj termin (wartość atrybutu)
                        $term_name = stripslashes($attr_value);
                        $term = term_exists($term_name, $taxonomy);

                        if (!$term) {
                            $term = wp_insert_term($term_name, $taxonomy);
                            if (is_wp_error($term)) {
                                add_to_log("BŁĄD - Nie można utworzyć terminu: " . $term->get_error_message());
                                continue;
                            }
                            add_to_log("DEBUG - Utworzono nowy termin: " . $term_name . " dla taksonomii " . $taxonomy);
                        } else {
                            add_to_log("DEBUG - Znaleziono istniejący termin: " . $term_name . " dla taksonomii " . $taxonomy);
                        }

                        // Pobierz ID terminu
                        $term_id = is_array($term) ? $term['term_id'] : $term;

                        // Przypisz atrybut do produktu
                        wp_set_object_terms($product_id, $term_id, $taxonomy, true);

                        // Dodaj atrybut do meta danych produktu
                        $product_attributes[$taxonomy] = array(
                            'name' => $taxonomy,
                            'value' => '',
                            'position' => count($product_attributes) + 1,
                            'is_visible' => 1,
                            'is_variation' => 0,
                            'is_taxonomy' => 1
                        );

                        add_to_log("DEBUG - Dodano atrybut: " . $attr_name . " = " . $attr_value . " do produktu " . $product_name);
                        $attributes_added++;
                    }

                    // Zapisz atrybuty do produktu
                    if (!empty($product_attributes)) {
                        update_post_meta($product_id, '_product_attributes', $product_attributes);
                        add_to_log("DEBUG - Zapisano atrybuty do produktu: " . $product_name);
                    }
                }

                // Import obrazków
                if (isset($product->images) && $product->images->image) {
                    $image_ids = array();
                    $image_urls = array();

                    add_to_log("DEBUG - Przetwarzanie obrazków dla produktu: " . $product_name);

                    // Sprawdzam czy images->image to tablica czy pojedynczy obiekt
                    $images = $product->images->image;
                    if (!is_array($images)) {
                        $images = array($images); // Konwersja pojedynczego obiektu do tablicy
                    }

                    // Najpierw zbierz wszystkie URL obrazków
                    foreach ($images as $image) {
                        $image_url = '';

                        // Dokładne sprawdzenie struktury XML z obrazkiem
                        if (is_object($image)) {
                            if (isset($image->src)) {
                                // Format: <image><src>URL</src></image>
                                $image_url = (string) $image->src;
                                add_to_log("DEBUG - Znaleziono URL obrazka w formacie <src>: " . $image_url);
                            } else if (isset($image['src'])) {
                                // Format Malfini: <image src="URL"/>
                                $image_url = (string) $image['src'];
                                add_to_log("DEBUG - Znaleziono URL obrazka w atrybucie src: " . $image_url);
                            } else {
                                // Format: <image>URL</image>
                                $image_url = (string) $image;
                                add_to_log("DEBUG - Znaleziono URL obrazka bezpośrednio w tagu: " . $image_url);
                            }
                        } else {
                            // Prosty string
                            $image_url = (string) $image;
                            add_to_log("DEBUG - Znaleziono URL obrazka jako prosty string: " . $image_url);
                        }

                        if (!empty($image_url)) {
                            add_to_log("DEBUG - Dodano URL obrazka do listy: " . $image_url);
                            $image_urls[] = $image_url;
                        } else {
                            add_to_log("DEBUG - Pominięto pusty URL obrazka");
                        }
                    }

                    add_to_log("DEBUG - Znaleziono obrazków: " . count($image_urls));

                    // Określ indeks głównego zdjęcia dla danej hurtowni
                    $main_image_index = get_main_image_index($supplier, count($image_urls));
                    add_to_log("DEBUG - Indeks głównego zdjęcia dla hurtowni " . $supplier . ": " . $main_image_index);

                    // Importuj wszystkie obrazki
                    foreach ($image_urls as $index => $image_url) {
                        $is_featured = ($index === $main_image_index);
                        add_to_log("DEBUG - Importowanie obrazka: " . $image_url . ($is_featured ? " (główny)" : ""));

                        $attachment_id = import_image($image_url, $product_id, $is_featured);

                        if ($attachment_id) {
                            add_to_log("DEBUG - Obrazek dodany, ID: " . $attachment_id);
                            $image_ids[] = $attachment_id;
                            $images_added++;

                            if ($is_featured) {
                                add_to_log("Dodano główne zdjęcie dla produktu: " . $product_name);
                            }
                        } else {
                            add_to_log("BŁĄD - Nie można zaimportować obrazka: " . $image_url);
                        }
                    }

                    // Dodaj pozostałe obrazki jako galeria produktu
                    if (count($image_ids) > 1) {
                        // Filtrujemy, aby usunąć obrazek główny z galerii
                        $featured_image_id = get_post_thumbnail_id($product_id);
                        add_to_log("DEBUG - ID głównego obrazka: " . $featured_image_id);

                        $gallery_ids = array_filter($image_ids, function ($id) use ($featured_image_id) {
                            return $id != $featured_image_id;
                        });

                        if (!empty($gallery_ids)) {
                            add_to_log("DEBUG - Dodawanie galerii, obrazki: " . implode(", ", $gallery_ids));
                            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                        } else {
                            add_to_log("DEBUG - Brak obrazków do galerii");
                        }
                    } else {
                        add_to_log("DEBUG - Za mało obrazków do utworzenia galerii");
                    }
                } else {
                    add_to_log("DEBUG - Brak obrazków dla produktu w XML");
                }

                $updated++;
                add_to_log("Zaktualizowano produkt: " . $product_name);
            } else {
                // Utwórz nowy produkt
                $product = new WC_Product();
                $product->set_name($product_args['name']);
                $product->set_description($product_args['description']);
                $product->set_short_description($product_args['short_description']);
                $product->set_status('publish'); // Upewnij się, że status jest zawsze 'publish'
                $product->set_featured($product_args['featured']);
                $product->set_catalog_visibility($product_args['catalog_visibility']);
                $product->set_sku($product_args['sku']);
                $product->set_regular_price($product_args['regular_price']);
                $product->set_sale_price($product_args['sale_price']);
                $product->set_virtual($product_args['virtual']);
                $product->set_downloadable($product_args['downloadable']);
                $product->set_manage_stock($product_args['manage_stock']);
                $product->set_stock_quantity($product_args['stock_quantity']);
                $product->set_stock_status($product_args['stock_status']);
                $product->set_backorders($product_args['backorders']);
                $product->set_sold_individually($product_args['sold_individually']);
                $product->set_weight($product_args['weight']);
                $product->set_length($product_args['length']);
                $product->set_width($product_args['width']);
                $product->set_height($product_args['height']);

                // Zapisz produkt
                add_to_log("DEBUG - Zapisywanie nowego produktu WooCommerce: " . $product_args['name']);
                $product_id = $product->save();

                // Sprawdź czy produkt został rzeczywiście utworzony
                if (!$product_id || is_wp_error($product_id)) {
                    add_to_log("BŁĄD - Nie udało się utworzyć produktu: " . $product_args['name']);
                    if (is_wp_error($product_id)) {
                        add_to_log("BŁĄD - " . $product_id->get_error_message());
                    }
                    $failed++;
                    continue;
                } else {
                    add_to_log("DEBUG - Utworzono nowy produkt, ID: " . $product_id);

                    // Dodatkowe sprawdzenie po utworzeniu
                    $post_status = get_post_status($product_id);
                    add_to_log("DEBUG - Status nowego produktu: " . $post_status);

                    // Upewnij się, że produkt ma status 'publish'
                    if ($post_status !== 'publish') {
                        add_to_log("DEBUG - Wymuszanie statusu 'publish' dla produktu: " . $product_id);
                        wp_update_post(array(
                            'ID' => $product_id,
                            'post_status' => 'publish'
                        ));
                    }

                    // Dodaj metadane, aby oznaczyć produkt jako zaimportowany
                    update_post_meta($product_id, '_mhi_imported', 'yes');
                    update_post_meta($product_id, '_mhi_supplier', $supplier);
                    update_post_meta($product_id, '_mhi_import_date', current_time('mysql'));

                    add_to_log("DEBUG - Metadane produktu zaktualizowane");
                }

                // Dodaj kategorie z obsługą hierarchii
                if (isset($product->categories)) {
                    $category_ids = array();

                    add_to_log("DEBUG - Przetwarzanie kategorii dla produktu: " . $product_name);

                    // Sprawdzam czy categories->category to tablica czy pojedynczy obiekt
                    $categories = isset($product->categories->category) ? $product->categories->category : array();
                    if (!is_array($categories)) {
                        $categories = array($categories); // Konwersja pojedynczego obiektu do tablicy
                    }

                    foreach ($categories as $category) {
                        $cat_string = (string) $category;
                        add_to_log("DEBUG - Kategoria z XML: '" . $cat_string . "'");

                        if (!empty($cat_string)) {
                            $new_categories = process_categories($cat_string);
                            add_to_log("DEBUG - Znaleziono ID kategorii: " . implode(", ", $new_categories));

                            $category_ids = array_merge($category_ids, $new_categories);
                            $categories_added += count($new_categories);
                        }
                    }

                    if (!empty($category_ids)) {
                        add_to_log("DEBUG - Przypisywanie kategorii do produktu: " . implode(", ", array_unique($category_ids)));
                        $result = wp_set_object_terms($product_id, array_unique($category_ids), 'product_cat');
                        if (is_wp_error($result)) {
                            add_to_log("BŁĄD - Nie można przypisać kategorii: " . $result->get_error_message());
                        } else {
                            add_to_log("DEBUG - Kategorie przypisane pomyślnie");
                        }
                    } else {
                        add_to_log("DEBUG - Brak kategorii do przypisania");
                    }
                }

                // Przetwarzanie atrybutów produktu
                if (isset($product->attributes) && $product->attributes->attribute) {
                    $product_attributes = array();

                    add_to_log("DEBUG - Przetwarzanie atrybutów dla produktu: " . $product_name);

                    // Sprawdzam czy attributes->attribute to tablica czy pojedynczy obiekt
                    $attributes = $product->attributes->attribute;
                    if (!is_array($attributes)) {
                        $attributes = array($attributes); // Konwersja pojedynczego obiektu do tablicy
                    }

                    foreach ($attributes as $attribute) {
                        $attr_name = '';
                        $attr_value = '';

                        // Obsługa różnych formatów atrybutów
                        if (isset($attribute['name']) && isset($attribute['value'])) {
                            // Format: <attribute name="Color" value="Red" />
                            $attr_name = (string) $attribute['name'];
                            $attr_value = (string) $attribute['value'];
                            add_to_log("DEBUG - Znaleziono atrybut w formacie z atrybutami: " . $attr_name . " = " . $attr_value);
                        } else if (isset($attribute->name) && isset($attribute->value)) {
                            // Format: <attribute><name>Color</name><value>Red</value></attribute>
                            $attr_name = (string) $attribute->name;
                            $attr_value = (string) $attribute->value;
                            add_to_log("DEBUG - Znaleziono atrybut w formacie z zagnieżdżonymi elementami: " . $attr_name . " = " . $attr_value);
                        } else {
                            // Prosty format tekstowy (możliwa konieczność parsowania)
                            $attr_text = (string) $attribute;
                            if (strpos($attr_text, ':') !== false) {
                                list($attr_name, $attr_value) = array_map('trim', explode(':', $attr_text, 2));
                            } else if (strpos($attr_text, '=') !== false) {
                                list($attr_name, $attr_value) = array_map('trim', explode('=', $attr_text, 2));
                            } else {
                                $attr_name = "Cecha";
                                $attr_value = $attr_text;
                            }
                            add_to_log("DEBUG - Parsowanie atrybutu z tekstu: " . $attr_text . " -> " . $attr_name . " = " . $attr_value);
                        }

                        // Pomiń puste atrybuty
                        if (empty($attr_name) || empty($attr_value)) {
                            add_to_log("DEBUG - Pominięto pusty atrybut");
                            continue;
                        }

                        // Usuń niepotrzebne białe znaki
                        $attr_name = trim($attr_name);
                        $attr_value = trim($attr_value);

                        $taxonomy_name = get_or_create_attribute($attr_name);
                        if (!$taxonomy_name) {
                            add_to_log("BŁĄD - Nie można utworzyć atrybutu: " . $attr_name);
                            continue;
                        }

                        // Przygotuj slug taksonomii
                        $taxonomy = 'pa_' . wc_sanitize_taxonomy_name($attr_name);

                        // Upewnij się, że taksonomia istnieje
                        if (!taxonomy_exists($taxonomy)) {
                            add_to_log("OSTRZEŻENIE - Taksonomia nadal nie istnieje: " . $taxonomy);

                            // Odśwież listę taksonomii atrybutów
                            delete_transient('wc_attribute_taxonomies');
                            WC()->attributes->init(); // Zainicjuj ponownie atrybuty

                            if (!taxonomy_exists($taxonomy)) {
                                add_to_log("BŁĄD - Nie można utworzyć taksonomii: " . $taxonomy);
                                continue;
                            }
                        }

                        // Przygotuj termin (wartość atrybutu)
                        $term_name = stripslashes($attr_value);
                        $term = term_exists($term_name, $taxonomy);

                        if (!$term) {
                            $term = wp_insert_term($term_name, $taxonomy);
                            if (is_wp_error($term)) {
                                add_to_log("BŁĄD - Nie można utworzyć terminu: " . $term->get_error_message());
                                continue;
                            }
                            add_to_log("DEBUG - Utworzono nowy termin: " . $term_name . " dla taksonomii " . $taxonomy);
                        } else {
                            add_to_log("DEBUG - Znaleziono istniejący termin: " . $term_name . " dla taksonomii " . $taxonomy);
                        }

                        // Pobierz ID terminu
                        $term_id = is_array($term) ? $term['term_id'] : $term;

                        // Przypisz atrybut do produktu
                        wp_set_object_terms($product_id, $term_id, $taxonomy, true);

                        // Dodaj atrybut do meta danych produktu
                        $product_attributes[$taxonomy] = array(
                            'name' => $taxonomy,
                            'value' => '',
                            'position' => count($product_attributes) + 1,
                            'is_visible' => 1,
                            'is_variation' => 0,
                            'is_taxonomy' => 1
                        );

                        add_to_log("DEBUG - Dodano atrybut: " . $attr_name . " = " . $attr_value . " do produktu " . $product_name);
                        $attributes_added++;
                    }

                    // Zapisz atrybuty do produktu
                    if (!empty($product_attributes)) {
                        update_post_meta($product_id, '_product_attributes', $product_attributes);
                        add_to_log("DEBUG - Zapisano atrybuty do produktu: " . $product_name);
                    }
                }

                // Import obrazków
                if (isset($product->images) && $product->images->image) {
                    $image_ids = array();
                    $image_urls = array();

                    add_to_log("DEBUG - Przetwarzanie obrazków dla produktu: " . $product_name);

                    // Sprawdzam czy images->image to tablica czy pojedynczy obiekt
                    $images = $product->images->image;
                    if (!is_array($images)) {
                        $images = array($images); // Konwersja pojedynczego obiektu do tablicy
                    }

                    // Najpierw zbierz wszystkie URL obrazków
                    foreach ($images as $image) {
                        $image_url = '';

                        // Dokładne sprawdzenie struktury XML z obrazkiem
                        if (is_object($image)) {
                            if (isset($image->src)) {
                                // Format: <image><src>URL</src></image>
                                $image_url = (string) $image->src;
                                add_to_log("DEBUG - Znaleziono URL obrazka w formacie <src>: " . $image_url);
                            } else if (isset($image['src'])) {
                                // Format Malfini: <image src="URL"/>
                                $image_url = (string) $image['src'];
                                add_to_log("DEBUG - Znaleziono URL obrazka w atrybucie src: " . $image_url);
                            } else {
                                // Format: <image>URL</image>
                                $image_url = (string) $image;
                                add_to_log("DEBUG - Znaleziono URL obrazka bezpośrednio w tagu: " . $image_url);
                            }
                        } else {
                            // Prosty string
                            $image_url = (string) $image;
                            add_to_log("DEBUG - Znaleziono URL obrazka jako prosty string: " . $image_url);
                        }

                        if (!empty($image_url)) {
                            add_to_log("DEBUG - Dodano URL obrazka do listy: " . $image_url);
                            $image_urls[] = $image_url;
                        } else {
                            add_to_log("DEBUG - Pominięto pusty URL obrazka");
                        }
                    }

                    add_to_log("DEBUG - Znaleziono obrazków: " . count($image_urls));

                    // Określ indeks głównego zdjęcia dla danej hurtowni
                    $main_image_index = get_main_image_index($supplier, count($image_urls));
                    add_to_log("DEBUG - Indeks głównego zdjęcia dla hurtowni " . $supplier . ": " . $main_image_index);

                    // Importuj wszystkie obrazki
                    foreach ($image_urls as $index => $image_url) {
                        $is_featured = ($index === $main_image_index);
                        add_to_log("DEBUG - Importowanie obrazka: " . $image_url . ($is_featured ? " (główny)" : ""));

                        $attachment_id = import_image($image_url, $product_id, $is_featured);

                        if ($attachment_id) {
                            add_to_log("DEBUG - Obrazek dodany, ID: " . $attachment_id);
                            $image_ids[] = $attachment_id;
                            $images_added++;

                            if ($is_featured) {
                                add_to_log("Dodano główne zdjęcie dla produktu: " . $product_name);
                            }
                        } else {
                            add_to_log("BŁĄD - Nie można zaimportować obrazka: " . $image_url);
                        }
                    }

                    // Dodaj pozostałe obrazki jako galeria produktu
                    if (count($image_ids) > 1) {
                        // Filtrujemy, aby usunąć obrazek główny z galerii
                        $featured_image_id = get_post_thumbnail_id($product_id);
                        add_to_log("DEBUG - ID głównego obrazka: " . $featured_image_id);

                        $gallery_ids = array_filter($image_ids, function ($id) use ($featured_image_id) {
                            return $id != $featured_image_id;
                        });

                        if (!empty($gallery_ids)) {
                            add_to_log("DEBUG - Dodawanie galerii, obrazki: " . implode(", ", $gallery_ids));
                            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                        } else {
                            add_to_log("DEBUG - Brak obrazków do galerii");
                        }
                    } else {
                        add_to_log("DEBUG - Za mało obrazków do utworzenia galerii");
                    }
                } else {
                    add_to_log("DEBUG - Brak obrazków dla produktu w XML");
                }

                $created++;
                add_to_log("Utworzono nowy produkt: " . $product_name);
            }

            // Aktualizuj statystyki po każdym produkcie
            update_stats($processed, $total_products, $created, $updated, $skipped, $failed, $images_added, $attributes_added, $categories_added, $optimized_images, $start_time);
        }

        // Zatwierdź transakcję
        $wpdb->query('COMMIT');

        // Włącz z powrotem hooki
        wp_suspend_cache_invalidation(false);
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);

        // Wyczyść cache
        clean_post_cache($product_id);
        wc_delete_product_transients($product_id);
        wp_cache_flush();

        // Oblicz całkowity czas wykonania
        $total_time = microtime(true) - $start_time;

        // Wyświetl podsumowanie
        echo '<div class="summary">
            <h2>Import zakończony</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Produkty łącznie</h3>
                    <div class="value">' . $total_products . '</div>
                </div>
                <div class="stat-box">
                    <h3>Utworzone</h3>
                    <div class="value">' . $created . '</div>
                </div>
                <div class="stat-box">
                    <h3>Zaktualizowane</h3>
                    <div class="value">' . $updated . '</div>
                </div>
                <div class="stat-box">
                    <h3>Pominięte</h3>
                    <div class="value">' . $skipped . '</div>
                </div>
                <div class="stat-box">
                    <h3>Błędy</h3>
                    <div class="value">' . $failed . '</div>
                </div>
                <div class="stat-box">
                    <h3>Zdjęcia</h3>
                    <div class="value">' . $images_added . '</div>
                </div>
                <div class="stat-box">
                    <h3>Optymalizacje WebP</h3>
                    <div class="value">' . $optimized_images . '</div>
                </div>
                <div class="stat-box">
                    <h3>Atrybuty</h3>
                    <div class="value">' . $attributes_added . '</div>
                </div>
                <div class="stat-box">
                    <h3>Kategorie</h3>
                    <div class="value">' . $categories_added . '</div>
                </div>
            </div>
            <p>Całkowity czas importu: ' . gmdate("H:i:s", $total_time) . '</p>
            <a href="' . admin_url('admin.php?page=mhi-import') . '" class="back-link">Wróć do listy importerów</a>
        </div>';

    } catch (Exception $e) {
        // Anuluj transakcję w przypadku błędu
        $wpdb->query('ROLLBACK');

        // Zabezpiecz komunikaty błędów dla JavaScript
        $error_message = addslashes(str_replace(array("\r", "\n"), array('\\r', '\\n'), $e->getMessage()));
        $error_line = $e->getLine();
        $error_file = addslashes(str_replace(array("\r", "\n"), array('\\r', '\\n'), $e->getFile()));

        // Wyświetl błąd
        echo '<div class="error">';
        echo '<p>Wystąpił błąd podczas importu:</p>';
        echo '<p>' . $e->getMessage() . '</p>';
        echo '<p>Linia: ' . $error_line . '</p>';
        echo '<p>Plik: ' . $error_file . '</p>';
        echo '</div>';

        // Zamknij kontener
        echo '</div>';

        // Włącz z powrotem hooki
        wp_suspend_cache_invalidation(false);
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);

        exit;
    }

    // Zamknij kontener
    echo '</div>';

    // Zaktualizuj footer
    echo '<div class="footer">
        <p>Multi Hurtownie Integration (' . MHI_VERSION . ')</p>
        <p>&copy; ' . date('Y') . ' - Wszystkie prawa zastrzeżone</p>
    </div>';

    // Zamknij body i html
    echo '</body></html>';
}

// Uruchom import z uwzględnieniem wznowienia
$start_from = 0;
if ($resume && isset($import_status['processed'])) {
    $start_from = $import_status['processed'];
    add_to_log("Wznawianie importu od produktu: " . ($start_from + 1));
}

run_import($xml_file, $supplier, '2048M', $start_from);
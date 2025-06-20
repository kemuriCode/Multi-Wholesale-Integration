<?php
/**
 * Klasa generatora plików XML importu WooCommerce dla hurtowni Par.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Par_WC_XML_Generator
 * 
 * Generuje pliki XML kompatybilne z WooCommerce na podstawie danych Par.
 * Obsługuje duże pliki XML poprzez przetwarzanie strumieniowe i zarządzanie pamięcią.
 */
class MHI_Par_WC_XML_Generator
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    private $name = 'par';

    /**
     * Ścieżka do katalogu z plikami XML.
     *
     * @var string
     */
    private $source_dir;

    /**
     * Ścieżka do katalogu docelowego.
     *
     * @var string
     */
    private $target_dir;

    /**
     * Ścieżka do plików logów.
     *
     * @var string
     */
    private $log_dir;

    /**
     * Dane produktów.
     *
     * @var array
     */
    private $product_data = [];

    /**
     * Dane stanów magazynowych.
     *
     * @var array
     */
    private $stock_data = [];

    /**
     * Dane kategorii.
     *
     * @var array
     */
    private $category_data = [];

    /**
     * Mapa kategorii ID => Nazwa kategorii
     * 
     * @var array
     */
    private $category_map = [];

    /**
     * Mapa kategorii ID => Pełna ścieżka kategorii (string)
     * 
     * @var array
     */
    private $category_path_map = [];

    /**
     * Licznik produktów
     * 
     * @var integer
     */
    private $product_count = 0;

    /**
     * Konstruktor.
     *
     * @param string $source_dir Ścieżka do katalogu z plikami XML.
     */
    public function __construct($source_dir = '')
    {
        // Wyłączamy buforowanie wyjścia
        if (ob_get_level())
            ob_end_flush();

        // Logowanie do domyślnego output dla debugowania
        echo "Konstruktor wywołany z parametrem source_dir: " . ($source_dir ? $source_dir : 'pusty') . "\n";
        flush();

        // Ustawienie limitu pamięci dla przetwarzania dużych plików XML
        ini_set('memory_limit', '1536M'); // Zwiększamy limit pamięci
        set_time_limit(1800); // 30 minut na wykonanie

        if (empty($source_dir)) {
            $upload_dir = wp_upload_dir();
            $this->source_dir = trailingslashit($upload_dir['basedir']) . "wholesale/{$this->name}";
        } else {
            $this->source_dir = rtrim($source_dir, '/');
        }

        // Upewnij się, że ścieżka jest prawidłowa bez podwójnych slashów
        $this->source_dir = rtrim($this->source_dir, '/');

        echo "Ustawiony katalog źródłowy: {$this->source_dir}\n";
        flush();
        echo "Sprawdzam czy katalog istnieje: " . (file_exists($this->source_dir) ? "TAK" : "NIE") . "\n";
        flush();

        if (file_exists($this->source_dir)) {
            echo "Zawartość katalogu:\n";
            flush();
            $files = scandir($this->source_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo " - $file\n";
                    flush();
                }
            }
        }

        $this->target_dir = $this->source_dir;

        // Utworzenie katalogów na logi jeśli nie istnieją
        $this->log_dir = $this->source_dir . '/logs';
        if (!file_exists($this->log_dir)) {
            echo "Tworzę katalog logów: {$this->log_dir}\n";
            flush();
            wp_mkdir_p($this->log_dir);
        }

        // Ustawienie strefy czasowej dla poprawnych logów
        date_default_timezone_set('Europe/Warsaw');

        // Inicjalizacja plików logów
        $this->log('debug_init.txt', 'Inicjalizacja generatora XML dla hurtowni ' . $this->name, false);
        echo "Inicjalizacja zakończona\n";
        flush();
    }

    /**
     * Metoda pomocnicza do logowania
     * 
     * @param string $file Nazwa pliku log
     * @param string $message Wiadomość
     * @param boolean $append Czy dopisywać (true) czy tworzyć nowy (false)
     */
    private function log($file, $message, $append = true)
    {
        $log_file = $this->log_dir . '/' . $file;
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";

        if ($append) {
            file_put_contents($log_file, $log_message, FILE_APPEND);
        } else {
            file_put_contents($log_file, $log_message);
        }

        // Dodaj też wpis do logu WordPress
        if (function_exists('MHI_Logger::info')) {
            MHI_Logger::info($message);
        }

        // Zapisz też do error_log dla sprawdzenia w razie problemów
        error_log('MHI_PAR: ' . $message);
    }

    /**
     * Wczytuje dane z plików XML hurtowni.
     *
     * @return boolean True jeśli dane zostały wczytane pomyślnie, false w przeciwnym razie.
     */
    public function load_xml_data()
    {
        if (class_exists('MHI_Logger')) {
            MHI_Logger::info("PAR - Rozpoczynam wczytywanie danych XML z katalogu: {$this->source_dir}");
        }

        $this->log('debug_summary.txt', 'Rozpoczęcie wczytywania danych XML', false);
        $this->log('debug_load.txt', 'Rozpoczęcie wczytywania danych XML', false);
        $this->log('debug_error.txt', 'Logi błędów podczas wczytywania danych XML', false);

        // Lista plików Par (poprawne nazwy)
        $required_files = [
            'produkty' => [
                'pattern' => 'lista_produktow.xml', // Poprawka: Par używa tego pliku
                'required' => true
            ],
            'stany' => [
                'pattern' => 'stan_magazynowy.xml', // Poprawka: Par używa tego pliku
                'required' => true
            ],
            'kategorie' => [
                'pattern' => 'kategorie.xml',
                'required' => false
            ]
        ];

        // Sprawdzamy dostępne pliki XML
        $available_files = [];
        foreach ($required_files as $key => $file_info) {
            $patterns = is_array($file_info['pattern']) ? $file_info['pattern'] : [$file_info['pattern']];
            $file_found = false;

            foreach ($patterns as $pattern) {
                $file_path = trailingslashit($this->source_dir) . $pattern;

                // Debug: zapisz dokładną ścieżkę
                $this->log('debug_load.txt', "Sprawdzam ścieżkę: {$file_path}");

                if (file_exists($file_path)) {
                    $available_files[$key] = $file_path;
                    $this->log('debug_load.txt', "Znaleziono plik: {$pattern} dla klucza {$key}");
                    if (class_exists('MHI_Logger')) {
                        MHI_Logger::info("PAR - Znaleziono plik: {$pattern}");
                    }
                    $file_found = true;
                    break;
                }
            }

            if (!$file_found) {
                if ($file_info['required']) {
                    $patterns_str = implode(' lub ', $patterns);
                    $this->log('debug_error.txt', "BŁĄD: Wymagany plik {$patterns_str} nie istnieje!");
                    if (class_exists('MHI_Logger')) {
                        MHI_Logger::error("PAR - BŁĄD: Wymagany plik {$patterns_str} nie istnieje!");
                    }
                    return false;
                } else {
                    $patterns_str = implode(' lub ', $patterns);
                    $this->log('debug_load.txt', "Plik {$patterns_str} nie istnieje (opcjonalny)");
                }
            }
        }

        // Sprawdzamy, czy mamy przynajmniej plik lista_produktow.xml
        if (!isset($available_files['produkty'])) {
            $this->log('debug_error.txt', "BŁĄD: Brak wymaganego pliku lista_produktow.xml!");
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error("PAR - BŁĄD: Brak wymaganego pliku lista_produktow.xml!");
            }
            return false;
        }

        // Wczytujemy dane kategorii jeśli plik istnieje
        if (isset($available_files['kategorie'])) {
            $this->log('debug_load.txt', "Wczytywanie danych kategorii...");
            $categories_file = $available_files['kategorie'];

            $categories_xml = simplexml_load_file($categories_file, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($categories_xml !== false) {
                $this->log('debug_load.txt', "Wczytano plik kategorii: " . basename($categories_file));

                // Inicjalizacja map kategorii
                $this->category_map = [];
                $this->category_path_map = [];

                // Przetwarzanie kategorii głównych
                foreach ($categories_xml->category as $category) {
                    $category_id = (string) $category['id'];
                    $category_name = (string) $category['name'];
                    $this->category_map[$category_id] = $category_name;
                    $this->category_path_map[$category_id] = $category_name;

                    // Przetwarzanie podkategorii (nodes)
                    if (isset($category->node)) {
                        foreach ($category->node as $node) {
                            $node_id = (string) $node['id'];
                            $node_name = (string) $node['name'];
                            $this->category_map[$node_id] = $node_name;
                            $this->category_path_map[$node_id] = $category_name . ' > ' . $node_name;
                        }
                    }
                }

                $this->log('debug_load.txt', "Zakończono wczytywanie kategorii. Łącznie: " . count($this->category_map) . " kategorii.");
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info("PAR - Wczytano " . count($this->category_map) . " kategorii");
                }
            } else {
                $this->log('debug_error.txt', "OSTRZEŻENIE: Nie można wczytać pliku kategorii!");
            }
        }

        // Wczytujemy dane stanów magazynowych
        if (isset($available_files['stany'])) {
            $this->log('debug_load.txt', "Wczytywanie danych stanów magazynowych...");
            $stocks_file = $available_files['stany'];

            $stocks_xml = simplexml_load_file($stocks_file, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($stocks_xml !== false) {
                foreach ($stocks_xml->produkt as $stock) {
                    $kod = (string) $stock->kod;
                    if (!empty($kod)) {
                        $this->stock_data[$kod] = $stock;
                    }
                }
                $this->log('debug_load.txt', "Zakończono wczytywanie stanów magazynowych. Łącznie: " . count($this->stock_data) . " rekordów.");
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::info("PAR - Wczytano " . count($this->stock_data) . " stanów magazynowych");
                }
            } else {
                $this->log('debug_error.txt', "BŁĄD: Nie można wczytać pliku stanów magazynowych!");
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error("PAR - BŁĄD: Nie można wczytać pliku stanów magazynowych!");
                }
                return false;
            }
        } else {
            $this->log('debug_error.txt', "BŁĄD: Brak pliku stanów magazynowych!");
            return false;
        }

        // Wczytujemy dane produktów używając XMLReader dla dużych plików
        $this->log('debug_load.txt', "Wczytywanie danych produktów...");
        $products_file = $available_files['produkty'];

        $reader = new XMLReader();
        if (!$reader->open($products_file)) {
            $this->log('debug_error.txt', "BŁĄD: Nie można otworzyć pliku produktów!");
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error("PAR - BŁĄD: Nie można otworzyć pliku produktów!");
            }
            return false;
        }

        // Przesuwamy się do pierwszego elementu <product> (poprawka struktury Par)
        while ($reader->read() && $reader->name !== 'product')
            ;

        $product_count = 0;
        // Czytamy produkty jeden po drugim
        while ($reader->name === 'product') {
            $node = new SimpleXMLElement($reader->readOuterXml());
            $kod = (string) $node->kod;

            if (!empty($kod)) {
                $this->product_data[$kod] = $node;
                $product_count++;

                if ($product_count % 1000 === 0) {
                    $this->log('debug_load.txt', "Przetworzono {$product_count} produktów...");
                    if (class_exists('MHI_Logger')) {
                        MHI_Logger::info("PAR - Przetworzono {$product_count} produktów...");
                    }
                }
            }

            // Przejdź do następnego elementu <product> (poprawka struktury Par)
            $reader->next('product');
        }

        $reader->close();
        $this->product_count = $product_count;
        $this->log('debug_load.txt', "Zakończono wczytywanie produktów. Łącznie: {$product_count} produktów.");

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info("PAR - Zakończono wczytywanie danych XML. Produkty: {$this->product_count}, Stany: " . count($this->stock_data) . ", Kategorie: " . count($this->category_map));
        }

        $this->log('debug_summary.txt', "Zakończono wczytywanie danych XML. Produkty: {$this->product_count}, Stany: " . count($this->stock_data) . ", Kategorie: " . count($this->category_map));
        return true;
    }

    /**
     * Generuje plik XML do importu do WooCommerce.
     *
     * @return bool True jeśli plik został wygenerowany pomyślnie, false w przeciwnym razie.
     */
    public function generate_woocommerce_xml()
    {
        // Ustawienie limitu pamięci dla przetwarzania dużych plików XML
        ini_set('memory_limit', '1536M'); // Zwiększamy limit pamięci
        set_time_limit(1800); // 30 minut na wykonanie

        // Rozpocznij logowanie procesu
        $timestamp = date('Y-m-d H:i:s');
        $this->log('debug_generate.txt', "Rozpoczęto generowanie: {$timestamp}", false);
        $start_time = microtime(true);

        // Usuń stary plik XML jeśli istnieje (dla czystego generowania)
        $output_file = $this->target_dir . '/woocommerce_import_' . $this->name . '.xml';
        if (file_exists($output_file)) {
            unlink($output_file);
            $this->log('debug_generate.txt', "Usunięto stary plik XML: {$output_file}");
        }

        // Załaduj dane XML jeśli jeszcze nie zostały załadowane
        if (empty($this->product_data)) {
            $this->log('debug_generate.txt', "Brak danych produktów. Wczytuję dane XML.");
            if (!$this->load_xml_data()) {
                $this->log('debug_error.txt', "Nie udało się wczytać danych XML.");
                return false;
            }
        }

        $total_products = count($this->product_data);
        $this->log('debug_generate.txt', "Ilość produktów do przetworzenia: {$total_products} ({$timestamp})");

        // Upewnij się, że mamy rzeczywiste dane przed rozpoczęciem generowania
        if ($total_products == 0) {
            $this->log('debug_error.txt', "Brak produktów do przetworzenia! Anulowanie generowania.");
            return false;
        }

        try {
            // Parametry przetwarzania wsadowego
            $batch_size = 100; // Mniejszy rozmiar wsadu dla lepszego zarządzania pamięcią
            $batch_count = 0;
            $processed_count = 0;
            $total_batches = ceil($total_products / $batch_size);

            $this->log('debug_generate.txt', "Tworzenie pliku z nagłówkiem XML");

            // Tworzenie pliku z nagłówkiem XML
            file_put_contents($output_file, '<?xml version="1.0" encoding="UTF-8"?><products>', FILE_USE_INCLUDE_PATH);
            $this->log('debug_generate.txt', "Utworzono plik z nagłówkiem XML ({$timestamp})");

            // Kontener na aktualny wsad XML
            $xml_chunk = '';

            // Statystyki błędów
            $error_count = 0;
            $error_items = [];

            // Dla każdego produktu w naszej kolekcji
            foreach ($this->product_data as $kod => $product) {
                try {
                    // Pobierz dane stanu magazynowego dla tego produktu
                    $stock_data = isset($this->stock_data[$kod]) ? $this->stock_data[$kod] : null;

                    // Sprawdź, czy produkt powinien być przetworzony (czy ma dane stanów magazynowych)
                    if (!$stock_data) {
                        $this->log('debug_error.txt', "Brak danych stanu magazynowego dla produktu {$kod}, pomijam");
                        continue;
                    }

                    // Tworzenie elementu produktu jako string XML
                    $product_xml = $this->generate_product_xml($product, $kod, $stock_data);

                    // Dodajemy do aktualnej porcji XML
                    if (!empty($product_xml)) {
                        $xml_chunk .= $product_xml;
                        $processed_count++;
                    }

                    // Log tylko dla co 10 produktów (aby zmniejszyć ilość logów)
                    if ($processed_count % 10 === 0) {
                        $percent_done = round(($processed_count / $total_products) * 100, 1);
                        $this->log('debug_generate.txt', "Przetworzono {$processed_count} z {$total_products} produktów ({$percent_done}%)");
                    }

                    // Kiedy osiągniemy rozmiar porcji, zapisujemy do pliku
                    if ($processed_count % $batch_size === 0) {
                        $batch_count++;
                        file_put_contents($output_file, $xml_chunk, FILE_APPEND | FILE_USE_INCLUDE_PATH);
                        $xml_chunk = ''; // Resetujemy porcję

                        $this->log('debug_generate.txt', "Zapisano wsad {$batch_count} z {$total_batches} ({$processed_count} produktów)");

                        // Zwolnij pamięć
                        gc_collect_cycles();

                        // Aktualizacja czasu wykonania
                        $current_time = microtime(true);
                        $execution_time = round($current_time - $start_time, 2);
                        $this->log('debug_generate.txt', "Czas wykonania: {$execution_time}s");
                    }
                } catch (Exception $e) {
                    $error_count++;
                    $error_items[] = $kod;
                    $this->log('debug_error.txt', "Błąd przetwarzania produktu {$kod}: " . $e->getMessage());

                    // Kontynuuj z następnym produktem pomimo błędu
                    continue;
                }
            }

            // Zapisz pozostałe produkty
            if (!empty($xml_chunk)) {
                file_put_contents($output_file, $xml_chunk, FILE_APPEND | FILE_USE_INCLUDE_PATH);
                $remaining_count = $processed_count % $batch_size;
                $this->log('debug_generate.txt', "Zapisano pozostałe produkty: {$remaining_count} ({$timestamp})");
            }

            // Zamknij znacznik główny
            file_put_contents($output_file, '</products>', FILE_APPEND | FILE_USE_INCLUDE_PATH);

            // Podsumowanie procesu
            $end_time = microtime(true);
            $execution_time = round($end_time - $start_time, 2);

            $success_count = $processed_count - $error_count;
            $this->log('debug_generate.txt', "Zakończono generowanie. Łącznie produktów: {$success_count} ({$timestamp})");
            $this->log('debug_generate.txt', "Czas wykonania: {$execution_time}s, Błędów: {$error_count}");

            if ($error_count > 0) {
                $error_summary = "Podsumowanie błędów:\n";
                $error_summary .= "Łączna liczba błędów: {$error_count}\n";
                $error_summary .= "Produkty z błędami: " . implode(', ', array_slice($error_items, 0, 20)) .
                    (count($error_items) > 20 ? " ... (i " . (count($error_items) - 20) . " więcej)" : "");
                $this->log('debug_error_summary.txt', $error_summary, false);
            }

            // Sprawdź rozmiar wygenerowanego pliku
            if (file_exists($output_file)) {
                $file_size = filesize($output_file);
                $file_size_mb = round($file_size / (1024 * 1024), 2);
                $this->log('debug_generate.txt', "Rozmiar pliku: {$file_size} bajtów ({$file_size_mb} MB) ({$timestamp})");
            }

            return true;
        } catch (Exception $e) {
            $this->log('debug_error.txt', "Błąd podczas generowania pliku XML do importu WooCommerce: " .
                $e->getMessage() . " w linii " . $e->getLine() . "\n" . $e->getTraceAsString());

            return false;
        }
    }

    /**
     * Generuje XML dla pojedynczego produktu.
     *
     * @param SimpleXMLElement $product Dane produktu z XML Par.
     * @param string $kod Kod produktu.
     * @param SimpleXMLElement $stock_data Dane stanu magazynowego.
     * @return string Fragment XML z danymi produktu.
     * @throws Exception Jeśli wystąpi błąd podczas generowania XML produktu.
     */
    private function generate_product_xml($product, $kod, $stock_data)
    {
        // Tworzymy nowy dokument XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Tworzymy element główny
        $item = $dom->createElement('item');
        $dom->appendChild($item);

        // ID (unikalny identyfikator produktu)
        $this->add_xml_element($dom, $item, 'id', $kod);

        // SKU (kod produktu)
        $this->add_xml_element($dom, $item, 'sku', $kod);

        // Nazwa produktu - sprawdzamy różne możliwe pola
        $product_name = '';
        if (!empty($product->nazwa)) {
            $product_name = (string) $product->nazwa;
        } elseif (!empty($product->name)) {
            $product_name = (string) $product->name;
        } elseif (!empty($product->tytul)) {
            $product_name = (string) $product->tytul;
        } else {
            $product_name = 'Produkt ' . $kod;
        }
        $this->add_xml_element($dom, $item, 'name', $product_name);

        // Opis produktu - sprawdzamy różne możliwe pola
        $description = '';
        if (!empty($product->opis)) {
            $description = (string) $product->opis;
        } elseif (!empty($product->description)) {
            $description = (string) $product->description;
        }

        if (!empty($product->opis_dodatkowy)) {
            $description .= "\n\n" . (string) $product->opis_dodatkowy;
        } elseif (!empty($product->opis_pelny)) {
            $description .= "\n\n" . (string) $product->opis_pelny;
        }

        $this->add_xml_element($dom, $item, 'description', $description);

        // Krótki opis
        $short_description = mb_substr(strip_tags($description), 0, 200);
        if (mb_strlen(strip_tags($description)) > 200) {
            $short_description .= '...';
        }
        $this->add_xml_element($dom, $item, 'short_description', $short_description);

        // Cena regularna
        $cena_pln = (string) $stock_data->cena_katalogowa;
        $this->add_xml_element($dom, $item, 'regular_price', $cena_pln);

        // Cena po rabacie (jako cena sprzedaży)
        if (isset($stock_data->cena_po_rabacie) && !empty($stock_data->cena_po_rabacie)) {
            $sale_price = (string) $stock_data->cena_po_rabacie;
            $this->add_xml_element($dom, $item, 'sale_price', $sale_price);
        }

        // Stan magazynowy
        $stock_quantity = (int) $stock_data->stan_magazynowy;
        $this->add_xml_element($dom, $item, 'stock_quantity', $stock_quantity);

        // Status stanu magazynowego
        $stock_status = ($stock_quantity > 0) ? 'instock' : 'outofstock';
        $this->add_xml_element($dom, $item, 'stock_status', $stock_status);

        // Backorders - jeśli są oczekiwane dostawy
        if ((int) $stock_data->ilosc_dostawy > 0 || (int) $stock_data->ilosc_dostawy_niezatwierdzonej > 0) {
            $this->add_xml_element($dom, $item, 'backorders', 'notify');
            $backorder_text = "Oczekiwana dostawa: ";
            if ((int) $stock_data->ilosc_dostawy > 0) {
                $backorder_text .= (int) $stock_data->ilosc_dostawy . " szt.";
            } else {
                $backorder_text .= (int) $stock_data->ilosc_dostawy_niezatwierdzonej . " szt. (niezatwierdzona)";
            }
            $this->add_xml_element($dom, $item, 'backorder_text', $backorder_text);
        }

        // Kategorie
        // Kategorie - ulepszone mapowanie
        $categories_string = '';

        if (isset($product->kategorie) && isset($product->kategorie->kategoria)) {
            $categories = [];
            foreach ($product->kategorie->kategoria as $category) {
                $cat_id = (string) $category['id'];
                if (isset($this->category_path_map[$cat_id])) {
                    $categories[] = $this->category_path_map[$cat_id];
                } else {
                    $cat_name = (string) $category;
                    if (!empty($cat_name)) {
                        $categories[] = $cat_name;
                    }
                }
            }
            $categories_string = implode(' | ', $categories);
        }

        // Dodaj kategorie główne jeśli nie ma podkategorii
        if (empty($categories_string) && isset($product->kategoria_id)) {
            $cat_id = (string) $product->kategoria_id;
            if (isset($this->category_path_map[$cat_id])) {
                $categories_string = $this->category_path_map[$cat_id];
            }
        }

        if (!empty($categories_string)) {
            $this->add_xml_element($dom, $item, 'categories', $categories_string);
        } else {
            $this->add_xml_element($dom, $item, 'categories', 'Gadżety reklamowe');
        }

        // Dodajemy atrybuty
        $attributes_element = $dom->createElement('attributes');
        $item->appendChild($attributes_element);

        // Atrybut Kolor - sprawdzamy różne pola
        $color_value = '';
        if (!empty($product->kolor_podstawowy)) {
            $color_value = (string) $product->kolor_podstawowy;
        } elseif (!empty($product->kolor)) {
            $color_value = (string) $product->kolor;
        } elseif (!empty($product->color)) {
            $color_value = (string) $product->color;
        }

        if (!empty($color_value)) {
            $color_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($color_attribute);

            $this->add_xml_element($dom, $color_attribute, 'name', 'Kolor');
            $this->add_xml_element($dom, $color_attribute, 'value', $color_value);
            $this->add_xml_element($dom, $color_attribute, 'visible', '1');
            $this->add_xml_element($dom, $color_attribute, 'global', '1');
        }

        // Atrybut Wymiary
        if (!empty($product->wymiary)) {
            $dimensions_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($dimensions_attribute);

            $this->add_xml_element($dom, $dimensions_attribute, 'name', 'Wymiary');
            $this->add_xml_element($dom, $dimensions_attribute, 'value', (string) $product->wymiary);
            $this->add_xml_element($dom, $dimensions_attribute, 'visible', '1');
            $this->add_xml_element($dom, $dimensions_attribute, 'global', '1');
        }

        // Atrybut Materiał
        if (!empty($product->material_wykonania)) {
            $material_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($material_attribute);

            $this->add_xml_element($dom, $material_attribute, 'name', 'Materiał');
            $this->add_xml_element($dom, $material_attribute, 'value', (string) $product->material_wykonania);
            $this->add_xml_element($dom, $material_attribute, 'visible', '1');
            $this->add_xml_element($dom, $material_attribute, 'global', '1');
        }

        // Atrybut Eco (jeśli produkt jest eco)
        if (isset($product->eco) && (string) $product->eco === 'true') {
            $eco_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($eco_attribute);

            $this->add_xml_element($dom, $eco_attribute, 'name', 'Eco');
            $this->add_xml_element($dom, $eco_attribute, 'value', 'Tak');
            $this->add_xml_element($dom, $eco_attribute, 'visible', '1');
            $this->add_xml_element($dom, $eco_attribute, 'global', '1');
        }

        // Dodajemy techniki znakowania
        if (isset($product->techniki_zdobienia) && isset($product->techniki_zdobienia->technika)) {
            $marking_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($marking_attribute);

            $this->add_xml_element($dom, $marking_attribute, 'name', 'Techniki znakowania');

            $marking_methods = [];
            foreach ($product->techniki_zdobienia->technika as $technique) {
                $technique_name = (string) $technique->technika_zdobienia;
                $technique_place = (string) $technique->miejsce_zdobienia;
                $marking_methods[] = $technique_name . ' (' . $technique_place . ')';
            }

            $marking_value = implode(' | ', $marking_methods);
            $this->add_xml_element($dom, $marking_attribute, 'value', $marking_value);
            $this->add_xml_element($dom, $marking_attribute, 'visible', '1');
            $this->add_xml_element($dom, $marking_attribute, 'global', '0');
        }

        // Dodajemy obrazy produktu
        $images_element = $dom->createElement('images');
        $item->appendChild($images_element);

        // Bazowy URL dla zdjęć
        $base_image_url = 'https://www.par.com.pl/uploads/products/';

        // Główne zdjęcie (kod produktu)
        $main_image = $dom->createElement('image');
        $images_element->appendChild($main_image);

        $main_image_url = $base_image_url . $kod . '.jpg';
        $this->add_xml_element($dom, $main_image, 'src', $main_image_url);
        $this->add_xml_element($dom, $main_image, 'position', '0');

        // Dodatkowe zdjęcia (jeśli istnieją)
        for ($i = 2; $i <= 5; $i++) {
            $additional_image = $dom->createElement('image');
            $images_element->appendChild($additional_image);

            $additional_image_url = $base_image_url . $kod . '_' . $i . '.jpg';
            $this->add_xml_element($dom, $additional_image, 'src', $additional_image_url);
            $this->add_xml_element($dom, $additional_image, 'position', (string) ($i - 1));
        }

        // Pobieramy wygenerowany XML jako string
        $xml_string = $dom->saveXML($dom->documentElement);

        return $xml_string;
    }

    /**
     * Dodaje element XML z tekstem.
     *
     * @param DOMDocument $dom Dokument XML.
     * @param DOMElement $parent Element nadrzędny.
     * @param string $name Nazwa elementu.
     * @param string $value Wartość elementu.
     * @return DOMElement Utworzony element.
     */
    private function add_xml_element($dom, $parent, $name, $value)
    {
        // Oczyść wartość przed dodaniem
        $clean_value = $this->clean_html($value);

        $element = $dom->createElement($name);
        $text = $dom->createTextNode($clean_value);
        $element->appendChild($text);
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Czyści kod HTML dla bezpiecznego użycia w opisie
     *
     * @param string $html
     * @return string
     */
    private function clean_html($html)
    {
        // Usuń niebezpieczne tagi i skrypty
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html);
        $html = strip_tags($html, '<p><br><ul><li><ol><h1><h2><h3><h4><h5><strong><em><b><i><table><tr><td><th>');

        // Usuń niebezpieczne atrybuty
        $html = preg_replace('/\bon\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*\'[^\']*\'/i', '', $html);

        return $html;
    }
}

// Kod do samodzielnego uruchomienia poza WordPress
if (isset($argv[0]) && basename($argv[0]) == basename(__FILE__)) {
    echo "Rozpoczynam samodzielne uruchomienie generatora Par XML...\n";

    // Ustawienie podstawowej funkcji wp_mkdir_p dla działania poza WordPress
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($dir)
        {
            if (file_exists($dir))
                return true;
            return mkdir($dir, 0777, true);
        }
    }

    // Ustawienie podstawowej funkcji wp_upload_dir dla działania poza WordPress
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir()
        {
            return ['basedir' => dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-content/uploads'];
        }
    }

    // Stały katalog par
    $par_dir = '/Users/kemi/Local Sites/promoprint/app/public/wp-content/uploads/wholesale/par';

    // Sprawdź, czy katalog istnieje
    if (!file_exists($par_dir)) {
        echo "BŁĄD: Katalog $par_dir nie istnieje!\n";
        exit(1);
    }

    // Stwórz generator i uruchom przetwarzanie
    $generator = new MHI_Par_WC_XML_Generator($par_dir);

    echo "Rozpoczynam wczytywanie danych...\n";
    $load_result = $generator->load_xml_data();

    if ($load_result) {
        echo "Wczytanie danych zakończone sukcesem. Rozpoczynam generowanie XML...\n";
        $generate_result = $generator->generate_woocommerce_xml();

        if ($generate_result) {
            echo "Generowanie XML zakończone sukcesem!\n";
        } else {
            echo "BŁĄD: Nie udało się wygenerować pliku XML!\n";
        }
    } else {
        echo "BŁĄD: Nie udało się wczytać danych XML!\n";
    }

    echo "Zakończono działanie.\n";
}
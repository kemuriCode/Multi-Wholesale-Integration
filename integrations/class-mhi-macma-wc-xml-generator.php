<?php
/**
 * Klasa generatora plików XML importu WooCommerce dla hurtowni Macma.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Macma_WC_XML_Generator
 * 
 * Generuje pliki XML kompatybilne z WooCommerce na podstawie danych Macma.
 * Obsługuje duże pliki XML poprzez przetwarzanie strumieniowe i zarządzanie pamięcią.
 */
class MHI_Macma_WC_XML_Generator
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    private $name = 'macma';

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
     * Dane cenowe.
     *
     * @var array
     */
    private $price_data = [];

    /**
     * Dane kategorii.
     *
     * @var array
     */
    private $category_data = [];

    /**
     * Dane kolorów.
     *
     * @var array
     */
    private $color_data = [];

    /**
     * Dane materiałów.
     *
     * @var array
     */
    private $material_data = [];

    /**
     * Dane znakowania.
     *
     * @var array
     */
    private $marking_data = [];

    /**
     * Dane promocji.
     *
     * @var array
     */
    private $promo_data = [];

    /**
     * Drzewko kategorii
     * 
     * @var array
     */
    private $category_tree = [];

    /**
     * Mapa kategorii ID => Obiekt kategorii (SimpleXMLElement)
     * 
     * @var array
     */
    private $category_data_map_by_id = [];

    /**
     * Mapa kategorii ID => Pełna ścieżka kategorii (string)
     * 
     * @var array
     */
    private $category_path_map = [];

    /**
     * Flaga wskazująca, czy mamy dane testowe
     * 
     * @var boolean
     */
    private $is_sample_data = false;

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
            $this->source_dir = trailingslashit($upload_dir['basedir']) . "hurtownie/{$this->name}";
        } else {
            $this->source_dir = rtrim($source_dir, '/');
        }

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
        error_log('MHI_MACMA: ' . $message);
    }

    /**
     * Wczytuje dane z plików XML hurtowni.
     *
     * @return boolean True jeśli dane zostały wczytane pomyślnie, false w przeciwnym razie.
     */
    public function load_xml_data()
    {
        echo "Rozpoczynam wczytywanie danych XML z katalogu: {$this->source_dir}\n";

        $this->log('debug_summary.txt', 'Rozpoczęcie wczytywania danych XML', false);
        $this->log('debug_load.txt', 'Rozpoczęcie wczytywania danych XML', false);
        $this->log('debug_error.txt', 'Logi błędów podczas wczytywania danych XML', false);
        $this->log('debug_extract_code.txt', 'Logi ekstrakcji kodów produktów', false);

        // Lista plików, które będą przetwarzane
        $required_files = [
            'products' => [
                'pattern' => ['products.xml', 'oferta.xml'], // Dodajemy alternatywną nazwę pliku 'oferta.xml'
                'required' => true
            ],
            'stany_magazynowe' => [
                'pattern' => 'stany_magazynowe.xml',
                'required' => false
            ],
            'ceny' => [
                'pattern' => 'ceny.xml',
                'required' => false
            ],
            'categories' => [
                'pattern' => ['categories.xml', 'kategorie.xml'], // Dodajemy alternatywną nazwę pliku 'kategorie.xml'
                'required' => false
            ],
            'kolory' => [
                'pattern' => 'kolory.xml',
                'required' => false
            ],
            'materialy' => [
                'pattern' => 'materialy.xml',
                'required' => false
            ],
            'znakowanie' => [
                'pattern' => 'znakowanie.xml',
                'required' => false
            ],
            'promocje' => [
                'pattern' => 'promocje.xml',
                'required' => false
            ]
        ];

        // Sprawdzamy dostępne pliki XML
        $available_files = [];
        foreach ($required_files as $key => $file_info) {
            $patterns = is_array($file_info['pattern']) ? $file_info['pattern'] : [$file_info['pattern']];
            $file_found = false;

            foreach ($patterns as $pattern) {
                $file_path = $this->source_dir . '/' . $pattern;
                echo "Sprawdzam plik: {$file_path} - " . (file_exists($file_path) ? "ISTNIEJE" : "NIE ISTNIEJE") . "\n";

                if (file_exists($file_path)) {
                    $available_files[$key] = $file_path;
                    $this->log('debug_load.txt', "Znaleziono plik: {$pattern} dla klucza {$key}");
                    echo "Znaleziono plik: {$pattern} dla klucza {$key}\n";
                    $file_found = true;
                    break;
                }
            }

            if (!$file_found) {
                if ($file_info['required']) {
                    $patterns_str = implode(' lub ', $patterns);
                    $this->log('debug_error.txt', "BŁĄD: Wymagany plik {$patterns_str} nie istnieje!");
                    echo "BŁĄD: Wymagany plik {$patterns_str} nie istnieje!\n";
                    return false;
                } else {
                    $patterns_str = implode(' lub ', $patterns);
                    $this->log('debug_load.txt', "Plik {$patterns_str} nie istnieje (opcjonalny)");
                    echo "Plik {$patterns_str} nie istnieje (opcjonalny)\n";
                }
            }
        }

        // Sprawdzamy, czy mamy przynajmniej plik products.xml lub oferta.xml
        if (!isset($available_files['products'])) {
            $this->log('debug_error.txt', "BŁĄD: Brak wymaganego pliku products.xml lub oferta.xml!");
            echo "BŁĄD: Brak wymaganego pliku products.xml lub oferta.xml!\n";
            return false;
        }

        // Logowanie dostępnych plików
        $this->log('debug_load.txt', "Dostępne pliki: " . print_r($available_files, true));

        // Wczytujemy dane produktów (XML)
        $products_file = $available_files['products'];
        $this->log('debug_load.txt', "Wczytywanie pliku produktów: {$products_file}");
        $products_xml = simplexml_load_file($products_file, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($products_xml === false) {
            $this->log('debug_error.txt', "BŁĄD: Nie można wczytać pliku produktów ({$products_file})!");
            return false;
        }

        // Wczytujemy produkty do tablicy indeksowanej kodem produktu
        $this->log('debug_load.txt', "Przetwarzanie produktów z pliku: " . basename($products_file));

        // Sprawdzamy, czy mamy dane testowe czy właściwą strukturę
        $products_container = null;
        if (isset($products_xml->produkt)) {
            $this->log('debug_load.txt', "Wykryto strukturę pliku produktów: <produkt>");
            $products_container = $products_xml->produkt;
        } elseif (isset($products_xml->product)) {
            $this->log('debug_load.txt', "Wykryto strukturę pliku produktów: <product>");
            $products_container = $products_xml->product;
        } elseif (isset($products_xml->offer)) {
            $this->log('debug_load.txt', "Wykryto strukturę pliku produktów: <offer>");
            $products_container = $products_xml->offer;
        } elseif (isset($products_xml->oferta)) {
            $this->log('debug_load.txt', "Wykryto strukturę pliku produktów: <oferta>");
            $products_container = $products_xml->oferta;
        } else {
            // Sprawdzamy, czy dane są bezpośrednio w korzeniu
            $root_children = $products_xml->children();
            if (count($root_children) > 0) {
                $this->log('debug_load.txt', "Wykryto dane w korzeniu pliku produktów");
                $products_container = $root_children;
            } else {
                $this->log('debug_error.txt', "BŁĄD: Nieznana struktura pliku produktów!");
                return false;
            }
        }

        // Wczytanie produktów
        foreach ($products_container as $product) {
            $code = $this->extract_product_code($product);
            if ($code) {
                $this->product_data[$code] = $product;
                $this->product_count++;

                if ($this->product_count % 1000 === 0) {
                    $this->log('debug_load.txt', "Przetworzono {$this->product_count} produktów...");
                }
            }
        }

        $this->log('debug_load.txt', "Zakończono wczytywanie produktów. Łącznie: {$this->product_count} produktów.");

        // Wczytanie stanów magazynowych jeśli plik istnieje
        if (isset($available_files['stany_magazynowe'])) {
            $this->log('debug_load.txt', "Wczytywanie stanów magazynowych...");
            $stocks_file = $available_files['stany_magazynowe'];

            $stocks_xml = simplexml_load_file($stocks_file, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($stocks_xml !== false) {
                // Sprawdzenie, jaka jest struktura pliku ze stanami
                if (isset($stocks_xml->produkt)) {
                    $stocks_container = $stocks_xml->produkt;
                    $this->log('debug_load.txt', "Wykryto standardową strukturę pliku stanów magazynowych");
                } elseif (isset($stocks_xml->stock)) {
                    $stocks_container = $stocks_xml->stock;
                    $this->log('debug_load.txt', "Wykryto alternatywną strukturę pliku stanów magazynowych (stock)");
                } elseif (isset($stocks_xml->stan)) {
                    $stocks_container = $stocks_xml->stan;
                    $this->log('debug_load.txt', "Wykryto alternatywną strukturę pliku stanów magazynowych (stan)");
                } else {
                    // Sprawdzamy, czy plik zawiera pojedynczy korzeń - w takim przypadku musimy obsłużyć go inaczej
                    $this->log('debug_load.txt', "Nie wykryto standardowej struktury, sprawdzam czy plik ma postać tabeli");

                    // Sprawdzamy, czy dane są bezpośrednio w korzeniu (bez elementu opakowania)
                    $root_children = $stocks_xml->children();
                    if (count($root_children) > 0) {
                        $this->log('debug_load.txt', "Wykryto dane w korzeniu pliku, próbuję przetwarzać bezpośrednio");
                        $stocks_container = $root_children;
                    } else {
                        $this->log('debug_error.txt', "BŁĄD: Nieznana struktura pliku stanów magazynowych!");
                        $stocks_container = [];
                    }
                }

                // Wczytanie stanów magazynowych - indeksowanie po kodzie produktu
                $stocks_count = 0;
                foreach ($stocks_container as $stock) {
                    // Kod produktu w pliku stany_magazynowe.xml może być oznaczony jako code, code_full, id lub product_id
                    $code = null;
                    if (isset($stock->code_full) && !empty((string) $stock->code_full)) {
                        $code = (string) $stock->code_full;
                    } elseif (isset($stock->code) && !empty((string) $stock->code)) {
                        $code = (string) $stock->code;
                    } elseif (isset($stock->id) && !empty((string) $stock->id)) {
                        $code = (string) $stock->id;
                    } elseif (isset($stock->product_id) && !empty((string) $stock->product_id)) {
                        $code = (string) $stock->product_id;
                    } elseif (isset($stock['code']) && !empty((string) $stock['code'])) {
                        $code = (string) $stock['code'];
                    } elseif (isset($stock['id']) && !empty((string) $stock['id'])) {
                        $code = (string) $stock['id'];
                    }

                    if ($code) {
                        $this->log('debug_extract_code.txt', "Stany magazynowe - kod produktu: {$code}");
                        $this->stock_data[$code] = $stock;
                        $stocks_count++;
                    } else {
                        $this->log('debug_error.txt', "BŁĄD: Nie można znaleźć kodu produktu w pliku stanów magazynowych");
                    }
                }

                $this->log('debug_load.txt', "Zakończono wczytywanie stanów magazynowych. Łącznie: {$stocks_count} rekordów.");
            } else {
                $this->log('debug_error.txt', "OSTRZEŻENIE: Nie można wczytać pliku stanów magazynowych!");
            }
        }

        // Wczytanie danych cenowych jeśli plik istnieje
        if (isset($available_files['ceny'])) {
            $this->log('debug_load.txt', "Wczytywanie danych cenowych...");
            $prices_file = $available_files['ceny'];

            $prices_xml = simplexml_load_file($prices_file, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($prices_xml !== false) {
                // Sprawdzenie, jaka jest struktura pliku z cenami
                if (isset($prices_xml->produkt)) {
                    $prices_container = $prices_xml->produkt;
                    $this->log('debug_load.txt', "Wykryto standardową strukturę pliku cen (produkt)");
                } elseif (isset($prices_xml->price)) {
                    $prices_container = $prices_xml->price;
                    $this->log('debug_load.txt', "Wykryto alternatywną strukturę pliku cen (price)");
                } elseif (isset($prices_xml->cena)) {
                    $prices_container = $prices_xml->cena;
                    $this->log('debug_load.txt', "Wykryto alternatywną strukturę pliku cen (cena)");
                } else {
                    // Sprawdzamy, czy plik zawiera pojedynczy korzeń - w takim przypadku musimy obsłużyć go inaczej
                    $this->log('debug_load.txt', "Nie wykryto standardowej struktury, sprawdzam czy plik ma postać tabeli");

                    // Sprawdzamy, czy dane są bezpośrednio w korzeniu (bez elementu opakowania)
                    $root_children = $prices_xml->children();
                    if (count($root_children) > 0) {
                        $this->log('debug_load.txt', "Wykryto dane w korzeniu pliku, próbuję przetwarzać bezpośrednio");
                        $prices_container = $root_children;
                    } else {
                        $this->log('debug_error.txt', "BŁĄD: Nieznana struktura pliku z cenami!");
                        $prices_container = [];
                    }
                }

                // Wczytanie cen - indeksowanie po kodzie produktu
                $prices_count = 0;
                foreach ($prices_container as $price) {
                    // Kod produktu w pliku ceny.xml może być oznaczony jako code, code_full, id lub product_id
                    $code = null;
                    if (isset($price->code_full) && !empty((string) $price->code_full)) {
                        $code = (string) $price->code_full;
                    } elseif (isset($price->code) && !empty((string) $price->code)) {
                        $code = (string) $price->code;
                    } elseif (isset($price->id) && !empty((string) $price->id)) {
                        $code = (string) $price->id;
                    } elseif (isset($price->product_id) && !empty((string) $price->product_id)) {
                        $code = (string) $price->product_id;
                    } elseif (isset($price['code']) && !empty((string) $price['code'])) {
                        $code = (string) $price['code'];
                    } elseif (isset($price['id']) && !empty((string) $price['id'])) {
                        $code = (string) $price['id'];
                    }

                    if ($code) {
                        $this->log('debug_extract_code.txt', "Ceny - kod produktu: {$code}");
                        $this->price_data[$code] = $price;
                        $prices_count++;
                    } else {
                        $this->log('debug_error.txt', "BŁĄD: Nie można znaleźć kodu produktu w pliku cen");
                    }
                }

                $this->log('debug_load.txt', "Zakończono wczytywanie cen. Łącznie: {$prices_count} rekordów.");
            } else {
                $this->log('debug_error.txt', "OSTRZEŻENIE: Nie można wczytać pliku cen!");
            }
        }

        // Wczytanie danych znakowania jeśli plik istnieje
        if (isset($available_files['znakowanie'])) {
            $this->log('debug_load.txt', "Wczytywanie danych znakowania...");
            $marking_file = $available_files['znakowanie'];

            $marking_xml = simplexml_load_file($marking_file, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($marking_xml !== false) {
                // Sprawdzenie, jaka jest struktura pliku znakowania
                if (isset($marking_xml->markgroup)) {
                    $marking_container = $marking_xml->markgroup;
                    $this->log('debug_load.txt', "Wykryto standardową strukturę pliku znakowania (markgroup)");
                } elseif (isset($marking_xml->xml->markgroup)) {
                    $marking_container = $marking_xml->xml->markgroup;
                    $this->log('debug_load.txt', "Wykryto strukturę pliku znakowania z elementem xml (xml->markgroup)");
                } else {
                    // Próba znalezienia danych w innych strukturach
                    $this->log('debug_load.txt', "Nie wykryto standardowej struktury, sprawdzam alternatywne struktury");

                    // Sprawdzamy, czy dane są w innej strukturze
                    $marking_container = [];
                    if (isset($marking_xml->xml) && count($marking_xml->xml->children()) > 0) {
                        foreach ($marking_xml->xml->children() as $child_name => $child) {
                            if ($child_name == 'markgroup') {
                                $marking_container = $marking_xml->xml->children();
                                $this->log('debug_load.txt', "Wykryto strukturę z elementem xml i bezpośrednim dostępem do markgroup");
                                break;
                            }
                        }
                    }

                    if (empty($marking_container)) {
                        $this->log('debug_error.txt', "BŁĄD: Nieznana struktura pliku znakowania!");
                    }
                }

                // Wczytanie znakowania - indeksowanie po kodzie znakowania
                $marking_count = 0;
                foreach ($marking_container as $marking) {
                    // Kod znakowania może być w różnych miejscach struktury
                    $code = null;
                    $id = null;
                    $name = null;

                    // Sprawdzamy różne możliwe lokalizacje danych
                    if (isset($marking->baseinfo->id)) {
                        $id = (string) $marking->baseinfo->id;
                    } elseif (isset($marking->id)) {
                        $id = (string) $marking->id;
                    }

                    if (isset($marking->baseinfo->code)) {
                        $code = (string) $marking->baseinfo->code;
                    } elseif (isset($marking->code)) {
                        $code = (string) $marking->code;
                    }

                    if (isset($marking->baseinfo->name)) {
                        $name = (string) $marking->baseinfo->name;
                    } elseif (isset($marking->name)) {
                        $name = (string) $marking->name;
                    }

                    if ($id && ($code || $name)) {
                        $this->log('debug_extract_code.txt', "Znakowanie - id: {$id}, kod: {$code}, nazwa: {$name}");
                        // Zapisujemy dane znakowania pod ID
                        $this->marking_data[$id] = $marking;
                        // Dodatkowo zapisujemy mapowanie kod->ID i nazwa->ID dla łatwiejszego wyszukiwania
                        if ($code) {
                            $this->marking_data['code_' . $code] = $id;
                        }
                        if ($name) {
                            $this->marking_data['name_' . $name] = $id;
                        }
                        $marking_count++;
                    } else {
                        $this->log('debug_error.txt', "BŁĄD: Nie można znaleźć id, kodu lub nazwy znakowania");
                    }
                }

                $this->log('debug_load.txt', "Zakończono wczytywanie znakowania. Łącznie: {$marking_count} rekordów.");
            } else {
                $this->log('debug_error.txt', "OSTRZEŻENIE: Nie można wczytać pliku znakowania!");
            }
        }

        // Tutaj możesz dodać wczytywanie pozostałych plików (kategorie, kolory, materiały, znakowanie, promocje)
        // ...

        // Wczytanie danych kategorii jeśli plik istnieje
        if (isset($available_files['categories'])) {
            $this->log('debug_load.txt', "Wczytywanie danych kategorii...");
            $categories_file = $available_files['categories'];

            $categories_xml = simplexml_load_file($categories_file, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($categories_xml !== false) {
                $this->log('debug_load.txt', "Wczytano plik kategorii: " . basename($categories_file));

                // Sprawdzenie, jaka jest struktura pliku z kategoriami
                $categories_container = null;
                if (isset($categories_xml->category)) {
                    $categories_container = $categories_xml->category;
                    $this->log('debug_load.txt', "Wykryto standardową strukturę pliku kategorii (category)");
                } elseif (isset($categories_xml->xml) && isset($categories_xml->xml->category)) {
                    $categories_container = $categories_xml->xml->category;
                    $this->log('debug_load.txt', "Wykryto strukturę pliku kategorii z elementem xml (xml->category)");
                } else {
                    // Sprawdzamy, czy dane są bezpośrednio w korzeniu (bez elementu opakowania)
                    $root_children = $categories_xml->children();
                    if (count($root_children) > 0) {
                        $categories_container = $root_children;
                        $this->log('debug_load.txt', "Wykryto dane kategorii w korzeniu pliku");
                    } else {
                        $this->log('debug_error.txt', "BŁĄD: Nieznana struktura pliku kategorii!");
                        $categories_container = [];
                    }
                }

                // Wczytanie i przetworzenie kategorii
                $categories_count = 0;

                // Inicjalizacja mapy kategorii
                $this->category_data_map_by_id = [];
                $this->category_path_map = [];

                // Dla każdej kategorii głównej w pliku
                foreach ($categories_container as $category) {
                    $category_id = (string) $category->id;
                    $category_name = (string) $category->name;

                    // Zapisujemy kategorię do bezpośredniej mapy
                    $this->category_data[$category_id] = $category;
                    $categories_count++;

                    // Budujemy ścieżki kategorii rekursywnie
                    $this->build_category_paths_recursive($category, "", $this->category_data_map_by_id);
                }

                $this->log('debug_load.txt', "Zakończono wczytywanie kategorii. Łącznie: {$categories_count} kategorii głównych, " . count($this->category_path_map) . " wszystkich kategorii.");

                // Wypisanie kilku pierwszych ścieżek kategorii do logów (dla debugowania)
                $path_samples = array_slice($this->category_path_map, 0, 5, true);
                foreach ($path_samples as $id => $path) {
                    $this->log('debug_load.txt', "Przykładowa ścieżka kategorii [ID: {$id}]: {$path}");
                }
            } else {
                $this->log('debug_error.txt', "OSTRZEŻENIE: Nie można wczytać pliku kategorii!");
            }
        }

        $this->log('debug_summary.txt', "Zakończono wczytywanie danych XML. Produkty: {$this->product_count}, Stany: " . count($this->stock_data) . ", Ceny: " . count($this->price_data) . ", Znakowanie: " . count($this->marking_data) . ", Kategorie: " . count($this->category_path_map));
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
            foreach ($this->product_data as $code => $product) {
                try {
                    // Sprawdź czy to nie jest przykładowy produkt wygenerowany przez system
                    if ($code === 'M001' && $this->is_sample_data) {
                        $this->log('debug_generate.txt', "Pomijam przykładowy produkt M001");
                        continue;
                    }

                    // Tworzenie elementu produktu jako string XML
                    $product_xml = $this->generate_product_xml($product, $code);

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
                    $error_items[] = $code;
                    $this->log('debug_error.txt', "Błąd przetwarzania produktu {$code}: " . $e->getMessage());

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
                $this->log('debug_generate.txt', "Rozmiar pliku: {$file_size} bajtów ({$timestamp})");

                // Sprawdź, czy plik nie jest za mały (potencjalny problem)
                if ($file_size < 1000 && $total_products > 1 && $processed_count == 0) {
                    $this->log('debug_error.txt', "OSTRZEŻENIE: Wygenerowany plik jest podejrzanie mały ({$file_size} bajtów) dla {$total_products} produktów");

                    // Próba naprawy TYLKO jeśli naprawdę nie udało się wygenerować żadnych produktów
                    if ($processed_count == 0) {
                        $this->log('debug_generate.txt', "Próba powtórnego przetworzenia z naprawą plików...");

                        // Spróbuj ponownie załadować dane z naprawionych plików
                        $this->product_data = [];
                        if ($this->load_xml_data()) {
                            // Rekurencyjnie wywołaj tę metodę - ale tylko raz, żeby uniknąć pętli nieskończonej
                            static $retry_count = 0;
                            if ($retry_count < 1) {
                                $retry_count++;
                                return $this->generate_woocommerce_xml();
                            }
                        }
                    }
                }
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
     * @param SimpleXMLElement $product Dane produktu z XML Macma.
     * @param string $code Kod produktu.
     * @return string Fragment XML z danymi produktu.
     * @throws Exception Jeśli wystąpi błąd podczas generowania XML produktu.
     */
    private function generate_product_xml($product, $code)
    {
        // Tworzymy nowy dokument XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Tworzymy element główny
        $item = $dom->createElement('item');
        $dom->appendChild($item);

        // Dodajemy dane produktu do elementu
        $this->add_product_data($dom, $item, $product, $code);

        // Pobieramy wygenerowany XML jako string
        $xml_string = $dom->saveXML($dom->documentElement);

        return $xml_string;
    }

    /**
     * Dodaje dane produktu do elementu XML.
     * 
     * @param DOMDocument $dom Dokument XML
     * @param DOMElement $item Element XML, do którego zostaną dodane dane
     * @param SimpleXMLElement $product Element XML z danymi produktu
     * @param string $code Kod produktu (code_full)
     */
    private function add_product_data($dom, $item, $product, $code)
    {
        // Kod produktu - używamy code_full (nadrzędny) lub code_short jako fallback
        $code_short = '';

        // Próbujemy pobrać code_short z różnych miejsc
        if (isset($product->code_short)) {
            $code_short = (string) $product->code_short;
        } elseif (isset($product->symbol) && empty($code_short)) {
            $code_short = (string) $product->symbol;
        } elseif (isset($product->sap_code) && empty($code_short)) {
            $code_short = (string) $product->sap_code;
        } elseif (isset($product->reference) && empty($code_short)) {
            $code_short = (string) $product->reference;
        }

        // Jeśli nie mamy code_short, a mamy kod pełny, wyciągamy tylko część numeryczną
        if (empty($code_short) && !empty($code) && preg_match('/([A-Za-z0-9]+)/', $code, $matches)) {
            $code_short = $matches[1];
        }

        $code_full = (string) $product->code_full;
        if (empty($code_full)) {
            $code_full = $code; // Używamy kodu z extractProduct jeśli nie ma code_full
        }

        $code_to_use = !empty($code_full) ? $code_full : $code_short;

        // Upewnijmy się, że mamy jakiś kod do użycia
        if (empty($code_to_use)) {
            $code_to_use = $code;
        }

        // Debug info - sprawdzenie czy kod produktu jest prawidłowy
        $this->log('debug_extract_code.txt', "Generowanie XML dla produktu z kodem: {$code_to_use}");

        // Sprawdzanie, czy mamy dane stanu magazynowego dla tego produktu
        $stock_data = null;
        if (isset($this->stock_data[$code_to_use])) {
            $stock_data = $this->stock_data[$code_to_use];
        } elseif (!empty($code_short) && isset($this->stock_data[$code_short])) {
            $stock_data = $this->stock_data[$code_short];
            $this->log('debug_extract_code.txt', "Znaleziono dane stanów magazynowych po code_short: {$code_short}");
        }

        // Sprawdzanie, czy mamy dane cenowe dla tego produktu
        $price_data = null;
        if (isset($this->price_data[$code_to_use])) {
            $price_data = $this->price_data[$code_to_use];
            $this->log('debug_extract_code.txt', "Znaleziono dane cenowe dla kodu: {$code_to_use}");
        } else {
            $this->log('debug_error.txt', "Nie znaleziono danych cenowych dla kodu: {$code_to_use}");

            // Próba znalezienia danych cenowych po code_short jeśli nie znaleziono po code_full
            if (!empty($code_short) && isset($this->price_data[$code_short])) {
                $price_data = $this->price_data[$code_short];
                $this->log('debug_extract_code.txt', "Znaleziono dane cenowe dla code_short: {$code_short}");
            }
        }

        // ID (unikalny identyfikator produktu)
        $this->add_xml_element($dom, $item, 'id', $code_to_use);

        // SKU (kod produktu)
        $this->add_xml_element($dom, $item, 'sku', $code_to_use);

        // Nazwa produktu (jeśli jest dostępna, inaczej używamy kodu)
        $product_name = '';
        if (isset($product->name)) {
            $product_name = (string) $product->name;
        } elseif (isset($product->title)) {
            $product_name = (string) $product->title;
        } elseif (isset($product->product_name)) {
            $product_name = (string) $product->product_name;
        } elseif (isset($product->baseinfo) && isset($product->baseinfo->name)) {
            // Sprawdzamy strukturę baseinfo, która może występować w pliku oferta.xml
            $product_name = (string) $product->baseinfo->name;
        }

        if (empty($product_name)) {
            $product_name = "Produkt " . $code_to_use;
            $this->log('debug_error.txt', "Produkt {$code_to_use} nie ma nazwy, używam domyślnej.");
        }
        $this->add_xml_element($dom, $item, 'name', $product_name);

        // Opis produktu (jeśli jest dostępny)
        $description = '';
        if (isset($product->description)) {
            $description = (string) $product->description;
        } elseif (isset($product->details)) {
            $description = (string) $product->details;
        } elseif (isset($product->long_description)) {
            $description = (string) $product->long_description;
        } elseif (isset($product->baseinfo) && isset($product->baseinfo->description)) {
            // Sprawdzamy opis w strukturze baseinfo
            $description = (string) $product->baseinfo->description;
        } elseif (isset($product->baseinfo) && isset($product->baseinfo->long_description)) {
            $description = (string) $product->baseinfo->long_description;
        }

        if (!empty($description)) {
            // Czyścimy opis z potencjalnie niebezpiecznych tagów
            $description = $this->clean_html($description);
            $this->add_xml_element($dom, $item, 'description', $description);
        } else {
            $this->add_xml_element($dom, $item, 'description', $product_name);
        }

        // Krótki opis (jeśli jest dostępny, inaczej używamy fragmentu pełnego opisu)
        $short_description = '';
        if (isset($product->short_description)) {
            $short_description = (string) $product->short_description;
        } elseif (isset($product->intro)) {
            $short_description = (string) $product->intro;
        } elseif (isset($product->summary)) {
            $short_description = (string) $product->summary;
        } elseif (isset($product->baseinfo) && isset($product->baseinfo->short_description)) {
            $short_description = (string) $product->baseinfo->short_description;
        }

        if (!empty($short_description)) {
            $short_description = $this->clean_html($short_description);
            $this->add_xml_element($dom, $item, 'short_description', $short_description);
        } elseif (!empty($description)) {
            // Używamy pierwszych 200 znaków opisu jako krótki opis
            $short_text = mb_substr(strip_tags($description), 0, 200);
            if (mb_strlen(strip_tags($description)) > 200) {
                $short_text .= '...';
            }
            $this->add_xml_element($dom, $item, 'short_description', $short_text);
        } else {
            $this->add_xml_element($dom, $item, 'short_description', $product_name);
        }

        // Cena
        $price_value = '';
        if ($price_data && isset($price_data->price) && !empty($price_data->price)) {
            // Konwersja przecinka na kropkę dla formatu liczbowego
            $price = (string) $price_data->price;
            $price_value = str_replace(',', '.', $price);
        } else {
            // Próbujemy pobrać cenę bezpośrednio z produktu
            if (isset($product->price) && !empty($product->price)) {
                $price = (string) $product->price;
                $price_value = str_replace(',', '.', $price);
            } elseif (isset($product->net_price) && !empty($product->net_price)) {
                $price = (string) $product->net_price;
                $price_value = str_replace(',', '.', $price);
            } elseif (isset($product->retail_price) && !empty($product->retail_price)) {
                $price = (string) $product->retail_price;
                $price_value = str_replace(',', '.', $price);
            } elseif (isset($product->baseinfo) && isset($product->baseinfo->price) && !empty($product->baseinfo->price)) {
                $price = (string) $product->baseinfo->price;
                $price_value = str_replace(',', '.', $price);
            }
        }

        if (!empty($price_value)) {
            $this->add_xml_element($dom, $item, 'regular_price', $price_value);
            $this->log('debug_extract_code.txt', "Dodano cenę: {$price_value} dla produktu: {$code_to_use}");

            // Cena promocyjna (jeśli jest)
            if ($price_data && !empty($price_data->additional_offer) && $price_data->additional_offer == '1') {
                $sale_price = floatval($price_value) * 0.9; // 10% zniżki jako przykład
                $this->add_xml_element($dom, $item, 'sale_price', number_format($sale_price, 2, '.', ''));
            } elseif (isset($product->promo_price) && !empty($product->promo_price)) {
                $promo_price = str_replace(',', '.', (string) $product->promo_price);
                $this->add_xml_element($dom, $item, 'sale_price', $promo_price);
            } elseif (isset($product->sale_price) && !empty($product->sale_price)) {
                $sale_price = str_replace(',', '.', (string) $product->sale_price);
                $this->add_xml_element($dom, $item, 'sale_price', $sale_price);
            } elseif (isset($product->baseinfo) && isset($product->baseinfo->promo_price) && !empty($product->baseinfo->promo_price)) {
                $promo_price = str_replace(',', '.', (string) $product->baseinfo->promo_price);
                $this->add_xml_element($dom, $item, 'sale_price', $promo_price);
            }
        } else {
            // Jeśli nie mamy danych cenowych, ustawiamy domyślną cenę
            $this->add_xml_element($dom, $item, 'regular_price', '0.00');
            $this->log('debug_error.txt', "Produkt {$code_to_use} nie ma danych cenowych.");
        }

        // Stany magazynowe i dostępność
        $stock_quantity = 0;
        $stock_status = 'outofstock';

        if ($stock_data) {
            // Stan magazynowy - quantity_24h to stan dostępny od ręki
            if (isset($stock_data->quantity_24h)) {
                $stock_quantity = (int) $stock_data->quantity_24h;
            } elseif (isset($stock_data->quantity)) {
                $stock_quantity = (int) $stock_data->quantity;
            } elseif (isset($stock_data->stock)) {
                $stock_quantity = (int) $stock_data->stock;
            } elseif (isset($stock_data->available_quantity)) {
                $stock_quantity = (int) $stock_data->available_quantity;
            }

            $this->add_xml_element($dom, $item, 'stock_quantity', $stock_quantity);

            // Ustawiamy dostępność na podstawie stanu magazynowego
            if ($stock_quantity > 0) {
                $stock_status = 'instock';
            } else {
                // Sprawdzamy dostępność z dłuższym terminem
                $backorder_quantity = 0;
                if (isset($stock_data->quantity_37days)) {
                    $backorder_quantity = (int) $stock_data->quantity_37days;
                } elseif (isset($stock_data->quantity_future)) {
                    $backorder_quantity = (int) $stock_data->quantity_future;
                } elseif (isset($stock_data->incoming_quantity)) {
                    $backorder_quantity = (int) $stock_data->incoming_quantity;
                }

                if ($backorder_quantity > 0) {
                    $stock_status = 'onbackorder';
                    $backorder_text = "Dostępne w ciągu 3-7 dni: " . $backorder_quantity . " szt.";
                    $this->add_xml_element($dom, $item, 'backorders', 'notify');
                    $this->add_xml_element($dom, $item, 'backorder_text', $backorder_text);
                }
            }
        } else {
            // Próbujemy pobrać stan magazynowy z samego produktu
            if (isset($product->stock_quantity) && (int) $product->stock_quantity > 0) {
                $stock_quantity = (int) $product->stock_quantity;
                $stock_status = 'instock';
            } elseif (isset($product->available_quantity) && (int) $product->available_quantity > 0) {
                $stock_quantity = (int) $product->available_quantity;
                $stock_status = 'instock';
            } elseif (isset($product->in_stock) && $product->in_stock == 'true') {
                $stock_quantity = 10; // Domyślna wartość gdy wiemy tylko, że produkt jest dostępny
                $stock_status = 'instock';
            } elseif (isset($product->baseinfo) && isset($product->baseinfo->stock_quantity) && (int) $product->baseinfo->stock_quantity > 0) {
                $stock_quantity = (int) $product->baseinfo->stock_quantity;
                $stock_status = 'instock';
            }

            if ($stock_quantity > 0) {
                $this->add_xml_element($dom, $item, 'stock_quantity', $stock_quantity);
            }
        }

        // Ustaw ostateczny status dostępności
        $this->add_xml_element($dom, $item, 'stock_status', $stock_status);

        // Kategorie
        $categories = [];
        $categories_string = "";
        $category_id = null;

        // Najpierw sprawdzamy strukturę <categories> charakterystyczną dla pliku oferta.xml
        if (isset($product->categories) && isset($product->categories->category)) {
            $has_category = false;
            $category_path = '';

            // Iterujemy przez wszystkie kategorie i budujemy ścieżkę
            foreach ($product->categories->category as $cat) {
                if (isset($cat->id) && isset($cat->name)) {
                    $category_name = (string) $cat->name;
                    $cat_id = (string) $cat->id;

                    // Dodajemy kategorię główną
                    if (empty($category_path)) {
                        $category_path = $category_name;
                    } else {
                        $category_path .= ' > ' . $category_name;
                    }

                    // Sprawdzamy podkategorie
                    if (isset($cat->subcategory)) {
                        foreach ($cat->subcategory as $subcat) {
                            if (isset($subcat->id) && isset($subcat->name)) {
                                $subcat_name = (string) $subcat->name;
                                $category_path .= ' > ' . $subcat_name;
                            }
                        }
                    }

                    $has_category = true;
                    break; // Używamy pierwszej kategorii (możemy rozszerzyć, aby obsługiwać wiele kategorii)
                }
            }

            if ($has_category) {
                $categories_string = $category_path;
                $this->log('debug_extract_code.txt', "Znaleziono kategorie w strukturze <categories>: {$categories_string}");
            }
        }

        // Jeśli nie znaleźliśmy kategorii w strukturze <categories>, szukamy w innych miejscach
        if (empty($categories_string)) {
            // Próbujemy znaleźć identyfikator kategorii produktu w innych miejscach
            if (isset($product->category_id) && !empty($product->category_id)) {
                $category_id = (string) $product->category_id;
                $this->log('debug_extract_code.txt', "Znaleziono category_id: {$category_id} dla produktu: {$code_to_use}");
            } elseif (isset($product->category) && !empty($product->category) && ctype_digit((string) $product->category)) {
                $category_id = (string) $product->category;
                $this->log('debug_extract_code.txt', "Znaleziono category jako ID: {$category_id} dla produktu: {$code_to_use}");
            } elseif (isset($product->primary_category_id) && !empty($product->primary_category_id)) {
                $category_id = (string) $product->primary_category_id;
                $this->log('debug_extract_code.txt', "Znaleziono primary_category_id: {$category_id} dla produktu: {$code_to_use}");
            } elseif (isset($product->baseinfo) && isset($product->baseinfo->category_id) && !empty($product->baseinfo->category_id)) {
                $category_id = (string) $product->baseinfo->category_id;
                $this->log('debug_extract_code.txt', "Znaleziono category_id w baseinfo: {$category_id} dla produktu: {$code_to_use}");
            }

            // Jeśli znaleźliśmy ID kategorii, sprawdzamy czy mamy dla niej ścieżkę
            if ($category_id && isset($this->category_path_map[$category_id])) {
                $categories_string = $this->category_path_map[$category_id];
                $this->log('debug_extract_code.txt', "Znaleziono ścieżkę kategorii dla ID {$category_id}: {$categories_string}");
            } else {
                // Jeśli nie znaleźliśmy ID lub nie mamy dla niego ścieżki, próbujemy użyć nazwy kategorii
                if (isset($product->category_name) && !empty($product->category_name)) {
                    $category_name = (string) $product->category_name;
                    $categories[] = $category_name;
                    $this->log('debug_extract_code.txt', "Używam category_name: {$category_name} dla produktu: {$code_to_use}");
                } elseif (isset($product->category) && !empty($product->category) && !ctype_digit((string) $product->category)) {
                    $category_name = (string) $product->category;
                    $categories[] = $category_name;
                    $this->log('debug_extract_code.txt', "Używam category jako nazwę: {$category_name} dla produktu: {$code_to_use}");
                } elseif (isset($product->primary_category) && !empty($product->primary_category)) {
                    $category_name = (string) $product->primary_category;
                    $categories[] = $category_name;
                    $this->log('debug_extract_code.txt', "Używam primary_category: {$category_name} dla produktu: {$code_to_use}");
                } elseif (isset($product->baseinfo) && isset($product->baseinfo->category_name) && !empty($product->baseinfo->category_name)) {
                    $category_name = (string) $product->baseinfo->category_name;
                    $categories[] = $category_name;
                    $this->log('debug_extract_code.txt', "Używam category_name z baseinfo: {$category_name} dla produktu: {$code_to_use}");
                }

                // Sprawdzamy, czy możemy znaleźć kategorię po nazwie
                if (!empty($categories) && empty($categories_string)) {
                    // Szukamy kategorii po nazwie
                    foreach ($this->category_data_map_by_id as $cat_id => $cat_obj) {
                        $cat_name = isset($cat_obj->name) ? (string) $cat_obj->name : '';
                        if (!empty($cat_name) && $cat_name == $categories[0]) {
                            $categories_string = $this->category_path_map[$cat_id];
                            $this->log('debug_extract_code.txt', "Znaleziono ścieżkę kategorii dla nazwy {$categories[0]}: {$categories_string}");
                            break;
                        }
                    }

                    // Jeśli nadal nie znaleźliśmy, używamy prostej listy kategorii
                    if (empty($categories_string)) {
                        $categories_string = implode(' > ', $categories);
                    }
                }
            }
        }

        // Dodajemy dane kategorii do XML
        if (!empty($categories_string)) {
            $this->add_xml_element($dom, $item, 'categories', $categories_string);
        } else {
            $this->add_xml_element($dom, $item, 'categories', 'Bez kategorii');
        }

        // Dodajemy atrybuty
        $attributes_element = $dom->createElement('attributes');
        $item->appendChild($attributes_element);

        // Atrybut koloru (jeśli jest dostępny)
        $color = '';
        if (isset($product->color) && !empty($product->color)) {
            $color = (string) $product->color;
        } elseif (isset($product->colour) && !empty($product->colour)) {
            $color = (string) $product->colour;
        } elseif (isset($product->available_colors) && !empty($product->available_colors)) {
            $color = (string) $product->available_colors;
        }

        // Sprawdzamy, czy mamy dane koloru w bazie kolorów
        if (!empty($color) && isset($this->color_data[$color])) {
            $color_data = $this->color_data[$color];
            if (isset($color_data->parsed_name)) {
                $color = (string) $color_data->parsed_name;
            }
        }

        if (!empty($color)) {
            $color_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($color_attribute);

            $this->add_xml_element($dom, $color_attribute, 'name', 'Kolor');
            $this->add_xml_element($dom, $color_attribute, 'value', $color);
            $this->add_xml_element($dom, $color_attribute, 'visible', '1');
            $this->add_xml_element($dom, $color_attribute, 'global', '1');
        }

        // Atrybut rozmiaru/wymiarów (jeśli jest dostępny)
        $size = '';
        // Sprawdzamy dane z pliku ceny.xml
        if ($price_data && !empty($price_data->size)) {
            $size = (string) $price_data->size;
        }
        // Alternatywnie sprawdzamy dane z pliku produktów
        if (empty($size)) {
            if (isset($product->size) && !empty($product->size)) {
                $size = (string) $product->size;
            } elseif (isset($product->dimensions) && !empty($product->dimensions)) {
                $size = (string) $product->dimensions;
            } elseif (isset($product->measurements) && !empty($product->measurements)) {
                $size = (string) $product->measurements;
            }
        }

        if (!empty($size)) {
            $size_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($size_attribute);

            $this->add_xml_element($dom, $size_attribute, 'name', 'Wymiary');
            $this->add_xml_element($dom, $size_attribute, 'value', $size);
            $this->add_xml_element($dom, $size_attribute, 'visible', '1');
            $this->add_xml_element($dom, $size_attribute, 'global', '1');
        }

        // Atrybut materiału (jeśli jest dostępny)
        $material = '';
        if (isset($product->material) && !empty($product->material)) {
            $material = (string) $product->material;
        } elseif (isset($product->materials) && !empty($product->materials)) {
            $material = (string) $product->materials;
        }

        // Sprawdzamy, czy mamy dane materiału w bazie materiałów
        if (!empty($material) && isset($this->material_data[$material])) {
            $material_data = $this->material_data[$material];
            if (isset($material_data->parsed_name)) {
                $material = (string) $material_data->parsed_name;
            }
        }

        if (!empty($material)) {
            $material_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($material_attribute);

            $this->add_xml_element($dom, $material_attribute, 'name', 'Materiał');
            $this->add_xml_element($dom, $material_attribute, 'value', $material);
            $this->add_xml_element($dom, $material_attribute, 'visible', '1');
            $this->add_xml_element($dom, $material_attribute, 'global', '1');
        }

        // Atrybut marki (jeśli jest dostępny)
        $brand = '';
        // Sprawdzamy dane z pliku ceny.xml
        if ($price_data && !empty($price_data->brand)) {
            $brand = (string) $price_data->brand;
        }
        // Alternatywnie sprawdzamy dane z pliku produktów
        if (empty($brand)) {
            if (isset($product->brand) && !empty($product->brand)) {
                $brand = (string) $product->brand;
            } elseif (isset($product->manufacturer) && !empty($product->manufacturer)) {
                $brand = (string) $product->manufacturer;
            }
        }

        if (!empty($brand)) {
            $brand_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($brand_attribute);

            $this->add_xml_element($dom, $brand_attribute, 'name', 'Marka');
            $this->add_xml_element($dom, $brand_attribute, 'value', $brand);
            $this->add_xml_element($dom, $brand_attribute, 'visible', '1');
            $this->add_xml_element($dom, $brand_attribute, 'global', '1');
        }

        // Pojemność (jeśli jest dostępna)
        $capacity = '';
        // Sprawdzamy dane z pliku ceny.xml
        if ($price_data && !empty($price_data->capacity)) {
            $capacity = (string) $price_data->capacity;
        }
        // Alternatywnie sprawdzamy dane z pliku produktów
        if (empty($capacity)) {
            if (isset($product->capacity) && !empty($product->capacity)) {
                $capacity = (string) $product->capacity;
            } elseif (isset($product->volume) && !empty($product->volume)) {
                $capacity = (string) $product->volume;
            }
        }

        if (!empty($capacity)) {
            $capacity_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($capacity_attribute);

            $this->add_xml_element($dom, $capacity_attribute, 'name', 'Pojemność');
            $this->add_xml_element($dom, $capacity_attribute, 'value', $capacity);
            $this->add_xml_element($dom, $capacity_attribute, 'visible', '1');
            $this->add_xml_element($dom, $capacity_attribute, 'global', '1');
        }

        // Dodajemy dane znakowania
        if (isset($product->markgroups) && isset($product->markgroups->markgroup)) {
            $marking_attribute = $dom->createElement('attribute');
            $attributes_element->appendChild($marking_attribute);

            $this->add_xml_element($dom, $marking_attribute, 'name', 'Znakowanie');

            // Zbieramy wszystkie dostępne metody znakowania
            $marking_methods = [];
            foreach ($product->markgroups->markgroup as $markgroup) {
                $marking_id = (string) $markgroup->id;
                $marking_name = (string) $markgroup->name;
                $marking_code = (string) $markgroup->code;
                $marking_size = isset($markgroup->marking_size) ? (string) $markgroup->marking_size : '';

                // Próba znalezienia danych znakowania w naszej bazie
                $marking_info = null;
                if (isset($this->marking_data[$marking_id])) {
                    $marking_info = $this->marking_data[$marking_id];
                } elseif (isset($this->marking_data['code_' . $marking_code])) {
                    $marking_id_from_code = $this->marking_data['code_' . $marking_code];
                    if (isset($this->marking_data[$marking_id_from_code])) {
                        $marking_info = $this->marking_data[$marking_id_from_code];
                    }
                } elseif (isset($this->marking_data['name_' . $marking_name])) {
                    $marking_id_from_name = $this->marking_data['name_' . $marking_name];
                    if (isset($this->marking_data[$marking_id_from_name])) {
                        $marking_info = $this->marking_data[$marking_id_from_name];
                    }
                }

                // Tworzymy opis znakowania
                $marking_description = $marking_name;
                if (!empty($marking_size)) {
                    $marking_description .= " ({$marking_size})";
                }

                // Jeśli znaleźliśmy dodatkowe informacje, dodajemy je
                if ($marking_info) {
                    // Dodajemy dodatkowe informacje, jeśli są dostępne
                    if (isset($marking_info->baseinfo->short_name) && !empty($marking_info->baseinfo->short_name)) {
                        $short_name = (string) $marking_info->baseinfo->short_name;
                        $marking_description .= " - {$short_name}";
                    }

                    // Dodajemy informacje o kolorach
                    if (isset($marking_info->baseinfo->colors_max) && !empty($marking_info->baseinfo->colors_max)) {
                        $colors_max = (string) $marking_info->baseinfo->colors_max;
                        $marking_description .= ", max {$colors_max} kolory";
                    }

                    // Dodajemy informacje o full color
                    if (isset($marking_info->baseinfo->full_color) && $marking_info->baseinfo->full_color == '1') {
                        $marking_description .= ", full color";
                    }
                }

                $marking_methods[] = $marking_description;
            }

            // Łączymy wszystkie metody znakowania
            $marking_value = implode(' | ', $marking_methods);
            $this->add_xml_element($dom, $marking_attribute, 'value', $marking_value);
            $this->add_xml_element($dom, $marking_attribute, 'visible', '1');
            $this->add_xml_element($dom, $marking_attribute, 'global', '0');
        }

        // Dodajemy obrazy produktu (jeśli są dostępne)
        $images_element = $dom->createElement('images');
        $item->appendChild($images_element);

        // Generujemy ścieżki obrazów na podstawie ID produktu
        // Domyślna ścieżka do obrazów Macma
        $image_base_url = 'https://www.macma.pl/UserFiles/Catalog/Products/';

        // Obrazy główne i dodatkowe
        if (!empty($code_short)) {
            // Dodanie głównego obrazu
            $main_image = $dom->createElement('image');
            $images_element->appendChild($main_image);

            $main_image_url = $image_base_url . $code_short . '/main.jpg';
            $this->add_xml_element($dom, $main_image, 'src', $main_image_url);
            $this->add_xml_element($dom, $main_image, 'position', '0');

            // Dodanie dodatkowych obrazów jeśli istnieją
            for ($i = 1; $i <= 5; $i++) { // Zakładamy maks. 5 dodatkowych obrazów
                $additional_image = $dom->createElement('image');
                $images_element->appendChild($additional_image);

                $additional_image_url = $image_base_url . $code_short . '/' . $i . '.jpg';
                $this->add_xml_element($dom, $additional_image, 'src', $additional_image_url);
                $this->add_xml_element($dom, $additional_image, 'position', $i);
            }
        } else {
            // Próba pobrania obrazów bezpośrednio z produktu
            $image_urls = [];

            if (isset($product->images) && isset($product->images->image)) {
                foreach ($product->images->image as $image) {
                    if (isset($image->url)) {
                        $image_urls[] = (string) $image->url;
                    } else if (isset($image->src)) {
                        $image_urls[] = (string) $image->src;
                    } else {
                        $image_url = (string) $image;
                        if (!empty($image_url)) {
                            $image_urls[] = $image_url;
                        }
                    }
                }
            } elseif (isset($product->main_image) && !empty($product->main_image)) {
                $image_urls[] = (string) $product->main_image;
            }

            // Dodajemy znalezione obrazy
            foreach ($image_urls as $index => $image_url) {
                $image_element = $dom->createElement('image');
                $images_element->appendChild($image_element);

                $this->add_xml_element($dom, $image_element, 'src', $image_url);
                $this->add_xml_element($dom, $image_element, 'position', $index);
            }
        }
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
        $element = $dom->createElement($name);
        $text = $dom->createTextNode($value);
        $element->appendChild($text);
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Extracting product code from XML
     *
     * @param SimpleXMLElement $product
     * @return string
     */
    private function extract_product_code($product)
    {
        $code_sources = [
            'code_full' => isset($product->code_full) ? (string) $product->code_full : null,
            'code' => isset($product->code) ? (string) $product->code : null,
            'sku' => isset($product->sku) ? (string) $product->sku : null,
            'id' => isset($product->id) ? (string) $product->id : null,
            'symbol' => isset($product->symbol) ? (string) $product->symbol : null,
            'baseinfo->code_full' => isset($product->baseinfo->code_full) ? (string) $product->baseinfo->code_full : null,
            'baseinfo->code' => isset($product->baseinfo->code) ? (string) $product->baseinfo->code : null,
            'reference' => isset($product->reference) ? (string) $product->reference : null,
            'ref_number' => isset($product->ref_number) ? (string) $product->ref_number : null,
            'attributes()->code' => isset($product->attributes()->code) ? (string) $product->attributes()->code : null,
            'attributes()->id' => isset($product->attributes()->id) ? (string) $product->attributes()->id : null,
        ];

        $final_code = '';
        $source_found = 'none';

        foreach ($code_sources as $source_name => $code_value) {
            if (!empty($code_value)) {
                $final_code = $code_value;
                $source_found = $source_name;
                break;
            }
        }

        // Usunięto zbyt szczegółowe logowanie $product->asXML() dla każdego produktu, aby uniknąć ogromnych logów.
        // Zamiast tego logujemy tylko, jeśli kod jest pusty lub logujemy okresowo.

        if (empty($final_code)) {
            // Logowanie tylko fragmentu XML w przypadku braku kodu
            $product_xml_snippet = substr($product->asXML(), 0, 250);
            $this->log('debug_extract_code.txt', "ERROR: Could not extract a definitive product code/SKU. Source found: {$source_found}. Product XML snippet: {$product_xml_snippet}...");

            // Jako ostatnią deskę ratunku, próbujemy pobrać pełny XML produktu i wyszukać identyfikator
            $full_xml = $product->asXML();
            if (preg_match('/<code(?:_full)?[^>]*>(.*?)<\/code(?:_full)?>/', $full_xml, $matches)) {
                $final_code = trim($matches[1]);
                $source_found = 'regex_match';
                $this->log('debug_extract_code.txt', "Extracted product code via regex: '{$final_code}'");
            }
        } else {
            // Logowanie co np. 200-tny produkt, aby nie zapełniać logów, lub jeśli jest to specyficzny kod do debugowania.
            static $extract_log_counter = 0;
            if ($extract_log_counter % 200 === 0) {
                $this->log('debug_extract_code.txt', "Extracted product code: '{$final_code}' (Source: {$source_found})");
            }
            $extract_log_counter++;
        }

        return $final_code;
    }

    /**
     * Czyści kod HTML dla bezpiecznego użycia w opisie
     *
     * @param string $html
        $code = '';
        if (isset($product->baseinfo->code_full)) {
            $code = (string) $product->baseinfo->code_full;
        } elseif (isset($product->baseinfo->code)) {
            $code = (string) $product->baseinfo->code;
        } elseif (isset($product->code)) {
            $code = (string) $product->code;
        } elseif (isset($product->sku)) {
            $code = (string) $product->sku;
        } elseif (isset($product->id)) {
            $code = (string) $product->id;
        } elseif (isset($product->attributes()->code)) {
            $code = (string) $product->attributes()->code;
        }

        return $code;
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

    /**
     * Naprawia fragment XML
     *
     * @param string $fragment
     * @return string
     */
    private function repair_xml_fragment($fragment)
    {
        // Logujemy początek naprawy
        $this->log('debug_load.txt', "Rozpoczynam naprawę struktury XML");

        // 1. Znajdź i napraw główny tag root (sprawdź czy istnieje zamykający)
        // Sprawdź czy istnieje otwierający tag root
        if (preg_match('/<([a-zA-Z0-9_]+)[^>]*>/', $fragment, $root_matches)) {
            $root_tag = $root_matches[1];
            $this->log('debug_load.txt', "Wykryty root tag: {$root_tag}");

            // Sprawdź czy istnieje zamykający tag root
            if (!preg_match("/<\/{$root_tag}>$/", $fragment)) {
                $fragment .= "</{$root_tag}>";
                $this->log('debug_load.txt', "Dodano brakujący zamykający tag root: {$root_tag}");
            }
        }

        // 2. Popraw niezamknięte tagi <plac> bez zamknięcia (specyficzny dla Macma)
        $count = 0;
        $fragment = preg_replace('/<plac([^>]*)>([^<]*?)(?!<\/plac>)(?=<)/is', '<plac$1>$2</plac>', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Naprawiono {$count} niezamkniętych tagów <plac>");
        }

        // 3. Popraw tag <marking_details> bez zamknięcia (specyficzny dla Macma)
        $count = 0;
        $fragment = preg_replace('/<marking_details([^>]*)>([^<]*?)(?!<\/marking_details>)(?=<)/is', '<marking_details$1>$2</marking_details>', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Naprawiono {$count} niezamkniętych tagów <marking_details>");
        }

        // 4. Ogólna naprawa niezamkniętych tagów
        $tags_to_check = ['product', 'item', 'baseinfo', 'categories', 'category', 'color', 'materials', 'attributes', 'images', 'stock', 'price', 'material', 'marking', 'mark'];
        foreach ($tags_to_check as $tag) {
            $count = 0;
            $pattern = "/<{$tag}([^>]*)>(?:[^<]*?)(?!<\/{$tag}>)(?=<(?!\/{$tag}>))/is";
            $replacement = "<{$tag}$1>\n</{$tag}>";
            $fragment = preg_replace($pattern, $replacement, $fragment, -1, $count);
            if ($count > 0) {
                $this->log('debug_load.txt', "Naprawiono {$count} niezamkniętych tagów <{$tag}>");
            }
        }

        // 5. Napraw inne potencjalne problemy z pustymi tagami
        $count = 0;
        $fragment = preg_replace('/<([a-zA-Z0-9_]+)([^>]*)\/>/is', '<$1$2></$1>', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Przekształcono {$count} pustych tagów");
        }

        // 6. Zamień niedozwolone znaki w XML na encje
        $fragment = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;)/', '&amp;', $fragment);

        // 7. Usuń znaki kontrolne poza dozwolonymi w XML
        $fragment = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $fragment);

        // 8. Wykryj i napraw niezakończone fragmenty CDATA
        $count = 0;
        $fragment = preg_replace('/\<\!\[CDATA\[(.*?)(?!\]\]\>)(?=\<)/is', '<![CDATA[$1]]>', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Naprawiono {$count} niezakończonych sekcji CDATA");
        }

        // 9. Napraw znaki cudzysłowu w atrybutach (gdy brakuje cudzysłowu zamykającego)
        $count = 0;
        $fragment = preg_replace('/(\w+)=([^"\'][^\s>]*)/is', '$1="$2"', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Naprawiono {$count} atrybutów bez cudzysłowów");
        }

        // 10. Upewnij się, że plik ma prawidłowy nagłówek XML
        if (strpos($fragment, '<?xml') === false) {
            $fragment = '<?xml version="1.0" encoding="UTF-8"?>' . $fragment;
            $this->log('debug_load.txt', "Dodano brakujący nagłówek XML");
        }

        // 11. Zamień zduplikowane tagi zamykające
        $count = 0;
        $fragment = preg_replace('/<\/([a-zA-Z0-9_]+)>(\s*)<\/\1>/is', '</$1>', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Usunięto {$count} zduplikowanych tagów zamykających");
        }

        // 12. Usuń nieprawidłowe atrybuty w tagach
        $fragment = preg_replace('/(\w+)=\"[^"]*[<>][^"]*\"/is', '$1=""', $fragment);

        // 13. Usuń zbędne spacje i tabulatory między tagami
        $fragment = preg_replace('/>\s+</s', '><', $fragment);

        // 14. Popraw zagnieżdżone tagi XML z tym samym identyfikatorem
        $count = 0;
        $fragment = preg_replace('/<(\w+)([^>]*)>(\s*)<\1([^>]*)>/is', '<$1$2$4>', $fragment, -1, $count);
        if ($count > 0) {
            $this->log('debug_load.txt', "Naprawiono {$count} zagnieżdżonych tagów o tym samym identyfikatorze");
        }

        // 15. Napraw problematyczne tagi Macma
        $problematic_tags = ['code_full', 'code_short', 'intro', 'code', 'quantity', 'hex', 'parent_id', 'subcategory'];
        foreach ($problematic_tags as $tag) {
            $count = 0;
            $pattern = "/<{$tag}([^>]*)>([^<]*?)(?!<\/{$tag}>)(?=<)/is";
            $replacement = "<{$tag}$1>$2</{$tag}>";
            $fragment = preg_replace($pattern, $replacement, $fragment, -1, $count);
            if ($count > 0) {
                $this->log('debug_load.txt', "Naprawiono {$count} niezamkniętych tagów <{$tag}>");
            }
        }

        $this->log('debug_load.txt', "Zakończono naprawę struktury XML");

        return $fragment;
    }

    /**
     * Rekursywnie buduje mapę ścieżek kategorii.
     *
     * @param SimpleXMLElement $category_node Węzeł kategorii.
     * @param string $current_path Bieżąca ścieżka.
     * @param array $all_categories_map Mapa wszystkich kategorii ID => obiekt SimpleXML.
     */
    private function build_category_paths_recursive($category_node, $current_path, &$all_categories_map)
    {
        $category_id = (string) $category_node->id;
        // Używamy bardziej elastycznego pobierania nazwy kategorii
        $category_name = isset($category_node->name) ? (string) $category_node->name :
            (isset($category_node->n) ? (string) $category_node->n : 'Brak Nazwy');

        $new_path = $current_path ? $current_path . ' > ' . $category_name : $category_name;
        $this->category_path_map[$category_id] = $new_path;
        $all_categories_map[$category_id] = $category_node; // Zapisujemy też do płaskiej mapy

        if (isset($category_node->subcategories) && isset($category_node->subcategories->category)) {
            foreach ($category_node->subcategories->category as $subcategory_node) {
                $this->build_category_paths_recursive($subcategory_node, $new_path, $all_categories_map);
            }
        }
    }
}

// Kod do samodzielnego uruchomienia poza WordPress
if (isset($argv[0]) && basename($argv[0]) == basename(__FILE__)) {
    echo "Rozpoczynam samodzielne uruchomienie generatora Macma XML...\n";

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

    // Stały katalog macma
    $macma_dir = '/Users/kemi/Local Sites/promoprint/app/public/wp-content/uploads/hurtownie/macma';

    // Sprawdź, czy katalog istnieje
    if (!file_exists($macma_dir)) {
        echo "BŁĄD: Katalog $macma_dir nie istnieje!\n";
        exit(1);
    }

    // Stwórz generator i uruchom przetwarzanie
    $generator = new MHI_Macma_WC_XML_Generator($macma_dir);

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
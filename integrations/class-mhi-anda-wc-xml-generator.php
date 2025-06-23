<?php
/**
 * Klasa generatora plików XML importu WooCommerce dla hurtowni ANDA.
 * ROZSZERZONA WERSJA - wykorzystuje wszystkie dostępne dane z analizy.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_ANDA_WC_XML_Generator
 * 
 * Generuje pliki XML kompatybilne z WooCommerce na podstawie danych ANDA.
 * Wersja rozszerzona o warianty, technologie druku i dodatkowe atrybuty.
 */
class MHI_ANDA_WC_XML_Generator
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    private $name = 'anda';

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
     * Dane produktów z products.xml.
     *
     * @var array
     */
    private $products_data = [];

    /**
     * Dane cenowe z prices.xml.
     *
     * @var array
     */
    private $prices_data = [];

    /**
     * Dane stanów magazynowych z inventories.xml.
     *
     * @var array
     */
    private $inventories_data = [];

    /**
     * Dane kategorii z categories.xml.
     *
     * @var array
     */
    private $categories_data = [];

    /**
     * Dane znakowania z labeling.xml.
     *
     * @var array
     */
    private $labeling_data = [];

    /**
     * Dane technologii druku z printingprices.xml.
     *
     * @var array
     */
    private $printing_prices_data = [];

    /**
     * Mapa kategorii ANDA.
     *
     * @var array
     */
    private $category_map = [];

    /**
     * Płaska struktura kategorii dla łatwiejszego wyszukiwania.
     *
     * @var array
     */
    private $flat_categories = [];

    /**
     * Dostępne technologie druku.
     *
     * @var array
     */
    private $printing_technologies = [
        'C1' => 'Druk ceramiczny podstawowy',
        'C2' => 'Druk ceramiczny średni',
        'C3' => 'Druk ceramiczny premium',
        'DG1' => 'Druk cyfrowy - wkładka papierowa S',
        'DG2' => 'Druk cyfrowy - wkładka papierowa M',
        'DG3' => 'Druk cyfrowy - wkładka papierowa L',
        'DO1' => 'Epoxy doming - mały (do 25cm²)',
        'DO2' => 'Epoxy doming - średni (25-50cm²)',
        'DO3' => 'Epoxy doming - duży (50-100cm²)',
        'DO4' => 'Epoxy doming - bardzo duży (100-200cm²)',
        'DO5' => 'Epoxy doming - ekstra duży (200-350cm²)',
        'DTA1' => 'Transfer cyfrowy A - mały (do 50cm²)',
        'DTA2' => 'Transfer cyfrowy A - średni (50-150cm²)',
        'DTA3' => 'Transfer cyfrowy A - duży (150-300cm²)',
        'DTA4' => 'Transfer cyfrowy A - bardzo duży (300-600cm²)',
        'DTB1' => 'Transfer cyfrowy B - mały (do 50cm²)',
        'DTB2' => 'Transfer cyfrowy B - średni (50-150cm²)',
        'DTB3' => 'Transfer cyfrowy B - duży (150-300cm²)',
        'DTB4' => 'Transfer cyfrowy B - bardzo duży (300-600cm²)',
        'DTC1' => 'Transfer cyfrowy C - mały (do 50cm²)',
        'DTC2' => 'Transfer cyfrowy C - średni (50-150cm²)',
        'DTC3' => 'Transfer cyfrowy C - duży (150-300cm²)',
        'DTD1' => 'Transfer cyfrowy D - mały (do 50cm²)',
        'DTD2' => 'Transfer cyfrowy D - średni (50-150cm²)',
        'DTD3' => 'Transfer cyfrowy D - duży (150-300cm²)',
        'DTA1-HS' => 'Transfer cyfrowy + szycie opaski (czapki)'
    ];

    /**
     * Konstruktor.
     */
    public function __construct()
    {
        // Katalogi dla plików ANDA
        $this->source_dir = wp_upload_dir()['basedir'] . '/wholesale/anda';
        $this->target_dir = wp_upload_dir()['basedir'] . '/wholesale/anda'; // Zmieniono z xml_files na główny folder

        // Inicjalizacja map kategorii i technologii druku
        $this->init_category_map();
    }

    /**
     * Inicjalizuje mapę kategorii ANDA.
     */
    private function init_category_map()
    {
        $this->category_map = [
            '14000' => 'Do żywności i napojów',
            '14010' => 'Kubki, filiżanki i szklanki',
            '14020' => 'Akcesoria Coffee & Tea',
            '14030' => 'Pudełka śniadaniowe i pojemniki',
            '14040' => 'Akcesoria kuchenne',
            '14050' => 'Magnesy na lodówkę',
            '14060' => 'Torby termiczne',
            '14070' => 'Butelki',
            '14080' => 'Otwieracze do butelek',
            '14090' => 'Przybory do wina i akcesoria barowe',
            '4000' => 'Torby i podróże',
            '4010' => 'Torby zakupowe i plażowe',
            '4020' => 'Torby ze sznurkiem',
            '4030' => 'Plecaki i torby na ramię',
            '4040' => 'Torby podróżne',
            '4050' => 'Torby na laptopa i dokumenty',
            '4060' => 'Akcesoria podróżne',
            '4070' => 'Portfele i etui na karty',
            '4080' => 'Parasole',
            '3000' => 'Technologia i telefon',
            '3010' => 'Ładowarki USB',
            '3020' => 'Akcesoria do telefonów i tabletów',
            '3030' => 'Muzyka i audio',
            '3040' => 'USB pendrive',
            '3050' => 'Akcesoria komputerowe',
            '3060' => 'Zegary i zegarki',
            '3070' => 'Stacje pogodowe',
            '3080' => 'Power banki',
            '3090' => 'Bezprzewodowe ładowanie',
            '1000' => 'Do pisania',
            '1010' => 'Długopisy',
            '1020' => 'Rysiki do ekranów dotykowych',
            '1030' => 'Zestawy piśmiennicze',
            '1040' => 'Ołówki',
            '1050' => 'Zakreślacze',
            '1060' => 'Gumki i ostrzynki',
            '1070' => 'Futerały',
            '2000' => 'Biuro i praca',
            '2010' => 'Notesy i notatniki',
            '2020' => 'Podkładki',
            '2030' => 'Teczki na dokumenty',
            '2040' => 'Wizytowniki',
            '2050' => 'Ściereczki',
            '2060' => 'Smycze i uchwyty',
            '2070' => 'Przybory stołowe',
            '2080' => 'Linijki i zakładki',
            '2090' => 'Kalkulatory',
            '2100' => 'Szklane trofea',
            '8000' => 'Sport i wypoczynek',
            '8040' => 'Akcesoria plażowe',
            '8050' => 'Nadmuchiwane',
            '8070' => 'Outdoor i piesze wycieczki',
            '8100' => 'Akcesoria Events & Sport',
            '8110' => 'Produkty dla zwierząt',
            '8120' => 'Ogrodnictwo',
            '9000' => 'Witalność & pielęgnacja',
            '9010' => 'Lusterka i grzebienie',
            '9020' => 'Zestawy do Manicure & Makeup',
            '9030' => 'Kosmetyczki',
            '9040' => 'Akcesoria łazienkowe',
            '9050' => 'Ręczniki, szlafroki',
            '9060' => 'Produkty medyczne',
            '9070' => 'Cukierki',
            '9080' => 'Antystresy',
            '9090' => 'Dom & odpoczynek',
            '9100' => 'Produkty higieniczne i antybakteryjne',
            '6000' => 'Dzieci i zabawki',
            '6010' => 'Skarbonki',
            '6020' => 'Zabawki',
            '6030' => 'Pluszaki',
            '6040' => 'Puzzle',
            '6050' => 'Rysowania i kolorowanie',
            '7000' => 'Do kluczy i narzędzia',
            '7010' => 'Breloki',
            '7020' => 'Monety do wózków zakupowych',
            '7030' => 'Latarki',
            '7040' => 'Noże i narzędzia',
            '7050' => 'Miary',
            '7060' => 'Akcesoria samochodowe',
            '7070' => 'Produkty odblaskowe',
            '7080' => 'Zapalniczki',
            '10000' => 'Tekstylia i akcesoria',
            '8010' => 'Okulary przeciwsłoneczne',
            '8030' => 'Klapki',
            '10010' => 'Nakrycia głowy',
            '10020' => 'Szaliki i rękawiczki',
            '10030' => 'Przeciwdeszczowe',
            '10040' => 'Bluzy i kurtki',
            '10050' => 'T-shirty',
            '10060' => 'Koszule i koszulki Polo',
            '10070' => 'Odzież sportowa',
            '10080' => 'Plakietki',
            '10090' => 'Akcesoria modowe',
            '15000' => 'CreaPack',
            '15010' => 'CreaBox',
            '15020' => 'CreaSleeve'
        ];
    }

    /**
     * Generuje KOMPLETNY plik XML ANDA dla WooCommerce z wszystkimi danymi.
     *
     * @return array Status operacji
     */
    public function generate_all_xml_files()
    {
        try {
            error_log('MHI ANDA: Rozpoczynam KOMPLETNE generowanie pliku XML z wszystkimi danymi');

            $this->log_memory_usage('Początek generowania');

            // Sprawdź czy katalog źródłowy istnieje
            if (!is_dir($this->source_dir)) {
                throw new Exception("Katalog źródłowy nie istnieje: {$this->source_dir}");
            }

            // Utwórz katalog docelowy jeśli nie istnieje
            if (!is_dir($this->target_dir)) {
                wp_mkdir_p($this->target_dir);
            }

            // Wczytaj dane z plików XML
            $this->load_all_data();
            $this->log_memory_usage('Po wczytaniu danych');

            // Generuj pliki ze wszystkimi danymi
            $products_result = $this->generate_products_xml();
            $categories_result = $this->generate_categories_xml();
            $printing_result = $this->generate_printing_services_xml();

            $this->log_memory_usage('Po wygenerowaniu wszystkich plików');

            // Zwolnij pamięć
            unset($this->products_data, $this->categories_data, $this->printing_prices_data);
            gc_collect_cycles();

            return [
                'success' => true,
                'message' => 'Wszystkie pliki XML zostały wygenerowane z kompletnymi danymi ANDA',
                'results' => [
                    'products' => $products_result,
                    'categories' => $categories_result,
                    'printing_prices' => $printing_result
                ]
            ];

        } catch (Exception $e) {
            error_log('MHI ANDA ERROR: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Błąd: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Loguje aktualnie używaną pamięć.
     *
     * @param string $stage Etap procesu
     */
    private function log_memory_usage($stage)
    {
        $memory_used = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_used_mb = round($memory_used / 1024 / 1024, 2);
        $memory_peak_mb = round($memory_peak / 1024 / 1024, 2);

        error_log("MHI ANDA MEMORY: $stage - Używana: {$memory_used_mb}MB, Szczyt: {$memory_peak_mb}MB");
    }

    /**
     * Wczytuje wszystkie dane z plików XML ANDA.
     */
    private function load_all_data()
    {
        error_log('MHI ANDA: Wczytuję rozszerzone dane z plików XML');

        // Zwiększ limit pamięci dla wszystkich produktów
        ini_set('memory_limit', '1024M');

        // Wczytaj wszystkie produkty (bez ograniczeń)
        $this->products_data = $this->load_xml_file('products.xml', 'product', 0);
        error_log('MHI ANDA: Wczytano ' . count($this->products_data) . ' produktów');

        // Wczytaj wszystkie ceny
        $this->prices_data = $this->load_xml_file('prices.xml', 'price', 0);
        error_log('MHI ANDA: Wczytano ' . count($this->prices_data) . ' cen');

        // Wczytaj wszystkie stany magazynowe - element to 'record' nie 'inventory'
        $this->inventories_data = $this->load_xml_file('inventories.xml', 'record', 0);
        error_log('MHI ANDA: Wczytano ' . count($this->inventories_data) . ' rekordów magazynowych');

        // Wczytaj wszystkie kategorie
        $this->categories_data = $this->load_categories_xml();
        error_log('MHI ANDA: Wczytano ' . count($this->categories_data) . ' kategorii');

        // Wczytaj wszystkie dane znakowania  
        $this->labeling_data = $this->load_xml_file('labeling.xml', 'labelingInfo', 0);
        error_log('MHI ANDA: Wczytano ' . count($this->labeling_data) . ' danych znakowania');

        // Wczytaj ceny technologii druku
        $this->printing_prices_data = $this->load_printing_prices_xml();
        error_log('MHI ANDA: Wczytano ' . count($this->printing_prices_data) . ' technologii druku');

        // Stwórz płaską strukturę kategorii
        $this->build_flat_categories();
    }

    /**
     * Wczytuje dane kategorii z categories.xml.
     *
     * @return array Dane kategorii
     */
    private function load_categories_xml()
    {
        $file_path = $this->source_dir . '/categories.xml';

        if (!file_exists($file_path)) {
            error_log("MHI ANDA: Plik categories.xml nie istnieje");
            return [];
        }

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($file_path);

            if ($xml === false) {
                throw new Exception("Nie można wczytać pliku categories.xml");
            }

            // Konwertuj XML na tablicę z pełną hierarchią
            $categories_array = [];
            if (isset($xml->category)) {
                foreach ($xml->category as $category) {
                    $categories_array[] = $this->parse_category_recursive($category);
                }
            }

            error_log("MHI ANDA: Wczytano " . count($categories_array) . " głównych kategorii");
            return $categories_array;

        } catch (Exception $e) {
            error_log("MHI ANDA ERROR: Błąd podczas wczytywania categories.xml: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Rekurencyjnie parsuje kategorie z XML.
     *
     * @param SimpleXMLElement $category
     * @return array
     */
    private function parse_category_recursive($category)
    {
        $result = [
            'id' => (string) $category->externalId,
            'name' => (string) $category->name,
            'children' => []
        ];

        if (isset($category->children->category)) {
            foreach ($category->children->category as $child) {
                $result['children'][] = $this->parse_category_recursive($child);
            }
        }

        return $result;
    }

    /**
     * Buduje płaską strukturę kategorii dla łatwiejszego wyszukiwania.
     */
    private function build_flat_categories()
    {
        $this->flat_categories = [];

        if (!empty($this->categories_data)) {
            foreach ($this->categories_data as $category) {
                $this->flatten_categories_new($category);
            }
        }
    }

    /**
     * Nowa metoda do spłaszczania hierarchii kategorii.
     *
     * @param array $category
     * @param string $parent_path
     */
    private function flatten_categories_new($category, $parent_path = '')
    {
        if (!isset($category['id']))
            return;

        $category_id = $category['id'];
        $category_name = $category['name'];
        $full_path = $parent_path ? $parent_path . ' > ' . $category_name : $category_name;

        $this->flat_categories[$category_id] = [
            'id' => $category_id,
            'name' => $category_name,
            'path' => $full_path,
            'parent_path' => $parent_path
        ];

        // Recursively process children
        if (!empty($category['children'])) {
            foreach ($category['children'] as $child) {
                $this->flatten_categories_new($child, $full_path);
            }
        }
    }

    /**
     * Rekurencyjnie spłaszcza strukturę kategorii.
     *
     * @param array $categories
     * @param string $parent_path
     */
    private function flatten_categories($categories, $parent_path = '')
    {
        // Jeśli to pojedyncza kategoria, zamień na tablicę
        if (isset($categories['externalId'])) {
            $categories = [$categories];
        }

        foreach ($categories as $category) {
            if (!isset($category['externalId']))
                continue;

            $category_id = (string) $category['externalId'];
            $category_name = isset($category['n']) ? (string) $category['n'] : '';

            $full_path = $parent_path ? $parent_path . ' > ' . $category_name : $category_name;

            $this->flat_categories[$category_id] = [
                'id' => $category_id,
                'name' => $category_name,
                'path' => $full_path,
                'parent_path' => $parent_path
            ];

            // Recursively process children
            if (!empty($category['children']['category'])) {
                $this->flatten_categories($category['children']['category'], $full_path);
            }
        }
    }

    /**
     * Wczytuje dane technologii druku z printingprices.xml.
     *
     * @return array Dane technologii druku
     */
    private function load_printing_prices_xml()
    {
        $file_path = $this->source_dir . '/printingprices.xml';

        if (!file_exists($file_path)) {
            error_log("MHI ANDA: Plik printingprices.xml nie istnieje");
            return [];
        }

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($file_path);

            if ($xml === false) {
                throw new Exception("Nie można wczytać pliku printingprices.xml");
            }

            $data = [];
            foreach ($xml->prices->price as $price) {
                $tech_code = (string) $price->TechnologyCode;
                $tech_name = (string) $price->TechnologyName;

                $ranges = [];
                foreach ($price->ranges->range as $range) {
                    $ranges[] = [
                        'colors' => (string) $range->NumberOfColours,
                        'qty_from' => (int) $range->QuantityFrom,
                        'qty_to' => (int) $range->QuantityTo,
                        'unit_price' => (float) $range->UnitPrice,
                        'setup_cost' => (float) $range->SetupCost,
                        'size_from' => isset($range->SizeFrom) ? (float) $range->SizeFrom : null,
                        'size_to' => isset($range->SizeTo) ? (float) $range->SizeTo : null
                    ];
                }

                $data[$tech_code] = [
                    'code' => $tech_code,
                    'name' => $tech_name,
                    'ranges' => $ranges
                ];
            }

            return $data;

        } catch (Exception $e) {
            error_log("MHI ANDA ERROR: Błąd podczas wczytywania printingprices.xml: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Wczytuje dane z pliku XML z ograniczeniem pamięci.
     *
     * @param string $filename Nazwa pliku
     * @param string $element_name Nazwa elementu do wczytania
     * @param int $limit Maksymalna liczba elementów do wczytania
     * @return array Dane
     */
    private function load_xml_file($filename, $element_name = 'product', $limit = 1000)
    {
        $file_path = $this->source_dir . '/' . $filename;

        if (!file_exists($file_path)) {
            error_log("MHI ANDA: Plik $filename nie istnieje");
            return [];
        }

        try {
            $data = [];
            $count = 0;

            // Użyj XMLReader dla oszczędnego wczytywania
            $reader = new XMLReader();
            if (!$reader->open($file_path)) {
                throw new Exception("Nie można otworzyć pliku XML: $filename");
            }

            // Znajdź pierwszy element o podanej nazwie
            while ($reader->read() && $reader->localName !== $element_name) {
                // Przeszukuj aż znajdziesz pierwszy element
            }

            // Wczytuj elementy jeden po drugim
            do {
                if ($reader->localName === $element_name && $reader->nodeType === XMLReader::ELEMENT) {
                    if ($limit > 0 && $count >= $limit) {
                        error_log("MHI ANDA: Osiągnięto limit $limit elementów dla $filename");
                        break;
                    }

                    $doc = new DOMDocument();
                    $element = $reader->expand($doc);

                    if ($element) {
                        $xml = simplexml_import_dom($element);
                        $item_array = $this->xml_to_array($xml);

                        // Używaj itemNumber jako klucza dla produktów, cen i inventory records
                        if ($element_name === 'product' || $element_name === 'price' || $element_name === 'record') {
                            $key = (string) $xml->itemNumber;
                            if (!empty($key)) {
                                // Dla record (inventories) możemy mieć multiple entries per itemNumber
                                if ($element_name === 'record') {
                                    if (!isset($data[$key])) {
                                        $data[$key] = [];
                                    }
                                    $data[$key][] = $item_array;
                                } else {
                                    $data[$key] = $item_array;
                                }
                            }
                        } else {
                            $data[] = $item_array;
                        }

                        $count++;

                        // Zwolnij pamięć co 100 elementów
                        if ($count % 100 === 0) {
                            unset($xml, $element, $item_array);
                            gc_collect_cycles();
                        }
                    }
                }
            } while ($reader->next($element_name));

            $reader->close();

            error_log("MHI ANDA: Wczytano $count elementów z $filename");
            return $data;

        } catch (Exception $e) {
            error_log("MHI ANDA: Błąd wczytywania $filename: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Konwertuje SimpleXMLElement na tablicę w sposób oszczędny pamięciowo.
     *
     * @param SimpleXMLElement $xml
     * @return array
     */
    private function xml_to_array($xml)
    {
        try {
            // Dla małych elementów używaj standardowej metody
            $string_representation = $xml->asXML();
            if (strlen($string_representation) < 50000) { // 50KB
                $array = json_decode(json_encode($xml), true);
                return is_array($array) ? $array : [];
            }

            // Dla większych elementów używaj iteracyjnej metody
            $result = [];

            // Konwertuj atrybuty
            $attributes = $xml->attributes();
            if ($attributes) {
                foreach ($attributes as $key => $value) {
                    $result['@' . $key] = (string) $value;
                }
            }

            // Konwertuj wartość węzła
            $content = trim((string) $xml);
            if (!empty($content)) {
                $result['value'] = $content;
            }

            // Konwertuj dziecko węzły
            $children = [];
            foreach ($xml->children() as $name => $child) {
                $child_array = $this->xml_to_array($child);

                if (isset($children[$name])) {
                    if (!is_array($children[$name]) || !isset($children[$name][0])) {
                        $children[$name] = [$children[$name]];
                    }
                    $children[$name][] = $child_array;
                } else {
                    $children[$name] = $child_array;
                }

                // Zwolnij pamięć dla dziecka
                unset($child_array);
            }

            if (!empty($children)) {
                $result = array_merge($result, $children);
            }

            // Zwolnij pamięć
            unset($children, $content, $attributes, $string_representation);

            return $result;

        } catch (Exception $e) {
            error_log("MHI ANDA: Błąd konwersji XML do tablicy: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generuje KOMPLETNY plik XML dla WooCommerce z wszystkimi danymi ANDA.
     *
     * @return array Status operacji
     */
    private function generate_complete_woocommerce_xml()
    {
        error_log("MHI ANDA: Generuję KOMPLETNY plik XML z wszystkimi danymi ANDA");

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Główny element
        $root = $xml->createElement('woocommerce_import');
        $xml->appendChild($root);

        // Dodaj informacje o źródle danych
        $info = $xml->createElement('import_info');
        $this->add_xml_element($xml, $info, 'source', 'ANDA Wholesale');
        $this->add_xml_element($xml, $info, 'generated', date('Y-m-d H:i:s'));
        $this->add_xml_element($xml, $info, 'total_products', count($this->products_data));
        $this->add_xml_element($xml, $info, 'total_categories', count($this->flat_categories));
        $this->add_xml_element($xml, $info, 'total_printing_technologies', count($this->printing_prices_data));
        $root->appendChild($info);

        // Sekcja produktów
        $products_section = $xml->createElement('products');
        $root->appendChild($products_section);

        $count = 0;
        $batch_size = 50;
        $processed = 0;

        foreach ($this->products_data as $item_number => $product) {
            $product_element = $this->create_complete_product_element($xml, $product, $item_number);
            if ($product_element) {
                $products_section->appendChild($product_element);
                $count++;
            }

            $processed++;

            // Zwolnij pamięć co batch_size elementów
            if ($processed % $batch_size === 0) {
                unset($product, $product_element);
                gc_collect_cycles();
                error_log("MHI ANDA: Przetworzono $processed produktów...");
            }
        }

        // Zapisz plik
        $filename = 'anda_complete_import.xml';
        $file_path = $this->target_dir . '/' . $filename;

        if ($xml->save($file_path)) {
            error_log("MHI ANDA: Wygenerowano KOMPLETNY plik z $count produktami: $filename");

            // Zwolnij pamięć po zapisaniu
            unset($xml, $root);
            gc_collect_cycles();

            return ['success' => true, 'file' => $filename, 'count' => $count];
        } else {
            throw new Exception("Nie można zapisać pliku $filename");
        }
    }

    /**
     * Generuje plik XML z produktami dla WooCommerce w standardowej strukturze.
     *
     * @return array Status operacji
     */
    private function generate_products_xml()
    {
        error_log("MHI ANDA: Generuję plik XML z produktami ANDA w standardowej strukturze");

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Główny element - standardowa struktura
        $root = $xml->createElement('products');
        $xml->appendChild($root);

        $count = 0;
        $batch_size = 50; // Przetwarzaj po 50 produktów naraz
        $processed = 0;

        foreach ($this->products_data as $item_number => $product) {
            $product_element = $this->create_standard_product_element($xml, $product, $item_number);
            if ($product_element) {
                $root->appendChild($product_element);
                $count++;
            }

            $processed++;

            // Zwolnij pamięć co batch_size elementów
            if ($processed % $batch_size === 0) {
                unset($product, $product_element);
                gc_collect_cycles();
                error_log("MHI ANDA: Przetworzono $processed produktów...");
            }
        }

        // Zapisz plik
        $filename = 'woocommerce_import_anda.xml';
        $file_path = $this->target_dir . '/' . $filename;

        if ($xml->save($file_path)) {
            error_log("MHI ANDA: Wygenerowano $count produktów w pliku $filename");

            // Zwolnij pamięć po zapisaniu
            unset($xml, $root);
            gc_collect_cycles();

            return ['success' => true, 'file' => $filename, 'count' => $count];
        } else {
            throw new Exception("Nie można zapisać pliku $filename");
        }
    }

    /**
     * Tworzy KOMPLETNY element produktu ze wszystkimi danymi ANDA.
     *
     * @param DOMDocument $xml
     * @param array $product
     * @param string $item_number
     * @return DOMElement|null
     */
    private function create_complete_product_element($xml, $product, $item_number)
    {
        try {
            $product_element = $xml->createElement('product');

            // === PODSTAWOWE DANE PRODUKTU ===
            $this->add_xml_element($xml, $product_element, 'sku', $item_number);

            // Nazwa produktu - połącz wszystkie dostępne informacje
            $name = $this->build_complete_product_name($product, $item_number);
            $this->add_xml_element($xml, $product_element, 'name', $name);

            // Opis produktu
            $description = $this->build_complete_description($product);
            $this->add_xml_element($xml, $product_element, 'description', $description);

            // === CENY I KOSZTY ===
            $price_data = $this->get_product_price($item_number);
            if (!empty($price_data)) {
                $this->add_pricing_data($xml, $product_element, $price_data);
            } else {
                // Jeśli brak ceny, ustaw podstawową cenę z produktu lub 0
                $fallback_price = !empty($product['listPrice']) ? $product['listPrice'] : '0';
                $this->add_xml_element($xml, $product_element, 'regular_price', $fallback_price);
                error_log("MHI ANDA: Używam fallback ceny dla produktu $item_number: $fallback_price");
            }

            // === KATEGORIE Z PEŁNĄ HIERARCHIĄ ===
            $this->add_complete_categories($xml, $product_element, $product);

            // === KOMPLETNE ATRYBUTY ===
            $this->add_complete_attributes($xml, $product_element, $product, $item_number);

            // === STAN MAGAZYNOWY ===
            $stock = $this->get_product_stock($item_number);
            $this->add_xml_element($xml, $product_element, 'stock_quantity', $stock);
            $stock_status = $stock > 0 ? 'instock' : 'outofstock';
            $this->add_xml_element($xml, $product_element, 'stock_status', $stock_status);

            // === TECHNOLOGIE DRUKU JAKO ATRYBUTY ===
            $this->add_printing_technologies_as_attributes($xml, $product_element, $product);

            // === DANE ZNAKOWANIA ===
            $this->add_labeling_data($xml, $product_element, $item_number);

            // === META DATA Z WSZYSTKIMI INFORMACJAMI ===
            $this->add_complete_meta_data($xml, $product_element, $product, $item_number);

            // === ZDJĘCIA ===
            $this->add_complete_images($xml, $product_element, $product);

            return $product_element;

        } catch (Exception $e) {
            error_log("MHI ANDA: Błąd tworzenia kompletnego elementu produktu $item_number: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tworzy element produktu w standardowej strukturze WooCommerce.
     *
     * @param DOMDocument $xml
     * @param array $product
     * @param string $item_number
     * @return DOMElement|null
     */
    private function create_standard_product_element($xml, $product, $item_number)
    {
        try {
            $product_element = $xml->createElement('product');

            // SKU - kod produktu
            $this->add_xml_element($xml, $product_element, 'sku', $item_number);

            // Nazwa produktu - połącz designName z krótką nazwą
            $name = !empty($product['designName']) ? $product['designName'] : '';
            if (!empty($product['n'])) {
                $name .= ($name ? ' - ' : '') . $product['n'];
            }
            if (empty($name)) {
                $name = $item_number; // Fallback na kod produktu
            }
            $this->add_xml_element($xml, $product_element, 'name', $name);

            // Opis produktu
            $description = !empty($product['descriptions']) ? $product['descriptions'] : '';
            $this->add_xml_element($xml, $product_element, 'description', $description);

            // === CENY Z POPRAWNEGO MAPOWANIA ===
            $price_data = $this->get_product_price($item_number);
            if (!empty($price_data)) {
                $this->add_pricing_data($xml, $product_element, $price_data);
            } else {
                // Fallback - sprawdź cenę w danych produktu
                $fallback_price = !empty($product['listPrice']) ? $product['listPrice'] : '0';
                $this->add_xml_element($xml, $product_element, 'regular_price', $fallback_price);
                error_log("MHI ANDA: Standardowy - używam fallback ceny dla produktu $item_number: $fallback_price");
            }

            // === KATEGORIE Z POPRAWNEGO MAPOWANIA ===
            $this->add_complete_categories($xml, $product_element, $product);

            // === KOMPLETNE ATRYBUTY ===
            $this->add_complete_attributes($xml, $product_element, $product, $item_number);

            // === STAN MAGAZYNOWY ===
            $stock = $this->get_product_stock($item_number);
            $this->add_xml_element($xml, $product_element, 'stock_quantity', $stock);
            $stock_status = $stock > 0 ? 'instock' : 'outofstock';
            $this->add_xml_element($xml, $product_element, 'stock_status', $stock_status);

            // === TECHNOLOGIE DRUKU ===
            $this->add_printing_technologies_as_attributes($xml, $product_element, $product);

            // === DANE ZNAKOWANIA ===
            $this->add_labeling_data($xml, $product_element, $item_number);

            // === KOMPLETNE META DATA ===
            $this->add_complete_meta_data($xml, $product_element, $product, $item_number);

            // === KOMPLETNE ZDJĘCIA ===
            $this->add_complete_images($xml, $product_element, $product);

            return $product_element;

        } catch (Exception $e) {
            error_log("MHI ANDA: Błąd tworzenia elementu produktu $item_number: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Dodaje kategorie w standardowej strukturze na podstawie danych z produktu.
     */
    private function add_standard_categories($xml, $product_element, $product)
    {
        $categories_element = $xml->createElement('categories');
        $product_element->appendChild($categories_element);

        $categories_added = [];

        // Najpierw sprawdź czy produkt ma już kategorie w danych
        if (!empty($product['categories']['category'])) {
            $product_categories = $product['categories']['category'];

            // Jeśli to pojedyncza kategoria, zamień na tablicę
            if (isset($product_categories['name'])) {
                $product_categories = [$product_categories];
            }

            foreach ($product_categories as $category) {
                if (!empty($category['name'])) {
                    $category_name = $category['name'];
                    if (!in_array($category_name, $categories_added)) {
                        $category_element = $xml->createElement('category');
                        $category_element->nodeValue = htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8');
                        $categories_element->appendChild($category_element);
                        $categories_added[] = $category_name;
                    }
                }
            }
        }

        // Jeśli nie znaleziono kategorii w danych produktu, użyj mapowania
        if (empty($categories_added)) {
            $mapped_categories = $this->map_product_to_categories($product);

            foreach ($mapped_categories as $category_name) {
                $category_element = $xml->createElement('category');
                $category_element->nodeValue = htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8');
                $categories_element->appendChild($category_element);
            }
        }
    }

    /**
     * Mapuje produkt do kategorii na podstawie nazwy i słów kluczowych.
     *
     * @param array $product
     * @return array Lista nazw kategorii
     */
    private function map_product_to_categories($product)
    {
        // Użyj designName lub name jako nazwy produktu
        $product_name = strtolower($product['designName'] ?? $product['name'] ?? '');
        $mapped_categories = [];

        // Debug log
        error_log("MHI ANDA: Mapowanie kategorii dla produktu: $product_name");

        // Mapowanie na podstawie słów kluczowych w nazwie produktu
        $category_mapping = [
            // Do żywności i napojów - kubki (kolejność ma znaczenie - bardziej specyficzne pierwsze)
            'creacup' => ['Do żywności i napojów', 'Kubki i filiżanki termiczne'],
            'termiczny' => ['Do żywności i napojów', 'Kubki i filiżanki termiczne'],
            'kubek' => ['Do żywności i napojów', 'Kubki, filiżanki i szklanki'],
            'cup' => ['Do żywności i napojów', 'Kubki, filiżanki i szklanki'],
            'filiżank' => ['Do żywności i napojów', 'Kubki, filiżanki i szklanki'],
            'szklank' => ['Do żywności i napojów', 'Kubki, filiżanki i szklanki'],
            'termos' => ['Do żywności i napojów', 'Butelki izolowane i termosy'],
            'butelka' => ['Do żywności i napojów', 'Butelki'],
            'sportow' => ['Do żywności i napojów', 'Butelki sportowe'],
            'otwieracz' => ['Do żywności i napojów', 'Otwieracze do butelek'],
            'magnesy' => ['Do żywności i napojów', 'Magnesy na lodówkę'],

            // Torby i podróże
            'torba' => ['Torby i podróże', 'Torby zakupowe i plażowe'],
            'plecak' => ['Torby i podróże', 'Plecaki i torby na ramię'],
            'walizka' => ['Torby i podróże', 'Torby podróżne'],
            'parasol' => ['Torby i podróże', 'Parasole'],
            'portfel' => ['Torby i podróże', 'Portfele i etui na karty'],

            // Technologia i telefon
            'power bank' => ['Techonologia i telefon', 'Power banki'],
            'ładowarka' => ['Techonologia i telefon', 'ładowarki USB'],
            'pendrive' => ['Techonologia i telefon', 'USB pendrive'],
            'słuchawk' => ['Techonologia i telefon', 'Muzyka i audio'],
            'głośnik' => ['Techonologia i telefon', 'Muzyka i audio'],
            'zegarek' => ['Techonologia i telefon', 'Zegary i zegarki'],
            'zegar' => ['Techonologia i telefon', 'Zegary i zegarki'],

            // Do pisania
            'długopis' => ['Do pisania', 'Długopisy'],
            'ołówek' => ['Do pisania', 'Ołówki'],
            'rysik' => ['Do pisania', 'Rysiki do ekranów dotykowych'],

            // Biuro i praca
            'notes' => ['Biuro i praca', 'Notesy i notatniki'],
            'notatnik' => ['Biuro i praca', 'Notesy i notatniki'],
            'podkładka' => ['Biuro i praca', 'Podkładki'],
            'smycz' => ['Biuro i praca', 'Smycze i uchwyty'],

            // Sport i wypoczynek
            'piłka' => ['Sport i wypoczynek', 'Nadmuchiwane'],
            'koc' => ['Sport i wypoczynek', 'Outdoor i piesze wycieczki'],

            // Witalność & pielęgnacja
            'lusterko' => ['Witalność & pielęgnacja', 'Lusterka i grzebienie'],
            'ręcznik' => ['Witalność & pielęgnacja', 'Ręczniki, szlafroki'],
            'antystres' => ['Witalność & pielęgnacja', 'Antystresy'],

            // Do kluczy i narzędzia
            'brelok' => ['Do kluczy i narzędzia', 'Breloki'],
            'latarka' => ['Do kluczy i narzędzia', 'Latarki'],
            'odblask' => ['Do kluczy i narzędzia', 'Produkty odblaskowe'],

            // Tekstylia i akcesoria
            'czapka' => ['Tekstylia i akcesoria', 'Nakrycia głowy'],
            'koszulka' => ['Tekstylia i akcesoria', 'T-shirty'],
            't-shirt' => ['Tekstylia i akcesoria', 'T-shirty'],
            'bluza' => ['Tekstylia i akcesoria', 'Bluzy i kurtki'],
            'polo' => ['Tekstylia i akcesoria', 'Koszule i koszulki Polo'],
            'okulary' => ['Tekstylia i akcesoria', 'Okulary przeciwsłoneczne'],
        ];

        // Znajdź pasujące kategorie
        foreach ($category_mapping as $keyword => $categories) {
            if (strpos($product_name, $keyword) !== false) {
                $mapped_categories = array_merge($mapped_categories, $categories);
                error_log("MHI ANDA: Znaleziono pasujące słowo kluczowe: $keyword -> " . implode(', ', $categories));
                break; // Weź pierwszą pasującą kategorię
            }
        }

        // Jeśli nie znaleziono pasujących kategorii, użyj domyślnej
        if (empty($mapped_categories)) {
            $mapped_categories = ['Różne'];
            error_log("MHI ANDA: Nie znaleziono pasujących kategorii, używam domyślnej: Różne");
        }

        return array_unique($mapped_categories);
    }

    /**
     * Dodaje atrybuty w standardowej strukturze.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     * @param string $item_number
     */
    private function add_standard_attributes($xml, $product_element, $product, $item_number)
    {
        $attributes_element = $xml->createElement('attributes');
        $product_element->appendChild($attributes_element);

        // Materiał
        $material = $this->detect_material($product);
        if (!empty($material)) {
            $this->add_standard_attribute($xml, $attributes_element, 'Materiał', $material);
        }

        // Wymiary
        if (!empty($product['width']) && !empty($product['height']) && !empty($product['depth'])) {
            $dimensions = $product['width'] . ' x ' . $product['height'] . ' x ' . $product['depth'] . ' cm';
            $this->add_standard_attribute($xml, $attributes_element, 'Wymiary', $dimensions);
        }

        // Waga
        if (!empty($product['individualProductWeightGram'])) {
            $weight = $product['individualProductWeightGram'] . ' g';
            $this->add_standard_attribute($xml, $attributes_element, 'Waga', $weight);
        }

        // Kolor podstawowy
        if (!empty($product['primaryColor'])) {
            $this->add_standard_attribute($xml, $attributes_element, 'Kolor', $product['primaryColor']);
        }

        // Kolor drugorzędny
        if (!empty($product['secondaryColor'])) {
            $this->add_standard_attribute($xml, $attributes_element, 'Kolor dodatkowy', $product['secondaryColor']);
        }

        // Minimalna ilość zamówienia
        if (!empty($product['minimumOrderQuantity'])) {
            $this->add_standard_attribute($xml, $attributes_element, 'Minimalne zamówienie', $product['minimumOrderQuantity'] . ' szt.');
        }

        // Pakowanie
        if (!empty($product['packaging'])) {
            $this->add_standard_attribute($xml, $attributes_element, 'Pakowanie', $product['packaging']);
        }

        // Certyfikaty
        $certifications = $this->detect_certifications($product);
        if (!empty($certifications)) {
            $this->add_standard_attribute($xml, $attributes_element, 'Certyfikaty', implode(', ', $certifications));
        }

        // Technologie druku
        $printing_technologies = $this->get_suitable_printing_technologies($product);
        if (!empty($printing_technologies)) {
            $tech_names = array_map(function ($tech) {
                return !empty($tech['name']) ? $tech['name'] : $tech['code'];
            }, $printing_technologies);
            $this->add_standard_attribute($xml, $attributes_element, 'Technika znakowania', implode(', ', $tech_names));
        }

        // Miejsce znakowania (z danych produktu)
        if (!empty($product['labelingArea'])) {
            $this->add_standard_attribute($xml, $attributes_element, 'Miejsce znakowania', $product['labelingArea']);
        }

        // Wymiar znakowania (z danych produktu)
        if (!empty($product['labelingDimensions'])) {
            $this->add_standard_attribute($xml, $attributes_element, 'Wymiar znakowania', $product['labelingDimensions']);
        }
    }

    /**
     * Dodaje pojedynczy atrybut w standardowej strukturze.
     *
     * @param DOMDocument $xml
     * @param DOMElement $attributes_element
     * @param string $name
     * @param string $value
     */
    private function add_standard_attribute($xml, $attributes_element, $name, $value)
    {
        if (empty($value))
            return;

        $attribute_element = $xml->createElement('attribute');
        $attributes_element->appendChild($attribute_element);

        $name_element = $xml->createElement('name');
        $name_element->nodeValue = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $attribute_element->appendChild($name_element);

        $value_element = $xml->createElement('value');
        $value_element->nodeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $attribute_element->appendChild($value_element);

        $visible_element = $xml->createElement('visible');
        $visible_element->nodeValue = '1';
        $attribute_element->appendChild($visible_element);
    }

    /**
     * Dodaje meta data w standardowej strukturze.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     * @param string $item_number
     */
    private function add_standard_meta_data($xml, $product_element, $product, $item_number)
    {
        // Kod ANDA
        $this->add_standard_meta($xml, $product_element, '_anda_code', $item_number);

        // EAN
        if (!empty($product['eanCode'])) {
            $this->add_standard_meta($xml, $product_element, '_anda_ean', $product['eanCode']);
        }

        // Kraj pochodzenia
        if (!empty($product['countryOfOrigin'])) {
            $this->add_standard_meta($xml, $product_element, '_anda_country_origin', $product['countryOfOrigin']);
        }

        // Czy produkt na zamówienie
        $custom_production = !empty($product['customProduction']) && $product['customProduction'] == '1' ? '1' : '0';
        $this->add_standard_meta($xml, $product_element, '_anda_custom_production', $custom_production);

        // Status dostępności
        $available = empty($product['temporarilyUnavailable']) || $product['temporarilyUnavailable'] != '1' ? '1' : '0';
        $this->add_standard_meta($xml, $product_element, '_anda_available', $available);

        // Minimalna ilość zamówienia
        if (!empty($product['minimumOrderQuantity'])) {
            $this->add_standard_meta($xml, $product_element, '_anda_min_order_qty', $product['minimumOrderQuantity']);
        }

        // Cena brutto (VAT 23%)
        $price_data = $this->get_product_price($item_number);
        $net_price = !empty($price_data['listPrice']) ? $price_data['listPrice'] : 0;
        $gross_price = $net_price * 1.23;
        $this->add_standard_meta($xml, $product_element, '_anda_gross_price', number_format($gross_price, 2, '.', ''));
    }

    /**
     * Dodaje pojedynczy meta data element.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param string $key
     * @param string $value
     */
    private function add_standard_meta($xml, $product_element, $key, $value)
    {
        if (empty($value))
            return;

        $meta_element = $xml->createElement('meta_data');
        $product_element->appendChild($meta_element);

        $key_element = $xml->createElement('key');
        $key_element->nodeValue = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $meta_element->appendChild($key_element);

        $value_element = $xml->createElement('value');
        $value_element->nodeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $meta_element->appendChild($value_element);
    }

    /**
     * Dodaje zdjęcia w standardowej strukturze.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     */
    private function add_standard_images($xml, $product_element, $product)
    {
        $images_element = $xml->createElement('images');
        $product_element->appendChild($images_element);

        // Główne zdjęcie
        if (!empty($product['primaryImage'])) {
            $image_element = $xml->createElement('image');
            $image_element->setAttribute('src', $product['primaryImage']);
            $images_element->appendChild($image_element);
        }

        // Dodatkowe zdjęcia z sekcji images w ANDA
        if (!empty($product['images']['image'])) {
            $gallery_images = $product['images']['image'];

            // Jeśli to pojedyncze zdjęcie (string)
            if (is_string($gallery_images)) {
                if ($gallery_images !== $product['primaryImage']) { // Nie duplikuj głównego zdjęcia
                    $image_element = $xml->createElement('image');
                    $image_element->setAttribute('src', $gallery_images);
                    $images_element->appendChild($image_element);
                }
            }
            // Jeśli to tablica zdjęć
            else if (is_array($gallery_images)) {
                foreach ($gallery_images as $image_url) {
                    if (is_string($image_url) && !empty($image_url) && $image_url !== $product['primaryImage']) {
                        $image_element = $xml->createElement('image');
                        $image_element->setAttribute('src', $image_url);
                        $images_element->appendChild($image_element);
                    }
                }
            }
        }

        // Alternatywnie sprawdź inne pola zdjęć (backup)
        $additional_image_fields = ['secondaryImage', 'image1', 'image2', 'image3', 'image4', 'image5'];
        foreach ($additional_image_fields as $field) {
            if (!empty($product[$field]) && $product[$field] !== $product['primaryImage']) {
                $image_element = $xml->createElement('image');
                $image_element->setAttribute('src', $product[$field]);
                $images_element->appendChild($image_element);
            }
        }
    }

    /**
     * Generuje plik XML z kategoriami.
     *
     * @return array Status operacji
     */
    private function generate_categories_xml()
    {
        error_log("MHI ANDA: Generuję plik XML z kategoriami ANDA");

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('Categories');
        $xml->appendChild($root);

        $count = 0;
        foreach ($this->categories_data as $category) {
            $category_element = $this->create_category_element($xml, $category);
            if ($category_element) {
                $root->appendChild($category_element);
                $count++;
            }
        }

        $filename = 'categories_wc.xml';
        $file_path = $this->target_dir . '/' . $filename;

        if ($xml->save($file_path)) {
            error_log("MHI ANDA: Wygenerowano $count kategorii w pliku $filename");
            return ['success' => true, 'file' => $filename, 'count' => $count];
        } else {
            throw new Exception("Nie można zapisać pliku $filename");
        }
    }

    /**
     * Tworzy element kategorii dla XML.
     *
     * @param DOMDocument $xml
     * @param array $category
     * @return DOMElement|null
     */
    private function create_category_element($xml, $category)
    {
        if (empty($category['n'])) {
            return null;
        }

        $item = $xml->createElement('Category');

        $this->add_xml_element($xml, $item, 'Name', $category['n']);

        if (!empty($category['externalId'])) {
            $this->add_xml_element($xml, $item, 'ExternalId', $category['externalId']);
        }

        if (!empty($category['parentId'])) {
            $this->add_xml_element($xml, $item, 'ParentId', $category['parentId']);
        }

        return $item;
    }

    /**
     * Dodaje element do XML.
     *
     * @param DOMDocument $xml
     * @param DOMElement $parent
     * @param string $name
     * @param string $value
     */
    private function add_xml_element($xml, $parent, $name, $value)
    {
        $element = $xml->createElement($name);
        $element->appendChild($xml->createTextNode($value));
        $parent->appendChild($element);
    }

    /**
     * Pobiera informacje o wygenerowanych plikach.
     *
     * @return array
     */
    public function get_generated_files_info()
    {
        $files = [];
        $target_files = [
            'products_wc.xml',
            'categories_wc.xml',
            'printing_services_wc.xml',
            'product_variations_wc.xml'
        ];

        foreach ($target_files as $filename) {
            $file_path = $this->target_dir . '/' . $filename;
            if (file_exists($file_path)) {
                $files[] = [
                    'name' => $filename,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'url' => wp_upload_dir()['baseurl'] . '/wholesale/' . $this->name . '/xml_files/' . $filename
                ];
            }
        }

        return $files;
    }

    /**
     * Generuje plik XML z usługami druku/znakowania.
     *
     * @return array Status operacji
     */
    private function generate_printing_services_xml()
    {
        error_log("MHI ANDA: Generuję plik XML z usługami druku ANDA");

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('PrintingServices');
        $xml->appendChild($root);

        $count = 0;
        foreach ($this->printing_prices_data as $tech_data) {
            $service_element = $this->create_printing_service_element($xml, $tech_data);
            if ($service_element) {
                $root->appendChild($service_element);
                $count++;
            }
        }

        $filename = 'printing_services_wc.xml';
        $file_path = $this->target_dir . '/' . $filename;

        if ($xml->save($file_path)) {
            error_log("MHI ANDA: Wygenerowano $count usług druku w pliku $filename");
            return ['success' => true, 'file' => $filename, 'count' => $count];
        } else {
            throw new Exception("Nie można zapisać pliku $filename");
        }
    }

    /**
     * Tworzy element usługi druku dla XML.
     *
     * @param DOMDocument $xml
     * @param array $tech_data
     * @return DOMElement|null
     */
    private function create_printing_service_element($xml, $tech_data)
    {
        if (empty($tech_data['code']) || empty($tech_data['name'])) {
            return null;
        }

        $item = $xml->createElement('Service');

        $this->add_xml_element($xml, $item, 'Code', $tech_data['code']);
        $this->add_xml_element($xml, $item, 'Name', $tech_data['name']);
        $this->add_xml_element($xml, $item, 'Type', 'printing');

        // Dodaj kategorie produktów, dla których dostępna jest ta technologia
        $suitable_categories = $this->get_suitable_categories_for_technology($tech_data['code']);
        if (!empty($suitable_categories)) {
            $this->add_xml_element($xml, $item, 'SuitableCategories', implode(',', $suitable_categories));
        }

        // Dodaj informacje o cenach
        if (!empty($tech_data['ranges'])) {
            $price_ranges = $xml->createElement('PriceRanges');

            foreach ($tech_data['ranges'] as $range) {
                $range_element = $xml->createElement('Range');

                $this->add_xml_element($xml, $range_element, 'Colors', $range['colors']);
                $this->add_xml_element($xml, $range_element, 'QuantityFrom', $range['qty_from']);
                $this->add_xml_element($xml, $range_element, 'QuantityTo', $range['qty_to']);
                $this->add_xml_element($xml, $range_element, 'UnitPrice', $range['unit_price']);
                $this->add_xml_element($xml, $range_element, 'SetupCost', $range['setup_cost']);

                if ($range['size_from']) {
                    $this->add_xml_element($xml, $range_element, 'SizeFrom', $range['size_from']);
                }
                if ($range['size_to']) {
                    $this->add_xml_element($xml, $range_element, 'SizeTo', $range['size_to']);
                }

                $price_ranges->appendChild($range_element);
            }

            $item->appendChild($price_ranges);
        }

        return $item;
    }

    /**
     * Zwraca kategorie produktów odpowiednie dla danej technologii druku.
     *
     * @param string $tech_code
     * @return array
     */
    private function get_suitable_categories_for_technology($tech_code)
    {
        $category_mapping = [
            'C1' => ['14010', '14020'], // Druk ceramiczny - kubki, akcesoria coffee
            'C2' => ['14010', '14020'],
            'C3' => ['14010', '14020'],
            'DG1' => ['2010', '2030', '4050'], // Druk cyfrowy - notesy, teczki, torby na laptop
            'DG2' => ['2010', '2030', '4050'],
            'DG3' => ['2010', '2030', '4050'],
            'DO1' => ['7010', '7020'], // Doming - breloki, monety
            'DO2' => ['7010', '7020'],
            'DO3' => ['7010', '7020'],
            'DO4' => ['7010', '7020'],
            'DO5' => ['7010', '7020'],
            'DTA1' => ['10050', '10040', '10060'], // Transfer - t-shirty, bluzy, polo
            'DTA2' => ['10050', '10040', '10060'],
            'DTA3' => ['10050', '10040', '10060'],
            'DTA4' => ['10050', '10040', '10060'],
            'DTB1' => ['4000', '4010', '4020'], // Transfer B - torby
            'DTB2' => ['4000', '4010', '4020'],
            'DTB3' => ['4000', '4010', '4020'],
            'DTB4' => ['4000', '4010', '4020'],
            'DTC1' => ['10000', '10010'], // Transfer C - akcesoria tekstylne
            'DTC2' => ['10000', '10010'],
            'DTC3' => ['10000', '10010'],
            'DTD1' => ['10070', '8100'], // Transfer D - odzież sportowa
            'DTD2' => ['10070', '8100'],
            'DTD3' => ['10070', '8100'],
            'DTA1-HS' => ['10010'] // Transfer + hafciarstwo - czapki
        ];

        return isset($category_mapping[$tech_code]) ? $category_mapping[$tech_code] : [];
    }

    /**
     * Generuje plik XML z wariantami produktów.
     *
     * @return array Status operacji
     */
    private function generate_product_variations_xml()
    {
        error_log("MHI ANDA: Generuję plik XML z wariantami produktów ANDA");

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('ProductVariations');
        $xml->appendChild($root);

        $count = 0;
        foreach ($this->products_data as $item_number => $product) {
            $variations = $this->create_product_variations($product, $item_number);

            if (!empty($variations)) {
                foreach ($variations as $variation) {
                    $variation_element = $this->create_variation_element($xml, $variation);
                    if ($variation_element) {
                        $root->appendChild($variation_element);
                        $count++;
                    }
                }
            }
        }

        $filename = 'product_variations_wc.xml';
        $file_path = $this->target_dir . '/' . $filename;

        if ($xml->save($file_path)) {
            error_log("MHI ANDA: Wygenerowano $count wariantów produktów w pliku $filename");
            return ['success' => true, 'file' => $filename, 'count' => $count];
        } else {
            throw new Exception("Nie można zapisać pliku $filename");
        }
    }

    /**
     * Tworzy warianty produktu na podstawie dostępnych atrybutów.
     *
     * @param array $product
     * @param string $base_item_number
     * @return array
     */
    private function create_product_variations($product, $base_item_number)
    {
        $variations = [];
        $attributes = $this->extract_variation_attributes($product);

        if (empty($attributes)) {
            return $variations;
        }

        // Generuj kombinacje atrybutów
        $combinations = $this->generate_attribute_combinations($attributes);

        foreach ($combinations as $index => $combination) {
            $variation_sku = $base_item_number . '-VAR-' . ($index + 1);

            $variations[] = [
                'parent_sku' => $base_item_number,
                'variation_sku' => $variation_sku,
                'name' => $product['n'] . ' - ' . implode(', ', array_values($combination)),
                'attributes' => $combination,
                'price' => $this->get_product_price($base_item_number),
                'stock' => $this->get_product_stock($base_item_number)
            ];
        }

        return $variations;
    }

    /**
     * Wyciąga atrybuty nadające się do tworzenia wariantów.
     *
     * @param array $product
     * @return array
     */
    private function extract_variation_attributes($product)
    {
        $variation_attributes = [];

        if (empty($product['specification']['property'])) {
            return $variation_attributes;
        }

        $properties = $product['specification']['property'];

        // Jeśli to pojedyncza właściwość
        if (isset($properties['n'])) {
            $properties = [$properties];
        }

        // Zdefiniuj atrybuty, które mogą tworzyć warianty
        $variation_attribute_names = [
            'kolor',
            'color',
            'colour',
            'rozmiar',
            'size',
            'wielkość',
            'pojemność',
            'capacity',
            'volume',
            'materiał',
            'material',
            'gramatura',
            'weight'
        ];

        foreach ($properties as $property) {
            if (!empty($property['n']) && !empty($property['values']['value'])) {
                $attr_name = strtolower($property['n']);

                // Sprawdź czy to atrybut, który może tworzyć warianty
                foreach ($variation_attribute_names as $var_attr) {
                    if (strpos($attr_name, $var_attr) !== false) {
                        $values = is_array($property['values']['value']) ?
                            $property['values']['value'] :
                            [$property['values']['value']];

                        // Tylko jeśli jest więcej niż jedna wartość
                        if (count($values) > 1) {
                            $variation_attributes[$property['n']] = $values;
                        }
                        break;
                    }
                }
            }
        }

        return $variation_attributes;
    }

    /**
     * Generuje kombinacje atrybutów.
     *
     * @param array $attributes
     * @return array
     */
    private function generate_attribute_combinations($attributes)
    {
        if (empty($attributes)) {
            return [];
        }

        $keys = array_keys($attributes);
        $values = array_values($attributes);

        // Maksymalnie 50 kombinacji, żeby nie przeciążyć systemu
        $max_combinations = 50;
        $total_combinations = array_product(array_map('count', $values));

        if ($total_combinations > $max_combinations) {
            // Ogranicz do pierwszych wartości każdego atrybutu
            foreach ($values as &$value_array) {
                $value_array = array_slice($value_array, 0, 3);
            }
        }

        return $this->cartesian_product($attributes);
    }

    /**
     * Oblicza iloczyn kartezjański atrybutów.
     *
     * @param array $arrays
     * @return array
     */
    private function cartesian_product($arrays)
    {
        $result = [[]];

        foreach ($arrays as $key => $array) {
            $temp = [];
            foreach ($result as $resultItem) {
                foreach ($array as $item) {
                    $temp[] = array_merge($resultItem, [$key => $item]);
                }
            }
            $result = $temp;
        }

        return $result;
    }

    /**
     * Tworzy element wariantu dla XML.
     *
     * @param DOMDocument $xml
     * @param array $variation
     * @return DOMElement|null
     */
    private function create_variation_element($xml, $variation)
    {
        if (empty($variation['variation_sku'])) {
            return null;
        }

        $item = $xml->createElement('Variation');

        $this->add_xml_element($xml, $item, 'ParentSKU', $variation['parent_sku']);
        $this->add_xml_element($xml, $item, 'VariationSKU', $variation['variation_sku']);
        $this->add_xml_element($xml, $item, 'Name', $variation['name']);
        $this->add_xml_element($xml, $item, 'Price', $variation['price']);
        $this->add_xml_element($xml, $item, 'Stock', $variation['stock']);

        // Dodaj atrybuty wariantu
        if (!empty($variation['attributes'])) {
            $attributes_element = $xml->createElement('Attributes');

            foreach ($variation['attributes'] as $attr_name => $attr_value) {
                $attr_element = $xml->createElement('Attribute');
                $this->add_xml_element($xml, $attr_element, 'Name', $this->sanitize_attribute_name($attr_name));
                $this->add_xml_element($xml, $attr_element, 'Value', $attr_value);
                $attributes_element->appendChild($attr_element);
            }

            $item->appendChild($attributes_element);
        }

        return $item;
    }

    /**
     * Pobiera główną nazwę kategorii dla produktu.
     *
     * @param array $product
     * @return string
     */
    private function get_main_category_name($product)
    {
        if (empty($product['categories']['category'])) {
            return '';
        }

        $categories = $product['categories']['category'];

        // Jeśli to pojedyncza kategoria
        if (isset($categories['n'])) {
            return $categories['n'];
        }

        // Jeśli to tablica kategorii, znajdź główną (level 1)
        if (is_array($categories)) {
            foreach ($categories as $category) {
                if (isset($category['level']) && $category['level'] == 1 && !empty($category['n'])) {
                    return $category['n'];
                }
            }

            // Jeśli nie ma kategorii poziomu 1, weź pierwszą dostępną
            foreach ($categories as $category) {
                if (!empty($category['n'])) {
                    return $category['n'];
                }
            }
        }

        return '';
    }

    /**
     * Pobiera cenę produktu z nowej struktury ANDA (prices.xml).
     * 
     * @param string $item_number
     * @return array
     */
    private function get_product_price($item_number)
    {
        $price_data = [];

        // Przeszukaj wszystkie ceny po itemNumber
        if (!empty($this->prices_data)) {
            foreach ($this->prices_data as $price_item) {
                if (isset($price_item['itemNumber']) && $price_item['itemNumber'] === $item_number) {
                    // ANDA struktura: <price><itemNumber>...</itemNumber><amount>...</amount><type>listPrice/discountPrice</type></price>
                    $type = $price_item['type'] ?? '';
                    $amount = $price_item['amount'] ?? '0';
                    $currency = $price_item['currency'] ?? 'PLN';

                    if ($type === 'listPrice') {
                        $price_data['listPrice'] = $amount;
                        $price_data['regular_price'] = $amount;
                        $price_data['currency'] = $currency;
                        error_log("MHI ANDA: Znaleziono listPrice dla $item_number: $amount $currency");
                    } elseif ($type === 'discountPrice') {
                        $price_data['discountPrice'] = $amount;
                        $price_data['sale_price'] = $amount;
                        error_log("MHI ANDA: Znaleziono discountPrice dla $item_number: $amount $currency");
                    }
                }
            }
        }

        // Loguj brak ceny dla debugowania
        if (empty($price_data)) {
            error_log("MHI ANDA: Brak ceny dla produktu: $item_number");
        }

        return $price_data;
    }

    /**
     * Pobiera stan magazynowy produktu z inventories.xml po itemNumber.
     * Struktura ANDA: <record><itemNumber>AP892006</itemNumber><type>central_stock</type><amount>0</amount></record>
     * Teraz inventories_data[itemNumber] = [array of records]
     *
     * @param string $item_number
     * @return string
     */
    private function get_product_stock($item_number)
    {
        $central_stock = 0;
        $incoming_stock = 0;
        $found_records = 0;

        // Sprawdź czy mamy bezpośrednio rekordy dla tego itemNumber
        if (!empty($this->inventories_data[$item_number])) {
            $records = $this->inventories_data[$item_number];

            // Jeśli jest tablica rekordów
            if (is_array($records)) {
                foreach ($records as $inventory) {
                    $type = trim($inventory['type'] ?? '');
                    $amount = intval($inventory['amount'] ?? '0');
                    $found_records++;

                    // Zbierz różne typy stock
                    if ($type === 'central_stock') {
                        $central_stock = $amount;
                        error_log("MHI ANDA STOCK: ✅ central_stock dla $item_number: $amount");
                    } elseif ($type === 'incoming_to_central_stock') {
                        $incoming_stock += $amount; // Możemy sumować incoming
                        error_log("MHI ANDA STOCK: 📥 incoming_stock dla $item_number: $amount");
                    } else {
                        error_log("MHI ANDA STOCK: ℹ️ Nieznany typ '$type' dla $item_number (amount: $amount)");
                    }
                }
            }
        } else {
            // Fallback - przeszukaj wszystkie rekordy (stara metoda)
            if (!empty($this->inventories_data)) {
                foreach ($this->inventories_data as $key => $records) {
                    if (is_array($records)) {
                        foreach ($records as $inventory) {
                            if (isset($inventory['itemNumber']) && trim($inventory['itemNumber']) === trim($item_number)) {
                                $type = trim($inventory['type'] ?? '');
                                $amount = intval($inventory['amount'] ?? '0');
                                $found_records++;

                                if ($type === 'central_stock') {
                                    $central_stock = $amount;
                                    error_log("MHI ANDA STOCK: ✅ (fallback) central_stock dla $item_number: $amount");
                                } elseif ($type === 'incoming_to_central_stock') {
                                    $incoming_stock += $amount;
                                    error_log("MHI ANDA STOCK: 📥 (fallback) incoming_stock dla $item_number: $amount");
                                }
                            }
                        }
                    }
                }
            }
        }

        // Logika obliczania finalnego stock
        $final_stock = $central_stock; // Głównie central_stock

        // Opcjonalnie: jeśli central_stock = 0, ale jest incoming, można użyć incoming
        // if ($final_stock === 0 && $incoming_stock > 0) {
        //     $final_stock = $incoming_stock;
        //     error_log("MHI ANDA STOCK: 🔄 Używam incoming_stock dla $item_number: $incoming_stock");
        // }

        if ($found_records === 0) {
            error_log("MHI ANDA STOCK: ❌ Brak rekordów dla produktu: $item_number");
        } elseif ($final_stock === 0) {
            error_log("MHI ANDA STOCK: ⚠️ Stock = 0 dla $item_number (central: $central_stock, incoming: $incoming_stock)");
        } else {
            error_log("MHI ANDA STOCK: 📦 Finalne stock dla $item_number: $final_stock (central: $central_stock)");
        }

        return (string) $final_stock;
    }

    /**
     * Wykrywa materiał produktu na podstawie nazwy i kategorii.
     */
    private function detect_material($product)
    {
        $name = strtolower($product['n'] ?? '');

        $material_keywords = [
            'ceramiczny' => 'Ceramika',
            'metalowy' => 'Metal',
            'plastikowy' => 'Plastik',
            'bawełna' => 'Bawełna',
            'poliester' => 'Poliester',
            'szkło' => 'Szkło',
            'drewno' => 'Drewno',
            'bambus' => 'Bambus',
            'skóra' => 'Skóra',
            'silikon' => 'Silikon',
            'aluminium' => 'Aluminium',
            'stal' => 'Stal nierdzewna'
        ];

        foreach ($material_keywords as $keyword => $material) {
            if (strpos($name, $keyword) !== false) {
                return $material;
            }
        }

        return null;
    }

    /**
     * Wykrywa certyfikaty na podstawie nazwy i kategorii.
     */
    private function detect_certifications($product)
    {
        $name = strtolower($product['n'] ?? '');
        $certifications = [];

        // Certyfikaty dla produktów spożywczych
        if (strpos($name, 'kubek') !== false || strpos($name, 'butelka') !== false) {
            $certifications[] = 'Bezpieczny dla żywności';
            $certifications[] = 'FDA';
        }

        // Certyfikaty ekologiczne
        if (strpos($name, 'bambus') !== false || strpos($name, 'ekologiczny') !== false) {
            $certifications[] = 'Ekologiczny';
        }

        // Certyfikaty dla elektroniki
        if (strpos($name, 'power bank') !== false || strpos($name, 'ładowarka') !== false) {
            $certifications[] = 'CE';
        }

        return $certifications;
    }

    /**
     * Zwraca odpowiednie technologie druku dla produktu.
     */
    private function get_suitable_printing_technologies($product)
    {
        $suitable_techs = [];

        if (empty($product['categories']['category'])) {
            return $suitable_techs;
        }

        $categories = $product['categories']['category'];
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        // Mapowanie kategorii na technologie druku
        $category_tech_mapping = [
            '14010' => ['C1', 'C2', 'C3'], // Kubki - druk ceramiczny
            '14020' => ['C1', 'C2', 'DG1'], // Akcesoria coffee - ceramiczny i cyfrowy
            '10050' => ['DTA1', 'DTA2', 'DTA3'], // T-shirty - transfer
            '10040' => ['DTA1', 'DTA2', 'DTB1'], // Bluzy - transfer
            '10060' => ['DTA1', 'DTA2'], // Polo - transfer
            '10010' => ['DTA1-HS', 'DTC1'], // Czapki - transfer + hafciarstwo
            '4000' => ['DTB1', 'DTB2'], // Torby - transfer B
            '7010' => ['DO1', 'DO2', 'DO3'], // Doming - breloki, monety
            '2010' => ['DG1', 'DG2'], // Notesy - cyfrowy
            '1010' => ['DG1'], // Długopisy - grawer/nadruk
        ];

        foreach ($categories as $category) {
            if (!empty($category['externalId'])) {
                $cat_id = $category['externalId'];
                if (isset($category_tech_mapping[$cat_id])) {
                    $suitable_techs = array_merge($suitable_techs, $category_tech_mapping[$cat_id]);
                }
            }
        }

        // Zwróć dane w poprawnym formacie
        $result = [];
        foreach (array_unique($suitable_techs) as $tech_code) {
            $result[] = [
                'code' => $tech_code,
                'name' => isset($this->printing_technologies[$tech_code]) ? $this->printing_technologies[$tech_code] : $tech_code
            ];
        }

        return $result;
    }

    /**
     * Buduje kompletną nazwę produktu z wszystkich dostępnych danych.
     *
     * @param array $product
     * @param string $item_number
     * @return string
     */
    private function build_complete_product_name($product, $item_number)
    {
        $name_parts = [];

        // Dodaj designName jeśli istnieje
        if (!empty($product['designName'])) {
            $name_parts[] = $product['designName'];
        }

        // Dodaj główną nazwę produktu
        if (!empty($product['n'])) {
            $name_parts[] = $product['n'];
        }

        // Dodaj informacje o kolorze jeśli są dostępne
        if (!empty($product['primaryColor'])) {
            $name_parts[] = '(' . $product['primaryColor'] . ')';
        }

        // Jeśli brak nazw, użyj kodu produktu
        if (empty($name_parts)) {
            $name_parts[] = 'Produkt ' . $item_number;
        }

        return implode(' ', $name_parts);
    }

    /**
     * Buduje kompletny opis produktu.
     *
     * @param array $product
     * @return string
     */
    private function build_complete_description($product)
    {
        $description_parts = [];

        // Główny opis
        if (!empty($product['descriptions'])) {
            $description_parts[] = $product['descriptions'];
        }

        // Dodaj informacje o wymiarach
        if (!empty($product['width']) && !empty($product['height']) && !empty($product['depth'])) {
            $dimensions = "Wymiary: {$product['width']} x {$product['height']} x {$product['depth']} cm";
            $description_parts[] = $dimensions;
        }

        // Dodaj wagę
        if (!empty($product['individualProductWeightGram'])) {
            $weight = "Waga: {$product['individualProductWeightGram']} g";
            $description_parts[] = $weight;
        }

        // Dodaj materiał jeśli wykryty
        $material = $this->detect_material($product);
        if ($material) {
            $description_parts[] = "Materiał: $material";
        }

        // Dodaj minimalne zamówienie
        if (!empty($product['minimumOrderQuantity'])) {
            $min_order = "Minimalne zamówienie: {$product['minimumOrderQuantity']} szt.";
            $description_parts[] = $min_order;
        }

        return implode("\n\n", $description_parts);
    }

    /**
     * POPRAWIONA funkcja dodająca ceny zgodnie z nową strukturą ANDA.
     */
    private function add_pricing_data($xml, $product_element, $price_data)
    {
        // Cena regularna (listPrice)
        if (!empty($price_data['regular_price']) || !empty($price_data['listPrice'])) {
            $regular_price = $price_data['regular_price'] ?? $price_data['listPrice'];
            $this->add_xml_element($xml, $product_element, 'regular_price', $regular_price);
            error_log("MHI ANDA: Dodano regular_price: $regular_price");
        }

        // Cena promocyjna (discountPrice)
        if (!empty($price_data['sale_price']) || !empty($price_data['discountPrice'])) {
            $sale_price = $price_data['sale_price'] ?? $price_data['discountPrice'];
            $this->add_xml_element($xml, $product_element, 'sale_price', $sale_price);
            error_log("MHI ANDA: Dodano sale_price: $sale_price");
        }

        // Waluta
        if (!empty($price_data['currency'])) {
            $this->add_xml_element($xml, $product_element, 'currency', $price_data['currency']);
        }
    }

    /**
     * Dodaje kompletne kategorie z pełną hierarchią.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     */
    private function add_complete_categories($xml, $product_element, $product)
    {
        $categories_element = $xml->createElement('categories');
        $product_element->appendChild($categories_element);

        $categories_added = [];

        // Znajdź kategorie produktu na podstawie różnych kryteriów
        $product_categories = $this->find_product_categories($product);

        // Jeśli nie znaleziono kategorii, dodaj domyślną
        if (empty($product_categories)) {
            $product_categories = [
                [
                    'name' => 'Produkty reklamowe',
                    'id' => '',
                    'path' => 'Produkty reklamowe'
                ]
            ];
            error_log("MHI ANDA: Używam domyślnej kategorii dla produktu: " . ($product['itemNumber'] ?? 'nieznany'));
        }

        foreach ($product_categories as $category_info) {
            $category_name = $category_info['name'];
            $category_path = $category_info['path'] ?? $category_name;

            // KATEGORIE JAK W AXPOL - proste <category>nazwa</category>
            if (!in_array($category_name, $categories_added)) {
                // Główna kategoria
                $category_element = $xml->createElement('category');
                $category_element->nodeValue = htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8');
                $categories_element->appendChild($category_element);
                $categories_added[] = $category_name;

                // Jeśli jest hierarchia (path), dodaj też pełną ścieżkę
                if (!empty($category_path) && $category_path !== $category_name) {
                    $full_path_element = $xml->createElement('category');
                    $full_path_element->nodeValue = htmlspecialchars($category_path, ENT_QUOTES, 'UTF-8');
                    $categories_element->appendChild($full_path_element);
                    error_log("MHI ANDA: Dodano hierarchię kategorii: $category_path");
                }
            }
        }
    }

    /**
     * Znajduje kategorie produktu używając różnych metod.
     *
     * @param array $product
     * @return array
     */
    private function find_product_categories($product)
    {
        $categories = [];

        // Metoda 1: Z danych produktu - sprawdź różne struktury
        if (!empty($product['categories'])) {
            if (isset($product['categories']['category'])) {
                $product_categories = $product['categories']['category'];
                if (isset($product_categories['name'])) {
                    $product_categories = [$product_categories];
                }

                foreach ($product_categories as $category) {
                    if (!empty($category['name'])) {
                        $categories[] = [
                            'name' => $category['name'],
                            'id' => $category['externalId'] ?? '',
                            'path' => $category['name']
                        ];
                    }
                }
            } elseif (is_string($product['categories'])) {
                // Kategorie jako string oddzielone przecinkami
                $cat_names = explode(',', $product['categories']);
                foreach ($cat_names as $cat_name) {
                    $cat_name = trim($cat_name);
                    if (!empty($cat_name)) {
                        $categories[] = [
                            'name' => $cat_name,
                            'id' => '',
                            'path' => $cat_name
                        ];
                    }
                }
            }
        }

        // Metoda 2: Wyszukaj w danych kategorii po categoryNumbers
        if (empty($categories) && !empty($product['categoryNumbers'])) {
            $cat_numbers = is_array($product['categoryNumbers']) ?
                $product['categoryNumbers'] :
                explode(',', $product['categoryNumbers']);

            foreach ($cat_numbers as $cat_number) {
                $cat_number = trim($cat_number);
                if (!empty($this->flat_categories[$cat_number])) {
                    $cat_data = $this->flat_categories[$cat_number];
                    $categories[] = [
                        'name' => $cat_data['name'],
                        'id' => $cat_data['id'],
                        'path' => $cat_data['path']
                    ];
                }
            }
        }

        // Metoda 3: Mapowanie na podstawie nazwy produktu
        if (empty($categories)) {
            $mapped_categories = $this->map_product_to_categories($product);
            foreach ($mapped_categories as $cat_name) {
                $categories[] = [
                    'name' => $cat_name,
                    'id' => '',
                    'path' => $cat_name
                ];
            }
        }

        // Metoda 4: Wyszukiwanie w hierarchii kategorii
        $enhanced_categories = [];
        foreach ($categories as $category) {
            $enhanced_cat = $this->enhance_category_with_hierarchy($category);
            if ($enhanced_cat) {
                $enhanced_categories[] = $enhanced_cat;
            }
        }

        // Loguj dla debugowania
        if (empty($categories)) {
            error_log("MHI ANDA: Brak kategorii dla produktu: " . ($product['itemNumber'] ?? 'nieznany'));
        }

        return !empty($enhanced_categories) ? $enhanced_categories : $categories;
    }

    /**
     * Wzbogaca kategorię o informacje z hierarchii.
     *
     * @param array $category
     * @return array|null
     */
    private function enhance_category_with_hierarchy($category)
    {
        if (!empty($category['id']) && isset($this->flat_categories[$category['id']])) {
            $flat_cat = $this->flat_categories[$category['id']];
            return [
                'name' => $flat_cat['name'],
                'id' => $flat_cat['id'],
                'path' => $flat_cat['path']
            ];
        }

        return $category;
    }

    /**
     * Czyści nazwę atrybutu.
     *
     * @param string $name
     * @return string
     */
    private function sanitize_attribute_name($name)
    {
        // Usuń polskie znaki i nieodpowiednie znaki
        $name = str_replace(
            ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż'],
            ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'A', 'C', 'E', 'L', 'N', 'O', 'S', 'Z', 'Z'],
            $name
        );

        // Usuń spacje i zastąp je podkreślnikami
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        return $name;
    }

    /**
     * Dodaje kompletne atrybuty produktu.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     * @param string $item_number
     */
    private function add_complete_attributes($xml, $product_element, $product, $item_number)
    {
        $attributes_element = $xml->createElement('attributes');
        $product_element->appendChild($attributes_element);

        // === PODSTAWOWE ATRYBUTY ===

        // Materiał
        $material = $this->detect_material($product);
        if (!empty($material)) {
            $this->add_complete_attribute($xml, $attributes_element, 'Materiał', $material);
        }

        // Wymiary
        if (!empty($product['width']) && !empty($product['height']) && !empty($product['depth'])) {
            $dimensions = $product['width'] . ' x ' . $product['height'] . ' x ' . $product['depth'] . ' cm';
            $this->add_complete_attribute($xml, $attributes_element, 'Wymiary', $dimensions);
        }

        // Wymiary pojedyncze
        if (!empty($product['width'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Szerokość', $product['width'] . ' cm');
        }
        if (!empty($product['height'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Wysokość', $product['height'] . ' cm');
        }
        if (!empty($product['depth'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Głębokość', $product['depth'] . ' cm');
        }

        // Waga
        if (!empty($product['individualProductWeightGram'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Waga', $product['individualProductWeightGram'] . ' g');
        }

        // Kolory
        if (!empty($product['primaryColor'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Kolor główny', $product['primaryColor']);
        }
        if (!empty($product['secondaryColor'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Kolor dodatkowy', $product['secondaryColor']);
        }

        // Minimalna ilość zamówienia
        if (!empty($product['minimumOrderQuantity'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Minimalne zamówienie', $product['minimumOrderQuantity'] . ' szt.');
        }

        // Pakowanie
        if (!empty($product['packaging'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Pakowanie', $product['packaging']);
        }

        // Certyfikaty
        $certifications = $this->detect_certifications($product);
        if (!empty($certifications)) {
            $this->add_complete_attribute($xml, $attributes_element, 'Certyfikaty', implode(', ', $certifications));
        }

        // Kraj pochodzenia
        if (!empty($product['countryOfOrigin'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Kraj pochodzenia', $product['countryOfOrigin']);
        }

        // EAN
        if (!empty($product['eanCode'])) {
            $this->add_complete_attribute($xml, $attributes_element, 'Kod EAN', $product['eanCode']);
        }

        // Status produktu
        $custom_production = !empty($product['customProduction']) && $product['customProduction'] == '1' ? 'Tak' : 'Nie';
        $this->add_complete_attribute($xml, $attributes_element, 'Produkt na zamówienie', $custom_production);

        $available = empty($product['temporarilyUnavailable']) || $product['temporarilyUnavailable'] != '1' ? 'Tak' : 'Nie';
        $this->add_complete_attribute($xml, $attributes_element, 'Dostępny', $available);

        // === ATRYBUTY Z SPECIFICATION ===
        $this->add_specification_attributes($xml, $attributes_element, $product);
    }

    /**
     * Dodaje atrybuty z sekcji specification produktu.
     *
     * @param DOMDocument $xml
     * @param DOMElement $attributes_element
     * @param array $product
     */
    private function add_specification_attributes($xml, $attributes_element, $product)
    {
        if (empty($product['specification']['property'])) {
            return;
        }

        $properties = $product['specification']['property'];

        // Jeśli to pojedyncza właściwość
        if (isset($properties['n'])) {
            $properties = [$properties];
        }

        foreach ($properties as $property) {
            if (!empty($property['n']) && !empty($property['values']['value'])) {
                $attr_name = $property['n'];
                $values = is_array($property['values']['value']) ?
                    $property['values']['value'] :
                    [$property['values']['value']];

                $attr_value = implode(', ', $values);
                $this->add_complete_attribute($xml, $attributes_element, $attr_name, $attr_value);
            }
        }
    }

    /**
     * POPRAWIONA funkcja dodająca technologie druku jako zwykłe atrybuty (bez cen).
     */
    private function add_printing_technologies_as_attributes($xml, $product_element, $product)
    {
        // Znajdź odpowiednie technologie dla tego produktu
        $suitable_technologies = $this->get_suitable_printing_technologies($product);

        if (!empty($suitable_technologies)) {
            // Dodaj technologie jako zwykły atrybut
            $tech_names = array_map(function ($tech) {
                return $tech['name'];
            }, $suitable_technologies);

            // Znajdź sekcję attributes i dodaj technologie
            $xpath = new DOMXPath($xml);
            $attributes_nodes = $xpath->query('attributes', $product_element);
            if ($attributes_nodes->length > 0) {
                $attributes_element = $attributes_nodes->item(0);
            } else {
                $attributes_element = $xml->createElement('attributes');
                $product_element->appendChild($attributes_element);
            }

            // Dodaj technologie jako atrybut z opcjami do wyboru
            $this->add_complete_attribute($xml, $attributes_element, 'Dostępne technologie znakowania', implode(', ', $tech_names));

            error_log("MHI ANDA: Dodano technologie jako atrybut: " . implode(', ', $tech_names));
        }

        // USUŃ sekcję printing_technologies (nie dodajemy jej z cenami)
        // Zgodnie z wymaganiem użytkownika - technologie mają być tylko jako atrybuty do wyboru
    }

    /**
     * Dodaje dane znakowania z labeling.xml.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param string $item_number
     */
    private function add_labeling_data($xml, $product_element, $item_number)
    {
        $labeling_section = $xml->createElement('labeling_info');
        $product_element->appendChild($labeling_section);

        // Znajdź dane znakowania dla tego produktu
        $labeling_data = $this->get_product_labeling_data($item_number);

        if (!empty($labeling_data)) {
            foreach ($labeling_data as $key => $value) {
                if (!empty($value)) {
                    $this->add_xml_element($xml, $labeling_section, $key, $value);
                }
            }
        }

        // Dodaj też podstawowe informacje o znakowaniu z produktu
        // (te dane będą dostępne jeśli są w strukturze produktu)
    }

    /**
     * Pobiera dane znakowania dla produktu.
     *
     * @param string $item_number
     * @return array
     */
    private function get_product_labeling_data($item_number)
    {
        foreach ($this->labeling_data as $labeling) {
            if (isset($labeling['itemNumber']) && $labeling['itemNumber'] === $item_number) {
                return $labeling;
            }
        }
        return [];
    }

    /**
     * Dodaje kompletne meta data z wszystkimi informacjami.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     * @param string $item_number
     */
    private function add_complete_meta_data($xml, $product_element, $product, $item_number)
    {
        $meta_section = $xml->createElement('meta_data');
        $product_element->appendChild($meta_section);

        // Kod ANDA
        $this->add_complete_meta($xml, $meta_section, '_anda_code', $item_number);

        // Wszystkie dostępne dane produktu jako meta
        $meta_fields = [
            'eanCode' => '_anda_ean',
            'countryOfOrigin' => '_anda_country_origin',
            'customProduction' => '_anda_custom_production',
            'temporarilyUnavailable' => '_anda_unavailable',
            'minimumOrderQuantity' => '_anda_min_order_qty',
            'packaging' => '_anda_packaging',
            'designName' => '_anda_design_name',
            'primaryColor' => '_anda_primary_color',
            'secondaryColor' => '_anda_secondary_color',
            'individualProductWeightGram' => '_anda_weight_gram',
            'width' => '_anda_width',
            'height' => '_anda_height',
            'depth' => '_anda_depth'
        ];

        foreach ($meta_fields as $product_field => $meta_key) {
            if (!empty($product[$product_field])) {
                $this->add_complete_meta($xml, $meta_section, $meta_key, $product[$product_field]);
            }
        }

        // Dane cenowe
        $price_data = $this->get_product_price($item_number);
        if (!empty($price_data)) {
            foreach ($price_data as $price_key => $price_value) {
                if (!empty($price_value)) {
                    $this->add_complete_meta($xml, $meta_section, '_anda_price_' . $price_key, $price_value);
                }
            }
        }

        // Stan magazynowy
        $stock = $this->get_product_stock($item_number);
        $this->add_complete_meta($xml, $meta_section, '_anda_stock', $stock);
    }

    /**
     * Dodaje kompletne zdjęcia produktu.
     *
     * @param DOMDocument $xml
     * @param DOMElement $product_element
     * @param array $product
     */
    private function add_complete_images($xml, $product_element, $product)
    {
        $images_element = $xml->createElement('images');
        $product_element->appendChild($images_element);

        $images_added = [];

        // Główne zdjęcie
        if (!empty($product['primaryImage'])) {
            $this->add_image_element($xml, $images_element, $product['primaryImage'], 'primary');
            $images_added[] = $product['primaryImage'];
        }

        // Dodatkowe zdjęcia z różnych źródeł
        $image_fields = ['secondaryImage', 'image1', 'image2', 'image3', 'image4', 'image5'];
        foreach ($image_fields as $field) {
            if (!empty($product[$field]) && !in_array($product[$field], $images_added)) {
                $this->add_image_element($xml, $images_element, $product[$field], 'gallery');
                $images_added[] = $product[$field];
            }
        }

        // Zdjęcia z sekcji images
        if (!empty($product['images']['image'])) {
            $gallery_images = $product['images']['image'];

            if (is_string($gallery_images)) {
                $gallery_images = [$gallery_images];
            }

            if (is_array($gallery_images)) {
                foreach ($gallery_images as $image_url) {
                    if (is_string($image_url) && !empty($image_url) && !in_array($image_url, $images_added)) {
                        $this->add_image_element($xml, $images_element, $image_url, 'gallery');
                        $images_added[] = $image_url;
                    }
                }
            }
        }
    }

    /**
     * Dodaje pojedynczy element zdjęcia.
     *
     * @param DOMDocument $xml
     * @param DOMElement $images_element
     * @param string $image_url
     * @param string $type
     */
    private function add_image_element($xml, $images_element, $image_url, $type = 'gallery')
    {
        $image_element = $xml->createElement('image');
        $image_element->setAttribute('src', $image_url);
        $image_element->setAttribute('type', $type);
        $images_element->appendChild($image_element);
    }

    /**
     * Dodaje pojedynczy atrybut w kompletnej strukturze.
     *
     * @param DOMDocument $xml
     * @param DOMElement $attributes_element
     * @param string $name
     * @param string $value
     */
    private function add_complete_attribute($xml, $attributes_element, $name, $value)
    {
        if (empty($value))
            return;

        $attribute_element = $xml->createElement('attribute');

        $this->add_xml_element($xml, $attribute_element, 'name', $name);
        $this->add_xml_element($xml, $attribute_element, 'value', $value);
        $this->add_xml_element($xml, $attribute_element, 'visible', '1');

        $attributes_element->appendChild($attribute_element);
    }

    /**
     * Dodaje pojedynczy meta data element.
     *
     * @param DOMDocument $xml
     * @param DOMElement $meta_section
     * @param string $key
     * @param string $value
     */
    private function add_complete_meta($xml, $meta_section, $key, $value)
    {
        if (empty($value))
            return;

        $meta_element = $xml->createElement('meta');

        $this->add_xml_element($xml, $meta_element, 'key', $key);
        $this->add_xml_element($xml, $meta_element, 'value', $value);

        $meta_section->appendChild($meta_element);
    }
}
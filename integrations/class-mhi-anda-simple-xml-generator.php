<?php
/**
 * UPROSZCZONY Generator XML dla ANDA
 * Każdy produkt z unikalnym SKU jako osobny produkt simple
 * 
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_ANDA_Simple_XML_Generator
 * 
 * Generuje uproszczone pliki XML dla ANDA - każdy SKU jako osobny produkt
 */
class MHI_ANDA_Simple_XML_Generator
{
    /**
     * Nazwa hurtowni.
     */
    private $name = 'anda';

    /**
     * Ścieżka do katalogu z plikami XML.
     */
    private $source_dir;

    /**
     * Ścieżka do katalogu docelowego.
     */
    private $target_dir;

    /**
     * Dane produktów z products.xml.
     */
    private $products_data = [];

    /**
     * Dane cenowe z prices.xml.
     */
    private $prices_data = [];

    /**
     * Dane stanów magazynowych z inventories.xml.
     */
    private $inventories_data = [];

    /**
     * Dane kategorii z categories.xml.
     */
    private $categories_data = [];

    /**
     * Dane znakowania z labeling.xml.
     */
    private $labeling_data = [];

    /**
     * Mapa kategorii ANDA.
     */
    private $category_map = [];

    /**
     * Płaska struktura kategorii.
     */
    private $flat_categories = [];

    /**
     * Konstruktor.
     */
    public function __construct()
    {
        $this->source_dir = wp_upload_dir()['basedir'] . '/wholesale/anda';
        $this->target_dir = wp_upload_dir()['basedir'] . '/wholesale/anda';

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
            '5000' => 'Biuro i szkoła',
            '5010' => 'Długopisy i pisaki',
            '5020' => 'Notatniki i kalendarze',
            '5030' => 'Organizery i plannery',
            '5040' => 'Akcesoria biurowe',
            '5050' => 'Plecaki szkolne',
            '5060' => 'Piórniki i organizery',
            '5070' => 'Papeterie i kartki',
            '5080' => 'Akcesoria do tablic',
            '6000' => 'Sport i rekreacja',
            '6010' => 'Akcesoria fitness',
            '6020' => 'Gry i zabawy',
            '6030' => 'Akcesoria rowerowe',
            '6040' => 'Akcesoria do pływania',
            '6050' => 'Akcesoria do jogi',
            '6060' => 'Akcesoria do golfa',
            '6070' => 'Akcesoria do tenisa',
            '6080' => 'Akcesoria do piłki nożnej',
            '7000' => 'Zdrowie i uroda',
            '7010' => 'Akcesoria do higieny',
            '7020' => 'Kosmetyczki i organizery',
            '7030' => 'Akcesoria do makijażu',
            '7040' => 'Akcesoria do pielęgnacji',
            '7050' => 'Akcesoria do masażu',
            '7060' => 'Akcesoria do manicure',
            '7070' => 'Akcesoria do pedicure',
            '7080' => 'Akcesoria do depilacji',
            '8000' => 'Dom i ogród',
            '8010' => 'Dekoracje domowe',
            '8020' => 'Akcesoria do łazienki',
            '8030' => 'Akcesoria do sypialni',
            '8040' => 'Akcesoria do kuchni',
            '8050' => 'Akcesoria do ogrodu',
            '8060' => 'Akcesoria do prania',
            '8070' => 'Akcesoria do sprzątania',
            '8080' => 'Akcesoria do organizacji',
            '9000' => 'Dzieci i zabawki',
            '9010' => 'Zabawki edukacyjne',
            '9020' => 'Zabawki kreatywne',
            '9030' => 'Zabawki ruchowe',
            '9040' => 'Zabawki muzyczne',
            '9050' => 'Zabawki plastyczne',
            '9060' => 'Zabawki logiczne',
            '9070' => 'Zabawki konstrukcyjne',
            '9080' => 'Zabawki tematyczne',
            '10000' => 'Święta i okazje',
            '10010' => 'Boże Narodzenie',
            '10020' => 'Wielkanoc',
            '10030' => 'Walentynki',
            '10040' => 'Dzień Matki',
            '10050' => 'Dzień Ojca',
            '10060' => 'Dzień Dziecka',
            '10070' => 'Urodziny',
            '10080' => 'Rocznice',
            '11000' => 'Profesjonalne',
            '11010' => 'Akcesoria medyczne',
            '11020' => 'Akcesoria fryzjerskie',
            '11030' => 'Akcesoria kosmetyczne',
            '11040' => 'Akcesoria do masażu',
            '11050' => 'Akcesoria do tatuażu',
            '11060' => 'Akcesoria do piercingu',
            '11070' => 'Akcesoria do manicure',
            '11080' => 'Akcesoria do pedicure',
            '12000' => 'Promocyjne',
            '12010' => 'Gadżety reklamowe',
            '12020' => 'Materiały promocyjne',
            '12030' => 'Akcesoria eventowe',
            '12040' => 'Akcesoria targowe',
            '12050' => 'Akcesoria konferencyjne',
            '12060' => 'Akcesoria szkoleniowe',
            '12070' => 'Akcesoria motywacyjne',
            '12080' => 'Akcesoria integracyjne'
        ];
    }

    /**
     * Generuje uproszczony plik XML dla importu.
     */
    public function generate_simple_xml($limit = 0)
    {
        try {
            // Załaduj dane
            $this->load_all_data($limit);

            // Utwórz XML
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');

            // Dodaj produkty
            foreach ($this->products_data as $item_number => $product) {
                $this->add_simple_product($xml, $product, $item_number);
            }

            // Zapisz plik
            $xml_file = $this->target_dir . '/woocommerce_import_anda_simple.xml';
            $xml->asXML($xml_file);

            return [
                'success' => true,
                'file' => 'woocommerce_import_anda_simple.xml',
                'products_count' => count($this->products_data),
                'file_size' => filesize($xml_file)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Załaduj wszystkie dane.
     */
    private function load_all_data($limit = 0)
    {
        // Załaduj produkty
        $this->products_data = $this->load_xml_file('products.xml', 'product', $limit);

        // Załaduj ceny
        $this->prices_data = $this->load_xml_file('prices.xml', 'price', $limit);

        // Załaduj stany magazynowe
        $this->inventories_data = $this->load_xml_file('inventories.xml', 'inventory', $limit);

        // Załaduj kategorie
        $this->load_categories_xml();

        // Załaduj znakowanie
        $this->labeling_data = $this->load_xml_file('labeling.xml', 'labeling', $limit);
    }

    /**
     * Załaduj plik XML.
     */
    private function load_xml_file($filename, $element_name = 'product', $limit = 1000)
    {
        $file_path = $this->source_dir . '/' . $filename;

        if (!file_exists($file_path)) {
            return [];
        }

        $xml = simplexml_load_file($file_path);
        if (!$xml) {
            return [];
        }

        $data = [];
        $count = 0;

        foreach ($xml->children() as $element) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }

            $element_data = $this->xml_to_array($element);

            if (isset($element_data['item_number'])) {
                $data[$element_data['item_number']] = $element_data;
            }

            $count++;
        }

        return $data;
    }

    /**
     * Konwertuj XML na tablicę.
     */
    private function xml_to_array($xml)
    {
        $array = [];

        foreach ($xml->children() as $child) {
            $name = $child->getName();
            $value = (string) $child;

            if (count($child->children()) > 0) {
                $array[$name] = $this->xml_to_array($child);
            } else {
                $array[$name] = $value;
            }
        }

        return $array;
    }

    /**
     * Załaduj kategorie.
     */
    private function load_categories_xml()
    {
        $file_path = $this->source_dir . '/categories.xml';

        if (!file_exists($file_path)) {
            return;
        }

        $xml = simplexml_load_file($file_path);
        if (!$xml) {
            return;
        }

        $this->categories_data = $this->xml_to_array($xml);
        $this->build_flat_categories();
    }

    /**
     * Zbuduj płaską strukturę kategorii.
     */
    private function build_flat_categories()
    {
        $this->flat_categories = [];

        if (isset($this->categories_data['categories'])) {
            foreach ($this->categories_data['categories'] as $category) {
                $this->flatten_categories($category);
            }
        }
    }

    /**
     * Spłaszcz kategorie rekurencyjnie.
     */
    private function flatten_categories($category, $parent_path = '')
    {
        if (!isset($category['category'])) {
            return;
        }

        foreach ($category['category'] as $cat) {
            $category_id = isset($cat['category_id']) ? $cat['category_id'] : '';
            $category_name = isset($cat['category_name']) ? $cat['category_name'] : '';

            $current_path = $parent_path ? $parent_path . ' > ' . $category_name : $category_name;

            if ($category_id) {
                $this->flat_categories[$category_id] = [
                    'id' => $category_id,
                    'name' => $category_name,
                    'path' => $current_path
                ];
            }

            // Rekurencyjnie przetwórz podkategorie
            if (isset($cat['categories'])) {
                $this->flatten_categories($cat['categories'], $current_path);
            }
        }
    }

    /**
     * Dodaj prosty produkt do XML.
     */
    private function add_simple_product($xml, $product, $item_number)
    {
        $item = $xml->addChild('item');

        // Podstawowe informacje
        $this->add_xml_element($xml, $item, 'g:id', $item_number);
        $this->add_xml_element($xml, $item, 'g:title', $this->build_product_name($product, $item_number));
        $this->add_xml_element($xml, $item, 'g:description', $this->build_product_description($product));
        $this->add_xml_element($xml, $item, 'g:link', '');
        $this->add_xml_element($xml, $item, 'g:image_link', $this->get_product_image($product));
        $this->add_xml_element($xml, $item, 'g:additional_image_link', '');
        $this->add_xml_element($xml, $item, 'g:availability', $this->get_product_availability($item_number));
        $this->add_xml_element($xml, $item, 'g:price', $this->get_product_price($item_number));
        $this->add_xml_element($xml, $item, 'g:brand', 'ANDA');
        $this->add_xml_element($xml, $item, 'g:gtin', $item_number);
        $this->add_xml_element($xml, $item, 'g:mpn', $item_number);
        $this->add_xml_element($xml, $item, 'g:condition', 'new');

        // Kategorie
        $this->add_product_categories($xml, $item, $product);

        // Atrybuty
        $this->add_product_attributes($xml, $item, $product, $item_number);

        // Meta dane
        $this->add_product_meta($xml, $item, $product, $item_number);
    }

    /**
     * Zbuduj nazwę produktu.
     */
    private function build_product_name($product, $item_number)
    {
        $name = isset($product['product_name']) ? $product['product_name'] : 'Produkt ANDA';

        // Dodaj informacje o materiale
        if (isset($product['material'])) {
            $name .= ' - ' . $product['material'];
        }

        // Dodaj informacje o rozmiarze
        if (isset($product['size'])) {
            $name .= ' (' . $product['size'] . ')';
        }

        return $name;
    }

    /**
     * Zbuduj opis produktu.
     */
    private function build_product_description($product)
    {
        $description = '';

        if (isset($product['product_name'])) {
            $description .= $product['product_name'] . "\n\n";
        }

        if (isset($product['material'])) {
            $description .= "Materiał: " . $product['material'] . "\n";
        }

        if (isset($product['size'])) {
            $description .= "Rozmiar: " . $product['size'] . "\n";
        }

        if (isset($product['color'])) {
            $description .= "Kolor: " . $product['color'] . "\n";
        }

        if (isset($product['description'])) {
            $description .= "\n" . $product['description'];
        }

        return $description;
    }

    /**
     * Pobierz cenę produktu.
     */
    private function get_product_price($item_number)
    {
        if (isset($this->prices_data[$item_number])) {
            $price_data = $this->prices_data[$item_number];

            if (isset($price_data['price'])) {
                $price = floatval($price_data['price']);
                return number_format($price, 2, '.', '') . ' PLN';
            }
        }

        return '0.00 PLN';
    }

    /**
     * Pobierz dostępność produktu.
     */
    private function get_product_availability($item_number)
    {
        if (isset($this->inventories_data[$item_number])) {
            $inventory_data = $this->inventories_data[$item_number];

            if (isset($inventory_data['quantity'])) {
                $quantity = intval($inventory_data['quantity']);
                return $quantity > 0 ? 'in stock' : 'out of stock';
            }
        }

        return 'out of stock';
    }

    /**
     * Pobierz obraz produktu.
     */
    private function get_product_image($product)
    {
        if (isset($product['image_url'])) {
            return $product['image_url'];
        }

        return '';
    }

    /**
     * Dodaj kategorie produktu.
     */
    private function add_product_categories($xml, $item, $product)
    {
        if (isset($product['category_id'])) {
            $category_id = $product['category_id'];

            if (isset($this->flat_categories[$category_id])) {
                $category = $this->flat_categories[$category_id];
                $this->add_xml_element($xml, $item, 'g:product_type', $category['path']);
            }
        }
    }

    /**
     * Dodaj atrybuty produktu.
     */
    private function add_product_attributes($xml, $item, $product, $item_number)
    {
        // Materiał
        if (isset($product['material'])) {
            $this->add_xml_element($xml, $item, 'g:material', $product['material']);
        }

        // Rozmiar
        if (isset($product['size'])) {
            $this->add_xml_element($xml, $item, 'g:size', $product['size']);
        }

        // Kolor
        if (isset($product['color'])) {
            $this->add_xml_element($xml, $item, 'g:color', $product['color']);
        }

        // Wymiary
        if (isset($product['dimensions'])) {
            $this->add_xml_element($xml, $item, 'g:dimensions', $product['dimensions']);
        }

        // Waga
        if (isset($product['weight'])) {
            $this->add_xml_element($xml, $item, 'g:weight', $product['weight']);
        }
    }

    /**
     * Dodaj meta dane produktu.
     */
    private function add_product_meta($xml, $item, $product, $item_number)
    {
        // SKU
        $this->add_xml_element($xml, $item, 'g:sku', $item_number);

        // Hurtownia
        $this->add_xml_element($xml, $item, 'g:supplier', 'ANDA');

        // Ilość w magazynie
        $stock = 0;
        if (isset($this->inventories_data[$item_number]['quantity'])) {
            $stock = intval($this->inventories_data[$item_number]['quantity']);
        }
        $this->add_xml_element($xml, $item, 'g:stock_quantity', $stock);

        // Cena
        $price = 0;
        if (isset($this->prices_data[$item_number]['price'])) {
            $price = floatval($this->prices_data[$item_number]['price']);
        }
        $this->add_xml_element($xml, $item, 'g:price_value', $price);
    }

    /**
     * Dodaj element XML.
     */
    private function add_xml_element($xml, $parent, $name, $value)
    {
        if (!empty($value)) {
            $element = $parent->addChild($name, htmlspecialchars($value));
        }
    }

    /**
     * Pobierz informacje o wygenerowanym pliku.
     */
    public function get_generated_file_info()
    {
        $xml_file = $this->target_dir . '/woocommerce_import_anda_simple.xml';

        if (file_exists($xml_file)) {
            return [
                'file' => 'woocommerce_import_anda_simple.xml',
                'size' => filesize($xml_file),
                'date' => date('Y-m-d H:i:s', filemtime($xml_file)),
                'exists' => true
            ];
        }

        return [
            'file' => 'woocommerce_import_anda_simple.xml',
            'exists' => false
        ];
    }
}
<?php
/**
 * Klasa generatora plików XML importu WooCommerce dla hurtowni Axpol.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Axpol_WC_XML_Generator
 * 
 * Generuje pliki XML kompatybilne z WooCommerce na podstawie danych Axpol.
 */
class MHI_Axpol_WC_XML_Generator
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    private $name = 'axpol';

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
     * Dane technik nadruku.
     *
     * @var array
     */
    private $print_data = [];

    /**
     * Informacje o cenach nadruków.
     *
     * @var array
     */
    private $print_price_data = [];

    /**
     * Konstruktor klasy.
     */
    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->source_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name;
        $this->target_dir = $this->source_dir;
    }

    /**
     * Wczytuje dane z plików XML Axpol.
     *
     * @return bool True jeśli dane zostały wczytane, false w przeciwnym razie.
     */
    public function load_xml_data()
    {
        try {
            // Wczytaj dane produktów
            $product_data_file = $this->source_dir . '/axpol_product_data_PL.xml';
            if (file_exists($product_data_file)) {
                $xml = simplexml_load_file($product_data_file);
                if ($xml) {
                    foreach ($xml->Row as $row) {
                        $code = (string) $row->CodeERP;
                        $this->product_data[$code] = $row;
                    }
                }
            } else {
                MHI_Logger::error('Nie znaleziono pliku z danymi produktów Axpol: ' . $product_data_file);
                return false;
            }

            // Wczytaj dane o stanach magazynowych
            $stock_data_file = $this->source_dir . '/axpol_stocklist_PL.xml';
            if (file_exists($stock_data_file)) {
                $xml = simplexml_load_file($stock_data_file);
                if ($xml) {
                    foreach ($xml->item as $item) {
                        $code = (string) $item->Kod;
                        $this->stock_data[$code] = $item;
                    }
                }
            } else {
                MHI_Logger::warning('Nie znaleziono pliku ze stanami magazynowymi Axpol: ' . $stock_data_file);
            }

            // Wczytaj dane o technikach nadruku
            $print_data_file = $this->source_dir . '/axpol_print_data_PL.xml';
            if (file_exists($print_data_file)) {
                $xml = simplexml_load_file($print_data_file);
                if ($xml) {
                    foreach ($xml->Row as $row) {
                        $code = (string) $row->CodeERP;
                        $this->print_data[$code] = $row;
                    }
                }
            } else {
                MHI_Logger::warning('Nie znaleziono pliku z danymi technik nadruku Axpol: ' . $print_data_file);
            }

            // Wczytaj dane o cenach nadruków (opcjonalnie)
            $print_price_file = $this->source_dir . '/axpol_print_pricelist_PL.xml';
            if (file_exists($print_price_file)) {
                $xml = simplexml_load_file($print_price_file);
                if ($xml) {
                    // Tutaj wczytaj dane o cenach nadruków
                    $this->print_price_data = []; // Implementacja zależna od struktury pliku
                }
            }

            MHI_Logger::info('Wczytano dane XML z plików Axpol. Produkty: ' . count($this->product_data) . ', Stany magazynowe: ' . count($this->stock_data) . ', Techniki nadruku: ' . count($this->print_data));
            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas wczytywania danych XML Axpol: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generuje plik XML do importu do WooCommerce.
     *
     * @return bool True jeśli plik został wygenerowany pomyślnie, false w przeciwnym razie.
     */
    public function generate_woocommerce_xml()
    {
        if (empty($this->product_data)) {
            if (!$this->load_xml_data()) {
                return false;
            }
        }

        try {
            // Tworzymy nowy dokument XML
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            // Tworzymy korzeń
            $root = $dom->createElement('products');
            $dom->appendChild($root);

            // Dla każdego produktu
            foreach ($this->product_data as $code => $product) {
                // Tworzenie elementu produktu
                $item = $dom->createElement('product');

                // Dodawanie pól produktu
                $this->add_product_data($dom, $item, $product, $code);

                // Dodanie produktu do roota
                $root->appendChild($item);
            }

            // Zapisz plik XML
            $output_file = $this->target_dir . '/woocommerce_import_' . $this->name . '.xml';
            $dom->save($output_file);

            MHI_Logger::info('Wygenerowano plik XML do importu WooCommerce: ' . $output_file);
            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas generowania pliku XML do importu WooCommerce: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dodaje dane produktu do dokumentu XML.
     *
     * @param DOMDocument $dom Dokument XML.
     * @param DOMElement $item Element produktu.
     * @param SimpleXMLElement $product Dane produktu z XML Axpol.
     * @param string $code Kod produktu.
     */
    private function add_product_data($dom, $item, $product, $code)
    {
        // Podstawowe dane produktu
        $this->add_xml_element($dom, $item, 'sku', $code);
        $this->add_xml_element($dom, $item, 'name', (string) $product->TitlePL);
        $this->add_xml_element($dom, $item, 'description', (string) $product->DescriptionPL);

        // Cena
        $price = (float) $product->NetPricePLN;
        $this->add_xml_element($dom, $item, 'regular_price', number_format($price, 2, '.', ''));

        // Kategorie
        $main_category = (string) $product->MainCategoryPL;
        $sub_category = (string) $product->SubCategoryPL;

        $categories = $dom->createElement('categories');
        $this->add_xml_element($dom, $categories, 'category', $main_category);
        if (!empty($sub_category)) {
            $this->add_xml_element($dom, $categories, 'category', $main_category . ' > ' . $sub_category);
        }
        $item->appendChild($categories);

        // Atrybuty produktu
        $attributes = $dom->createElement('attributes');

        // Materiał
        if (!empty($product->MaterialPL)) {
            $attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $attr, 'name', 'Materiał');
            $this->add_xml_element($dom, $attr, 'value', (string) $product->MaterialPL);
            $this->add_xml_element($dom, $attr, 'visible', '1');
            $attributes->appendChild($attr);
        }

        // Wymiary
        if (!empty($product->Dimensions)) {
            $attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $attr, 'name', 'Wymiary');
            $this->add_xml_element($dom, $attr, 'value', (string) $product->Dimensions);
            $this->add_xml_element($dom, $attr, 'visible', '1');
            $attributes->appendChild($attr);
        }

        // Waga
        if (!empty($product->ItemWeightG)) {
            $attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $attr, 'name', 'Waga');
            $this->add_xml_element($dom, $attr, 'value', (string) $product->ItemWeightG . ' g');
            $this->add_xml_element($dom, $attr, 'visible', '1');
            $attributes->appendChild($attr);
        }

        // Kolor
        if (!empty($product->ColorPL)) {
            $attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $attr, 'name', 'Kolor');
            $this->add_xml_element($dom, $attr, 'value', (string) $product->ColorPL);
            $this->add_xml_element($dom, $attr, 'visible', '1');
            $attributes->appendChild($attr);
        }

        // Pakowanie
        if (!empty($product->IndividualPacking)) {
            $attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $attr, 'name', 'Pakowanie');
            $this->add_xml_element($dom, $attr, 'value', (string) $product->IndividualPacking);
            $this->add_xml_element($dom, $attr, 'visible', '1');
            $attributes->appendChild($attr);
        }

        // Kolor wkładu (dla długopisów)
        if (!empty($product->InkColor)) {
            $attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $attr, 'name', 'Kolor wkładu');
            $this->add_xml_element($dom, $attr, 'value', (string) $product->InkColor);
            $this->add_xml_element($dom, $attr, 'visible', '1');
            $attributes->appendChild($attr);
        }

        // Dodaj dane o znakowianiu, jeśli są dostępne
        if (isset($this->print_data[$code])) {
            $print_info = $this->print_data[$code];

            // Miejsce znakowania
            if (!empty($print_info->Position_1_PrintPosition)) {
                $attr = $dom->createElement('attribute');
                $this->add_xml_element($dom, $attr, 'name', 'Miejsce znakowania');
                $this->add_xml_element($dom, $attr, 'value', (string) $print_info->Position_1_PrintPosition);
                $this->add_xml_element($dom, $attr, 'visible', '1');
                $attributes->appendChild($attr);
            }

            // Wymiary znakowania
            if (!empty($print_info->Position_1_PrintSize)) {
                $attr = $dom->createElement('attribute');
                $this->add_xml_element($dom, $attr, 'name', 'Wymiar znakowania');
                $this->add_xml_element($dom, $attr, 'value', (string) $print_info->Position_1_PrintSize);
                $this->add_xml_element($dom, $attr, 'visible', '1');
                $attributes->appendChild($attr);
            }

            // Technika znakowania
            if (!empty($print_info->Position_1_PrintTech_1)) {
                $attr = $dom->createElement('attribute');
                $this->add_xml_element($dom, $attr, 'name', 'Technika znakowania');
                $this->add_xml_element($dom, $attr, 'value', (string) $print_info->Position_1_PrintTech_1);
                $this->add_xml_element($dom, $attr, 'visible', '1');
                $attributes->appendChild($attr);
            }
        }

        $item->appendChild($attributes);

        // Stan magazynowy
        if (isset($this->stock_data[$code])) {
            $stock = $this->stock_data[$code];
            $stock_quantity = (int) $stock->na_magazynie_dostepne_teraz;
            $this->add_xml_element($dom, $item, 'stock_quantity', $stock_quantity);
            $this->add_xml_element($dom, $item, 'stock_status', $stock_quantity > 0 ? 'instock' : 'outofstock');

            // Dodatkowe informacje o możliwej dostawie
            $on_order = (int) $stock->na_zamowienie;
            if ($on_order > 0) {
                $next_delivery = $dom->createElement('meta_data');
                $this->add_xml_element($dom, $next_delivery, 'key', '_axpol_on_order');
                $this->add_xml_element($dom, $next_delivery, 'value', $on_order);
                $item->appendChild($next_delivery);

                if (!empty($stock->data_kolejnej_dostawy)) {
                    $delivery_date = $dom->createElement('meta_data');
                    $this->add_xml_element($dom, $delivery_date, 'key', '_axpol_next_delivery_date');
                    $this->add_xml_element($dom, $delivery_date, 'value', (string) $stock->data_kolejnej_dostawy);
                    $item->appendChild($delivery_date);
                }
            }
        } else {
            // Domyślnie ustaw jako niedostępny, jeśli nie ma informacji o stanie
            $this->add_xml_element($dom, $item, 'stock_quantity', '0');
            $this->add_xml_element($dom, $item, 'stock_status', 'outofstock');
        }

        // Obrazy
        $images = $dom->createElement('images');

        // Przygotuj kod produktu do użycia w URL-ach zdjęć
        $product_code = $code;
        $using_fallback = false;

        // Przygotowanie kodów produktów dla różnych formatów URL
        // Format dla głównego zdjęcia (zachowujemy kropki i myślniki)
        $main_code = $product_code;

        // Format dla galerii zdjęć (usuwamy kropki i myślniki)
        $gallery_code = str_replace(['.', '-', ' '], '', $product_code);

        // Główne zdjęcie (format: https://axpol.com.pl/files/fotohr/P308.841_S_0.jpg)
        $main_image_url = 'https://axpol.com.pl/files/fotohr/' . $main_code . '_S_0.jpg';
        $image = $dom->createElement('image');
        $image->setAttribute('src', $main_image_url);
        $images->appendChild($image);

        // Zapisz informację o głównym zdjęciu do logów
        MHI_Logger::info('Główne zdjęcie dla produktu ' . $code . ': ' . $main_image_url);

        // Dodatkowe zdjęcia - galeria (format: https://axpol.com.pl/files/foto_add_view/P308841_2.jpg)
        // Dodajemy dynamicznie dodatkowe zdjęcia
        for ($i = 2; $i <= 5; $i++) {
            $gallery_image_url = 'https://axpol.com.pl/files/foto_add_view/' . $gallery_code . '_' . $i . '.jpg';
            $image = $dom->createElement('image');
            $image->setAttribute('src', $gallery_image_url);
            $images->appendChild($image);

            // Zapisz informację o dodatkowym zdjęciu do logów dla pierwszego zdjęcia w galerii
            if ($i == 2) {
                MHI_Logger::info('Dodatkowe zdjęcie dla produktu ' . $code . ': ' . $gallery_image_url);
            }
        }

        // Zachowaj także oryginalne adresy URL zdjęć z pliku XML jako zapasowe
        if (!empty($product->Foto01)) {
            $original_image_url = 'https://axpol.com.pl/images/products/' . (string) $product->Foto01;
            $meta_original_img = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_original_img, 'key', '_axpol_original_img_url');
            $this->add_xml_element($dom, $meta_original_img, 'value', $original_image_url);
            $item->appendChild($meta_original_img);

            // Dodajemy również oryginalne zdjęcie jako dodatkowe
            $image = $dom->createElement('image');
            $image->setAttribute('src', $original_image_url);
            $images->appendChild($image);

            MHI_Logger::info('Oryginalny URL zdjęcia dla produktu ' . $code . ': ' . $original_image_url);
        }

        $item->appendChild($images);

        // Dodatkowe metadane
        $meta_data = $dom->createElement('meta_data');
        $this->add_xml_element($dom, $meta_data, 'key', '_axpol_code');
        $this->add_xml_element($dom, $meta_data, 'value', $code);
        $item->appendChild($meta_data);

        if (!empty($product->EAN)) {
            $ean = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $ean, 'key', '_axpol_ean');
            $this->add_xml_element($dom, $ean, 'value', (string) $product->EAN);
            $item->appendChild($ean);
        }
    }

    /**
     * Dodaje element XML z wartością.
     *
     * @param DOMDocument $dom Dokument XML.
     * @param DOMElement $parent Element nadrzędny.
     * @param string $name Nazwa elementu.
     * @param string $value Wartość elementu.
     */
    private function add_xml_element($dom, $parent, $name, $value)
    {
        $element = $dom->createElement($name);
        $text = $dom->createTextNode($value);
        $element->appendChild($text);
        $parent->appendChild($element);
    }
}
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

        // Stan magazynowy - ROZSZERZONA WERSJA dla AXPOL
        if (isset($this->stock_data[$code])) {
            $stock = $this->stock_data[$code];

            // Podstawowe stany
            $dostepne_teraz = (int) $stock->na_magazynie_dostepne_teraz;
            $w_rezerwacji = (int) $stock->na_magazynie_w_rezerwacji;
            $na_zamowienie = (int) $stock->na_zamowienie;
            $kolejna_dostawa = (int) $stock->kolejna_dostawa;
            $data_dostawy = trim((string) $stock->data_kolejnej_dostawy);

            // Ustawienie stanu podstawowego WooCommerce
            $this->add_xml_element($dom, $item, 'stock_quantity', $dostepne_teraz);
            $this->add_xml_element($dom, $item, 'stock_status', $dostepne_teraz > 0 ? 'instock' : 'outofstock');

            // NOWE CUSTOM FIELDS dla AXPOL

            // Stan dostępny teraz
            $meta_dostepne = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_dostepne, 'key', '_axpol_dostepne_teraz');
            $this->add_xml_element($dom, $meta_dostepne, 'value', $dostepne_teraz);
            $item->appendChild($meta_dostepne);

            // Stan w rezerwacji
            $meta_rezerwacja = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_rezerwacja, 'key', '_axpol_w_rezerwacji');
            $this->add_xml_element($dom, $meta_rezerwacja, 'value', $w_rezerwacji);
            $item->appendChild($meta_rezerwacja);

            // Na zamówienie (1-2 dni)
            $meta_zamowienie = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_zamowienie, 'key', '_axpol_na_zamowienie');
            $this->add_xml_element($dom, $meta_zamowienie, 'value', $na_zamowienie);
            $item->appendChild($meta_zamowienie);

            // Kolejna dostawa
            if ($kolejna_dostawa > 0) {
                $meta_kolejna_dostawa = $dom->createElement('meta_data');
                $this->add_xml_element($dom, $meta_kolejna_dostawa, 'key', '_axpol_kolejna_dostawa');
                $this->add_xml_element($dom, $meta_kolejna_dostawa, 'value', $kolejna_dostawa);
                $item->appendChild($meta_kolejna_dostawa);
            }

            // Data kolejnej dostawy
            if (!empty($data_dostawy) && $data_dostawy !== '0') {
                $meta_data_dostawy = $dom->createElement('meta_data');
                $this->add_xml_element($dom, $meta_data_dostawy, 'key', '_axpol_data_dostawy');
                $this->add_xml_element($dom, $meta_data_dostawy, 'value', $data_dostawy);
                $item->appendChild($meta_data_dostawy);
            }

            // Flaga informacyjna o rodzaju dostępności
            $typ_dostepnosci = '';
            if ($dostepne_teraz > 0) {
                $typ_dostepnosci = 'dostepny_natychmiast';
            } elseif ($w_rezerwacji > 0) {
                $typ_dostepnosci = 'dostepny_z_rezerwacji';
            } elseif ($na_zamowienie > 0) {
                $typ_dostepnosci = 'dostepny_1_2_dni';
            } elseif ($kolejna_dostawa > 0) {
                $typ_dostepnosci = 'dostepny_pozniej';
            } else {
                $typ_dostepnosci = 'niedostepny';
            }

            $meta_typ = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_typ, 'key', '_axpol_typ_dostepnosci');
            $this->add_xml_element($dom, $meta_typ, 'value', $typ_dostepnosci);
            $item->appendChild($meta_typ);

            // Łączna dostępność (włączając różne stany)
            $laczna_dostepnosc = $dostepne_teraz + $w_rezerwacji + $na_zamowienie;
            $meta_laczna = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_laczna, 'key', '_axpol_laczna_dostepnosc');
            $this->add_xml_element($dom, $meta_laczna, 'value', $laczna_dostepnosc);
            $item->appendChild($meta_laczna);

            // Komunikat dla klienta o dostępności
            $komunikat_dostepnosci = $this->generate_availability_message($dostepne_teraz, $w_rezerwacji, $na_zamowienie, $kolejna_dostawa, $data_dostawy);
            if (!empty($komunikat_dostepnosci)) {
                $meta_komunikat = $dom->createElement('meta_data');
                $this->add_xml_element($dom, $meta_komunikat, 'key', '_axpol_komunikat_dostepnosci');
                $this->add_xml_element($dom, $meta_komunikat, 'value', $komunikat_dostepnosci);
                $item->appendChild($meta_komunikat);
            }

        } else {
            // Domyślnie ustaw jako niedostępny, jeśli nie ma informacji o stanie
            $this->add_xml_element($dom, $item, 'stock_quantity', '0');
            $this->add_xml_element($dom, $item, 'stock_status', 'outofstock');

            // Dodaj informację o braku danych
            $meta_brak_danych = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $meta_brak_danych, 'key', '_axpol_typ_dostepnosci');
            $this->add_xml_element($dom, $meta_brak_danych, 'value', 'brak_danych');
            $item->appendChild($meta_brak_danych);
        }

        // Obrazy
        $images = $dom->createElement('images');

        // Główne zdjęcie z Foto01 - używamy /files/fotohr/
        if (!empty($product->Foto01)) {
            $main_image_filename = (string) $product->Foto01;
            $main_image_url = 'https://axpol.com.pl/files/fotohr/' . $main_image_filename;
            $image = $dom->createElement('image');
            $image->setAttribute('src', $main_image_url);
            $images->appendChild($image);

            // Zapisz informację o głównym zdjęciu do logów
            MHI_Logger::info('Główne zdjęcie dla produktu ' . $code . ': ' . $main_image_url);
        }

        // Dodatkowe zdjęcia - galeria z Foto02, Foto03, Foto04, etc. - używamy /files/foto_add_view/
        for ($i = 2; $i <= 10; $i++) {
            $foto_field = 'Foto' . sprintf('%02d', $i); // Foto02, Foto03, etc.

            if (!empty($product->$foto_field)) {
                $gallery_image_filename = (string) $product->$foto_field;
                $gallery_image_url = 'https://axpol.com.pl/files/foto_add_view/' . $gallery_image_filename;
                $image = $dom->createElement('image');
                $image->setAttribute('src', $gallery_image_url);
                $images->appendChild($image);

                // Zapisz informację o dodatkowym zdjęciu do logów dla pierwszego zdjęcia w galerii
                if ($i == 2) {
                    MHI_Logger::info('Dodatkowe zdjęcie dla produktu ' . $code . ': ' . $gallery_image_url);
                }
            }
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

    /**
     * Generuje komunikat o dostępności produktu dla klienta.
     *
     * @param int $dostepne_teraz Dostępne natychmiast
     * @param int $w_rezerwacji W rezerwacji  
     * @param int $na_zamowienie Na zamówienie (1-2 dni)
     * @param int $kolejna_dostawa Kolejna dostawa
     * @param string $data_dostawy Data kolejnej dostawy
     * @return string Komunikat o dostępności
     */
    private function generate_availability_message($dostepne_teraz, $w_rezerwacji, $na_zamowienie, $kolejna_dostawa, $data_dostawy)
    {
        if ($dostepne_teraz > 0) {
            return "Dostępne natychmiast: {$dostepne_teraz} szt.";
        }

        if ($w_rezerwacji > 0) {
            return "Dostępne z rezerwacji: {$w_rezerwacji} szt. (mogą być dodatkowe warunki)";
        }

        if ($na_zamowienie > 0) {
            return "Dostępne na zamówienie: {$na_zamowienie} szt. (realizacja 1-2 dni robocze)";
        }

        if ($kolejna_dostawa > 0 && !empty($data_dostawy) && $data_dostawy !== '0') {
            return "Dostępne w kolejnej dostawie: {$kolejna_dostawa} szt. (przewidywana dostawa: {$data_dostawy})";
        }

        if ($kolejna_dostawa > 0) {
            return "Dostępne w kolejnej dostawie: {$kolejna_dostawa} szt. (data dostawy do ustalenia)";
        }

        return "Produkt obecnie niedostępny";
    }
}
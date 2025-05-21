<?php
/**
 * Klasa generatora plików XML importu WooCommerce dla hurtowni Malfini.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Malfini_WC_XML_Generator
 * 
 * Generuje pliki XML kompatybilne z WooCommerce na podstawie danych Malfini.
 */
class MHI_Malfini_WC_XML_Generator
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    private $name = 'malfini';

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
     * Konstruktor klasy.
     */
    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->source_dir = trailingslashit($upload_dir['basedir']) . 'hurtownie/' . $this->name;
        $this->target_dir = $this->source_dir;
    }

    /**
     * Wczytuje dane z plików XML Malfini.
     *
     * @return bool True jeśli dane zostały wczytane, false w przeciwnym razie.
     */
    public function load_xml_data()
    {
        try {
            // Wczytaj dane produktów
            $product_data_file = $this->source_dir . '/produkty.xml';
            if (file_exists($product_data_file)) {
                $xml = simplexml_load_file($product_data_file);
                if ($xml) {
                    foreach ($xml->children() as $index => $item) {
                        $code = (string) $item->code;
                        if (!empty($code)) {
                            $this->product_data[$code] = $item;
                        }
                    }
                }
            } else {
                MHI_Logger::error('Nie znaleziono pliku z danymi produktów Malfini: ' . $product_data_file);
                return false;
            }

            MHI_Logger::info('Wczytano dane XML z plików Malfini. Produkty: ' . count($this->product_data));
            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas wczytywania danych XML Malfini: ' . $e->getMessage());
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
     * @param SimpleXMLElement $product Dane produktu z XML Malfini.
     * @param string $code Kod produktu.
     */
    private function add_product_data($dom, $item, $product, $code)
    {
        // Podstawowe dane produktu
        $this->add_xml_element($dom, $item, 'sku', $code);
        $this->add_xml_element($dom, $item, 'name', (string) $product->name);

        // Łączymy opis z różnych pól
        $description = '';
        if (!empty($product->subtitle)) {
            $description .= '<strong>' . (string) $product->subtitle . '</strong><br>';
        }
        if (!empty($product->specification)) {
            $description .= '<p>' . (string) $product->specification . '</p>';
        }
        if (!empty($product->description)) {
            $description .= '<p>' . (string) $product->description . '</p>';
        }

        $this->add_xml_element($dom, $item, 'description', $description);

        // Kategorie
        $categoryName = (string) $product->categoryName;
        $genderName = (string) $product->gender;

        $categories = $dom->createElement('categories');
        if (!empty($categoryName)) {
            $this->add_xml_element($dom, $categories, 'category', $categoryName);
        }
        if (!empty($genderName) && $genderName != 'Nezadáno') {
            // Dodaj kategorię płci tylko jeśli nie jest pusta i nie jest 'Nezadáno'
            if (!empty($categoryName)) {
                $this->add_xml_element($dom, $categories, 'category', $categoryName . ' > ' . $genderName);
            } else {
                $this->add_xml_element($dom, $categories, 'category', $genderName);
            }
        }
        $item->appendChild($categories);

        // Dodaj warianty produktu i atrybuty
        if (isset($product->variants) && !empty($product->variants)) {
            $attributes = $dom->createElement('attributes');
            $first_variant = true;
            $first_variant_price = 0;

            // Zbieramy kolory z wariantów
            $colors = [];
            foreach ($product->variants->children() as $variant) {
                if (!empty($variant->name)) {
                    $colors[] = (string) $variant->name;
                } elseif (!empty($variant->colorCode)) {
                    $colors[] = (string) $variant->colorCode;
                }
            }

            // Dodajemy atrybut koloru jeśli są dostępne kolory
            if (!empty($colors)) {
                $attr = $dom->createElement('attribute');
                $this->add_xml_element($dom, $attr, 'name', 'Kolor');
                $this->add_xml_element($dom, $attr, 'value', implode(', ', $colors));
                $this->add_xml_element($dom, $attr, 'visible', '1');
                $attributes->appendChild($attr);
            }

            // Pobierz atrybuty z pierwszego wariantu
            if (isset($product->variants->item0)) {
                $variant = $product->variants->item0;

                // Pobierz atrybuty z pierwszego wariantu
                if (isset($variant->attributes)) {
                    foreach ($variant->attributes->children() as $attribute) {
                        if (!empty($attribute->title) && !empty($attribute->text)) {
                            $attr = $dom->createElement('attribute');
                            $this->add_xml_element($dom, $attr, 'name', (string) $attribute->title);
                            $this->add_xml_element($dom, $attr, 'value', (string) $attribute->text);
                            $this->add_xml_element($dom, $attr, 'visible', '1');
                            $attributes->appendChild($attr);
                        }
                    }
                }

                // Dodaj informacje o marce jako atrybut
                if (!empty($product->trademark)) {
                    $attr = $dom->createElement('attribute');
                    $this->add_xml_element($dom, $attr, 'name', 'Marka');
                    $this->add_xml_element($dom, $attr, 'value', (string) $product->trademark);
                    $this->add_xml_element($dom, $attr, 'visible', '1');
                    $attributes->appendChild($attr);
                }
            }

            $item->appendChild($attributes);

            // Pobieramy obrazy z pierwszego wariantu
            if (isset($product->variants->item0->images)) {
                $images = $dom->createElement('images');

                // Główne zdjęcie
                foreach ($product->variants->item0->images->children() as $image_data) {
                    if (!empty($image_data->link)) {
                        $image = $dom->createElement('image');
                        $image->setAttribute('src', (string) $image_data->link);
                        $images->appendChild($image);

                        // Log tylko dla pierwszego obrazu
                        if ($images->childNodes->length === 1) {
                            MHI_Logger::info('Zdjęcie dla produktu ' . $code . ': ' . (string) $image_data->link);
                        }
                    }
                }

                $item->appendChild($images);
            }

            // Ustawiamy domyślne wartości dla magazynu
            $this->add_xml_element($dom, $item, 'stock_quantity', '0');
            $this->add_xml_element($dom, $item, 'stock_status', 'instock');
        }

        // Dodatkowe metadane
        $meta_data = $dom->createElement('meta_data');
        $this->add_xml_element($dom, $meta_data, 'key', '_malfini_code');
        $this->add_xml_element($dom, $meta_data, 'value', $code);
        $item->appendChild($meta_data);

        // PDF z kartą produktu
        if (!empty($product->productCardPdf)) {
            $product_card = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $product_card, 'key', '_malfini_product_card_pdf');
            $this->add_xml_element($dom, $product_card, 'value', (string) $product->productCardPdf);
            $item->appendChild($product_card);
        }

        // PDF z tabelą rozmiarów
        if (!empty($product->sizeChartPdf)) {
            $size_chart = $dom->createElement('meta_data');
            $this->add_xml_element($dom, $size_chart, 'key', '_malfini_size_chart_pdf');
            $this->add_xml_element($dom, $size_chart, 'value', (string) $product->sizeChartPdf);
            $item->appendChild($size_chart);
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
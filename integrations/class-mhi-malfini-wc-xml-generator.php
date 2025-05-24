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
        $this->source_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name;
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
        // OBLICZAMY CENY I STANY MAGAZYNOWE
        $total_stock = 0;
        $min_price = null;
        $max_price = null;
        $available_variants = [];

        // Sprawdzamy każdy wariant (kolor + rozmiary)
        foreach ($product->variants->children() as $variant) {
            $variant_code = (string) $variant->code;
            $color_name = (string) $variant->name;

            // Sprawdzamy każdy rozmiar w wariancie
            foreach ($variant->nomenclatures->children() as $nomenclature) {
                $sku = (string) $nomenclature->productSizeCode;
                $size = (string) $nomenclature->sizeName;

                // ✅ CENY: Sprawdź sellPrice, jeśli brak to użyj domyślnej ceny
                $price = 0;
                if (!empty($nomenclature->sellPrice)) {
                    $price = (float) $nomenclature->sellPrice;
                } else {
                    // DOMYŚLNA CENA: 50 PLN (można skonfigurować)
                    $price = 50.00;
                    MHI_Logger::warning("Brak ceny dla {$sku}, używam domyślnej: {$price} PLN");
                }

                if ($price > 0) {
                    if ($min_price === null || $price < $min_price) {
                        $min_price = $price;
                    }
                    if ($max_price === null || $price > $max_price) {
                        $max_price = $price;
                    }
                }

                // ✅ STAN MAGAZYNOWY: używamy expeditionQuantity
                $stock = 0;
                if (!empty($nomenclature->expeditionQuantity)) {
                    $stock = (int) $nomenclature->expeditionQuantity;
                    $total_stock += $stock;
                }

                // Dodaj do dostępnych wariantów jeśli ma stock
                if ($stock > 0) {
                    $available_variants[] = [
                        'sku' => $sku,
                        'color' => $color_name,
                        'size' => $size,
                        'price' => $price,
                        'stock' => $stock
                    ];
                }
            }
        }

        // PODSTAWOWE DANE PRODUKTU
        $this->add_xml_element($dom, $item, 'sku', $code);
        $this->add_xml_element($dom, $item, 'name', (string) $product->name);
        $this->add_xml_element($dom, $item, 'type', 'simple');
        $this->add_xml_element($dom, $item, 'status', 'publish');

        // ✅ CENY - zawsze dodaj cenę
        if ($min_price !== null && $min_price > 0) {
            $this->add_xml_element($dom, $item, 'regular_price', number_format($min_price, 2, '.', ''));

            // Jeśli są różne ceny w wariantach, dodaj info w opisie
            if ($max_price !== null && $max_price != $min_price) {
                $price_range_text = 'Cena od ' . number_format($min_price, 2) . ' do ' . number_format($max_price, 2) . ' PLN';
                $this->add_xml_element($dom, $item, 'price_range_info', $price_range_text);
            }

            MHI_Logger::info('Ceny dla produktu ' . $code . ': od ' . $min_price . ' do ' . $max_price . ' PLN');
        } else {
            // Fallback - domyślna cena
            $this->add_xml_element($dom, $item, 'regular_price', '50.00');
            MHI_Logger::warning('Brak cen dla produktu ' . $code . ', używam domyślnej: 50.00 PLN');
        }

        // ✅ STAN MAGAZYNOWY
        if ($total_stock > 0) {
            $this->add_xml_element($dom, $item, 'stock_quantity', (string) $total_stock);
            $this->add_xml_element($dom, $item, 'manage_stock', 'yes');
            $this->add_xml_element($dom, $item, 'stock_status', 'instock');
        } else {
            $this->add_xml_element($dom, $item, 'stock_quantity', '0');
            $this->add_xml_element($dom, $item, 'manage_stock', 'yes');
            $this->add_xml_element($dom, $item, 'stock_status', 'outofstock');
        }

        MHI_Logger::info('Stan magazynowy dla produktu ' . $code . ': ' . $total_stock . ' szt.');

        // OPIS PRODUKTU
        $description = '';
        if (!empty($product->subtitle)) {
            $description .= '<strong>' . htmlspecialchars((string) $product->subtitle) . '</strong><br>';
        }
        if (!empty($product->specification)) {
            $description .= '<p>' . htmlspecialchars((string) $product->specification) . '</p>';
        }
        if (!empty($product->description)) {
            $description .= '<p>' . htmlspecialchars((string) $product->description) . '</p>';
        }
        $this->add_xml_element($dom, $item, 'description', $description);

        // ✅ KATEGORIE - w formacie dla import.php
        $categories_text = '';
        if (!empty($product->categoryName)) {
            $categories_text = (string) $product->categoryName;

            // Dodaj gender jako podkategorię
            if (!empty($product->gender)) {
                $categories_text .= ' > ' . (string) $product->gender;
            }
        }
        if (!empty($categories_text)) {
            $this->add_xml_element($dom, $item, 'categories', $categories_text);
        }

        // ✅ ATRYBUTY - w formacie XML dla import.php
        $all_attributes = [];

        // Zbierz wszystkie unikalne atrybuty ze wszystkich wariantów
        foreach ($product->variants->children() as $variant) {
            $color_name = (string) $variant->name;
            $all_attributes['Kolor'][] = $color_name;

            // Atrybuty z pierwszego wariantu (są takie same dla całego produktu)
            if (isset($variant->attributes)) {
                foreach ($variant->attributes->children() as $attr) {
                    $attr_title = (string) $attr->title;
                    $attr_text = (string) $attr->text;

                    if (!empty($attr_title) && !empty($attr_text)) {
                        $all_attributes[$attr_title][] = $attr_text;
                    }
                }
            }
        }

        // Usuń duplikaty i stwórz XML dla atrybutów
        if (!empty($all_attributes)) {
            $attributes_element = $dom->createElement('attributes');

            foreach ($all_attributes as $attr_name => $attr_values) {
                $unique_values = array_unique($attr_values);
                $attr_value_text = implode(', ', $unique_values);

                $attribute_element = $dom->createElement('attribute');
                $this->add_xml_element($dom, $attribute_element, 'name', $attr_name);
                $this->add_xml_element($dom, $attribute_element, 'value', $attr_value_text);
                $attributes_element->appendChild($attribute_element);
            }

            $item->appendChild($attributes_element);
        }

        // ✅ OBRAZY - wszystkie obrazy ze wszystkich wariantów
        $all_images = [];
        foreach ($product->variants->children() as $variant) {
            if (isset($variant->images)) {
                foreach ($variant->images->children() as $image) {
                    $image_url = (string) $image->link;
                    if (!empty($image_url) && !in_array($image_url, $all_images)) {
                        $all_images[] = $image_url;
                    }
                }
            }
        }

        // Dodaj obrazy w formacie XML dla import.php
        if (!empty($all_images)) {
            $images_element = $dom->createElement('images');

            foreach ($all_images as $image_url) {
                $image_element = $dom->createElement('image');
                $image_element->setAttribute('src', $image_url);
                $images_element->appendChild($image_element);
            }

            $item->appendChild($images_element);
        }

        MHI_Logger::info('Wygenerowano dane dla produktu ' . $code . ' - ' . count($available_variants) . ' dostępnych wariantów, ' . count($all_images) . ' obrazów, ' . count($all_attributes) . ' atrybutów');

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
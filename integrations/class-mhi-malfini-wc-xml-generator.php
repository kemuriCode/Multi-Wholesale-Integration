<?php
/**
 * Klasa generatora plików XML importu WooCommerce dla hurtowni Malfini.
 * Zaktualizowana do pracy z danymi JSON z API v4.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Malfini_WC_XML_Generator
 * 
 * Generuje pliki XML kompatybilne z WooCommerce na podstawie danych JSON z API Malfini v4.
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
     * Ścieżka do katalogu z plikami JSON.
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
     * Dane produktów z API.
     *
     * @var array
     */
    private $products_data = [];

    /**
     * Dane dostępności z API.
     *
     * @var array
     */
    private $availabilities_data = [];

    /**
     * Dane cen z API.
     *
     * @var array
     */
    private $prices_data = [];

    /**
     * Konstruktor klasy.
     *
     * @param string $source_dir Opcjonalna ścieżka do katalogu źródłowego.
     */
    public function __construct($source_dir = null)
    {
        if ($source_dir) {
            $this->source_dir = trailingslashit($source_dir);
        } else {
            $upload_dir = wp_upload_dir();
            $this->source_dir = trailingslashit($upload_dir['basedir']) . 'wholesale/' . $this->name . '/';
        }
        $this->target_dir = $this->source_dir;
    }

    /**
     * Wczytuje dane z plików JSON Malfini API v4.
     *
     * @return bool True jeśli dane zostały wczytane, false w przeciwnym razie.
     */
    public function load_json_data()
    {
        try {
            // Wczytaj dane produktów
            $products_file = $this->source_dir . 'products.json';
            if (file_exists($products_file)) {
                $json_content = file_get_contents($products_file);
                $this->products_data = json_decode($json_content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if (class_exists('MHI_Logger')) {
                        MHI_Logger::error('Błąd JSON w pliku products.json: ' . json_last_error_msg());
                    }
                    return false;
                }
            } else {
                if (class_exists('MHI_Logger')) {
                    MHI_Logger::error('Nie znaleziono pliku products.json: ' . $products_file);
                }
                return false;
            }

            // Wczytaj dane dostępności (opcjonalne) - optymalizacja: mapa po productSizeCode
            $availabilities_file = $this->source_dir . 'availabilities.json';
            if (file_exists($availabilities_file)) {
                $json_content = file_get_contents($availabilities_file);
                $availabilities_raw = json_decode($json_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($availabilities_raw)) {
                    // Przekształć na mapę productSizeCode => dostępność dla szybszego dostępu
                    foreach ($availabilities_raw as $item) {
                        if (isset($item['productSizeCode'])) {
                            $this->availabilities_data[$item['productSizeCode']] = $item;
                        }
                    }
                }
            }

            // Wczytaj dane cen (opcjonalne) - optymalizacja: mapa po productSizeCode
            $prices_file = $this->source_dir . 'prices.json';
            if (file_exists($prices_file)) {
                $json_content = file_get_contents($prices_file);
                $prices_raw = json_decode($json_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($prices_raw)) {
                    // Przekształć na mapę productSizeCode => cena dla szybszego dostępu
                    foreach ($prices_raw as $item) {
                        if (isset($item['productSizeCode'])) {
                            $this->prices_data[$item['productSizeCode']] = $item;
                        }
                    }
                }
            }

            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Wczytano dane JSON z API Malfini. Produkty: ' . count($this->products_data) .
                    ', Dostępność: ' . count($this->availabilities_data) .
                    ', Ceny: ' . count($this->prices_data));
            }
            return true;
        } catch (Exception $e) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas wczytywania danych JSON Malfini: ' . $e->getMessage());
            }
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
        if (empty($this->products_data)) {
            if (!$this->load_json_data()) {
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

            $processed_count = 0;

            // Dla każdego produktu z API
            foreach ($this->products_data as $product) {
                if (!isset($product['code']) || empty($product['code'])) {
                    continue;
                }

                // Tworzenie elementu produktu
                $item = $dom->createElement('product');

                // Dodawanie pól produktu
                $this->add_product_data($dom, $item, $product);

                // Dodanie produktu do roota
                $root->appendChild($item);
                $processed_count++;
            }

            // Zapisz plik XML
            $output_file = $this->target_dir . 'woocommerce_import_' . $this->name . '.xml';
            $dom->save($output_file);

            if (class_exists('MHI_Logger')) {
                MHI_Logger::info('Wygenerowano plik XML do importu WooCommerce: ' . $output_file . ' (' . $processed_count . ' produktów)');
            }
            return true;
        } catch (Exception $e) {
            if (class_exists('MHI_Logger')) {
                MHI_Logger::error('Błąd podczas generowania pliku XML do importu WooCommerce: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Dodaje dane produktu do dokumentu XML.
     *
     * @param DOMDocument $dom Dokument XML.
     * @param DOMElement $item Element produktu.
     * @param array $product Dane produktu z API Malfini.
     */
    private function add_product_data($dom, $item, $product)
    {
        $code = $product['code'];

        // PODSTAWOWE DANE PRODUKTU
        $this->add_xml_element($dom, $item, 'sku', $code);
        $this->add_xml_element($dom, $item, 'name', $product['name'] ?? '');
        $this->add_xml_element($dom, $item, 'type', 'variable'); // Produkty Malfini mają warianty
        $this->add_xml_element($dom, $item, 'status', 'publish');

        // OPIS PRODUKTU
        $description = '';
        if (!empty($product['subtitle'])) {
            $description .= '<strong>' . htmlspecialchars($product['subtitle']) . '</strong><br>';
        }
        if (!empty($product['specification'])) {
            $description .= '<p><strong>Specyfikacja:</strong> ' . htmlspecialchars($product['specification']) . '</p>';
        }
        if (!empty($product['description'])) {
            $description .= '<p>' . htmlspecialchars($product['description']) . '</p>';
        }
        $this->add_xml_element($dom, $item, 'description', $description);

        // KATEGORIE
        $categories_text = '';
        if (!empty($product['categoryName'])) {
            $categories_text = $product['categoryName'];

            // Dodaj gender jako podkategorię
            if (!empty($product['gender'])) {
                $categories_text .= ' > ' . $product['gender'];
            }
        }
        if (!empty($categories_text)) {
            $this->add_xml_element($dom, $item, 'categories', $categories_text);
        }

        // OBLICZ CENY I STANY MAGAZYNOWE Z WARIANTÓW
        $total_stock = 0;
        $min_price = null;
        $max_price = null;
        $all_colors = [];
        $all_sizes = [];

        // Dane dostępności i cen są już załadowane w mapach po productSizeCode
        // Nie potrzebujemy ich pobierać po kodzie produktu

        // Przetwórz warianty
        if (isset($product['variants']) && is_array($product['variants'])) {
            foreach ($product['variants'] as $variant) {
                $color_name = $variant['name'] ?? '';
                if (!empty($color_name) && !in_array($color_name, $all_colors)) {
                    $all_colors[] = $color_name;
                }

                // Przetwórz rozmiary w wariancie
                if (isset($variant['nomenclatures']) && is_array($variant['nomenclatures'])) {
                    foreach ($variant['nomenclatures'] as $nomenclature) {
                        $size = $nomenclature['sizeName'] ?? '';
                        $sku = $nomenclature['productSizeCode'] ?? '';

                        if (!empty($size) && !in_array($size, $all_sizes)) {
                            $all_sizes[] = $size;
                        }

                        // Pobierz cenę z API cen lub użyj domyślnej
                        $price = $this->get_price_for_sku($sku);
                        if ($price > 0) {
                            if ($min_price === null || $price < $min_price) {
                                $min_price = $price;
                            }
                            if ($max_price === null || $price > $max_price) {
                                $max_price = $price;
                            }
                        }

                        // Pobierz stan magazynowy z API dostępności
                        $stock = $this->get_stock_for_sku($sku);
                        $total_stock += $stock;
                    }
                }
            }
        }

        // CENY
        if ($min_price !== null && $min_price > 0) {
            $this->add_xml_element($dom, $item, 'regular_price', number_format($min_price, 2, '.', ''));

            if ($max_price !== null && $max_price != $min_price) {
                $price_range_text = 'Cena od ' . number_format($min_price, 2) . ' do ' . number_format($max_price, 2) . ' PLN';
                $this->add_xml_element($dom, $item, 'price_range_info', $price_range_text);
            }
        } else {
            // Domyślna cena
            $this->add_xml_element($dom, $item, 'regular_price', '50.00');
        }

        // STAN MAGAZYNOWY
        if ($total_stock > 0) {
            $this->add_xml_element($dom, $item, 'stock_quantity', (string) $total_stock);
            $this->add_xml_element($dom, $item, 'manage_stock', 'yes');
            $this->add_xml_element($dom, $item, 'stock_status', 'instock');
        } else {
            $this->add_xml_element($dom, $item, 'stock_quantity', '0');
            $this->add_xml_element($dom, $item, 'manage_stock', 'yes');
            $this->add_xml_element($dom, $item, 'stock_status', 'outofstock');
        }

        // ATRYBUTY
        $attributes_element = $dom->createElement('attributes');

        // Atrybut Kolor
        if (!empty($all_colors)) {
            $color_attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $color_attr, 'name', 'Kolor');
            $this->add_xml_element($dom, $color_attr, 'value', implode(', ', $all_colors));
            $this->add_xml_element($dom, $color_attr, 'variation', 'yes');
            $attributes_element->appendChild($color_attr);
        }

        // Atrybut Rozmiar
        if (!empty($all_sizes)) {
            $size_attr = $dom->createElement('attribute');
            $this->add_xml_element($dom, $size_attr, 'name', 'Rozmiar');
            $this->add_xml_element($dom, $size_attr, 'value', implode(', ', $all_sizes));
            $this->add_xml_element($dom, $size_attr, 'variation', 'yes');
            $attributes_element->appendChild($size_attr);
        }

        // Dodatkowe atrybuty z pierwszego wariantu
        if (isset($product['variants'][0]['attributes']) && is_array($product['variants'][0]['attributes'])) {
            foreach ($product['variants'][0]['attributes'] as $attr) {
                if (!empty($attr['title']) && !empty($attr['text'])) {
                    $attr_element = $dom->createElement('attribute');
                    $this->add_xml_element($dom, $attr_element, 'name', $attr['title']);
                    $this->add_xml_element($dom, $attr_element, 'value', $attr['text']);
                    $this->add_xml_element($dom, $attr_element, 'variation', 'no');
                    $attributes_element->appendChild($attr_element);
                }
            }
        }

        $item->appendChild($attributes_element);

        // OBRAZY
        $all_images = [];
        if (isset($product['variants']) && is_array($product['variants'])) {
            foreach ($product['variants'] as $variant) {
                if (isset($variant['images']) && is_array($variant['images'])) {
                    foreach ($variant['images'] as $image) {
                        $image_url = $image['link'] ?? '';
                        if (!empty($image_url) && !in_array($image_url, $all_images)) {
                            $all_images[] = $image_url;
                        }
                    }
                }
            }
        }

        if (!empty($all_images)) {
            $images_element = $dom->createElement('images');
            foreach ($all_images as $image_url) {
                $image_element = $dom->createElement('image');
                $image_element->setAttribute('src', $image_url);
                $images_element->appendChild($image_element);
            }
            $item->appendChild($images_element);
        }

        // METADANE - grupowane w jedną sekcję meta_data
        $meta_data_element = $dom->createElement('meta_data');

        // Dodaj podstawowe metadane Malfini
        $this->add_meta_to_group($dom, $meta_data_element, '_malfini_code', $code);
        $this->add_meta_to_group($dom, $meta_data_element, '_malfini_trademark', $product['trademark'] ?? '');

        // Dodaj informacje o kategorii i typie
        if (!empty($product['categoryCode'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_category_code', $product['categoryCode']);
        }
        if (!empty($product['categoryName'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_category_name', $product['categoryName']);
        }
        if (!empty($product['type'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_type', $product['type']);
        }
        if (!empty($product['gender'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_gender', $product['gender']);
        }
        if (!empty($product['genderCode'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_gender_code', $product['genderCode']);
        }

        // Dodaj wszystkie dostępne PDF-y z API
        if (!empty($product['productCardPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_product_card_pdf', $product['productCardPdf']);
        }
        if (!empty($product['sizeChartPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_size_chart_pdf', $product['sizeChartPdf']);
        }
        if (!empty($product['additionalInformationPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_additional_info_pdf', $product['additionalInformationPdf']);
        }
        if (!empty($product['certificationPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_certification_pdf', $product['certificationPdf']);
        }
        if (!empty($product['declarationPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_declaration_pdf', $product['declarationPdf']);
        }
        if (!empty($product['technicalSpecificationPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_technical_spec_pdf', $product['technicalSpecificationPdf']);
        }
        if (!empty($product['userInformationPdf'])) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_user_info_pdf', $product['userInformationPdf']);
        }

        // Dodaj informacje o cenach i dostępności
        if ($min_price !== null && $min_price > 0) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_min_price', number_format($min_price, 2, '.', ''));
        }
        if ($max_price !== null && $max_price > 0) {
            $this->add_meta_to_group($dom, $meta_data_element, '_malfini_max_price', number_format($max_price, 2, '.', ''));
        }
        $this->add_meta_to_group($dom, $meta_data_element, '_malfini_total_stock', (string) $total_stock);
        $this->add_meta_to_group($dom, $meta_data_element, '_malfini_colors_count', (string) count($all_colors));
        $this->add_meta_to_group($dom, $meta_data_element, '_malfini_sizes_count', (string) count($all_sizes));

        // Dodaj sekcję meta_data do produktu
        $item->appendChild($meta_data_element);

        if (class_exists('MHI_Logger')) {
            MHI_Logger::info('Wygenerowano XML dla produktu ' . $code . ' - ' . count($all_colors) . ' kolorów, ' .
                count($all_sizes) . ' rozmiarów, ' . count($all_images) . ' obrazów, stan: ' . $total_stock);
        }
    }

    /**
     * Pobiera cenę dla danego SKU z danych API.
     *
     * @param string $sku SKU produktu (productSizeCode).
     * @param array|null $price_data Nieużywane - dane są już w mapie.
     * @return float Cena produktu.
     */
    private function get_price_for_sku($sku, $price_data = null)
    {
        // Sprawdź bezpośrednio w mapie cen po productSizeCode
        if (isset($this->prices_data[$sku])) {
            $price_item = $this->prices_data[$sku];
            return (float) ($price_item['price'] ?? 50.00);
        }

        return 50.00; // Domyślna cena jeśli nie znaleziono
    }

    /**
     * Pobiera stan magazynowy dla danego SKU z danych API.
     *
     * @param string $sku SKU produktu (productSizeCode).
     * @param array|null $availability_data Nieużywane - dane są już w mapie.
     * @return int Stan magazynowy.
     */
    private function get_stock_for_sku($sku, $availability_data = null)
    {
        // Sprawdź bezpośrednio w mapie dostępności po productSizeCode
        if (isset($this->availabilities_data[$sku])) {
            $avail_item = $this->availabilities_data[$sku];
            return (int) ($avail_item['quantity'] ?? 0);
        }

        return 0;
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
     * Dodaje metadane do produktu (stara metoda - zachowana dla kompatybilności).
     *
     * @param DOMDocument $dom Dokument XML.
     * @param DOMElement $parent Element nadrzędny.
     * @param string $key Klucz metadanych.
     * @param string $value Wartość metadanych.
     */
    private function add_meta_data($dom, $parent, $key, $value)
    {
        $meta_data = $dom->createElement('meta_data');
        $this->add_xml_element($dom, $meta_data, 'key', $key);
        $this->add_xml_element($dom, $meta_data, 'value', $value);
        $parent->appendChild($meta_data);
    }

    /**
     * Dodaje pojedynczy element meta do grupy meta_data.
     *
     * @param DOMDocument $dom Dokument XML.
     * @param DOMElement $meta_data_group Element grupy meta_data.
     * @param string $key Klucz metadanych.
     * @param string $value Wartość metadanych.
     */
    private function add_meta_to_group($dom, $meta_data_group, $key, $value)
    {
        $meta_element = $dom->createElement('meta');
        $this->add_xml_element($dom, $meta_element, 'key', $key);
        $this->add_xml_element($dom, $meta_element, 'value', $value);
        $meta_data_group->appendChild($meta_element);
    }
}
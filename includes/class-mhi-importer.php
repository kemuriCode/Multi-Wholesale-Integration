<?php
/**
 * Klasa obsługująca import produktów do WooCommerce
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdź czy funkcje WooCommerce są dostępne
if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku($sku)
    {
        global $wpdb;
        MHI_Logger::warning('Funkcja wc_get_product_id_by_sku użyta przed załadowaniem WooCommerce');
        $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
        return $product_id;
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id)
    {
        MHI_Logger::warning('Funkcja wc_get_product użyta przed załadowaniem WooCommerce');
        return get_post($product_id);
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product
    {
        private $id = 0;
        private $data = array();

        public function set_sku($sku)
        {
            MHI_Logger::warning('Próba użycia klasy WC_Product przed załadowaniem WooCommerce');
            $this->data['sku'] = $sku;
            return true;
        }

        public function save()
        {
            MHI_Logger::warning('Próba zapisania produktu WC_Product przed załadowaniem WooCommerce');
            // Zaslepka - tworzymy pusty wpis
            if (empty($this->id)) {
                $this->id = wp_insert_post(array(
                    'post_title' => isset($this->data['name']) ? $this->data['name'] : 'Produkt',
                    'post_type' => 'product',
                    'post_status' => 'publish'
                ));

                if ($this->id && isset($this->data['sku'])) {
                    update_post_meta($this->id, '_sku', $this->data['sku']);
                }
            }
            return $this->id;
        }

        // Pozostałe wymagane metody jako zaślepki
        public function set_name($name)
        {
            $this->data['name'] = $name;
        }
        public function set_description($desc)
        {
            $this->data['description'] = $desc;
        }
        public function set_short_description($desc)
        {
            $this->data['short_description'] = $desc;
        }
        public function set_status($status)
        {
            $this->data['status'] = $status;
        }
        public function set_featured($featured)
        {
            $this->data['featured'] = $featured;
        }
        public function set_catalog_visibility($visibility)
        {
            $this->data['visibility'] = $visibility;
        }
        public function set_regular_price($price)
        {
            $this->data['regular_price'] = $price;
        }
        public function set_sale_price($price)
        {
            $this->data['sale_price'] = $price;
        }
        public function set_virtual($virtual)
        {
            $this->data['virtual'] = $virtual;
        }
        public function set_downloadable($downloadable)
        {
            $this->data['downloadable'] = $downloadable;
        }
        public function set_manage_stock($manage)
        {
            $this->data['manage_stock'] = $manage;
        }
        public function set_stock_quantity($qty)
        {
            $this->data['stock_quantity'] = $qty;
        }
        public function set_stock_status($status)
        {
            $this->data['stock_status'] = $status;
        }
        public function set_backorders($backorders)
        {
            $this->data['backorders'] = $backorders;
        }
        public function set_sold_individually($sold)
        {
            $this->data['sold_individually'] = $sold;
        }
        public function set_weight($weight)
        {
            $this->data['weight'] = $weight;
        }
        public function set_length($length)
        {
            $this->data['length'] = $length;
        }
        public function set_width($width)
        {
            $this->data['width'] = $width;
        }
        public function set_height($height)
        {
            $this->data['height'] = $height;
        }
        public function set_reviews_allowed($allowed)
        {
            $this->data['reviews_allowed'] = $allowed;
        }
        public function set_purchase_note($note)
        {
            $this->data['purchase_note'] = $note;
        }
        public function set_menu_order($order)
        {
            $this->data['menu_order'] = $order;
        }

        public function get_name()
        {
            return isset($this->data['name']) ? $this->data['name'] : '';
        }
        public function get_description()
        {
            return isset($this->data['description']) ? $this->data['description'] : '';
        }
        public function get_short_description()
        {
            return isset($this->data['short_description']) ? $this->data['short_description'] : '';
        }
        public function get_regular_price()
        {
            return isset($this->data['regular_price']) ? $this->data['regular_price'] : '';
        }
        public function get_sale_price()
        {
            return isset($this->data['sale_price']) ? $this->data['sale_price'] : '';
        }
        public function get_stock_quantity()
        {
            return isset($this->data['stock_quantity']) ? $this->data['stock_quantity'] : 0;
        }
    }
}

if (!function_exists('wc_attribute_taxonomy_name')) {
    function wc_attribute_taxonomy_name($name)
    {
        MHI_Logger::warning('Funkcja wc_attribute_taxonomy_name użyta przed załadowaniem WooCommerce');
        return 'pa_' . sanitize_title($name);
    }
}

if (!function_exists('wc_attribute_taxonomy_id_by_name')) {
    function wc_attribute_taxonomy_id_by_name($name)
    {
        MHI_Logger::warning('Funkcja wc_attribute_taxonomy_id_by_name użyta przed załadowaniem WooCommerce');
        return false;
    }
}

if (!function_exists('wc_sanitize_taxonomy_name')) {
    function wc_sanitize_taxonomy_name($name)
    {
        MHI_Logger::warning('Funkcja wc_sanitize_taxonomy_name użyta przed załadowaniem WooCommerce');
        return sanitize_title($name);
    }
}

if (!function_exists('wc_create_attribute')) {
    function wc_create_attribute($args)
    {
        MHI_Logger::warning('Funkcja wc_create_attribute użyta przed załadowaniem WooCommerce');
        return false;
    }
}

/**
 * Klasa MHI_Importer
 * 
 * Obsługuje import produktów, kategorii i atrybutów do WooCommerce
 * z plików XML generowanych przez integracje z hurtowniami.
 */
class MHI_Importer
{
    /**
     * Nazwa hurtowni.
     *
     * @var string
     */
    private $supplier_name;

    /**
     * Ścieżka do pliku XML.
     *
     * @var string
     */
    private $xml_file;

    /**
     * Identyfikator zadania importu.
     *
     * @var string
     */
    private $import_id;

    /**
     * Rozmiar partii przetwarzanych produktów.
     *
     * @var int
     */
    private $batch_size = 10;

    /**
     * Status importu.
     *
     * @var array
     */
    private $status = array(
        'status' => 'idle',
        'total' => 0,
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'current_product' => '',
        'message' => '',
        'percent' => 0,
        'start_time' => 0,
        'end_time' => 0,
        'elapsed_time' => 0,
        'estimated_time' => 0,
    );

    /**
     * Czy proces importu powinien zostać zatrzymany.
     *
     * @var bool
     */
    private $should_stop = false;

    /**
     * Konstruktor.
     *
     * @param string $supplier_name Nazwa hurtowni.
     */
    public function __construct($supplier_name)
    {
        $this->supplier_name = sanitize_text_field($supplier_name);
        $this->import_id = 'mhi_import_' . $this->supplier_name . '_' . time();
        $this->load_status();

        // Pobierz ścieżkę do pliku XML
        $upload_dir = wp_upload_dir();
        $this->xml_file = trailingslashit($upload_dir['basedir']) . 'hurtownie/' . $this->supplier_name . '/woocommerce_import_' . $this->supplier_name . '.xml';
    }

    /**
     * Rozpoczyna import produktów.
     * 
     * @return bool|WP_Error True jeśli import został zainicjowany, obiekt WP_Error w przypadku błędu.
     */
    public function start_import()
    {
        // Sprawdź czy WooCommerce jest dostępne
        if (!class_exists('WooCommerce')) {
            MHI_Logger::error('WooCommerce nie jest aktywny. Nie można rozpocząć importu produktów.');
            return new WP_Error('woocommerce_missing', __('WooCommerce nie jest aktywny. Import produktów jest niemożliwy.', 'multi-hurtownie-integration'));
        }

        // Sprawdź czy wszystkie potrzebne funkcje WooCommerce są dostępne
        $missing_functions = [];
        foreach (['wc_get_product_id_by_sku', 'wc_get_product', 'wc_attribute_taxonomy_name', 'wc_attribute_taxonomy_id_by_name', 'wc_sanitize_taxonomy_name', 'wc_create_attribute'] as $func) {
            if (!function_exists($func)) {
                $missing_functions[] = $func;
            }
        }

        if (!empty($missing_functions) || !class_exists('WC_Product')) {
            $missing = implode(', ', $missing_functions);
            $missing .= !class_exists('WC_Product') ? (empty($missing) ? 'WC_Product' : ', WC_Product') : '';
            MHI_Logger::error('Brakujące elementy WooCommerce: ' . $missing);
            return new WP_Error('woocommerce_incomplete', __('Brakujące elementy WooCommerce. Import produktów jest niemożliwy.', 'multi-hurtownie-integration'));
        }

        // Sprawdź czy plik XML istnieje
        if (!file_exists($this->xml_file)) {
            return new WP_Error('xml_file_missing', __('Nie znaleziono pliku XML do importu.', 'multi-hurtownie-integration'));
        }

        try {
            // Załaduj plik XML
            $xml = simplexml_load_file($this->xml_file);
            if (!$xml) {
                return new WP_Error('xml_load_failed', __('Nie można załadować pliku XML.', 'multi-hurtownie-integration'));
            }

            // Przygotuj status importu
            $products_count = count($xml->children());
            $this->status = array(
                'status' => 'running',
                'total' => $products_count,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'current_product' => '',
                'message' => sprintf(
                    __('Rozpoczynam import %d produktów z hurtowni %s', 'multi-hurtownie-integration'),
                    $products_count,
                    $this->supplier_name
                ),
                'percent' => 0,
                'start_time' => time(),
                'end_time' => 0,
                'elapsed_time' => 0,
                'estimated_time' => 0,
            );
            $this->save_status();

            // Uruchom pierwszy batch w tle
            $this->schedule_batch_import(1);

            MHI_Logger::info(sprintf(
                'Rozpoczęto import produktów z hurtowni %s. Łącznie: %d produktów.',
                $this->supplier_name,
                $products_count
            ));

            return true;
        } catch (Exception $e) {
            MHI_Logger::error('Błąd podczas inicjalizacji importu: ' . $e->getMessage());
            return new WP_Error('import_init_error', $e->getMessage());
        }
    }

    /**
     * Planuje akcję importu partii produktów.
     * 
     * @param int $batch_number Numer partii.
     * @return bool True jeśli zadanie zostało zaplanowane.
     */
    private function schedule_batch_import($batch_number)
    {
        $args = array(
            'import_id' => $this->import_id,
            'supplier_name' => $this->supplier_name,
            'batch_number' => $batch_number
        );

        // Sprawdź czy Action Scheduler jest dostępny
        if (function_exists('as_schedule_single_action')) {
            // Zaplanuj akcję za 5 sekund
            $scheduled = as_schedule_single_action(time() + 5, 'mhi_process_import_batch', $args);

            if ($scheduled) {
                MHI_Logger::info(sprintf(
                    'Zaplanowano partię %d dla importu %s',
                    $batch_number,
                    $this->import_id
                ));
                return true;
            } else {
                MHI_Logger::error(sprintf(
                    'Nie udało się zaplanować partii %d dla importu %s',
                    $batch_number,
                    $this->import_id
                ));
                return false;
            }
        } else {
            // Spróbuj użyć globalnego obiektu procesów w tle, jeśli jest dostępny
            global $mhi_background_process;
            if (isset($mhi_background_process) && method_exists($mhi_background_process, 'schedule_next_batch')) {
                return $mhi_background_process->schedule_next_batch($this->import_id, $this->supplier_name, $batch_number, 5);
            }

            MHI_Logger::error('Nie można zaplanować importu - Action Scheduler nie jest dostępny!');
            return false;
        }
    }

    /**
     * Przetwarza partię produktów z pliku XML.
     * 
     * @param int $batch_number Numer partii.
     * @return bool True jeśli przetwarzanie zakończyło się sukcesem.
     */
    public function process_batch($batch_number)
    {
        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // Dodaj logowanie na początek
        MHI_Logger::info('Rozpoczęto przetwarzanie partii ' . $batch_number . ' dla dostawcy ' . $this->supplier_name);
        error_log('MHI DEBUG: Rozpoczęto przetwarzanie partii ' . $batch_number . ' dla dostawcy ' . $this->supplier_name);

        // Sprawdź czy import powinien zostać zatrzymany
        if ($this->should_stop()) {
            MHI_Logger::warning('Wykryto flagę zatrzymania importu. Zatrzymuję przetwarzanie partii ' . $batch_number);
            $this->mark_as_stopped();
            return false;
        }

        try {
            // Załaduj plik XML z obsługą dużych plików
            libxml_use_internal_errors(true);
            $xml_content = file_get_contents($this->xml_file);

            if (!$xml_content) {
                $this->update_status([
                    'status' => 'error',
                    'message' => 'Nie można załadować pliku XML: ' . $this->xml_file,
                ]);
                MHI_Logger::error('Nie można załadować pliku XML: ' . $this->xml_file);
                return false;
            }

            $xml = simplexml_load_string($xml_content);

            if (!$xml) {
                $error_message = "Błędy parsowania XML:\n";
                foreach (libxml_get_errors() as $error) {
                    $error_message .= "Linia " . $error->line . ": " . $error->message . "\n";
                }
                libxml_clear_errors();

                $this->update_status([
                    'status' => 'error',
                    'message' => 'Nie można przetworzyć pliku XML: błędy składni.',
                ]);
                MHI_Logger::error('Błąd parsowania XML: ' . $error_message);
                return false;
            }

            // Oblicz indeks początkowy i końcowy dla danej partii
            $start_index = ($batch_number - 1) * $this->batch_size;
            $end_index = min($start_index + $this->batch_size, $this->status['total']);

            // Pobierz produkty z danej partii
            $products = array_slice((array) $xml->children(), $start_index, $this->batch_size);

            // Dodatkowe debugowanie dla liczby produktów
            error_log('MHI DEBUG: Znaleziono ' . count($products) . ' produktów do przetworzenia w partii ' . $batch_number);
            MHI_Logger::info('Znaleziono ' . count($products) . ' produktów do przetworzenia w partii ' . $batch_number);

            // Aktualizuj status
            $this->update_status([
                'message' => sprintf(
                    __('Przetwarzanie produktów %d-%d z %d', 'multi-hurtownie-integration'),
                    $start_index + 1,
                    $end_index,
                    $this->status['total']
                )
            ]);

            // Przetwórz produkty z partii
            foreach ($products as $index => $product) {
                // Sprawdź czy import powinien zostać zatrzymany przed każdym produktem
                if ($this->should_stop()) {
                    MHI_Logger::warning('Wykryto flagę zatrzymania importu podczas przetwarzania produktu. Zatrzymuję import.');
                    $this->mark_as_stopped();
                    return false;
                }

                // Aktualizuj licznik przetworzonych produktów
                $current_index = $start_index + $index + 1;
                $this->status['processed'] = $current_index;
                $this->status['percent'] = round(($current_index / $this->status['total']) * 100);

                // Aktualizuj czas
                $this->status['elapsed_time'] = time() - $this->status['start_time'];
                if ($this->status['processed'] > 0) {
                    $time_per_product = $this->status['elapsed_time'] / $this->status['processed'];
                    $remaining_products = $this->status['total'] - $this->status['processed'];
                    $this->status['estimated_time'] = round($time_per_product * $remaining_products);
                }

                // Pobierz SKU produktu
                $sku = (string) $product->sku;
                if (empty($sku)) {
                    $sku = (string) $product->id;
                }

                $this->status['current_product'] = $sku;
                $this->save_status();

                // Dodatkowe logowanie przed importem produktu
                error_log('MHI DEBUG: Przetwarzanie produktu: ' . $sku);

                // Importuj produkt
                try {
                    $result = $this->import_product($product);

                    // Dodatkowe logowanie po imporcie produktu
                    error_log('MHI DEBUG: Wynik importu produktu ' . $sku . ': ' . $result);

                    // Aktualizuj statystyki na podstawie wyniku
                    if ($result === 'created') {
                        $this->status['created']++;
                        error_log('MHI DEBUG: Produkt utworzony: ' . $sku);
                    } elseif ($result === 'updated') {
                        $this->status['updated']++;
                        error_log('MHI DEBUG: Produkt zaktualizowany: ' . $sku);
                    } elseif ($result === 'skipped') {
                        $this->status['skipped']++;
                        error_log('MHI DEBUG: Produkt pominięty: ' . $sku);
                    } else {
                        $this->status['failed']++;
                        error_log('MHI DEBUG: Produkt nie zaimportowany: ' . $sku);
                    }
                } catch (Exception $e) {
                    MHI_Logger::error(sprintf(
                        'Błąd podczas importu produktu %s: %s',
                        $sku,
                        $e->getMessage()
                    ));
                    error_log('MHI DEBUG: Wyjątek podczas importu produktu ' . $sku . ': ' . $e->getMessage());
                    $this->status['failed']++;
                }

                // Zapisz status co każdy produkt
                $this->save_status();
            }

            // Sprawdź ponownie czy nie należy zatrzymać importu
            if ($this->should_stop()) {
                MHI_Logger::warning('Wykryto flagę zatrzymania importu po przetworzeniu partii. Zatrzymuję import.');
                $this->mark_as_stopped();
                return false;
            }

            // Sprawdź czy to była ostatnia partia
            if ($end_index >= $this->status['total']) {
                // Import zakończony
                $this->complete_import();
                error_log('MHI DEBUG: Import zakończony. Przetworzono ' . $this->status['processed'] . ' produktów.');
                return true;
            } else {
                // Zaplanuj następną partię
                error_log('MHI DEBUG: Planowanie następnej partii: ' . ($batch_number + 1));
                $this->schedule_batch_import($batch_number + 1);
                return true;
            }
        } catch (Exception $e) {
            MHI_Logger::error('Wyjątek podczas przetwarzania partii ' . $batch_number . ': ' . $e->getMessage());
            error_log('MHI DEBUG: Wyjątek podczas przetwarzania partii ' . $batch_number . ': ' . $e->getMessage());

            // Aktualizuj status na błąd
            $this->update_status([
                'status' => 'error',
                'message' => sprintf(
                    __('Błąd podczas importu: %s', 'multi-hurtownie-integration'),
                    $e->getMessage()
                )
            ]);

            return false;
        }
    }

    /**
     * Importuje pojedynczy produkt do WooCommerce.
     * 
     * @param SimpleXMLElement $product_data Dane produktu z XML.
     * @return string Status importu (created, updated, skipped, failed).
     */
    private function import_product($product_data)
    {
        // Sprawdź czy WooCommerce jest dostępne
        if (!class_exists('WooCommerce')) {
            MHI_Logger::error('WooCommerce nie jest aktywny. Import produktów jest niemożliwy.');
            error_log('MHI DEBUG: WooCommerce nie jest aktywny. Import produktów jest niemożliwy.');
            return 'failed';
        }

        try {
            // Pobierz SKU produktu
            $sku = (string) $product_data->sku;
            if (empty($sku)) {
                $sku = (string) $product_data->id;
            }

            error_log('MHI DEBUG: Rozpoczęto import produktu: ' . $sku);

            // Debugowanie nazwy produktu
            $product_name = (string) $product_data->name;
            if (empty($product_name)) {
                $product_name = (string) $product_data->n; // Czasami nazwa może być w polu 'n'
            }
            error_log('MHI DEBUG: Nazwa produktu: ' . $product_name);

            // Sprawdź czy produkt już istnieje
            $product_id = null;
            $product = null;

            if (function_exists('wc_get_product_id_by_sku')) {
                error_log('MHI DEBUG: Sprawdzanie czy produkt istnieje: ' . $sku);
                $product_id = wc_get_product_id_by_sku($sku);
                error_log('MHI DEBUG: ID produktu: ' . ($product_id ? $product_id : 'brak'));

                if ($product_id && function_exists('wc_get_product')) {
                    $product = wc_get_product($product_id);
                    error_log('MHI DEBUG: Produkt ' . $sku . ' istnieje, typ: ' . (is_object($product) ? get_class($product) : 'nie jest obiektem'));
                }
            } else {
                // Fallback gdy WooCommerce nie jest dostępne
                global $wpdb;
                error_log('MHI DEBUG: Funkcja wc_get_product_id_by_sku niedostępna. Używam zapasowej metody SQL.');
                $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
                MHI_Logger::warning('Funkcja wc_get_product_id_by_sku niedostępna. Używam zapasowej metody.');
            }

            // Sprawdź czy dane produktu się zmieniły
            if ($product && $this->product_unchanged($product, $product_data)) {
                error_log('MHI DEBUG: Produkt ' . $sku . ' nie wymaga aktualizacji.');
                return 'skipped';
            }

            // Dane podstawowe produktu
            $product_args = array(
                'name' => $product_name,
                'description' => (string) $product_data->description,
                'short_description' => (string) $product_data->short_description,
                'status' => 'publish',
                'featured' => (string) $product_data->featured === 'yes',
                'catalog_visibility' => (string) $product_data->visibility ?: 'visible',
                'sku' => $sku,
                'regular_price' => (string) $product_data->regular_price,
                'sale_price' => (string) $product_data->sale_price,
                'virtual' => (string) $product_data->virtual === 'yes',
                'downloadable' => (string) $product_data->downloadable === 'yes',
                'manage_stock' => isset($product_data->manage_stock) ? ((string) $product_data->manage_stock === 'yes') : true,
                'stock_quantity' => (int) $product_data->stock_quantity,
                'stock_status' => (string) $product_data->stock_status ?: 'instock',
                'backorders' => (string) $product_data->backorders ?: 'no',
                'sold_individually' => (string) $product_data->sold_individually === 'yes',
                'weight' => (string) $product_data->weight,
                'length' => (string) $product_data->length,
                'width' => (string) $product_data->width,
                'height' => (string) $product_data->height,
                'reviews_allowed' => (string) $product_data->reviews_allowed === 'yes',
                'purchase_note' => (string) $product_data->purchase_note,
                'menu_order' => (int) $product_data->menu_order,
            );

            error_log('MHI DEBUG: Przygotowano dane podstawowe produktu ' . $sku);

            // Utworzenie lub aktualizacja produktu
            if (!$product) {
                // Nowy produkt
                error_log('MHI DEBUG: Tworzenie nowego produktu: ' . $sku);

                if (!class_exists('WC_Product')) {
                    MHI_Logger::error('Klasa WC_Product niedostępna. Nie można utworzyć nowego produktu.');
                    error_log('MHI DEBUG: Klasa WC_Product niedostępna. Nie można utworzyć nowego produktu.');
                    return 'failed';
                }

                // Wyraźnie sprawdź czy klasa istnieje
                if (!class_exists('WC_Product')) {
                    error_log('MHI DEBUG: WC_Product nie istnieje po ponownym sprawdzeniu');
                    return 'failed';
                }

                try {
                    $product = new WC_Product();
                    error_log('MHI DEBUG: Utworzono instancję WC_Product');

                    $product->set_sku($sku);
                    $this->update_product_data($product, $product_args);

                    error_log('MHI DEBUG: Zapisywanie nowego produktu ' . $sku);
                    $product_id = $product->save();
                    error_log('MHI DEBUG: Produkt zapisany, ID: ' . $product_id);

                    // Dodaj kategorie, atrybuty i obrazki
                    $this->add_product_categories($product_id, $product_data);
                    $this->add_product_attributes($product_id, $product_data);
                    $this->add_product_images($product_id, $product_data);

                    // Oznacz produkt jako zaimportowany przez MHI
                    update_post_meta($product_id, '_mhi_imported', 'yes');
                    update_post_meta($product_id, '_mhi_supplier', $this->supplier_name);
                    update_post_meta($product_id, '_mhi_import_date', current_time('mysql'));

                    error_log('MHI DEBUG: Zakończono tworzenie produktu ' . $sku);
                    return 'created';
                } catch (Exception $e) {
                    error_log('MHI DEBUG: Wyjątek podczas tworzenia produktu ' . $sku . ': ' . $e->getMessage());
                    throw $e; // Przekazujemy wyjątek wyżej
                }
            } else {
                // Aktualizacja istniejącego produktu
                error_log('MHI DEBUG: Aktualizacja istniejącego produktu: ' . $sku);
                $this->update_product_data($product, $product_args);

                try {
                    // Zamiast wywoływać save(), użyjmy bardziej uniwersalnego podejścia
                    if ($product instanceof WC_Product && method_exists($product, 'save')) {
                        // To jest prawdziwy obiekt WC_Product z WooCommerce
                        error_log('MHI DEBUG: Zapisywanie produktu WC_Product ' . $sku);
                        $product->save();
                        error_log('MHI DEBUG: Produkt WC_Product zapisany ' . $sku);
                    } else {
                        // To jest WP_Post lub inna klasa bez metody save()
                        error_log('MHI DEBUG: Zapisywanie produktu innego typu ' . $sku . ', klasa: ' . get_class($product));
                        if (is_object($product) && isset($product->ID)) {
                            $post_data = [
                                'ID' => $product->ID,
                                'post_title' => isset($product_args['name']) ? $product_args['name'] : '',
                                'post_content' => isset($product_args['description']) ? $product_args['description'] : '',
                                'post_excerpt' => isset($product_args['short_description']) ? $product_args['short_description'] : '',
                                'post_status' => isset($product_args['status']) ? $product_args['status'] : 'publish',
                            ];
                            wp_update_post($post_data);
                            error_log('MHI DEBUG: Post zaktualizowany wp_update_post: ' . $product->ID);

                            // Aktualizuj meta dane
                            if (isset($product_args['regular_price'])) {
                                update_post_meta($product->ID, '_regular_price', $product_args['regular_price']);
                            }
                            if (isset($product_args['sale_price'])) {
                                update_post_meta($product->ID, '_sale_price', $product_args['sale_price']);
                            }
                            if (isset($product_args['stock_quantity'])) {
                                update_post_meta($product->ID, '_stock', $product_args['stock_quantity']);
                            }
                            error_log('MHI DEBUG: Meta dane produktu zaktualizowane ' . $sku);
                        } else {
                            error_log('MHI DEBUG: Nie można zaktualizować produktu - brak ID lub nie jest obiektem');
                        }
                    }

                    // Aktualizuj kategorie, atrybuty i obrazki
                    $this->add_product_categories($product_id, $product_data);
                    $this->add_product_attributes($product_id, $product_data);
                    $this->add_product_images($product_id, $product_data);

                    error_log('MHI DEBUG: Zakończono aktualizację produktu ' . $sku);
                    return 'updated';
                } catch (Exception $e) {
                    error_log('MHI DEBUG: Wyjątek podczas aktualizacji produktu ' . $sku . ': ' . $e->getMessage());
                    throw $e; // Przekazujemy wyjątek wyżej
                }
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $error_trace = $e->getTraceAsString();
            MHI_Logger::error('Wyjątek podczas importu produktu: ' . $error_message);
            error_log('MHI DEBUG: Wyjątek podczas importu produktu: ' . $error_message);
            error_log('MHI DEBUG: Trace: ' . $error_trace);
            return 'failed';
        }

        // Ten kod nie powinien się wykonać, ale dla bezpieczeństwa
        error_log('MHI DEBUG: Nieznany błąd - kod nie powinien tu dotrzeć');
        return 'failed';
    }

    /**
     * Aktualizuje dane produktu.
     * 
     * @param WC_Product $product Obiekt produktu.
     * @param array $data Dane produktu.
     */
    private function update_product_data($product, $data)
    {
        // Ustaw właściwości produktu
        if (isset($data['name']))
            $product->set_name($data['name']);
        if (isset($data['description']))
            $product->set_description($data['description']);
        if (isset($data['short_description']))
            $product->set_short_description($data['short_description']);
        if (isset($data['status']))
            $product->set_status($data['status']);
        if (isset($data['featured']))
            $product->set_featured($data['featured']);
        if (isset($data['catalog_visibility']))
            $product->set_catalog_visibility($data['catalog_visibility']);
        if (isset($data['regular_price']))
            $product->set_regular_price($data['regular_price']);
        if (isset($data['sale_price']))
            $product->set_sale_price($data['sale_price']);
        if (isset($data['virtual']))
            $product->set_virtual($data['virtual']);
        if (isset($data['downloadable']))
            $product->set_downloadable($data['downloadable']);
        if (isset($data['manage_stock']))
            $product->set_manage_stock($data['manage_stock']);
        if (isset($data['stock_quantity']))
            $product->set_stock_quantity($data['stock_quantity']);
        if (isset($data['stock_status']))
            $product->set_stock_status($data['stock_status']);
        if (isset($data['backorders']))
            $product->set_backorders($data['backorders']);
        if (isset($data['sold_individually']))
            $product->set_sold_individually($data['sold_individually']);
        if (isset($data['weight']))
            $product->set_weight($data['weight']);
        if (isset($data['length']))
            $product->set_length($data['length']);
        if (isset($data['width']))
            $product->set_width($data['width']);
        if (isset($data['height']))
            $product->set_height($data['height']);
        if (isset($data['reviews_allowed']))
            $product->set_reviews_allowed($data['reviews_allowed']);
        if (isset($data['purchase_note']))
            $product->set_purchase_note($data['purchase_note']);
        if (isset($data['menu_order']))
            $product->set_menu_order($data['menu_order']);
    }

    /**
     * Dodaje kategorie do produktu.
     * 
     * @param int $product_id ID produktu.
     * @param SimpleXMLElement $product_data Dane produktu z XML.
     */
    private function add_product_categories($product_id, $product_data)
    {
        if (!isset($product_data->categories)) {
            MHI_Logger::info(sprintf('Produkt %d nie ma kategorii.', $product_id));
            return;
        }

        // Dodajemy logowanie, aby łatwiej diagnozować problem
        MHI_Logger::info(sprintf('Rozpoczęto dodawanie kategorii dla produktu %d', $product_id));

        $categories_to_assign = array();
        $category_hierarchy = array();

        // Dodajemy logowanie XML kategorii
        $categories_xml = '';
        foreach ($product_data->categories->category as $category) {
            $categories_xml .= (string) $category . ', ';
        }
        MHI_Logger::info(sprintf('Kategorie w XML: %s', trim($categories_xml, ', ')));

        // Pierwszy przebieg - analizuj wszystkie kategorie i zbuduj strukturę hierarchii
        foreach ($product_data->categories->category as $category) {
            $category_name = (string) $category;

            // Specjalna obsługa dla Malfini - sprawdź czy to nie są numery
            if ($this->supplier_name === 'malfini' && is_numeric($category_name)) {
                MHI_Logger::warning(sprintf('Znaleziono liczbę zamiast nazwy kategorii: %s dla produktu %d', $category_name, $product_id));
                continue; // Pomijamy numeryczne "kategorie"
            }

            // Dodaj logowanie aktualnie przetwarzanej kategorii
            MHI_Logger::info(sprintf('Przetwarzanie kategorii: %s', $category_name));

            // Zamień encje HTML na znaki
            $category_name = str_replace('&gt;', '>', $category_name);
            $category_name = html_entity_decode($category_name);
            MHI_Logger::info(sprintf('Po konwersji HTML: %s', $category_name));

            // Sprawdź czy mamy do czynienia z hierarchią kategorii
            if (strpos($category_name, '>') !== false) {
                // Podziel na części hierarchii
                $categories_path = array_map('trim', explode('>', $category_name));
                MHI_Logger::info(sprintf('Hierarchia kategorii: %s', implode(' > ', $categories_path)));

                // Utwórz ścieżkę kategorii
                $parent_id = 0;
                $full_path = '';

                foreach ($categories_path as $index => $cat_name) {
                    if (empty($cat_name))
                        continue;

                    // Dla pierwszego elementu sprawdź, czy istnieje już główna kategoria
                    if ($index === 0) {
                        // Najpierw szukaj dokładnie po nazwie
                        $matching_terms = get_terms(array(
                            'taxonomy' => 'product_cat',
                            'name' => $cat_name,
                            'hide_empty' => false,
                            'fields' => 'all',
                            'parent' => 0 // Tylko kategorie główne
                        ));

                        $term = null;
                        if (!empty($matching_terms) && !is_wp_error($matching_terms)) {
                            $term = reset($matching_terms); // Pierwszy element
                            $term = array(
                                'term_id' => $term->term_id,
                                'term_taxonomy_id' => $term->term_taxonomy_id
                            );
                            MHI_Logger::info(sprintf('Znaleziono istniejącą kategorię główną po dokładnej nazwie: %s (term_id: %d)', $cat_name, $term['term_id']));
                        } else {
                            // Sprawdź używając term_exists() jako zapasowe podejście
                            $term = term_exists($cat_name, 'product_cat');
                        }

                        if (!$term) {
                            // Utwórz główną kategorię
                            $term = wp_insert_term($cat_name, 'product_cat');
                            if (!is_wp_error($term)) {
                                MHI_Logger::info(sprintf('Utworzono kategorię główną: %s (term_id: %d)', $cat_name, $term['term_id']));
                                update_term_meta($term['term_id'], '_mhi_imported', 'yes');
                                update_term_meta($term['term_id'], '_mhi_supplier', $this->supplier_name);
                            } else {
                                MHI_Logger::error(sprintf(
                                    'Błąd tworzenia kategorii głównej %s: %s',
                                    $cat_name,
                                    $term->get_error_message()
                                ));
                                continue;
                            }
                        }

                        $parent_id = $term['term_id'];
                        $full_path = $cat_name;

                        // Zachowaj informację o kategorii głównej
                        $category_hierarchy[$cat_name] = $parent_id;
                    } else {
                        $full_path .= ' > ' . $cat_name;

                        // Najpierw szukaj dokładnie po nazwie i rodzicu
                        $matching_terms = get_terms(array(
                            'taxonomy' => 'product_cat',
                            'name' => $cat_name,
                            'hide_empty' => false,
                            'fields' => 'all',
                            'parent' => $parent_id
                        ));

                        $term = null;
                        if (!empty($matching_terms) && !is_wp_error($matching_terms)) {
                            $term = reset($matching_terms); // Pierwszy element
                            $term = array(
                                'term_id' => $term->term_id,
                                'term_taxonomy_id' => $term->term_taxonomy_id
                            );
                            MHI_Logger::info(sprintf(
                                'Znaleziono istniejącą podkategorię po dokładnej nazwie: %s (term_id: %d, parent: %d)',
                                $cat_name,
                                $term['term_id'],
                                $parent_id
                            ));
                        } else {
                            // Sprawdź używając term_exists() jako zapasowe podejście
                            $term = term_exists($cat_name, 'product_cat', $parent_id);
                        }

                        if (!$term) {
                            // Utwórz podkategorię
                            $term = wp_insert_term($cat_name, 'product_cat', array('parent' => $parent_id));
                            if (!is_wp_error($term)) {
                                MHI_Logger::info(sprintf(
                                    'Utworzono podkategorię: %s (term_id: %d, parent: %d)',
                                    $cat_name,
                                    $term['term_id'],
                                    $parent_id
                                ));
                                update_term_meta($term['term_id'], '_mhi_imported', 'yes');
                                update_term_meta($term['term_id'], '_mhi_supplier', $this->supplier_name);
                            } else {
                                MHI_Logger::error(sprintf(
                                    'Błąd tworzenia podkategorii %s (parent %s): %s',
                                    $cat_name,
                                    $parent_id,
                                    $term->get_error_message()
                                ));
                                continue;
                            }
                        }

                        $parent_id = $term['term_id'];

                        // Zachowaj informację o podkategorii
                        $category_hierarchy[$full_path] = $parent_id;
                    }
                }

                // Dodaj ostatni element (najgłębsza kategoria) do przypisania
                if ($parent_id > 0) {
                    $categories_to_assign[] = $parent_id;
                    MHI_Logger::info(sprintf('Dodano kategorię do przypisania: ID %d (%s)', $parent_id, $full_path));
                }
            } else {
                // Pojedyncza kategoria (bez hierarchii)
                // Najpierw szukaj dokładnie po nazwie
                $matching_terms = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'name' => $category_name,
                    'hide_empty' => false,
                    'fields' => 'all'
                ));

                $term = null;
                if (!empty($matching_terms) && !is_wp_error($matching_terms)) {
                    $term = reset($matching_terms); // Pierwszy element
                    $term = array(
                        'term_id' => $term->term_id,
                        'term_taxonomy_id' => $term->term_taxonomy_id
                    );
                    MHI_Logger::info(sprintf('Znaleziono istniejącą pojedynczą kategorię po dokładnej nazwie: %s (term_id: %d)', $category_name, $term['term_id']));
                } else {
                    // Sprawdź używając term_exists() jako zapasowe podejście
                    $term = term_exists($category_name, 'product_cat');
                }

                if (!$term) {
                    // Utwórz kategorię
                    $term = wp_insert_term($category_name, 'product_cat');
                    if (!is_wp_error($term)) {
                        MHI_Logger::info(sprintf('Utworzono pojedynczą kategorię: %s (term_id: %d)', $category_name, $term['term_id']));
                        update_term_meta($term['term_id'], '_mhi_imported', 'yes');
                        update_term_meta($term['term_id'], '_mhi_supplier', $this->supplier_name);
                    } else {
                        MHI_Logger::error(sprintf(
                            'Błąd tworzenia pojedynczej kategorii %s: %s',
                            $category_name,
                            $term->get_error_message()
                        ));
                        continue;
                    }
                }

                // Zachowaj ID kategorii
                $category_hierarchy[$category_name] = $term['term_id'];
                $categories_to_assign[] = $term['term_id'];
                MHI_Logger::info(sprintf('Dodano pojedynczą kategorię do przypisania: ID %d (%s)', $term['term_id'], $category_name));
            }
        }

        // Usuń duplikaty kategorii
        $categories_to_assign = array_unique($categories_to_assign);

        // Przypisz unikalne kategorie do produktu
        if (!empty($categories_to_assign)) {
            MHI_Logger::info(sprintf('Przypisywanie kategorii do produktu %d: %s', $product_id, implode(', ', $categories_to_assign)));
            $result = wp_set_object_terms($product_id, $categories_to_assign, 'product_cat');

            if (is_wp_error($result)) {
                MHI_Logger::error(sprintf(
                    'Błąd podczas przypisywania kategorii do produktu %d: %s',
                    $product_id,
                    $result->get_error_message()
                ));
            } else {
                MHI_Logger::info(sprintf(
                    'Przypisano %d kategorii do produktu %d',
                    count($categories_to_assign),
                    $product_id
                ));
            }
        } else {
            MHI_Logger::warning(sprintf('Brak kategorii do przypisania dla produktu %d', $product_id));
        }
    }

    /**
     * Dodaje atrybuty do produktu.
     * 
     * @param int $product_id ID produktu.
     * @param SimpleXMLElement $product_data Dane produktu z XML.
     */
    private function add_product_attributes($product_id, $product_data)
    {
        if (!isset($product_data->attributes)) {
            MHI_Logger::info(sprintf('Produkt %d nie ma atrybutów.', $product_id));
            return;
        }

        // Dodajemy logowanie, aby łatwiej diagnozować problem
        MHI_Logger::info(sprintf('Rozpoczęto dodawanie atrybutów dla produktu %d', $product_id));

        $attributes = array();

        // Pobierz wszystkie istniejące atrybuty WooCommerce dla późniejszego porównania
        $existing_attributes = array();
        if (function_exists('wc_get_attribute_taxonomies')) {
            $wc_attributes = wc_get_attribute_taxonomies();
            foreach ($wc_attributes as $attr) {
                $existing_attributes[$attr->attribute_name] = $attr->attribute_id;
                $existing_attributes[$attr->attribute_label] = $attr->attribute_id;
            }
            MHI_Logger::info(sprintf('Znaleziono %d istniejących atrybutów w systemie', count($existing_attributes)));
        }

        // Pobierz listę atrybutów
        foreach ($product_data->attributes->attribute as $attribute) {
            // Obsługa różnych formatów XML - niektórzy dostawcy używają <name>, inni <n>
            $name = '';
            if (isset($attribute->name)) {
                $name = (string) $attribute->name;
            } elseif (isset($attribute->n)) {
                $name = (string) $attribute->n;
            }

            if (empty($name)) {
                MHI_Logger::warning(sprintf('Pominięto atrybut bez nazwy dla produktu %d', $product_id));
                continue;
            }

            MHI_Logger::info(sprintf('Przetwarzanie atrybutu: %s dla produktu %d', $name, $product_id));

            $values = array();

            // Pobierz wartości atrybutu - obsługa różnych formatów XML
            if (isset($attribute->value)) {
                $value = (string) $attribute->value;
                if (!empty($value)) {
                    // Sprawdź, czy wartość zawiera wiele opcji rozdzielonych przecinkami
                    if (strpos($value, ',') !== false) {
                        $separated_values = array_map('trim', explode(',', $value));
                        foreach ($separated_values as $val) {
                            if (!empty($val)) {
                                $values[] = $val;
                            }
                        }
                        MHI_Logger::info(sprintf('Znaleziono wiele wartości (%d) w atrybucie %s: %s', count($values), $name, $value));
                    } else {
                        $values[] = $value;
                    }
                }
            } elseif (isset($attribute->values)) {
                foreach ($attribute->values->value as $value) {
                    $value_str = (string) $value;
                    if (!empty($value_str)) {
                        $values[] = $value_str;
                    }
                }
            }

            if (empty($values)) {
                MHI_Logger::warning(sprintf('Pominięto atrybut %s bez wartości dla produktu %d', $name, $product_id));
                continue;
            }

            MHI_Logger::info(sprintf('Znaleziono %d wartości dla atrybutu %s: %s', count($values), $name, implode(', ', $values)));

            // Przygotuj nazwę taksonomii
            $slug = sanitize_title($name);
            $taxonomy = '';
            $attr_id = null;

            // Sprawdź czy atrybut już istnieje (po nazwie lub slugiem)
            if (isset($existing_attributes[$name])) {
                $attr_id = $existing_attributes[$name];
                MHI_Logger::info(sprintf('Znaleziono istniejący atrybut po nazwie: %s (ID: %d)', $name, $attr_id));
            } elseif (isset($existing_attributes[$slug])) {
                $attr_id = $existing_attributes[$slug];
                MHI_Logger::info(sprintf('Znaleziono istniejący atrybut po slugu: %s (ID: %d)', $slug, $attr_id));
            }

            // Używaj funkcji WooCommerce jeśli są dostępne
            if (function_exists('wc_attribute_taxonomy_name') && function_exists('wc_attribute_taxonomy_id_by_name')) {
                if (!$attr_id) {
                    $attr_id = wc_attribute_taxonomy_id_by_name($slug);
                }
                $taxonomy = wc_attribute_taxonomy_name($slug);
                MHI_Logger::info(sprintf('Przygotowano taksonomię: %s (slug: %s, attr_id: %s)', $taxonomy, $slug, $attr_id));
            } else {
                // Fallback gdy funkcje WooCommerce nie są dostępne
                $taxonomy = 'pa_' . $slug;
                MHI_Logger::warning(sprintf('Funkcje atrybutów WooCommerce niedostępne. Używam zapasowej taksonomii: %s', $taxonomy));
            }

            // Sprawdź czy taksonomia istnieje, jeśli nie - utwórz
            if (!taxonomy_exists($taxonomy)) {
                MHI_Logger::info(sprintf('Taksonomia %s nie istnieje, tworzenie...', $taxonomy));

                // Zarejestruj nową taksonomię atrybutu
                if (function_exists('wc_create_attribute')) {
                    // Sprawdź czy atrybut już istnieje po nazwie
                    if (!$attr_id) {
                        $attribute_id = wc_create_attribute(array(
                            'name' => $name,
                            'slug' => $slug,
                            'type' => 'select',
                            'order_by' => 'menu_order',
                            'has_archives' => false
                        ));

                        if (!is_wp_error($attribute_id)) {
                            MHI_Logger::info(sprintf('Utworzono atrybut globalny: %s (ID: %d)', $name, $attribute_id));

                            // Dodaj do kolekcji istniejących atrybutów
                            $existing_attributes[$name] = $attribute_id;
                            $existing_attributes[$slug] = $attribute_id;
                        } else {
                            MHI_Logger::error(sprintf('Błąd podczas tworzenia atrybutu %s: %s', $name, $attribute_id->get_error_message()));
                            continue;
                        }
                    }

                    // Zarejestruj taksonomię
                    register_taxonomy(
                        $taxonomy,
                        'product',
                        array(
                            'hierarchical' => false,
                            'show_ui' => true,
                            'query_var' => true,
                            'rewrite' => array('slug' => $slug),
                        )
                    );
                    MHI_Logger::info(sprintf('Zarejestrowano taksonomię: %s', $taxonomy));
                } else {
                    // Fallback - używamy własnej metody rejestracji taksonomii
                    register_taxonomy(
                        $taxonomy,
                        'product',
                        array(
                            'hierarchical' => false,
                            'show_ui' => true,
                            'query_var' => true,
                            'rewrite' => array('slug' => $slug),
                        )
                    );
                    MHI_Logger::warning(sprintf('Funkcja wc_create_attribute niedostępna. Utworzono tylko taksonomię dla %s.', $name));
                }
            } else {
                MHI_Logger::info(sprintf('Taksonomia %s już istnieje.', $taxonomy));
            }

            // Pobierz istniejące wartości dla taksonomii
            $existing_terms = array();
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'all'
            ));

            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $existing_terms[$term->name] = $term->term_id;
                    $existing_terms[$term->slug] = $term->term_id;
                }
                MHI_Logger::info(sprintf('Znaleziono %d istniejących wartości dla atrybutu %s', count($terms), $name));
            }

            // Upewnij się, że wartości atrybutu istnieją w taksonomii
            $term_ids = array();
            foreach ($values as $value) {
                if (empty($value))
                    continue;

                // Sprawdź najpierw czy wartość istnieje w naszej kolekcji
                if (isset($existing_terms[$value])) {
                    $term_id = $existing_terms[$value];
                    $term_ids[] = $term_id;
                    MHI_Logger::info(sprintf('Znaleziono istniejącą wartość atrybutu %s: %s (term_id: %d)', $name, $value, $term_id));
                    continue;
                }

                // Sprawdź slug wartości
                $value_slug = sanitize_title($value);
                if (isset($existing_terms[$value_slug])) {
                    $term_id = $existing_terms[$value_slug];
                    $term_ids[] = $term_id;
                    MHI_Logger::info(sprintf('Znaleziono istniejącą wartość atrybutu %s po slugu: %s (term_id: %d)', $name, $value, $term_id));
                    continue;
                }

                // Jeśli nie znaleziono, sprawdź używając term_exists
                $term = term_exists($value, $taxonomy);
                if (!$term) {
                    $term = wp_insert_term($value, $taxonomy);
                    if (!is_wp_error($term)) {
                        MHI_Logger::info(sprintf('Utworzono wartość atrybutu %s: %s (term_id: %d)', $name, $value, $term['term_id']));

                        // Dodaj do kolekcji istniejących wartości
                        $existing_terms[$value] = $term['term_id'];
                        $existing_terms[sanitize_title($value)] = $term['term_id'];
                    } else {
                        MHI_Logger::error(sprintf('Błąd podczas tworzenia wartości atrybutu %s: %s - %s', $name, $value, $term->get_error_message()));
                        continue;
                    }
                } else {
                    MHI_Logger::info(sprintf('Znaleziono istniejącą wartość atrybutu %s: %s (term_id: %d)', $name, $value, $term['term_id']));
                }

                if (!is_wp_error($term)) {
                    $term_ids[] = $term['term_id'];
                }
            }

            // Przypisz wartości do produktu
            if (!empty($term_ids)) {
                MHI_Logger::info(sprintf('Przypisywanie %d wartości atrybutu %s do produktu %d', count($term_ids), $name, $product_id));
                $result = wp_set_object_terms($product_id, $term_ids, $taxonomy);
                if (is_wp_error($result)) {
                    MHI_Logger::error(sprintf('Błąd podczas przypisywania atrybutu %s do produktu %d: %s', $name, $product_id, $result->get_error_message()));
                } else {
                    MHI_Logger::info(sprintf('Przypisano atrybut %s z %d wartościami do produktu %d', $name, count($term_ids), $product_id));
                }
            } else {
                MHI_Logger::warning(sprintf('Brak wartości do przypisania dla atrybutu %s produktu %d', $name, $product_id));
            }

            // Dodaj atrybut do tablicy atrybutów
            $is_visible = true;
            if (isset($attribute->visible)) {
                $is_visible = ((string) $attribute->visible === 'yes' || (string) $attribute->visible === '1');
            }

            $is_variation = false;
            if (isset($attribute->variation)) {
                $is_variation = ((string) $attribute->variation === 'yes' || (string) $attribute->variation === '1');
            }

            $attributes[$taxonomy] = array(
                'name' => $taxonomy,
                'value' => '',
                'position' => isset($attribute->position) ? (int) $attribute->position : 0,
                'is_visible' => $is_visible,
                'is_variation' => $is_variation,
                'is_taxonomy' => true
            );

            MHI_Logger::info(sprintf(
                'Dodano atrybut %s do tablicy atrybutów (widoczny: %s, wariacja: %s)',
                $taxonomy,
                $is_visible ? 'tak' : 'nie',
                $is_variation ? 'tak' : 'nie'
            ));
        }

        // Zapisz atrybuty w meta danych produktu
        if (!empty($attributes)) {
            MHI_Logger::info(sprintf('Zapisywanie %d atrybutów dla produktu %d', count($attributes), $product_id));
            $result = update_post_meta($product_id, '_product_attributes', $attributes);
            if (!$result) {
                MHI_Logger::error(sprintf('Błąd podczas zapisywania atrybutów dla produktu %d', $product_id));
            } else {
                MHI_Logger::info(sprintf('Zapisano %d atrybutów dla produktu %d', count($attributes), $product_id));
            }
        } else {
            MHI_Logger::warning(sprintf('Brak atrybutów do zapisania dla produktu %d', $product_id));
        }
    }

    /**
     * Dodaje obrazki do produktu.
     * 
     * @param int $product_id ID produktu.
     * @param SimpleXMLElement $product_data Dane produktu z XML.
     */
    private function add_product_images($product_id, $product_data)
    {
        if (!isset($product_data->images) || !isset($product_data->images->image)) {
            MHI_Logger::info(sprintf('Produkt %d nie ma zdjęć.', $product_id));
            return;
        }

        // Dodajemy logowanie, aby łatwiej diagnozować problem
        MHI_Logger::info(sprintf('Rozpoczęto dodawanie obrazków dla produktu %d (dostawca: %s)', $product_id, $this->supplier_name));

        // Konwertuj SimpleXMLElement do tablicy
        $images = array();
        foreach ($product_data->images->image as $image) {
            $image_url = '';
            $alt_text = '';

            // Sprawdź różne formaty XML - mogą być <image>URL</image> lub <image src="URL"/>
            if (isset($image['src'])) {
                // Format Malfini: <image src="URL"/>
                $image_url = (string) $image['src'];
                MHI_Logger::info(sprintf('Znaleziono obrazek z atrybutem src: %s', $image_url));

                // Sprawdź czy jest tekst alternatywny
                if (isset($image['alt'])) {
                    $alt_text = (string) $image['alt'];
                }
            } else {
                // Standardowy format: <image>URL</image>
                $image_url = (string) $image;
                MHI_Logger::info(sprintf('Znaleziono obrazek w zawartości tagu: %s', $image_url));

                // Sprawdź czy jest tekst alternatywny
                if (isset($image->alt)) {
                    $alt_text = (string) $image->alt;
                }
            }

            if (!empty($image_url)) {
                $images[] = array(
                    'url' => $image_url,
                    'alt' => $alt_text
                );
                MHI_Logger::info(sprintf('Dodano obrazek do przetworzenia: %s', $image_url));
            } else {
                MHI_Logger::warning('Znaleziono pusty URL obrazka. Pomijam.');
            }
        }

        if (empty($images)) {
            MHI_Logger::warning(sprintf('Produkt %d nie ma poprawnych linków do zdjęć.', $product_id));
            return;
        }

        MHI_Logger::info(sprintf('Znaleziono %d zdjęć dla produktu %d.', count($images), $product_id));

        // Określ indeks głównego zdjęcia w zależności od dostawcy
        $main_image_index = 0; // Domyślnie pierwsze zdjęcie

        // Dla Axpol ostatnie zdjęcie jest główne
        if ($this->supplier_name === 'axpol') {
            $main_image_index = count($images) - 1;
            MHI_Logger::info(sprintf('Dostawca Axpol - używam ostatniego zdjęcia (%d) jako głównego.', $main_image_index + 1));
        }
        // Dodatkowa konfiguracja dla innych dostawców
        else if ($this->supplier_name === 'malfini' && count($images) > 1) {
            $main_image_index = 1; // Drugi obraz jest główny dla Malfini
            MHI_Logger::info(sprintf('Dostawca Malfini - używam drugiego zdjęcia (%d) jako głównego.', $main_image_index + 1));
        }

        // Pobierz i zapisz wszystkie zdjęcia
        $image_ids = array();
        $main_image_id = 0;

        foreach ($images as $index => $image) {
            $start_time = microtime(true);
            MHI_Logger::info(sprintf('Pobieranie zdjęcia %d/%d: %s', $index + 1, count($images), $image['url']));

            // Pobierz i optymalizuj obrazek
            $attachment_id = $this->create_image_from_url($image['url'], $image['alt']);

            if ($attachment_id) {
                $image_ids[] = $attachment_id;
                $elapsed = round(microtime(true) - $start_time, 2);
                MHI_Logger::info(sprintf(
                    'Zdjęcie %d/%d dodane pomyślnie jako załącznik ID: %d (%.2fs)',
                    $index + 1,
                    count($images),
                    $attachment_id,
                    $elapsed
                ));

                // Zapisz ID głównego zdjęcia
                if ($index === $main_image_index) {
                    $main_image_id = $attachment_id;
                    MHI_Logger::info(sprintf(
                        'Ustawiam zdjęcie %d (ID: %d) jako główne dla produktu %d.',
                        $index + 1,
                        $attachment_id,
                        $product_id
                    ));
                }
            } else {
                MHI_Logger::error(sprintf(
                    'Nie udało się pobrać zdjęcia %d/%d: %s',
                    $index + 1,
                    count($images),
                    $image['url']
                ));
            }
        }

        // Ustaw główne zdjęcie produktu
        if ($main_image_id > 0) {
            $result = set_post_thumbnail($product_id, $main_image_id);
            if ($result) {
                MHI_Logger::info(sprintf('Ustawiono główne zdjęcie (ID: %d) dla produktu %d.', $main_image_id, $product_id));
            } else {
                MHI_Logger::error(sprintf('Nie udało się ustawić głównego zdjęcia (ID: %d) dla produktu %d.', $main_image_id, $product_id));
            }
        } else {
            MHI_Logger::warning(sprintf('Brak głównego zdjęcia dla produktu %d.', $product_id));
        }

        // Przygotuj listę zdjęć do galerii (wszystkie poza głównym)
        $gallery_ids = array_filter($image_ids, function ($id) use ($main_image_id) {
            return $id != $main_image_id;
        });

        // Dodaj pozostałe obrazki jako galeria produktu
        if (!empty($gallery_ids)) {
            $gallery_string = implode(',', $gallery_ids);
            $result = update_post_meta($product_id, '_product_image_gallery', $gallery_string);
            if ($result) {
                MHI_Logger::info(sprintf('Ustawiono galerię z %d zdjęciami dla produktu %d.', count($gallery_ids), $product_id));
            } else {
                MHI_Logger::error(sprintf('Nie udało się ustawić galerii dla produktu %d.', $product_id));
            }
        } else {
            MHI_Logger::info(sprintf('Brak dodatkowych zdjęć do galerii dla produktu %d.', $product_id));
        }
    }

    /**
     * Tworzy obrazek z URL i dodaje go do biblioteki mediów.
     * 
     * @param string $image_url URL obrazka.
     * @param string $alt_text Tekst alternatywny.
     * @return int|false ID załącznika lub false w przypadku błędu.
     */
    private function create_image_from_url($image_url, $alt_text = '')
    {
        // Sprawdź, czy obrazek o takim URL już istnieje
        $attachment_id = $this->get_attachment_id_by_url($image_url);
        if ($attachment_id) {
            MHI_Logger::info(sprintf('Znaleziono istniejący załącznik ID: %d dla URL: %s', $attachment_id, $image_url));
            return $attachment_id;
        }

        // Sprawdź po samej nazwie pliku jako ostateczność
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        $attachment_by_filename = $this->get_attachment_id_by_filename($filename);
        if ($attachment_by_filename) {
            MHI_Logger::info(sprintf('Znaleziono istniejący załącznik ID: %d dla nazwy pliku: %s', $attachment_by_filename, $filename));
            return $attachment_by_filename;
        }

        // Przygotuj folder zapisu
        $upload_dir = wp_upload_dir();
        $supplier_folder = trailingslashit($upload_dir['basedir']) . 'hurtownie/' . $this->supplier_name;

        // Utwórz folder jeśli nie istnieje
        if (!file_exists($supplier_folder)) {
            wp_mkdir_p($supplier_folder);
        }

        // Przygotuj nazwę pliku (unikalna)
        $filename = wp_unique_filename($supplier_folder, sanitize_file_name(basename($image_url)));
        $filepath = $supplier_folder . '/' . $filename;

        // Pobierz obrazek
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            MHI_Logger::error(sprintf(
                'Błąd podczas pobierania obrazka: %s - %s',
                $image_url,
                $response->get_error_message()
            ));
            return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            MHI_Logger::error(sprintf(
                'Niepoprawny kod odpowiedzi HTTP: %d dla URL: %s',
                wp_remote_retrieve_response_code($response),
                $image_url
            ));
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            MHI_Logger::error(sprintf('Pobrano pusty obrazek z: %s', $image_url));
            return false;
        }

        // Zapisz oryginalny plik
        $saved = file_put_contents($filepath, $image_data);
        if (!$saved) {
            MHI_Logger::error(sprintf('Nie udało się zapisać obrazka: %s', $filepath));
            return false;
        }

        // Optymalizuj obraz - konwersja do WebP jeśli jest dostępna
        $optimized_path = $this->optimize_image($filepath);
        $final_path = $optimized_path ? $optimized_path : $filepath;

        // Sprawdź czy plik faktycznie istnieje po optymalizacji
        if (!file_exists($final_path)) {
            MHI_Logger::error(sprintf('Plik po optymalizacji nie istnieje: %s', $final_path));
            return false;
        }

        // Przygotuj względną ścieżkę do załącznika - ważne dla WordPress
        $upload_path = $upload_dir['basedir'];
        $relative_path = substr($final_path, strlen($upload_path) + 1); // +1 dla /
        MHI_Logger::info(sprintf('Ścieżka względna pliku: %s', $relative_path));

        // Przygotuj dane załącznika
        $filetype = wp_check_filetype(basename($final_path), null);
        $attachment = array(
            'guid' => $upload_dir['baseurl'] . '/' . $relative_path,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($final_path)),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        MHI_Logger::info(sprintf('Dodawanie załącznika do biblioteki mediów: %s', $relative_path));

        // Wstaw załącznik do bazy danych - użyj względnej ścieżki
        $attachment_id = wp_insert_attachment($attachment, $relative_path);
        if (!$attachment_id) {
            MHI_Logger::error(sprintf('Nie udało się wstawić załącznika: %s', basename($final_path)));
            return false;
        }

        // Załaduj wymagane pliki do generowania metadanych
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        // Wygeneruj metadane
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $final_path);
        if (empty($attachment_data)) {
            MHI_Logger::warning(sprintf('Nie udało się wygenerować metadanych dla załącznika: %s', basename($final_path)));
        }

        // Aktualizuj metadane załącznika
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Ustaw tekst alternatywny
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }

        // Zapisz oryginalny URL obrazka jako metadane
        update_post_meta($attachment_id, '_mhi_original_url', $image_url);
        update_post_meta($attachment_id, '_mhi_supplier', $this->supplier_name);
        update_post_meta($attachment_id, '_mhi_imported', 'yes');

        // Upewnij się, że załącznik jest widoczny w bibliotece mediów
        if (!wp_get_attachment_url($attachment_id)) {
            // Spróbuj naprawić wpis załącznika
            $updated_attachment = array(
                'ID' => $attachment_id,
                'guid' => $upload_dir['baseurl'] . '/' . $relative_path,
                'post_mime_type' => $filetype['type']
            );
            wp_update_post($updated_attachment);

            // Dodaj wpis o pliku załącznika
            update_post_meta($attachment_id, '_wp_attached_file', $relative_path);

            MHI_Logger::info(sprintf('Naprawiono załącznik ID: %d, URL: %s', $attachment_id, $upload_dir['baseurl'] . '/' . $relative_path));
        }

        MHI_Logger::info(sprintf('Załącznik dodany do biblioteki mediów ID: %d, URL: %s', $attachment_id, wp_get_attachment_url($attachment_id)));

        return $attachment_id;
    }

    /**
     * Optymalizuje obraz i konwertuje do WebP jeśli to możliwe.
     * 
     * @param string $source_path Ścieżka do oryginalnego obrazu.
     * @return string|false Ścieżka do zoptymalizowanego obrazu lub false w przypadku błędu.
     */
    private function optimize_image($source_path)
    {
        if (!file_exists($source_path)) {
            MHI_Logger::error(sprintf('Nie można zoptymalizować - plik nie istnieje: %s', $source_path));
            return false;
        }

        $info = getimagesize($source_path);
        if (!$info) {
            MHI_Logger::error(sprintf('Nie można odczytać informacji o obrazie: %s', $source_path));
            return false;
        }

        // Sprawdź czy PHP obsługuje WebP
        if (!function_exists('imagewebp')) {
            MHI_Logger::warning('Funkcja imagewebp nie jest dostępna - pomijam optymalizację WebP.');
            return false;
        }

        // Przygotuj nową ścieżkę dla WebP
        $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $source_path);

        try {
            $mime_type = $info['mime'];
            $image = null;

            // Wczytaj obraz na podstawie typu MIME
            switch ($mime_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($source_path);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($source_path);
                    // Zachowaj przezroczystość
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($source_path);
                    break;
                default:
                    MHI_Logger::warning(sprintf('Nieobsługiwany typ obrazu: %s - %s', $mime_type, $source_path));
                    return false;
            }

            if (!$image) {
                MHI_Logger::error(sprintf('Nie udało się utworzyć obrazu z pliku: %s', $source_path));
                return false;
            }

            // Zapisz jako WebP
            $success = imagewebp($image, $webp_path, 80);
            imagedestroy($image);

            if ($success) {
                MHI_Logger::info(sprintf('Obraz zoptymalizowany i przekonwertowany do WebP: %s', basename($webp_path)));
                return $webp_path;
            } else {
                MHI_Logger::error(sprintf('Nie udało się zapisać obrazu WebP: %s', basename($webp_path)));
                return false;
            }
        } catch (Exception $e) {
            MHI_Logger::error(sprintf(
                'Wyjątek podczas optymalizacji obrazu: %s - %s',
                basename($source_path),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Sprawdza, czy dane produktu uległy zmianie.
     * 
     * @param WC_Product $product Produkt WooCommerce.
     * @param SimpleXMLElement $product_data Dane produktu z XML.
     * @return bool True jeśli produkt nie uległ zmianie.
     */
    private function product_unchanged($product, $product_data)
    {
        // Porównaj podstawowe dane
        if ($product->get_name() !== (string) $product_data->name) {
            return false;
        }

        if ($product->get_description() !== (string) $product_data->description) {
            return false;
        }

        if ($product->get_short_description() !== (string) $product_data->short_description) {
            return false;
        }

        // Porównaj cenę
        if ($product->get_regular_price() !== (string) $product_data->regular_price) {
            return false;
        }

        // Porównaj cenę wyprzedażową
        if ($product->get_sale_price() !== (string) $product_data->sale_price) {
            return false;
        }

        // Porównaj stan magazynowy
        if ($product->get_stock_quantity() !== (int) $product_data->stock_quantity) {
            return false;
        }

        // Pozostałe porównania można dodać w miarę potrzeb

        // Produkt nie uległ zmianie
        return true;
    }

    /**
     * Kompletuje proces importu i aktualizuje status.
     */
    private function complete_import()
    {
        // Oblicz czas trwania importu
        $end_time = time();
        $duration = $end_time - $this->status['start_time'];

        // Aktualizuj status
        $this->status['status'] = 'completed';
        $this->status['end_time'] = $end_time;
        $this->status['elapsed_time'] = $duration;
        $this->status['message'] = sprintf(
            __('Import zakończony. Przetworzono %d produktów w %s.', 'multi-hurtownie-integration'),
            $this->status['processed'],
            $this->format_time($duration)
        );
        $this->status['percent'] = 100;

        // Zapisz status
        $this->save_status();

        MHI_Logger::info(sprintf(
            'Import dla %s zakończony. Utworzono: %d, Zaktualizowano: %d, Pominięto: %d, Błędy: %d. Czas: %s.',
            $this->supplier_name,
            $this->status['created'],
            $this->status['updated'],
            $this->status['skipped'],
            $this->status['failed'],
            $this->format_time($duration)
        ));
    }

    /**
     * Oznacza import jako zatrzymany.
     */
    private function mark_as_stopped()
    {
        $end_time = time();
        $duration = $end_time - $this->status['start_time'];

        // Aktualizuj status
        $this->status['status'] = 'stopped';
        $this->status['end_time'] = $end_time;
        $this->status['elapsed_time'] = $duration;
        $this->status['message'] = sprintf(
            __('Import zatrzymany. Dodano: %d, Zaktualizowano: %d, Pominięto: %d, Błędy: %d. Przetworzono %d z %d produktów.', 'multi-hurtownie-integration'),
            $this->status['created'],
            $this->status['updated'],
            $this->status['skipped'],
            $this->status['failed'],
            $this->status['processed'],
            $this->status['total']
        );

        // Zapisz status
        $this->save_status();

        MHI_Logger::warning(sprintf(
            'Import z hurtowni %s został zatrzymany. Dodano: %d, Zaktualizowano: %d, Pominięto: %d, Błędy: %d. Przetworzono %d z %d produktów.',
            $this->supplier_name,
            $this->status['created'],
            $this->status['updated'],
            $this->status['skipped'],
            $this->status['failed'],
            $this->status['processed'],
            $this->status['total']
        ));

        // Usuń flagę zatrzymania
        delete_option('mhi_stop_import_' . $this->supplier_name);
    }

    /**
     * Sprawdza, czy import powinien zostać zatrzymany.
     * 
     * @return bool True jeśli import powinien zostać zatrzymany.
     */
    public function should_stop()
    {
        return (bool) get_option('mhi_stop_import_' . $this->supplier_name, false);
    }

    /**
     * Zatrzymuje import.
     * 
     * @return bool True jeśli zatrzymanie powiodło się.
     */
    public function stop_import()
    {
        update_option('mhi_stop_import_' . $this->supplier_name, true);
        MHI_Logger::warning('Żądanie zatrzymania importu z hurtowni ' . $this->supplier_name);
        return true;
    }

    /**
     * Ładuje status importu.
     */
    private function load_status()
    {
        $saved_status = get_option('mhi_import_status_' . $this->supplier_name, []);

        // Sprawdź czy zapis statusu nie jest starszy niż 30 minut
        if (!empty($saved_status) && isset($saved_status['start_time'])) {
            $elapsed_time = time() - $saved_status['start_time'];

            // Jeśli import jest w trakcie i minęło zbyt dużo czasu, resetuj go
            if (($saved_status['status'] === 'running') && ($elapsed_time > 1800)) {
                error_log('MHI DEBUG: Znaleziono zawieszony import - resetuję status');
                $saved_status['status'] = 'error';
                $saved_status['message'] = 'Import został przerwany (timeout)';
                $saved_status['end_time'] = time();
                update_option('mhi_import_status_' . $this->supplier_name, $saved_status);
            }
        }

        // Teraz załaduj status (oryginalny lub zaktualizowany)
        if (!empty($saved_status)) {
            $this->status = array_merge($this->status, $saved_status);
        }
    }

    /**
     * Zapisuje status importu.
     */
    private function save_status()
    {
        update_option('mhi_import_status_' . $this->supplier_name, $this->status);
    }

    /**
     * Aktualizuje wybrane pola statusu.
     * 
     * @param array $data Dane do aktualizacji.
     */
    public function update_status($data)
    {
        // Jeśli aktualizujemy poszczególne liczniki statystyk, aktualizuj też procent
        if (isset($data['processed']) || isset($data['total'])) {
            $processed = isset($data['processed']) ? $data['processed'] : $this->status['processed'];
            $total = isset($data['total']) ? $data['total'] : $this->status['total'];

            if ($total > 0) {
                $data['percent'] = round(($processed / $total) * 100);
            }
        }

        // Aktualizuj czas trwania, jeśli jest to import w trakcie
        if (
            isset($this->status['start_time']) && $this->status['start_time'] > 0 &&
            (!isset($data['status']) || $data['status'] === 'running')
        ) {

            $data['elapsed_time'] = time() - $this->status['start_time'];

            // Oblicz szacowany czas pozostały
            if (isset($this->status['processed']) && $this->status['processed'] > 0 && isset($this->status['total'])) {
                $time_per_product = $data['elapsed_time'] / $this->status['processed'];
                $remaining_products = $this->status['total'] - $this->status['processed'];
                $data['estimated_time'] = round($time_per_product * $remaining_products);
            }
        }

        // Zaloguj każdą aktualizację statusu
        error_log('MHI DEBUG: Aktualizacja statusu: ' . json_encode($data));

        // Aktualizuj dane statusu
        $this->status = array_merge($this->status, $data);

        // Zapisz zaktualizowany status
        $this->save_status();
    }

    /**
     * Formatuje czas w sekundach do postaci czytelnej dla człowieka.
     * 
     * @param int $seconds Czas w sekundach.
     * @return string Sformatowany czas.
     */
    private function format_time($seconds)
    {
        if ($seconds < 60) {
            return sprintf(_n('%d sekunda', '%d sekund', $seconds, 'multi-hurtownie-integration'), $seconds);
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf(_n('%d minuta', '%d minut', $minutes, 'multi-hurtownie-integration'), $minutes) .
                ($secs > 0 ? ', ' . sprintf(_n('%d sekunda', '%d sekund', $secs, 'multi-hurtownie-integration'), $secs) : '');
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf(_n('%d godzina', '%d godzin', $hours, 'multi-hurtownie-integration'), $hours) .
                ($minutes > 0 ? ', ' . sprintf(_n('%d minuta', '%d minut', $minutes, 'multi-hurtownie-integration'), $minutes) : '');
        }
    }

    /**
     * Pobiera aktualny status importu
     * 
     * @param string|null $supplier_name Opcjonalna nazwa dostawcy (dla wywołania statycznego)
     * @return array Tablica z danymi statusu importu
     */
    public function get_status($supplier_name = null)
    {
        // Jeśli podano nazwę dostawcy, zachowaj się jak metoda statyczna
        if ($supplier_name !== null) {
            $supplier = sanitize_text_field($supplier_name);
            $status = get_option('mhi_import_status_' . $supplier, []);

            if (empty($status)) {
                $status = array(
                    'status' => 'idle',
                    'total' => 0,
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'current_product' => '',
                    'message' => __('Import nie został jeszcze rozpoczęty.', 'multi-hurtownie-integration'),
                    'percent' => 0,
                    'start_time' => 0,
                    'end_time' => 0,
                    'elapsed_time' => 0,
                    'estimated_time' => 0,
                );
            }

            return $status;
        }

        // W przeciwnym razie, zachowaj się jak metoda instancji
        return $this->status;
    }

    /**
     * Pobiera ID załącznika na podstawie URL.
     * 
     * @param string $url URL obrazka.
     * @return int|false ID załącznika lub false jeśli nie znaleziono.
     */
    private function get_attachment_id_by_url($url)
    {
        global $wpdb;

        // Podstawowa walidacja
        if (empty($url)) {
            return false;
        }

        // Najpierw sprawdź bezpośrednio po oryginalnym URL
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhi_original_url' AND meta_value = %s
            LIMIT 1",
            $url
        ));

        if ($attachment_id) {
            MHI_Logger::info(sprintf('Znaleziono załącznik po oryginalnym URL w metadanych: %d', $attachment_id));
            return (int) $attachment_id;
        }

        // Pobierz bazowy URL
        $upload_dir = wp_upload_dir();
        $upload_base_url = $upload_dir['baseurl'];

        // Jeśli URL jest względny, dodaj domenę
        if (strpos($url, 'http') !== 0) {
            if (strpos($url, '/') === 0) {
                $url = home_url($url);
            } else {
                $url = $upload_base_url . '/' . $url;
            }
        }

        // Sprawdź po guid (najprostsze podejście)
        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND guid = %s",
            $url
        ));

        if (!empty($attachment[0])) {
            MHI_Logger::info(sprintf('Znaleziono załącznik po GUID: %d', $attachment[0]));
            return (int) $attachment[0];
        }

        // Najpierw sprawdź czy obraz nie został już zaimportowany do naszego folderu
        $supplier_base = $upload_base_url . '/hurtownie/' . $this->supplier_name . '/';
        $filename = basename($url);

        // Wyszukaj po nazwie pliku i katalogu dostawcy
        $attachments = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'attachment' 
            AND pm.meta_key = '_wp_attached_file' 
            AND pm.meta_value LIKE %s",
            '%hurtownie/' . $this->supplier_name . '/%' . $filename . '%'
        ));

        if (!empty($attachments)) {
            MHI_Logger::info(sprintf('Znaleziono załącznik po ścieżce w folderze dostawcy: %d', $attachments[0]));
            return (int) $attachments[0];
        }

        // Próbujemy znaleźć tylko po nazwie pliku
        return $this->get_attachment_id_by_filename($filename);
    }

    /**
     * Pobiera ID załącznika na podstawie nazwy pliku.
     * 
     * @param string $filename Nazwa pliku.
     * @return int|false ID załącznika lub false jeśli nie znaleziono.
     */
    private function get_attachment_id_by_filename($filename)
    {
        global $wpdb;

        // Podstawowa walidacja
        if (empty($filename)) {
            return false;
        }

        // Szukaj po nazwie pliku w metadanych
        $attachments = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wp_attached_file' 
             AND meta_value LIKE %s",
            '%' . $wpdb->esc_like(basename($filename)) . '%'
        ));

        if (!empty($attachments)) {
            return (int) $attachments[0];
        }

        // Szukaj po nazwie pliku w tytule załącznika
        $attachments = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND (post_title LIKE %s OR guid LIKE %s)",
            '%' . $wpdb->esc_like(basename($filename, '.' . pathinfo($filename, PATHINFO_EXTENSION))) . '%',
            '%' . $wpdb->esc_like(basename($filename)) . '%'
        ));

        if (!empty($attachments)) {
            return (int) $attachments[0];
        }

        return false;
    }
}
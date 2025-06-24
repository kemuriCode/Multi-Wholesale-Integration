<?php
/**
 * Wyświetlanie informacji o dostępności produktów AXPOL na stronie produktu.
 *
 * @package MHI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa do obsługi wyświetlania dostępności produktów AXPOL
 */
class MHI_Axpol_Product_Availability_Display
{
    /**
     * Konstruktor - rejestruje hooki
     */
    public function __construct()
    {
        // Hook do wyświetlania informacji o dostępności na stronie produktu
        add_action('woocommerce_single_product_summary', array($this, 'display_axpol_availability_info'), 25);

        // CSS dla stylowania
        add_action('wp_head', array($this, 'add_availability_css'));
    }

    /**
     * Sprawdza czy produkt pochodzi z AXPOL
     *
     * @param int $product_id ID produktu
     * @return bool
     */
    private function is_axpol_product($product_id)
    {
        // Sprawdź czy produkt ma meta fields AXPOL
        $axpol_typ = get_post_meta($product_id, '_axpol_typ_dostepnosci', true);
        return !empty($axpol_typ);
    }

    /**
     * Wyświetla informacje o dostępności dla produktów AXPOL
     */
    public function display_axpol_availability_info()
    {
        global $product;

        if (!$product || !$this->is_axpol_product($product->get_id())) {
            return;
        }

        $product_id = $product->get_id();

        // Pobierz dane o dostępności
        $dostepne_teraz = (int) get_post_meta($product_id, '_axpol_dostepne_teraz', true);
        $w_rezerwacji = (int) get_post_meta($product_id, '_axpol_w_rezerwacji', true);
        $na_zamowienie = (int) get_post_meta($product_id, '_axpol_na_zamowienie', true);
        $kolejna_dostawa = (int) get_post_meta($product_id, '_axpol_kolejna_dostawa', true);
        $data_dostawy = get_post_meta($product_id, '_axpol_data_dostawy', true);
        $typ_dostepnosci = get_post_meta($product_id, '_axpol_typ_dostepnosci', true);
        $komunikat = get_post_meta($product_id, '_axpol_komunikat_dostepnosci', true);

        // Wyświetl informacje
        echo '<div class="axpol-availability-info">';
        echo '<h4 class="axpol-availability-title">Dostępność produktu</h4>';

        // Główny komunikat
        if (!empty($komunikat)) {
            $status_class = $this->get_status_class($typ_dostepnosci);
            echo '<div class="axpol-main-status ' . esc_attr($status_class) . '">';
            echo '<span class="axpol-status-icon"></span>';
            echo '<span class="axpol-status-text">' . esc_html($komunikat) . '</span>';
            echo '</div>';
        }

        // Szczegółowe informacje
        if ($dostepne_teraz > 0 || $w_rezerwacji > 0 || $na_zamowienie > 0 || $kolejna_dostawa > 0) {
            echo '<div class="axpol-details">';
            echo '<table class="axpol-availability-table">';

            if ($dostepne_teraz > 0) {
                echo '<tr class="axpol-available-now">';
                echo '<td><strong>Na magazynie:</strong></td>';
                echo '<td><span class="axpol-quantity">' . $dostepne_teraz . ' szt.</span> <small>(dostępne natychmiast)</small></td>';
                echo '</tr>';
            }

            if ($w_rezerwacji > 0) {
                echo '<tr class="axpol-reserved">';
                echo '<td><strong>W rezerwacji:</strong></td>';
                echo '<td><span class="axpol-quantity">' . $w_rezerwacji . ' szt.</span> <small>(mogą być warunki)</small></td>';
                echo '</tr>';
            }

            if ($na_zamowienie > 0) {
                echo '<tr class="axpol-on-order">';
                echo '<td><strong>Na zamówienie:</strong></td>';
                echo '<td><span class="axpol-quantity">' . $na_zamowienie . ' szt.</span> <small>(realizacja 1-2 dni)</small></td>';
                echo '</tr>';
            }

            if ($kolejna_dostawa > 0) {
                echo '<tr class="axpol-next-delivery">';
                echo '<td><strong>Kolejna dostawa:</strong></td>';
                echo '<td><span class="axpol-quantity">' . $kolejna_dostawa . ' szt.</span>';
                if (!empty($data_dostawy) && $data_dostawy !== '0') {
                    echo ' <small>(przewidywana: ' . esc_html($data_dostawy) . ')</small>';
                } else {
                    echo ' <small>(data do ustalenia)</small>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Zwraca klasę CSS dla statusu dostępności
     *
     * @param string $typ_dostepnosci Typ dostępności
     * @return string Klasa CSS
     */
    private function get_status_class($typ_dostepnosci)
    {
        switch ($typ_dostepnosci) {
            case 'dostepny_natychmiast':
                return 'status-available';
            case 'dostepny_z_rezerwacji':
                return 'status-reserved';
            case 'dostepny_1_2_dni':
                return 'status-on-order';
            case 'dostepny_pozniej':
                return 'status-later';
            case 'niedostepny':
            case 'brak_danych':
            default:
                return 'status-unavailable';
        }
    }

    /**
     * Dodaje CSS do stylowania informacji o dostępności
     */
    public function add_availability_css()
    {
        // Tylko na stronach produktów
        if (!is_product()) {
            return;
        }

        echo '<style>
        .axpol-availability-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .axpol-availability-title {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 1.1em;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }
        
        .axpol-main-status {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .axpol-status-icon {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-available .axpol-status-icon {
            background: #28a745;
        }
        
        .status-reserved {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-reserved .axpol-status-icon {
            background: #ffc107;
        }
        
        .status-on-order {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b3d7ff;
        }
        
        .status-on-order .axpol-status-icon {
            background: #007bff;
        }
        
        .status-later {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .status-later .axpol-status-icon {
            background: #6c757d;
        }
        
        .status-unavailable {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-unavailable .axpol-status-icon {
            background: #dc3545;
        }
        
        .axpol-availability-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        
        .axpol-availability-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .axpol-availability-table td:first-child {
            width: 40%;
            color: #6c757d;
        }
        
        .axpol-quantity {
            font-weight: 600;
            color: #495057;
        }
        
        .axpol-availability-table small {
            color: #6c757d;
            font-style: italic;
        }
        
        .axpol-available-now td:first-child {
            color: #28a745;
        }
        
        .axpol-reserved td:first-child {
            color: #ffc107;
        }
        
        .axpol-on-order td:first-child {
            color: #007bff;
        }
        
        .axpol-next-delivery td:first-child {
            color: #6c757d;
        }
        </style>';
    }
}

// Inicjalizuj tylko jeśli WooCommerce jest aktywne
if (class_exists('WooCommerce')) {
    new MHI_Axpol_Product_Availability_Display();
}
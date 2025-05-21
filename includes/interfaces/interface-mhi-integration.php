<?php
/**
 * Interfejs dla modułów integracji z hurtowniami
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined("ABSPATH")) {
    exit;
}

/**
 * Interfejs MHI_Integration_Interface
 * 
 * Definiuje wymagane metody dla klas integracji z hurtowniami
 */
interface MHI_Integration_Interface
{
    /**
     * Nawiązuje połączenie z zewnętrznym źródłem danych
     *
     * @return bool Informacja czy połączenie zostało nawiązane
     */
    public function connect();

    /**
     * Pobiera pliki z zewnętrznego źródła
     *
     * @return mixed Wynik operacji pobierania plików
     */
    public function fetch_files();

    /**
     * Sprawdza poprawność danych logowania
     *
     * @return bool Informacja czy dane logowania są poprawne
     */
    public function validate_credentials();

    /**
     * Przetwarza pobrane pliki
     *
     * @param array $files Lista plików do przetworzenia
     * @return mixed Wynik operacji przetwarzania plików
     */
    public function process_files($files);

    /**
     * Zwraca nazwę integracji.
     *
     * @return string Nazwa integracji.
     */
    public function get_name();

    /**
     * Importuje produkty z pliku XML do WooCommerce (dostępne tylko dla hurtowni 1).
     *
     * @return string Informacja o wyniku importu.
     * @throws Exception W przypadku błędu podczas importu.
     */
    public function import_products_to_woocommerce();

    /**
     * Anuluje pobieranie plików.
     *
     * @return void
     */
    public function cancel_download();

    /**
     * Pobiera pliki zdjęć z hurtowni.
     *
     * @param int $batch_number Numer partii do pobrania
     * @param string $img_dir Katalog ze zdjęciami na serwerze
     * @return array Tablica z informacjami o pobranych plikach.
     */
    public function fetch_images($batch_number, $img_dir);
}
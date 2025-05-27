# Multi-Wholesale-Integration

Plugin integrujący WordPress z wieloma hurtowniami reklamowymi za pomocą różnych protokołów (FTP, SFTP, API).

## Funkcje

- Automatyczne pobieranie i przetwarzanie danych z wielu hurtowni
- Generowanie plików XML zgodnych z WooCommerce
- Import produktów, kategorii, atrybutów i zdjęć
- Optymalizacja obrazów (konwersja do WebP)
- Harmonogramowanie zadań importu
- Zaawansowane narzędzia czyszczenia mediów

## Obsługiwane hurtownie

- Malfini
- Axpol
- PAR
- Inspirion
- Macma

## Instalacja

1. Pobierz plugin
2. Prześlij go do katalogu `/wp-content/plugins/`
3. Aktywuj plugin w panelu administratora WordPress
4. Skonfiguruj połączenia z hurtowniami w zakładce Ustawienia > Multi-Wholesale

## Wymagania

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

## Autor

**kemuriCode**

- GitHub: [https://github.com/kemuriCode](https://github.com/kemuriCode)

## Licencja

Ten projekt jest objęty licencją GPL-2.0+

# Multi Wholesale Integration - Instrukcja konfiguracji

## Problemy z pobieraniem plików z PAR i Malfini

### Problem z PAR
**Błąd:** `cURL error 28: Operation timed out after 30002 milliseconds with 0 bytes received`

**Rozwiązanie:**
1. Przejdź do **Ustawienia → Multi-Hurtownie → PAR**
2. Upewnij się, że pola są wypełnione:
   - **Login API**: Twój login do API PAR
   - **Hasło API**: Twoje hasło do API PAR
3. Sprawdź czy masz dostęp do białej listy IP u PAR
4. Skontaktuj się z PAR aby dodać Twój adres IP do białej listy

### Problem z Malfini
**Status:** ✅ **CAŁKOWICIE ROZWIĄZANY** - Malfini używa teraz REST API v4

**Konfiguracja:**
1. Przejdź do **Ustawienia → Multi-Hurtownie → Malfini**
2. Dane dostępowe są już skonfigurowane:
   - **API URL**: https://api.malfini.com/api/v4/
   - **Login API**: dmurawski@promo-mix.pl
   - **Hasło API**: mul4eQ
3. Włącz hurtownię zaznaczając checkbox "Włączona"
4. Kliknij "Pobierz pliki" aby pobrać dane z API

**✅ Przycisk "Pobierz pliki" działa i pobiera:**
- `products.json` - 396 produktów (~7.4 MB)
- `availabilities.json` - dostępność produktów  
- `prices.json` - ceny produktów

**Naprawione problemy:**
- ✅ Poprawiono token autoryzacyjny (`access_token` zamiast `token`)
- ✅ Dodano szczegółowe logowanie
- ✅ Ustawiono domyślne włączenie hurtowni
- ✅ Przetestowano połączenie z API

## Konfiguracja hurtowni

### PAR
1. Skontaktuj się z PAR aby otrzymać:
   - Login do API
   - Hasło do API
   - Dodanie Twojego IP do białej listy
2. Wprowadź dane w panelu administracyjnym
3. Włącz hurtownię zaznaczając checkbox "Włączona"

### Malfini
1. **Status**: ✅ Skonfigurowane - używa REST API v4
2. Dane dostępowe są już wprowadzone w systemie:
   - Login: dmurawski@promo-mix.pl
   - Hasło: mul4eQ
3. Włącz hurtownię w panelu administracyjnym
4. API automatycznie pobiera:
   - Produkty (/product)
   - Dostępność (/product/availabilities)  
   - Ceny (/product/prices)

## Testowanie połączenia

Po skonfigurowaniu danych:
1. Przejdź do odpowiedniej zakładki hurtowni
2. Kliknij "Pobierz pliki"
3. Sprawdź logi w katalogu `wp-content/uploads/wholesale/logs/`

## Rozwiązywanie problemów

### Sprawdź logi
Logi znajdują się w: `wp-content/uploads/wholesale/logs/mhi_log_YYYY-MM-DD.log`

### Typowe problemy:
- **Timeout**: Zwiększ timeout w konfiguracji lub skontaktuj się z hurtownią
- **Błędy SSL**: Sprawdź czy serwer obsługuje HTTPS
- **Błędy FTP**: Sprawdź dane dostępowe i tryb pasywny
- **Brak uprawnień**: Sprawdź uprawnienia do katalogów

### Kontakt z hurtowniami:
- **PAR**: Poproś o dodanie do białej listy IP
- **Malfini**: Poproś o dostęp FTP i strukturę katalogów

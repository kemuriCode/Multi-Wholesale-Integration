# 🚀 CRON IMPORT - Instrukcja Użycia

## Opis

Plik `cron-import.php` to wydajny importer produktów zaprojektowany do uruchamiania przez cron lub z linii poleceń. Zawiera **identyczne funkcjonalności** jak `import.php`, ale bez interfejsu wizualnego, co czyni go idealnym do automatyzacji.

## ✅ Funkcjonalności

- **Identyczne mapowanie pól** jak w `import.php`
- **Pełna obsługa atrybutów** (tworzenie globalnych atrybutów, taksonomii, terminów)
- **Kompletna obsługa kategorii** (hierarchiczne i pojedyncze)
- **Automatyczne mapowanie marek** (z różnych źródeł XML)
- **Zaawansowana obsługa galerii obrazów** z konwersją WebP
- **Custom fields (meta_data)** z XML
- **Wydajne logowanie** do plików
- **Statystyki i raporty**
- **Obsługa błędów** i recovery
- **Optymalizacja wydajności** (cache, przerwy, limity)

## 🔧 Sposoby Uruchamiania

### 1. Z Linii Poleceń (CLI)

```bash
# Podstawowe użycie
php /path/to/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini

# Z dodatkowymi parametrami
php /path/to/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini replace_images=1 log_level=debug max_products=50

# Przykład pełnej ścieżki
php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini
```

### 2. Przez HTTP (z kluczem dostępu)

```bash
# Podstawowe użycie
curl "https://twoja-domena.pl/wp-content/plugins/multi-wholesale-integration/cron-import.php?admin_key=mhi_cron_access&supplier=malfini"

# Z parametrami
curl "https://twoja-domena.pl/wp-content/plugins/multi-wholesale-integration/cron-import.php?admin_key=mhi_cron_access&supplier=malfini&replace_images=1&log_level=info"
```

### 3. Przez Cron (automatycznie)

```bash
# Edytuj crontab
crontab -e

# Dodaj wpis (np. codziennie o 2:00)
0 2 * * * php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini >> /var/log/malfini-import.log 2>&1

# Lub co 6 godzin
0 */6 * * * php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini log_level=warning

# Różni dostawcy w różnych godzinach
0 2 * * * php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=malfini
0 3 * * * php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=axpol
0 4 * * * php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=macma
0 5 * * * php /var/www/html/wp-content/plugins/multi-wholesale-integration/cron-import.php supplier=par
```

## 📋 Parametry

| Parametr | Opis | Wartości | Domyślnie |
|----------|------|----------|-----------|
| `supplier` | **Wymagany** - nazwa hurtowni | `malfini`, `axpol`, `macma`, `par` | - |
| `replace_images` | Zastąp istniejące obrazy galerii | `1` lub `0` | `0` |
| `test_xml` | Użyj pliku testowego | `1` lub `0` | `0` |
| `log_level` | Poziom logowania | `error`, `warning`, `info`, `debug` | `info` |
| `max_products` | Limit produktów do przetworzenia | liczba lub `0` (bez limitu) | `0` |
| `admin_key` | Klucz dostępu dla HTTP | `mhi_cron_access` | - |

## 📊 Logowanie

### Lokalizacja Logów

Logi są zapisywane w:
```
/wp-content/uploads/wholesale/logs/cron_import_{supplier}_{data}.log
```

Przykład:
```
/wp-content/uploads/wholesale/logs/cron_import_malfini_2024-01-15.log
```

### Poziomy Logowania

- **error** - tylko błędy krytyczne
- **warning** - błędy + ostrzeżenia
- **info** - błędy + ostrzeżenia + informacje (domyślnie)
- **debug** - wszystko + szczegółowe debugowanie

### Przykład Logu

```
[2024-01-15 14:30:01] [info] 🚀 ROZPOCZĘCIE IMPORTU CRON - Dostawca: malfini
[2024-01-15 14:30:01] [info] 📄 Plik XML: woocommerce_import_malfini.xml
[2024-01-15 14:30:02] [info] ✅ Znaleziono 1250 produktów do importu
[2024-01-15 14:30:03] [info] 🔄 [1/1250] Przetwarzanie: T-shirt Basic (SKU: MAL001)
[2024-01-15 14:30:03] [info] ✅ Utworzono produkt ID: 12345
[2024-01-15 14:30:45] [info] 🎉 IMPORT ZAKOŃCZONY!
[2024-01-15 14:30:45] [info] ⏱️ Czas: 42.3 sekund
[2024-01-15 14:30:45] [info] 📊 Utworzono: 45, Zaktualizowano: 1205, Błędów: 0, Obrazów: 3750
```

## 📈 Statystyki

Po każdym imporcie statystyki są zapisywane w opcjach WordPress:

```php
// Pobierz ostatnie statystyki
$stats = get_option('mhi_last_cron_import_malfini');

// Struktura danych
$stats = [
    'supplier' => 'malfini',
    'total_products' => 1250,
    'created' => 45,
    'updated' => 1205,
    'failed' => 0,
    'images' => 3750,
    'duration' => 42.3,
    'timestamp' => '2024-01-15 14:30:45',
    'xml_file' => 'woocommerce_import_malfini.xml',
    'log_file' => 'cron_import_malfini_2024-01-15.log'
];
```

## 🔄 Przykłady Użycia

### Import Podstawowy

```bash
# Import wszystkich produktów Malfini
php cron-import.php supplier=malfini
```

### Import z Zastąpieniem Obrazów

```bash
# Zastąp wszystkie obrazy galerii nowymi
php cron-import.php supplier=malfini replace_images=1
```

### Import Testowy

```bash
# Użyj pliku test_gallery.xml z ograniczoną liczbą produktów
php cron-import.php supplier=malfini test_xml=1 max_products=10 log_level=debug
```

### Import Cichy (tylko błędy)

```bash
# Loguj tylko błędy krytyczne
php cron-import.php supplier=malfini log_level=error
```

### Import przez HTTP

```bash
# Uruchom przez przeglądarkę lub curl
curl "https://twoja-domena.pl/wp-content/plugins/multi-wholesale-integration/cron-import.php?admin_key=mhi_cron_access&supplier=malfini&log_level=info"
```

## ⚡ Optymalizacja Wydajności

### Automatyczne Optymalizacje

- **Cache wyłączony** podczas importu
- **Przerwy co 10 produktów** (0.1s) żeby nie przeciążyć serwera
- **Limity pamięci** zwiększone do 2GB
- **Timeout wyłączony** dla długich importów
- **Duplikaty obrazów** automatycznie wykrywane

### Ręczne Optymalizacje

```bash
# Ogranicz liczbę produktów dla testów
php cron-import.php supplier=malfini max_products=100

# Użyj niższego poziomu logowania dla szybszego działania
php cron-import.php supplier=malfini log_level=warning

# Pomiń obrazy jeśli nie są potrzebne (modyfikacja kodu)
```

## 🛠️ Rozwiązywanie Problemów

### Sprawdź Logi

```bash
# Pokaż ostatnie logi
tail -f /wp-content/uploads/wholesale/logs/cron_import_malfini_$(date +%Y-%m-%d).log

# Szukaj błędów
grep "error" /wp-content/uploads/wholesale/logs/cron_import_malfini_*.log
```

### Typowe Problemy

1. **Brak pliku XML**
   ```
   Błąd: Plik XML nie istnieje
   Rozwiązanie: Najpierw wygeneruj XML przez panel admin lub API
   ```

2. **Brak uprawnień**
   ```
   Błąd: Brak uprawnień do importu cron
   Rozwiązanie: Użyj admin_key=mhi_cron_access dla HTTP
   ```

3. **Błędy pamięci**
   ```
   Błąd: Fatal error: Allowed memory size
   Rozwiązanie: Zwiększ memory_limit w PHP lub użyj max_products
   ```

4. **Timeout**
   ```
   Błąd: Maximum execution time exceeded
   Rozwiązanie: Zwiększ max_execution_time lub użyj CLI
   ```

### Testowanie

```bash
# Test z małą liczbą produktów
php cron-import.php supplier=malfini max_products=5 log_level=debug

# Test z plikiem testowym
php cron-import.php supplier=malfini test_xml=1 log_level=debug

# Sprawdź składnię PHP
php -l cron-import.php
```

## 🔒 Bezpieczeństwo

### Klucz Dostępu HTTP

Dla dostępu HTTP wymagany jest klucz: `admin_key=mhi_cron_access`

### Zalecenia

- **Używaj CLI** zamiast HTTP gdy to możliwe
- **Ogranicz dostęp** do pliku przez .htaccess
- **Monitoruj logi** pod kątem nieautoryzowanych prób dostępu
- **Używaj HTTPS** dla połączeń HTTP

### Przykład .htaccess

```apache
# Ogranicz dostęp do cron-import.php
<Files "cron-import.php">
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
    Allow from localhost
    # Dodaj swoje IP
    Allow from 192.168.1.100
</Files>
```

## 📞 Wsparcie

W przypadku problemów:

1. **Sprawdź logi** - zawsze pierwszy krok
2. **Przetestuj z debug** - `log_level=debug`
3. **Użyj małej próbki** - `max_products=5`
4. **Sprawdź uprawnienia** plików i folderów
5. **Zweryfikuj XML** - czy plik istnieje i jest poprawny

## 🎯 Różnice od import.php

| Funkcja | import.php | cron-import.php |
|---------|------------|-----------------|
| Interfejs wizualny | ✅ Tak | ❌ Nie |
| Logowanie do pliku | ❌ Nie | ✅ Tak |
| Uruchamianie CLI | ❌ Nie | ✅ Tak |
| Statystyki JSON | ❌ Nie | ✅ Tak |
| Poziomy logowania | ❌ Nie | ✅ Tak |
| Limity produktów | ❌ Nie | ✅ Tak |
| Mapowanie pól | ✅ Identyczne | ✅ Identyczne |
| Obsługa obrazów | ✅ Identyczne | ✅ Identyczne |
| Atrybuty/kategorie | ✅ Identyczne | ✅ Identyczne |

**Wniosek**: `cron-import.php` to wydajny odpowiednik `import.php` bez interfejsu wizualnego, idealny do automatyzacji! 
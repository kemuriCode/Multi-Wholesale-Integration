# 🔥 ANDA Simple Generator - Instrukcja Obsługi

## 📋 Opis Systemu

ANDA Simple Generator to uproszczony system importu produktów z hurtowni ANDA. Każdy produkt z unikalnym SKU jest traktowany jako osobny produkt simple, bez grupowania w warianty.

### 🎯 Główne Zalety

- **Prostota**: Każdy SKU = osobny produkt
- **Szybkość**: Brak skomplikowanej logiki wariantów
- **Przejrzystość**: Łatwe mapowanie kategorii i atrybutów
- **Testowanie**: Możliwość testów na małych partiach
- **Kontrola**: Podgląd XML przed importem

## 🚀 Jak Używać

### 1. Generowanie XML

1. **Przejdź do panelu ANDA**:
   - WordPress Admin → Multi Hurtownie Integration → ANDA
   - Znajdź sekcję "ANDA Simple Generator"

2. **Otwórz generator**:
   - Kliknij "Otwórz ANDA Simple Generator"
   - Lub bezpośrednio: `/wp-content/plugins/multi-wholesale-integration/test-anda-simple-generator.php`

3. **Wybierz opcję generowania**:
   - **🔥 Generuj pełny XML** - wszystkie produkty
   - **🧪 Test (100 produktów)** - test na małej partii
   - **🧪 Test (500 produktów)** - test na średniej partii

### 2. Import Produktów

1. **Po wygenerowaniu XML**:
   - Kliknij "📥 Import XML"
   - Lub bezpośrednio: `/wp-content/plugins/multi-wholesale-integration/anda-simple-import.php`

2. **Skonfiguruj import**:
   - **Batch Size**: Liczba produktów na raz (domyślnie 50)
   - **Force Update**: Nadpisz istniejące produkty
   - **Max Products**: Limit produktów do importu

3. **Uruchom import**:
   - Kliknij "▶️ Start Import"
   - Monitoruj postęp w czasie rzeczywistym

## 📊 Struktura XML

Każdy produkt w XML zawiera:

```xml
<item>
    <g:id>SKU_PRODUKTU</g:id>
    <g:title>Nazwa produktu</g:title>
    <g:description>Opis produktu</g:description>
    <g:price>10.50 PLN</g:price>
    <g:stock_quantity>100</g:stock_quantity>
    <g:product_type>Kategoria > Podkategoria</g:product_type>
    <g:material>Bawełna</g:material>
    <g:size>M</g:size>
    <g:color>Czerwony</g:color>
    <g:supplier>ANDA</g:supplier>
</item>
```

## 🏷️ Mapowanie Kategorii

System automatycznie mapuje kategorie ANDA:

| ID ANDA | Nazwa Kategorii |
|---------|-----------------|
| 14000 | Do żywności i napojów |
| 14010 | Kubki, filiżanki i szklanki |
| 4000 | Torby i podróże |
| 4010 | Torby zakupowe i plażowe |
| 3000 | Technologia i telefon |
| 5000 | Biuro i szkoła |
| 6000 | Sport i rekreacja |
| 7000 | Zdrowie i uroda |
| 8000 | Dom i ogród |
| 9000 | Dzieci i zabawki |
| 10000 | Święta i okazje |
| 11000 | Profesjonalne |
| 12000 | Promocyjne |

## 🔧 Atrybuty Produktowe

Każdy produkt może mieć następujące atrybuty:

- **Materiał** (`g:material`): Bawełna, Poliester, Ceramika, itp.
- **Rozmiar** (`g:size`): S, M, L, XL, 38, 39, 16GB, itp.
- **Kolor** (`g:color`): Czerwony, Niebieski, Zielony, itp.

## 📁 Pliki Systemu

### Generator XML
- **Plik**: `integrations/class-mhi-anda-simple-xml-generator.php`
- **Funkcja**: Generuje uproszczony XML z produktami ANDA

### Importer
- **Plik**: `anda-simple-import.php`
- **Funkcja**: Importuje produkty z XML do WooCommerce

### Test Generator
- **Plik**: `test-anda-simple-generator.php`
- **Funkcja**: Interfejs webowy do generowania XML

## ⚙️ Konfiguracja

### Limity Pamięci
```php
ini_set('memory_limit', '1024M');
set_time_limit(0);
ignore_user_abort(true);
```

### Katalogi
- **Źródłowe**: `/wp-content/uploads/wholesale/anda/`
- **Docelowe**: `/wp-content/uploads/wholesale/anda/`
- **XML**: `woocommerce_import_anda_simple.xml`

## 🔍 Rozwiązywanie Problemów

### Problem: Brak plików źródłowych
**Rozwiązanie**: Upewnij się, że pliki XML ANDA są pobrane:
- `products.xml`
- `prices.xml`
- `inventories.xml`
- `categories.xml`
- `labeling.xml`

### Problem: Błąd pamięci
**Rozwiązanie**: Zwiększ limit pamięci w PHP:
```php
ini_set('memory_limit', '2048M');
```

### Problem: Timeout podczas importu
**Rozwiązanie**: Użyj mniejszych batchów (10-20 produktów)

### Problem: Błędne kategorie
**Rozwiązanie**: Sprawdź mapowanie kategorii w generatorze

## 📈 Monitoring

### Statystyki Importu
- **Przetworzone**: Liczba przetworzonych produktów
- **Utworzone**: Nowe produkty
- **Zaktualizowane**: Istniejące produkty
- **Błędy**: Problemy podczas importu

### Logi
Wszystkie operacje są logowane w czasie rzeczywistym:
- ✅ Sukces
- ⚠️ Ostrzeżenia
- ❌ Błędy
- ℹ️ Informacje

## 🎯 Najlepsze Praktyki

1. **Testuj na małych partiach** przed pełnym importem
2. **Sprawdzaj XML** przed importem
3. **Monitoruj pamięć** podczas dużych importów
4. **Twórz kopie zapasowe** przed importem
5. **Używaj Force Update** ostrożnie

## 🔄 Workflow

1. **Pobierz dane ANDA** (jeśli potrzebne)
2. **Wygeneruj XML** (test → pełny)
3. **Sprawdź XML** (podgląd)
4. **Importuj produkty** (małe partie → pełny)
5. **Sprawdź wyniki** (statystyki, logi)

## 📞 Wsparcie

W przypadku problemów:
1. Sprawdź logi w czasie rzeczywistym
2. Użyj opcji testowych
3. Sprawdź uprawnienia plików
4. Zweryfikuj konfigurację PHP

---

**Wersja**: 1.0  
**Data**: 2025-01-07  
**Autor**: Multi Hurtownie Integration 
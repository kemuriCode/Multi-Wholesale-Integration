# 🤖 AI Kategorie - Inteligentna reorganizacja kategorii WooCommerce

Zaawansowany system analizy i reorganizacji kategorii produktów w WooCommerce wykorzystujący najnowsze API OpenAI.

## 📋 Spis treści

- [Funkcje](#funkcje)
- [Wymagania](#wymagania)
- [Konfiguracja](#konfiguracja)
- [Jak to działa](#jak-to-działa)
- [Instrukcja użytkowania](#instrukcja-użytkowania)
- [Bezpieczeństwo](#bezpieczeństwo)
- [Rozwiązywanie problemów](#rozwiązywanie-problemów)
- [API Reference](#api-reference)

## ✨ Funkcje

### 🔍 Analiza kategorii przez AI
- **Automatyczna analiza** wszystkich kategorii i podkategorii WooCommerce
- **Analiza produktów** - badanie przykładowych produktów w każdej kategorii
- **Identyfikacja problemów** - wykrywanie duplikatów, niejasnych nazw, złej hierarchii
- **Inteligentne rekomendacje** - propozycje poprawy struktury kategorii

### 🏗️ Reorganizacja struktury
- **Scalanie kategorii** - łączenie podobnych kategorii w jedną
- **Tworzenie hierarchii** - proponowanie logicznej struktury głównych kategorii i podkategorii
- **Usuwanie redundancji** - eliminacja niepotrzebnych kategorii
- **Optymalizacja SEO** - uwzględnienie najlepszych praktyk SEO

### 🛡️ Zarządzanie bezpieczeństwem
- **Kopie zapasowe** - automatyczne tworzenie kopii przed zmianami
- **Przywracanie** - łatwe przywracanie poprzedniej struktury
- **Podgląd zmian** - dokładny przegląd planowanych modyfikacji przed wdrożeniem
- **Wersjonowanie** - zachowywanie historii zmian

### 📊 Import/Export
- **Eksport analiz** - zapisywanie wyników analizy AI do pliku JSON
- **Eksport ustawień** - backup całej konfiguracji systemu
- **Import ustawień** - przywracanie konfiguracji z pliku
- **Dzielenie się** - możliwość współdzielenia ustawień między sklepami

## 📋 Wymagania

### Środowisko
- **WordPress**: 5.8+
- **WooCommerce**: 6.0+
- **PHP**: 8.0+ (zalecane 8.2+)
- **Composer**: Do zarządzania zależnościami
- **Pamięć**: Minimum 256MB, zalecane 512MB+

### Zależności PHP
- `ext-json` - Do przetwarzania JSON
- `ext-curl` - Do komunikacji z API OpenAI
- `ext-mbstring` - Do obsługi UTF-8

### Zewnętrzne usługi
- **OpenAI API** - Klucz API z dostępem do GPT-4o lub nowszego
- **Internet** - Stałe połączenie do komunikacji z OpenAI

## ⚙️ Konfiguracja

### 1. Instalacja dependencies

```bash
cd wp-content/plugins/multi-wholesale-integration
composer install
```

### 2. Pozyskanie klucza OpenAI API

1. Przejdź na https://platform.openai.com/api-keys
2. Zaloguj się lub załóż konto
3. Kliknij "Create new secret key"
4. Skopiuj wygenerowany klucz (zanotuj - nie będzie już widoczny!)

### 3. Konfiguracja w WordPress

1. Przejdź do **Ustawienia → Multi-Hurtownie → AI Kategorie**
2. W sekcji "Ustawienia OpenAI API":
   - **Klucz API OpenAI**: Wklej swój klucz API
   - **Model AI**: Wybierz model (zalecane: GPT-4o)
   - **Maksymalne tokeny**: 4000 (domyślnie)
   - **Temperatura**: 0.3 (dla stabilnych wyników)
3. Kliknij **"Zapisz ustawienia"**
4. Przetestuj połączenie klikając **"Testuj połączenie"**

### 4. Pierwsza kopia zapasowa

**WAŻNE**: Przed pierwszym użyciem utwórz kopię zapasową:
1. Przejdź do sekcji "Zarządzanie kopiami zapasowymi"
2. Kliknij **"💾 Utwórz kopię zapasową"**

## 🔬 Jak to działa

### Proces analizy

1. **Zbieranie danych**
   - System pobiera wszystkie kategorie WooCommerce
   - Dla każdej kategorii pobiera 3 przykładowe produkty
   - Analizuje nazwy, opisy i strukturę hierarchii

2. **Wysyłanie do AI**
   - Dane są formatowane w strukturalny prompt
   - Prompt zawiera kontekst sklepu i obecną strukturę
   - AI otrzymuje instrukcje optymalizacji

3. **Analiza przez AI**
   - GPT-4o analizuje podobieństwa między kategoriami
   - Identyfikuje problemy w strukturze
   - Proponuje logiczne grupowanie
   - Uwzględnia SEO i UX

4. **Generowanie raportu**
   - AI zwraca strukturalny JSON z rekomendacjami
   - Raport zawiera plan migracji i uzasadnienia
   - System waliduje i prezentuje wyniki

### Algorytm scalania

```
Dla każdej pary kategorii:
├── Analiza nazw (podobieństwo semantyczne)
├── Analiza produktów (wspólne cechy)
├── Analiza hierarchii (logiczna struktura)
└── Ocena korzyści (SEO, UX, zarządzanie)

Jeśli podobieństwo > próg:
├── Zaproponuj scalenie
├── Wybierz nazwę docelową
├── Zaplanuj przeniesienie produktów
└── Określ nową hierarchię
```

## 📖 Instrukcja użytkowania

### Krok 1: Przygotowanie kontekstu

1. W sekcji "Analiza kategorii przez AI" wprowadź opis swojego sklepu:
   ```
   Sklep internetowy specjalizujący się w artykułach promocyjnych 
   i reklamowych. Oferujemy produkty takie jak: odzież promocyjna, 
   gadżety reklamowe, akcesoria biurowe...
   ```

2. Bądź szczegółowy - lepszy kontekst = lepsza analiza

### Krok 2: Uruchomienie analizy

1. Kliknij **"🤖 Rozpocznij analizę AI"**
2. Proces może potrwać 30-120 sekund (zależnie od liczby kategorii)
3. Poczekaj na wyniki - nie odświeżaj strony

### Krok 3: Przegląd wyników

AI wygeneruje raport zawierający:

#### 🔍 Zidentyfikowane problemy
- Lista problemów w obecnej strukturze
- Przykład: "Kategorie 'Koszulki' i 'T-shirty' zawierają podobne produkty"

#### 💡 Rekomendacje AI
- Konkretne sugestie poprawy
- Przykład: "Scal kategorie odzieżowe w główną kategorię 'Odzież promocyjna'"

#### 🏗️ Proponowana struktura
- Wizualizacja nowej hierarchii
- Pokazuje główne kategorie i podkategorie
- Wyświetla które kategorie zostaną scalone

#### 🔄 Plan zmian
- **Kategorie do scalenia**: Szczegółowy plan łączenia
- **Nowe kategorie**: Lista kategorii do utworzenia
- **Kategorie do usunięcia**: Lista niepotrzebnych kategorii

### Krok 4: Wdrożenie zmian

1. **Przegląd planu**: Dokładnie sprawdź proponowane zmiany
2. **Ostatnia kopia**: System automatycznie utworzy kopię zapasową
3. **Wdrożenie**: Kliknij **"✅ Wdróż zmiany"**
4. **Potwierdzenie**: Potwierdź w oknie dialogowym

### Krok 5: Weryfikacja

1. Przejdź do **Produkty → Kategorie** w WooCommerce
2. Sprawdź nową strukturę kategorii
3. Zweryfikuj czy produkty zostały poprawnie przeniesione
4. W razie problemów użyj kopii zapasowej

## 🛡️ Bezpieczeństwo

### Kopie zapasowe

System automatycznie tworzy kopie zapasowe:
- **Przed każdym wdrożeniem zmian**
- **Na żądanie użytkownika**
- **Zachowuje ostatnie 10 kopii**

### Przywracanie

W przypadku problemów:
1. Przejdź do "Zarządzanie kopiami zapasowymi"
2. Znajdź odpowiednią kopię w tabeli
3. Kliknij **"Przywróć"** przy wybranej kopii
4. Potwierdź operację

### Walidacja danych

- Wszystkie dane wejściowe są sanityzowane
- API klucze są bezpiecznie przechowywane
- Komunikacja z OpenAI jest szyfrowana (HTTPS)
- Tokeny WordPress nonce chronią przed atakami CSRF

## 🔧 Rozwiązywanie problemów

### Problem: "Klucz API OpenAI nie został skonfigurowany"

**Rozwiązanie**:
1. Sprawdź czy klucz został poprawnie wprowadzony
2. Upewnij się że klucz zaczyna się od "sk-"
3. Sprawdź czy konto OpenAI ma dostępne kredyty
4. Przetestuj połączenie przyciskiem "Testuj połączenie"

### Problem: "Błąd podczas analizy kategorii"

**Możliwe przyczyny**:
- Brak internetu lub problemy z połączeniem
- Przekroczenie limitu tokenów OpenAI
- Błąd w formacie odpowiedzi AI

**Rozwiązanie**:
1. Sprawdź połączenie internetowe
2. Przetestuj API przyciskiem "Testuj połączenie"
3. Zmniejsz liczbę kategorii przez usunięcie pustych
4. Spróbuj ponownie za kilka minut

### Problem: "Import produktów nie przeniósł wszystkich pozycji"

**Rozwiązanie**:
1. Przywróć kopię zapasową
2. Sprawdź logi błędów w WordPress
3. Zwiększ limit pamięci PHP (recommend 512MB+)
4. Spróbuj ponownie z mniejszą liczbą kategorii

### Problem: "AI proponuje niewłaściwe scalenia"

**Rozwiązanie**:
1. Popraw opis kontekstu sklepu - bądź bardziej szczegółowy
2. Usuń kategorie testowe lub niepotrzebne przed analizą
3. Sprawdź przykładowe produkty w problematycznych kategoriach
4. Spróbuj z ustawieniem temperatury 0.2 (bardziej konserwatywne)

### Debugging

Włącz debugowanie w `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logi znajdziesz w `/wp-content/debug.log`

## 📚 API Reference

### Klasa MHI_AI_Category_Analyzer

#### Główne metody

```php
// Przeprowadza analizę kategorii
public function analyze_categories(string $context_description = ''): array

// Tworzy kopię zapasową
public function create_backup(): bool

// Przywraca kopię zapasową
public function restore_backup(int $backup_version): bool

// Implementuje zmiany
public function implement_changes(array $migration_plan): array

// Testuje połączenie z API
public function test_api_connection(): array
```

#### Przykład użycia

```php
try {
    $analyzer = new MHI_AI_Category_Analyzer();
    
    // Analiza kategorii
    $result = $analyzer->analyze_categories('Sklep z gadżetami reklamowymi');
    
    if ($result['success']) {
        echo "Analiza zakończona. Użyto {$result['tokens_used']} tokenów.";
        
        // Implementacja zmian
        $changes = $analyzer->implement_changes($result['data']['migration_plan']);
        
        if ($changes['success']) {
            echo "Zmiany zaimplementowane pomyślnie!";
        }
    }
} catch (Exception $e) {
    echo "Błąd: " . $e->getMessage();
}
```

### Struktura odpowiedzi API

```json
{
  "analysis": {
    "current_issues": [
      "Kategorie 'Koszulki' i 'T-shirty' zawierają podobne produkty",
      "Brak jasnej hierarchii w kategoriach odzieżowych"
    ],
    "recommendations": [
      "Scal podobne kategorie odzieżowe",
      "Utwórz główną kategorię 'Odzież promocyjna'"
    ]
  },
  "proposed_structure": {
    "main_categories": [
      {
        "name": "Odzież promocyjna",
        "description": "Wszystkie rodzaje odzieży z możliwością personalizacji",
        "subcategories": [
          {
            "name": "Koszulki i T-shirty",
            "description": "Koszulki, t-shirty, polo",
            "merge_from": ["Koszulki", "T-shirty", "Polo"]
          }
        ]
      }
    ]
  },
  "migration_plan": {
    "categories_to_merge": [
      {
        "target": "Koszulki i T-shirty",
        "sources": ["Koszulki", "T-shirty", "Polo"],
        "reason": "Produkty są bardzo podobne i należą do tej samej grupy odzieżowej"
      }
    ],
    "categories_to_delete": ["Stare kategorie"],
    "new_categories": ["Odzież promocyjna"]
  }
}
```

## 💡 Najlepsze praktyki

### Przygotowanie do analizy

1. **Wyczyść kategorie testowe** - usuń puste lub testowe kategorie
2. **Dodaj opisy kategorii** - pomaga AI lepiej zrozumieć przeznaczenie
3. **Sprawdź produkty** - upewnij się że produkty są w odpowiednich kategoriach
4. **Zachowaj kopię** - zawsze utwórz kopię zapasową przed zmianami

### Optymalizacja kosztów

1. **Używaj GPT-4o-mini** dla mniejszych sklepów (tańszy)
2. **Ograniczaj tokeny** - usuń niepotrzebne kategorie przed analizą
3. **Grupuj analizy** - nie rób wielu analiz dziennie
4. **Monitoruj koszty** w panelu OpenAI

### Bezpieczeństwo

1. **Regularne kopie** - twórz kopie zapasowe przed zmianami
2. **Testuj na staging** - przetestuj na kopii strony
3. **Stopniowe wdrażanie** - wprowadzaj zmiany po kawałku
4. **Monitoruj wyniki** - sprawdzaj wpływ na SEO i sprzedaż

## 🆘 Wsparcie

### Kontakt
- **GitHub Issues**: [Zgłoś problem](https://github.com/kemuriCode/Multi-Wholesale-Integration/issues)
- **Email**: support@promo-mix.pl
- **Dokumentacja**: [README główne](README.md)

### Zasoby
- [Dokumentacja OpenAI API](https://platform.openai.com/docs)
- [WooCommerce Developer Docs](https://woocommerce.github.io/code-reference/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)

---

**Zbudowane z ❤️ przez [kemuriCode](https://github.com/kemuriCode)**

*Multi Wholesale Integration - Inteligentne zarządzanie hurtowniami i kategoriami* 
# 📋 Instrukcja Mapowania Kategorii - Multi Wholesale Integration

## 🎯 Cel systemu

System mapowania kategorii pozwala na ujednolicenie kategorii z różnych hurtowni, eliminację duplikatów i stworzenie spójnej struktury kategorii w sklepie. Jest to narzędzie do zarządzania kategoriami produktów z różnych dostawców.

## 📋 Jak to działa

### 1. **Analiza kategorii**
- System automatycznie skanuje wszystkie kategorie produktów
- Wykrywa źródło kategorii (hurtownię) na podstawie produktów
- Wyświetla statystyki: wszystkie kategorie, z produktami, puste

### 2. **Mapowanie**
- Wybierz kategorie które chcesz połączyć
- Określ główną kategorię docelową
- System zapisuje mapowania bez wprowadzania zmian

### 3. **Zastosowanie**
- Po zatwierdzeniu mapowania, system przenosi produkty
- Produkty z mapowanych kategorii trafiają do kategorii docelowej
- Automatyczna aktualizacja metadanych hurtowni

### 4. **Czyszczenie**
- Puste kategorie zostaną automatycznie usunięte
- System zachowuje kopie zapasowe przed operacjami

## ⚠️ Ważne informacje

### 🔒 Bezpieczeństwo
- **Zawsze rób kopię zapasową przed rozpoczęciem mapowania!**
- Operacja jest nieodwracalna - używaj ostrożnie
- System automatycznie tworzy kopie zapasowe przed operacjami

### 📊 Funkcjonalności
- Mapowanie działa tylko na kategoriach z produktami
- System automatycznie wykrywa źródło kategorii (hurtownię)
- Obsługuje wszystkie hurtownie: Malfini, Axpol, PAR, Inspirion, Macma, ANDA

## 🚀 Jak używać

### Krok 1: Przygotowanie
1. Przejdź do **Wtyczki > Multi Hurtownie Integration**
2. Wybierz zakładkę **"Mapowanie Kategorii"**
3. Przeczytaj instrukcję i statystyki

### Krok 2: Kopia zapasowa
1. Kliknij **"📦 Utwórz kopię zapasową"**
2. Poczekaj na potwierdzenie utworzenia kopii
3. **NIGDY nie pomijaj tego kroku!**

### Krok 3: Analiza kategorii
1. Sprawdź tabelę kategorii
2. Zwróć uwagę na kolumny:
   - **Nazwa kategorii** - nazwa kategorii
   - **Hurtownia** - źródło kategorii (kolorowe oznaczenia)
   - **Produkty** - liczba produktów w kategorii
   - **Status** - czy kategoria jest zmapowana

### Krok 4: Mapowanie
1. **Zaznacz kategorie** które chcesz połączyć
2. **Wybierz kategorię docelową** z dropdown
3. Możesz użyć **"Zaznacz wszystkie"** dla szybkiego wyboru
4. Kliknij **"💾 Zapisz mapowanie"**

### Krok 5: Zastosowanie
1. Sprawdź czy mapowanie jest poprawne
2. Kliknij **"Zastosuj mapowanie"**
3. Potwierdź operację
4. System przeniesie produkty i wyczyści mapowanie

### Krok 6: Czyszczenie
1. Po zastosowaniu mapowania sprawdź puste kategorie
2. Kliknij **"🗑️ Usuń puste kategorie"**
3. Potwierdź usunięcie

## 🎨 Oznaczenia kolorów

### Hurtownie:
- **🔵 Malfini** - niebieski
- **🟣 Axpol** - fioletowy  
- **🟠 PAR** - pomarańczowy
- **🟢 Inspirion** - zielony
- **🩷 Macma** - różowy
- **🟢 ANDA** - jasnozielony

### Statusy:
- **🟢 Zmapowana** - kategoria ma przypisane mapowanie
- **🟡 Nie zmapowana** - kategoria nie ma mapowania

## 📊 Statystyki

System wyświetla:
- **Wszystkie kategorie** - łączna liczba
- **Kategorie z produktami** - tylko te z produktami
- **Puste kategorie** - do usunięcia
- **Zapisane mapowania** - aktualne mapowania

## 💾 Kopie zapasowe

### Tworzenie kopii:
- Automatyczne przed operacjami
- Ręczne przez przycisk "📦 Utwórz kopię zapasową"
- Zawiera: kategorie, mapowania, timestamp

### Przywracanie kopii:
1. Wybierz kopię z dropdown
2. Kliknij "Przywróć kopię zapasową"
3. Potwierdź operację

### Lokalizacja kopii:
```
wp-content/uploads/mhi-backups/
```

## 🔧 Funkcje zaawansowane

### Wykrywanie hurtowni:
- System sprawdza metadane produktów
- Automatycznie zapisuje źródło w kategorii
- Obsługuje wszystkie hurtownie pluginu

### Bezpieczne przenoszenie:
- Sprawdza istnienie kategorii
- Obsługuje błędy i wyświetla komunikaty
- Aktualizuje metadane hurtowni produktów

### Walidacja:
- Sprawdza czy kategorie istnieją
- Weryfikuje mapowania przed zastosowaniem
- Zapobiega błędnym operacjom

## 🚨 Rozwiązywanie problemów

### Problem: "Brak mapowań do zastosowania"
**Rozwiązanie:** Najpierw zapisz mapowanie, potem zastosuj

### Problem: "Błąd podczas przenoszenia produktów"
**Rozwiązanie:** 
1. Sprawdź czy kategorie istnieją
2. Upewnij się że masz uprawnienia
3. Sprawdź logi błędów

### Problem: "Nie można utworzyć kopii zapasowej"
**Rozwiązanie:**
1. Sprawdź uprawnienia do folderu uploads
2. Upewnij się że jest miejsce na dysku
3. Sprawdź logi błędów

### Problem: Kategorie nie są wykrywane
**Rozwiązanie:**
1. Sprawdź czy produkty mają metadane hurtowni
2. Uruchom ponownie analizę kategorii
3. Sprawdź czy WooCommerce jest aktywne

## 📞 Wsparcie

W przypadku problemów:
1. Sprawdź logi błędów WordPress
2. Upewnij się że masz kopię zapasową
3. Skontaktuj się z administratorem

## 🔄 Przykład użycia

### Scenariusz: Ujednolicenie kategorii "Koszulki"

1. **Analiza:** Znajdujesz kategorie:
   - "Koszulki" (Malfini) - 15 produktów
   - "Koszulki męskie" (Axpol) - 8 produktów  
   - "Koszulki damskie" (PAR) - 12 produktów

2. **Mapowanie:** 
   - Zaznacz "Koszulki męskie" i "Koszulki damskie"
   - Wybierz "Koszulki" (Malfini) jako kategorię docelową
   - Zapisz mapowanie

3. **Zastosowanie:**
   - Kliknij "Zastosuj mapowanie"
   - System przeniesie 20 produktów do "Koszulki"
   - Usunie puste kategorie

4. **Rezultat:**
   - Jedna kategoria "Koszulki" z 35 produktami
   - Czytelna struktura kategorii
   - Mniej bałaganu w sklepie

---

**⚠️ Pamiętaj: Zawsze rób kopię zapasową przed operacjami!** 
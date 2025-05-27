#!/bin/bash

# =============================================================================
# SKRYPT URUCHAMIANIA CRON IMPORT
# Ułatwia uruchamianie cron-import.php z różnymi parametrami
# =============================================================================

# Konfiguracja
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
CRON_IMPORT="$PLUGIN_DIR/cron-import.php"

# Kolory dla output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funkcja pomocy
show_help() {
    echo -e "${BLUE}🚀 CRON IMPORT - Skrypt Uruchamiania${NC}"
    echo ""
    echo "Użycie: $0 [OPCJE] DOSTAWCA"
    echo ""
    echo "DOSTAWCY:"
    echo "  malfini    Import produktów Malfini"
    echo "  axpol      Import produktów Axpol"
    echo "  macma      Import produktów Macma"
    echo "  par        Import produktów PAR"
    echo ""
    echo "OPCJE:"
    echo "  -r, --replace-images    Zastąp istniejące obrazy galerii"
    echo "  -t, --test             Użyj pliku testowego (test_gallery.xml)"
    echo "  -l, --log-level LEVEL  Poziom logowania (error|warning|info|debug)"
    echo "  -m, --max-products N   Maksymalna liczba produktów do przetworzenia"
    echo "  -q, --quiet            Tryb cichy (tylko błędy)"
    echo "  -v, --verbose          Tryb szczegółowy (debug)"
    echo "  -h, --help             Pokaż tę pomoc"
    echo ""
    echo "PRZYKŁADY:"
    echo "  $0 malfini                           # Podstawowy import Malfini"
    echo "  $0 -r malfini                       # Import z zastąpieniem obrazów"
    echo "  $0 -t -m 10 malfini                 # Test z 10 produktami"
    echo "  $0 -v malfini                       # Import z debugowaniem"
    echo "  $0 -q axpol                         # Cichy import Axpol"
    echo "  $0 --log-level warning macma        # Import Macma z ostrzeżeniami"
    echo ""
}

# Funkcja logowania
log() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    case $level in
        "ERROR")
            echo -e "${RED}[${timestamp}] [ERROR] ${message}${NC}" >&2
            ;;
        "WARNING")
            echo -e "${YELLOW}[${timestamp}] [WARNING] ${message}${NC}"
            ;;
        "INFO")
            echo -e "${GREEN}[${timestamp}] [INFO] ${message}${NC}"
            ;;
        "DEBUG")
            echo -e "${BLUE}[${timestamp}] [DEBUG] ${message}${NC}"
            ;;
    esac
}

# Sprawdź czy plik cron-import.php istnieje
check_cron_import() {
    if [[ ! -f "$CRON_IMPORT" ]]; then
        log "ERROR" "Plik cron-import.php nie istnieje: $CRON_IMPORT"
        exit 1
    fi
}

# Sprawdź czy PHP jest dostępne
check_php() {
    if ! command -v php &> /dev/null; then
        log "ERROR" "PHP nie jest zainstalowane lub niedostępne w PATH"
        exit 1
    fi
    
    local php_version=$(php -v | head -n1 | cut -d' ' -f2)
    log "INFO" "Używam PHP w wersji: $php_version"
}

# Walidacja dostawcy
validate_supplier() {
    local supplier=$1
    case $supplier in
        "malfini"|"axpol"|"macma"|"par")
            return 0
            ;;
        *)
            log "ERROR" "Nieprawidłowy dostawca: $supplier"
            log "ERROR" "Dostępni dostawcy: malfini, axpol, macma, par"
            exit 1
            ;;
    esac
}

# Parsowanie argumentów
REPLACE_IMAGES=0
TEST_XML=0
LOG_LEVEL="info"
MAX_PRODUCTS=0
SUPPLIER=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -r|--replace-images)
            REPLACE_IMAGES=1
            shift
            ;;
        -t|--test)
            TEST_XML=1
            shift
            ;;
        -l|--log-level)
            LOG_LEVEL="$2"
            shift 2
            ;;
        -m|--max-products)
            MAX_PRODUCTS="$2"
            shift 2
            ;;
        -q|--quiet)
            LOG_LEVEL="error"
            shift
            ;;
        -v|--verbose)
            LOG_LEVEL="debug"
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        -*)
            log "ERROR" "Nieznana opcja: $1"
            show_help
            exit 1
            ;;
        *)
            if [[ -z "$SUPPLIER" ]]; then
                SUPPLIER="$1"
            else
                log "ERROR" "Zbyt wiele argumentów: $1"
                show_help
                exit 1
            fi
            shift
            ;;
    esac
done

# Sprawdź czy podano dostawcę
if [[ -z "$SUPPLIER" ]]; then
    log "ERROR" "Nie podano dostawcy!"
    show_help
    exit 1
fi

# Walidacje
validate_supplier "$SUPPLIER"
check_php
check_cron_import

# Buduj parametry
PARAMS="supplier=$SUPPLIER"

if [[ $REPLACE_IMAGES -eq 1 ]]; then
    PARAMS="$PARAMS replace_images=1"
fi

if [[ $TEST_XML -eq 1 ]]; then
    PARAMS="$PARAMS test_xml=1"
fi

if [[ "$LOG_LEVEL" != "info" ]]; then
    PARAMS="$PARAMS log_level=$LOG_LEVEL"
fi

if [[ $MAX_PRODUCTS -gt 0 ]]; then
    PARAMS="$PARAMS max_products=$MAX_PRODUCTS"
fi

# Pokaż konfigurację
log "INFO" "🚀 Rozpoczynam import CRON"
log "INFO" "📦 Dostawca: $SUPPLIER"
log "INFO" "🔧 Parametry: $PARAMS"
log "INFO" "📄 Skrypt: $CRON_IMPORT"

if [[ $REPLACE_IMAGES -eq 1 ]]; then
    log "WARNING" "⚠️  Obrazy galerii zostaną zastąpione!"
fi

if [[ $TEST_XML -eq 1 ]]; then
    log "INFO" "🧪 Tryb testowy - używam test_gallery.xml"
fi

if [[ $MAX_PRODUCTS -gt 0 ]]; then
    log "INFO" "📊 Limit produktów: $MAX_PRODUCTS"
fi

# Uruchom import
log "INFO" "▶️  Uruchamiam import..."
echo ""

# Zapisz czas rozpoczęcia
START_TIME=$(date +%s)

# Uruchom PHP z parametrami
php "$CRON_IMPORT" $PARAMS
EXIT_CODE=$?

# Oblicz czas wykonania
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo ""

# Pokaż wynik
if [[ $EXIT_CODE -eq 0 ]]; then
    log "INFO" "✅ Import zakończony pomyślnie!"
    log "INFO" "⏱️  Czas wykonania: ${DURATION}s"
else
    log "ERROR" "❌ Import zakończony z błędami (kod: $EXIT_CODE)"
    log "ERROR" "⏱️  Czas wykonania: ${DURATION}s"
fi

# Pokaż lokalizację logów
LOG_DIR="$(dirname "$CRON_IMPORT")/../../../uploads/wholesale/logs"
if [[ -d "$LOG_DIR" ]]; then
    LOG_FILE="$LOG_DIR/cron_import_${SUPPLIER}_$(date +%Y-%m-%d).log"
    if [[ -f "$LOG_FILE" ]]; then
        log "INFO" "📋 Logi zapisane w: $LOG_FILE"
        
        # Pokaż ostatnie linie logu jeśli nie jest tryb cichy
        if [[ "$LOG_LEVEL" != "error" ]]; then
            echo ""
            log "INFO" "📄 Ostatnie linie logu:"
            echo "----------------------------------------"
            tail -n 5 "$LOG_FILE"
            echo "----------------------------------------"
        fi
    fi
fi

# Pokaż statystyki jeśli dostępne
if command -v wp &> /dev/null; then
    WP_DIR="$(dirname "$CRON_IMPORT")/../../../.."
    if [[ -f "$WP_DIR/wp-config.php" ]]; then
        log "INFO" "📊 Pobieranie statystyk..."
        cd "$WP_DIR"
        STATS=$(wp option get "mhi_last_cron_import_$SUPPLIER" --format=json 2>/dev/null)
        if [[ $? -eq 0 && "$STATS" != "false" ]]; then
            echo ""
            log "INFO" "📈 Statystyki importu:"
            echo "$STATS" | php -r '
                $stats = json_decode(file_get_contents("php://stdin"), true);
                if ($stats) {
                    echo "   Produkty łącznie: " . $stats["total_products"] . "\n";
                    echo "   Utworzone: " . $stats["created"] . "\n";
                    echo "   Zaktualizowane: " . $stats["updated"] . "\n";
                    echo "   Błędy: " . $stats["failed"] . "\n";
                    echo "   Obrazy: " . $stats["images"] . "\n";
                    echo "   Czas: " . $stats["duration"] . "s\n";
                }
            '
        fi
    fi
fi

exit $EXIT_CODE 
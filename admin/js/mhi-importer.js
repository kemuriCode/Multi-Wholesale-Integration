/**
 * Skrypt obsługujący interfejs importu produktów do WooCommerce
 * 
 * @package MHI
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Usunięcie debugowego alertu
    // alert('Skrypt MHI Importer został załadowany!');

    // Elementy interfejsu
    const $importButtons = $('.mhi-import-products-button');
    const $stopButtons = $('.mhi-stop-import-button');
    const $progressBars = $('.mhi-import-progress');
    const $progressTexts = $('.mhi-import-progress-text');
    const $statusInfos = $('.mhi-import-status-info');
    const $importStats = $('.mhi-import-stats');

    // Status aktualizacji
    let updateInterval = null;
    let currentSupplier = '';

    // Zmienna przechowująca nonce utworzony przy ładowaniu strony
    let ajaxNonce = '';

    // Ustawienie parametrów AJAX dla wszystkich żądań
    $.ajaxSetup({
        beforeSend: function (xhr) {
            console.log('Wysyłanie żądania AJAX z parametrami:', this.data);
        },
        error: function (xhr, status, error) {
            console.error('Globalny błąd AJAX:', status, error);
            console.error('Status HTTP:', xhr.status, xhr.statusText);
            console.error('Odpowiedź:', xhr.responseText);

            // Dodanie bezpośredniego komunikatu w konsoli dla błędu 400
            if (xhr.status === 400) {
                console.error('BŁĄD 400: Nieprawidłowe żądanie. Sprawdź czy punkty końcowe AJAX są prawidłowo zarejestrowane.');
            }
        }
    });

    // Sprawdź czy mamy nonce z localizacji
    if (typeof MHI_Ajax !== 'undefined' && MHI_Ajax.nonce) {
        ajaxNonce = MHI_Ajax.nonce;
        console.log('Pobrany nonce z localizacji:', ajaxNonce);
    } else {
        // Jeśli nie, spróbuj użyć globalnej zmiennej i specjalnego nonce dla importu
        const nonceField = document.querySelector('input[name="mhi_import_products_nonce"]');
        if (nonceField) {
            ajaxNonce = nonceField.value;
            console.log('Pobrany nonce z pola formularza import:', ajaxNonce);
        } else {
            // Próba pobrania bezpośredniego nonce dla dostawcy
            const directNonceFields = document.querySelectorAll('[id^="mhi-direct-nonce-"]');
            if (directNonceFields.length > 0) {
                ajaxNonce = directNonceFields[0].value;
                console.log('Pobrany nonce z pola bezpośredniego:', ajaxNonce);
            } else {
                // Próba pobrania standardowego nonce z formularza
                const wpNonceField = document.querySelector('input[name="_wpnonce"]');
                if (wpNonceField) {
                    ajaxNonce = wpNonceField.value;
                    console.log('Pobrany nonce z pola formularza WP:', ajaxNonce);
                } else {
                    console.error('Nie można odnaleźć nonce na stronie!');
                }
            }
        }
    }

    console.log('MHI Importer JS inicjalizacja');
    console.log('Liczba przycisków importu:', $importButtons.length);

    // Wypisz wszystkie przyciski dla debugowania
    $importButtons.each(function (index) {
        const $button = $(this);
        const supplier = $button.data('supplier');
        console.log(`Przycisk #${index}: supplier=${supplier}, id=${$button.attr('id')}, class=${$button.attr('class')}`);
    });

    /**
     * Inicjalizacja
     */
    function init() {
        // Obsługa przycisku importu przez zdarzenie onclick inline (bardziej bezpośrednie)
        $importButtons.each(function () {
            const $button = $(this);
            const supplier = $button.data('supplier');
            // Pobierz nonce z atrybutu data-nonce (dodane w HTML)
            const buttonNonce = $button.data('nonce');
            if (buttonNonce) {
                console.log(`Nonce dla przycisku ${supplier}:`, buttonNonce);
            }
            $button.attr('onclick', `importProducts('${supplier}', this); return false;`);
        });

        // Obsługa przycisku zatrzymania przez zdarzenie onclick inline
        $stopButtons.each(function () {
            const $button = $(this);
            const supplier = $button.data('supplier');
            $button.attr('onclick', `stopImport('${supplier}', this); return false;`);
        });

        // Dodaj też standardową obsługę przez jQuery dla pewności
        $importButtons.on('click', function (e) {
            e.preventDefault();
            const supplier = $(this).data('supplier');
            if (!supplier) {
                alert('Błąd: Nie określono dostawcy');
                return;
            }
            importProducts(supplier, this);
        });

        $stopButtons.on('click', function (e) {
            e.preventDefault();
            const supplier = $(this).data('supplier');
            if (!supplier) {
                alert('Błąd: Nie określono dostawcy');
                return;
            }
            stopImport(supplier, this);
        });

        // Sprawdź status importu przy załadowaniu strony dla wszystkich dostawców
        $importButtons.each(function () {
            const supplier = $(this).data('supplier');
            if (supplier) {
                console.log('Sprawdzanie statusu dla dostawcy:', supplier);
                checkImportStatus(supplier, $(this).closest('.mhi-import-section'));
            }
        });
    }

    /**
     * Funkcja globalnie dostępna dla przycisku importu
     */
    window.importProducts = function (supplier, buttonElement) {
        console.log('Wywołano importProducts dla dostawcy:', supplier);

        const $section = $(buttonElement).closest('.mhi-import-section');
        $(buttonElement).prop('disabled', true);

        // Wyświetl pasek postępu
        const $progressBar = $section.find('.mhi-import-progress');
        $progressBar.show();
        $progressBar.find('.mhi-progress-bar').css('width', '0%');
        $progressBar.find('.mhi-progress-percent').text('0%');

        startImport(supplier, $section);
    };

    /**
     * Funkcja globalnie dostępna dla przycisku zatrzymania
     */
    window.stopImport = function (supplier, buttonElement) {
        console.log('Wywołano stopImport dla dostawcy:', supplier);

        const $section = $(buttonElement).closest('.mhi-import-section');
        $(buttonElement).prop('disabled', true);

        // Wyświetl informację o próbie zatrzymania
        showMessage('Zatrzymywanie importu... Proszę czekać...', 'info', $section);

        stopImportAjax(supplier, $section);
    };

    /**
     * Rozpoczyna import produktów
     */
    function startImport(supplier, $section) {
        console.log('Rozpoczynam import dla dostawcy:', supplier);

        // Dodaj wiadomość na interfejsie o próbie rozpoczęcia importu
        showMessage('Rozpoczynam import... Proszę czekać...', 'info', $section);

        // Pobierz nonce dla konkretnego dostawcy
        let supplierNonce = ajaxNonce;

        // Spróbuj pobrać nonce z HTML dla przycisku importu tego dostawcy
        const directNonce = document.querySelector('#mhi-direct-nonce-' + supplier);
        if (directNonce) {
            supplierNonce = directNonce.value;
            console.log('Pobrany nonce inline dla ' + supplier + ':', supplierNonce);
        } else {
            // Ostatnia szansa - pobierz z atrybutu data
            const importButton = document.querySelector('#mhi-import-' + supplier + '-button');
            if (importButton && importButton.dataset.nonce) {
                supplierNonce = importButton.dataset.nonce;
                console.log('Pobrany nonce z data-nonce dla ' + supplier + ':', supplierNonce);
            }
        }

        // Przygotuj dane do wysyłki
        const ajaxData = {
            action: 'mhi_start_import',
            supplier: supplier
        };

        // Dodaj nonce do danych
        if (supplierNonce) {
            ajaxData.nonce = supplierNonce;
            console.log('Używam nonce dla importu ' + supplier + ':', supplierNonce);
        } else if (ajaxNonce) {
            ajaxData.nonce = ajaxNonce;
            console.log('Używam ogólnego nonce dla importu:', ajaxNonce);
        } else {
            console.error('Brak nonce do żądania AJAX!');
            showMessage('Błąd: Brak tokenu bezpieczeństwa. Odśwież stronę i spróbuj ponownie.', 'error', $section);
            $section.find('.mhi-import-products-button').prop('disabled', false);
            return;
        }

        // Wyślij żądanie AJAX
        $.ajax({
            url: typeof MHI_Ajax !== 'undefined' ? MHI_Ajax.ajaxurl : ajaxurl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                console.log('Odpowiedź AJAX dla startu importu:', response);

                if (response.success) {
                    showMessage(response.data.message, 'success', $section);

                    // Rozpocznij okresowe sprawdzanie statusu
                    startStatusChecking(supplier, $section);

                    // Pokaż przycisk zatrzymania
                    $section.find('.mhi-stop-import-button').show();
                } else {
                    showMessage(response.data.message || 'Wystąpił nieznany błąd podczas inicjowania importu.', 'error', $section);
                    $section.find('.mhi-import-products-button').prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                console.error('Błąd AJAX:', error, xhr.responseText);
                console.error('Status HTTP:', xhr.status, xhr.statusText);

                let errorMessage = 'Wystąpił błąd podczas komunikacji z serwerem';

                if (xhr.status === 400) {
                    errorMessage += ': Bad Request. Sprawdź poprawność danych.';
                } else if (xhr.status === 403) {
                    errorMessage += ': Brak uprawnień do wykonania tej operacji.';
                } else if (xhr.status === 404) {
                    errorMessage += ': Nie znaleziono punktu końcowego AJAX.';
                } else if (xhr.status === 500) {
                    errorMessage += ': Wewnętrzny błąd serwera. Sprawdź logi PHP.';
                } else {
                    errorMessage += ': ' + error;
                }

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.data && response.data.message) {
                        errorMessage = 'Błąd: ' + response.data.message;
                    }
                } catch (e) {
                    // Nie udało się sparsować odpowiedzi JSON, użyj podstawowego komunikatu błędu
                }

                showMessage(errorMessage, 'error', $section);
                $section.find('.mhi-import-products-button').prop('disabled', false);
            }
        });
    }

    /**
     * Wysyła żądanie AJAX do zatrzymania importu
     */
    function stopImportAjax(supplier, $section) {
        // Pobierz nonce dla zatrzymania (może być inny niż dla importu)
        let stopNonce = ajaxNonce;

        // Próba pobrania nonce z HTML
        const directNonce = document.querySelector('#mhi-direct-nonce-' + supplier);
        if (directNonce) {
            stopNonce = directNonce.value;
            console.log('Pobrany nonce inline dla zatrzymania ' + supplier + ':', stopNonce);
        }

        // Przygotuj dane do wysyłki
        const ajaxData = {
            action: 'mhi_stop_import',
            supplier: supplier,
            nonce: stopNonce
        };

        console.log('Wysyłanie żądania zatrzymania AJAX z parametrami:', ajaxData);

        // Wyślij żądanie AJAX
        $.ajax({
            url: typeof MHI_Ajax !== 'undefined' ? MHI_Ajax.ajaxurl : ajaxurl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                console.log('Odpowiedź AJAX dla zatrzymania importu:', response);

                if (response.success) {
                    showMessage(response.data.message || 'Import zostanie zatrzymany po zakończeniu obecnej partii produktów.', 'success', $section);

                    // Zmień przycisk
                    $section.find('.mhi-stop-import-button').hide();
                    $section.find('.mhi-import-products-button').prop('disabled', false);

                    // Kontynuuj sprawdzanie statusu
                    startStatusChecking(supplier, $section);
                } else {
                    showMessage(response.data.message || 'Wystąpił błąd podczas zatrzymywania importu.', 'error', $section);
                    $section.find('.mhi-stop-import-button').prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                console.error('Błąd AJAX podczas zatrzymywania importu:', error);
                console.error('Status HTTP:', xhr.status, xhr.statusText);
                console.error('Odpowiedź:', xhr.responseText);

                let errorMessage = 'Wystąpił błąd podczas komunikacji z serwerem';

                // Szczegółowy komunikat o błędzie
                if (xhr.status === 400) {
                    errorMessage = 'Błąd zapytania (Bad Request). Sprawdź logi serwera.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Brak uprawnień do zatrzymania importu.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Nie znaleziono punktu końcowego AJAX.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Wewnętrzny błąd serwera.';
                }

                showMessage(errorMessage, 'error', $section);
                $section.find('.mhi-stop-import-button').prop('disabled', false);
            }
        });
    }

    /**
     * Rozpoczyna okresowe sprawdzanie statusu importu
     */
    function startStatusChecking(supplier, $section) {
        // Zatrzymaj istniejący interwał
        if (updateInterval) {
            clearInterval(updateInterval);
        }

        // Sprawdź status natychmiast
        checkImportStatus(supplier, $section);

        // Ustaw sprawdzanie statusu co 3 sekundy
        updateInterval = setInterval(function () {
            checkImportStatus(supplier, $section);
        }, 3000);
    }

    /**
     * Sprawdza status importu
     */
    function checkImportStatus(supplier, $section) {
        // Przygotuj dane do wysyłki
        const ajaxData = {
            action: 'mhi_get_import_status',
            supplier: supplier
        };

        // Dodaj nonce do danych
        if (ajaxNonce) {
            ajaxData.nonce = ajaxNonce;
        } else {
            console.error('Brak nonce do żądania AJAX przy sprawdzaniu statusu!');
            return;
        }

        $.ajax({
            url: typeof MHI_Ajax !== 'undefined' ? MHI_Ajax.ajaxurl : ajaxurl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.status) {
                    console.log('Status dla dostawcy ' + supplier + ':', response.data.status);
                    updateImportStatus(response.data.status, $section);
                }
            },
            error: function (xhr, status, error) {
                console.error('Błąd podczas sprawdzania statusu:', error);
            }
        });
    }

    /**
     * Aktualizuje status importu w interfejsie użytkownika
     */
    function updateImportStatus(status, $section) {
        if (!status) return;

        // Aktualizuj pasek postępu
        updateProgressBar(status.percent, $section);

        // Aktualizuj tekst statusu
        const statusText = status.message || '';
        $section.find('.mhi-import-status-info').html(statusText);

        // Aktualizuj statystyki importu
        updateStats(status, $section);

        // W zależności od statusu, pokaż lub ukryj przyciski
        if (status.status === 'completed' || status.status === 'error' || status.status === 'stopped') {
            // Import zakończony, zatrzymany lub wystąpił błąd - można ponownie rozpocząć
            $section.find('.mhi-import-products-button').prop('disabled', false);
            $section.find('.mhi-stop-import-button').hide();

            // Zatrzymaj sprawdzanie statusu
            clearInterval(updateInterval);
            updateInterval = null;

            // Dostosuj kolor paska postępu
            if (status.status === 'error') {
                $section.find('.mhi-progress-bar').css('background-color', '#dc3545'); // czerwony dla błędu
            } else if (status.status === 'stopped') {
                $section.find('.mhi-progress-bar').css('background-color', '#ffc107'); // żółty dla zatrzymanego
            }
        } else if (status.status === 'running') {
            // Import w trakcie - można zatrzymać
            $section.find('.mhi-import-products-button').prop('disabled', true);
            $section.find('.mhi-stop-import-button').show().prop('disabled', false);
        } else if (status.status === 'stopping') {
            // Import w trakcie zatrzymywania - czekamy
            $section.find('.mhi-import-products-button').prop('disabled', true);
            $section.find('.mhi-stop-import-button').prop('disabled', true);

            // Dodaj dodatkową informację o zatrzymywaniu
            const stoppingText = $section.find('.mhi-stopping-info');
            if (stoppingText.length === 0) {
                $section.find('.mhi-import-status-info').after(
                    '<div class="mhi-stopping-info" style="color: #ff9800; font-weight: bold; margin-top: 10px;">' +
                    'Import będzie zatrzymany po zakończeniu bieżącej partii. Proszę czekać...' +
                    '</div>'
                );
            }

            // Zmień kolor paska postępu na pomarańczowy
            $section.find('.mhi-progress-bar').css('background-color', '#ff9800');
        }
    }

    /**
     * Aktualizuje pasek postępu
     */
    function updateProgressBar(percent, $section) {
        $section.find('.mhi-progress-bar').css('width', percent + '%');
        $section.find('.mhi-progress-percent').text(percent + '%');
    }

    /**
     * Aktualizuje statystyki importu
     */
    function updateStats(status, $section) {
        if (!status) {
            return;
        }

        let statsHtml = '';

        if (status.processed !== undefined) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Przetworzono:</span> <span class="mhi-stat-value">' + status.processed + ' / ' + status.total + '</span></div>';
        }

        if (status.created !== undefined) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Dodano:</span> <span class="mhi-stat-value">' + status.created + '</span></div>';
        }

        if (status.updated !== undefined) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Zaktualizowano:</span> <span class="mhi-stat-value">' + status.updated + '</span></div>';
        }

        if (status.skipped !== undefined) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Pominięto:</span> <span class="mhi-stat-value">' + status.skipped + '</span></div>';
        }

        if (status.failed !== undefined) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Błędy:</span> <span class="mhi-stat-value">' + status.failed + '</span></div>';
        }

        if (status.current_product) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Aktualny produkt:</span> <span class="mhi-stat-value">' + status.current_product + '</span></div>';
        }

        if (status.elapsed_time) {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Czas:</span> <span class="mhi-stat-value">' + formatTime(status.elapsed_time) + '</span></div>';
        }

        if (status.estimated_time && status.status === 'running') {
            statsHtml += '<div class="mhi-stat"><span class="mhi-stat-label">Szacowany pozostały czas:</span> <span class="mhi-stat-value">' + formatTime(status.estimated_time) + '</span></div>';
        }

        $section.find('.mhi-import-stats').html(statsHtml);
        $section.find('.mhi-import-stats').show();
    }

    /**
     * Wyświetla komunikat
     */
    function showMessage(message, type, $section) {
        // Usuń wcześniejsze komunikaty
        $section.find('.mhi-message').remove();

        // Utwórz nowy komunikat
        const $message = $('<div class="mhi-message mhi-message-' + type + '">' + message + '</div>');

        // Dodaj komunikat nad paskiem postępu
        $section.find('.mhi-import-progress').before($message);

        // Przewiń do komunikatu
        $('html, body').animate({
            scrollTop: $message.offset().top - 50
        }, 500);
    }

    /**
     * Formatuje czas w sekundach na format czytelny dla człowieka
     */
    function formatTime(seconds) {
        seconds = parseInt(seconds, 10);

        if (seconds < 60) {
            return seconds + " s";
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return minutes + " min " + remainingSeconds + " s";
        } else {
            const hours = Math.floor(seconds / 3600);
            const remainingMinutes = Math.floor((seconds % 3600) / 60);
            return hours + " godz " + remainingMinutes + " min";
        }
    }

    // Bezpośrednie sprawdzenie punktów końcowych AJAX
    $.post(
        typeof MHI_Ajax !== 'undefined' ? MHI_Ajax.ajaxurl : ajaxurl,
        { action: 'mhi_test_connection' },
        function (response) {
            console.log('Test połączenia AJAX zakończony sukcesem');
        }
    ).fail(function (xhr, status, error) {
        console.error('Test połączenia AJAX zakończony błędem:', status, error);
    });

    // Funkcja pomocnicza do debugowania
    function logDebug(message) {
        console.log('MHI Debug: ' + message);
    }

    // Funkcja pomocnicza do debugowania błędów
    function logError(message) {
        console.error('MHI Error: ' + message);
    }

    // Inicjalizacja skryptu
    init();
}); 
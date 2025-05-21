/**
 * Skrypt dla panelu administracyjnego wtyczki Multi Hurtownie Integration.
 *
 * @package MHI
 */

(function ($) {
    'use strict';

    // Inicjalizacja po załadowaniu strony
    $(document).ready(function () {
        // Obsługa włączania/wyłączania sekcji ustawień hurtowni
        $('.mhi-hurtownia-enabled').on('change', function () {
            var hurtownia = $(this).data('hurtownia');
            var enabled = $(this).is(':checked');

            $('.mhi-hurtownia-' + hurtownia + '-settings').toggle(enabled);
        });

        // Inicjalne ukrycie sekcji dla wyłączonych hurtowni
        $('.mhi-hurtownia-enabled').each(function () {
            var hurtownia = $(this).data('hurtownia');
            var enabled = $(this).is(':checked');

            $('.mhi-hurtownia-' + hurtownia + '-settings').toggle(enabled);
        });

        // Obsługa przycisków testowania połączenia
        $('.mhi-test-connection').on('click', function (e) {
            e.preventDefault();

            var hurtownia = $(this).data('hurtownia');
            var $statusContainer = $('#mhi-' + hurtownia + '-connection-status');

            $statusContainer.html('<span class="spinner is-active"></span> ' + mhi_admin.testing_connection);

            // Wykonanie żądania AJAX
            $.ajax({
                url: mhi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhi_test_connection',
                    hurtownia: hurtownia,
                    nonce: mhi_admin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $statusContainer.html('<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ' + response.data.message);
                    } else {
                        $statusContainer.html('<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span> ' + response.data.message);
                    }
                },
                error: function () {
                    $statusContainer.html('<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span> ' + mhi_admin.connection_error);
                }
            });
        });

        // Pokazywanie/ukrywanie pól hasła
        $('.mhi-toggle-password').on('click', function (e) {
            e.preventDefault();

            var $passwordField = $($(this).data('target'));
            var currentType = $passwordField.attr('type');

            if (currentType === 'password') {
                $passwordField.attr('type', 'text');
                $(this).html(mhi_admin.hide_password);
                $(this).find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $passwordField.attr('type', 'password');
                $(this).html(mhi_admin.show_password);
                $(this).find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Identyfikatory hurtowni, dla których chcemy odświeżać status
        const hurtownieIds = [
            'hurtownia_1',
            'hurtownia_2',
            'hurtownia_3',
            'hurtownia_4',
            'hurtownia_5'
        ];

        // Zmienna do śledzenia aktywnych pobierań
        let activeFetches = {};

        // Częstotliwość odświeżania statusu (w milisekundach)
        const refreshInterval = 2000;

        // Funkcja odświeżająca status pobierania
        function refreshStatus() {
            // Sprawdź aktywną zakładkę
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'general';

            // Określ który status odświeżyć
            let statusIds = [];

            if (tab === 'general') {
                // Na zakładce ogólnej pokazujemy statusy wszystkich hurtowni
                statusIds = hurtownieIds;
            } else if (tab === 'hurtownia-1') {
                statusIds.push('hurtownia_1');
            } else if (tab === 'axpol') {
                statusIds.push('hurtownia_2');
            } else if (tab === 'par') {
                statusIds.push('hurtownia_3');
            } else if (tab === 'inspirion') {
                statusIds.push('hurtownia_4');
            } else if (tab === 'macma') {
                statusIds.push('hurtownia_5');
            }

            // Pobierz aktualny status dla każdej hurtowni na widocznej zakładce
            statusIds.forEach(function (hurtowniaId) {
                const $statusContainer = $('#mhi-download-status-' + hurtowniaId);

                if ($statusContainer.length) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mhi_get_download_status',
                            hurtownia: hurtowniaId,
                            nonce: mhi_admin.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                const statusText = response.data.status;
                                $statusContainer.find('.mhi-status-content').html(statusText);

                                // Sprawdź, czy trwa pobieranie i zaktualizuj flagę
                                if (statusText.includes('Pobieranie')) {
                                    activeFetches[hurtowniaId] = true;

                                    // Dodaj animację ładowania
                                    if (!$statusContainer.hasClass('mhi-loading')) {
                                        $statusContainer.addClass('mhi-loading');
                                        $statusContainer.find('h4').append(' <span class="spinner is-active" style="float: none; margin: 0 0 0 5px;"></span>');
                                    }
                                } else {
                                    activeFetches[hurtowniaId] = false;

                                    // Usuń animację ładowania
                                    $statusContainer.removeClass('mhi-loading');
                                    $statusContainer.find('h4 .spinner').remove();
                                }
                            }
                        }
                    });
                }
            });

            // Sprawdź, czy mamy aktywne pobierania
            let hasActiveFetches = Object.values(activeFetches).some(status => status === true);

            // Kontynuuj odświeżanie jeśli są aktywne pobierania
            if (hasActiveFetches) {
                setTimeout(refreshStatus, refreshInterval);
            }
        }

        // Inicjalizuj status pobierania
        refreshStatus();

        // Odśwież status po kliknięciu przycisku pobierania
        $('input[name^="mhi_manual_run_"]').on('click', function () {
            // Pobierz ID hurtowni z nazwy przycisku
            const buttonName = $(this).attr('name');
            const hurtowniaId = buttonName.replace('mhi_manual_run_', '');

            // Ustaw flagę aktywnego pobierania
            if (hurtowniaId) {
                activeFetches[hurtowniaId] = true;
            } else {
                // Dla przycisku "Uruchom wszystkie" ustaw wszystkie flagi
                hurtownieIds.forEach(id => activeFetches[id] = true);
            }

            // Odśwież po sekundzie, aby dać czas na inicjalizację
            setTimeout(refreshStatus, 1000);
        });

        // Obsługa przycisku importu produktów do WooCommerce
        $('#mhi-import-products').on('click', function () {
            const $button = $(this);
            const $spinner = $('#mhi-import-spinner');
            const $result = $('#mhi-import-result');
            var $progressContainer = $('#mhi-import-progress-container');
            var $progressBar = $('#mhi-import-progress');
            var $progressText = $('#mhi-import-progress-text');
            var importPaused = false;
            var importCancelled = false;

            // Przygotuj UI do importu
            $button.attr('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            // Pokaż progress bar, jeśli istnieje lub utwórz go
            if ($progressContainer.length === 0) {
                $result.after('<div id="mhi-import-progress-container" style="margin-top: 10px; margin-bottom: 10px;">' +
                    '<div class="progress-label" id="mhi-import-progress-text">Przygotowanie importu...</div>' +
                    '<div class="progress" style="height: 20px; background-color: #f0f0f0; border-radius: 4px; overflow: hidden; margin-top: 5px;">' +
                    '<div id="mhi-import-progress" style="width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;"></div>' +
                    '</div>' +
                    '<div class="mhi-import-controls" style="margin-top: 10px;">' +
                    '<button id="mhi-pause-import" class="button">Wstrzymaj import</button> ' +
                    '<button id="mhi-resume-import" class="button" style="display:none;">Wznów import</button> ' +
                    '<button id="mhi-cancel-import" class="button">Anuluj import</button> ' +
                    '<button id="mhi-reset-products" class="button button-secondary" style="float:right;">Zresetuj wszystkie produkty</button>' +
                    '</div></div>');
                $progressContainer = $('#mhi-import-progress-container');
                $progressBar = $('#mhi-import-progress');
                $progressText = $('#mhi-import-progress-text');
            } else {
                $progressContainer.show();
                $progressBar.css('width', '0%');
                $progressText.text('Przygotowanie importu...');

                // Aktualizuj przyciski kontrolne
                if ($('.mhi-import-controls').length === 0) {
                    $progressContainer.append('<div class="mhi-import-controls" style="margin-top: 10px;">' +
                        '<button id="mhi-pause-import" class="button">Wstrzymaj import</button> ' +
                        '<button id="mhi-resume-import" class="button" style="display:none;">Wznów import</button> ' +
                        '<button id="mhi-cancel-import" class="button">Anuluj import</button> ' +
                        '<button id="mhi-reset-products" class="button button-secondary" style="float:right;">Zresetuj wszystkie produkty</button>' +
                        '</div>');
                }
            }

            // Sprawdź aktualny stan importu przed rozpoczęciem
            checkImportStatus(function (status) {
                if (status === 'paused') {
                    // Import jest wstrzymany - pokaż odpowiednie przyciski
                    $('#mhi-pause-import').hide();
                    $('#mhi-resume-import').show();
                    $result.html('<div class="notice notice-warning inline"><p>Import jest wstrzymany. Kliknij "Wznów import", aby kontynuować.</p></div>');
                    $button.attr('disabled', false);
                } else if (status === 'in_progress') {
                    // Import jest już w trakcie - wznów od miejsca, w którym się zatrzymał
                    $result.html('<div class="notice notice-info inline"><p>Wznawianie importu od ostatniego punktu...</p></div>');
                    startImport(status.next_offset || 0);
                } else {
                    // Normalny początek importu
                    startImport(0);
                }
            });

            // Funkcja sprawdza aktualny stan importu
            function checkImportStatus(callback) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_1',
                        action_type: 'check',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success && response.data) {
                            callback(response.data.status || 'none');
                        } else {
                            callback('none');
                        }
                    },
                    error: function () {
                        callback('none');
                    }
                });
            }

            // Funkcja uruchamia import produktów
            function startImport(offset) {
                // Jeśli import jest wstrzymany lub anulowany, zatrzymaj proces
                if (importPaused || importCancelled) {
                    $spinner.removeClass('is-active');
                    $button.attr('disabled', false);
                    return;
                }

                // Wykonaj żądanie AJAX do importu produktów
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_1',
                        offset: offset,
                        batch_size: 5, // Mniejszy batch dla stabilności
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            if (response.data.completed) {
                                // Import zakończony
                                $progressBar.css('width', '100%');
                                $progressText.text('Import zakończony! Przetworzono: ' + response.data.processed + ' z ' + response.data.total + ' produktów.');
                                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                $spinner.removeClass('is-active');
                                $button.attr('disabled', false);

                                // Ukryj przyciski kontrolne
                                $('.mhi-import-controls').hide();

                                // Ukryj progress bar po 5 sekundach
                                setTimeout(function () {
                                    $progressContainer.hide();
                                }, 5000);
                            } else {
                                // Import w trakcie
                                const progress = response.data.total > 0 ? Math.round((response.data.processed / response.data.total) * 100) : 0;
                                $progressBar.css('width', progress + '%').addClass('progress-active');
                                $progressText.text('Przetworzono: ' + response.data.processed + ' z ' + response.data.total + ' produktów (' + progress + '%)');

                                // Dodaj aktualizację do wyniku
                                $result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');

                                // Kontynuuj import z następnym offsetem
                                setTimeout(function () {
                                    startImport(response.data.next_offset);
                                }, 500); // Krótka przerwa między batchami
                            }
                        } else {
                            // Błąd importu
                            let errorMessage = 'Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd podczas importu') + '. ';
                            if (response.data && response.data.error_offset) {
                                errorMessage += 'Kliknij "Wznów import", aby kontynuować od miejsca, w którym wystąpił błąd.';
                                // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'mhi_import_products_hurtownia_1',
                                        action_type: 'save_error_point',
                                        error_offset: response.data.error_offset,
                                        nonce: mhi_admin.nonce
                                    },
                                    success: function () {
                                        // Pokaż przycisk wznowienia
                                        importPaused = true;
                                        $('#mhi-pause-import').hide();
                                        $('#mhi-resume-import').show();
                                    }
                                });
                            } else {
                                errorMessage += 'Spróbuj ponownie klikając "Wznów import".';
                                // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'mhi_import_products_hurtownia_1',
                                        action_type: 'save_error_point',
                                        error_offset: offset,
                                        nonce: mhi_admin.nonce
                                    },
                                    success: function () {
                                        // Pokaż przycisk wznowienia
                                        importPaused = true;
                                        $('#mhi-pause-import').hide();
                                        $('#mhi-resume-import').show();
                                    }
                                });
                            }
                            $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    },
                    error: function (xhr, status, error) {
                        // Obsługa błędów HTTP
                        let errorMessage = 'Błąd połączenia podczas importu. ';
                        if (xhr.status === 504) {
                            errorMessage += 'Przekroczenie limitu czasu (Gateway Timeout). Kliknij "Wznów import", aby kontynuować od miejsca, w którym wystąpił błąd.';
                            // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mhi_import_products_hurtownia_1',
                                    action_type: 'save_error_point',
                                    error_offset: offset,
                                    nonce: mhi_admin.nonce
                                },
                                success: function () {
                                    // Pokaż przycisk wznowienia
                                    importPaused = true;
                                    $('#mhi-pause-import').hide();
                                    $('#mhi-resume-import').show();
                                }
                            });
                        } else {
                            errorMessage += 'Kod błędu: ' + xhr.status + '. Spróbuj ponownie klikając "Wznów import".';
                            // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mhi_import_products_hurtownia_1',
                                    action_type: 'save_error_point',
                                    error_offset: offset,
                                    nonce: mhi_admin.nonce
                                },
                                success: function () {
                                    // Pokaż przycisk wznowienia
                                    importPaused = true;
                                    $('#mhi-pause-import').hide();
                                    $('#mhi-resume-import').show();
                                }
                            });
                        }
                        $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                        $spinner.removeClass('is-active');
                        $button.attr('disabled', false);
                    }
                });
            }

            // Obsługa przycisku wstrzymania importu
            $(document).on('click', '#mhi-pause-import', function (e) {
                e.preventDefault();
                importPaused = true;

                // Wyślij żądanie AJAX aby zapisać stan "wstrzymany" w bazie
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_1',
                        action_type: 'pause',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                            $('#mhi-pause-import').hide();
                            $('#mhi-resume-import').show();
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    }
                });
            });

            // Obsługa przycisku wznowienia importu
            $(document).on('click', '#mhi-resume-import', function (e) {
                e.preventDefault();
                importPaused = false;
                $button.attr('disabled', true);
                $spinner.addClass('is-active');

                // Wyślij żądanie AJAX aby wznowić import
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_1',
                        action_type: 'resume',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#mhi-resume-import').hide();
                            $('#mhi-pause-import').show();
                            $result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
                            startImport(response.data.next_offset || 0);
                        }
                    }
                });
            });

            // Obsługa przycisku anulowania importu
            $(document).on('click', '#mhi-cancel-import', function (e) {
                e.preventDefault();
                importCancelled = true;

                // Wyślij żądanie AJAX aby anulować import
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_1',
                        action_type: 'cancel',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                            $progressContainer.hide();
                        }
                    }
                });
            });

            // Obsługa przycisku resetowania produktów
            $(document).on('click', '#mhi-reset-products', function (e) {
                e.preventDefault();

                // Potwierdź operację
                if (!confirm('UWAGA: Ta operacja usunie WSZYSTKIE produkty, kategorie, atrybuty i zdjęcia z WooCommerce. Tej operacji nie można cofnąć! Czy na pewno chcesz kontynuować?')) {
                    return;
                }

                $(this).attr('disabled', true).text('Resetowanie...');

                // Wyślij żądanie AJAX aby zresetować produkty
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_1',
                        action_type: 'reset',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            $('#mhi-reset-products').attr('disabled', false).text('Zresetuj wszystkie produkty');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas resetowania: ' + response.data.message + '</p></div>');
                            $('#mhi-reset-products').attr('disabled', false).text('Zresetuj wszystkie produkty');
                        }
                    },
                    error: function () {
                        $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas resetowania produktów.</p></div>');
                        $('#mhi-reset-products').attr('disabled', false).text('Zresetuj wszystkie produkty');
                    }
                });
            });
        });

        // Obsługa przycisku importu produktów do WooCommerce dla hurtowni 3 (PAR)
        $('#mhi-import-products-hurtownia-3').on('click', function () {
            const $button = $(this);
            const $spinner = $('#mhi-import-spinner-hurtownia-3');
            const $result = $('#mhi-import-result-hurtownia-3');
            var $progressContainer = $('#mhi-import-progress-container-hurtownia-3');
            var $progressBar, $progressText;
            var importPaused = false;
            var importCancelled = false;

            // Przygotuj UI do importu
            $button.attr('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            // Pokaż progress bar, jeśli istnieje lub utwórz go
            if ($progressContainer.length === 0) {
                $result.after('<div id="mhi-import-progress-container-hurtownia-3" style="margin-top: 10px; margin-bottom: 10px;">' +
                    '<div class="progress-label" id="mhi-import-progress-text-hurtownia-3">Przygotowanie importu...</div>' +
                    '<div class="progress" style="height: 20px; background-color: #f0f0f0; border-radius: 4px; overflow: hidden; margin-top: 5px;">' +
                    '<div id="mhi-import-progress-hurtownia-3" style="width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;"></div>' +
                    '</div>' +
                    '<div class="mhi-import-controls-hurtownia-3" style="margin-top: 10px;">' +
                    '<button id="mhi-pause-import-hurtownia-3" class="button">Wstrzymaj import</button> ' +
                    '<button id="mhi-resume-import-hurtownia-3" class="button" style="display:none;">Wznów import</button> ' +
                    '<button id="mhi-cancel-import-hurtownia-3" class="button">Anuluj import</button> ' +
                    '<button id="mhi-reset-products-hurtownia-3" class="button button-secondary" style="float:right;">Zresetuj stan importu</button>' +
                    '</div></div>');
                $progressContainer = $('#mhi-import-progress-container-hurtownia-3');
                $progressBar = $('#mhi-import-progress-hurtownia-3');
                $progressText = $('#mhi-import-progress-text-hurtownia-3');
            } else {
                $progressContainer.show();
                $progressBar = $('#mhi-import-progress-hurtownia-3');
                $progressText = $('#mhi-import-progress-text-hurtownia-3');
                $progressBar.css('width', '0%');
                $progressText.text('Przygotowanie importu...');

                // Aktualizuj przyciski kontrolne
                if ($('.mhi-import-controls-hurtownia-3').length === 0) {
                    $progressContainer.append('<div class="mhi-import-controls-hurtownia-3" style="margin-top: 10px;">' +
                        '<button id="mhi-pause-import-hurtownia-3" class="button">Wstrzymaj import</button> ' +
                        '<button id="mhi-resume-import-hurtownia-3" class="button" style="display:none;">Wznów import</button> ' +
                        '<button id="mhi-cancel-import-hurtownia-3" class="button">Anuluj import</button> ' +
                        '<button id="mhi-reset-products-hurtownia-3" class="button button-secondary" style="float:right;">Zresetuj stan importu</button>' +
                        '</div>');
                }
            }

            // Sprawdź aktualny stan importu przed rozpoczęciem
            checkImportStatus(function (status, data) {
                if (status === 'paused') {
                    // Import jest wstrzymany - pokaż odpowiednie przyciski
                    $('#mhi-pause-import-hurtownia-3').hide();
                    $('#mhi-resume-import-hurtownia-3').show();
                    $result.html('<div class="notice notice-warning inline"><p>Import jest wstrzymany. Kliknij "Wznów import", aby kontynuować.</p></div>');
                    $button.attr('disabled', false);
                    $spinner.removeClass('is-active');
                } else if (status === 'in_progress') {
                    // Import jest już w trakcie - wznów od miejsca, w którym się zatrzymał
                    $result.html('<div class="notice notice-info inline"><p>Wznawianie importu od ostatniego punktu...</p></div>');
                    startImport(data && data.next_offset ? data.next_offset : 0);
                } else if (status === 'cancelled') {
                    // Import został anulowany - pokaż komunikat
                    $result.html('<div class="notice notice-warning inline"><p>Import został anulowany. Zresetuj stan importu, aby rozpocząć nowy import.</p></div>');
                    $button.attr('disabled', false);
                    $spinner.removeClass('is-active');
                } else {
                    // Normalny początek importu
                    startImport(0);
                }
            });

            // Funkcja sprawdza aktualny stan importu
            function checkImportStatus(callback) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_3',
                        action_type: 'check',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success && response.data) {
                            callback(response.data.status || 'none', response.data);
                        } else {
                            callback('none', null);
                        }
                    },
                    error: function () {
                        callback('none', null);
                    }
                });
            }

            // Funkcja uruchamia import produktów
            function startImport(offset) {
                // Jeśli import jest wstrzymany lub anulowany, zatrzymaj proces
                if (importPaused || importCancelled) {
                    $spinner.removeClass('is-active');
                    $button.attr('disabled', false);
                    return;
                }

                // Wykonaj żądanie AJAX do importu produktów
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_3',
                        offset: offset,
                        batch_size: 5, // Mniejszy batch dla stabilności
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            if (response.data.completed) {
                                // Import zakończony
                                $progressBar.css('width', '100%');
                                $progressText.text('Import zakończony! Przetworzono: ' + response.data.processed + ' z ' + response.data.total + ' produktów.');
                                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                $spinner.removeClass('is-active');
                                $button.attr('disabled', false);

                                // Ukryj przyciski kontrolne
                                $('.mhi-import-controls-hurtownia-3').hide();

                                // Ukryj progress bar po 5 sekundach
                                setTimeout(function () {
                                    $progressContainer.hide();
                                }, 5000);
                            } else {
                                // Import w trakcie
                                const progress = response.data.total > 0 ? Math.round((response.data.processed / response.data.total) * 100) : 0;
                                $progressBar.css('width', progress + '%').addClass('progress-active');
                                $progressText.text('Przetworzono: ' + response.data.processed + ' z ' + response.data.total + ' produktów (' + progress + '%)');

                                // Dodaj aktualizację do wyniku
                                $result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');

                                // Kontynuuj import z następnym offsetem
                                setTimeout(function () {
                                    startImport(response.data.next_offset);
                                }, 500); // Krótka przerwa między batchami
                            }
                        } else {
                            // Błąd importu
                            let errorMessage = 'Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd podczas importu') + '. ';
                            if (response.data && response.data.error_offset) {
                                errorMessage += 'Kliknij "Wznów import", aby kontynuować od miejsca, w którym wystąpił błąd.';
                                // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'mhi_import_products_hurtownia_3',
                                        action_type: 'save_error_point',
                                        error_offset: response.data.error_offset,
                                        nonce: mhi_admin.nonce
                                    },
                                    success: function () {
                                        // Pokaż przycisk wznowienia
                                        importPaused = true;
                                        $('#mhi-pause-import-hurtownia-3').hide();
                                        $('#mhi-resume-import-hurtownia-3').show();
                                    }
                                });
                            } else {
                                errorMessage += 'Spróbuj ponownie klikając "Wznów import".';
                                // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'mhi_import_products_hurtownia_3',
                                        action_type: 'save_error_point',
                                        error_offset: offset,
                                        nonce: mhi_admin.nonce
                                    },
                                    success: function () {
                                        // Pokaż przycisk wznowienia
                                        importPaused = true;
                                        $('#mhi-pause-import-hurtownia-3').hide();
                                        $('#mhi-resume-import-hurtownia-3').show();
                                    }
                                });
                            }
                            $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    },
                    error: function (xhr, status, error) {
                        // Obsługa błędów HTTP
                        let errorMessage = 'Błąd połączenia podczas importu. ';
                        if (xhr.status === 504) {
                            errorMessage += 'Przekroczenie limitu czasu (Gateway Timeout). Kliknij "Wznów import", aby kontynuować od miejsca, w którym wystąpił błąd.';
                            // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mhi_import_products_hurtownia_3',
                                    action_type: 'save_error_point',
                                    error_offset: offset,
                                    nonce: mhi_admin.nonce
                                },
                                success: function () {
                                    // Pokaż przycisk wznowienia
                                    importPaused = true;
                                    $('#mhi-pause-import-hurtownia-3').hide();
                                    $('#mhi-resume-import-hurtownia-3').show();
                                }
                            });
                        } else {
                            errorMessage += 'Kod błędu: ' + xhr.status + '. Spróbuj ponownie klikając "Wznów import".';
                            // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mhi_import_products_hurtownia_3',
                                    action_type: 'save_error_point',
                                    error_offset: offset,
                                    nonce: mhi_admin.nonce
                                },
                                success: function () {
                                    // Pokaż przycisk wznowienia
                                    importPaused = true;
                                    $('#mhi-pause-import-hurtownia-3').hide();
                                    $('#mhi-resume-import-hurtownia-3').show();
                                }
                            });
                        }
                        $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                        $spinner.removeClass('is-active');
                        $button.attr('disabled', false);
                    }
                });
            }

            // Obsługa przycisku wstrzymania importu
            $(document).on('click', '#mhi-pause-import-hurtownia-3', function (e) {
                e.preventDefault();
                importPaused = true;

                // Wyślij żądanie AJAX aby zapisać stan "wstrzymany" w bazie
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_3',
                        action_type: 'pause',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                            $('#mhi-pause-import-hurtownia-3').hide();
                            $('#mhi-resume-import-hurtownia-3').show();
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    }
                });
            });

            // Obsługa przycisku wznowienia importu
            $(document).on('click', '#mhi-resume-import-hurtownia-3', function (e) {
                e.preventDefault();
                importPaused = false;
                $button.attr('disabled', true);
                $spinner.addClass('is-active');

                // Wyślij żądanie AJAX aby wznowić import
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_3',
                        action_type: 'resume',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#mhi-resume-import-hurtownia-3').hide();
                            $('#mhi-pause-import-hurtownia-3').show();
                            $result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
                            startImport(response.data.next_offset || 0);
                        }
                    }
                });
            });

            // Obsługa przycisku anulowania importu
            $(document).on('click', '#mhi-cancel-import-hurtownia-3', function (e) {
                e.preventDefault();
                importCancelled = true;

                // Wyślij żądanie AJAX aby anulować import
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_3',
                        action_type: 'cancel',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                            $progressContainer.hide();
                        }
                    }
                });
            });

            // Obsługa przycisku resetowania stanu importu
            $(document).on('click', '#mhi-reset-products-hurtownia-3', function (e) {
                e.preventDefault();

                // Potwierdź operację
                if (!confirm('Ta operacja zresetuje stan importu, co pozwoli na rozpoczęcie importu od początku. Czy na pewno chcesz kontynuować?')) {
                    return;
                }

                $(this).attr('disabled', true).text('Resetowanie...');

                // Wyślij żądanie AJAX aby zresetować stan importu
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_3',
                        action_type: 'reset',
                        nonce: mhi_admin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            $('#mhi-reset-products-hurtownia-3').attr('disabled', false).text('Zresetuj stan importu');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas resetowania: ' + response.data.message + '</p></div>');
                            $('#mhi-reset-products-hurtownia-3').attr('disabled', false).text('Zresetuj stan importu');
                        }
                    },
                    error: function () {
                        $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas resetowania stanu importu.</p></div>');
                        $('#mhi-reset-products-hurtownia-3').attr('disabled', false).text('Zresetuj stan importu');
                    }
                });
            });
        });

        // Obsługa przycisku importu produktów do WooCommerce z Hurtowni 5 (Macma)
        $('#mhi-import-products-hurtownia-5').on('click', function () {
            const $button = $(this);
            const $spinner = $('#mhi-import-spinner-hurtownia-5');
            const $result = $('#mhi-import-result-hurtownia-5');
            var $progressContainer = $('#mhi-import-progress-container-hurtownia-5');
            var $progressBar = $('#mhi-import-progress-hurtownia-5');
            var $progressText = $('#mhi-import-progress-text-hurtownia-5');
            var importPaused = false;
            var importCancelled = false;

            // Przygotuj UI do importu
            $button.attr('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            // Pokaż progress bar, jeśli istnieje lub utwórz go
            if ($progressContainer.length === 0) {
                $result.after('<div id="mhi-import-progress-container-hurtownia-5" style="margin-top: 10px; margin-bottom: 10px;">' +
                    '<div class="progress-label" id="mhi-import-progress-text-hurtownia-5">Przygotowanie importu...</div>' +
                    '<div class="progress" style="height: 20px; background-color: #f0f0f0; border-radius: 4px; overflow: hidden; margin-top: 5px;">' +
                    '<div id="mhi-import-progress-hurtownia-5" style="width: 0%; height: 100%; background-color: #0073aa; transition: width 0.3s;"></div>' +
                    '</div>' +
                    '<div class="mhi-import-controls-hurtownia-5" style="margin-top: 10px;">' +
                    '<button id="mhi-pause-import-hurtownia-5" class="button">Wstrzymaj import</button> ' +
                    '<button id="mhi-resume-import-hurtownia-5" class="button" style="display:none;">Wznów import</button> ' +
                    '<button id="mhi-cancel-import-hurtownia-5" class="button">Anuluj import</button> ' +
                    '<button id="mhi-reset-products-hurtownia-5" class="button button-secondary" style="float:right;">Zresetuj stan importu</button>' +
                    '</div></div>');
                $progressContainer = $('#mhi-import-progress-container-hurtownia-5');
                $progressBar = $('#mhi-import-progress-hurtownia-5');
                $progressText = $('#mhi-import-progress-text-hurtownia-5');
            } else {
                $progressContainer.show();
                $progressBar.css('width', '0%');
                $progressText.text('Przygotowanie importu...');

                // Aktualizuj przyciski kontrolne
                if ($('.mhi-import-controls-hurtownia-5').length === 0) {
                    $progressContainer.append('<div class="mhi-import-controls-hurtownia-5" style="margin-top: 10px;">' +
                        '<button id="mhi-pause-import-hurtownia-5" class="button">Wstrzymaj import</button> ' +
                        '<button id="mhi-resume-import-hurtownia-5" class="button" style="display:none;">Wznów import</button> ' +
                        '<button id="mhi-cancel-import-hurtownia-5" class="button">Anuluj import</button> ' +
                        '<button id="mhi-reset-products-hurtownia-5" class="button button-secondary" style="float:right;">Zresetuj stan importu</button>' +
                        '</div>');
                }
            }

            // Sprawdź aktualny stan importu przed rozpoczęciem
            checkImportStatusHurtownia5(function (status, data) {
                if (status === 'paused') {
                    // Import jest wstrzymany - pokaż odpowiednie przyciski
                    $('#mhi-pause-import-hurtownia-5').hide();
                    $('#mhi-resume-import-hurtownia-5').show();
                    $result.html('<div class="notice notice-warning inline"><p>Import jest wstrzymany. Kliknij "Wznów import", aby kontynuować.</p></div>');
                    $button.attr('disabled', false);
                    $spinner.removeClass('is-active');
                } else if (status === 'in_progress') {
                    // Import jest już w trakcie - wznów od miejsca, w którym się zatrzymał
                    $result.html('<div class="notice notice-info inline"><p>Wznawianie importu od ostatniego punktu...</p></div>');
                    startImportHurtownia5(data && data.next_offset ? data.next_offset : 0);
                } else if (status === 'cancelled') {
                    // Import został anulowany - pokaż komunikat
                    $result.html('<div class="notice notice-warning inline"><p>Import został anulowany. Zresetuj stan importu, aby rozpocząć nowy import.</p></div>');
                    $button.attr('disabled', false);
                    $spinner.removeClass('is-active');
                } else {
                    // Normalny początek importu
                    startImportHurtownia5(0);
                }
            });

            // Obsługa przycisku wstrzymania importu
            $(document).on('click', '#mhi-pause-import-hurtownia-5', function (e) {
                e.preventDefault();
                importPaused = true;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_5',
                        nonce: mhi_admin.nonce,
                        action_type: 'pause'
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                            $('#mhi-pause-import-hurtownia-5').hide();
                            $('#mhi-resume-import-hurtownia-5').show();
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas wstrzymywania importu: ' + response.data.message + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    },
                    error: function () {
                        $result.html('<div class="notice notice-error inline"><p>Błąd podczas wstrzymywania importu.</p></div>');
                        $spinner.removeClass('is-active');
                        $button.attr('disabled', false);
                    }
                });
            });

            // Obsługa przycisku wznowienia importu
            $(document).on('click', '#mhi-resume-import-hurtownia-5', function (e) {
                e.preventDefault();
                importPaused = false;
                $button.attr('disabled', true);
                $spinner.addClass('is-active');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_5',
                        nonce: mhi_admin.nonce,
                        action_type: 'resume'
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
                            $('#mhi-pause-import-hurtownia-5').show();
                            $('#mhi-resume-import-hurtownia-5').hide();

                            // Kontynuuj import
                            startImportHurtownia5(response.data.next_offset || 0);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas wznawiania importu: ' + response.data.message + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    },
                    error: function () {
                        $result.html('<div class="notice notice-error inline"><p>Błąd podczas wznawiania importu.</p></div>');
                        $spinner.removeClass('is-active');
                        $button.attr('disabled', false);
                    }
                });
            });

            // Obsługa przycisku anulowania importu
            $(document).on('click', '#mhi-cancel-import-hurtownia-5', function (e) {
                e.preventDefault();
                importCancelled = true;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_5',
                        nonce: mhi_admin.nonce,
                        action_type: 'cancel'
                    },
                    success: function (response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                            $button.attr('disabled', false);
                            $spinner.removeClass('is-active');
                            $progressContainer.hide();
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas anulowania importu: ' + response.data.message + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                        }
                    },
                    error: function () {
                        $result.html('<div class="notice notice-error inline"><p>Błąd podczas anulowania importu.</p></div>');
                        $spinner.removeClass('is-active');
                        $button.attr('disabled', false);
                    }
                });
            });

            // Obsługa przycisku resetowania stanu importu
            $(document).on('click', '#mhi-reset-products-hurtownia-5', function (e) {
                e.preventDefault();

                if (confirm('Czy na pewno chcesz zresetować stan importu? To pozwoli na rozpoczęcie nowego importu.')) {
                    $result.html('<div class="notice notice-warning inline"><p>Resetowanie stanu importu...</p></div>');
                    $(this).attr('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mhi_import_products_hurtownia_5',
                            nonce: mhi_admin.nonce,
                            action_type: 'reset'
                        },
                        success: function (response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                $('#mhi-reset-products-hurtownia-5').attr('disabled', false);
                                $button.attr('disabled', false);
                                $spinner.removeClass('is-active');
                                $progressBar.css('width', '0%');
                                $progressText.text('Import został zresetowany. Kliknij "Importuj produkty", aby rozpocząć nowy import.');
                            } else {
                                $result.html('<div class="notice notice-error inline"><p>Błąd podczas resetowania: ' + response.data.message + '</p></div>');
                                $('#mhi-reset-products-hurtownia-5').attr('disabled', false);
                            }
                        },
                        error: function () {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas resetowania danych.</p></div>');
                            $('#mhi-reset-products-hurtownia-5').attr('disabled', false);
                        }
                    });
                }
            });

            function checkImportStatusHurtownia5(callback) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_5',
                        nonce: mhi_admin.nonce,
                        action_type: 'check'
                    },
                    success: function (response) {
                        if (response.success) {
                            callback(response.data.status, response.data);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Błąd podczas sprawdzania statusu importu: ' + response.data.message + '</p></div>');
                            $button.attr('disabled', false);
                            $spinner.removeClass('is-active');
                            callback('none', null);
                        }
                    },
                    error: function () {
                        $result.html('<div class="notice notice-error inline"><p>Błąd podczas sprawdzania statusu importu.</p></div>');
                        $button.attr('disabled', false);
                        $spinner.removeClass('is-active');
                        callback('none', null);
                    }
                });
            }

            function startImportHurtownia5(offset) {
                // Zatrzymaj, jeśli import został wstrzymany lub anulowany
                if (importPaused || importCancelled) {
                    $spinner.removeClass('is-active');
                    $button.attr('disabled', false);
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_5',
                        nonce: mhi_admin.nonce,
                        offset: offset,
                        batch_size: 10
                    },
                    success: function (response) {
                        if (response.success) {
                            // Aktualizuj pasek postępu
                            var progress = Math.round((response.data.processed / response.data.total) * 100);
                            $progressBar.css('width', progress + '%');
                            $progressText.text('Przetworzono ' + response.data.processed + ' z ' + response.data.total + ' produktów (' + progress + '%)');

                            // Aktualizuj statystyki importu
                            var statsHtml = '<div class="mhi-import-stats" style="margin-top: 10px;">' +
                                '<strong>Statystyki:</strong><br>' +
                                'Utworzono: ' + response.data.stats.created + '<br>' +
                                'Zaktualizowano: ' + response.data.stats.updated + '<br>' +
                                'Pominięto: ' + response.data.stats.skipped + '<br>' +
                                'Pominięto duplikaty: ' + response.data.stats.duplicates_skipped + '<br>' +
                                'Błędy: ' + response.data.stats.errors + '<br>' +
                                '</div>';

                            // Dodaj lub aktualizuj statystyki
                            if ($('.mhi-import-stats').length === 0) {
                                $progressContainer.append(statsHtml);
                            } else {
                                $('.mhi-import-stats').replaceWith(statsHtml);
                            }

                            // Sprawdź, czy import został zakończony
                            if (response.data.completed) {
                                $progressBar.css('width', '100%');
                                $progressText.text('Import zakończony. Przetworzono ' + response.data.total + ' produktów.');
                                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                $button.attr('disabled', false);
                                $spinner.removeClass('is-active');
                                // Ukryj przyciski kontrolne po zakończeniu
                                $('.mhi-import-controls-hurtownia-5').hide();
                            } else {
                                // Kontynuuj import następnej partii
                                setTimeout(function () {
                                    startImportHurtownia5(response.data.next_offset);
                                }, 500); // Krótka przerwa między batchami
                            }
                        } else {
                            // Obsługa błędu
                            $result.html('<div class="notice notice-error inline"><p>Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd podczas importu') + '</p></div>');
                            $button.attr('disabled', false);
                            $spinner.removeClass('is-active');

                            // Spróbuj zapisać punkt, w którym wystąpił błąd
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mhi_import_products_hurtownia_5',
                                    nonce: mhi_admin.nonce,
                                    action_type: 'save_error_point',
                                    error_offset: offset
                                },
                                success: function () {
                                    // Pokaż przycisk wznowienia
                                    importPaused = true;
                                    $('#mhi-pause-import-hurtownia-5').hide();
                                    $('#mhi-resume-import-hurtownia-5').show();
                                }
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        // Obsługa błędu AJAX
                        var errorMessage = 'Błąd podczas importu: ' + (error || 'nieznany błąd');
                        if (xhr.status === 504) {
                            errorMessage += ' (Timeout). Kliknij "Wznów import", aby kontynuować.';
                        }

                        $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                        $button.attr('disabled', false);
                        $spinner.removeClass('is-active');

                        // Spróbuj zapisać punkt, w którym wystąpił błąd
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mhi_import_products_hurtownia_5',
                                nonce: mhi_admin.nonce,
                                action_type: 'save_error_point',
                                error_offset: offset
                            },
                            success: function () {
                                // Pokaż przycisk wznowienia
                                importPaused = true;
                                $('#mhi-pause-import-hurtownia-5').hide();
                                $('#mhi-resume-import-hurtownia-5').show();
                            }
                        });
                    }
                });
            }
        });
    });
})(jQuery); 
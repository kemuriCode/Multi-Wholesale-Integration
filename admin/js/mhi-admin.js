jQuery(document).ready(function ($) {
    // Status pobierania
    var downloadStatus = {};
    var downloadIntervals = {};

    // Obsługa przycisku pobierania plików
    $('.mhi-fetch-files-button').on('click', function (e) {
        e.preventDefault();
        var hurtowniaId = $(this).data('hurtownia');
        updateStatus(hurtowniaId, 'Rozpoczynam pobieranie plików...');

        // Pokaż przyciski anulowania
        showCancelButton(hurtowniaId);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_fetch_files',
                hurtownia_id: hurtowniaId,
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    startStatusCheck(hurtowniaId);
                } else {
                    updateStatus(hurtowniaId, 'Błąd: ' + response.data.message);
                    hideCancelButton(hurtowniaId);
                }
            },
            error: function () {
                updateStatus(hurtowniaId, 'Błąd połączenia z serwerem.');
                hideCancelButton(hurtowniaId);
            }
        });
    });

    // Obsługa przycisku pobierania zdjęć
    $('.mhi-fetch-images-button').on('click', function (e) {
        e.preventDefault();
        var hurtowniaId = $(this).data('hurtownia');
        startImageDownload(hurtowniaId, 1);
    });

    // Obsługa przycisku anulowania pobierania
    $(document).on('click', '.mhi-cancel-download-button', function (e) {
        e.preventDefault();
        var hurtowniaId = $(this).data('hurtownia');
        cancelDownload(hurtowniaId);
    });

    // Obsługa przycisku importu produktów do WooCommerce dla hurtowni 3 (PAR)
    $(document).on('click', '#mhi-import-products-hurtownia-3', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-3');
        var $result = $('#mhi-import-result-hurtownia-3');
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
        }

        // Sprawdź aktualny stan importu przed rozpoczęciem
        checkImportStatusHurtownia3(function (status, data) {
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
                startImportHurtownia3(data && data.next_offset ? data.next_offset : 0);
            } else if (status === 'cancelled') {
                // Import został anulowany - pokaż komunikat
                $result.html('<div class="notice notice-warning inline"><p>Import został anulowany. Zresetuj stan importu, aby rozpocząć nowy import.</p></div>');
                $button.attr('disabled', false);
                $spinner.removeClass('is-active');
            } else {
                // Normalny początek importu
                startImportHurtownia3(0);
            }
        });
    });

    // Obsługa przycisku wstrzymania importu dla hurtowni 3
    $(document).on('click', '#mhi-pause-import-hurtownia-3', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-3');
        var $result = $('#mhi-import-result-hurtownia-3');

        $button.attr('disabled', true);

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
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Błąd podczas wstrzymywania importu: ' + response.data.message + '</p></div>');
                }
                $button.attr('disabled', false);
            },
            error: function () {
                $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas wstrzymywania importu.</p></div>');
                $button.attr('disabled', false);
            }
        });
    });

    // Obsługa przycisku wznowienia importu dla hurtowni 3
    $(document).on('click', '#mhi-resume-import-hurtownia-3', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-3');
        var $result = $('#mhi-import-result-hurtownia-3');

        $button.attr('disabled', true);
        $spinner.addClass('is-active');

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
                    startImportHurtownia3(response.data.next_offset || 0);
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Błąd: ' + response.data.message + '</p></div>');
                    $spinner.removeClass('is-active');
                    $button.attr('disabled', false);
                }
            },
            error: function () {
                $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas wznawiania importu.</p></div>');
                $spinner.removeClass('is-active');
                $button.attr('disabled', false);
            }
        });
    });

    // Obsługa przycisku anulowania importu dla hurtowni 3
    $(document).on('click', '#mhi-cancel-import-hurtownia-3', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-3');
        var $result = $('#mhi-import-result-hurtownia-3');

        $button.attr('disabled', true);

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
                    $('#mhi-pause-import-hurtownia-3').attr('disabled', false);
                    $('#mhi-import-products-hurtownia-3').attr('disabled', false);
                    $spinner.removeClass('is-active');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Błąd podczas anulowania importu: ' + response.data.message + '</p></div>');
                }
                $button.attr('disabled', false);
            },
            error: function () {
                $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas anulowania importu.</p></div>');
                $button.attr('disabled', false);
            }
        });
    });

    // Obsługa przycisku resetowania importu dla hurtowni 3
    $(document).on('click', '#mhi-reset-products-hurtownia-3', function (e) {
        e.preventDefault();

        if (!confirm('Ta operacja zresetuje stan importu, co pozwoli na rozpoczęcie importu od początku. Czy na pewno chcesz kontynuować?')) {
            return;
        }

        var $button = $(this);
        var $result = $('#mhi-import-result-hurtownia-3');
        $button.attr('disabled', true).text('Resetowanie...');

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
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Błąd podczas resetowania: ' + response.data.message + '</p></div>');
                }
                $button.attr('disabled', false).text('Zresetuj stan importu');
                $('#mhi-import-products-hurtownia-3').attr('disabled', false);
            },
            error: function () {
                $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas resetowania stanu importu.</p></div>');
                $button.attr('disabled', false).text('Zresetuj stan importu');
            }
        });
    });

    // Obsługa przycisku importu produktów do WooCommerce z Hurtowni 5 (Macma)
    $(document).on('click', '#mhi-import-products-hurtownia-5', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-5');
        var $result = $('#mhi-import-result-hurtownia-5');
        var $progressContainer = $('#mhi-import-progress-container-hurtownia-5');
        var $progressBar, $progressText;
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
            $progressBar = $('#mhi-import-progress-hurtownia-5');
            $progressText = $('#mhi-import-progress-text-hurtownia-5');
            $progressBar.css('width', '0%');
            $progressText.text('Przygotowanie importu...');
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
    });

    // Obsługa przycisku wstrzymania importu dla hurtowni 5
    $(document).on('click', '#mhi-pause-import-hurtownia-5', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-5');
        var $result = $('#mhi-import-result-hurtownia-5');

        $button.attr('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_import_products_hurtownia_5',
                action_type: 'pause',
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result.html('<div class="notice notice-warning inline"><p>' + response.data.message + '</p></div>');
                    $('#mhi-pause-import-hurtownia-5').hide();
                    $('#mhi-resume-import-hurtownia-5').show();
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Błąd podczas wstrzymywania importu: ' + response.data.message + '</p></div>');
                }
                $button.attr('disabled', false);
                $spinner.removeClass('is-active');
            },
            error: function () {
                $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas wstrzymywania importu.</p></div>');
                $button.attr('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Obsługa przycisku wznowienia importu dla hurtowni 5
    $(document).on('click', '#mhi-resume-import-hurtownia-5', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $spinner = $('#mhi-import-spinner-hurtownia-5');
        var $result = $('#mhi-import-result-hurtownia-5');

        $button.attr('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_import_products_hurtownia_5',
                action_type: 'resume',
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#mhi-resume-import-hurtownia-5').hide();
                    $('#mhi-pause-import-hurtownia-5').show();
                    $result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
                    startImportHurtownia5(response.data.next_offset || 0);
                } else {
                    $result.html('<div class="notice notice-error inline"><p>Błąd: ' + response.data.message + '</p></div>');
                    $spinner.removeClass('is-active');
                    $button.attr('disabled', false);
                }
            },
            error: function () {
                $result.html('<div class="notice notice-error inline"><p>Błąd połączenia podczas wznawiania importu.</p></div>');
                $spinner.removeClass('is-active');
                $button.attr('disabled', false);
            }
        });
    });

    // Funkcja sprawdza aktualny stan importu dla hurtowni 3
    function checkImportStatusHurtownia3(callback) {
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

    // Funkcja sprawdza aktualny stan importu dla hurtowni 5
    function checkImportStatusHurtownia5(callback) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_import_products_hurtownia_5',
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

    // Funkcja uruchamia import produktów dla hurtowni 3
    function startImportHurtownia3(offset) {
        var $button = $('#mhi-import-products-hurtownia-3');
        var $spinner = $('#mhi-import-spinner-hurtownia-3');
        var $result = $('#mhi-import-result-hurtownia-3');
        var $progressContainer = $('#mhi-import-progress-container-hurtownia-3');
        var $progressBar = $('#mhi-import-progress-hurtownia-3');
        var $progressText = $('#mhi-import-progress-text-hurtownia-3');

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

                        // Sprawdź, czy import został wstrzymany
                        checkImportStatusHurtownia3(function (status) {
                            if (status !== 'paused' && status !== 'cancelled') {
                                // Kontynuuj import z następnym offsetem tylko jeśli nie wstrzymano ani nie anulowano
                                setTimeout(function () {
                                    startImportHurtownia3(response.data.next_offset);
                                }, 500); // Krótka przerwa między batchami
                            } else if (status === 'paused') {
                                $result.html('<div class="notice notice-warning inline"><p>Import został wstrzymany.</p></div>');
                                $spinner.removeClass('is-active');
                                $('#mhi-pause-import-hurtownia-3').hide();
                                $('#mhi-resume-import-hurtownia-3').show();
                            } else if (status === 'cancelled') {
                                $result.html('<div class="notice notice-warning inline"><p>Import został anulowany.</p></div>');
                                $spinner.removeClass('is-active');
                                $button.attr('disabled', false);
                                $progressContainer.hide();
                            }
                        });
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
                } else {
                    errorMessage += 'Kod błędu: ' + xhr.status + '. Spróbuj ponownie klikając "Wznów import".';
                }

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
                        $('#mhi-pause-import-hurtownia-3').hide();
                        $('#mhi-resume-import-hurtownia-3').show();
                    }
                });

                $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                $spinner.removeClass('is-active');
                $button.attr('disabled', false);
            }
        });
    }

    // Funkcja uruchamia import produktów dla hurtowni 5
    function startImportHurtownia5(offset) {
        var $button = $('#mhi-import-products-hurtownia-5');
        var $spinner = $('#mhi-import-spinner-hurtownia-5');
        var $result = $('#mhi-import-result-hurtownia-5');
        var $progressContainer = $('#mhi-import-progress-container-hurtownia-5');
        var $progressBar = $('#mhi-import-progress-hurtownia-5');
        var $progressText = $('#mhi-import-progress-text-hurtownia-5');

        // Wykonaj żądanie AJAX do importu produktów
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_import_products_hurtownia_5',
                offset: offset,
                batch_size: 10,
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Sprawdź, czy import został wstrzymany lub anulowany
                    checkImportStatusHurtownia5(function (status) {
                        if (status === 'paused') {
                            $result.html('<div class="notice notice-warning inline"><p>Import został wstrzymany.</p></div>');
                            $spinner.removeClass('is-active');
                            $('#mhi-pause-import-hurtownia-5').hide();
                            $('#mhi-resume-import-hurtownia-5').show();
                            return;
                        } else if (status === 'cancelled') {
                            $result.html('<div class="notice notice-warning inline"><p>Import został anulowany.</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);
                            $progressContainer.hide();
                            return;
                        }

                        // Kontynuuj normalną obsługę tylko jeśli nie wstrzymano ani nie anulowano
                        if (response.data.completed) {
                            // Import zakończony
                            $progressBar.css('width', '100%');
                            $progressText.text('Import zakończony! Przetworzono: ' + response.data.processed + ' z ' + response.data.total + ' produktów.');
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            $spinner.removeClass('is-active');
                            $button.attr('disabled', false);

                            // Ukryj przyciski kontrolne
                            $('.mhi-import-controls-hurtownia-5').hide();

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

                            // Aktualizuj statystyki importu
                            var statsHtml = '<div class="mhi-import-stats" style="margin-top: 10px;">' +
                                '<strong>Statystyki:</strong><br>' +
                                'Utworzono: ' + response.data.stats.created + '<br>' +
                                'Zaktualizowano: ' + response.data.stats.updated + '<br>' +
                                'Pominięto: ' + response.data.stats.skipped + '<br>' +
                                'Błędy: ' + response.data.stats.errors + '<br>' +
                                '</div>';

                            // Dodaj lub aktualizuj statystyki
                            if ($('.mhi-import-stats').length === 0) {
                                $progressContainer.append(statsHtml);
                            } else {
                                $('.mhi-import-stats').replaceWith(statsHtml);
                            }

                            // Kontynuuj import z następnym offsetem
                            setTimeout(function () {
                                startImportHurtownia5(response.data.next_offset);
                            }, 500); // Krótka przerwa między batchami
                        }
                    });
                } else {
                    // Błąd importu
                    let errorMessage = 'Błąd: ' + (response.data ? response.data.message : 'Nieznany błąd podczas importu') + '. ';

                    // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mhi_import_products_hurtownia_5',
                            action_type: 'save_error_point',
                            error_offset: offset,
                            nonce: mhi_admin.nonce
                        },
                        success: function () {
                            // Pokaż przycisk wznowienia
                            $('#mhi-pause-import-hurtownia-5').hide();
                            $('#mhi-resume-import-hurtownia-5').show();
                            $result.html('<div class="notice notice-error inline"><p>' + errorMessage + 'Kliknij "Wznów import", aby kontynuować od miejsca, w którym wystąpił błąd.</p></div>');
                        }
                    });

                    $spinner.removeClass('is-active');
                    $button.attr('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                // Obsługa błędów HTTP
                let errorMessage = 'Błąd połączenia podczas importu. ';
                if (xhr.status === 504) {
                    errorMessage += 'Przekroczenie limitu czasu (Gateway Timeout). ';
                } else {
                    errorMessage += 'Kod błędu: ' + xhr.status + '. ';
                }
                errorMessage += 'Kliknij "Wznów import", aby kontynuować od miejsca, w którym wystąpił błąd.';

                // Zapisz stan importu jako wstrzymany z ostatnim offsetem
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mhi_import_products_hurtownia_5',
                        action_type: 'save_error_point',
                        error_offset: offset,
                        nonce: mhi_admin.nonce
                    },
                    success: function () {
                        // Pokaż przycisk wznowienia
                        $('#mhi-pause-import-hurtownia-5').hide();
                        $('#mhi-resume-import-hurtownia-5').show();
                    }
                });

                $result.html('<div class="notice notice-error inline"><p>' + errorMessage + '</p></div>');
                $spinner.removeClass('is-active');
                $button.attr('disabled', false);
            }
        });
    }

    // Funkcja rozpoczynająca pobieranie zdjęć
    function startImageDownload(hurtowniaId, batchNumber) {
        updateStatus(hurtowniaId, 'Rozpoczynam pobieranie zdjęć (partia ' + batchNumber + ')...');

        // Pokaż przyciski anulowania
        showCancelButton(hurtowniaId);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_fetch_images_batch',
                hurtownia_id: hurtowniaId,
                batch_number: batchNumber,
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateStatus(hurtowniaId, response.data.status);

                    // Sprawdź, czy są jeszcze partie do pobrania
                    if (response.data.remaining_batches > 0) {
                        // Pobierz następną partię po krótkim opóźnieniu
                        setTimeout(function () {
                            startImageDownload(hurtowniaId, batchNumber + 1);
                        }, 1000);
                    } else {
                        // Zakończono pobieranie wszystkich partii
                        updateStatus(hurtowniaId, 'Zakończono pobieranie zdjęć.');
                        hideCancelButton(hurtowniaId);
                    }
                } else {
                    updateStatus(hurtowniaId, 'Błąd: ' + response.data.message);
                    hideCancelButton(hurtowniaId);
                }
            },
            error: function () {
                updateStatus(hurtowniaId, 'Błąd połączenia z serwerem.');
                hideCancelButton(hurtowniaId);
            }
        });
    }

    // Funkcja anulująca pobieranie
    function cancelDownload(hurtowniaId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_cancel_download',
                hurtownia_id: hurtowniaId,
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateStatus(hurtowniaId, 'Anulowano pobieranie.');
                    stopStatusCheck(hurtowniaId);
                    hideCancelButton(hurtowniaId);
                } else {
                    updateStatus(hurtowniaId, 'Błąd: ' + response.data.message);
                }
            },
            error: function () {
                updateStatus(hurtowniaId, 'Błąd podczas anulowania pobierania.');
            }
        });
    }

    // Funkcja rozpoczynająca sprawdzanie statusu
    function startStatusCheck(hurtowniaId) {
        // Zatrzymaj poprzedni interval, jeśli istnieje
        stopStatusCheck(hurtowniaId);

        // Sprawdź status natychmiast
        checkDownloadStatus(hurtowniaId);

        // Ustaw interval na sprawdzanie statusu co 2 sekundy
        downloadIntervals[hurtowniaId] = setInterval(function () {
            checkDownloadStatus(hurtowniaId);
        }, 2000);
    }

    // Funkcja zatrzymująca sprawdzanie statusu
    function stopStatusCheck(hurtowniaId) {
        if (downloadIntervals[hurtowniaId]) {
            clearInterval(downloadIntervals[hurtowniaId]);
            delete downloadIntervals[hurtowniaId];
        }
    }

    // Funkcja sprawdzająca status pobierania
    function checkDownloadStatus(hurtowniaId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhi_check_download_status',
                hurtownia_id: hurtowniaId,
                nonce: mhi_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateStatus(hurtowniaId, response.data.status);

                    // Jeśli status zawiera 'Zakończono' lub 'Anulowano', zatrzymaj sprawdzanie
                    if (response.data.status.indexOf('Zakończono') !== -1 ||
                        response.data.status.indexOf('Anulowano') !== -1) {
                        stopStatusCheck(hurtowniaId);
                        hideCancelButton(hurtowniaId);
                    }
                }
            }
        });
    }

    // Funkcja aktualizująca status pobierania
    function updateStatus(hurtowniaId, status) {
        $('.mhi-download-status[data-hurtownia="' + hurtowniaId + '"]').text(status);
        downloadStatus[hurtowniaId] = status;
    }

    // Funkcja pokazująca przycisk anulowania pobierania
    function showCancelButton(hurtowniaId) {
        var $cancelButton = $('.mhi-cancel-download-button[data-hurtownia="' + hurtowniaId + '"]');

        // Jeśli przycisk nie istnieje, utwórz go
        if ($cancelButton.length === 0) {
            var $statusContainer = $('.mhi-download-status[data-hurtownia="' + hurtowniaId + '"]').parent();
            $cancelButton = $('<button class="button mhi-cancel-download-button" data-hurtownia="' + hurtowniaId + '">Anuluj pobieranie</button>');
            $statusContainer.append($cancelButton);
        } else {
            $cancelButton.show();
        }
    }

    // Funkcja ukrywająca przycisk anulowania pobierania
    function hideCancelButton(hurtowniaId) {
        $('.mhi-cancel-download-button[data-hurtownia="' + hurtowniaId + '"]').hide();
    }
}); 
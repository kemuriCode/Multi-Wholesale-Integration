// **NOWE FUNKCJE AI** - ZOPTYMALIZOWANE PRZECIWKO TIMEOUT

// Inteligentna analiza AI z timeout protection
$('#intelligent-ai-analysis-btn').on('click', function (e) {
    e.preventDefault();

    const $button = $(this);
    const $results = $('#intelligent-analysis-results');
    const $progress = $('#intelligent-analysis-progress');
    const contextDescription = $('#ai-context-description').val() || '';

    // Sprawdź czy przycisk nie jest już w trakcie działania
    if ($button.prop('disabled')) {
        return;
    }

    $button.prop('disabled', true);
    $button.html('<span class="spinner is-active"></span> 🚀 Wykonuję szybką analizę AI...');
    $results.hide();
    $progress.show().html(`
            <div class="notice notice-info">
                <p><strong>⚡ SZYBKA ANALIZA AI W TOKU...</strong></p>
                <p>🔍 Analizuję próbkę produktów (max 80)</p>
                <p>🧠 Optymalizacja przeciwko timeout</p>
                <p>⏱️ Czas: 2-4 minuty max</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="animation: progress-animation 180s linear;"></div>
                </div>
            </div>
        `);

    // Timeout protection - przerwij po 4 minutach
    const timeoutId = setTimeout(() => {
        console.warn('⚠️ Timeout protection aktywny - przerywam żądanie');
        $button.prop('disabled', false);
        $button.html('🧠 Inteligentna Analiza AI');
        $progress.hide();
        showNotification('⚠️ Operacja przekroczyła bezpieczny limit czasu (4 min). Spróbuj ponownie z mniejszymi danymi.', 'warning');
    }, 240000); // 4 minuty

    $.ajax({
        url: mhi_ajax.ajax_url,
        type: 'POST',
        timeout: 240000, // 4 minuty timeout
        data: {
            action: 'mhi_intelligent_ai_analysis',
            nonce: mhi_ajax.nonce,
            context_description: contextDescription
        },
        success: function (response) {
            clearTimeout(timeoutId);

            if (response.success) {
                const analysisData = response.data.analysis_result;
                const performance = response.data.performance;

                let resultHtml = `
                        <div class="notice notice-success">
                            <h3>✅ ZOPTYMALIZOWANA ANALIZA AI ZAKOŃCZONA!</h3>
                            <p><strong>Typ:</strong> ${performance.type}</p>
                            <p><strong>Tokeny użyte:</strong> ${performance.tokens_used}</p>
                            <p><strong>Czas przetwarzania:</strong> ${performance.processing_time}</p>
                            <p><strong>Optymalizacja:</strong> ${performance.optimization_applied ? '✅ Zastosowana' : '❌ Brak'}</p>
                        </div>
                    `;

                // Wyświetl wyniki quick analysis
                if (analysisData.quick_analysis) {
                    resultHtml += `
                            <div class="ai-analysis-section">
                                <h4>📊 Szybka Analiza Próbki</h4>
                                <ul>
                                    ${(analysisData.quick_analysis.main_insights || []).map(insight => `<li><strong>💡 ${insight}</strong></li>`).join('')}
                                    ${(analysisData.quick_analysis.category_problems || []).map(problem => `<li><span style="color: orange;">⚠️ ${problem}</span></li>`).join('')}
                                    ${(analysisData.quick_analysis.quick_recommendations || []).map(rec => `<li><span style="color: green;">✅ ${rec}</span></li>`).join('')}
                                </ul>
                            </div>
                        `;
                }

                // Wyświetl strukturę
                if (analysisData.quick_structure && analysisData.quick_structure.proposed_structure) {
                    resultHtml += '<div class="ai-analysis-section"><h4>🏗️ Proponowana Struktura Kategorii</h4>';
                    const mainCategories = analysisData.quick_structure.proposed_structure.main_categories || [];

                    mainCategories.forEach(category => {
                        resultHtml += `
                                <div class="category-proposal">
                                    <h5>📁 ${category.name}</h5>
                                    <p>${category.description || ''}</p>
                            `;

                        if (category.subcategories && category.subcategories.length > 0) {
                            resultHtml += '<ul>';
                            category.subcategories.forEach(sub => {
                                resultHtml += `<li>📄 ${sub.name} - ${sub.description || ''}</li>`;
                            });
                            resultHtml += '</ul>';
                        }

                        resultHtml += '</div>';
                    });

                    resultHtml += '</div>';
                }

                $results.html(resultHtml).show();
                showNotification(response.data.message, 'success');
            } else {
                console.error('Błąd analizy AI:', response.data);
                $results.html(`
                        <div class="notice notice-error">
                            <h4>❌ Błąd podczas analizy</h4>
                            <p>${response.data.message}</p>
                            <p><strong>Typ błędu:</strong> ${response.data.type || 'nieznany'}</p>
                        </div>
                    `).show();
                showNotification('❌ ' + response.data.message, 'error');
            }
        },
        error: function (xhr, status, error) {
            clearTimeout(timeoutId);
            console.error('AJAX Error:', status, error);

            let errorMessage = 'Błąd komunikacji z serwerem';
            if (status === 'timeout') {
                errorMessage = '⏱️ Przekroczono limit czasu (4 min). Analiza AI była zbyt długa.';
            } else if (xhr.status === 504) {
                errorMessage = '🌐 Gateway Timeout - serwer nie odpowiedział w czasie. Spróbuj z mniejszymi danymi.';
            } else if (xhr.status === 502) {
                errorMessage = '🔧 Bad Gateway - problem z konfiguracją serwera.';
            }

            $results.html(`
                    <div class="notice notice-error">
                        <h4>❌ Błąd systemowy</h4>
                        <p>${errorMessage}</p>
                        <p><strong>Status:</strong> ${status} (${xhr.status})</p>
                        <p><strong>Szczegóły:</strong> ${error}</p>
                        <hr>
                        <p><strong>💡 Rozwiązania:</strong></p>
                        <ul>
                            <li>Odczekaj kilka minut i spróbuj ponownie</li>
                            <li>Sprawdź czy klucz OpenAI API jest poprawny</li>
                            <li>Skontaktuj się z administratorem</li>
                        </ul>
                    </div>
                `).show();
            showNotification('❌ ' + errorMessage, 'error');
        },
        complete: function () {
            clearTimeout(timeoutId);
            $button.prop('disabled', false);
            $button.html('🧠 Inteligentna Analiza AI');
            $progress.hide();
        }
    });
});

// CSS dla progress bar
if (!$('#ai-progress-styles').length) {
    $('head').append(`
            <style id="ai-progress-styles">
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background-color: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                    margin: 10px 0;
                }
                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #007cba, #00a0d2);
                    width: 0%;
                    border-radius: 10px;
                }
                @keyframes progress-animation {
                    0% { width: 0%; }
                    50% { width: 70%; }
                    100% { width: 95%; }
                }
            </style>
        `);
} 
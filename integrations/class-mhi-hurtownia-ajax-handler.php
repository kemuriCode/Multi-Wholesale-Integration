/**
* AJAX: Inteligentna dwufazowa analiza AI - ZOPTYMALIZOWANA
*/
public function handle_intelligent_ai_analysis()
{
check_ajax_referer('mhi_hurtownia_ajax_nonce', 'nonce');

if (!current_user_can('manage_options')) {
wp_die('Brak uprawnień');
}

try {
// TIMEOUT PROTECTION - zwiększ limit czasu
set_time_limit(300); // 5 minut max
ini_set('max_execution_time', 300);

// Optymalizacja pamięci
ini_set('memory_limit', '512M');

$context_description = sanitize_text_field($_POST['context_description'] ?? '');
$analyzer = new MHI_AI_Category_Analyzer();

// Sprawdź czy AI jest skonfigurowane
if (!$analyzer->is_ai_configured()) {
wp_send_json_error([
'message' => '❌ Klucz API OpenAI nie został skonfigurowany. Ustaw klucz w ustawieniach wtyczki.',
'type' => 'configuration_error'
]);
return;
}

// Niestandardowe ustawienia optymalizacji
$custom_settings = [
'max_products_sample' => 80, // Zmniejszono z 100
'max_tokens' => 6000, // Zmniejszono z 8000
'timeout_protection' => true,
'quick_mode' => true
];

$this->logger->info('🚀 ROZPOCZĘCIE ZOPTYMALIZOWANEJ ANALIZY AI (AJAX)');

// Wykonaj szybką analizę
$result = $analyzer->intelligent_two_phase_analysis($context_description, $custom_settings);

if ($result['success']) {
$this->logger->info('✅ ZOPTYMALIZOWANA ANALIZA AJAX ZAKOŃCZONA POMYŚLNIE');

wp_send_json_success([
'message' => '✅ Inteligentna analiza AI została zakończona pomyślnie!',
'analysis_result' => $result['data'],
'performance' => [
'type' => 'optimized_quick',
'tokens_used' => $result['data']['ai_metadata']['total_tokens_used'] ?? 0,
'processing_time' => number_format($result['data']['ai_metadata']['processing_time'] ?? 0, 2) . 's',
'optimization_applied' => true
]
]);
} else {
$this->logger->error('❌ Błąd podczas zoptymalizowanej analizy: ' . $result['error']);

wp_send_json_error([
'message' => '❌ Błąd podczas analizy: ' . $result['error'],
'type' => 'analysis_error'
]);
}

} catch (Exception $e) {
$this->logger->error('❌ Błąd AJAX analizy AI: ' . $e->getMessage());

wp_send_json_error([
'message' => '❌ Błąd podczas analizy AI: ' . $e->getMessage(),
'type' => 'system_error'
]);
}
}

/**
* AJAX: Szybkie mapowanie produktów bez kategorii
*/
public function handle_map_uncategorized_products()
{
check_ajax_referer('mhi_hurtownia_ajax_nonce', 'nonce');

if (!current_user_can('manage_options')) {
wp_die('Brak uprawnień');
}

try {
// TIMEOUT PROTECTION dla mapowania
set_time_limit(180); // 3 minuty max
ini_set('max_execution_time', 180);

$analyzer = new MHI_AI_Category_Analyzer();

if (!$analyzer->is_ai_configured()) {
wp_send_json_error([
'message' => '❌ Klucz API OpenAI nie został skonfigurowany.',
'type' => 'configuration_error'
]);
return;
}

$this->logger->info('🎯 ROZPOCZĘCIE SZYBKIEGO MAPOWANIA PRODUKTÓW');

// Szybkie mapowanie z ograniczeniami
$result = $analyzer->map_uncategorized_products([
'max_products_per_batch' => 20, // Mniejsze batche
'quick_mode' => true,
'timeout_protection' => true
]);

if ($result['success']) {
wp_send_json_success([
'message' => '✅ Mapowanie produktów zakończone pomyślnie!',
'mapping_result' => $result['data'],
'stats' => [
'products_mapped' => $result['data']['products_mapped'] ?? 0,
'categories_used' => $result['data']['categories_used'] ?? 0,
'processing_time' => $result['data']['processing_time'] ?? 'N/A'
]
]);
} else {
wp_send_json_error([
'message' => '❌ Błąd podczas mapowania: ' . $result['error'],
'type' => 'mapping_error'
]);
}

} catch (Exception $e) {
$this->logger->error('❌ Błąd AJAX mapowania: ' . $e->getMessage());

wp_send_json_error([
'message' => '❌ Błąd systemowy: ' . $e->getMessage(),
'type' => 'system_error'
]);
}
}
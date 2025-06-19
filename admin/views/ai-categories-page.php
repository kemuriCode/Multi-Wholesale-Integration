<?php
/**
 * Szablon strony AI Kategorie
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Inicjalizacja klasy AI analizera
try {
    $ai_analyzer = new MHI_AI_Category_Analyzer();
} catch (Exception $e) {
    echo '<div class="notice notice-error"><p>Błąd inicjalizacji AI: ' . esc_html($e->getMessage()) . '</p></div>';
    return;
}

// Obsługa formularzy
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mhi_ai_test_connection']) && wp_verify_nonce($_POST['mhi_ai_test_nonce'], 'mhi_ai_test_connection')) {
        $test_result = $ai_analyzer->test_api_connection();
        if ($test_result['success']) {
            add_settings_error('mhi_ai_categories', 'test_success', $test_result['message'], 'success');
        } else {
            add_settings_error('mhi_ai_categories', 'test_error', 'Błąd połączenia: ' . $test_result['error'], 'error');
        }
    }

    if (isset($_POST['mhi_ai_analyze_categories']) && wp_verify_nonce($_POST['mhi_ai_analyze_nonce'], 'mhi_ai_analyze_categories')) {
        $context_description = sanitize_textarea_field($_POST['context_description'] ?? '');
        $analysis_result = $ai_analyzer->analyze_categories($context_description);

        if ($analysis_result['success']) {
            add_settings_error('mhi_ai_categories', 'analysis_success', 'Analiza kategorii zakończona pomyślnie! Użyto ' . $analysis_result['tokens_used'] . ' tokenów.', 'success');
        } else {
            add_settings_error('mhi_ai_categories', 'analysis_error', 'Błąd analizy: ' . $analysis_result['error'], 'error');
        }
    }

    if (isset($_POST['mhi_ai_create_backup']) && wp_verify_nonce($_POST['mhi_ai_backup_nonce'], 'mhi_ai_create_backup')) {
        if ($ai_analyzer->create_backup()) {
            add_settings_error('mhi_ai_categories', 'backup_success', 'Kopia zapasowa kategorii została utworzona pomyślnie!', 'success');
        } else {
            add_settings_error('mhi_ai_categories', 'backup_error', 'Błąd podczas tworzenia kopii zapasowej.', 'error');
        }
    }

    if (isset($_POST['mhi_ai_restore_backup']) && wp_verify_nonce($_POST['mhi_ai_restore_nonce'], 'mhi_ai_restore_backup')) {
        $backup_version = (int) $_POST['backup_version'];
        if ($ai_analyzer->restore_backup($backup_version)) {
            add_settings_error('mhi_ai_categories', 'restore_success', 'Kopia zapasowa została przywrócona pomyślnie!', 'success');
        } else {
            add_settings_error('mhi_ai_categories', 'restore_error', 'Błąd podczas przywracania kopii zapasowej.', 'error');
        }
    }

    if (isset($_POST['mhi_ai_implement_changes']) && wp_verify_nonce($_POST['mhi_ai_implement_nonce'], 'mhi_ai_implement_changes')) {
        $latest_analysis = $ai_analyzer->get_latest_analysis();
        if ($latest_analysis && isset($latest_analysis['result']['migration_plan'])) {
            $implementation_result = $ai_analyzer->implement_changes($latest_analysis['result']['migration_plan']);

            if ($implementation_result['success']) {
                $message = 'Zmiany zostały zaimplementowane pomyślnie! Wykonane zmiany: ' . count($implementation_result['changes_made']);
                add_settings_error('mhi_ai_categories', 'implement_success', $message, 'success');
            } else {
                $message = 'Błędy podczas implementacji: ' . implode(', ', $implementation_result['errors']);
                add_settings_error('mhi_ai_categories', 'implement_error', $message, 'error');
            }
        }
    }
}

// Pobranie danych
$latest_analysis = $ai_analyzer->get_latest_analysis();
$backups = get_option('mhi_category_backups', []);
$api_key = get_option('mhi_openai_api_key', '');
?>

<div class="mhi-ai-categories-wrapper">

    <!-- Sekcja: Ustawienia OpenAI -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-admin-network"></span>
                <?php _e('Ustawienia OpenAI API', 'multi-hurtownie-integration'); ?></h2>
        </div>
        <div class="inside">
            <?php if (empty($api_key)): ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php _e('Uwaga:', 'multi-hurtownie-integration'); ?></strong>
                        <?php _e('Klucz API OpenAI nie został skonfigurowany. Wprowadź klucz API poniżej.', 'multi-hurtownie-integration'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('mhi_openai_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label
                                for="mhi_openai_api_key"><?php _e('Klucz API OpenAI', 'multi-hurtownie-integration'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="mhi_openai_api_key" name="mhi_openai_api_key"
                                value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Wprowadź klucz API z', 'multi-hurtownie-integration'); ?>
                                <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label
                                for="mhi_openai_model"><?php _e('Model AI', 'multi-hurtownie-integration'); ?></label>
                        </th>
                        <td>
                            <select id="mhi_openai_model" name="mhi_openai_model">
                                <option value="gpt-4o" <?php selected(get_option('mhi_openai_model', 'gpt-4o'), 'gpt-4o'); ?>>GPT-4o (Zalecany)</option>
                                <option value="gpt-4o-mini" <?php selected(get_option('mhi_openai_model'), 'gpt-4o-mini'); ?>>GPT-4o Mini (Tańszy)</option>
                                <option value="gpt-4-turbo" <?php selected(get_option('mhi_openai_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('mhi_openai_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Ekonomiczny)</option>
                            </select>
                            <p class="description">
                                <?php _e('Wybierz model AI do analizy kategorii. GPT-4o jest zalecany dla najlepszych wyników.', 'multi-hurtownie-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label
                                for="mhi_openai_max_tokens"><?php _e('Maksymalne tokeny', 'multi-hurtownie-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="mhi_openai_max_tokens" name="mhi_openai_max_tokens"
                                value="<?php echo esc_attr(get_option('mhi_openai_max_tokens', 4000)); ?>" min="1000"
                                max="8000" class="small-text" />
                            <p class="description">
                                <?php _e('Maksymalna liczba tokenów w odpowiedzi AI (1000-8000).', 'multi-hurtownie-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label
                                for="mhi_openai_temperature"><?php _e('Temperatura', 'multi-hurtownie-integration'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="mhi_openai_temperature" name="mhi_openai_temperature"
                                value="<?php echo esc_attr(get_option('mhi_openai_temperature', 0.3)); ?>" min="0"
                                max="1" step="0.1" class="small-text" />
                            <p class="description">
                                <?php _e('Kontroluje kreatywność AI (0.0 = konserwatywny, 1.0 = kreatywny). Zalecane: 0.3', 'multi-hurtownie-integration'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Zapisz ustawienia', 'multi-hurtownie-integration')); ?>
            </form>

            <!-- Test połączenia -->
            <div class="mhi-test-connection"
                style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                <h3><?php _e('Test połączenia z OpenAI', 'multi-hurtownie-integration'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('mhi_ai_test_connection', 'mhi_ai_test_nonce'); ?>
                    <input type="submit" name="mhi_ai_test_connection" class="button button-secondary"
                        value="<?php _e('Testuj połączenie', 'multi-hurtownie-integration'); ?>">
                </form>
            </div>
        </div>
    </div>

    <!-- Sekcja: Analiza kategorii -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-admin-generic"></span>
                <?php _e('Analiza kategorii przez AI', 'multi-hurtownie-integration'); ?></h2>
        </div>
        <div class="inside">
            <div class="notice notice-info inline">
                <p><strong><?php _e('Jak to działa:', 'multi-hurtownie-integration'); ?></strong></p>
                <ul style="margin-left: 20px;">
                    <li><?php _e('AI analizuje wszystkie kategorie i przykładowe produkty', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('Identyfikuje podobne kategorie i proponuje scalenia', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('Tworzy czytelną hierarchię kategorii', 'multi-hurtownie-integration'); ?></li>
                    <li><?php _e('Eliminuje redundantne kategorie', 'multi-hurtownie-integration'); ?></li>
                    <li><?php _e('Uwzględnia SEO i doświadczenie użytkownika', 'multi-hurtownie-integration'); ?></li>
                </ul>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('mhi_ai_analyze_categories', 'mhi_ai_analyze_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label
                                for="context_description"><?php _e('Opis kontekstu sklepu', 'multi-hurtownie-integration'); ?></label>
                        </th>
                        <td>
                            <textarea id="context_description" name="context_description" rows="4" cols="50"
                                class="large-text">
Sklep internetowy specjalizujący się w artykułach promocyjnych i reklamowych. 
Oferujemy produkty takie jak: odzież promocyjna, gadżety reklamowe, akcesoria biurowe, 
artykuły sportowe, torby i plecaki, kubki i termosy, akcesoria elektroniczne, 
oraz usługi personalizacji (nadruki, grawerowanie, haft).

Nasi klienci to głównie firmy poszukujące materiałów promocyjnych, 
ale obsługujemy też klientów indywidualnych.
                            </textarea>
                            <p class="description">
                                <?php _e('Opisz czym zajmuje się Twój sklep. To pomoże AI lepiej zrozumieć kontekst i zaproponować odpowiednie kategorie.', 'multi-hurtownie-integration'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="mhi-analysis-buttons">
                    <input type="submit" name="mhi_ai_analyze_categories" class="button button-primary button-large"
                        value="<?php _e('🤖 Rozpocznij analizę AI', 'multi-hurtownie-integration'); ?>" <?php echo empty($api_key) ? 'disabled' : ''; ?>>

                    <?php if (empty($api_key)): ?>
                        <p class="description" style="color: #d63638;">
                            <?php _e('Skonfiguruj klucz API OpenAI aby móc uruchomić analizę.', 'multi-hurtownie-integration'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Sekcja: Wyniki analizy -->
    <?php if ($latest_analysis): ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><span class="dashicons dashicons-chart-pie"></span>
                    <?php _e('Wyniki analizy AI', 'multi-hurtownie-integration'); ?></h2>
            </div>
            <div class="inside">
                <div class="mhi-analysis-meta">
                    <p><strong><?php _e('Data analizy:', 'multi-hurtownie-integration'); ?></strong>
                        <?php echo esc_html($latest_analysis['timestamp']); ?></p>
                    <p><strong><?php _e('Status:', 'multi-hurtownie-integration'); ?></strong>
                        <span class="status-<?php echo esc_attr($latest_analysis['status']); ?>">
                            <?php echo esc_html(ucfirst($latest_analysis['status'])); ?>
                        </span>
                    </p>
                </div>

                <?php if (isset($latest_analysis['result']['analysis'])): ?>
                    <!-- Problemy i rekomendacje -->
                    <div class="mhi-analysis-section">
                        <h3><?php _e('🔍 Zidentyfikowane problemy', 'multi-hurtownie-integration'); ?></h3>
                        <ul class="mhi-issues-list">
                            <?php foreach ($latest_analysis['result']['analysis']['current_issues'] as $issue): ?>
                                <li class="issue-item">❌ <?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="mhi-analysis-section">
                        <h3><?php _e('💡 Rekomendacje AI', 'multi-hurtownie-integration'); ?></h3>
                        <ul class="mhi-recommendations-list">
                            <?php foreach ($latest_analysis['result']['analysis']['recommendations'] as $recommendation): ?>
                                <li class="recommendation-item">✅ <?php echo esc_html($recommendation); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Proponowana struktura -->
                    <div class="mhi-analysis-section">
                        <h3><?php _e('🏗️ Proponowana struktura kategorii', 'multi-hurtownie-integration'); ?></h3>
                        <div class="mhi-proposed-structure">
                            <?php foreach ($latest_analysis['result']['proposed_structure']['main_categories'] as $main_cat): ?>
                                <div class="main-category-item">
                                    <h4>📁 <?php echo esc_html($main_cat['name']); ?></h4>
                                    <p class="category-description"><?php echo esc_html($main_cat['description']); ?></p>

                                    <?php if (!empty($main_cat['subcategories'])): ?>
                                        <ul class="subcategories-list">
                                            <?php foreach ($main_cat['subcategories'] as $subcat): ?>
                                                <li class="subcategory-item">
                                                    <strong>📂 <?php echo esc_html($subcat['name']); ?></strong>
                                                    <p><?php echo esc_html($subcat['description']); ?></p>
                                                    <?php if (!empty($subcat['merge_from'])): ?>
                                                        <p class="merge-info">
                                                            <em><?php _e('Scalenie z:', 'multi-hurtownie-integration'); ?>
                                                                <?php echo esc_html(implode(', ', $subcat['merge_from'])); ?></em>
                                                        </p>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Plan migracji -->
                    <div class="mhi-analysis-section">
                        <h3><?php _e('🔄 Plan zmian', 'multi-hurtownie-integration'); ?></h3>

                        <?php if (!empty($latest_analysis['result']['migration_plan']['categories_to_merge'])): ?>
                            <div class="migration-section">
                                <h4><?php _e('Kategorie do scalenia:', 'multi-hurtownie-integration'); ?></h4>
                                <ul class="merge-list">
                                    <?php foreach ($latest_analysis['result']['migration_plan']['categories_to_merge'] as $merge): ?>
                                        <li class="merge-item">
                                            <strong><?php echo esc_html($merge['target']); ?></strong>
                                            ← <?php echo esc_html(implode(', ', $merge['sources'])); ?>
                                            <br><em><?php echo esc_html($merge['reason']); ?></em>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($latest_analysis['result']['migration_plan']['new_categories'])): ?>
                            <div class="migration-section">
                                <h4><?php _e('Nowe kategorie do utworzenia:', 'multi-hurtownie-integration'); ?></h4>
                                <ul class="new-categories-list">
                                    <?php foreach ($latest_analysis['result']['migration_plan']['new_categories'] as $new_cat): ?>
                                        <li>➕ <?php echo esc_html($new_cat); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($latest_analysis['result']['migration_plan']['categories_to_delete'])): ?>
                            <div class="migration-section">
                                <h4><?php _e('Kategorie do usunięcia:', 'multi-hurtownie-integration'); ?></h4>
                                <ul class="delete-categories-list">
                                    <?php foreach ($latest_analysis['result']['migration_plan']['categories_to_delete'] as $del_cat): ?>
                                        <li>🗑️ <?php echo esc_html($del_cat); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Przyciski akcji -->
                    <div class="mhi-action-buttons">
                        <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                            <?php wp_nonce_field('mhi_ai_implement_changes', 'mhi_ai_implement_nonce'); ?>
                            <input type="submit" name="mhi_ai_implement_changes" class="button button-primary button-large"
                                value="<?php _e('✅ Wdróż zmiany', 'multi-hurtownie-integration'); ?>"
                                onclick="return confirm('<?php _e('Czy na pewno chcesz wdrożyć te zmiany? Zostanie automatycznie utworzona kopia zapasowa.', 'multi-hurtownie-integration'); ?>');">
                        </form>

                        <button type="button" class="button button-secondary" onclick="mhi_export_analysis()">
                            <?php _e('📊 Eksportuj analizę', 'multi-hurtownie-integration'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sekcja: Zarządzanie kopiami zapasowymi -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-backup"></span>
                <?php _e('Zarządzanie kopiami zapasowymi', 'multi-hurtownie-integration'); ?></h2>
        </div>
        <div class="inside">
            <div class="notice notice-info inline">
                <p><?php _e('Kopie zapasowe pozwalają na bezpieczne testowanie zmian w strukturze kategorii i łatwe przywracanie poprzedniego stanu.', 'multi-hurtownie-integration'); ?>
                </p>
            </div>

            <!-- Tworzenie kopii zapasowej -->
            <div class="backup-section">
                <h3><?php _e('Utwórz nową kopię zapasową', 'multi-hurtownie-integration'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('mhi_ai_create_backup', 'mhi_ai_backup_nonce'); ?>
                    <input type="submit" name="mhi_ai_create_backup" class="button button-secondary"
                        value="<?php _e('💾 Utwórz kopię zapasową', 'multi-hurtownie-integration'); ?>">
                </form>
            </div>

            <!-- Lista kopii zapasowych -->
            <?php if (!empty($backups)): ?>
                <div class="backups-list" style="margin-top: 20px;">
                    <h3><?php _e('Dostępne kopie zapasowe', 'multi-hurtownie-integration'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Wersja', 'multi-hurtownie-integration'); ?></th>
                                <th><?php _e('Data utworzenia', 'multi-hurtownie-integration'); ?></th>
                                <th><?php _e('Liczba kategorii', 'multi-hurtownie-integration'); ?></th>
                                <th><?php _e('Akcje', 'multi-hurtownie-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($backups) as $backup): ?>
                                <tr>
                                    <td>#<?php echo esc_html($backup['version']); ?></td>
                                    <td><?php echo esc_html($backup['timestamp']); ?></td>
                                    <td><?php echo count($backup['categories']); ?></td>
                                    <td>
                                        <form method="post" action="" style="display: inline;">
                                            <?php wp_nonce_field('mhi_ai_restore_backup', 'mhi_ai_restore_nonce'); ?>
                                            <input type="hidden" name="backup_version"
                                                value="<?php echo esc_attr($backup['version']); ?>">
                                            <input type="submit" name="mhi_ai_restore_backup" class="button button-small"
                                                value="<?php _e('Przywróć', 'multi-hurtownie-integration'); ?>"
                                                onclick="return confirm('<?php _e('Czy na pewno chcesz przywrócić tę kopię zapasową? Obecna struktura kategorii zostanie zastąpiona.', 'multi-hurtownie-integration'); ?>');">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p><em><?php _e('Brak dostępnych kopii zapasowych.', 'multi-hurtownie-integration'); ?></em></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sekcja: Import/Export -->
    <div class="postbox">
        <div class="postbox-header">
            <h2><span class="dashicons dashicons-upload"></span>
                <?php _e('Import / Export ustawień', 'multi-hurtownie-integration'); ?></h2>
        </div>
        <div class="inside">
            <div class="import-export-section">
                <div class="export-section" style="float: left; width: 48%;">
                    <h3><?php _e('Export ustawień', 'multi-hurtownie-integration'); ?></h3>
                    <p><?php _e('Eksportuj wszystkie ustawienia AI, analizy i kopie zapasowe do pliku JSON.', 'multi-hurtownie-integration'); ?>
                    </p>
                    <button type="button" class="button button-secondary" onclick="mhi_export_settings()">
                        <?php _e('📥 Eksportuj ustawienia', 'multi-hurtownie-integration'); ?>
                    </button>
                </div>

                <div class="import-section" style="float: right; width: 48%;">
                    <h3><?php _e('Import ustawień', 'multi-hurtownie-integration'); ?></h3>
                    <p><?php _e('Importuj ustawienia z pliku JSON.', 'multi-hurtownie-integration'); ?></p>
                    <form method="post" enctype="multipart/form-data" action="">
                        <?php wp_nonce_field('mhi_ai_import_settings', 'mhi_ai_import_nonce'); ?>
                        <input type="file" name="settings_file" accept=".json" required>
                        <br><br>
                        <input type="submit" name="mhi_ai_import_settings" class="button button-secondary"
                            value="<?php _e('📤 Importuj ustawienia', 'multi-hurtownie-integration'); ?>">
                    </form>
                </div>
                <div style="clear: both;"></div>
            </div>
        </div>
    </div>

</div>

<!-- Styl CSS -->
<style>
    .mhi-ai-categories-wrapper .postbox {
        margin-bottom: 20px;
    }

    .mhi-analysis-section {
        margin: 20px 0;
        padding: 15px;
        background: #f9f9f9;
        border-left: 4px solid #0073aa;
    }

    .mhi-issues-list,
    .mhi-recommendations-list {
        list-style: none;
        padding: 0;
    }

    .issue-item,
    .recommendation-item {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }

    .main-category-item {
        margin: 15px 0;
        padding: 15px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .subcategories-list {
        margin-left: 20px;
        list-style: none;
    }

    .subcategory-item {
        margin: 10px 0;
        padding: 10px;
        background: #f0f8ff;
        border-left: 3px solid #0073aa;
    }

    .merge-info {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .migration-section {
        margin: 15px 0;
    }

    .merge-item {
        margin: 10px 0;
        padding: 10px;
        background: #fff3cd;
        border-left: 3px solid #ffc107;
    }

    .mhi-action-buttons {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }

    .status-pending {
        color: #856404;
        background-color: #fff3cd;
        padding: 2px 8px;
        border-radius: 3px;
    }

    .status-completed {
        color: #155724;
        background-color: #d4edda;
        padding: 2px 8px;
        border-radius: 3px;
    }

    .backup-section {
        margin: 15px 0;
    }

    .import-export-section::after {
        content: "";
        display: table;
        clear: both;
    }
</style>

<!-- JavaScript -->
<script>
    function mhi_export_analysis() {
        <?php if ($latest_analysis): ?>
            const analysis = <?php echo json_encode($latest_analysis['result']); ?>;
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(analysis, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "ai-category-analysis-" + new Date().toISOString().slice(0, 10) + ".json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        <?php else: ?>
            alert('<?php _e('Brak analizy do eksportu. Najpierw przeprowadź analizę.', 'multi-hurtownie-integration'); ?>');
        <?php endif; ?>
    }

    function mhi_export_settings() {
        // Wywołanie AJAX do eksportu ustawień
        const formData = new FormData();
        formData.append('action', 'mhi_export_ai_settings');
        formData.append('nonce', '<?php echo wp_create_nonce('mhi_export_ai_settings'); ?>');

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data.data, null, 2));
                    const downloadAnchorNode = document.createElement('a');
                    downloadAnchorNode.setAttribute("href", dataStr);
                    downloadAnchorNode.setAttribute("download", "mhi-ai-settings-" + new Date().toISOString().slice(0, 10) + ".json");
                    document.body.appendChild(downloadAnchorNode);
                    downloadAnchorNode.click();
                    downloadAnchorNode.remove();
                } else {
                    alert('<?php _e('Błąd podczas eksportu ustawień.', 'multi-hurtownie-integration'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?php _e('Błąd podczas eksportu ustawień.', 'multi-hurtownie-integration'); ?>');
            });
    }
</script>
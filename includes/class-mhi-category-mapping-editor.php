<?php
/**
 * Edytor mapowania kategorii - interfejs do edycji propozycji AI
 *
 * @package MHI
 */

declare(strict_types=1);

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Category_Mapping_Editor
 * 
 * Zarządza interfejsem edycji propozycji kategorii od AI
 */
class MHI_Category_Mapping_Editor
{
    /**
     * Logger
     *
     * @var MHI_Logger
     */
    private $logger;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->logger = new MHI_Logger();
        add_action('wp_ajax_mhi_save_category_mapping', [$this, 'save_category_mapping']);
        add_action('wp_ajax_mhi_load_category_mapping', [$this, 'load_category_mapping']);
        add_action('wp_ajax_mhi_preview_mapping_changes', [$this, 'preview_mapping_changes']);
        add_action('wp_ajax_mhi_add_custom_category', [$this, 'add_custom_category']);
        add_action('wp_ajax_mhi_delete_mapping_item', [$this, 'delete_mapping_item']);
    }

    /**
     * Renderuje edytor mapowania kategorii
     *
     * @param array $ai_proposal Propozycja AI
     * @return string HTML edytora
     */
    public function render_mapping_editor(array $ai_proposal): string
    {
        if (empty($ai_proposal)) {
            return '<div class="notice notice-error"><p>Brak propozycji AI do edycji. Wykonaj najpierw analizę kategorii.</p></div>';
        }

        ob_start();
        ?>
        <div id="mhi-category-mapping-editor" class="mhi-mapping-editor">
            <div class="mhi-editor-header">
                <h2>📝 Edytor Mapowania Kategorii</h2>
                <p>Edytuj propozycje AI, dodawaj własne kategorie i twórz mapowanie produktów.</p>

                <div class="mhi-editor-actions">
                    <button type="button" class="button button-primary" id="mhi-save-mapping">
                        💾 Zapisz Mapowanie
                    </button>
                    <button type="button" class="button" id="mhi-preview-changes">
                        👁 Podgląd Zmian
                    </button>
                    <button type="button" class="button" id="mhi-add-new-category">
                        ➕ Dodaj Kategorię
                    </button>
                    <button type="button" class="button" id="mhi-reset-to-ai">
                        🔄 Przywróć Propozycję AI
                    </button>
                </div>
            </div>

            <div class="mhi-editor-content">
                <div class="mhi-editor-sidebar">
                    <div class="mhi-sidebar-section">
                        <h3>🎯 Obecne Kategorie</h3>
                        <div id="mhi-current-categories">
                            <?php echo $this->render_current_categories(); ?>
                        </div>
                    </div>

                    <div class="mhi-sidebar-section">
                        <h3>📊 Statystyki</h3>
                        <div id="mhi-mapping-stats">
                            <?php echo $this->render_mapping_statistics($ai_proposal); ?>
                        </div>
                    </div>
                </div>

                <div class="mhi-editor-main">
                    <div class="mhi-tabs">
                        <nav class="mhi-tab-nav">
                            <button class="mhi-tab-button active" data-tab="structure">🏗 Struktura</button>
                            <button class="mhi-tab-button" data-tab="mapping">🔗 Mapowanie</button>
                            <button class="mhi-tab-button" data-tab="new-categories">➕ Nowe Kategorie</button>
                            <button class="mhi-tab-button" data-tab="migration">📦 Migracja</button>
                        </nav>

                        <div class="mhi-tab-content active" id="mhi-tab-structure">
                            <?php echo $this->render_structure_editor($ai_proposal); ?>
                        </div>

                        <div class="mhi-tab-content" id="mhi-tab-mapping">
                            <?php echo $this->render_mapping_editor_tab($ai_proposal); ?>
                        </div>

                        <div class="mhi-tab-content" id="mhi-tab-new-categories">
                            <?php echo $this->render_new_categories_tab($ai_proposal); ?>
                        </div>

                        <div class="mhi-tab-content" id="mhi-tab-migration">
                            <?php echo $this->render_migration_tab($ai_proposal); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mhi-editor-footer">
                <div class="mhi-changes-summary" id="mhi-changes-summary">
                    <strong>Podsumowanie zmian:</strong> <span id="mhi-changes-count">0 zmian</span>
                </div>
            </div>
        </div>

        <style>
            .mhi-mapping-editor {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin: 20px 0;
            }

            .mhi-editor-header {
                padding: 20px;
                border-bottom: 1px solid #ccd0d4;
                background: #f9f9f9;
            }

            .mhi-editor-header h2 {
                margin: 0 0 10px 0;
                color: #23282d;
            }

            .mhi-editor-actions {
                margin-top: 15px;
            }

            .mhi-editor-actions .button {
                margin-right: 10px;
            }

            .mhi-editor-content {
                display: flex;
                min-height: 600px;
            }

            .mhi-editor-sidebar {
                width: 300px;
                border-right: 1px solid #ccd0d4;
                background: #fafafa;
            }

            .mhi-sidebar-section {
                padding: 20px;
                border-bottom: 1px solid #ccd0d4;
            }

            .mhi-sidebar-section h3 {
                margin: 0 0 15px 0;
                color: #23282d;
                font-size: 14px;
                font-weight: 600;
            }

            .mhi-editor-main {
                flex: 1;
                padding: 20px;
            }

            .mhi-tab-nav {
                display: flex;
                border-bottom: 1px solid #ccd0d4;
                margin-bottom: 20px;
            }

            .mhi-tab-button {
                background: none;
                border: none;
                padding: 10px 20px;
                cursor: pointer;
                border-bottom: 2px solid transparent;
                font-weight: 500;
            }

            .mhi-tab-button.active {
                border-bottom-color: #0073aa;
                color: #0073aa;
            }

            .mhi-tab-content {
                display: none;
            }

            .mhi-tab-content.active {
                display: block;
            }

            .mhi-editor-footer {
                padding: 15px 20px;
                border-top: 1px solid #ccd0d4;
                background: #f9f9f9;
            }

            .mhi-category-item {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 10px;
                padding: 15px;
            }

            .mhi-category-item.draggable {
                cursor: move;
            }

            .mhi-category-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .mhi-category-name {
                font-weight: 600;
                color: #23282d;
            }

            .mhi-category-actions {
                display: flex;
                gap: 5px;
            }

            .mhi-category-actions button {
                padding: 2px 8px;
                font-size: 12px;
                line-height: 1.4;
            }

            .mhi-subcategories {
                margin-left: 20px;
                border-left: 2px solid #0073aa;
                padding-left: 15px;
            }

            .mhi-mapping-item {
                display: flex;
                align-items: center;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 5px;
                background: #fff;
            }

            .mhi-mapping-arrow {
                margin: 0 15px;
                color: #0073aa;
                font-weight: bold;
            }

            .mhi-product-count {
                background: #0073aa;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                margin-left: 10px;
            }

            .mhi-changes-summary {
                color: #666;
                font-size: 14px;
            }

            .mhi-form-field {
                margin-bottom: 15px;
            }

            .mhi-form-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }

            .mhi-form-field input,
            .mhi-form-field select,
            .mhi-form-field textarea {
                width: 100%;
                max-width: 400px;
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                // Inicjalizacja edytora
                window.MHI_CategoryMappingEditor = {
                    init: function () {
                        this.bindEvents();
                        this.loadMapping();
                    },

                    bindEvents: function () {
                        // Przełączanie zakładek
                        $('.mhi-tab-button').on('click', function () {
                            const tab = $(this).data('tab');
                            $('.mhi-tab-button').removeClass('active');
                            $('.mhi-tab-content').removeClass('active');
                            $(this).addClass('active');
                            $('#mhi-tab-' + tab).addClass('active');
                        });

                        // Zapisywanie mapowania
                        $('#mhi-save-mapping').on('click', this.saveMapping);

                        // Podgląd zmian
                        $('#mhi-preview-changes').on('click', this.previewChanges);

                        // Dodawanie nowych kategorii
                        $('#mhi-add-new-category').on('click', this.addNewCategory);

                        // Reset do propozycji AI
                        $('#mhi-reset-to-ai').on('click', this.resetToAI);
                    },

                    saveMapping: function () {
                        const mappingData = window.MHI_CategoryMappingEditor.collectMappingData();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mhi_save_category_mapping',
                                mapping_data: mappingData,
                                nonce: '<?php echo wp_create_nonce('mhi_mapping_nonce'); ?>'
                            },
                            success: function (response) {
                                if (response.success) {
                                    alert('✅ Mapowanie zostało zapisane pomyślnie!');
                                    location.reload();
                                } else {
                                    alert('❌ Błąd podczas zapisywania: ' + response.data);
                                }
                            }
                        });
                    },

                    collectMappingData: function () {
                        // Implementacja zbierania danych z formularza
                        return {
                            structure: this.collectStructureData(),
                            mapping: this.collectMappingRules(),
                            new_categories: this.collectNewCategories(),
                            migration_plan: this.collectMigrationPlan()
                        };
                    },

                    // Pozostałe metody...
                };

                // Inicjalizacja
                window.MHI_CategoryMappingEditor.init();
            });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Renderuje edytor struktury kategorii
     */
    private function render_structure_editor(array $ai_proposal): string
    {
        ob_start();
        ?>
        <div class="mhi-structure-editor">
            <h3>🏗 Struktura Kategorii</h3>
            <p>Przeciągnij i upuść kategorie aby zmienić strukturę. Kliknij na kategorię aby ją edytować.</p>

            <div id="mhi-category-tree">
                <?php echo $this->render_category_tree($ai_proposal); ?>
            </div>

            <div class="mhi-structure-actions">
                <button type="button" class="button" id="mhi-expand-all">📂 Rozwiń Wszystkie</button>
                <button type="button" class="button" id="mhi-collapse-all">📁 Zwiń Wszystkie</button>
                <button type="button" class="button" id="mhi-auto-sort">🔤 Sortuj Alfabetycznie</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje drzewo kategorii
     */
    private function render_category_tree(array $ai_proposal): string
    {
        if (!isset($ai_proposal['proposed_structure']['main_categories'])) {
            return '<p>Brak struktury kategorii do wyświetlenia.</p>';
        }

        $categories = $ai_proposal['proposed_structure']['main_categories'];

        ob_start();
        echo '<div class="mhi-category-tree">';

        foreach ($categories as $index => $category) {
            echo $this->render_category_item($category, $index, 0);
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renderuje pojedynczy element kategorii
     */
    private function render_category_item(array $category, int $index, int $level): string
    {
        $indent = str_repeat('    ', $level);
        $category_id = 'category_' . $level . '_' . $index;

        ob_start();
        ?>
        <div class="mhi-category-item draggable" data-category-id="<?php echo $category_id; ?>"
            data-level="<?php echo $level; ?>">
            <div class="mhi-category-header">
                <div class="mhi-category-info">
                    <span class="mhi-category-name"><?php echo esc_html($category['name']); ?></span>
                    <?php if (isset($category['expected_product_count'])): ?>
                        <span class="mhi-product-count"><?php echo esc_html($category['expected_product_count']); ?>
                            produktów</span>
                    <?php endif; ?>
                </div>
                <div class="mhi-category-actions">
                    <button type="button" class="button button-small mhi-edit-category"
                        data-category-id="<?php echo $category_id; ?>">
                        ✏ Edytuj
                    </button>
                    <button type="button" class="button button-small mhi-add-subcategory"
                        data-category-id="<?php echo $category_id; ?>">
                        ➕ Podkategoria
                    </button>
                    <button type="button" class="button button-small mhi-delete-category"
                        data-category-id="<?php echo $category_id; ?>">
                        🗑 Usuń
                    </button>
                </div>
            </div>

            <?php if (!empty($category['description'])): ?>
                <div class="mhi-category-description">
                    <?php echo esc_html($category['description']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($category['subcategories']) && !empty($category['subcategories'])): ?>
                <div class="mhi-subcategories">
                    <?php foreach ($category['subcategories'] as $sub_index => $subcategory): ?>
                        <?php echo $this->render_category_item($subcategory, $sub_index, $level + 1); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje zakładkę mapowania
     */
    private function render_mapping_editor_tab(array $ai_proposal): string
    {
        ob_start();
        ?>
        <div class="mhi-mapping-tab">
            <h3>🔗 Mapowanie Produktów</h3>
            <p>Skonfiguruj jak produkty będą przypisywane do nowych kategorii.</p>

            <div class="mhi-mapping-rules">
                <?php echo $this->render_mapping_rules($ai_proposal); ?>
            </div>

            <div class="mhi-mapping-actions">
                <button type="button" class="button" id="mhi-add-mapping-rule">➕ Dodaj Regułę</button>
                <button type="button" class="button" id="mhi-test-mapping">🧪 Testuj Mapowanie</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje reguły mapowania
     */
    private function render_mapping_rules(array $ai_proposal): string
    {
        if (!isset($ai_proposal['migration_plan']['categories_to_merge'])) {
            return '<p>Brak reguł mapowania.</p>';
        }

        $mapping_rules = $ai_proposal['migration_plan']['categories_to_merge'];

        ob_start();
        foreach ($mapping_rules as $index => $rule) {
            ?>
            <div class="mhi-mapping-item" data-rule-index="<?php echo $index; ?>">
                <div class="mhi-mapping-source">
                    <strong>Źródło:</strong><br>
                    <?php echo implode(', ', array_map('esc_html', $rule['sources'])); ?>
                </div>
                <div class="mhi-mapping-arrow">→</div>
                <div class="mhi-mapping-target">
                    <strong>Cel:</strong><br>
                    <?php echo esc_html($rule['target']); ?>
                </div>
                <div class="mhi-mapping-info">
                    <?php if (isset($rule['estimated_products'])): ?>
                        <span class="mhi-product-count"><?php echo esc_html($rule['estimated_products']); ?></span>
                    <?php endif; ?>
                    <button type="button" class="button button-small mhi-edit-mapping" data-rule-index="<?php echo $index; ?>">
                        ✏ Edytuj
                    </button>
                    <button type="button" class="button button-small mhi-delete-mapping" data-rule-index="<?php echo $index; ?>">
                        🗑 Usuń
                    </button>
                </div>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Renderuje obecne kategorie w sidebarze
     */
    private function render_current_categories(): string
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 20
        ]);

        ob_start();
        foreach ($categories as $category) {
            ?>
            <div class="mhi-current-category-item" data-category-id="<?php echo $category->term_id; ?>">
                <div class="mhi-category-name"><?php echo esc_html($category->name); ?></div>
                <div class="mhi-category-count"><?php echo $category->count; ?> produktów</div>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Renderuje statystyki mapowania
     */
    private function render_mapping_statistics(array $ai_proposal): string
    {
        $stats = [
            'proposed_categories' => 0,
            'categories_to_merge' => 0,
            'categories_to_delete' => 0,
            'new_categories' => 0
        ];

        if (isset($ai_proposal['proposed_structure']['main_categories'])) {
            $stats['proposed_categories'] = count($ai_proposal['proposed_structure']['main_categories']);
        }

        if (isset($ai_proposal['migration_plan'])) {
            $plan = $ai_proposal['migration_plan'];
            $stats['categories_to_merge'] = isset($plan['categories_to_merge']) ? count($plan['categories_to_merge']) : 0;
            $stats['categories_to_delete'] = isset($plan['categories_to_delete']) ? count($plan['categories_to_delete']) : 0;
            $stats['new_categories'] = isset($plan['new_categories']) ? count($plan['new_categories']) : 0;
        }

        ob_start();
        ?>
        <div class="mhi-mapping-stats">
            <div class="mhi-stat-item">
                <div class="mhi-stat-number"><?php echo $stats['proposed_categories']; ?></div>
                <div class="mhi-stat-label">Proponowane kategorie</div>
            </div>
            <div class="mhi-stat-item">
                <div class="mhi-stat-number"><?php echo $stats['categories_to_merge']; ?></div>
                <div class="mhi-stat-label">Do scalenia</div>
            </div>
            <div class="mhi-stat-item">
                <div class="mhi-stat-number"><?php echo $stats['new_categories']; ?></div>
                <div class="mhi-stat-label">Nowe kategorie</div>
            </div>
            <div class="mhi-stat-item">
                <div class="mhi-stat-number"><?php echo $stats['categories_to_delete']; ?></div>
                <div class="mhi-stat-label">Do usunięcia</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje zakładkę nowych kategorii
     */
    private function render_new_categories_tab(array $ai_proposal): string
    {
        ob_start();
        ?>
        <div class="mhi-new-categories-tab">
            <h3>➕ Nowe Kategorie</h3>
            <p>Dodaj własne kategorie lub edytuj te proponowane przez AI.</p>

            <form id="mhi-new-category-form">
                <div class="mhi-form-field">
                    <label for="new-category-name">Nazwa kategorii:</label>
                    <input type="text" id="new-category-name" name="name" required>
                </div>

                <div class="mhi-form-field">
                    <label for="new-category-parent">Kategoria nadrzędna:</label>
                    <select id="new-category-parent" name="parent">
                        <option value="">Główna kategoria</option>
                        <?php echo $this->render_category_options($ai_proposal); ?>
                    </select>
                </div>

                <div class="mhi-form-field">
                    <label for="new-category-description">Opis:</label>
                    <textarea id="new-category-description" name="description" rows="3"></textarea>
                </div>

                <button type="submit" class="button button-primary">➕ Dodaj Kategorię</button>
            </form>

            <div id="mhi-new-categories-list">
                <?php echo $this->render_new_categories_list($ai_proposal); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje opcje kategorii dla selecta
     */
    private function render_category_options(array $ai_proposal): string
    {
        if (!isset($ai_proposal['proposed_structure']['main_categories'])) {
            return '';
        }

        ob_start();
        foreach ($ai_proposal['proposed_structure']['main_categories'] as $category) {
            echo '<option value="' . esc_attr($category['name']) . '">' . esc_html($category['name']) . '</option>';

            if (isset($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    echo '<option value="' . esc_attr($subcategory['name']) . '">-- ' . esc_html($subcategory['name']) . '</option>';
                }
            }
        }
        return ob_get_clean();
    }

    /**
     * Renderuje listę nowych kategorii
     */
    private function render_new_categories_list(array $ai_proposal): string
    {
        if (!isset($ai_proposal['migration_plan']['new_categories'])) {
            return '<p>Brak nowych kategorii.</p>';
        }

        $new_categories = $ai_proposal['migration_plan']['new_categories'];

        ob_start();
        ?>
        <h4>Proponowane nowe kategorie:</h4>
        <div class="mhi-new-categories-grid">
            <?php foreach ($new_categories as $index => $category): ?>
                <div class="mhi-new-category-item" data-category-index="<?php echo $index; ?>">
                    <div class="mhi-category-name">
                        <?php echo is_array($category) ? esc_html($category['name']) : esc_html($category); ?>
                    </div>
                    <?php if (is_array($category) && isset($category['parent'])): ?>
                        <div class="mhi-category-parent">
                            Nadrzędna: <?php echo esc_html($category['parent']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (is_array($category) && isset($category['reason'])): ?>
                        <div class="mhi-category-reason">
                            <?php echo esc_html($category['reason']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="mhi-category-actions">
                        <button type="button" class="button button-small mhi-edit-new-category" data-index="<?php echo $index; ?>">
                            ✏ Edytuj
                        </button>
                        <button type="button" class="button button-small mhi-delete-new-category"
                            data-index="<?php echo $index; ?>">
                            🗑 Usuń
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje zakładkę migracji
     */
    private function render_migration_tab(array $ai_proposal): string
    {
        ob_start();
        ?>
        <div class="mhi-migration-tab">
            <h3>📦 Plan Migracji</h3>
            <p>Przegląd wszystkich zmian które zostaną wprowadzone.</p>

            <div class="mhi-migration-preview">
                <?php echo $this->render_migration_preview($ai_proposal); ?>
            </div>

            <div class="mhi-migration-actions">
                <button type="button" class="button button-primary" id="mhi-execute-migration">
                    🚀 Wykonaj Migrację
                </button>
                <button type="button" class="button" id="mhi-create-backup">
                    💾 Utwórz Kopię Zapasową
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuje podgląd migracji
     */
    private function render_migration_preview(array $ai_proposal): string
    {
        if (!isset($ai_proposal['migration_plan'])) {
            return '<p>Brak planu migracji.</p>';
        }

        $plan = $ai_proposal['migration_plan'];

        ob_start();
        ?>
        <div class="mhi-migration-sections">
            <?php if (!empty($plan['categories_to_merge'])): ?>
                <div class="mhi-migration-section">
                    <h4>🔗 Scalenie kategorii</h4>
                    <?php foreach ($plan['categories_to_merge'] as $merge): ?>
                        <div class="mhi-migration-item">
                            <strong>Scal:</strong> <?php echo implode(', ', array_map('esc_html', $merge['sources'])); ?>
                            <strong>→</strong> <?php echo esc_html($merge['target']); ?>
                            <?php if (isset($merge['reason'])): ?>
                                <br><em><?php echo esc_html($merge['reason']); ?></em>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($plan['new_categories'])): ?>
                <div class="mhi-migration-section">
                    <h4>➕ Nowe kategorie</h4>
                    <?php foreach ($plan['new_categories'] as $category): ?>
                        <div class="mhi-migration-item">
                            <strong>Utwórz:</strong>
                            <?php echo is_array($category) ? esc_html($category['name']) : esc_html($category); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($plan['categories_to_delete'])): ?>
                <div class="mhi-migration-section">
                    <h4>🗑 Usunięcie kategorii</h4>
                    <?php foreach ($plan['categories_to_delete'] as $category): ?>
                        <div class="mhi-migration-item">
                            <strong>Usuń:</strong> <?php echo esc_html($category); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Zapisuje mapowanie kategorii
     */
    public function save_category_mapping(): void
    {
        check_ajax_referer('mhi_mapping_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $mapping_data = $_POST['mapping_data'] ?? [];

        try {
            // Zapisz mapowanie w bazie danych
            $saved_mapping = [
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'mapping_data' => $mapping_data,
                'status' => 'user_edited'
            ];

            update_option('mhi_user_category_mapping', $saved_mapping);

            $this->logger->info('Mapowanie kategorii zostało zapisane przez użytkownika');

            wp_send_json_success('Mapowanie zostało zapisane pomyślnie');

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas zapisywania mapowania: ' . $e->getMessage());
            wp_send_json_error('Błąd podczas zapisywania: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Ładuje mapowanie kategorii
     */
    public function load_category_mapping(): void
    {
        check_ajax_referer('mhi_mapping_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $mapping = get_option('mhi_user_category_mapping', null);

        wp_send_json_success($mapping);
    }

    /**
     * AJAX: Podgląd zmian w mapowaniu
     */
    public function preview_mapping_changes(): void
    {
        check_ajax_referer('mhi_mapping_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $mapping_data = $_POST['mapping_data'] ?? [];

        try {
            // Symuluj zmiany bez rzeczywistego wykonania
            $preview = $this->simulate_mapping_changes($mapping_data);

            wp_send_json_success($preview);

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas generowania podglądu: ' . $e->getMessage());
        }
    }

    /**
     * Symuluje zmiany mapowania
     */
    private function simulate_mapping_changes(array $mapping_data): array
    {
        // Implementacja symulacji zmian
        return [
            'affected_products' => 0,
            'new_categories' => [],
            'deleted_categories' => [],
            'moved_products' => []
        ];
    }

    /**
     * AJAX: Dodaje niestandardową kategorię
     */
    public function add_custom_category(): void
    {
        check_ajax_referer('mhi_mapping_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $category_data = $_POST['category_data'] ?? [];

        try {
            // Dodaj kategorię do tymczasowego mapowania
            $current_mapping = get_option('mhi_user_category_mapping', []);

            if (!isset($current_mapping['mapping_data']['new_categories'])) {
                $current_mapping['mapping_data']['new_categories'] = [];
            }

            $current_mapping['mapping_data']['new_categories'][] = $category_data;

            update_option('mhi_user_category_mapping', $current_mapping);

            wp_send_json_success('Kategoria została dodana');

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas dodawania kategorii: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Usuwa element mapowania
     */
    public function delete_mapping_item(): void
    {
        check_ajax_referer('mhi_mapping_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $item_type = $_POST['item_type'] ?? '';
        $item_index = (int) ($_POST['item_index'] ?? 0);

        try {
            $current_mapping = get_option('mhi_user_category_mapping', []);

            if (isset($current_mapping['mapping_data'][$item_type][$item_index])) {
                unset($current_mapping['mapping_data'][$item_type][$item_index]);
                // Reindeksuj tablicę
                $current_mapping['mapping_data'][$item_type] = array_values($current_mapping['mapping_data'][$item_type]);

                update_option('mhi_user_category_mapping', $current_mapping);
            }

            wp_send_json_success('Element został usunięty');

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas usuwania: ' . $e->getMessage());
        }
    }
}
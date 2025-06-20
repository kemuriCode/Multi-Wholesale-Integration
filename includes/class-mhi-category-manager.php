<?php
/**
 * Główny menedżer kategorii - łączy AI, optymalizację i edytor
 *
 * @package MHI
 */

declare(strict_types=1);

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Dołącz wszystkie komponenty
require_once plugin_dir_path(__FILE__) . 'class-mhi-ai-category-analyzer.php';
require_once plugin_dir_path(__FILE__) . 'class-mhi-category-ai-optimizer.php';
require_once plugin_dir_path(__FILE__) . 'class-mhi-category-mapping-editor.php';

/**
 * Klasa MHI_Category_Manager
 * 
 * Główny menedżer wszystkich funkcji związanych z kategoriami
 */
class MHI_Category_Manager
{
    /**
     * Instancja singletona
     *
     * @var MHI_Category_Manager
     */
    private static $instance = null;

    /**
     * Analizator AI
     *
     * @var MHI_AI_Category_Analyzer
     */
    private $analyzer;

    /**
     * Optymalizator AI
     *
     * @var MHI_Category_AI_Optimizer
     */
    private $optimizer;

    /**
     * Edytor mapowania
     *
     * @var MHI_Category_Mapping_Editor
     */
    private $editor;

    /**
     * Logger
     *
     * @var MHI_Logger
     */
    private $logger;

    /**
     * Konstruktor prywatny (singleton)
     */
    private function __construct()
    {
        $this->logger = new MHI_Logger();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Pobiera instancję singletona
     */
    public static function get_instance(): MHI_Category_Manager
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicjalizuje komponenty
     */
    private function init_components(): void
    {
        $this->analyzer = new MHI_AI_Category_Analyzer();
        $this->optimizer = new MHI_Category_AI_Optimizer();
        $this->editor = new MHI_Category_Mapping_Editor();
    }

    /**
     * Inicjalizuje hooki WordPress
     */
    private function init_hooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // AJAX hooks dla nowego inteligentnego systemu
        add_action('wp_ajax_mhi_intelligent_analysis', [$this, 'ajax_intelligent_analysis']);
        add_action('wp_ajax_mhi_map_uncategorized_products', [$this, 'ajax_map_uncategorized_products']);
        add_action('wp_ajax_mhi_map_xml_categories', [$this, 'ajax_map_xml_categories']);
        add_action('wp_ajax_mhi_cleanup_empty_categories', [$this, 'ajax_cleanup_empty_categories']);

        // AJAX hooks dla klasycznego systemu
        add_action('wp_ajax_mhi_analyze_categories', [$this, 'ajax_analyze_categories']);
        add_action('wp_ajax_mhi_apply_migration', [$this, 'ajax_apply_migration']);
        add_action('wp_ajax_mhi_create_backup', [$this, 'ajax_create_backup']);
        add_action('wp_ajax_mhi_restore_backup', [$this, 'ajax_restore_backup']);
        add_action('wp_ajax_mhi_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_mhi_import_settings', [$this, 'ajax_import_settings']);
    }

    /**
     * Dodaje menu administratora
     */
    public function add_admin_menu(): void
    {
        add_submenu_page(
            'mhi-settings',
            'AI Kategorie',
            '🤖 AI Kategorie',
            'manage_options',
            'mhi-ai-categories',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Dołącza skrypty i style administratora
     */
    public function enqueue_admin_scripts($hook): void
    {
        if ($hook !== 'mhi-settings_page_mhi-ai-categories') {
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');

        wp_enqueue_script(
            'mhi-category-manager',
            plugin_dir_url(__FILE__) . '../assets/js/category-manager.js',
            ['jquery', 'jquery-ui-sortable'],
            '1.0.0',
            true
        );

        wp_localize_script('mhi-category-manager', 'mhi_category_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhi_category_nonce')
        ]);

        wp_enqueue_style(
            'mhi-category-manager',
            plugin_dir_url(__FILE__) . '../assets/css/category-manager.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Renderuje stronę administratora
     */
    public function render_admin_page(): void
    {
        ?>
        <div class="wrap mhi-category-manager-wrap">
            <h1>🤖 AI Menedżer Kategorii</h1>
            <p>Zaawansowana analiza i optymalizacja kategorii produktów przez sztuczną inteligencję.</p>

            <div class="mhi-category-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#dashboard" class="nav-tab nav-tab-active">📊 Dashboard</a>
                    <a href="#analysis" class="nav-tab">🔍 Analiza AI</a>
                    <a href="#optimization" class="nav-tab">⚡ Optymalizacja</a>
                    <a href="#mapping" class="nav-tab">🗺 Mapowanie</a>
                    <a href="#settings" class="nav-tab">⚙ Ustawienia</a>
                </nav>

                <div id="dashboard" class="mhi-tab-content active">
                    <?php $this->render_dashboard(); ?>
                </div>

                <div id="analysis" class="mhi-tab-content">
                    <?php $this->render_analysis_tab(); ?>
                </div>

                <div id="optimization" class="mhi-tab-content">
                    <?php $this->render_optimization_tab(); ?>
                </div>

                <div id="mapping" class="mhi-tab-content">
                    <?php $this->render_mapping_tab(); ?>
                </div>

                <div id="settings" class="mhi-tab-content">
                    <?php $this->render_settings_tab(); ?>
                </div>
            </div>
        </div>

        <style>
            .mhi-category-manager-wrap {
                margin: 20px 0;
            }

            .mhi-tab-content {
                display: none;
                padding: 20px 0;
            }

            .mhi-tab-content.active {
                display: block;
            }

            .mhi-dashboard-widgets {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }

            .mhi-widget {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
            }

            .mhi-widget h3 {
                margin: 0 0 15px 0;
                color: #23282d;
            }

            .mhi-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }

            .mhi-stat-card {
                text-align: center;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }

            .mhi-stat-number {
                font-size: 24px;
                font-weight: bold;
                color: #0073aa;
            }

            .mhi-stat-label {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                // Obsługa zakładek
                $('.nav-tab-wrapper .nav-tab').on('click', function (e) {
                    e.preventDefault();

                    const targetTab = $(this).attr('href').substring(1);

                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.mhi-tab-content').removeClass('active');

                    $(this).addClass('nav-tab-active');
                    $('#' + targetTab).addClass('active');
                });
            });
        </script>
        <?php
    }

    /**
     * Renderuje dashboard
     */
    private function render_dashboard(): void
    {
        // Dodaj timeout protection i cache
        set_time_limit(60); // Max 60 sekund

        try {
            // Cache dla statystyk na 5 minut
            $stats = get_transient('mhi_category_stats');
            if (false === $stats) {
                $stats = $this->get_quick_category_statistics();
                set_transient('mhi_category_stats', $stats, 300); // 5 minut cache
            }

            $recent_analysis = $this->analyzer->get_latest_analysis();
            $optimizations = $this->optimizer->get_optimization_history();
        } catch (Exception $e) {
            // Fallback jeśli coś się nie powiedzie
            $stats = [
                'total_categories' => 0,
                'categories_with_products' => 0,
                'empty_categories' => 0,
                'potential_for_subdivision' => []
            ];
            $recent_analysis = null;
            $optimizations = [];
        }

        ?>
        <div class="mhi-dashboard-widgets">
            <div class="mhi-widget">
                <h3>📊 Statystyki Kategorii</h3>
                <div class="mhi-stats-grid">
                    <div class="mhi-stat-card">
                        <div class="mhi-stat-number"><?php echo $stats['total_categories']; ?></div>
                        <div class="mhi-stat-label">Wszystkich kategorii</div>
                    </div>
                    <div class="mhi-stat-card">
                        <div class="mhi-stat-number"><?php echo $stats['categories_with_products']; ?></div>
                        <div class="mhi-stat-label">Z produktami</div>
                    </div>
                    <div class="mhi-stat-card">
                        <div class="mhi-stat-number"><?php echo $stats['empty_categories']; ?></div>
                        <div class="mhi-stat-label">Pustych</div>
                    </div>
                    <div class="mhi-stat-card">
                        <div class="mhi-stat-number"><?php echo count($stats['potential_for_subdivision']); ?></div>
                        <div class="mhi-stat-label">Do podziału</div>
                    </div>
                </div>
            </div>

            <div class="mhi-widget">
                <h3>🤖 Ostatnia Analiza AI</h3>
                <?php if ($recent_analysis): ?>
                    <p><strong>Data:</strong> <?php echo $recent_analysis['timestamp']; ?></p>
                    <p><strong>Status:</strong> <?php echo $recent_analysis['status']; ?></p>
                    <button type="button" class="button" onclick="showAnalysisDetails()">📋 Pokaż Szczegóły</button>
                    <button type="button" class="button button-primary" onclick="editAnalysis()">✏ Edytuj Propozycję</button>
                <?php else: ?>
                    <p>Brak przeprowadzonej analizy.</p>
                    <button type="button" class="button button-primary" onclick="runNewAnalysis()">🚀 Uruchom Analizę</button>
                <?php endif; ?>
            </div>

            <div class="mhi-widget">
                <h3>⚡ Historia Optymalizacji</h3>
                <?php if (!empty($optimizations)): ?>
                    <p>Wykonano <?php echo count($optimizations); ?> optymalizacji</p>
                    <ul>
                        <?php foreach (array_slice($optimizations, -3) as $opt): ?>
                            <li>
                                <strong><?php echo $opt['optimization_id']; ?></strong>
                                <br><small><?php echo $opt['timestamp']; ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Brak przeprowadzonych optymalizacji.</p>
                <?php endif; ?>
                <button type="button" class="button button-primary" onclick="runOptimization()">⚡ Nowa Optymalizacja</button>
            </div>

            <div class="mhi-widget">
                <h3>🎯 Rekomendacje</h3>
                <?php
                $recommendations = $this->analyzer->generate_recommendations_report();
                if (!empty($recommendations['recommendations'])):
                    ?>
                    <ul>
                        <?php foreach (array_slice($recommendations['recommendations'], 0, 5) as $rec): ?>
                            <li>
                                <span class="priority-<?php echo $rec['priority']; ?>">●</span>
                                <strong><?php echo $rec['title']; ?></strong>
                                <br><small><?php echo $rec['description']; ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Brak aktualnych rekomendacji.</p>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .priority-high {
                color: #dc3232;
            }

            .priority-medium {
                color: #ffb900;
            }

            .priority-low {
                color: #46b450;
            }
        </style>
        <?php
    }

    /**
     * Renderuje zakładkę analizy
     */
    private function render_analysis_tab(): void
    {
        ?>
        <div class="mhi-analysis-section">
            <h2>🧠 NOWY INTELIGENTNY SYSTEM AI KATEGORYZACJI</h2>

            <div class="mhi-ai-status">
                <?php $this->display_ai_status(); ?>
            </div>

            <div class="mhi-control-group">
                <h3>🚀 Dwufazowa Analiza AI</h3>
                <p class="description">Nowy system najpierw analizuje WSZYSTKIE produkty globalnie, a potem tworzy logiczną
                    hierarchię bez powtórzeń.</p>

                <div class="mhi-buttons-row">
                    <button type="button" class="button button-primary button-large" id="mhi-start-intelligent-analysis">
                        🧠 Uruchom Inteligentną Analizę AI
                    </button>
                    <span class="description">Dwufazowa analiza: globalna → hierarchia → mapowanie</span>
                </div>
            </div>

            <div class="mhi-control-group">
                <h3>🎯 Mapowanie Produktów</h3>
                <p class="description">Inteligentne mapowanie produktów do kategorii AI.</p>

                <div class="mhi-buttons-row">
                    <button type="button" class="button button-secondary" id="mhi-map-uncategorized">
                        🎯 Zmapuj Produkty Bez Kategorii
                    </button>

                    <button type="button" class="button button-secondary" id="mhi-map-xml-categories">
                        📥 Mapuj Kategorie XML do AI
                    </button>

                    <button type="button" class="button button-secondary" id="mhi-cleanup-empty">
                        🧹 Usuń Puste Kategorie XML
                    </button>
                </div>
            </div>

            <div class="mhi-control-group">
                <h3>⚙️ Ustawienia Inteligentnej Analizy</h3>
                <form id="mhi-intelligent-analysis-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="max_hierarchy_depth">Maksymalna głębokość hierarchii:</label></th>
                            <td>
                                <select id="max_hierarchy_depth" name="max_hierarchy_depth">
                                    <option value="3">3 poziomy</option>
                                    <option value="4" selected>4 poziomy</option>
                                    <option value="5">5 poziomów</option>
                                    <option value="6">6 poziomów</option>
                                </select>
                                <p class="description">Maksymalna głębokość struktury kategorii (np. Główna → Pod → Pod-pod)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="min_products_for_subcategory">Min. produktów dla podkategorii:</label></th>
                            <td>
                                <input type="number" id="min_products_for_subcategory" name="min_products_for_subcategory"
                                    value="8" min="3" max="30">
                                <p class="description">Minimalna liczba produktów wymagana do utworzenia podkategorii</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="products_per_category">Produktów do analizy per kategoria:</label></th>
                            <td>
                                <input type="number" id="products_per_category" name="products_per_category" value="10" min="5"
                                    max="50">
                                <p class="description">Liczba przykładowych produktów do analizy w każdej kategorii</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="max_tokens">Maksymalne tokeny AI:</label></th>
                            <td>
                                <select id="max_tokens" name="max_tokens">
                                    <option value="8000">8,000 tokenów</option>
                                    <option value="12000" selected>12,000 tokenów</option>
                                    <option value="16000">16,000 tokenów</option>
                                    <option value="24000">24,000 tokenów (mega dokładne)</option>
                                </select>
                                <p class="description">Więcej tokenów = dokładniejsza analiza (wyższy koszt)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="include_product_descriptions">Uwzględnij opisy produktów:</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="include_product_descriptions" name="include_product_descriptions"
                                        checked>
                                    Analizuj także opisy produktów (dokładniejsze kategoryzowanie)
                                </label>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <div class="mhi-results-section" id="mhi-intelligent-results" style="display: none;">
                <h3>📊 Wyniki Inteligentnej Analizy</h3>
                <div id="mhi-intelligent-results-content"></div>
            </div>

        </div>

        <div class="mhi-analysis-section">
            <h2>🔍 Klasyczna Analiza Kategorii przez AI</h2>
            <p class="description">Stary system analizy - pozostawiony dla porównania.</p>

            <div class="mhi-analysis-controls">
                <div class="mhi-control-group">
                    <h3>Ustawienia Klasycznej Analizy</h3>
                    <form id="mhi-analysis-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="max_hierarchy_depth_classic">Maksymalna głębokość hierarchii:</label></th>
                                <td>
                                    <select id="max_hierarchy_depth_classic" name="max_hierarchy_depth">
                                        <option value="3">3 poziomy</option>
                                        <option value="4" selected>4 poziomy</option>
                                        <option value="5">5 poziomów</option>
                                        <option value="6">6 poziomów</option>
                                    </select>
                                    <p class="description">Maksymalna głębokość struktury kategorii (np. Główna → Pod → Pod-pod)
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="min_products_for_subcategory_classic">Min. produktów dla podkategorii:</label>
                                </th>
                                <td>
                                    <input type="number" id="min_products_for_subcategory_classic"
                                        name="min_products_for_subcategory" value="5" min="2" max="20">
                                    <p class="description">Minimalna liczba produktów wymagana do utworzenia podkategorii</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="products_per_category_classic">Produktów do analizy per kategoria:</label></th>
                                <td>
                                    <input type="number" id="products_per_category_classic" name="products_per_category"
                                        value="5" min="3" max="10">
                                    <p class="description">Liczba przykładowych produktów do analizy w każdej kategorii</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="include_product_descriptions_classic">Uwzględnij opisy produktów:</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="include_product_descriptions_classic"
                                            name="include_product_descriptions" checked>
                                        Analizuj opisy produktów (dokładniejsze, ale wolniejsze)
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="enable_chunking_classic">Podział na części:</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="enable_chunking_classic" name="enable_chunking" checked>
                                        Podziel analizę na mniejsze części (dla >20 kategorii)
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <div class="mhi-form-section">
                            <h4>Kontekst Sklepu</h4>
                            <textarea name="context_description" rows="4" cols="80"
                                placeholder="Opisz swój sklep, główne kategorie produktów, grupę docelową...">Sklep z artykułami promocyjnymi, reklamowymi i firmowymi. Sprzedajemy torby, plecaki, gadżety reklamowe, artykuły biurowe i prezenty firmowe.</textarea>
                        </div>

                        <p class="submit">
                            <button type="button" class="button button-primary button-large" id="run-analysis">
                                🚀 Uruchom Analizę AI
                            </button>
                            <button type="button" class="button" id="test-api-connection">
                                🔗 Testuj Połączenie API
                            </button>
                        </p>
                    </form>
                </div>

                <div class="mhi-analysis-results" id="analysis-results" style="display: none;">
                    <h3>📋 Wyniki Analizy</h3>
                    <div id="analysis-content"></div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#run-analysis').on('click', function () {
                    const button = $(this);
                    const originalText = button.text();

                    button.text('🔄 Analizowanie...').prop('disabled', true);

                    const formData = $('#mhi-analysis-form').serializeArray();
                    const analysisData = {};

                    formData.forEach(function (item) {
                        if (item.name === 'include_product_descriptions' || item.name === 'enable_chunking') {
                            analysisData[item.name] = true;
                        } else {
                            analysisData[item.name] = item.value;
                        }
                    });

                    $.ajax({
                        url: mhi_category_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'mhi_run_category_analysis',
                            settings: analysisData,
                            nonce: mhi_category_ajax.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#analysis-results').show();
                                $('#analysis-content').html('<div class="notice notice-success"><p>✅ Analiza zakończona pomyślnie! Tokeny użyte: ' + (response.data.tokens_used || 0) + '</p></div>');

                                // Pokaż przycisk do edycji
                                $('#analysis-content').append('<button type="button" class="button button-primary" onclick="location.hash=\'mapping\'">✏ Edytuj Propozycję</button>');
                            } else {
                                $('#analysis-results').show();
                                $('#analysis-content').html('<div class="notice notice-error"><p>❌ Błąd: ' + response.data + '</p></div>');
                            }
                        },
                        error: function () {
                            $('#analysis-results').show();
                            $('#analysis-content').html('<div class="notice notice-error"><p>❌ Błąd komunikacji z serwerem</p></div>');
                        },
                        complete: function () {
                            button.text(originalText).prop('disabled', false);
                        }
                    });
                });

                $('#test-api-connection').on('click', function () {
                    const button = $(this);
                    button.text('🔄 Testowanie...').prop('disabled', true);

                    $.ajax({
                        url: mhi_category_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'mhi_test_api_connection',
                            nonce: mhi_category_ajax.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                alert('✅ Połączenie z OpenAI API działa poprawnie!');
                            } else {
                                alert('❌ Błąd połączenia: ' + response.data);
                            }
                        },
                        complete: function () {
                            button.text('🔗 Testuj Połączenie API').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Renderuje zakładkę optymalizacji
     */
    private function render_optimization_tab(): void
    {
        ?>
        <div class="mhi-optimization-section">
            <h2>⚡ Zaawansowana Optymalizacja AI</h2>
            <p>Głęboka analiza produktów i tworzenie całkowicie nowej struktury kategorii.</p>

            <div class="mhi-optimization-controls">
                <form id="mhi-optimization-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="create_new_categories">Twórz nowe kategorie:</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="create_new_categories" name="create_new_categories" checked>
                                    Pozwól AI tworzyć całkowicie nowe kategorie
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="analyze_product_similarity">Analiza podobieństwa produktów:</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="analyze_product_similarity" name="analyze_product_similarity"
                                        checked>
                                    Analizuj nazwy, atrybuty i relacje między produktami
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="aggressive_optimization">Agresywna optymalizacja:</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="aggressive_optimization" name="aggressive_optimization">
                                    Pozwól na duże zmiany w strukturze (UWAGA: może znacznie zmienić sklep)
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="button" class="button button-primary button-large" id="run-optimization">
                            ⚡ Uruchom Optymalizację
                        </button>
                    </p>
                </form>

                <div class="mhi-optimization-results" id="optimization-results" style="display: none;">
                    <h3>⚡ Wyniki Optymalizacji</h3>
                    <div id="optimization-content"></div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#run-optimization').on('click', function () {
                    const button = $(this);
                    button.text('🔄 Optymalizowanie...').prop('disabled', true);

                    const formData = $('#mhi-optimization-form').serializeArray();
                    const optimizationData = {};

                    formData.forEach(function (item) {
                        optimizationData[item.name] = true;
                    });

                    $.ajax({
                        url: mhi_category_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'mhi_run_category_optimization',
                            settings: optimizationData,
                            nonce: mhi_category_ajax.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#optimization-results').show();
                                $('#optimization-content').html('<div class="notice notice-success"><p>✅ Optymalizacja zakończona!</p></div>');
                            } else {
                                $('#optimization-results').show();
                                $('#optimization-content').html('<div class="notice notice-error"><p>❌ Błąd: ' + response.data + '</p></div>');
                            }
                        },
                        complete: function () {
                            button.text('⚡ Uruchom Optymalizację').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Renderuje zakładkę mapowania
     */
    private function render_mapping_tab(): void
    {
        $recent_analysis = $this->analyzer->get_latest_analysis();

        if (!$recent_analysis) {
            echo '<div class="notice notice-warning"><p>⚠ Brak dostępnej analizy. Wykonaj najpierw analizę kategorii w zakładce "Analiza AI".</p></div>';
            return;
        }

        echo $this->editor->render_mapping_editor($recent_analysis['result'] ?? []);
    }

    /**
     * Renderuje zakładkę ustawień
     */
    private function render_settings_tab(): void
    {
        $current_settings = $this->analyzer->export_settings();

        ?>
        <div class="mhi-settings-section">
            <h2>⚙ Ustawienia AI Kategorii</h2>

            <form method="post" action="options.php">
                <?php settings_fields('mhi_ai_category_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mhi_openai_api_key">Klucz API OpenAI:</label></th>
                        <td>
                            <input type="password" id="mhi_openai_api_key" name="mhi_openai_api_key"
                                value="<?php echo esc_attr(get_option('mhi_openai_api_key', '')); ?>" class="regular-text" />
                            <p class="description">Twój klucz API OpenAI. Potrzebny do działania funkcji AI.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mhi_openai_model">Model AI:</label></th>
                        <td>
                            <select id="mhi_openai_model" name="mhi_openai_model">
                                <option value="gpt-4o" <?php selected(get_option('mhi_openai_model'), 'gpt-4o'); ?>>GPT-4o
                                    (Zalecany)</option>
                                <option value="gpt-4" <?php selected(get_option('mhi_openai_model'), 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('mhi_openai_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mhi_openai_max_tokens">Maksymalne tokeny:</label></th>
                        <td>
                            <input type="number" id="mhi_openai_max_tokens" name="mhi_openai_max_tokens"
                                value="<?php echo esc_attr(get_option('mhi_openai_max_tokens', 8000)); ?>" min="1000"
                                max="20000" />
                            <p class="description">Maksymalna liczba tokenów w odpowiedzi AI (więcej = bardziej szczegółowe
                                analizy)</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('💾 Zapisz Ustawienia'); ?>
            </form>

            <div class="mhi-settings-actions">
                <h3>🔧 Narzędzia Kategorii</h3>
                <p>
                    <button type="button" class="button button-primary" id="refresh-categories">🔄 Odśwież Liczniki
                        Kategorii</button>
                    <span id="refresh-status" style="margin-left: 10px;"></span>
                </p>
                <p class="description">
                    Naprawia problem z kategoriami pokazującymi 0 produktów gdy rzeczywiście zawierają produkty.
                    Odświeża liczniki wszystkich kategorii produktów.
                </p>

                <h3>📤 Eksport/Import</h3>
                <p>
                    <button type="button" class="button" id="export-settings">📤 Eksportuj Ustawienia</button>
                    <button type="button" class="button" id="import-settings">📥 Importuj Ustawienia</button>
                </p>
                <input type="file" id="import-file" style="display: none;" accept=".json">
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Odświeżanie liczników kategorii
                $('#refresh-categories').on('click', function () {
                    const $button = $(this);
                    const $status = $('#refresh-status');

                    $button.prop('disabled', true).text('🔄 Odświeżanie...');
                    $status.html('<span style="color: #0073aa;">Trwa odświeżanie kategorii...</span>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mhi_refresh_category_counts',
                            nonce: '<?php echo wp_create_nonce('mhi_category_nonce'); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                $status.html('<span style="color: #46b450;">✅ ' + response.data.message + '</span>');
                                // Odśwież stronę po 2 sekundach żeby pokazać nowe liczniki
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            } else {
                                $status.html('<span style="color: #dc3232;">❌ Błąd: ' + response.data + '</span>');
                            }
                        },
                        error: function () {
                            $status.html('<span style="color: #dc3232;">❌ Błąd połączenia</span>');
                        },
                        complete: function () {
                            $button.prop('disabled', false).text('🔄 Odśwież Liczniki Kategorii');
                        }
                    });
                });

                $('#export-settings').on('click', function () {
                    const settings = <?php echo json_encode($current_settings); ?>;
                    const blob = new Blob([JSON.stringify(settings, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'mhi-ai-settings.json';
                    a.click();
                    URL.revokeObjectURL(url);
                });

                $('#import-settings').on('click', function () {
                    $('#import-file').click();
                });

                $('#import-file').on('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            try {
                                const settings = JSON.parse(e.target.result);
                                // Implementuj import ustawień
                                alert('📥 Import ustawień będzie wkrótce dostępny');
                            } catch (error) {
                                alert('❌ Błąd importu: Nieprawidłowy plik JSON');
                            }
                        };
                        reader.readAsText(file);
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX: Uruchamia analizę kategorii
     */
    public function ajax_run_analysis(): void
    {
        check_ajax_referer('mhi_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $settings = $_POST['settings'] ?? [];
        $context_description = $settings['context_description'] ?? '';

        try {
            $result = $this->analyzer->analyze_categories($context_description, $settings);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Uruchamia optymalizację kategorii
     */
    public function ajax_run_optimization(): void
    {
        check_ajax_referer('mhi_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $settings = $_POST['settings'] ?? [];

        try {
            $result = $this->optimizer->deep_category_optimization($settings);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Wykonuje mapowanie kategorii
     */
    public function ajax_execute_mapping(): void
    {
        check_ajax_referer('mhi_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $mapping_id = $_POST['mapping_id'] ?? '';

        try {
            // Implementacja wykonania mapowania
            wp_send_json_success(['message' => 'Mapowanie zostało wykonane']);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Testuje połączenie z API
     */
    public function ajax_test_api_connection(): void
    {
        check_ajax_referer('mhi_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        try {
            $result = $this->analyzer->test_api_connection();

            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Odświeża liczniki kategorii
     */
    public function ajax_refresh_category_counts(): void
    {
        check_ajax_referer('mhi_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        try {
            $result = $this->refresh_category_counts();
            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Odświeża liczniki wszystkich kategorii
     *
     * @return array
     */
    public function refresh_category_counts(): array
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ]);

        $updated = 0;
        $fixed = 0;

        foreach ($categories as $category) {
            $real_count = $this->get_real_category_product_count($category->term_id);

            // Sprawdź czy licznik jest nieprawidłowy
            if ($category->count != $real_count) {
                $fixed++;

                // Zaktualizuj licznik kategorii
                wp_update_term_count_now([$category->term_id], 'product_cat');

                // Jeśli wciąż nie jest prawidłowy, wymuś aktualizację
                global $wpdb;
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    ['count' => $real_count],
                    ['term_taxonomy_id' => $category->term_taxonomy_id]
                );
            }
            $updated++;
        }

        // Wyczyść cache
        wp_cache_flush();
        clean_term_cache(array_column($categories, 'term_id'), 'product_cat');

        return [
            'success' => true,
            'updated' => $updated,
            'fixed' => $fixed,
            'message' => sprintf(
                'Odświeżono %d kategorii. Naprawiono %d nieprawidłowych liczników.',
                $updated,
                $fixed
            )
        ];
    }

    /**
     * Pobiera szybkie statystyki kategorii (zoptymalizowane)
     *
     * @return array
     */
    private function get_quick_category_statistics(): array
    {
        global $wpdb;

        // Pobierz podstawowe liczby z bazy danych
        $total_categories = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->term_taxonomy} 
            WHERE taxonomy = 'product_cat'
        ");

        $categories_with_products = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->term_taxonomy} 
            WHERE taxonomy = 'product_cat' AND count > 0
        ");

        return [
            'total_categories' => (int) $total_categories,
            'categories_with_products' => (int) $categories_with_products,
            'empty_categories' => (int) $total_categories - (int) $categories_with_products,
            'potential_for_subdivision' => [] // Uproszczone - nie liczymy tego w szybkiej wersji
        ];
    }

    /**
     * Pobiera rzeczywistą liczbę produktów w kategorii
     *
     * @param int $category_id
     * @return int
     */
    private function get_real_category_product_count(int $category_id): int
    {
        $category = get_term($category_id, 'product_cat');
        if (!$category || is_wp_error($category)) {
            return 0;
        }

        $products = wc_get_products([
            'category' => [$category->slug],
            'status' => 'publish',
            'limit' => -1,
            'return' => 'ids'
        ]);

        return count($products);
    }

    /**
     * Wyświetla status AI kategoryzacji
     */
    private function display_ai_status(): void
    {
        $stats = $this->analyzer->get_ai_categorization_stats();
        ?>
        <div class="mhi-ai-status-box">
            <div class="mhi-status-grid">
                <div class="mhi-status-item">
                    <span class="mhi-status-number"><?php echo $stats['total_products']; ?></span>
                    <span class="mhi-status-label">Wszystkich produktów</span>
                </div>
                <div class="mhi-status-item">
                    <span class="mhi-status-number"><?php echo $stats['ai_categorized_products']; ?></span>
                    <span class="mhi-status-label">Skategoryzowanych przez AI</span>
                </div>
                <div class="mhi-status-item">
                    <span class="mhi-status-number"><?php echo $stats['uncategorized_products']; ?></span>
                    <span class="mhi-status-label">Bez kategorii</span>
                </div>
                <div class="mhi-status-item">
                    <span class="mhi-status-number"><?php echo $stats['ai_generated_categories']; ?></span>
                    <span class="mhi-status-label">Kategorii AI</span>
                </div>
                <div class="mhi-status-item">
                    <span class="mhi-status-number"><?php echo round($stats['categorization_percentage'], 1); ?>%</span>
                    <span class="mhi-status-label">Pokrycie AI</span>
                </div>
            </div>
        </div>

        <?php
        // Dodaj JavaScript
        $this->add_javascript();
    }

    /**
     * Dodaje JavaScript dla interfejsu
     */
    private function add_javascript(): void
    {
        ?>
        <script>
            jQuery(document).ready(function ($) {
                // Nowy inteligentny system AI
                $('#mhi-start-intelligent-analysis').click(function () {
                    const button = $(this);
                    const formData = $('#mhi-intelligent-analysis-form').serialize();

                    button.prop('disabled', true).text('🧠 Analizuję...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'mhi_intelligent_analysis',
                            ...parseFormData(formData),
                            nonce: '<?php echo wp_create_nonce('mhi_intelligent_analysis'); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#mhi-intelligent-results').show();
                                $('#mhi-intelligent-results-content').html(formatIntelligentResults(response.data));
                                showNotification('✅ Inteligentna analiza zakończona pomyślnie!', 'success');
                            } else {
                                showNotification('❌ Błąd: ' + response.data, 'error');
                            }
                        },
                        error: function () {
                            showNotification('❌ Błąd komunikacji z serwerem', 'error');
                        },
                        complete: function () {
                            button.prop('disabled', false).text('🧠 Uruchom Inteligentną Analizę AI');
                        }
                    });
                });

                // Mapowanie produktów bez kategorii
                $('#mhi-map-uncategorized').click(function () {
                    const button = $(this);
                    button.prop('disabled', true).text('🎯 Mapuję...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'mhi_map_uncategorized_products',
                            nonce: '<?php echo wp_create_nonce('mhi_map_uncategorized'); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                showNotification(`✅ Zmapowano ${response.data.mapped_products} produktów!`, 'success');
                                updateAiStatus();
                            } else {
                                showNotification('❌ Błąd: ' + response.data, 'error');
                            }
                        },
                        complete: function () {
                            button.prop('disabled', false).text('🎯 Zmapuj Produkty Bez Kategorii');
                        }
                    });
                });

                // Mapowanie kategorii XML
                $('#mhi-map-xml-categories').click(function () {
                    const button = $(this);
                    button.prop('disabled', true).text('📥 Mapuję...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'mhi_map_xml_categories',
                            nonce: '<?php echo wp_create_nonce('mhi_map_xml'); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                showNotification(`✅ Zmapowano kategorie XML do struktury AI!`, 'success');
                            } else {
                                showNotification('❌ Błąd: ' + response.data, 'error');
                            }
                        },
                        complete: function () {
                            button.prop('disabled', false).text('📥 Mapuj Kategorie XML do AI');
                        }
                    });
                });

                // Czyszczenie pustych kategorii
                $('#mhi-cleanup-empty').click(function () {
                    const button = $(this);
                    button.prop('disabled', true).text('🧹 Czyszczę...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'mhi_cleanup_empty_categories',
                            nonce: '<?php echo wp_create_nonce('mhi_cleanup_empty'); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                showNotification(`✅ Usunięto ${response.data.deleted_categories} pustych kategorii!`, 'success');
                            } else {
                                showNotification('❌ Błąd: ' + response.data, 'error');
                            }
                        },
                        complete: function () {
                            button.prop('disabled', false).text('🧹 Usuń Puste Kategorie XML');
                        }
                    });
                });

                // Klasyczna analiza AI (stary system)
                $('#mhi-start-analysis').click(function () {
                    const button = $(this);
                    const formData = $('#mhi-analysis-form').serialize();

                    button.prop('disabled', true).text('🔍 Analizuję...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'mhi_analyze_categories',
                            ...parseFormData(formData),
                            nonce: '<?php echo wp_create_nonce('mhi_analyze_categories'); ?>'
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#mhi-results').show();
                                $('#mhi-results-content').html(formatAnalysisResults(response.data));
                                showNotification('✅ Analiza kategorii zakończona pomyślnie!', 'success');
                            } else {
                                showNotification('❌ Błąd: ' + response.data, 'error');
                            }
                        },
                        error: function () {
                            showNotification('❌ Błąd komunikacji z serwerem', 'error');
                        },
                        complete: function () {
                            button.prop('disabled', false).text('🔍 Rozpocznij Analizę AI');
                        }
                    });
                });

                // Funkcje pomocnicze
                function parseFormData(formData) {
                    const data = {};
                    formData.split('&').forEach(pair => {
                        const [key, value] = pair.split('=');
                        data[decodeURIComponent(key)] = decodeURIComponent(value);
                    });
                    return data;
                }

                function formatIntelligentResults(data) {
                    let html = '<div class="mhi-intelligent-results">';

                    html += '<h4>🧠 Wyniki Dwufazowej Analizy AI</h4>';
                    html += `<p><strong>Tokeny wykorzystane:</strong> ${data.ai_metadata.total_tokens_used}</p>`;
                    html += `<p><strong>Kategorii do utworzenia:</strong> ${data.product_mapping.categories_to_create.length}</p>`;

                    if (data.intelligent_structure && data.intelligent_structure.proposed_structure) {
                        html += '<h5>📂 Nowa Struktura Kategorii:</h5>';
                        html += '<div class="mhi-category-tree">';

                        data.intelligent_structure.proposed_structure.main_categories.forEach(category => {
                            html += `<div class="mhi-main-category">📂 <strong>${category.name}</strong> - ${category.description}</div>`;

                            if (category.subcategories) {
                                category.subcategories.forEach(subcategory => {
                                    html += `<div class="mhi-subcategory">└─ ${subcategory.name} - ${subcategory.description}</div>`;

                                    if (subcategory.subcategories) {
                                        subcategory.subcategories.forEach(subsubcategory => {
                                            html += `<div class="mhi-subsubcategory">  └─ ${subsubcategory.name} - ${subsubcategory.description}</div>`;
                                        });
                                    }
                                });
                            }
                        });

                        html += '</div>';
                    }

                    html += '</div>';
                    return html;
                }

                function formatAnalysisResults(data) {
                    // Formatowanie wyników klasycznej analizy
                    let html = '<div class="mhi-analysis-results">';
                    html += '<h4>🔍 Wyniki Klasycznej Analizy</h4>';
                    // ... dodaj formatowanie
                    html += '</div>';
                    return html;
                }

                function showNotification(message, type) {
                    const notification = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
                    $('.mhi-analysis-section').first().prepend(notification);

                    setTimeout(() => {
                        notification.fadeOut();
                    }, 5000);
                }

                function updateAiStatus() {
                    location.reload(); // Prosty sposób odświeżenia statusu
                }
            });
        </script>

        <style>
            .mhi-ai-status-box {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .mhi-status-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }

            .mhi-status-item {
                text-align: center;
                padding: 10px;
                background: white;
                border-radius: 6px;
                border: 1px solid #e0e0e0;
            }

            .mhi-status-number {
                display: block;
                font-size: 24px;
                font-weight: bold;
                color: #0073aa;
            }

            .mhi-status-label {
                display: block;
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }

            .mhi-buttons-row {
                display: flex;
                gap: 15px;
                align-items: center;
                margin: 15px 0;
            }

            .mhi-buttons-row .description {
                font-style: italic;
                color: #666;
            }

            .mhi-control-group {
                margin-bottom: 30px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
            }

            .mhi-control-group h3 {
                margin-top: 0;
                color: #333;
            }

            .mhi-category-tree .mhi-main-category {
                font-weight: bold;
                margin: 10px 0;
                color: #0073aa;
            }

            .mhi-category-tree .mhi-subcategory {
                margin-left: 20px;
                color: #333;
            }

            .mhi-category-tree .mhi-subsubcategory {
                margin-left: 40px;
                color: #666;
            }
        </style>
        <?php
    }

    /**
     * AJAX: Inteligentna dwufazowa analiza AI
     */
    public function ajax_intelligent_analysis(): void
    {
        try {
            // Weryfikacja nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhi_intelligent_analysis')) {
                wp_die('Błąd bezpieczeństwa');
            }

            // Pobierz ustawienia
            $settings = [
                'max_hierarchy_depth' => intval($_POST['max_hierarchy_depth'] ?? 4),
                'min_products_for_subcategory' => intval($_POST['min_products_for_subcategory'] ?? 8),
                'products_per_category' => intval($_POST['products_per_category'] ?? 10),
                'include_product_descriptions' => isset($_POST['include_product_descriptions']),
                'max_tokens' => intval($_POST['max_tokens'] ?? 12000)
            ];

            // Uruchom inteligentną analizę z ustawieniami

            // Uruchom inteligentną analizę
            $result = $this->analyzer->intelligent_two_phase_analysis('', $settings);

            if ($result['success']) {
                wp_send_json_success($result['data']);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas analizy: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Mapowanie produktów bez kategorii
     */
    public function ajax_map_uncategorized_products(): void
    {
        try {
            // Weryfikacja nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhi_map_uncategorized')) {
                wp_die('Błąd bezpieczeństwa');
            }

            $result = $this->analyzer->map_uncategorized_products();

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas mapowania: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Mapowanie kategorii XML do struktury AI
     */
    public function ajax_map_xml_categories(): void
    {
        try {
            // Weryfikacja nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhi_map_xml')) {
                wp_die('Błąd bezpieczeństwa');
            }

            // Pobierz kategorie XML (z importów)
            $xml_categories = $this->get_xml_import_categories();

            $result = $this->analyzer->map_xml_categories_to_ai_structure($xml_categories);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas mapowania kategorii XML: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Czyszczenie pustych kategorii XML
     */
    public function ajax_cleanup_empty_categories(): void
    {
        try {
            // Weryfikacja nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhi_cleanup_empty')) {
                wp_die('Błąd bezpieczeństwa');
            }

            $result = $this->analyzer->cleanup_empty_xml_categories();

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            wp_send_json_error('Błąd podczas czyszczenia: ' . $e->getMessage());
        }
    }

    /**
     * Pobiera kategorie z importów XML
     */
    private function get_xml_import_categories(): array
    {
        // Znajdź kategorie z metadanymi importu XML
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => '_xml_import',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $xml_categories = [];
        foreach ($categories as $category) {
            $xml_categories[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count,
                'xml_source' => get_term_meta($category->term_id, '_xml_import', true)
            ];
        }

        return $xml_categories;
    }
}
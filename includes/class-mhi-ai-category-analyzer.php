<?php
/**
 * Klasa do analizy i reorganizacji kategorii produktów przez OpenAI
 *
 * @package MHI
 */

declare(strict_types=1);

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Dołączenie autoloadera Composer
require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';

use OpenAI\Client;

/**
 * Klasa MHI_AI_Category_Analyzer
 * 
 * Zarządza analizą kategorii produktów WooCommerce przez OpenAI API
 */
class MHI_AI_Category_Analyzer
{
    /**
     * Instancja klienta OpenAI
     *
     * @var Client
     */
    private $openai_client;

    /**
     * Klucz API OpenAI
     *
     * @var string
     */
    private $api_key;

    /**
     * Model OpenAI do użycia
     *
     * @var string
     */
    private $model;

    /**
     * Maksymalna liczba tokenów w odpowiedzi
     *
     * @var int
     */
    private $max_tokens;

    /**
     * Temperatura dla generowania odpowiedzi
     *
     * @var float
     */
    private $temperature;

    /**
     * Maksymalna głębokość hierarchii kategorii
     *
     * @var int
     */
    private $max_hierarchy_depth;

    /**
     * Minimalną liczba produktów wymagana do utworzenia podkategorii
     *
     * @var int
     */
    private $min_products_for_subcategory;

    /**
     * Czy uwzględniać opis produktów w analizie
     *
     * @var bool
     */
    private $include_product_descriptions;

    /**
     * Liczba produktów do analizy per kategoria
     *
     * @var int
     */
    private $products_per_category;

    /**
     * Czy dzielić analizę na mniejsze części (chunking)
     *
     * @var bool
     */
    private $enable_chunking;

    /**
     * Rozmiar chunk'a dla analizy
     *
     * @var int
     */
    private $chunk_size;

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
        $this->init_settings();
        // Klient OpenAI będzie inicjalizowany "lazy" - dopiero gdy potrzebny
    }

    /**
     * Inicjalizuje ustawienia
     */
    private function init_settings(): void
    {
        $this->api_key = get_option('mhi_openai_api_key', '');
        $this->model = get_option('mhi_openai_model', 'gpt-4o');
        $this->max_tokens = (int) get_option('mhi_openai_max_tokens', 8000);
        $this->temperature = (float) get_option('mhi_openai_temperature', 0.3);
        $this->max_hierarchy_depth = (int) get_option('mhi_max_hierarchy_depth', 4);
        $this->min_products_for_subcategory = (int) get_option('mhi_min_products_for_subcategory', 5);
        $this->include_product_descriptions = (bool) get_option('mhi_include_product_descriptions', true);
        $this->products_per_category = (int) get_option('mhi_products_per_category', 5);
        $this->enable_chunking = (bool) get_option('mhi_enable_chunking', true);
        $this->chunk_size = (int) get_option('mhi_chunk_size', 20);
    }

    /**
     * Pobiera klienta OpenAI (metoda pomocnicza)
     */
    private function get_openai_client()
    {
        if (!$this->openai_client) {
            $this->init_openai_client();
        }
        return $this->openai_client;
    }

    /**
     * Inicjalizuje klienta OpenAI
     */
    private function init_openai_client(): void
    {
        if (empty($this->api_key)) {
            $this->logger->error('Klucz API OpenAI nie został skonfigurowany.');
            throw new Exception('Klucz API OpenAI nie został skonfigurowany.');
        }

        try {
            $this->openai_client = \OpenAI::client($this->api_key);
            $this->logger->info('Klient OpenAI zainicjalizowany pomyślnie.');
        } catch (Exception $e) {
            $this->logger->error('Błąd inicjalizacji klienta OpenAI: ' . $e->getMessage());
            throw new Exception('Nie można zainicjalizować klienta OpenAI: ' . $e->getMessage());
        }
    }

    /**
     * NOWY INTELIGENTNY SYSTEM - ZOPTYMALIZOWANY: Szybka dwufazowa analiza kategorii
     * Optymalizacja przeciwko Gateway Timeout
     *
     * @param string $context_description Opis kontekstu sklepu
     * @param array $custom_settings Niestandardowe ustawienia analizy
     * @return array Wynik analizy
     */
    public function intelligent_two_phase_analysis(string $context_description = '', array $custom_settings = []): array
    {
        try {
            $this->logger->info('🚀 ROZPOCZĘCIE ZOPTYMALIZOWANEJ ANALIZY AI (przeciwko timeout)');

            // Sprawdź czy klucz API jest ustawiony
            if (empty($this->api_key)) {
                throw new Exception('Klucz API OpenAI nie został skonfigurowany. Ustaw klucz w ustawieniach wtyczki.');
            }

            // Zastosuj niestandardowe ustawienia z optymalizacjami
            $optimized_settings = array_merge([
                'max_hierarchy_depth' => 4,
                'min_products_for_subcategory' => 8,
                'products_per_category' => 5, // Zmniejszono z 10
                'include_product_descriptions' => false, // Wyłącz dla szybkości
                'max_tokens' => 8000, // Zmniejszono z 12000
                'max_products_sample' => 100, // NOWE: limit próbki produktów
                'timeout_protection' => true // NOWE: ochrona przed timeout
            ], $custom_settings);

            $this->apply_custom_settings($optimized_settings);

            // QUICK PHASE 1: Szybka analiza ograniczonej próbki
            $this->logger->info('⚡ QUICK PHASE 1: Szybka analiza próbki danych');
            $quick_analysis = $this->quick_phase_1_analysis($context_description, $optimized_settings);

            if (!$quick_analysis['success']) {
                return $quick_analysis;
            }

            // QUICK PHASE 2: Szybkie tworzenie hierarchii
            $this->logger->info('⚡ QUICK PHASE 2: Szybkie tworzenie hierarchii');
            $quick_structure = $this->quick_phase_2_hierarchy($quick_analysis['data'], $context_description);

            if (!$quick_structure['success']) {
                return $quick_structure;
            }

            // PHASE 3: Mapowanie (bez AI - lokalne)
            $this->logger->info('📊 PHASE 3: Lokalne mapowanie produktów');
            $final_mapping = $this->local_product_mapping($quick_structure['data'], $quick_analysis['data']);

            // Zapisz wynik z oznaczeniem "QUICK"
            $final_result = [
                'analysis_type' => 'intelligent_quick_optimized',
                'timestamp' => current_time('mysql'),
                'quick_analysis' => $quick_analysis['data'],
                'quick_structure' => $quick_structure['data'],
                'product_mapping' => $final_mapping,
                'ai_metadata' => [
                    'generated_by_ai' => true,
                    'analysis_version' => '2.1_optimized',
                    'total_tokens_used' => ($quick_analysis['tokens_used'] ?? 0) + ($quick_structure['tokens_used'] ?? 0),
                    'optimization_applied' => true,
                    'timeout_protection' => true,
                    'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
                ]
            ];

            $this->save_intelligent_analysis_result($final_result);
            $this->logger->info('✅ ZOPTYMALIZOWANA ANALIZA ZAKOŃCZONA POMYŚLNIE');

            return [
                'success' => true,
                'data' => $final_result
            ];

        } catch (Exception $e) {
            $this->logger->error('❌ Błąd podczas zoptymalizowanej analizy: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * QUICK PHASE 1: Szybka analiza ograniczonej próbki danych
     */
    private function quick_phase_1_analysis(string $context_description, array $settings): array
    {
        try {
            // Pobierz tylko PRÓBKĘ produktów i kategorii (nie wszystkie!)
            $sample_products = $this->get_limited_products_sample($settings['max_products_sample']);
            $key_categories = $this->get_key_categories(); // Tylko kategorie z produktami

            $this->logger->info("⚡ Analizuję próbkę: {count($sample_products)} produktów w {count($key_categories)} kategoriach");

            // Przygotuj minimalne dane dla AI
            $analysis_data = [
                'sample_size' => count($sample_products),
                'categories_count' => count($key_categories),
                'products_sample' => $sample_products,
                'categories_sample' => array_slice($key_categories, 0, 30), // Max 30 kategorii
                'quick_patterns' => $this->extract_quick_patterns($sample_products)
            ];

            // Krótki prompt dla szybkiej analizy
            $prompt = $this->create_quick_phase_1_prompt($analysis_data, $context_description);

            $response = $this->get_openai_client()->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Jesteś ekspertem e-commerce. Wykonaj SZYBKĄ analizę próbki danych. Bądź zwięzły i konkretny.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 4000, // Zmniejszone dla szybkości
                'temperature' => 0.1,
            ]);

            $response_content = $response->choices[0]->message->content;
            $analysis_result = json_decode($response_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON quick phase 1: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $analysis_result,
                'tokens_used' => $response->usage->totalTokens ?? 0
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd w quick phase 1: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * QUICK PHASE 2: Szybkie tworzenie hierarchii kategorii
     */
    private function quick_phase_2_hierarchy(array $quick_analysis, string $context_description): array
    {
        try {
            // Krótki prompt dla szybkiego tworzenia hierarchii
            $prompt = $this->create_quick_phase_2_prompt($quick_analysis, $context_description);

            $response = $this->get_openai_client()->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Na podstawie analizy próbki, stwórz PROSTĄ hierarchię kategorii. Maksymalnie 5 głównych kategorii, bez powtórzeń.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 3000, // Zmniejszone
                'temperature' => 0.2,
            ]);

            $response_content = $response->choices[0]->message->content;
            $hierarchy_result = json_decode($response_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON quick phase 2: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $hierarchy_result,
                'tokens_used' => $response->usage->totalTokens ?? 0
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd w quick phase 2: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Lokalne mapowanie produktów (bez AI - szybkie)
     */
    private function local_product_mapping(array $structure, array $analysis): array
    {
        // Proste mapowanie bez użycia AI dla szybkości
        $mapping = [
            'categories_to_create' => [],
            'products_to_move' => [],
            'categories_to_delete' => [],
            'estimated_time' => 'fast_local_processing'
        ];

        // Jeśli są główne kategorie w strukturze
        if (isset($structure['proposed_structure']['main_categories'])) {
            foreach ($structure['proposed_structure']['main_categories'] as $main_category) {
                $mapping['categories_to_create'][] = [
                    'name' => $main_category['name'],
                    'slug' => sanitize_title($main_category['name']),
                    'description' => $main_category['description'] ?? '',
                    'parent' => 0,
                    'ai_generated' => true,
                    'priority' => 'high'
                ];

                // Podkategorie (jeśli są)
                if (!empty($main_category['subcategories'])) {
                    foreach ($main_category['subcategories'] as $subcategory) {
                        $mapping['categories_to_create'][] = [
                            'name' => $subcategory['name'],
                            'slug' => sanitize_title($subcategory['name']),
                            'description' => $subcategory['description'] ?? '',
                            'parent' => sanitize_title($main_category['name']),
                            'ai_generated' => true,
                            'priority' => 'medium'
                        ];
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Pobiera ograniczoną próbkę produktów dla szybkiej analizy
     */
    private function get_limited_products_sample(int $max_products = 100): array
    {
        $products = wc_get_products([
            'limit' => $max_products,
            'status' => 'publish',
            'orderby' => 'rand' // Losowa próbka
        ]);

        $products_data = [];
        foreach ($products as $product) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);

            $products_data[] = [
                'id' => $product->get_id(),
                'name' => substr($this->sanitize_utf8($product->get_name()), 0, 50), // Skróć nazwy
                'sku' => $this->sanitize_utf8($product->get_sku()),
                'price' => $product->get_price(),
                'categories' => array_slice($this->sanitize_utf8_array($categories), 0, 3) // Max 3 kategorie
            ];
        }

        return $products_data;
    }

    /**
     * Pobiera tylko kluczowe kategorie (z produktami)
     */
    private function get_key_categories(): array
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true, // Tylko z produktami
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 50 // Max 50 kategorii
        ]);

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count,
                'parent' => $category->parent
            ];
        }

        return $result;
    }

    /**
     * Wyodrębnia szybkie wzorce z próbki produktów
     */
    private function extract_quick_patterns(array $products): array
    {
        $patterns = [
            'common_words' => [],
            'price_range' => ['min' => 0, 'max' => 0, 'avg' => 0],
            'categories_count' => 0
        ];

        // Tylko podstawowe statystyki
        $prices = array_filter(array_column($products, 'price'));
        if (!empty($prices)) {
            $patterns['price_range'] = [
                'min' => min($prices),
                'max' => max($prices),
                'avg' => array_sum($prices) / count($prices)
            ];
        }

        // Top 10 najczęstszych słów
        $all_names = implode(' ', array_column($products, 'name'));
        $words = str_word_count(strtolower($all_names), 1);
        $word_counts = array_count_values($words);
        arsort($word_counts);
        $patterns['common_words'] = array_slice($word_counts, 0, 10);

        return $patterns;
    }

    /**
     * Tworzy szybki prompt dla fazy 1
     */
    private function create_quick_phase_1_prompt(array $data, string $context_description): string
    {
        return sprintf('SZYBKA ANALIZA PRÓBKI KATEGORII

KONTEKST: %s

DANE PRÓBKI:
- Produktów: %d (z %d+ ogółem)
- Kategorii: %d  
- Top słowa: %s
- Ceny: %.0f-%.0f zł

ZADANIE:
Szybka analiza próbki. Odpowiedz w JSON:

{
  "main_insights": ["3-4 główne typy produktów"],
  "category_problems": ["główne problemy z kategoriami"],
  "quick_recommendations": ["2-3 szybkie rekomendacje"]
}',
            $context_description ?: 'Sklep e-commerce',
            $data['sample_size'],
            $data['sample_size'] * 10,
            $data['categories_count'],
            implode(', ', array_slice(array_keys($data['quick_patterns']['common_words']), 0, 5)),
            $data['quick_patterns']['price_range']['min'],
            $data['quick_patterns']['price_range']['max']
        );
    }

    /**
     * Tworzy szybki prompt dla fazy 2
     */
    private function create_quick_phase_2_prompt(array $analysis, string $context_description): string
    {
        return sprintf('SZYBKA HIERARCHIA KATEGORII

ANALIZA: %s

ZADANIE:
Stwórz PROSTĄ hierarchię. Max 5 głównych kategorii.

JSON:
{
  "proposed_structure": {
    "main_categories": [
      {
        "name": "Nazwa głównej kategorii",
        "description": "Krótki opis",
        "subcategories": [
          {
            "name": "Podkategoria",
            "description": "Opis"
          }
        ]
      }
    ]
  }
}',
            json_encode($analysis['main_insights'] ?? [], JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * FAZA 1: Globalna analiza wszystkich produktów i kategorii
     */
    private function phase_1_global_analysis(string $context_description): array
    {
        try {
            // Pobierz WSZYSTKIE produkty i kategorie dla pełnej analizy
            $all_products = $this->get_all_products_for_analysis();
            $all_categories = $this->get_all_categories();

            $this->logger->info("📈 Analizuję {count($all_products)} produktów w {count($all_categories)} kategoriach");

            // Przygotuj dane dla AI
            $analysis_data = [
                'total_products' => count($all_products),
                'total_categories' => count($all_categories),
                'products_sample' => array_slice($all_products, 0, 200), // Próbka dla AI
                'categories_full' => $all_categories,
                'product_patterns' => $this->extract_product_patterns($all_products),
                'category_relationships' => $this->analyze_category_relationships($all_categories)
            ];

            // Prompt dla fazy 1 - analiza globalna
            $prompt = $this->create_phase_1_prompt($analysis_data, $context_description);

            $response = $this->get_openai_client()->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Jesteś ekspertem e-commerce z 15-letnim doświadczeniem w organizacji katalogów produktów. Twoja specjalność to tworzenie logicznych, nie-powtarzających się hierarchii kategorii.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $this->max_tokens * 2, // Więcej tokenów dla szczegółowej analizy
                'temperature' => 0.1, // Niska temperatura dla precyzyjnej analizy
            ]);

            $response_content = $response->choices[0]->message->content;
            $analysis_result = json_decode($response_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON odpowiedzi fazy 1: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $analysis_result,
                'tokens_used' => $response->usage->totalTokens ?? 0
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd w fazie 1: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * FAZA 2: Tworzenie inteligentnej hierarchii kategorii
     */
    private function phase_2_intelligent_hierarchy(array $global_analysis, string $context_description): array
    {
        try {
            // Na podstawie globalnej analizy, stwórz logiczną hierarchię
            $prompt = $this->create_phase_2_prompt($global_analysis, $context_description);

            $response = $this->get_openai_client()->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Na podstawie przeprowadzonej analizy globalnej, stwórz JEDNĄ spójną hierarchię kategorii. ŻADNYCH POWTÓRZEŃ! Każdy produkt może należeć tylko do jednej głównej ścieżki kategorii.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $this->max_tokens * 1.5,
                'temperature' => 0.2,
            ]);

            $response_content = $response->choices[0]->message->content;
            $hierarchy_result = json_decode($response_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON odpowiedzi fazy 2: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $hierarchy_result,
                'tokens_used' => $response->usage->totalTokens ?? 0
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd w fazie 2: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * FAZA 3: Mapowanie produktów do nowej struktury
     */
    private function phase_3_product_mapping(array $intelligent_structure, array $global_analysis): array
    {
        // Stwórz szczegółowe mapowanie produktów do nowych kategorii
        $mapping = [
            'categories_to_create' => [],
            'products_to_move' => [],
            'categories_to_delete' => [],
            'unmapped_products' => []
        ];

        // Przeanalizuj każdą główną kategorię z nowej struktury
        foreach ($intelligent_structure['proposed_structure']['main_categories'] as $main_category) {
            $mapping['categories_to_create'][] = [
                'name' => $main_category['name'],
                'slug' => sanitize_title($main_category['name']),
                'description' => $main_category['description'],
                'parent' => 0,
                'ai_generated' => true
            ];

            // Dodaj podkategorie
            if (!empty($main_category['subcategories'])) {
                foreach ($main_category['subcategories'] as $subcategory) {
                    $mapping['categories_to_create'][] = [
                        'name' => $subcategory['name'],
                        'slug' => sanitize_title($subcategory['name']),
                        'description' => $subcategory['description'],
                        'parent' => sanitize_title($main_category['name']),
                        'ai_generated' => true,
                        'merge_from' => $subcategory['merge_from'] ?? []
                    ];

                    // Pod-podkategorie
                    if (!empty($subcategory['subcategories'])) {
                        foreach ($subcategory['subcategories'] as $sub_subcategory) {
                            $mapping['categories_to_create'][] = [
                                'name' => $sub_subcategory['name'],
                                'slug' => sanitize_title($sub_subcategory['name']),
                                'description' => $sub_subcategory['description'],
                                'parent' => sanitize_title($subcategory['name']),
                                'ai_generated' => true,
                                'merge_from' => $sub_subcategory['merge_from'] ?? []
                            ];
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Pobiera wszystkie produkty dla analizy
     */
    private function get_all_products_for_analysis(): array
    {
        $products = wc_get_products([
            'limit' => -1,
            'status' => 'publish'
        ]);

        $products_data = [];
        foreach ($products as $product) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
            $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);

            $products_data[] = [
                'id' => $product->get_id(),
                'name' => $this->sanitize_utf8($product->get_name()),
                'sku' => $this->sanitize_utf8($product->get_sku()),
                'price' => $product->get_price(),
                'categories' => $this->sanitize_utf8_array($categories),
                'tags' => $this->sanitize_utf8_array($tags),
                'short_description' => $this->sanitize_utf8(wp_strip_all_tags(substr($product->get_short_description(), 0, 100)))
            ];
        }

        return $products_data;
    }

    /**
     * Wyodrębnia wzorce z produktów
     */
    private function extract_product_patterns(array $products): array
    {
        $patterns = [
            'price_ranges' => [],
            'common_keywords' => [],
            'category_clusters' => [],
            'sku_patterns' => []
        ];

        // Analiza zakresów cenowych
        $prices = array_filter(array_column($products, 'price'));
        if (!empty($prices)) {
            $patterns['price_ranges'] = [
                'min' => min($prices),
                'max' => max($prices),
                'avg' => array_sum($prices) / count($prices),
                'ranges' => [
                    'budget' => array_filter($prices, fn($p) => $p < 50),
                    'mid' => array_filter($prices, fn($p) => $p >= 50 && $p < 200),
                    'premium' => array_filter($prices, fn($p) => $p >= 200)
                ]
            ];
        }

        // Analiza słów kluczowych w nazwach
        $all_names = implode(' ', array_column($products, 'name'));
        $words = str_word_count(strtolower($all_names), 1);
        $word_counts = array_count_values($words);
        arsort($word_counts);
        $patterns['common_keywords'] = array_slice($word_counts, 0, 50);

        return $patterns;
    }

    /**
     * Analizuje relacje między kategoriami
     */
    private function analyze_category_relationships(array $categories): array
    {
        $relationships = [
            'parent_child' => [],
            'similar_names' => [],
            'overlapping_products' => []
        ];

        foreach ($categories as $category) {
            if ($category['parent'] > 0) {
                $parent = get_term($category['parent'], 'product_cat');
                $relationships['parent_child'][] = [
                    'parent' => $parent->name,
                    'child' => $category['name'],
                    'products' => $category['count']
                ];
            }
        }

        return $relationships;
    }

    /**
     * Tworzy prompt dla fazy 1 - analiza globalna
     */
    private function create_phase_1_prompt(array $data, string $context_description): string
    {
        return sprintf('FAZA 1: GLOBALNA ANALIZA PRODUKTÓW I KATEGORII

KONTEKST SKLEPU:
%s

DANE DO ANALIZY:
- Łącznie produktów: %d
- Łącznie kategorii: %d
- Najczęstsze słowa w nazwach produktów: %s
- Zakresy cenowe: %.2f - %.2f zł (średnia: %.2f zł)

PRZYKŁADOWE PRODUKTY (pierwsze 10):
%s

AKTUALNE KATEGORIE:
%s

ZADANIE:
Przeanalizuj GLOBALNIE wszystkie dane i odpowiedz w formacie JSON:

{
  "global_insights": {
    "main_product_types": ["główne typy produktów znalezione"],
    "natural_groupings": ["naturalne grupowania produktów"],
    "price_segments": ["segmenty cenowe"],
    "common_themes": ["wspólne motywy/tematy"]
  },
  "current_problems": {
    "duplicated_categories": ["kategorie które się powtarzają"],
    "illogical_hierarchy": ["problemy z hierarchią"],
    "missing_categories": ["brakujące kategorie dla produktów"],
    "empty_categories": ["puste kategorie"]
  },
  "categories_summary": {
    "total_analyzed": %d,
    "with_products": "liczba kategorii z produktami",
    "empty": "liczba pustych kategorii",
    "main_issues": ["główne problemy"]
  }
}',
            $context_description ?: 'Sklep internetowy z artykułami promocyjnymi i reklamowymi',
            $data['total_products'],
            $data['total_categories'],
            implode(', ', array_slice(array_keys($data['product_patterns']['common_keywords']), 0, 10)),
            $data['product_patterns']['price_ranges']['min'] ?? 0,
            $data['product_patterns']['price_ranges']['max'] ?? 0,
            $data['product_patterns']['price_ranges']['avg'] ?? 0,
            $this->format_products_for_prompt(array_slice($data['products_sample'], 0, 10)),
            $this->format_categories_for_prompt(array_slice($data['categories_full'], 0, 20)),
            $data['total_categories']
        );
    }

    /**
     * Tworzy prompt dla fazy 2 - tworzenie hierarchii
     */
    private function create_phase_2_prompt(array $global_analysis, string $context_description): string
    {
        return sprintf('FAZA 2: TWORZENIE INTELIGENTNEJ HIERARCHII

WYNIKI GLOBALNEJ ANALIZY:
%s

ZADANIE:
Na podstawie przeprowadzonej analizy globalnej, stwórz JEDNĄ spójną hierarchię kategorii.

ZASADY:
1. ŻADNYCH POWTÓRZEŃ - jeśli jest "Długopisy" jako podkategoria, to NIE może być osobnej głównej kategorii "Długopisy"
2. Maksymalnie %d poziomy głębokości
3. Logiczna hierarchia: Główna → Podkategoria → Pod-podkategoria
4. Każdy produkt należy do JEDNEJ ścieżki kategorii
5. Użyj naturalnych grupowań znalezionych w analizie globalnej

ODPOWIEDZ W FORMACIE JSON:
{
  "proposed_structure": {
    "main_categories": [
      {
        "name": "Nazwa głównej kategorii",
        "description": "Opis kategorii",
        "expected_products": "liczba produktów",
        "subcategories": [
          {
            "name": "Nazwa podkategorii", 
            "description": "Opis podkategorii",
            "merge_from": ["obecne kategorie do scalenia"],
            "subcategories": [
              {
                "name": "Nazwa pod-podkategorii",
                "description": "Opis",
                "merge_from": ["kategorie do scalenia"]
              }
            ]
          }
        ]
      }
    ]
  },
  "hierarchy_logic": {
    "reasoning": "wyjaśnienie logiki hierarchii",
    "avoided_duplications": ["jak uniknięto powtórzeń"],
    "main_principles": ["główne zasady użyte"]
  }
}',
            json_encode($global_analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            $this->max_hierarchy_depth
        );
    }

    /**
     * Zapisuje wynik inteligentnej analizy z metadanymi AI
     */
    private function save_intelligent_analysis_result(array $result): void
    {
        // Zapisz główny wynik
        update_option('mhi_ai_category_analysis', $result);

        // Zapisz metadane AI dla systemu pamięci
        $ai_memory = get_option('mhi_ai_category_memory', []);
        $ai_memory[] = [
            'timestamp' => current_time('mysql'),
            'analysis_id' => uniqid('ai_analysis_'),
            'categories_created' => count($result['product_mapping']['categories_to_create'] ?? []),
            'analysis_version' => $result['ai_metadata']['analysis_version'],
            'tokens_used' => $result['ai_metadata']['total_tokens_used']
        ];

        // Zachowaj tylko ostatnie 10 analiz w pamięci
        if (count($ai_memory) > 10) {
            $ai_memory = array_slice($ai_memory, -10);
        }

        update_option('mhi_ai_category_memory', $ai_memory);
    }

    /**
     * Formatuje produkty dla prompt'a
     */
    private function format_products_for_prompt(array $products): string
    {
        $formatted = [];
        foreach ($products as $product) {
            $formatted[] = sprintf(
                '- %s (SKU: %s, Cena: %s zł, Kategorie: %s)',
                $product['name'],
                $product['sku'] ?: 'brak',
                $product['price'] ?: '0',
                implode(', ', $product['categories'])
            );
        }
        return implode("\n", $formatted);
    }

    /**
     * Formatuje kategorie dla prompt'a
     */
    private function format_categories_for_prompt(array $categories): string
    {
        $formatted = [];
        foreach ($categories as $category) {
            $parent_info = $category['parent'] > 0 ? ' (podkategoria)' : ' (główna)';
            $formatted[] = sprintf(
                '- %s%s: %d produktów',
                $category['name'],
                $parent_info,
                $category['count']
            );
        }
        return implode("\n", $formatted);
    }

    /**
     * Analizuje wszystkie kategorie produktów w WooCommerce
     *
     * @param string $context_description Opis kontekstu sklepu
     * @param array $custom_settings Niestandardowe ustawienia analizy
     * @return array Wynik analizy
     */
    public function analyze_categories(string $context_description = '', array $custom_settings = []): array
    {
        try {
            $this->logger->info('Rozpoczęcie analizy kategorii przez AI');

            // Sprawdź czy klucz API jest ustawiony
            if (empty($this->api_key)) {
                throw new Exception('Klucz API OpenAI nie został skonfigurowany. Ustaw klucz w ustawieniach wtyczki.');
            }

            $this->logger->info('Klucz API jest ustawiony, rozpoczynanie analizy...');

            // Zastosuj niestandardowe ustawienia jeśli podane
            $this->apply_custom_settings($custom_settings);

            // Pobranie wszystkich kategorii
            $categories = $this->get_all_categories();
            $this->logger->info('Znaleziono ' . count($categories) . ' kategorii do analizy');

            // Pobranie przykładowych produktów dla każdej kategorii
            $categories_with_products = $this->enrich_categories_with_products($categories);

            // Sprawdź czy użyć chunking dla dużej liczby kategorii
            if ($this->enable_chunking && count($categories_with_products) > $this->chunk_size) {
                return $this->analyze_categories_in_chunks($categories_with_products, $context_description);
            }

            // Standardowa analiza
            return $this->perform_single_analysis($categories_with_products, $context_description);

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas analizy kategorii: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Stosuje niestandardowe ustawienia dla analizy
     *
     * @param array $settings
     */
    private function apply_custom_settings(array $settings): void
    {
        if (isset($settings['max_hierarchy_depth'])) {
            $this->max_hierarchy_depth = (int) $settings['max_hierarchy_depth'];
        }
        if (isset($settings['min_products_for_subcategory'])) {
            $this->min_products_for_subcategory = (int) $settings['min_products_for_subcategory'];
        }
        if (isset($settings['products_per_category'])) {
            $this->products_per_category = (int) $settings['products_per_category'];
        }
        if (isset($settings['include_product_descriptions'])) {
            $this->include_product_descriptions = (bool) $settings['include_product_descriptions'];
        }
    }

    /**
     * Analizuje kategorie w mniejszych częściach (chunks)
     *
     * @param array $categories
     * @param string $context_description
     * @return array
     */
    private function analyze_categories_in_chunks(array $categories, string $context_description): array
    {
        $this->logger->info('Rozpoczynanie analizy w częściach - ' . count($categories) . ' kategorii');

        $chunks = array_chunk($categories, $this->chunk_size);
        $all_results = [];
        $total_tokens = 0;

        foreach ($chunks as $index => $chunk) {
            $this->logger->info('Analizowanie części ' . ($index + 1) . '/' . count($chunks));

            $chunk_result = $this->perform_single_analysis($chunk, $context_description, $index + 1);

            if ($chunk_result['success']) {
                $all_results[] = $chunk_result['data'];
                $total_tokens += $chunk_result['tokens_used'] ?? 0;
            } else {
                $this->logger->error('Błąd w części ' . ($index + 1) . ': ' . $chunk_result['error']);
            }

            // Krótka przerwa między zapytaniami aby uniknąć rate limitów
            sleep(2);
        }

        // Scalenie wyników z wszystkich części
        $merged_result = $this->merge_chunk_results($all_results);

        // Zapisanie scalonego wyniku
        $this->save_analysis_result($merged_result);

        return [
            'success' => true,
            'data' => $merged_result,
            'tokens_used' => $total_tokens,
            'chunks_processed' => count($chunks)
        ];
    }

    /**
     * Wykonuje pojedynczą analizę dla grupy kategorii
     *
     * @param array $categories
     * @param string $context_description
     * @param int $chunk_number
     * @return array
     */
    private function perform_single_analysis(array $categories, string $context_description, int $chunk_number = 0): array
    {
        // Przygotowanie prompta dla AI
        $prompt = $this->prepare_analysis_prompt($categories, $context_description, $chunk_number);

        // Sanityzacja UTF-8 przed wysłaniem
        $clean_prompt = $this->sanitize_utf8($prompt);
        $clean_system_prompt = $this->sanitize_utf8($this->get_system_prompt());

        // Sprawdź czy prompt nie jest pusty po sanityzacji
        if (empty($clean_prompt) || mb_strlen($clean_prompt) < 10) {
            throw new Exception('Prompt jest pusty lub za krótki po sanityzacji UTF-8');
        }

        // Wysłanie zapytania do OpenAI
        $this->logger->info('Przygotowywanie klienta OpenAI...');
        $client = $this->get_openai_client();

        $chunk_info = $chunk_number > 0 ? " (część $chunk_number)" : "";
        $this->logger->info("Wysyłanie zapytania do OpenAI API$chunk_info...");

        try {
            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $clean_system_prompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $clean_prompt
                    ]
                ],
                'max_tokens' => $this->max_tokens,
                'temperature' => $this->temperature,
                'response_format' => ['type' => 'json_object']
            ]);
        } catch (Exception $e) {
            $this->logger->error('Błąd OpenAI API: ' . $e->getMessage());
            throw new Exception('Błąd podczas komunikacji z OpenAI: ' . $e->getMessage());
        }

        $this->logger->info("Otrzymano odpowiedź z OpenAI API$chunk_info");
        $analysis_result = json_decode($response->choices[0]->message->content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Błąd dekodowania JSON odpowiedzi: ' . json_last_error_msg());
        }

        if ($chunk_number === 0) {
            // Zapisanie wyniku analizy tylko dla pojedynczej analizy
            $this->save_analysis_result($analysis_result);
            $this->logger->info('Analiza kategorii zakończona pomyślnie');
        }

        return [
            'success' => true,
            'data' => $analysis_result,
            'tokens_used' => $response->usage->totalTokens ?? 0
        ];
    }

    /**
     * Scala wyniki z wielu części analizy
     *
     * @param array $chunk_results
     * @return array
     */
    private function merge_chunk_results(array $chunk_results): array
    {
        $merged = [
            'analysis' => [
                'current_issues' => [],
                'recommendations' => []
            ],
            'proposed_structure' => [
                'main_categories' => []
            ],
            'migration_plan' => [
                'categories_to_merge' => [],
                'categories_to_delete' => [],
                'new_categories' => []
            ]
        ];

        foreach ($chunk_results as $chunk) {
            // Scalanie problemów i rekomendacji
            if (isset($chunk['analysis']['current_issues'])) {
                $merged['analysis']['current_issues'] = array_merge(
                    $merged['analysis']['current_issues'],
                    $chunk['analysis']['current_issues']
                );
            }

            if (isset($chunk['analysis']['recommendations'])) {
                $merged['analysis']['recommendations'] = array_merge(
                    $merged['analysis']['recommendations'],
                    $chunk['analysis']['recommendations']
                );
            }

            // Scalanie struktury głównych kategorii
            if (isset($chunk['proposed_structure']['main_categories'])) {
                $merged['proposed_structure']['main_categories'] = array_merge(
                    $merged['proposed_structure']['main_categories'],
                    $chunk['proposed_structure']['main_categories']
                );
            }

            // Scalanie planu migracji
            if (isset($chunk['migration_plan']['categories_to_merge'])) {
                $merged['migration_plan']['categories_to_merge'] = array_merge(
                    $merged['migration_plan']['categories_to_merge'],
                    $chunk['migration_plan']['categories_to_merge']
                );
            }

            if (isset($chunk['migration_plan']['categories_to_delete'])) {
                $merged['migration_plan']['categories_to_delete'] = array_merge(
                    $merged['migration_plan']['categories_to_delete'],
                    $chunk['migration_plan']['categories_to_delete']
                );
            }

            if (isset($chunk['migration_plan']['new_categories'])) {
                $merged['migration_plan']['new_categories'] = array_merge(
                    $merged['migration_plan']['new_categories'],
                    $chunk['migration_plan']['new_categories']
                );
            }
        }

        // Usunięcie duplikatów
        $merged['analysis']['current_issues'] = array_unique($merged['analysis']['current_issues']);
        $merged['analysis']['recommendations'] = array_unique($merged['analysis']['recommendations']);
        $merged['migration_plan']['categories_to_delete'] = array_unique($merged['migration_plan']['categories_to_delete']);
        $merged['migration_plan']['new_categories'] = array_unique($merged['migration_plan']['new_categories']);

        return $merged;
    }

    /**
     * Oblicza średnią cenę produktów w kategorii
     *
     * @param string $category_slug
     * @return float
     */
    private function calculate_average_price(string $category_slug): float
    {
        $products = wc_get_products([
            'category' => [$category_slug],
            'limit' => 50, // Próbka do obliczenia średniej
            'status' => 'publish'
        ]);

        if (empty($products)) {
            return 0.0;
        }

        $total_price = 0;
        $count = 0;

        foreach ($products as $product) {
            $price = $product->get_price();
            if ($price > 0) {
                $total_price += (float) $price;
                $count++;
            }
        }

        return $count > 0 ? $total_price / $count : 0.0;
    }

    /**
     * Sprawdza czy kategoria ma podkategorie
     *
     * @param int $category_id
     * @return bool
     */
    private function has_subcategories(int $category_id): bool
    {
        $subcategories = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $category_id,
            'hide_empty' => false
        ]);

        return !empty($subcategories) && !is_wp_error($subcategories);
    }

    /**
     * Pobiera głębokość kategorii w hierarchii
     *
     * @param int $category_id
     * @return int
     */
    private function get_category_depth(int $category_id): int
    {
        $depth = 0;
        $current_id = $category_id;

        while ($current_id > 0) {
            $category = get_term($current_id, 'product_cat');
            if (is_wp_error($category) || !$category) {
                break;
            }

            $current_id = $category->parent;
            $depth++;
        }

        return $depth - 1; // Odejmij 1 bo liczymy od 0
    }

    /**
     * Pobiera wszystkie kategorie produktów WooCommerce
     *
     * @return array
     */
    private function get_all_categories(): array
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC'
        ]);

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent' => $category->parent,
                'count' => $category->count
            ];
        }

        return $result;
    }

    /**
     * Wzbogaca kategorie o przykładowe produkty
     *
     * @param array $categories
     * @return array
     */
    private function enrich_categories_with_products(array $categories): array
    {
        foreach ($categories as &$category) {
            // Napraw licznik kategorii - pobierz rzeczywistą liczbę produktów
            $real_product_count = $this->get_real_product_count($category['slug']);
            $category['count'] = $real_product_count;

            $products = wc_get_products([
                'category' => [$category['slug']],
                'limit' => $this->products_per_category,
                'status' => 'publish'
            ]);

            $category['sample_products'] = [];
            foreach ($products as $product) {
                // Zabezpieczenie przed błędami UTF-8
                $product_name = $this->sanitize_utf8($product->get_name());
                $product_sku = $this->sanitize_utf8($product->get_sku());

                $product_data = [
                    'name' => $product_name,
                    'categories' => $this->sanitize_utf8_array(wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names'])),
                    'price' => $product->get_price(),
                    'sku' => $product_sku
                ];

                // Uwzględnij opis produktu jeśli włączone w ustawieniach
                if ($this->include_product_descriptions) {
                    $description = wp_strip_all_tags($product->get_short_description());
                    if (empty($description)) {
                        $description = wp_strip_all_tags(substr($product->get_description(), 0, 200));
                    }
                    $product_data['description'] = $this->sanitize_utf8($description);
                }

                $category['sample_products'][] = $product_data;
            }

            // Dodaj statystyki kategorii
            $category['statistics'] = [
                'total_products' => $real_product_count,
                'avg_price' => $this->calculate_average_price($category['slug']),
                'has_subcategories' => $this->has_subcategories($category['id']),
                'depth_level' => $this->get_category_depth($category['id'])
            ];
        }

        return $categories;
    }

    /**
     * Pobiera rzeczywistą liczbę produktów w kategorii
     *
     * @param string $category_slug
     * @return int
     */
    private function get_real_product_count(string $category_slug): int
    {
        $products = wc_get_products([
            'category' => [$category_slug],
            'status' => 'publish',
            'limit' => -1,
            'return' => 'ids'
        ]);

        return count($products);
    }

    /**
     * Sanityzuje string do prawidłowego UTF-8
     *
     * @param string $text
     * @return string
     */
    private function sanitize_utf8(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Usuń nieprawidłowe znaki UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Usuń kontrolne znaki
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Usuń nieprawidłowe sekwencje UTF-8
        $text = filter_var($text, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

        return trim($text);
    }

    /**
     * Sanityzuje tablicę stringów do prawidłowego UTF-8
     *
     * @param array $array
     * @return array
     */
    private function sanitize_utf8_array(array $array): array
    {
        return array_map([$this, 'sanitize_utf8'], $array);
    }

    /**
     * Przygotowuje prompt systemu dla AI
     *
     * @return string
     */
    private function get_system_prompt(): string
    {
        return sprintf('Jesteś ekspertem e-commerce specjalizującym się w organizacji kategorii produktów. 
Twoim zadaniem jest analiza struktury kategorii sklepu internetowego i zaproponowanie lepszej organizacji.

USTAWIENIA ANALIZY:
- Maksymalna głębokość hierarchii: %d poziomów
- Minimalna liczba produktów dla utworzenia podkategorii: %d
- Uwzględnianie opisów produktów: %s
- Liczba produktów do analizy per kategoria: %d

ZASADY:
1. Analizuj podobieństwa między kategoriami i produktami na podstawie nazw, opisów, cen i SKU
2. Grupuj podobne kategorie w logiczne nadkategorie
3. Twórz podkategorie dla kategorii z dużą liczbą produktów (>%d)
4. Eliminuj redundantne kategorie
5. Twórz czytelną hierarchię do %d poziomów głębokości
6. Uwzględniaj SEO i user experience
7. Pamiętaj o polskim kontekście językowym
8. Rozważ tworzenie głębszej hierarchii dla kategorii jak "Torby" (torby bawełniane, plecaki, worki itp.)
9. Uwzględniaj statystyki: liczbę produktów, średnie ceny, obecne podkategorie

ZAWSZE odpowiadaj w formacie JSON z następującą strukturą:
{
  "analysis": {
    "current_issues": ["lista problemów z obecną strukturą"],
    "recommendations": ["lista szczegółowych rekomendacji"],
    "statistics_summary": "podsumowanie statystyk kategorii"
  },
  "proposed_structure": {
    "main_categories": [
      {
        "name": "Nazwa głównej kategorii",
        "description": "Opis kategorii",
        "expected_product_count": "szacowana liczba produktów",
        "subcategories": [
          {
            "name": "Nazwa podkategorii",
            "description": "Opis podkategorii",
            "merge_from": ["kategorie do scalenia"],
            "subcategories": [
              {
                "name": "Nazwa pod-podkategorii",
                "description": "Opis pod-podkategorii",
                "merge_from": ["kategorie do scalenia"]
              }
            ]
          }
        ]
      }
    ]
  },
  "migration_plan": {
    "categories_to_merge": [
      {
        "target": "docelowa kategoria",
        "sources": ["kategorie źródłowe"],
        "reason": "powód scalenia",
        "estimated_products": "liczba produktów do przeniesienia"
      }
    ],
    "categories_to_delete": ["kategorie do usunięcia"],
    "new_categories": [
      {
        "name": "nazwa nowej kategorii",
        "parent": "kategoria nadrzędna",
        "reason": "powód utworzenia"
      }
    ]
  }
}',
            $this->max_hierarchy_depth,
            $this->min_products_for_subcategory,
            $this->include_product_descriptions ? 'TAK' : 'NIE',
            $this->products_per_category,
            $this->min_products_for_subcategory,
            $this->max_hierarchy_depth
        );
    }

    /**
     * Przygotowuje prompt dla analizy kategorii
     *
     * @param array $categories
     * @param string $context_description
     * @param int $chunk_number
     * @return string
     */
    private function prepare_analysis_prompt(array $categories, string $context_description, int $chunk_number = 0): string
    {
        $prompt = "KONTEKST SKLEPU:\n";
        if (!empty($context_description)) {
            $prompt .= $context_description . "\n\n";
        } else {
            $prompt .= "Sklep internetowy z artykułami promocyjnymi i reklamowymi.\n\n";
        }

        if ($chunk_number > 0) {
            $prompt .= "UWAGA: To jest część $chunk_number analizy kategorii. Skup się na tej konkretnej grupie kategorii.\n\n";
        }

        $prompt .= "AKTUALNE KATEGORIE I PRODUKTY (Analizowanych: " . count($categories) . " kategorii):\n\n";

        foreach ($categories as $category) {
            $prompt .= "KATEGORIA: {$category['name']} (ID: {$category['id']})\n";
            $prompt .= "Opis: " . ($category['description'] ?: 'Brak opisu') . "\n";
            $prompt .= "Liczba produktów: {$category['count']}\n";

            // Dodaj statystyki
            if (isset($category['statistics'])) {
                $stats = $category['statistics'];
                $prompt .= "Średnia cena: " . number_format($stats['avg_price'], 2) . " PLN\n";
                $prompt .= "Poziom w hierarchii: {$stats['depth_level']}\n";
                $prompt .= "Ma podkategorie: " . ($stats['has_subcategories'] ? 'TAK' : 'NIE') . "\n";
            }

            if ($category['parent'] > 0) {
                $parent = get_term($category['parent']);
                if ($parent && !is_wp_error($parent)) {
                    $prompt .= "Kategoria nadrzędna: {$parent->name}\n";
                }
            }

            if (!empty($category['sample_products'])) {
                $prompt .= "Przykładowe produkty:\n";
                foreach ($category['sample_products'] as $product) {
                    $prompt .= "- {$product['name']}";

                    if (isset($product['price']) && $product['price'] > 0) {
                        $prompt .= " (Cena: {$product['price']} PLN)";
                    }

                    if (!empty($product['sku'])) {
                        $prompt .= " [SKU: {$product['sku']}]";
                    }

                    $prompt .= "\n";

                    if ($this->include_product_descriptions && !empty($product['description'])) {
                        $prompt .= "  Opis: " . substr($product['description'], 0, 200) . "...\n";
                    }

                    if (!empty($product['categories']) && count($product['categories']) > 1) {
                        $prompt .= "  Inne kategorie: " . implode(', ', $product['categories']) . "\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        if ($chunk_number > 0) {
            $prompt .= "\nPROSZĘ PRZEANALIZUJ POWYŻSZE KATEGORIE Z TEJ CZĘŚCI I ZAPROPONUJ ZMIANY. ";
            $prompt .= "Pamiętaj, że to jest część większej analizy - skup się na optymalizacji tej konkretnej grupy kategorii.";
        } else {
            $prompt .= "\nPROSZĘ PRZEANALIZUJ POWYŻSZE KATEGORIE I ZAPROPONUJ LEPSZĄ ORGANIZACJĘ. ";
            $prompt .= "Szczególnie zwróć uwagę na kategorie z dużą liczbą produktów, które mogłyby zostać podzielone na podkategorie (np. Torby -> Torby bawełniane, Plecaki, Worki).";
        }

        return $prompt;
    }

    /**
     * Zapisuje wynik analizy w bazie danych
     *
     * @param array $result
     */
    private function save_analysis_result(array $result): void
    {
        $analysis_data = [
            'timestamp' => current_time('mysql'),
            'result' => $result,
            'status' => 'pending'
        ];

        update_option('mhi_ai_category_analysis', $analysis_data);
    }

    /**
     * Pobiera ostatni wynik analizy
     *
     * @return array|null
     */
    public function get_latest_analysis(): ?array
    {
        return get_option('mhi_ai_category_analysis', null);
    }

    /**
     * Tworzy kopię zapasową obecnej struktury kategorii
     *
     * @return bool
     */
    public function create_backup(): bool
    {
        try {
            $categories = $this->get_all_categories();

            $backup_data = [
                'timestamp' => current_time('mysql'),
                'categories' => $categories,
                'version' => get_option('mhi_category_backup_version', 0) + 1
            ];

            $existing_backups = get_option('mhi_category_backups', []);
            $existing_backups[] = $backup_data;

            // Zachowaj tylko ostatnie 10 kopii zapasowych
            if (count($existing_backups) > 10) {
                $existing_backups = array_slice($existing_backups, -10);
            }

            update_option('mhi_category_backups', $existing_backups);
            update_option('mhi_category_backup_version', $backup_data['version']);

            $this->logger->info('Utworzono kopię zapasową kategorii #' . $backup_data['version']);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Błąd podczas tworzenia kopii zapasowej: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Przywraca kopię zapasową kategorii
     *
     * @param int $backup_version
     * @return bool
     */
    public function restore_backup(int $backup_version): bool
    {
        try {
            $backups = get_option('mhi_category_backups', []);
            $backup_to_restore = null;

            foreach ($backups as $backup) {
                if ($backup['version'] === $backup_version) {
                    $backup_to_restore = $backup;
                    break;
                }
            }

            if (!$backup_to_restore) {
                throw new Exception('Nie znaleziono kopii zapasowej #' . $backup_version);
            }

            // Usuń wszystkie obecne kategorie (oprócz uncategorized)
            $current_categories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false
            ]);

            foreach ($current_categories as $category) {
                if ($category->slug !== 'uncategorized') {
                    wp_delete_term($category->term_id, 'product_cat');
                }
            }

            // Przywróć kategorie z kopii zapasowej
            foreach ($backup_to_restore['categories'] as $category_data) {
                if ($category_data['slug'] !== 'uncategorized') {
                    wp_insert_term(
                        $category_data['name'],
                        'product_cat',
                        [
                            'description' => $category_data['description'],
                            'slug' => $category_data['slug'],
                            'parent' => $category_data['parent']
                        ]
                    );
                }
            }

            $this->logger->info('Przywrócono kopię zapasową kategorii #' . $backup_version);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Błąd podczas przywracania kopii zapasowej: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Implementuje zaproponowane zmiany w strukturze kategorii
     *
     * @param array $migration_plan
     * @return array
     */
    public function implement_changes(array $migration_plan): array
    {
        try {
            $this->logger->info('Rozpoczęcie implementacji zmian w kategoriach');

            // Utwórz kopię zapasową przed zmianami
            $this->create_backup();

            $results = [
                'success' => true,
                'changes_made' => [],
                'errors' => []
            ];

            // 1. Utwórz nowe kategorie
            if (!empty($migration_plan['new_categories'])) {
                foreach ($migration_plan['new_categories'] as $category_name) {
                    $result = wp_insert_term($category_name, 'product_cat');
                    if (is_wp_error($result)) {
                        $results['errors'][] = 'Błąd tworzenia kategorii "' . $category_name . '": ' . $result->get_error_message();
                    } else {
                        $results['changes_made'][] = 'Utworzono kategorię: ' . $category_name;
                    }
                }
            }

            // 2. Scal kategorie
            if (!empty($migration_plan['categories_to_merge'])) {
                foreach ($migration_plan['categories_to_merge'] as $merge_data) {
                    $this->merge_categories($merge_data, $results);
                }
            }

            // 3. Usuń niepotrzebne kategorie
            if (!empty($migration_plan['categories_to_delete'])) {
                foreach ($migration_plan['categories_to_delete'] as $category_name) {
                    $category = get_term_by('name', $category_name, 'product_cat');
                    if ($category) {
                        $deleted = wp_delete_term($category->term_id, 'product_cat');
                        if (is_wp_error($deleted)) {
                            $results['errors'][] = 'Błąd usuwania kategorii "' . $category_name . '": ' . $deleted->get_error_message();
                        } else {
                            $results['changes_made'][] = 'Usunięto kategorię: ' . $category_name;
                        }
                    }
                }
            }

            if (!empty($results['errors'])) {
                $results['success'] = false;
            }

            $this->logger->info('Zakończono implementację zmian w kategoriach');

            return $results;

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas implementacji zmian: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scala kategorie zgodnie z planem
     *
     * @param array $merge_data
     * @param array &$results
     */
    private function merge_categories(array $merge_data, array &$results): void
    {
        $target_category = get_term_by('name', $merge_data['target'], 'product_cat');

        if (!$target_category) {
            // Utwórz kategorię docelową jeśli nie istnieje
            $target_result = wp_insert_term($merge_data['target'], 'product_cat');
            if (is_wp_error($target_result)) {
                $results['errors'][] = 'Błąd tworzenia kategorii docelowej "' . $merge_data['target'] . '": ' . $target_result->get_error_message();
                return;
            }
            $target_category_id = $target_result['term_id'];
        } else {
            $target_category_id = $target_category->term_id;
        }

        // Przenieś produkty z kategorii źródłowych do docelowej
        foreach ($merge_data['sources'] as $source_name) {
            $source_category = get_term_by('name', $source_name, 'product_cat');
            if ($source_category) {
                $products = wc_get_products([
                    'category' => [$source_category->slug],
                    'limit' => -1
                ]);

                foreach ($products as $product) {
                    $current_categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
                    $current_categories = array_diff($current_categories, [$source_category->term_id]);
                    $current_categories[] = $target_category_id;

                    wp_set_post_terms($product->get_id(), $current_categories, 'product_cat');
                }

                $results['changes_made'][] = 'Przeniesiono produkty z "' . $source_name . '" do "' . $merge_data['target'] . '"';
            }
        }
    }

    /**
     * Eksportuje ustawienia analizy kategorii
     *
     * @return array
     */
    public function export_settings(): array
    {
        return [
            'analysis' => get_option('mhi_ai_category_analysis', null),
            'backups' => get_option('mhi_category_backups', []),
            'ai_settings' => [
                'api_key' => get_option('mhi_openai_api_key', ''),
                'model' => get_option('mhi_openai_model', 'gpt-4o'),
                'max_tokens' => get_option('mhi_openai_max_tokens', 8000),
                'temperature' => get_option('mhi_openai_temperature', 0.3),
                'max_hierarchy_depth' => get_option('mhi_max_hierarchy_depth', 4),
                'min_products_for_subcategory' => get_option('mhi_min_products_for_subcategory', 5),
                'include_product_descriptions' => get_option('mhi_include_product_descriptions', true),
                'products_per_category' => get_option('mhi_products_per_category', 5),
                'enable_chunking' => get_option('mhi_enable_chunking', true),
                'chunk_size' => get_option('mhi_chunk_size', 20)
            ]
        ];
    }

    /**
     * Importuje ustawienia analizy kategorii
     *
     * @param array $settings
     * @return bool
     */
    public function import_settings(array $settings): bool
    {
        try {
            if (isset($settings['analysis'])) {
                update_option('mhi_ai_category_analysis', $settings['analysis']);
            }

            if (isset($settings['backups'])) {
                update_option('mhi_category_backups', $settings['backups']);
            }

            if (isset($settings['ai_settings'])) {
                foreach ($settings['ai_settings'] as $key => $value) {
                    update_option('mhi_' . $key, $value);
                }
                // Ponownie zainicjalizuj ustawienia
                $this->init_settings();
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error('Błąd podczas importu ustawień: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Aktualizuje ustawienia analizy
     *
     * @param array $new_settings
     * @return bool
     */
    public function update_analysis_settings(array $new_settings): bool
    {
        try {
            $valid_settings = [
                'mhi_openai_model',
                'mhi_openai_max_tokens',
                'mhi_openai_temperature',
                'mhi_max_hierarchy_depth',
                'mhi_min_products_for_subcategory',
                'mhi_include_product_descriptions',
                'mhi_products_per_category',
                'mhi_enable_chunking',
                'mhi_chunk_size'
            ];

            foreach ($new_settings as $key => $value) {
                if (in_array($key, $valid_settings)) {
                    update_option($key, $value);
                }
            }

            // Ponownie zainicjalizuj ustawienia
            $this->init_settings();

            $this->logger->info('Ustawienia analizy zostały zaktualizowane');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas aktualizacji ustawień: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generuje raport z rekomendacjami na podstawie statystyk
     *
     * @return array
     */
    public function generate_recommendations_report(): array
    {
        $stats = $this->get_category_statistics();
        $recommendations = [];

        // Rekomendacje dotyczące pustych kategorii
        if ($stats['empty_categories'] > 0) {
            $recommendations[] = [
                'type' => 'empty_categories',
                'priority' => 'high',
                'title' => 'Usuń puste kategorie',
                'description' => "Znaleziono {$stats['empty_categories']} pustych kategorii. Rozważ ich usunięcie lub przeniesienie produktów.",
                'action' => 'cleanup'
            ];
        }

        // Rekomendacje dotyczące podziału kategorii
        if (!empty($stats['potential_for_subdivision'])) {
            $count = count($stats['potential_for_subdivision']);
            $recommendations[] = [
                'type' => 'subdivision',
                'priority' => 'medium',
                'title' => 'Podziel duże kategorie',
                'description' => "Znaleziono $count kategorii z dużą liczbą produktów, które mogłyby zostać podzielone na podkategorie.",
                'categories' => $stats['potential_for_subdivision'],
                'action' => 'subdivide'
            ];
        }

        // Rekomendacje dotyczące hierarchii
        if ($stats['max_depth'] > $this->max_hierarchy_depth) {
            $recommendations[] = [
                'type' => 'hierarchy_depth',
                'priority' => 'low',
                'title' => 'Zbyt głęboka hierarchia',
                'description' => "Obecna hierarchia ma {$stats['max_depth']} poziomów, a maksymalny zalecany to {$this->max_hierarchy_depth}.",
                'action' => 'flatten'
            ];
        } elseif ($stats['max_depth'] < 2) {
            $recommendations[] = [
                'type' => 'hierarchy_depth',
                'priority' => 'medium',
                'title' => 'Płaska struktura kategorii',
                'description' => 'Rozważ utworzenie hierarchii kategorii dla lepszej organizacji produktów.',
                'action' => 'organize'
            ];
        }

        // Rekomendacje dotyczące AI analizy
        if ($stats['total_categories'] > $this->chunk_size && !$this->enable_chunking) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'Włącz chunking dla dużych zbiorów',
                'description' => "Masz {$stats['total_categories']} kategorii. Włączenie chunking'u poprawi wydajność analizy AI.",
                'action' => 'enable_chunking'
            ];
        }

        return [
            'statistics' => $stats,
            'recommendations' => $recommendations,
            'generated_at' => current_time('mysql'),
            'next_analysis_recommended' => $this->should_recommend_analysis($stats)
        ];
    }

    /**
     * Sprawdza czy należy zarekomendować nową analizę
     *
     * @param array $stats
     * @return bool
     */
    private function should_recommend_analysis(array $stats): bool
    {
        $last_analysis = $this->get_latest_analysis();

        if (!$last_analysis) {
            return true;
        }

        // Jeśli ostatnia analiza była ponad tydzień temu
        $last_analysis_time = strtotime($last_analysis['timestamp']);
        $week_ago = strtotime('-1 week');

        if ($last_analysis_time < $week_ago) {
            return true;
        }

        // Jeśli jest dużo potencjalnych problemów
        $potential_issues = $stats['empty_categories'] + count($stats['potential_for_subdivision']);

        return $potential_issues > 10;
    }

    /**
     * Pobiera statystyki kategorii dla admina
     *
     * @return array
     */
    public function get_category_statistics(): array
    {
        // Sprawdź cache najpierw - cache na 5 minut
        $cached_stats = get_transient('mhi_category_statistics');
        if (false !== $cached_stats) {
            return $cached_stats;
        }

        // Dodaj timeout protection
        set_time_limit(60);

        try {
            // Szybka wersja bez pełnego wzbogacania produktami
            global $wpdb;

            // Podstawowe statystyki z bazy danych
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

            // Kategorie z dużą liczbą produktów (potencjał do podziału)
            $potential_subdivisions = $wpdb->get_results("
                SELECT tt.term_id, t.name, tt.count
                FROM {$wpdb->term_taxonomy} tt
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'product_cat' AND tt.count >= " . ($this->min_products_for_subcategory * 2) . "
                ORDER BY tt.count DESC
                LIMIT 20
            ");

            $stats = [
                'total_categories' => (int) $total_categories,
                'categories_with_products' => (int) $categories_with_products,
                'empty_categories' => (int) $total_categories - (int) $categories_with_products,
                'avg_products_per_category' => 0,
                'max_depth' => 3, // Domyślnie 3 - pełne obliczenie zbyt kosztowne
                'categories_by_depth' => [1 => 0, 2 => 0, 3 => 0],
                'categories_with_subcategories' => 0,
                'potential_for_subdivision' => []
            ];

            // Konwertuj wyniki do tablicy
            foreach ($potential_subdivisions as $cat) {
                $stats['potential_for_subdivision'][] = [
                    'name' => $cat->name,
                    'product_count' => (int) $cat->count,
                    'avg_price' => 0 // Uproszczone - nie obliczamy ceny
                ];
            }

            // Cache na 5 minut
            set_transient('mhi_category_statistics', $stats, 300);

            return $stats;

        } catch (Exception $e) {
            // Fallback w przypadku błędu
            $fallback_stats = [
                'total_categories' => 0,
                'categories_with_products' => 0,
                'empty_categories' => 0,
                'avg_products_per_category' => 0,
                'max_depth' => 0,
                'categories_by_depth' => [],
                'categories_with_subcategories' => 0,
                'potential_for_subdivision' => []
            ];

            // Cache nawet fallback na minutę
            set_transient('mhi_category_statistics', $fallback_stats, 60);

            return $fallback_stats;
        }
    }

    /**
     * Testuje połączenie z OpenAI API
     *
     * @return array
     */
    public function test_api_connection(): array
    {
        try {
            $this->logger->info('Rozpoczynanie testu połączenia z OpenAI API...');

            if (empty($this->api_key)) {
                throw new Exception('Klucz API OpenAI nie został skonfigurowany. Ustaw klucz w ustawieniach wtyczki.');
            }

            $this->logger->info('Tworzenie klienta OpenAI...');
            $client = $this->get_openai_client();

            $this->logger->info('Wysyłanie testowego zapytania...');
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Test połączenia. Odpowiedz krótko "OK".'
                    ]
                ],
                'max_tokens' => 10
            ]);

            $this->logger->info('Test połączenia zakończony pomyślnie');
            return [
                'success' => true,
                'message' => 'Połączenie z OpenAI API działa poprawnie',
                'model_used' => $response->model,
                'tokens_used' => $response->usage->totalTokens ?? 0
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd testu połączenia: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mapuje produkty bez kategorii do odpowiednich kategorii AI
     *
     * @return array Wynik mapowania
     */
    public function map_uncategorized_products(): array
    {
        try {
            $this->logger->info('🎯 Rozpoczęcie mapowania produktów bez kategorii');

            // Pobierz produkty bez kategorii
            $uncategorized_products = $this->get_uncategorized_products();

            if (empty($uncategorized_products)) {
                return [
                    'success' => true,
                    'message' => 'Wszystkie produkty są już skategoryzowane',
                    'mapped_products' => 0
                ];
            }

            // Pobierz ostatnią strukturę AI
            $ai_structure = $this->get_ai_generated_categories();

            if (empty($ai_structure)) {
                return [
                    'success' => false,
                    'error' => 'Brak struktury kategorii wygenerowanej przez AI. Uruchom najpierw analizę kategorii.'
                ];
            }

            // Użyj AI do mapowania produktów
            $mapping_result = $this->ai_map_products_to_categories($uncategorized_products, $ai_structure);

            if ($mapping_result['success']) {
                // Zastosuj mapowanie
                $applied_mappings = $this->apply_product_mappings($mapping_result['mappings']);

                $this->logger->info("✅ Zmapowano {$applied_mappings} produktów do kategorii AI");

                return [
                    'success' => true,
                    'mapped_products' => $applied_mappings,
                    'details' => $mapping_result['mappings']
                ];
            }

            return $mapping_result;

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas mapowania produktów: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mapuje nowe produkty z importów XML do istniejących kategorii AI
     *
     * @param array $new_categories Kategorie z XML
     * @return array Wynik mapowania
     */
    public function map_xml_categories_to_ai_structure(array $new_categories): array
    {
        try {
            $this->logger->info('📥 Rozpoczęcie mapowania kategorii XML do struktury AI');

            // Pobierz strukturę AI
            $ai_structure = $this->get_ai_generated_categories();

            if (empty($ai_structure)) {
                return [
                    'success' => false,
                    'error' => 'Brak struktury kategorii AI. Uruchom najpierw analizę kategorii.'
                ];
            }

            // Użyj AI do mapowania kategorii XML
            $mapping_result = $this->ai_map_xml_categories($new_categories, $ai_structure);

            if ($mapping_result['success']) {
                // Zapisz mapowanie dla przyszłego użycia
                $this->save_xml_mapping($mapping_result['mappings']);

                return [
                    'success' => true,
                    'mappings' => $mapping_result['mappings'],
                    'unmapped_categories' => $mapping_result['unmapped'] ?? []
                ];
            }

            return $mapping_result;

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas mapowania kategorii XML: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Czyści puste kategorie z XML po przeniesieniu produktów
     *
     * @return array Wynik czyszczenia
     */
    public function cleanup_empty_xml_categories(): array
    {
        try {
            $this->logger->info('🧹 Rozpoczęcie czyszczenia pustych kategorii XML');

            // Znajdź puste kategorie XML (nie wygenerowane przez AI)
            $empty_categories = $this->find_empty_xml_categories();

            if (empty($empty_categories)) {
                return [
                    'success' => true,
                    'message' => 'Brak pustych kategorii XML do usunięcia',
                    'deleted_categories' => 0
                ];
            }

            $deleted_count = 0;
            foreach ($empty_categories as $category_id) {
                // Upewnij się że kategoria nie jest wygenerowana przez AI
                if (!$this->is_ai_generated_category($category_id)) {
                    $result = wp_delete_term($category_id, 'product_cat');
                    if (!is_wp_error($result)) {
                        $deleted_count++;
                    }
                }
            }

            $this->logger->info("🗑️ Usunięto {$deleted_count} pustych kategorii XML");

            return [
                'success' => true,
                'deleted_categories' => $deleted_count,
                'details' => $empty_categories
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas czyszczenia kategorii: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera produkty bez kategorii
     */
    private function get_uncategorized_products(): array
    {
        $products = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_product_cat',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);

        // Dodatkowo sprawdź produkty które mogą mieć tylko pustą kategorię
        $additional_products = wc_get_products([
            'limit' => -1,
            'status' => 'publish'
        ]);

        $uncategorized = [];
        foreach ($additional_products as $product) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            if (empty($categories) || is_wp_error($categories)) {
                $uncategorized[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'short_description' => wp_strip_all_tags(substr($product->get_short_description(), 0, 100))
                ];
            }
        }

        return array_merge($products, $uncategorized);
    }

    /**
     * Pobiera kategorie wygenerowane przez AI
     */
    private function get_ai_generated_categories(): array
    {
        // Sprawdź czy mamy zapisane kategorie AI
        $ai_analysis = get_option('mhi_ai_category_analysis', null);

        if (!$ai_analysis || !isset($ai_analysis['intelligent_structure'])) {
            return [];
        }

        return $ai_analysis['intelligent_structure']['proposed_structure']['main_categories'] ?? [];
    }

    /**
     * Używa AI do mapowania produktów do kategorii
     */
    private function ai_map_products_to_categories(array $products, array $ai_categories): array
    {
        try {
            // Przygotuj strukturę kategorii do prompt'a
            $categories_structure = $this->format_ai_categories_for_prompt($ai_categories);

            // Przygotuj produkty do analizy (maksymalnie 50 naraz)
            $products_batch = array_slice($products, 0, 50);
            $products_formatted = $this->format_products_for_prompt($products_batch);

            $prompt = sprintf('MAPOWANIE PRODUKTÓW DO KATEGORII AI

DOSTĘPNE KATEGORIE (wygenerowane przez AI):
%s

PRODUKTY DO ZMAPOWANIA:
%s

ZADANIE:
Zmapuj każdy produkt do najbardziej odpowiedniej kategorii z dostępnych kategorii AI.

ODPOWIEDZ W FORMACIE JSON:
{
  "mappings": [
    {
      "product_id": "ID produktu",
      "product_name": "nazwa produktu",
      "assigned_category": "nazwa kategorii AI",
      "confidence": "wysoka/średnia/niska",
      "reasoning": "powód przypisania"
    }
  ]
}',
                $categories_structure,
                $products_formatted
            );

            $response = $this->get_openai_client()->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Jesteś ekspertem kategoryzacji produktów. Mapuj produkty do najbardziej logicznych kategorii na podstawie nazwy, opisu i cech produktu.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $this->max_tokens,
                'temperature' => 0.2,
            ]);

            $response_content = $response->choices[0]->message->content;
            $mapping_result = json_decode($response_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON mapowania: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'mappings' => $mapping_result['mappings'] ?? []
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Używa AI do mapowania kategorii XML do struktury AI
     */
    private function ai_map_xml_categories(array $xml_categories, array $ai_categories): array
    {
        try {
            $categories_structure = $this->format_ai_categories_for_prompt($ai_categories);
            $xml_formatted = implode("\n", array_map(function ($cat) {
                return "- {$cat['name']} ({$cat['count']} produktów)";
            }, $xml_categories));

            $prompt = sprintf('MAPOWANIE KATEGORII XML DO STRUKTURY AI

STRUKTURA AI:
%s

KATEGORIE Z XML:
%s

ZADANIE:
Zmapuj każdą kategorię XML do odpowiedniej kategorii AI.

ODPOWIEDZ W FORMACIE JSON:
{
  "mappings": [
    {
      "xml_category": "nazwa kategorii XML",
      "ai_category": "nazwa kategorii AI",
      "action": "merge/move",
      "confidence": "wysoka/średnia/niska"
    }
  ],
  "unmapped": ["kategorie XML które nie mają odpowiednika"]
}',
                $categories_structure,
                $xml_formatted
            );

            $response = $this->get_openai_client()->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Mapuj kategorie XML do istniejącej struktury AI, zachowując logikę kategoryzacji.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $this->max_tokens,
                'temperature' => 0.1,
            ]);

            $response_content = $response->choices[0]->message->content;
            $mapping_result = json_decode($response_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON mapowania XML: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'mappings' => $mapping_result['mappings'] ?? [],
                'unmapped' => $mapping_result['unmapped'] ?? []
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Zastosowuje mapowania produktów
     */
    private function apply_product_mappings(array $mappings): int
    {
        $applied_count = 0;

        foreach ($mappings as $mapping) {
            $product_id = $mapping['product_id'];
            $category_name = $mapping['assigned_category'];

            // Znajdź kategorię po nazwie
            $category = get_term_by('name', $category_name, 'product_cat');

            if ($category && !is_wp_error($category)) {
                // Przypisz produkt do kategorii
                $result = wp_set_post_terms($product_id, [$category->term_id], 'product_cat');

                if (!is_wp_error($result)) {
                    // Oznacz produkt jako zmapowany przez AI
                    update_post_meta($product_id, '_ai_categorized', current_time('mysql'));
                    update_post_meta($product_id, '_ai_category_confidence', $mapping['confidence']);
                    $applied_count++;
                }
            }
        }

        return $applied_count;
    }

    /**
     * Znajdzie puste kategorie XML (nie AI)
     */
    private function find_empty_xml_categories(): array
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'count' => 0 // Tylko puste kategorie
        ]);

        $empty_xml_categories = [];
        foreach ($categories as $category) {
            // Sprawdź czy kategoria nie jest wygenerowana przez AI
            if (!$this->is_ai_generated_category($category->term_id) && $category->count == 0) {
                $empty_xml_categories[] = $category->term_id;
            }
        }

        return $empty_xml_categories;
    }

    /**
     * Sprawdza czy kategoria jest wygenerowana przez AI
     */
    private function is_ai_generated_category(int $category_id): bool
    {
        $ai_generated = get_term_meta($category_id, '_ai_generated', true);
        return !empty($ai_generated);
    }

    /**
     * Formatuje kategorie AI dla prompt'a
     */
    private function format_ai_categories_for_prompt(array $categories): string
    {
        $formatted = [];

        foreach ($categories as $main_category) {
            $formatted[] = "📂 {$main_category['name']} - {$main_category['description']}";

            if (!empty($main_category['subcategories'])) {
                foreach ($main_category['subcategories'] as $subcategory) {
                    $formatted[] = "  └─ {$subcategory['name']} - {$subcategory['description']}";

                    if (!empty($subcategory['subcategories'])) {
                        foreach ($subcategory['subcategories'] as $sub_subcategory) {
                            $formatted[] = "    └─ {$sub_subcategory['name']} - {$sub_subcategory['description']}";
                        }
                    }
                }
            }
            $formatted[] = "";
        }

        return implode("\n", $formatted);
    }

    /**
     * Zapisuje mapowanie XML dla przyszłego użycia
     */
    private function save_xml_mapping(array $mappings): void
    {
        $existing_mappings = get_option('mhi_ai_xml_mappings', []);
        $existing_mappings[] = [
            'timestamp' => current_time('mysql'),
            'mappings' => $mappings
        ];

        // Zachowaj tylko ostatnie 20 mapowań
        if (count($existing_mappings) > 20) {
            $existing_mappings = array_slice($existing_mappings, -20);
        }

        update_option('mhi_ai_xml_mappings', $existing_mappings);
    }

    /**
     * Pobiera statystyki AI kategoryzacji
     */
    public function get_ai_categorization_stats(): array
    {
        $total_products = wc_get_products(['limit' => -1, 'return' => 'ids']);
        $ai_categorized = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_key' => '_ai_categorized',
            'fields' => 'ids'
        ]);

        $uncategorized = $this->get_uncategorized_products();
        $ai_categories = get_terms([
            'taxonomy' => 'product_cat',
            'meta_query' => [
                [
                    'key' => '_ai_generated',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        return [
            'total_products' => count($total_products),
            'ai_categorized_products' => count($ai_categorized),
            'uncategorized_products' => count($uncategorized),
            'ai_generated_categories' => count($ai_categories),
            'categorization_percentage' => count($total_products) > 0 ? (count($ai_categorized) / count($total_products)) * 100 : 0
        ];
    }
}
<?php
/**
 * AI Optymalizator kategorii - tworzy i optymalizuje strukturę kategorii
 *
 * @package MHI
 */

declare(strict_types=1);

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Category_AI_Optimizer
 * 
 * Zaawansowana optymalizacja kategorii przez AI z możliwością tworzenia nowych struktur
 */
class MHI_Category_AI_Optimizer
{
    /**
     * Logger
     *
     * @var MHI_Logger
     */
    private $logger;

    /**
     * Analizator kategorii
     *
     * @var MHI_AI_Category_Analyzer
     */
    private $analyzer;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->logger = new MHI_Logger();
        $this->analyzer = new MHI_AI_Category_Analyzer();
    }

    /**
     * Wykonuje głęboką analizę i optymalizację kategorii
     *
     * @param array $optimization_settings Ustawienia optymalizacji
     * @return array Wynik optymalizacji
     */
    public function deep_category_optimization(array $optimization_settings = []): array
    {
        try {
            $this->logger->info('Rozpoczęcie głębokiej optymalizacji kategorii przez AI');

            // Domyślne ustawienia optymalizacji
            $default_settings = [
                'create_new_categories' => true,
                'max_hierarchy_depth' => 5,
                'min_products_for_split' => 8,
                'analyze_product_similarity' => true,
                'optimize_for_seo' => true,
                'preserve_existing_assignments' => false,
                'aggressive_optimization' => false
            ];

            $settings = array_merge($default_settings, $optimization_settings);

            // Krok 1: Analiza produktów i ich podobieństwa
            $product_analysis = $this->analyze_product_relationships();

            // Krok 2: Analiza obecnych kategorii
            $category_analysis = $this->analyze_current_category_structure();

            // Krok 3: Generowanie optymalnej struktury
            $optimal_structure = $this->generate_optimal_structure($product_analysis, $category_analysis, $settings);

            // Krok 4: Tworzenie szczegółowego planu mapowania
            $mapping_plan = $this->create_detailed_mapping_plan($optimal_structure, $settings);

            $result = [
                'optimization_id' => uniqid('opt_'),
                'timestamp' => current_time('mysql'),
                'settings' => $settings,
                'product_analysis' => $product_analysis,
                'category_analysis' => $category_analysis,
                'optimal_structure' => $optimal_structure,
                'mapping_plan' => $mapping_plan,
                'performance_metrics' => $this->calculate_performance_metrics($optimal_structure)
            ];

            // Zapisz wynik optymalizacji
            $this->save_optimization_result($result);

            $this->logger->info('Głęboka optymalizacja kategorii zakończona pomyślnie');

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas głębokiej optymalizacji: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analizuje relacje między produktami
     *
     * @return array Analiza podobieństwa produktów
     */
    private function analyze_product_relationships(): array
    {
        $this->logger->info('Analizowanie relacji między produktami...');

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1
        ]);

        $relationships = [
            'by_name_similarity' => [],
            'by_price_range' => [],
            'by_attributes' => [],
            'by_categories' => [],
            'by_tags' => []
        ];

        // Grupuj produkty według podobieństwa nazw
        $relationships['by_name_similarity'] = $this->group_by_name_similarity($products);

        // Grupuj według zakresów cenowych
        $relationships['by_price_range'] = $this->group_by_price_ranges($products);

        // Grupuj według atrybutów produktów
        $relationships['by_attributes'] = $this->group_by_attributes($products);

        // Grupuj według współdzielonych kategorii
        $relationships['by_categories'] = $this->group_by_shared_categories($products);

        // Grupuj według tagów
        $relationships['by_tags'] = $this->group_by_tags($products);

        return $relationships;
    }

    /**
     * Grupuje produkty według podobieństwa nazw
     */
    private function group_by_name_similarity(array $products): array
    {
        $groups = [];
        $keywords = [];

        foreach ($products as $product) {
            $name = strtolower($product->get_name());
            $product_keywords = $this->extract_keywords($name);

            foreach ($product_keywords as $keyword) {
                if (!isset($keywords[$keyword])) {
                    $keywords[$keyword] = [];
                }
                $keywords[$keyword][] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price()
                ];
            }
        }

        // Filtruj grupy z co najmniej 3 produktami
        foreach ($keywords as $keyword => $products_list) {
            if (count($products_list) >= 3) {
                $groups[$keyword] = $products_list;
            }
        }

        return $groups;
    }

    /**
     * Wyodrębnia słowa kluczowe z nazwy produktu
     */
    private function extract_keywords(string $name): array
    {
        // Usuń typowe słowa wypełniające
        $stop_words = ['i', 'z', 'w', 'na', 'do', 'od', 'ze', 'dla', 'o', 'a', 'an', 'and', 'the', 'of', 'to', 'from', 'with'];

        // Podziel na słowa i wyfiltruj krótkie
        $words = preg_split('/[\s\-_.,;:()]+/', strtolower($name));
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $stop_words) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }

        return array_unique($keywords);
    }

    /**
     * Grupuje produkty według zakresów cenowych
     */
    private function group_by_price_ranges(array $products): array
    {
        $ranges = [
            '0-50' => [],
            '50-100' => [],
            '100-200' => [],
            '200-500' => [],
            '500-1000' => [],
            '1000+' => []
        ];

        foreach ($products as $product) {
            $price = (float) $product->get_price();

            if ($price <= 50) {
                $ranges['0-50'][] = $product->get_id();
            } elseif ($price <= 100) {
                $ranges['50-100'][] = $product->get_id();
            } elseif ($price <= 200) {
                $ranges['100-200'][] = $product->get_id();
            } elseif ($price <= 500) {
                $ranges['200-500'][] = $product->get_id();
            } elseif ($price <= 1000) {
                $ranges['500-1000'][] = $product->get_id();
            } else {
                $ranges['1000+'][] = $product->get_id();
            }
        }

        return array_filter($ranges, function ($range) {
            return count($range) > 0;
        });
    }

    /**
     * Grupuje produkty według atrybutów
     */
    private function group_by_attributes(array $products): array
    {
        $attribute_groups = [];

        foreach ($products as $product) {
            $attributes = $product->get_attributes();

            foreach ($attributes as $attribute_name => $attribute) {
                if (!isset($attribute_groups[$attribute_name])) {
                    $attribute_groups[$attribute_name] = [];
                }

                $values = $attribute->get_options();
                foreach ($values as $value) {
                    if (!isset($attribute_groups[$attribute_name][$value])) {
                        $attribute_groups[$attribute_name][$value] = [];
                    }
                    $attribute_groups[$attribute_name][$value][] = $product->get_id();
                }
            }
        }

        return $attribute_groups;
    }

    /**
     * Grupuje produkty według współdzielonych kategorii
     */
    private function group_by_shared_categories(array $products): array
    {
        $category_combinations = [];

        foreach ($products as $product) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
            sort($categories);

            $combination_key = implode('|', $categories);
            if (!isset($category_combinations[$combination_key])) {
                $category_combinations[$combination_key] = [];
            }
            $category_combinations[$combination_key][] = $product->get_id();
        }

        return array_filter($category_combinations, function ($products) {
            return count($products) > 1;
        });
    }

    /**
     * Grupuje produkty według tagów
     */
    private function group_by_tags(array $products): array
    {
        $tag_groups = [];

        foreach ($products as $product) {
            $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'slugs']);

            foreach ($tags as $tag) {
                if (!isset($tag_groups[$tag])) {
                    $tag_groups[$tag] = [];
                }
                $tag_groups[$tag][] = $product->get_id();
            }
        }

        return array_filter($tag_groups, function ($products) {
            return count($products) >= 3;
        });
    }

    /**
     * Analizuje obecną strukturę kategorii
     */
    private function analyze_current_category_structure(): array
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC'
        ]);

        $analysis = [
            'total_categories' => count($categories),
            'depth_distribution' => [],
            'size_distribution' => [],
            'orphaned_categories' => [],
            'oversized_categories' => [],
            'undersized_categories' => [],
            'potential_duplicates' => []
        ];

        foreach ($categories as $category) {
            $depth = $this->get_category_depth($category->term_id);
            $size = $category->count;

            // Dystrybucja głębokości
            if (!isset($analysis['depth_distribution'][$depth])) {
                $analysis['depth_distribution'][$depth] = 0;
            }
            $analysis['depth_distribution'][$depth]++;

            // Dystrybucja rozmiaru
            if ($size == 0) {
                $analysis['orphaned_categories'][] = $category->name;
            } elseif ($size > 50) {
                $analysis['oversized_categories'][] = [
                    'name' => $category->name,
                    'count' => $size
                ];
            } elseif ($size < 3) {
                $analysis['undersized_categories'][] = [
                    'name' => $category->name,
                    'count' => $size
                ];
            }

            // Rozmiar kategorii
            if ($size <= 5) {
                $size_range = '1-5';
            } elseif ($size <= 15) {
                $size_range = '6-15';
            } elseif ($size <= 50) {
                $size_range = '16-50';
            } else {
                $size_range = '50+';
            }

            if (!isset($analysis['size_distribution'][$size_range])) {
                $analysis['size_distribution'][$size_range] = 0;
            }
            $analysis['size_distribution'][$size_range]++;
        }

        // Znajdź potencjalne duplikaty
        $analysis['potential_duplicates'] = $this->find_potential_duplicate_categories($categories);

        return $analysis;
    }

    /**
     * Pobiera głębokość kategorii
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

        return $depth - 1;
    }

    /**
     * Znajduje potencjalne duplikaty kategorii
     */
    private function find_potential_duplicate_categories(array $categories): array
    {
        $duplicates = [];
        $names = [];

        foreach ($categories as $category) {
            $normalized_name = $this->normalize_category_name($category->name);

            if (!isset($names[$normalized_name])) {
                $names[$normalized_name] = [];
            }
            $names[$normalized_name][] = $category->name;
        }

        foreach ($names as $normalized => $category_names) {
            if (count($category_names) > 1) {
                $duplicates[] = $category_names;
            }
        }

        return $duplicates;
    }

    /**
     * Normalizuje nazwę kategorii do porównania
     */
    private function normalize_category_name(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-ząćęłńóśźż0-9]/', '', $name);
        return $name;
    }

    /**
     * Generuje optymalną strukturę kategorii
     */
    private function generate_optimal_structure(array $product_analysis, array $category_analysis, array $settings): array
    {
        $this->logger->info('Generowanie optymalnej struktury kategorii...');

        // Przygotuj prompt dla AI z detalami analizy
        $optimization_prompt = $this->prepare_optimization_prompt($product_analysis, $category_analysis, $settings);

        // Wywołaj AI do generowania struktury
        $ai_response = $this->call_ai_for_optimization($optimization_prompt);

        // Przetwórz odpowiedź AI
        $optimal_structure = $this->process_ai_optimization_response($ai_response, $product_analysis);

        return $optimal_structure;
    }

    /**
     * Przygotowuje prompt optymalizacyjny dla AI
     */
    private function prepare_optimization_prompt(array $product_analysis, array $category_analysis, array $settings): string
    {
        $prompt = "ZADANIE: ZAAWANSOWANA OPTYMALIZACJA KATEGORII SKLEPU\n\n";

        $prompt .= "USTAWIENIA OPTYMALIZACJI:\n";
        $prompt .= "- Tworzenie nowych kategorii: " . ($settings['create_new_categories'] ? 'TAK' : 'NIE') . "\n";
        $prompt .= "- Maksymalna głębokość hierarchii: {$settings['max_hierarchy_depth']}\n";
        $prompt .= "- Minimalna liczba produktów do podziału: {$settings['min_products_for_split']}\n";
        $prompt .= "- Optymalizacja SEO: " . ($settings['optimize_for_seo'] ? 'TAK' : 'NIE') . "\n";
        $prompt .= "- Agresywna optymalizacja: " . ($settings['aggressive_optimization'] ? 'TAK' : 'NIE') . "\n\n";

        $prompt .= "ANALIZA PRODUKTÓW:\n";
        $prompt .= "Znalezione grupy produktów według podobieństwa nazw:\n";
        foreach ($product_analysis['by_name_similarity'] as $keyword => $products) {
            $prompt .= "- '$keyword': " . count($products) . " produktów\n";
        }

        $prompt .= "\nZakresy cenowe produktów:\n";
        foreach ($product_analysis['by_price_range'] as $range => $products) {
            $prompt .= "- $range PLN: " . count($products) . " produktów\n";
        }

        if (!empty($product_analysis['by_attributes'])) {
            $prompt .= "\nGrupy według atrybutów:\n";
            foreach ($product_analysis['by_attributes'] as $attr_name => $values) {
                $prompt .= "- $attr_name: " . count($values) . " różnych wartości\n";
            }
        }

        $prompt .= "\nANALIZA OBECNYCH KATEGORII:\n";
        $prompt .= "- Całkowita liczba kategorii: {$category_analysis['total_categories']}\n";
        $prompt .= "- Puste kategorie: " . count($category_analysis['orphaned_categories']) . "\n";
        $prompt .= "- Zbyt duże kategorie (>50 produktów): " . count($category_analysis['oversized_categories']) . "\n";
        $prompt .= "- Zbyt małe kategorie (<3 produkty): " . count($category_analysis['undersized_categories']) . "\n";

        if (!empty($category_analysis['potential_duplicates'])) {
            $prompt .= "- Potencjalne duplikaty: " . count($category_analysis['potential_duplicates']) . "\n";
        }

        $prompt .= "\nINSTRUKCJE:\n";
        $prompt .= "1. STWÓRZ całkowicie nową, optymalną strukturę kategorii\n";
        $prompt .= "2. WYKORZYSTAJ analizę podobieństwa produktów do grupowania\n";
        $prompt .= "3. TWÓRZ hierarchię maksymalnie {$settings['max_hierarchy_depth']} poziomów\n";
        $prompt .= "4. DZIEL duże kategorie na sensowne podkategorie\n";
        $prompt .= "5. SCAL podobne małe kategorie\n";
        $prompt .= "6. TWÓRZ kategorie według wzorców: GŁÓWNA → PODKATEGORIA → POD-PODKATEGORIA\n";
        $prompt .= "7. PRZYKŁAD hierarchii dla Toreb: Torby → Torby bawełniane, Plecaki, Worki → Plecaki sportowe, Plecaki szkolne\n\n";

        $prompt .= "ODPOWIEDZ W FORMACIE JSON ze szczegółową strukturą kategorii, mapowaniem produktów i uzasadnieniami.";

        return $prompt;
    }

    /**
     * Wywołuje AI do optymalizacji
     */
    private function call_ai_for_optimization(string $prompt): array
    {
        try {
            $client = $this->analyzer->get_openai_client();

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->get_optimization_system_prompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 12000,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object']
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON odpowiedzi AI: ' . json_last_error_msg());
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Błąd wywołania AI: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Pobiera prompt systemowy dla optymalizacji
     */
    private function get_optimization_system_prompt(): string
    {
        return 'Jesteś ekspertem w optymalizacji struktur kategorii e-commerce. Twoje zadanie to utworzenie najlepszej możliwej struktury kategorii na podstawie analizy produktów.

GŁÓWNE ZASADY:
1. Twórz logiczną hierarchię kategorii
2. Grupuj produkty według podobieństwa funkcjonalnego, a nie tylko nazwy
3. Uwzględniaj SEO - kategorie powinny mieć słowa kluczowe
4. Twórz kategorie różnej wielkości - od szerokich po bardzo specjalistyczne
5. Zapewnij łatwą nawigację dla użytkowników

STRUKTURA ODPOWIEDZI JSON:
{
  "optimization_summary": {
    "total_categories_created": 0,
    "max_hierarchy_depth": 0,
    "optimization_strategy": "opis strategii"
  },
  "category_structure": [
    {
      "name": "Nazwa kategorii głównej",
      "slug": "slug-kategorii",
      "description": "Opis kategorii",
      "seo_keywords": ["słowo1", "słowo2"],
      "expected_products": 0,
      "subcategories": [
        {
          "name": "Podkategoria",
          "slug": "slug-podkategorii", 
          "description": "Opis",
          "seo_keywords": ["słowo"],
          "expected_products": 0,
          "product_mapping_rules": {
            "include_keywords": ["słowa w nazwie"],
            "exclude_keywords": ["słowa do wykluczenia"],
            "price_range": {"min": 0, "max": 1000},
            "attributes": {"atrybut": ["wartość"]}
          },
          "subcategories": []
        }
      ]
    }
  ],
  "migration_strategy": {
    "phase_1": "opis pierwszej fazy",
    "phase_2": "opis drugiej fazy", 
    "phase_3": "opis trzeciej fazy"
  }
}';
    }

    /**
     * Przetwarza odpowiedź AI optymalizacji
     */
    private function process_ai_optimization_response(array $ai_response, array $product_analysis): array
    {
        // Waliduj i przetworz strukturę kategorii z AI
        $processed_structure = [
            'summary' => $ai_response['optimization_summary'] ?? [],
            'categories' => $ai_response['category_structure'] ?? [],
            'migration_strategy' => $ai_response['migration_strategy'] ?? []
        ];

        // Dodaj szczegółowe mapowanie produktów do każdej kategorii
        $processed_structure['detailed_mapping'] = $this->create_detailed_product_mapping(
            $processed_structure['categories'],
            $product_analysis
        );

        return $processed_structure;
    }

    /**
     * Tworzy szczegółowe mapowanie produktów
     */
    private function create_detailed_product_mapping(array $categories, array $product_analysis): array
    {
        $mapping = [];

        foreach ($categories as $category) {
            $mapping[$category['slug']] = $this->map_products_to_category($category, $product_analysis);

            // Rekurencyjnie mapuj podkategorie
            if (!empty($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    $mapping[$subcategory['slug']] = $this->map_products_to_category($subcategory, $product_analysis);
                }
            }
        }

        return $mapping;
    }

    /**
     * Mapuje produkty do konkretnej kategorii
     */
    private function map_products_to_category(array $category, array $product_analysis): array
    {
        $mapped_products = [];

        if (isset($category['product_mapping_rules'])) {
            $rules = $category['product_mapping_rules'];

            // Mapuj na podstawie słów kluczowych
            if (isset($rules['include_keywords'])) {
                foreach ($rules['include_keywords'] as $keyword) {
                    if (isset($product_analysis['by_name_similarity'][$keyword])) {
                        $mapped_products = array_merge($mapped_products, $product_analysis['by_name_similarity'][$keyword]);
                    }
                }
            }

            // Filtruj według zakresu cenowego
            if (isset($rules['price_range'])) {
                $mapped_products = array_filter($mapped_products, function ($product) use ($rules) {
                    $price = $product['price'] ?? 0;
                    return $price >= $rules['price_range']['min'] && $price <= $rules['price_range']['max'];
                });
            }
        }

        return array_unique($mapped_products, SORT_REGULAR);
    }

    /**
     * Tworzy szczegółowy plan mapowania
     */
    private function create_detailed_mapping_plan(array $optimal_structure, array $settings): array
    {
        $plan = [
            'execution_phases' => [],
            'product_movements' => [],
            'category_operations' => [],
            'validation_rules' => []
        ];

        // Faza 1: Przygotowanie
        $plan['execution_phases']['phase_1'] = [
            'name' => 'Przygotowanie',
            'description' => 'Tworzenie kopii zapasowej i walidacja danych',
            'operations' => [
                'create_backup',
                'validate_product_data',
                'check_dependencies'
            ]
        ];

        // Faza 2: Tworzenie nowych kategorii
        $plan['execution_phases']['phase_2'] = [
            'name' => 'Tworzenie kategorii',
            'description' => 'Utworzenie nowej struktury kategorii',
            'operations' => []
        ];

        foreach ($optimal_structure['categories'] as $category) {
            $plan['execution_phases']['phase_2']['operations'][] = [
                'type' => 'create_category',
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'] ?? ''
            ];

            // Dodaj podkategorie
            if (!empty($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    $plan['execution_phases']['phase_2']['operations'][] = [
                        'type' => 'create_subcategory',
                        'name' => $subcategory['name'],
                        'slug' => $subcategory['slug'],
                        'parent' => $category['slug'],
                        'description' => $subcategory['description'] ?? ''
                    ];
                }
            }
        }

        // Faza 3: Mapowanie produktów
        $plan['execution_phases']['phase_3'] = [
            'name' => 'Mapowanie produktów',
            'description' => 'Przypisanie produktów do nowych kategorii',
            'operations' => []
        ];

        if (isset($optimal_structure['detailed_mapping'])) {
            foreach ($optimal_structure['detailed_mapping'] as $category_slug => $products) {
                $plan['execution_phases']['phase_3']['operations'][] = [
                    'type' => 'assign_products',
                    'category' => $category_slug,
                    'products' => $products
                ];
            }
        }

        return $plan;
    }

    /**
     * Oblicza metryki wydajności optymalizacji
     */
    private function calculate_performance_metrics(array $optimal_structure): array
    {
        $metrics = [
            'total_categories' => 0,
            'hierarchy_depth' => 0,
            'category_balance_score' => 0,
            'seo_optimization_score' => 0,
            'user_experience_score' => 0
        ];

        // Policz kategorie i głębokość
        $metrics['total_categories'] = $this->count_total_categories($optimal_structure['categories']);
        $metrics['hierarchy_depth'] = $this->calculate_max_depth($optimal_structure['categories']);

        // Oblicz wyniki balansowania kategorii
        $metrics['category_balance_score'] = $this->calculate_balance_score($optimal_structure);

        // Oblicz wynik SEO
        $metrics['seo_optimization_score'] = $this->calculate_seo_score($optimal_structure);

        // Oblicz wynik user experience
        $metrics['user_experience_score'] = $this->calculate_ux_score($optimal_structure);

        return $metrics;
    }

    /**
     * Liczy całkowitą liczbę kategorii
     */
    private function count_total_categories(array $categories): int
    {
        $count = count($categories);

        foreach ($categories as $category) {
            if (!empty($category['subcategories'])) {
                $count += $this->count_total_categories($category['subcategories']);
            }
        }

        return $count;
    }

    /**
     * Oblicza maksymalną głębokość hierarchii
     */
    private function calculate_max_depth(array $categories, int $current_depth = 1): int
    {
        $max_depth = $current_depth;

        foreach ($categories as $category) {
            if (!empty($category['subcategories'])) {
                $depth = $this->calculate_max_depth($category['subcategories'], $current_depth + 1);
                $max_depth = max($max_depth, $depth);
            }
        }

        return $max_depth;
    }

    /**
     * Oblicza wynik balansowania kategorii
     */
    private function calculate_balance_score(array $structure): float
    {
        // Implementacja algorytmu oceniającego balans kategorii
        return 85.0; // Placeholder
    }

    /**
     * Oblicza wynik SEO
     */
    private function calculate_seo_score(array $structure): float
    {
        $score = 0;
        $total_categories = 0;

        foreach ($structure['categories'] as $category) {
            $total_categories++;

            // Punkty za słowa kluczowe SEO
            if (!empty($category['seo_keywords'])) {
                $score += count($category['seo_keywords']) * 10;
            }

            // Punkty za dobry opis
            if (!empty($category['description']) && strlen($category['description']) > 50) {
                $score += 15;
            }

            // Rekurencyjnie dla podkategorii
            if (!empty($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    $total_categories++;

                    if (!empty($subcategory['seo_keywords'])) {
                        $score += count($subcategory['seo_keywords']) * 10;
                    }

                    if (!empty($subcategory['description']) && strlen($subcategory['description']) > 50) {
                        $score += 15;
                    }
                }
            }
        }

        return $total_categories > 0 ? min(100, ($score / $total_categories)) : 0;
    }

    /**
     * Oblicza wynik user experience
     */
    private function calculate_ux_score(array $structure): float
    {
        // Implementacja algorytmu oceniającego UX
        return 90.0; // Placeholder
    }

    /**
     * Zapisuje wynik optymalizacji
     */
    private function save_optimization_result(array $result): void
    {
        $existing_optimizations = get_option('mhi_category_optimizations', []);
        $existing_optimizations[] = $result;

        // Zachowaj tylko ostatnie 10 optymalizacji
        if (count($existing_optimizations) > 10) {
            $existing_optimizations = array_slice($existing_optimizations, -10);
        }

        update_option('mhi_category_optimizations', $existing_optimizations);
    }

    /**
     * Pobiera listę optymalizacji
     */
    public function get_optimization_history(): array
    {
        return get_option('mhi_category_optimizations', []);
    }

    /**
     * Wykonuje optymalizację według ID
     */
    public function execute_optimization(string $optimization_id): array
    {
        $optimizations = $this->get_optimization_history();
        $optimization = null;

        foreach ($optimizations as $opt) {
            if ($opt['optimization_id'] === $optimization_id) {
                $optimization = $opt;
                break;
            }
        }

        if (!$optimization) {
            throw new Exception('Nie znaleziono optymalizacji o ID: ' . $optimization_id);
        }

        // Wykonaj plan mapowania
        return $this->execute_mapping_plan($optimization['mapping_plan']);
    }

    /**
     * Wykonuje plan mapowania
     */
    private function execute_mapping_plan(array $mapping_plan): array
    {
        $results = [
            'success' => true,
            'executed_operations' => [],
            'errors' => []
        ];

        try {
            // Wykonaj operacje fazami
            foreach ($mapping_plan['execution_phases'] as $phase_name => $phase) {
                $this->logger->info("Wykonywanie fazy: $phase_name");

                foreach ($phase['operations'] as $operation) {
                    $operation_result = $this->execute_single_operation($operation);

                    if ($operation_result['success']) {
                        $results['executed_operations'][] = $operation;
                    } else {
                        $results['errors'][] = $operation_result['error'];
                    }
                }
            }

        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Wykonuje pojedynczą operację
     */
    private function execute_single_operation(array $operation): array
    {
        try {
            switch ($operation['type']) {
                case 'create_category':
                    return $this->create_category_operation($operation);

                case 'create_subcategory':
                    return $this->create_subcategory_operation($operation);

                case 'assign_products':
                    return $this->assign_products_operation($operation);

                default:
                    throw new Exception('Nieznany typ operacji: ' . $operation['type']);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Tworzy kategorię
     */
    private function create_category_operation(array $operation): array
    {
        $result = wp_insert_term(
            $operation['name'],
            'product_cat',
            [
                'description' => $operation['description'] ?? '',
                'slug' => $operation['slug']
            ]
        );

        if (is_wp_error($result)) {
            throw new Exception('Błąd tworzenia kategorii: ' . $result->get_error_message());
        }

        return ['success' => true, 'term_id' => $result['term_id']];
    }

    /**
     * Tworzy podkategorię
     */
    private function create_subcategory_operation(array $operation): array
    {
        $parent = get_term_by('slug', $operation['parent'], 'product_cat');

        if (!$parent) {
            throw new Exception('Nie znaleziono kategorii nadrzędnej: ' . $operation['parent']);
        }

        $result = wp_insert_term(
            $operation['name'],
            'product_cat',
            [
                'description' => $operation['description'] ?? '',
                'slug' => $operation['slug'],
                'parent' => $parent->term_id
            ]
        );

        if (is_wp_error($result)) {
            throw new Exception('Błąd tworzenia podkategorii: ' . $result->get_error_message());
        }

        return ['success' => true, 'term_id' => $result['term_id']];
    }

    /**
     * Przypisuje produkty do kategorii
     */
    private function assign_products_operation(array $operation): array
    {
        $category = get_term_by('slug', $operation['category'], 'product_cat');

        if (!$category) {
            throw new Exception('Nie znaleziono kategorii: ' . $operation['category']);
        }

        $assigned_count = 0;

        foreach ($operation['products'] as $product_data) {
            $product_id = $product_data['id'] ?? null;

            if ($product_id) {
                wp_set_post_terms($product_id, [$category->term_id], 'product_cat', true);
                $assigned_count++;
            }
        }

        return [
            'success' => true,
            'assigned_products' => $assigned_count
        ];
    }
}
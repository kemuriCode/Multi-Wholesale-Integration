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
        $this->max_tokens = (int) get_option('mhi_openai_max_tokens', 4000);
        $this->temperature = (float) get_option('mhi_openai_temperature', 0.3);
    }

    /**
     * Pobiera klienta OpenAI (lazy loading)
     */
    private function get_openai_client(): Client
    {
        if (null === $this->openai_client) {
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
     * Analizuje wszystkie kategorie produktów w WooCommerce
     *
     * @param string $context_description Opis kontekstu sklepu
     * @return array Wynik analizy
     */
    public function analyze_categories(string $context_description = ''): array
    {
        try {
            $this->logger->info('Rozpoczęcie analizy kategorii przez AI');

            // Sprawdź czy klucz API jest ustawiony
            if (empty($this->api_key)) {
                throw new Exception('Klucz API OpenAI nie został skonfigurowany. Ustaw klucz w ustawieniach wtyczki.');
            }

            $this->logger->info('Klucz API jest ustawiony, rozpoczynanie analizy...');

            // Pobranie wszystkich kategorii
            $categories = $this->get_all_categories();
            $this->logger->info('Znaleziono ' . count($categories) . ' kategorii do analizy');

            // Pobranie przykładowych produktów dla każdej kategorii
            $categories_with_products = $this->enrich_categories_with_products($categories);

            // Przygotowanie prompta dla AI
            $prompt = $this->prepare_analysis_prompt($categories_with_products, $context_description);

            // Wysłanie zapytania do OpenAI
            $this->logger->info('Przygotowywanie klienta OpenAI...');
            $client = $this->get_openai_client();

            $this->logger->info('Wysyłanie zapytania do OpenAI API...');
            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->get_system_prompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $this->max_tokens,
                'temperature' => $this->temperature,
                'response_format' => ['type' => 'json_object']
            ]);

            $this->logger->info('Otrzymano odpowiedź z OpenAI API');
            $analysis_result = json_decode($response->choices[0]->message->content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Błąd dekodowania JSON odpowiedzi: ' . json_last_error_msg());
            }

            // Zapisanie wyniku analizy
            $this->save_analysis_result($analysis_result);

            $this->logger->info('Analiza kategorii zakończona pomyślnie');

            return [
                'success' => true,
                'data' => $analysis_result,
                'tokens_used' => $response->usage->totalTokens ?? 0
            ];

        } catch (Exception $e) {
            $this->logger->error('Błąd podczas analizy kategorii: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
            $products = wc_get_products([
                'category' => [$category['slug']],
                'limit' => 3,
                'status' => 'publish'
            ]);

            $category['sample_products'] = [];
            foreach ($products as $product) {
                $category['sample_products'][] = [
                    'name' => $product->get_name(),
                    'description' => wp_strip_all_tags($product->get_short_description()),
                    'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names'])
                ];
            }
        }

        return $categories;
    }

    /**
     * Przygotowuje prompt systemu dla AI
     *
     * @return string
     */
    private function get_system_prompt(): string
    {
        return 'Jesteś ekspertem e-commerce specjalizującym się w organizacji kategorii produktów. 
Twoim zadaniem jest analiza struktury kategorii sklepu internetowego i zaproponowanie lepszej organizacji.

ZASADY:
1. Analizuj podobieństwa między kategoriami i produktami
2. Grupuj podobne kategorie w logiczne nadkategorie
3. Eliminuj redundantne kategorie
4. Twórz czytelną hierarchię
5. Uwzględniaj SEO i user experience
6. Pamiętaj o polskim kontekście językowym

ZAWSZE odpowiadaj w formacie JSON z następującą strukturą:
{
  "analysis": {
    "current_issues": ["lista problemów"],
    "recommendations": ["lista rekomendacji"]
  },
  "proposed_structure": {
    "main_categories": [
      {
        "name": "Nazwa głównej kategorii",
        "description": "Opis kategorii",
        "subcategories": [
          {
            "name": "Nazwa podkategorii",
            "description": "Opis podkategorii",
            "merge_from": ["kategorie do scalenia"]
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
        "reason": "powód scalenia"
      }
    ],
    "categories_to_delete": ["kategorie do usunięcia"],
    "new_categories": ["nowe kategorie do utworzenia"]
  }
}';
    }

    /**
     * Przygotowuje prompt dla analizy kategorii
     *
     * @param array $categories
     * @param string $context_description
     * @return string
     */
    private function prepare_analysis_prompt(array $categories, string $context_description): string
    {
        $prompt = "KONTEKST SKLEPU:\n";
        if (!empty($context_description)) {
            $prompt .= $context_description . "\n\n";
        } else {
            $prompt .= "Sklep internetowy z artykułami promocyjnymi i reklamowymi.\n\n";
        }

        $prompt .= "AKTUALNE KATEGORIE I PRODUKTY:\n\n";

        foreach ($categories as $category) {
            $prompt .= "KATEGORIA: {$category['name']} (ID: {$category['id']})\n";
            $prompt .= "Opis: " . ($category['description'] ?: 'Brak opisu') . "\n";
            $prompt .= "Liczba produktów: {$category['count']}\n";

            if ($category['parent'] > 0) {
                $parent = get_term($category['parent']);
                $prompt .= "Kategoria nadrzędna: {$parent->name}\n";
            }

            if (!empty($category['sample_products'])) {
                $prompt .= "Przykładowe produkty:\n";
                foreach ($category['sample_products'] as $product) {
                    $prompt .= "- {$product['name']}\n";
                    if (!empty($product['description'])) {
                        $prompt .= "  Opis: " . substr($product['description'], 0, 150) . "...\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "\nPROSZĘ PRZEANALIZUJ POWYŻSZE KATEGORIE I ZAPROPONUJ LEPSZĄ ORGANIZACJĘ.";

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
                'max_tokens' => get_option('mhi_openai_max_tokens', 4000),
                'temperature' => get_option('mhi_openai_temperature', 0.3)
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
                    update_option('mhi_openai_' . $key, $value);
                }
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error('Błąd podczas importu ustawień: ' . $e->getMessage());
            return false;
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
}
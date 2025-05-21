<?php
/**
 * Klasa obsługująca panel administracyjny wtyczki
 *
 * @package MHI
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa MHI_Admin
 * 
 * Obsługuje interfejs administracyjny wtyczki.
 */
class MHI_Admin
{
    /**
     * Inicjalizuje panel administracyjny
     */
    public function init()
    {
        // Dodanie menu w panelu administracyjnym
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Rejestracja ustawień
        add_action('admin_init', array($this, 'register_settings'));

        // Obsługa ręcznego uruchamiania pobierania
        add_action('admin_init', array($this, 'handle_manual_run'));

        // Dodaj skrypty i style
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Dodaj przycisk do czyszczenia mediów na stronie biblioteki mediów
        add_action('restrict_manage_posts', array($this, 'add_media_cleanup_button'));

        // Dodaj nowy link do czyszczenia mediów w menu biblioteki mediów
        add_action('admin_menu', array($this, 'add_media_cleanup_menu'));

        // Dodaj obsługę AJAX do czyszczenia mediów
        add_action('wp_ajax_mhi_cleanup_missing_media', array($this, 'ajax_cleanup_missing_media'));

        // Dodaj przycisk w górnym menu administratora
        add_action('admin_bar_menu', array($this, 'add_media_cleanup_to_adminbar'), 100);

        // Dodaj powiadomienie na stronie mediów
        add_action('admin_notices', array($this, 'add_media_cleanup_notice'));
    }

    /**
     * Dodaje menu w panelu administracyjnym
     */
    public function add_admin_menu()
    {
        // Dodajemy jako podzakładkę w sekcji Ustawienia
        add_submenu_page(
            'options-general.php',
            __('Multi-Hurtownie Integration', 'multi-hurtownie-integration'),
            __('Multi-Hurtownie', 'multi-hurtownie-integration'),
            'manage_options',
            'multi-hurtownie-integration',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Rejestruje ustawienia wtyczki
     */
    public function register_settings()
    {
        // Rejestracja sekcji ustawień głównych
        add_settings_section(
            'mhi_general_settings',
            __('Ustawienia ogólne', 'multi-hurtownie-integration'),
            array($this, 'general_settings_section_callback'),
            'multi-hurtownie-integration-general'
        );

        // Rejestracja pól ustawień głównych
        register_setting('mhi_general_settings', 'mhi_cron_interval');
        add_settings_field(
            'mhi_cron_interval',
            __('Domyślny interwał aktualizacji', 'multi-hurtownie-integration'),
            array($this, 'field_callback'),
            'multi-hurtownie-integration-general',
            'mhi_general_settings',
            array(
                'id' => 'mhi_cron_interval',
                'label_for' => 'mhi_cron_interval',
                'type' => 'select'
            )
        );

        // Rejestracja pól dla poszczególnych hurtowni
        $this->register_hurtownia_1_settings();
        $this->register_axpol_settings();
        $this->register_par_settings();
        $this->register_inspirion_settings();
        $this->register_macma_settings();
    }

    /**
     * Rejestruje ustawienia dla Hurtowni 1 (API)
     */
    private function register_hurtownia_1_settings()
    {
        add_settings_section(
            'mhi_hurtownia_1_settings',
            __('Ustawienia Hurtowni 1', 'multi-hurtownie-integration'),
            function () {
                echo '<p>' . __('Integracja przez API z dostępem do produktów i ich wariantów.', 'multi-hurtownie-integration') . '</p>';
            },
            'multi-hurtownie-integration-hurtownia-1'
        );

        $fields = array(
            'mhi_hurtownia_1_enabled' => array(
                'title' => __('Włączona', 'multi-hurtownie-integration'),
                'type' => 'checkbox'
            ),
            'mhi_hurtownia_1_interval' => array(
                'title' => __('Interwał aktualizacji', 'multi-hurtownie-integration'),
                'type' => 'select'
            ),
            'mhi_hurtownia_1_login' => array(
                'title' => __('Login API', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'dmurawski@promo-mix.pl'
            ),
            'mhi_hurtownia_1_password' => array(
                'title' => __('Hasło API', 'multi-hurtownie-integration'),
                'type' => 'password',
                'default' => 'mul4eQ'
            )
        );

        foreach ($fields as $id => $field) {
            register_setting('mhi_hurtownia_1_settings', $id);
            add_settings_field(
                $id,
                $field['title'],
                array($this, 'field_callback'),
                'multi-hurtownie-integration-hurtownia-1',
                'mhi_hurtownia_1_settings',
                array(
                    'id' => $id,
                    'label_for' => $id,
                    'type' => $field['type'],
                    'default' => isset($field['default']) ? $field['default'] : '',
                    'description' => isset($field['description']) ? $field['description'] : '',
                    'options' => isset($field['options']) ? $field['options'] : array()
                )
            );
        }
    }

    /**
     * Rejestruje ustawienia dla AXPOL Trading (Hurtownia 2)
     */
    private function register_axpol_settings()
    {
        add_settings_section(
            'mhi_hurtownia_2_settings',
            __('Ustawienia AXPOL Trading', 'multi-hurtownie-integration'),
            function () {
                echo '<p>' . __('Hurtownia 2 udostępnia dwa serwery – jeden dla plików XML, drugi dla zdjęć. Zalecane jest użycie protokołu SFTP.', 'multi-hurtownie-integration') . '</p>';
            },
            'multi-hurtownie-integration-axpol'
        );

        $fields = array(
            'mhi_hurtownia_2_enabled' => array(
                'title' => __('Włączona', 'multi-hurtownie-integration'),
                'type' => 'checkbox'
            ),
            'mhi_hurtownia_2_interval' => array(
                'title' => __('Interwał aktualizacji', 'multi-hurtownie-integration'),
                'type' => 'select'
            ),
            'mhi_hurtownia_2_protocol' => array(
                'title' => __('Protokół', 'multi-hurtownie-integration'),
                'type' => 'select_protocol',
                'default' => 'sftp'
            ),
            'mhi_hurtownia_2_xml_server' => array(
                'title' => __('Adres serwera XML', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'ftp2.axpol.com.pl'
            ),
            'mhi_hurtownia_2_xml_login' => array(
                'title' => __('Login dla serwera XML', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'userPL017'
            ),
            'mhi_hurtownia_2_xml_password' => array(
                'title' => __('Hasło dla serwera XML', 'multi-hurtownie-integration'),
                'type' => 'password',
                'default' => 'vSocD2N8'
            ),
            'mhi_hurtownia_2_img_server' => array(
                'title' => __('Adres serwera zdjęć', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'ftp.axpol.com.pl'
            ),
            'mhi_hurtownia_2_img_login' => array(
                'title' => __('Login dla serwera zdjęć', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'userPL017img'
            ),
            'mhi_hurtownia_2_img_password' => array(
                'title' => __('Hasło dla serwera zdjęć', 'multi-hurtownie-integration'),
                'type' => 'password',
                'default' => 'vSocD2N8'
            )
        );

        foreach ($fields as $id => $field) {
            register_setting('mhi_axpol_settings', $id);
            add_settings_field(
                $id,
                $field['title'],
                array($this, 'field_callback'),
                'multi-hurtownie-integration-axpol',
                'mhi_hurtownia_2_settings',
                array(
                    'id' => $id,
                    'label_for' => $id,
                    'type' => $field['type'],
                    'default' => isset($field['default']) ? $field['default'] : ''
                )
            );
        }
    }

    /**
     * Rejestruje ustawienia dla PAR (Hurtownia 3)
     */
    private function register_par_settings()
    {
        add_settings_section(
            'mhi_hurtownia_3_settings',
            __('Ustawienia PAR', 'multi-hurtownie-integration'),
            function () {
                echo '<p>' . __('Integracja przez API, pozwalająca pobierać produkty, kategorie, techniki zdobienia oraz stany magazynowe.', 'multi-hurtownie-integration') . '</p>';
            },
            'multi-hurtownie-integration-par'
        );

        $fields = array(
            'mhi_hurtownia_3_enabled' => array(
                'title' => __('Włączona', 'multi-hurtownie-integration'),
                'type' => 'checkbox'
            ),
            'mhi_hurtownia_3_interval' => array(
                'title' => __('Interwał aktualizacji', 'multi-hurtownie-integration'),
                'type' => 'select'
            ),
            'mhi_hurtownia_3_api_username' => array(
                'title' => __('Login API', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'dmurawski@promo-mix.pl'
            ),
            'mhi_hurtownia_3_api_password' => array(
                'title' => __('Hasło API', 'multi-hurtownie-integration'),
                'type' => 'password',
                'default' => '#Reklamy!1'
            )
        );

        foreach ($fields as $id => $field) {
            register_setting('mhi_par_settings', $id);
            add_settings_field(
                $id,
                $field['title'],
                array($this, 'field_callback'),
                'multi-hurtownie-integration-par',
                'mhi_hurtownia_3_settings',
                array(
                    'id' => $id,
                    'label_for' => $id,
                    'type' => $field['type'],
                    'default' => isset($field['default']) ? $field['default'] : '',
                    'description' => isset($field['description']) ? $field['description'] : '',
                    'options' => isset($field['options']) ? $field['options'] : array()
                )
            );
        }
    }

    /**
     * Rejestruje ustawienia dla Inspirion (Hurtownia 4)
     */
    private function register_inspirion_settings()
    {
        add_settings_section(
            'mhi_hurtownia_4_settings',
            __('Ustawienia Inspirion', 'multi-hurtownie-integration'),
            function () {
                echo '<p>' . __('Hurtownia 4 korzysta z FTP/SFTP, a dodatkowo udostępnia dostęp do epapera.', 'multi-hurtownie-integration') . '</p>';
            },
            'multi-hurtownie-integration-inspirion'
        );

        $fields = array(
            'mhi_hurtownia_4_enabled' => array(
                'title' => __('Włączona', 'multi-hurtownie-integration'),
                'type' => 'checkbox'
            ),
            'mhi_hurtownia_4_interval' => array(
                'title' => __('Interwał aktualizacji', 'multi-hurtownie-integration'),
                'type' => 'select'
            ),
            'mhi_hurtownia_4_protocol' => array(
                'title' => __('Protokół', 'multi-hurtownie-integration'),
                'type' => 'select_protocol',
                'default' => 'sftp'
            ),
            'mhi_hurtownia_4_server' => array(
                'title' => __('Adres serwera', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'ftp.inspirion.pl'
            ),
            'mhi_hurtownia_4_login' => array(
                'title' => __('Login', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'inp-customer'
            ),
            'mhi_hurtownia_4_password' => array(
                'title' => __('Hasło', 'multi-hurtownie-integration'),
                'type' => 'password',
                'default' => 'Q2JG9FZLo'
            ),
            'mhi_hurtownia_4_path' => array(
                'title' => __('Ścieżka', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => '/'
            ),
            'mhi_hurtownia_4_epaper_url' => array(
                'title' => __('URL do epapera', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'https://epaper.promotiontops-digital.com/PT2024/PL_mP2_ADC/'
            )
        );

        foreach ($fields as $id => $field) {
            register_setting('mhi_inspirion_settings', $id);
            add_settings_field(
                $id,
                $field['title'],
                array($this, 'field_callback'),
                'multi-hurtownie-integration-inspirion',
                'mhi_hurtownia_4_settings',
                array(
                    'id' => $id,
                    'label_for' => $id,
                    'type' => $field['type'],
                    'default' => isset($field['default']) ? $field['default'] : ''
                )
            );
        }
    }

    /**
     * Rejestruje ustawienia dla Macma (Hurtownia 3)
     */
    private function register_macma_settings()
    {
        add_settings_section(
            'mhi_hurtownia_3_settings',
            __('Ustawienia Macma', 'multi-hurtownie-integration'),
            function () {
                echo '<p>' . __('Hurtownia Macma udostępnia dane przez FTP w postaci plików XML.', 'multi-hurtownie-integration') . '</p>';
            },
            'multi-hurtownie-integration-macma'
        );

        $fields = array(
            'mhi_hurtownia_3_enabled' => array(
                'title' => __('Włączona', 'multi-hurtownie-integration'),
                'type' => 'checkbox'
            ),
            'mhi_hurtownia_3_interval' => array(
                'title' => __('Interwał aktualizacji', 'multi-hurtownie-integration'),
                'type' => 'select'
            ),
            'mhi_hurtownia_3_ftp_host' => array(
                'title' => __('Adres serwera FTP', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'ftp.macma.pl'
            ),
            'mhi_hurtownia_3_ftp_port' => array(
                'title' => __('Port FTP', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => '21'
            ),
            'mhi_hurtownia_3_ftp_user' => array(
                'title' => __('Użytkownik FTP', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => 'user_macma'
            ),
            'mhi_hurtownia_3_ftp_pass' => array(
                'title' => __('Hasło FTP', 'multi-hurtownie-integration'),
                'type' => 'password',
                'default' => 'pass_macma'
            ),
            'mhi_hurtownia_3_ftp_directory' => array(
                'title' => __('Katalog na serwerze FTP', 'multi-hurtownie-integration'),
                'type' => 'text',
                'default' => '/xml'
            )
        );

        foreach ($fields as $id => $field) {
            register_setting('mhi_hurtownia_3_settings', $id);
            add_settings_field(
                $id,
                $field['title'],
                array($this, 'field_callback'),
                'multi-hurtownie-integration-macma',
                'mhi_hurtownia_3_settings',
                array(
                    'id' => $id,
                    'label_for' => $id,
                    'type' => $field['type'],
                    'default' => isset($field['default']) ? $field['default'] : '',
                    'description' => isset($field['description']) ? $field['description'] : ''
                )
            );
        }
    }

    /**
     * Callback dla sekcji ustawień głównych
     */
    public function general_settings_section_callback()
    {
        echo '<p>' . __('Konfiguracja ogólnych ustawień wtyczki.', 'multi-hurtownie-integration') . '</p>';
    }

    /**
     * Callback dla pól formularza
     *
     * @param array $args Argumenty pola.
     */
    public function field_callback($args)
    {
        // Pobierz aktualną wartość
        $id = $args['id'];
        $value = get_option($id, '');
        $default = isset($args['default']) ? $args['default'] : '';
        $description = isset($args['description']) ? $args['description'] : '';

        // Jeśli wartość jest pusta, ustaw domyślną
        if ('' === $value && '' !== $default) {
            $value = $default;
        }

        // Renderuj pole w zależności od typu
        switch ($args['type']) {
            case 'checkbox':
                ?>
                <label for="<?php echo esc_attr($id); ?>">
                    <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" value="1" <?php checked(1, $value); ?>>
                    <?php _e('Włącz', 'multi-hurtownie-integration'); ?>
                </label>
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'select':
                ?>
                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>">
                    <option value="hourly" <?php selected('hourly', $value); ?>>
                        <?php _e('Co godzinę', 'multi-hurtownie-integration'); ?>
                    </option>
                    <option value="twicedaily" <?php selected('twicedaily', $value); ?>>
                        <?php _e('Dwa razy dziennie', 'multi-hurtownie-integration'); ?>
                    </option>
                    <option value="daily" <?php selected('daily', $value); ?>>
                        <?php _e('Raz dziennie', 'multi-hurtownie-integration'); ?>
                    </option>
                    <option value="weekly" <?php selected('weekly', $value); ?>>
                        <?php _e('Raz w tygodniu', 'multi-hurtownie-integration'); ?>
                    </option>
                </select>
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'select_protocol':
                ?>
                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>">
                    <option value="ftp" <?php selected('ftp', $value); ?>>
                        <?php _e('FTP', 'multi-hurtownie-integration'); ?>
                    </option>
                    <option value="sftp" <?php selected('sftp', $value); ?>>
                        <?php _e('SFTP (zalecane)', 'multi-hurtownie-integration'); ?>
                    </option>
                </select>
                <?php if (strpos($id, 'hurtownia_2') !== false || strpos($id, 'axpol') !== false): ?>
                    <p class="description">
                        <?php _e('SFTP używa portu 2223 zgodnie z dokumentacją AXPOL.', 'multi-hurtownie-integration'); ?>
                    </p>
                <?php elseif (strpos($id, 'hurtownia_4') !== false || strpos($id, 'inspirion') !== false): ?>
                    <p class="description">
                        <?php _e('Dla SFTP używany jest standardowy port 22.', 'multi-hurtownie-integration'); ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'number':
                ?>
                <input type="number" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>"
                    value="<?php echo esc_attr($value); ?>" class="small-text" min="1" max="100">
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'text':
                ?>
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>"
                    value="<?php echo esc_attr($value); ?>" class="regular-text">
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'password':
                ?>
                <input type="password" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>"
                    value="<?php echo esc_attr($value); ?>" class="regular-text">
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'radio':
                ?>
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html($args['title']); ?></span></legend>
                    <?php foreach ($args['options'] as $option_value => $option_label): ?>
                        <label for="<?php echo esc_attr($id . '_' . $option_value); ?>">
                            <input id="<?php echo esc_attr($id . '_' . $option_value); ?>" name="<?php echo esc_attr($id); ?>" type="radio"
                                value="<?php echo esc_attr($option_value); ?>" <?php checked($option_value, $value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
                break;

            default:
                // Domyślny typ pola tekstowego
                ?>
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>"
                    value="<?php echo esc_attr($value); ?>" class="regular-text">
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
        <?php
        }
    }

    /**
     * Wyświetla stronę ustawień wtyczki
     */
    public function display_settings_page()
    {
        // Sprawdzenie uprawnień
        if (!current_user_can('manage_options')) {
            return;
        }

        // Wyświetlanie strony ustawień
        require_once MHI_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Rejestruje i dodaje skrypty oraz style
     */
    public function enqueue_scripts($hook)
    {
        // Wczytaj skrypty i style tylko na stronie ustawień wtyczki
        if ($hook !== 'settings_page_multi-hurtownie-integration') {
            return;
        }

        // Zarejestruj styles
        wp_enqueue_style('mhi-admin-css', MHI_PLUGIN_URL . 'admin/css/mhi-admin.css', array(), MHI_VERSION);

        // Zarejestruj skrypty
        wp_enqueue_script('mhi-admin-js', MHI_PLUGIN_URL . 'admin/js/mhi-admin.js', array('jquery'), MHI_VERSION, true);

        // Lokalizacja skryptu
        wp_localize_script('mhi-admin-js', 'mhi_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhi_admin_ajax'),
            'importing' => __('Importowanie...', 'multi-hurtownie-integration'),
            'import_complete' => __('Import zakończony', 'multi-hurtownie-integration'),
            'error' => __('Błąd', 'multi-hurtownie-integration'),
            'generating' => __('Generowanie...', 'multi-hurtownie-integration'),
            'generation_complete' => __('Generowanie zakończone', 'multi-hurtownie-integration')
        ));

        // Dodaj skrypt inline dla obsługi przycisku generowania XML dla hurtowni 2
        $generate_xml_script = "
            jQuery(document).ready(function($) {
                // Obsługa przycisku generowania XML dla hurtowni 2
                $('#mhi-generate-xml-button-hurtownia-2').click(function() {
                    var button = $(this);
                    var spinner = $('#mhi-generate-xml-spinner-hurtownia-2');
                    var resultDiv = $('#mhi-generate-xml-result-hurtownia-2');
                    
                    // Zmiana wyglądu przycisku podczas przetwarzania
                    button.prop('disabled', true);
                    spinner.addClass('is-active');
                    
                    // Wyczyść poprzedni wynik
                    resultDiv.empty();
                    
                    // Wywołanie AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mhi_generate_xml_hurtownia_2',
                            security: button.data('nonce')
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html('<div class=\"notice notice-success\"><p>' + response.data.message + '</p></div>');
                            } else {
                                resultDiv.html('<div class=\"notice notice-error\"><p><strong>" . __('Błąd:', 'multi-hurtownie-integration') . "</strong> ' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            resultDiv.html('<div class=\"notice notice-error\"><p><strong>" . __('Błąd:', 'multi-hurtownie-integration') . "</strong> " . __('Wystąpił błąd podczas komunikacji z serwerem.', 'multi-hurtownie-integration') . "</p></div>');
                        },
                        complete: function() {
                            // Przywrócenie wyglądu przycisku
                            button.prop('disabled', false);
                            spinner.removeClass('is-active');
                        }
                    });
                });
            });
        ";
        wp_add_inline_script('mhi-admin-js', $generate_xml_script);
    }

    /**
     * Obsługuje ręczne uruchamianie pobierania danych
     */
    public function handle_manual_run()
    {
        // Sprawdź uruchomienie dla hurtowni 1
        if (isset($_POST['mhi_manual_run_hurtownia_1'])) {
            if (!isset($_POST['mhi_manual_run_hurtownia_1_nonce']) || !wp_verify_nonce($_POST['mhi_manual_run_hurtownia_1_nonce'], 'mhi_manual_run_hurtownia_1')) {
                wp_die(__('Niepoprawny token bezpieczeństwa', 'multi-hurtownie-integration'));
            }

            $this->run_integration('hurtownia_1');
        }

        // Sprawdź uruchomienie dla hurtowni 2
        if (isset($_POST['mhi_manual_run_hurtownia_2'])) {
            if (!isset($_POST['mhi_manual_run_hurtownia_2_nonce']) || !wp_verify_nonce($_POST['mhi_manual_run_hurtownia_2_nonce'], 'mhi_manual_run_hurtownia_2')) {
                wp_die(__('Niepoprawny token bezpieczeństwa', 'multi-hurtownie-integration'));
            }

            $this->run_integration('hurtownia_2');
        }

        // Sprawdź uruchomienie dla hurtowni 3
        if (isset($_POST['mhi_manual_run_hurtownia_3'])) {
            if (!isset($_POST['mhi_manual_run_hurtownia_3_nonce']) || !wp_verify_nonce($_POST['mhi_manual_run_hurtownia_3_nonce'], 'mhi_manual_run_hurtownia_3')) {
                wp_die(__('Niepoprawny token bezpieczeństwa', 'multi-hurtownie-integration'));
            }

            $this->run_integration('hurtownia_3');
        }

        // Sprawdź uruchomienie dla hurtowni 4
        if (isset($_POST['mhi_manual_run_hurtownia_4'])) {
            if (!isset($_POST['mhi_manual_run_hurtownia_4_nonce']) || !wp_verify_nonce($_POST['mhi_manual_run_hurtownia_4_nonce'], 'mhi_manual_run_hurtownia_4')) {
                wp_die(__('Niepoprawny token bezpieczeństwa', 'multi-hurtownie-integration'));
            }

            $this->run_integration('hurtownia_4');
        }

        // Sprawdź uruchomienie dla hurtowni 5
        if (isset($_POST['mhi_manual_run_hurtownia_5'])) {
            if (!isset($_POST['mhi_manual_run_hurtownia_5_nonce']) || !wp_verify_nonce($_POST['mhi_manual_run_hurtownia_5_nonce'], 'mhi_manual_run_hurtownia_5')) {
                wp_die(__('Niepoprawny token bezpieczeństwa', 'multi-hurtownie-integration'));
            }

            $this->run_integration('hurtownia_5');
        }
    }

    /**
     * Uruchamia integrację dla danej hurtowni
     * 
     * @param string $integration_name Nazwa integracji do uruchomienia
     */
    private function run_integration($integration_name)
    {
        // Utwórz instancję klasy integracji
        $class_name = 'MHI_' . str_replace('_', '_', ucfirst($integration_name));
        if (!class_exists($class_name)) {
            MHI_Logger::error('Nie znaleziono klasy integracji: ' . $class_name);
            add_settings_error(
                'mhi_integration',
                'mhi_integration_error',
                sprintf(__('Błąd: Nie znaleziono klasy integracji %s', 'multi-hurtownie-integration'), $class_name),
                'error'
            );
            return;
        }

        // Ustaw status pobierania
        update_option('mhi_download_status_' . $integration_name, __('Inicjalizacja pobierania...', 'multi-hurtownie-integration'));

        // Utwórz instancję i pobierz pliki
        $integration = new $class_name();
        $files = $integration->fetch_files();

        // Podsumowanie
        if (empty($files)) {
            update_option('mhi_download_status_' . $integration_name, __('Nie pobrano żadnych plików', 'multi-hurtownie-integration'));
            add_settings_error(
                'mhi_integration',
                'mhi_integration_warning',
                sprintf(__('Nie pobrano żadnych plików z %s', 'multi-hurtownie-integration'), str_replace('_', ' ', $integration_name)),
                'warning'
            );
        } else {
            // Liczba nowych i pominiętych plików
            $downloaded = 0;
            $skipped = 0;

            foreach ($files as $file) {
                if (isset($file['status']) && $file['status'] === 'downloaded') {
                    $downloaded++;
                } elseif (isset($file['status']) && $file['status'] === 'skipped') {
                    $skipped++;
                } else {
                    $downloaded++; // Dla kompatybilności wstecznej
                }
            }

            $message = sprintf(
                __('Zakończono pobieranie z %s. Pobrano %d nowych plików, pominięto %d plików.', 'multi-hurtownie-integration'),
                str_replace('_', ' ', $integration_name),
                $downloaded,
                $skipped
            );

            update_option('mhi_download_status_' . $integration_name, $message);
            add_settings_error(
                'mhi_integration',
                'mhi_integration_success',
                $message,
                'success'
            );
        }
    }

    /**
     * Dodaje przycisk do czyszczenia mediów na stronie biblioteki mediów
     */
    public function add_media_cleanup_button($post_type)
    {
        if ($post_type !== 'attachment') {
            return;
        }

        ?>
        <div class="alignleft actions">
            <button type="button" id="mhi-cleanup-media" class="button button-primary"
                style="background-color: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-trash" style="margin-right: 5px; vertical-align: text-bottom;"></span>
                <?php _e('Wyczyść puste kafelki mediów', 'multi-hurtownie-integration'); ?>
            </button>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('#mhi-cleanup-media').on('click', function () {
                    if (confirm('<?php _e('Czy na pewno chcesz wyczyścić puste wpisy mediów? Ta operacja jest nieodwracalna.', 'multi-hurtownie-integration'); ?>')) {
                        $(this).prop('disabled', true).html('<span class="spinner is-active" style="float:left; margin-right:5px;"></span> <?php _e('Czyszczenie...', 'multi-hurtownie-integration'); ?>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mhi_cleanup_missing_media',
                                security: '<?php echo wp_create_nonce('mhi_cleanup_media_nonce'); ?>'
                            },
                            success: function (response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    location.reload();
                                } else {
                                    alert(response.data.message || '<?php _e('Wystąpił błąd podczas czyszczenia mediów.', 'multi-hurtownie-integration'); ?>');
                                    $('#mhi-cleanup-media').prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-right: 5px; vertical-align: text-bottom;"></span> <?php _e('Wyczyść puste kafelki mediów', 'multi-hurtownie-integration'); ?>');
                                }
                            },
                            error: function () {
                                alert('<?php _e('Wystąpił błąd podczas czyszczenia mediów.', 'multi-hurtownie-integration'); ?>');
                                $('#mhi-cleanup-media').prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-right: 5px; vertical-align: text-bottom;"></span> <?php _e('Wyczyść puste kafelki mediów', 'multi-hurtownie-integration'); ?>');
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Czyści puste wpisy mediów poprzez AJAX
     */
    public function ajax_cleanup_missing_media()
    {
        // Sprawdź uprawnienia użytkownika
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Brak uprawnień do wykonania tej operacji.', 'multi-hurtownie-integration')]);
            return;
        }

        // Sprawdź nonce
        check_ajax_referer('mhi_cleanup_media_nonce', 'security');

        // Limit czasu wykonania skryptu (300 sekund = 5 minut)
        set_time_limit(300);

        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');

        // Pobierz wszystkie załączniki w porcjach dla lepszej wydajności
        $paged = 1;
        $per_page = 100;
        $deleted_count = 0;

        do {
            $attachments = get_posts([
                'post_type' => 'attachment',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'post_status' => 'any'
            ]);

            if (empty($attachments)) {
                break;
            }

            // Sprawdź każdy załącznik w bieżącej porcji
            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);

                // Jeśli plik nie istnieje fizycznie
                if (!file_exists($file_path) || empty($file_path)) {
                    // Usuń załącznik i powiązane metadane
                    wp_delete_attachment($attachment->ID, true);
                    $deleted_count++;
                }
            }

            // Wyczyść pamięć
            wp_reset_postdata();

            // Przejdź do następnej strony
            $paged++;

        } while (!empty($attachments));

        wp_send_json_success([
            'message' => sprintf(
                __('Operacja zakończona pomyślnie. Usunięto %d pustych wpisów medialnych.', 'multi-hurtownie-integration'),
                $deleted_count
            )
        ]);
    }

    /**
     * Dodaje link do czyszczenia mediów w menu biblioteki mediów
     */
    public function add_media_cleanup_menu()
    {
        add_submenu_page(
            'upload.php',                        // Parent slug (Media)
            __('Wyczyść puste media', 'multi-hurtownie-integration'),     // Page title
            __('Wyczyść puste media', 'multi-hurtownie-integration'),     // Menu title
            'manage_options',                    // Capability
            'mhi-cleanup-media',                 // Menu slug
            array($this, 'render_media_cleanup_page') // Function
        );

        // Obsługa żądania czyszczenia mediów z formularza
        if (isset($_GET['page']) && $_GET['page'] === 'mhi-cleanup-media' && isset($_POST['mhi_cleanup_submit']) && check_admin_referer('mhi_cleanup_media_nonce')) {
            $this->handle_media_cleanup();
        }
    }

    /**
     * Renderuje stronę czyszczenia mediów
     */
    public function render_media_cleanup_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Czyszczenie pustych mediów', 'multi-hurtownie-integration'); ?></h1>

            <div class="card">
                <h2><?php _e('Narzędzie do czyszczenia pustych kafelków w bibliotece mediów', 'multi-hurtownie-integration'); ?>
                </h2>
                <p><?php _e('To narzędzie pomoże Ci wyczyścić wpisy mediów, których pliki fizyczne zostały usunięte, a wpisy nadal pozostają w bazie danych.', 'multi-hurtownie-integration'); ?>
                </p>
                <p><?php _e('Kliknij przycisk poniżej, aby rozpocząć skanowanie i usuwanie pustych wpisów.', 'multi-hurtownie-integration'); ?>
                </p>

                <form method="post" action="">
                    <?php wp_nonce_field('mhi_cleanup_media_nonce'); ?>
                    <p>
                        <button type="submit" name="mhi_cleanup_submit" id="mhi-cleanup-media-button"
                            class="button button-primary" style="background-color: #d63638; border-color: #d63638;">
                            <span class="dashicons dashicons-trash"
                                style="margin-right: 5px; vertical-align: text-bottom;"></span>
                            <?php _e('Rozpocznij czyszczenie pustych mediów', 'multi-hurtownie-integration'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <?php if (isset($_GET['cleaned']) && $_GET['cleaned'] > 0): ?>
                <div class="notice notice-success">
                    <p>
                        <?php
                        $count = intval($_GET['cleaned']);
                        printf(
                            _n(
                                'Operacja zakończona pomyślnie. Usunięto %d pusty wpis medialny.',
                                'Operacja zakończona pomyślnie. Usunięto %d pustych wpisów medialnych.',
                                $count,
                                'multi-hurtownie-integration'
                            ),
                            $count
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['cleaned']) && $_GET['cleaned'] == 0): ?>
                <div class="notice notice-info">
                    <p><?php _e('Nie znaleziono pustych wpisów medialnych do usunięcia.', 'multi-hurtownie-integration'); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="margin-top: 20px;">
                <h3><?php _e('Wskazówki', 'multi-hurtownie-integration'); ?></h3>
                <ul style="list-style-type: disc; padding-left: 20px;">
                    <li><?php _e('Operacja może zająć kilka minut w przypadku dużej biblioteki mediów.', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('Usunięte zostaną tylko wpisy mediów, których pliki fizyczne nie istnieją.', 'multi-hurtownie-integration'); ?>
                    </li>
                    <li><?php _e('Wtyczka wykonuje automatyczne czyszczenie raz dziennie.', 'multi-hurtownie-integration'); ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Obsługuje żądanie czyszczenia mediów
     */
    public function handle_media_cleanup()
    {
        // Limit czasu wykonania skryptu (300 sekund = 5 minut)
        set_time_limit(300);

        // Zwiększ limit pamięci
        ini_set('memory_limit', '512M');

        // Pobierz wszystkie załączniki w porcjach dla lepszej wydajności
        $paged = 1;
        $per_page = 100;
        $deleted_count = 0;

        do {
            $attachments = get_posts([
                'post_type' => 'attachment',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'post_status' => 'any'
            ]);

            if (empty($attachments)) {
                break;
            }

            // Sprawdź każdy załącznik w bieżącej porcji
            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);

                // Jeśli plik nie istnieje fizycznie
                if (!file_exists($file_path) || empty($file_path)) {
                    // Usuń załącznik i powiązane metadane
                    wp_delete_attachment($attachment->ID, true);
                    $deleted_count++;
                }
            }

            // Wyczyść pamięć
            wp_reset_postdata();

            // Przejdź do następnej strony
            $paged++;

        } while (!empty($attachments));

        // Przekieruj z powrotem do strony narzędzia z informacją o wyniku
        wp_redirect(admin_url('upload.php?page=mhi-cleanup-media&cleaned=' . $deleted_count));
        exit;
    }

    /**
     * Dodaje przycisk czyszczenia mediów w górnym pasku administratora
     */
    public function add_media_cleanup_to_adminbar($admin_bar)
    {
        // Tylko jeśli użytkownik ma uprawnienia administracyjne
        if (current_user_can('manage_options')) {
            $admin_bar->add_node([
                'id' => 'mhi-clean-media',
                'title' => '<span class="ab-icon dashicons dashicons-trash" style="top: 2px;"></span>' . __('Wyczyść puste media', 'multi-hurtownie-integration'),
                'href' => admin_url('upload.php?page=mhi-cleanup-media'),
                'meta' => [
                    'title' => __('Wyczyść puste wpisy mediów', 'multi-hurtownie-integration'),
                    'class' => 'mhi-clean-media-button'
                ]
            ]);
        }
    }

    /**
     * Dodaje powiadomienie o możliwości czyszczenia mediów na stronie mediów
     */
    public function add_media_cleanup_notice()
    {
        $screen = get_current_screen();

        // Tylko na stronie głównej biblioteki mediów
        if ($screen && $screen->id === 'upload') {
            ?>
            <div class="notice notice-info" style="border-left-color: #d63638; position: relative; padding-right: 38px;">
                <h3 style="margin-top: 10px;"><?php _e('Masz puste kafelki w bibliotece mediów?', 'multi-hurtownie-integration'); ?>
                </h3>
                <p>
                    <?php _e('Jeśli widzisz puste kafelki (bez obrazków) w bibliotece mediów, możesz je łatwo wyczyścić.', 'multi-hurtownie-integration'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('upload.php?page=mhi-cleanup-media'); ?>" class="button"
                        style="background-color: #d63638; border-color: #d63638; color: white;">
                        <span class="dashicons dashicons-trash" style="margin: 4px 5px 0 0;"></span>
                        <?php _e('Wyczyść puste media', 'multi-hurtownie-integration'); ?>
                    </a>
                </p>
                <a href="#" class="notice-dismiss" style="text-decoration: none;"></a>
            </div>
            <script>
                jQuery(document).ready(function ($) {
                    $('.notice-dismiss').on('click', function () {
                        $(this).closest('.notice').slideUp();
                    });
                });
            </script>
            <?php
        }
    }

    /**
     * Obsługuje żądanie AJAX pobierania plików.
     */
    public function ajax_fetch_files()
    {
        // Sprawdź nonce
        check_ajax_referer('mhi-ajax-nonce', 'nonce');

        // Pobierz parametry
        $hurtownia_id = isset($_POST['hurtownia_id']) ? sanitize_text_field($_POST['hurtownia_id']) : '';

        if (empty($hurtownia_id)) {
            wp_send_json_error(array('message' => __('Brak identyfikatora hurtowni', 'multi-hurtownie-integration')));
            return;
        }

        // Zresetuj flagę anulowania
        update_option('mhi_cancel_download_' . $hurtownia_id, false);

        // Pobierz instancję hurtowni
        $integration = $this->get_integration($hurtownia_id);
        if (!$integration) {
            wp_send_json_error(array('message' => __('Nie znaleziono hurtowni o podanym identyfikatorze', 'multi-hurtownie-integration')));
            return;
        }

        // Uruchom pobieranie w tle
        $this->schedule_download($hurtownia_id);

        wp_send_json_success(array(
            'message' => __('Rozpoczęto pobieranie plików', 'multi-hurtownie-integration'),
            'hurtownia_id' => $hurtownia_id,
        ));
    }

    /**
     * Planuje wykonanie zadania pobierania plików w tle.
     *
     * @param string $hurtownia_id Identyfikator hurtowni.
     */
    private function schedule_download($hurtownia_id)
    {
        // Zaplanuj uruchomienie zadania za 5 sekund (w tle)
        wp_schedule_single_event(time() + 5, 'mhi_single_download_task', array($hurtownia_id));

        // Upewnij się, że WordPress uruchomi zaplanowane zadanie
        spawn_cron();
    }

    /**
     * Pobiera instancję integracji dla danej hurtowni.
     *
     * @param string $hurtownia_id Identyfikator hurtowni.
     * @return MHI_Integration_Interface|null Instancja integracji lub null.
     */
    private function get_integration($hurtownia_id)
    {
        // Automatycznie identyfikuj i ładuj klasy integracji
        $class_name = 'MHI_' . ucfirst(str_replace('hurtownia_', 'Hurtownia_', $hurtownia_id));

        if (class_exists($class_name)) {
            $integration = new $class_name();
            if ($integration instanceof MHI_Integration_Interface) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Wyświetla stronę importu produktów
     */
    public function display_product_import_page()
    {
        // Sprawdź czy przekazano parametr supplier
        if (isset($_GET['supplier'])) {
            $supplier = sanitize_text_field($_GET['supplier']);

            // Sprawdź czy podany dostawca jest obsługiwany
            $supported_suppliers = array('malfini', 'axpol', 'par', 'macma', 'inspirion');

            if (in_array($supplier, $supported_suppliers)) {
                // Ścieżka do skryptu importu
                $import_script = plugin_dir_path(dirname(__FILE__)) . 'import.php';

                // Dołącz skrypt importu
                if (file_exists($import_script)) {
                    // Przekazujemy sterowanie do skryptu importu
                    include $import_script;
                    return;
                } else {
                    echo '<div class="wrap"><h1>' . __('Import produktów', 'multi-hurtownie-integration') . '</h1>';
                    echo '<div class="notice notice-error"><p>' . __('Nie znaleziono skryptu importu: import.php', 'multi-hurtownie-integration') . '</p></div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="wrap"><h1>' . __('Import produktów', 'multi-hurtownie-integration') . '</h1>';
                echo '<div class="notice notice-error"><p>' . __('Nieobsługiwany dostawca: ', 'multi-hurtownie-integration') . $supplier . '</p></div>';
                echo '</div>';
            }
        } else {
            // Wyświetl listę dostępnych dostawców do importu
            echo '<div class="wrap">';
            echo '<h1>' . __('Import produktów', 'multi-hurtownie-integration') . '</h1>';
            echo '<p>' . __('Wybierz hurtownię, z której chcesz zaimportować produkty:', 'multi-hurtownie-integration') . '</p>';

            echo '<div class="mhi-supplier-list">';
            echo '<a href="' . admin_url('admin.php?page=mhi-product-import&supplier=malfini') . '" class="mhi-supplier-card">';
            echo '<h3>Malfini</h3>';
            echo '<p>' . __('Import produktów z hurtowni Malfini', 'multi-hurtownie-integration') . '</p>';
            echo '</a>';

            echo '<a href="' . admin_url('admin.php?page=mhi-product-import&supplier=axpol') . '" class="mhi-supplier-card">';
            echo '<h3>Axpol</h3>';
            echo '<p>' . __('Import produktów z hurtowni Axpol', 'multi-hurtownie-integration') . '</p>';
            echo '</a>';

            echo '<a href="' . admin_url('admin.php?page=mhi-product-import&supplier=par') . '" class="mhi-supplier-card">';
            echo '<h3>PAR</h3>';
            echo '<p>' . __('Import produktów z hurtowni PAR', 'multi-hurtownie-integration') . '</p>';
            echo '</a>';

            echo '<a href="' . admin_url('admin.php?page=mhi-product-import&supplier=macma') . '" class="mhi-supplier-card">';
            echo '<h3>Macma</h3>';
            echo '<p>' . __('Import produktów z hurtowni Macma', 'multi-hurtownie-integration') . '</p>';
            echo '</a>';

            echo '<a href="' . admin_url('admin.php?page=mhi-product-import&supplier=inspirion') . '" class="mhi-supplier-card">';
            echo '<h3>Inspirion</h3>';
            echo '<p>' . __('Import produktów z hurtowni Inspirion', 'multi-hurtownie-integration') . '</p>';
            echo '</a>';

            echo '</div>';

            echo '<style>
                .mhi-supplier-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    margin-top: 20px;
                }
                .mhi-supplier-card {
                    display: block;
                    padding: 20px;
                    border-radius: 5px;
                    background-color: #fff;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    text-decoration: none;
                    color: #333;
                    width: calc(33.33% - 20px);
                    min-width: 250px;
                    transition: all 0.2s ease-in-out;
                }
                .mhi-supplier-card:hover {
                    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
                    transform: translateY(-2px);
                }
                .mhi-supplier-card h3 {
                    margin-top: 0;
                    color: #0073aa;
                }
            </style>';

            echo '</div>';
        }
    }
}
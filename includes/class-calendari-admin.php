<?php

defined('ABSPATH') || exit;

final class Calendari_Admin {
    private Calendari_DB $db;

    private Calendari_Degree_DB $degree_db;

    public function __construct(Calendari_DB $db, Calendari_Degree_DB $degree_db) {
        $this->db = $db;
        $this->degree_db = $degree_db;
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_calendari_filter_database', array($this, 'handle_filter_ajax'));
        add_action('wp_ajax_calendari_shortcode_builder_options', array($this, 'handle_shortcode_builder_options_ajax'));
    }

    public function register_menu(): void {
        add_menu_page(
            __('Calendari', 'calendari'),
            __('Calendari', 'calendari'),
            'manage_options',
            'calendari',
            array($this, 'render_page'),
            'dashicons-calendar-alt',
            30
        );
    }

    public function enqueue_assets(string $hook): void {
        if ('toplevel_page_calendari' === $hook) {
            wp_enqueue_style('calendari-tabler-icons', 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.49.0/dist/tabler-icons.min.css', array(), '3.49.0');
            wp_enqueue_style('calendari-admin', CALENDARI_URL . 'assets/css/admin.css', array('calendari-tabler-icons'), CALENDARI_VERSION);
            wp_enqueue_style('calendari-admin-font', 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap', array(), null);
            wp_enqueue_script('calendari-admin', CALENDARI_URL . 'assets/js/admin.js', array(), CALENDARI_VERSION, true);
            wp_localize_script('calendari-admin', 'CalendariAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'filterNonce' => wp_create_nonce('calendari_filter_records'),
                'filterError' => __('Impossibile aggiornare i filtri. Riprova.', 'calendari'),
                'builderNonce' => wp_create_nonce('calendari_shortcode_builder_options'),
                'builderOptionsError' => __('Impossibile caricare le opzioni per la tipologia selezionata.', 'calendari'),
            ));
            $colors = Calendari_Colors::derive($this->get_graphics_settings()['accent_color']);
            wp_add_inline_style('calendari-admin', sprintf(
                '.calendari-admin { --calendari-admin-accent: %s; --calendari-admin-deep: %s; --calendari-admin-soft: %s; --calendari-admin-surface: %s; --calendari-admin-card: %s; --calendari-admin-ink: %s; --calendari-admin-muted: %s; --calendari-admin-line: %s; --calendari-admin-contrast: %s; }',
                $colors['accent'],
                $colors['deep'],
                $colors['soft'],
                $colors['surface'],
                $colors['card'],
                $colors['ink'],
                $colors['muted'],
                $colors['line'],
                $colors['contrast']
            ));
        }
    }

    public function handle_actions(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['calendari_import'])) {
            $this->handle_import();
        }

        if (isset($_POST['calendari_save'])) {
            $this->handle_save();
        }

        if (isset($_POST['calendari_save_graphics'])) {
            $this->handle_graphics_save();
        }

        if (isset($_POST['calendari_reset_graphics'])) {
            $this->handle_graphics_reset();
        }

        if (isset($_POST['calendari_degree_save'])) {
            $this->handle_degree_save();
        }

        if (isset($_GET['calendari_delete'])) {
            $this->handle_delete();
        }

        if (isset($_GET['calendari_visibility'])) {
            $this->handle_visibility();
        }

        if (isset($_GET['calendari_degree_delete'])) {
            $this->handle_degree_delete();
        }

        if (isset($_GET['calendari_degree_visibility'])) {
            $this->handle_degree_visibility();
        }
    }

    public function handle_filter_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permessi insufficienti.', 'calendari')), 403);
        }
        if (!check_ajax_referer('calendari_filter_records', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Richiesta scaduta. Ricarica la pagina.', 'calendari')), 403);
        }

        $tab = sanitize_key($this->ajax_filter_value('tab'));
        $page = max(1, absint($this->ajax_filter_value('paged')));
        $per_page = 25;

        if ('database_laurea' === $tab) {
            $filters = array(
                'aa_id' => Calendari_Format::normalize_academic_year_id($this->ajax_filter_value('aa_id')),
                'mese' => absint($this->ajax_filter_value('mese')),
                'data' => $this->ajax_filter_value('data'),
                'sede' => $this->ajax_filter_value('sede'),
            );
            $aa_values = $this->degree_db->distinct_aa_ids();
            if ('' !== $filters['aa_id'] && !in_array($filters['aa_id'], $aa_values, true)) {
                $filters['aa_id'] = '';
            }
            $month_values = $this->degree_db->distinct_months();
            if ($filters['mese'] && !in_array($filters['mese'], $month_values, true)) {
                $filters['mese'] = 0;
            }
            $date_values = $this->degree_db->distinct_dates();
            if ('' !== $filters['data'] && !in_array($filters['data'], $date_values, true)) {
                $filters['data'] = '';
            }
            $venue_values = $this->degree_db->distinct_venues();
            if ('' !== $filters['sede'] && !in_array($filters['sede'], $venue_values, true)) {
                $filters['sede'] = '';
            }

            $total = $this->degree_db->count($filters);
            $records = $this->degree_db->list($filters, $per_page, ($page - 1) * $per_page);
            ob_start();
            $this->render_degree_session_results($records, $total, $page, $filters);
            $html = (string) ob_get_clean();
            wp_send_json_success(array('html' => $html, 'total' => $total, 'page' => $page));
        }

        if ('database_esami' === $tab) {
            $filters = array(
                'aa_id' => Calendari_Format::normalize_academic_year_id($this->ajax_filter_value('aa_id')),
                'tipo_corso' => Calendari_Format::normalize_course_type($this->ajax_filter_value('tipo_corso')),
                'gruppo' => $this->ajax_filter_value('gruppo'),
                'tipo_prova' => $this->ajax_filter_value('tipo_prova'),
                'mese' => absint($this->ajax_filter_value('mese')),
            );
            $aa_values = $this->db->distinct_aa_ids();
            if ('' !== $filters['aa_id'] && !in_array($filters['aa_id'], $aa_values, true)) {
                $filters['aa_id'] = '';
            }
            $course_options = Calendari_Format::course_type_options($this->db->distinct_course_types($filters['aa_id']));
            $course_values = array_column($course_options, 'value');
            if ('' !== $filters['tipo_corso'] && !in_array($filters['tipo_corso'], $course_values, true)) {
                $filters['tipo_corso'] = '';
            }
            $group_values = $this->db->distinct_group_names($filters['aa_id'], $filters['tipo_corso']);
            if ('' !== $filters['gruppo'] && !in_array($filters['gruppo'], $group_values, true)) {
                $filters['gruppo'] = '';
            }
            $exam_type_values = array_map(static function (string $value): string {
                return '' === $value ? '__empty' : $value;
            }, $this->db->distinct_exam_types());
            if ('' !== $filters['tipo_prova'] && !in_array($filters['tipo_prova'], $exam_type_values, true)) {
                $filters['tipo_prova'] = '';
            }
            $month_values = $this->db->distinct_months();
            if ($filters['mese'] && !in_array($filters['mese'], $month_values, true)) {
                $filters['mese'] = 0;
            }

            $total = $this->db->count($filters);
            $records = $this->db->list($filters, $per_page, ($page - 1) * $per_page);
            ob_start();
            $this->render_exam_repository_results($records, $total, $page, $filters);
            $html = (string) ob_get_clean();
            wp_send_json_success(array(
                'html' => $html,
                'total' => $total,
                'count_label' => number_format_i18n($total) . ' ' . __('eventi', 'calendari'),
                'page' => $page,
            ));
        }

        wp_send_json_error(array('message' => __('Sezione di filtro non valida.', 'calendari')), 400);
    }

    private function ajax_filter_value(string $key): string {
        $value = wp_unslash($_POST[$key] ?? '');

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    public function handle_shortcode_builder_options_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permessi insufficienti.', 'calendari')), 403);
        }
        if (!check_ajax_referer('calendari_shortcode_builder_options', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Richiesta scaduta. Ricarica la pagina.', 'calendari')), 403);
        }

        $tipologia = sanitize_key($this->ajax_filter_value('builder_tipologia'));
        if (!in_array($tipologia, array('esami', 'laurea'), true)) {
            wp_send_json_error(array('message' => __('Tipologia di calendario non valida.', 'calendari')), 400);
        }

        $academic_years = 'laurea' === $tipologia ? $this->degree_db->distinct_aa_ids() : $this->db->distinct_aa_ids();
        $aa_id = Calendari_Format::normalize_academic_year_id($this->ajax_filter_value('builder_aa_id'));
        if ('' !== $aa_id && !in_array($aa_id, $academic_years, true)) {
            $aa_id = '';
        }
        $course_types = 'esami' === $tipologia ? $this->db->distinct_course_types($aa_id) : array();

        wp_send_json_success(array(
            'academic_years' => array_map(static function (string $value): array { return array('value' => $value, 'label' => Calendari_Format::academic_year_label($value)); }, array_values($academic_years)),
            'course_types' => Calendari_Format::course_type_options($course_types),
        ));
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Non hai i permessi per gestire i calendari.', 'calendari'));
        }

        $tab = sanitize_key($_GET['tab'] ?? 'shortcode');
        $allowed_tabs = array('shortcode', 'database_esami', 'database_laurea', 'grafica', 'importazione');
        if (!in_array($tab, $allowed_tabs, true)) {
            $tab = 'shortcode';
        }

        echo '<div class="wrap calendari-admin">';
        echo '<div class="calendari-admin-header"><div><h1>' . esc_html__('Calendari', 'calendari') . '</h1></div><span class="calendari-admin-version">v' . esc_html(CALENDARI_VERSION) . '</span></div>';

        $this->render_tabs($tab);

        if ('database_laurea' === $tab) {
            $this->render_degree_sessions($this->pull_notice('database_laurea'));
        } elseif ('shortcode' === $tab) {
            $this->render_shortcode_builder();
        } elseif ('database_esami' === $tab) {
            $this->render_repository($this->pull_notice('database_esami'));
        } elseif ('grafica' === $tab) {
            $this->render_graphics_settings($this->pull_notice('grafica'));
        } elseif ('importazione' === $tab) {
            $this->render_import_form($this->pull_notice('importazione'));
        }

        echo '</div>';
    }

    private function render_tabs(string $active_tab): void {
        $tabs = array(
            'shortcode' => __('Shortcode', 'calendari'),
            'database_esami' => __('Database Appelli d\'Esame', 'calendari'),
            'database_laurea' => __('Database Sessioni di Laurea', 'calendari'),
            'grafica' => __('Formato', 'calendari'),
            'importazione' => __('Importazione', 'calendari'),
        );
        echo '<nav class="calendari-tabs" aria-label="' . esc_attr__('Sezioni Calendari', 'calendari') . '">';
        foreach ($tabs as $tab => $label) {
            $url = add_query_arg(array('page' => 'calendari', 'tab' => $tab), admin_url('admin.php'));
            $class = $tab === $active_tab ? ' class="is-active"' : '';
            echo '<a' . $class . ' href="' . esc_url($url) . '"' . ($tab === $active_tab ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private function render_shortcode_builder(): void {
        $requested_tipologia = wp_unslash($_GET['builder_tipologia'] ?? '');
        $tipologia = is_scalar($requested_tipologia) ? sanitize_key((string) $requested_tipologia) : '';
        $tipologia = in_array($tipologia, array('esami', 'laurea'), true) ? $tipologia : '';
        $aa_id = Calendari_Format::normalize_academic_year_id(sanitize_text_field($_GET['builder_aa_id'] ?? ''));
        $tipo_corso = strtolower(Calendari_Format::normalize_course_type(sanitize_text_field($_GET['builder_tipo_corso'] ?? '')));
        $mostra_tipo_prova = sanitize_key($_GET['builder_mostra_tipo_prova'] ?? 'nascondi');
        $mostra_tipo_prova = in_array($mostra_tipo_prova, array('mostra', 'nascondi'), true) ? $mostra_tipo_prova : 'nascondi';
        $filtro_tipo_prova = sanitize_key($_GET['builder_filtro_tipo_prova'] ?? 'tutti');
        $filtro_tipo_prova = in_array($filtro_tipo_prova, array('tutti', 'orali', 'scritte'), true) ? $filtro_tipo_prova : 'tutti';
        $view = sanitize_key($_GET['builder_view'] ?? 'table');
        $aa_options = 'laurea' === $tipologia
            ? $this->degree_db->distinct_aa_ids()
            : ('esami' === $tipologia ? $this->db->distinct_aa_ids() : array());
        if ('' !== $aa_id && !in_array($aa_id, $aa_options, true)) {
            $aa_id = '';
        }
        $course_options = 'esami' === $tipologia
            ? Calendari_Format::course_type_options($this->db->distinct_course_types($aa_id))
            : array();
        $course_values = array_map('strtolower', array_column($course_options, 'value'));
        if ('esami' === $tipologia && !in_array($tipo_corso, $course_values, true)) {
            $tipo_corso = '';
        }
        if ('esami' !== $tipologia) {
            $tipo_corso = '';
        }
        $is_cds_course = 'esami' === $tipologia && 'cds' === $tipo_corso;
        if (!$is_cds_course) {
            $mostra_tipo_prova = 'nascondi';
            $filtro_tipo_prova = 'tutti';
        }
        $tabbed_view_enabled = $is_cds_course && 'tutti' === $filtro_tipo_prova;
        $allowed_views = $tabbed_view_enabled ? array('table', 'grid', 'both', 'tabs') : array('table', 'grid', 'both');
        $view = in_array($view, $allowed_views, true) ? $view : 'table';
        $shortcode = '';
        if ('laurea' === $tipologia && '' !== $aa_id) {
            $shortcode = '[calendari tipologia="laurea" aa_id="' . $aa_id . '" view="' . $view . '"]';
        } elseif ('esami' === $tipologia && '' !== $aa_id && '' !== $tipo_corso) {
            $course_label = Calendari_Format::course_type_label($tipo_corso);
            $shortcode = '[calendari aa_id="' . $aa_id . '" tipo_corso="' . $course_label . '" mostra_tipo_prova="' . $mostra_tipo_prova . '" filtro_tipo_prova="' . $filtro_tipo_prova . '" view="' . $view . '"]';
        }
        $preview_requested = '1' === (string) ($_GET['preview'] ?? '');
        $missing_tipologia = $preview_requested && '' === $tipologia;
        $missing_aa_id = $preview_requested && '' !== $tipologia && '' === $aa_id;
        $missing_tipo_corso = $preview_requested && 'esami' === $tipologia && '' === $tipo_corso;
        $validation_message = '';
        if ($preview_requested && !$shortcode) {
            if ($missing_tipologia) {
                $validation_message = 'Seleziona una tipologia di calendario per generare l’anteprima.';
            } elseif ($missing_aa_id && $missing_tipo_corso) {
                $validation_message = 'Seleziona un anno accademico e un tipo di corso per generare l’anteprima degli esami.';
            } elseif ($missing_aa_id) {
                $validation_message = 'Seleziona un anno accademico per generare l’anteprima.';
            } elseif ($missing_tipo_corso) {
                $validation_message = 'Seleziona un tipo di corso per generare l’anteprima degli esami.';
            }
        }
        $builder_validation_attempted = '' !== $validation_message;
        ?>
        <div class="calendari-card calendari-builder-card">
            <div class="calendari-section-heading">
                <h2><?php esc_html_e('Crea il tuo shortcode', 'calendari'); ?></h2>
                <p><?php esc_html_e('Imposta i parametri e genera lo shortcode da inserire nella pagina del calendario.', 'calendari'); ?></p>
            </div>
            <form method="get" id="calendari-shortcode-builder-form" class="calendari-builder-form calendari-shortcode-builder-form">
                <input type="hidden" name="page" value="calendari">
                <input type="hidden" name="tab" value="shortcode">
                <input type="hidden" id="calendari-shortcode-preview-mode" name="preview" value="1">
                <label><span><?php esc_html_e('Tipologia', 'calendari'); ?></span><select id="calendari-builder-type" name="builder_tipologia">
                    <option value="" <?php selected($tipologia, ''); ?>><?php esc_html_e('Seleziona una tipologia di calendario', 'calendari'); ?></option>
                    <option value="esami" <?php selected($tipologia, 'esami'); ?>><?php esc_html_e('Esami', 'calendari'); ?></option>
                    <option value="laurea" <?php selected($tipologia, 'laurea'); ?>><?php esc_html_e('Sessioni di laurea', 'calendari'); ?></option>
                </select></label>
                <label class="<?php echo '' === $tipologia ? 'is-disabled' : ''; ?>"><span><?php esc_html_e('Anno accademico', 'calendari'); ?></span><select id="calendari-builder-academic-year" name="builder_aa_id" <?php disabled('' === $tipologia); ?>><option value=""><?php esc_html_e('Seleziona un anno accademico', 'calendari'); ?></option><?php foreach ($aa_options as $option) : ?><option value="<?php echo esc_attr($option); ?>" <?php selected($aa_id, $option); ?>><?php echo esc_html(Calendari_Format::academic_year_label($option)); ?></option><?php endforeach; ?></select></label>
                <label class="<?php echo 'esami' === $tipologia ? '' : 'is-disabled'; ?>"><span><?php esc_html_e('Tipo corso', 'calendari'); ?></span><select id="calendari-builder-course-type" name="builder_tipo_corso" <?php disabled('esami' !== $tipologia); ?>><option value=""><?php esc_html_e('Seleziona un tipo corso', 'calendari'); ?></option><?php foreach ($course_options as $option) : ?><option value="<?php echo esc_attr(strtolower($option['value'])); ?>" <?php selected($tipo_corso, strtolower($option['value'])); ?>><?php echo esc_html($option['label']); ?></option><?php endforeach; ?></select></label>
                <label class="<?php echo $is_cds_course ? '' : 'is-disabled'; ?>"><span><?php esc_html_e('Mostra colonna tipo prova', 'calendari'); ?></span><select name="builder_mostra_tipo_prova" <?php disabled(!$is_cds_course); ?>>
                    <option value="nascondi" <?php selected($mostra_tipo_prova, 'nascondi'); ?>><?php esc_html_e('Nascosta', 'calendari'); ?></option>
                    <option value="mostra" <?php selected($mostra_tipo_prova, 'mostra'); ?>><?php esc_html_e('Mostra', 'calendari'); ?></option>
                </select></label>
                <label class="<?php echo $is_cds_course ? '' : 'is-disabled'; ?>"><span><?php esc_html_e('TIPO PROVA', 'calendari'); ?></span><select name="builder_filtro_tipo_prova" <?php disabled(!$is_cds_course); ?>>
                    <option value="tutti" <?php selected($filtro_tipo_prova, 'tutti'); ?>><?php esc_html_e('Tutti', 'calendari'); ?></option>
                    <option value="orali" <?php selected($filtro_tipo_prova, 'orali'); ?>><?php esc_html_e('Orali', 'calendari'); ?></option>
                    <option value="scritte" <?php selected($filtro_tipo_prova, 'scritte'); ?>><?php esc_html_e('Scritte', 'calendari'); ?></option>
                </select></label>
                <label class="<?php echo '' === $tipologia ? 'is-disabled' : ''; ?>"><span><?php esc_html_e('Visualizzazione', 'calendari'); ?></span><select name="builder_view" <?php disabled('' === $tipologia); ?>>
                    <option value="table" <?php selected($view, 'table'); ?>><?php esc_html_e('Tabella', 'calendari'); ?></option>
                    <option value="grid" <?php selected($view, 'grid'); ?>><?php esc_html_e('Griglia', 'calendari'); ?></option>
                    <option value="both" <?php selected($view, 'both'); ?>><?php esc_html_e('Tabella e griglia', 'calendari'); ?></option>
                    <option value="tabs" <?php selected($view, 'tabs'); ?> <?php disabled(!$tabbed_view_enabled); ?>><?php esc_html_e('Vista a schede', 'calendari'); ?></option>
                </select></label>
                <button type="submit" class="button button-primary" <?php disabled('' === $tipologia); ?>><?php esc_html_e('Genera anteprima', 'calendari'); ?></button>
                <span class="screen-reader-text" data-builder-status role="status" aria-live="polite"></span>
                <?php if ($validation_message) : ?>
                    <div id="calendari-builder-validation" class="calendari-builder-validation calendari-status-notice is-error" role="alert" aria-live="assertive"><p><?php echo esc_html($validation_message); ?></p></div>
                <?php endif; ?>
            </form>
            <?php if (!$builder_validation_attempted && 'laurea' === $tipologia && !$aa_options) : ?>
                <div class="calendari-builder-validation calendari-status-notice" role="status"><p><?php esc_html_e('Il database non contiene ancora anni accademici per le sessioni di laurea.', 'calendari'); ?></p></div>
            <?php elseif (!$builder_validation_attempted && 'esami' === $tipologia && (!$aa_options || !$course_options)) : ?>
                <div class="calendari-builder-validation calendari-status-notice" role="status"><p><?php esc_html_e('L’archivio non contiene ancora valori disponibili per il generatore di shortcode.', 'calendari'); ?></p></div>
            <?php endif; ?>
            <?php if ($shortcode) : ?>
                <div class="calendari-generated-shortcode">
                    <label for="calendari-generated-code"><?php esc_html_e('SHORTCODE', 'calendari'); ?></label>
                    <div class="calendari-copy-row"><input id="calendari-generated-code" type="text" readonly value="<?php echo esc_attr($shortcode); ?>"><button type="button" class="button" data-copy-target="calendari-generated-code"><?php esc_html_e('Copia', 'calendari'); ?></button></div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ('1' === (string) ($_GET['preview'] ?? '') && $shortcode) : ?>
            <div class="calendari-card calendari-preview-card">
                <div class="calendari-section-heading">
                    <h2><?php esc_html_e('Anteprima', 'calendari'); ?></h2>
                </div>
                <?php echo do_shortcode($shortcode); ?>
            </div>
        <?php endif; ?>
        <?php
    }

    private function render_degree_sessions(?array $notice = null): void {
        $filters = array(
            'aa_id' => Calendari_Format::normalize_academic_year_id(sanitize_text_field($_GET['aa_id'] ?? '')) ,
            'mese' => absint($_GET['mese'] ?? 0),
            'data' => sanitize_text_field($_GET['data'] ?? ''),
            'sede' => sanitize_text_field($_GET['sede'] ?? ''),
        );
        $aa_values = $this->degree_db->distinct_aa_ids();
        if ('' !== $filters['aa_id'] && !in_array($filters['aa_id'], $aa_values, true)) {
            $filters['aa_id'] = '';
        }

        $month_values = $this->degree_db->distinct_months();
        if ($filters['mese'] && !in_array($filters['mese'], $month_values, true)) {
            $filters['mese'] = 0;
        }

        $date_values = $this->degree_db->distinct_dates();
        if ('' !== $filters['data'] && !in_array($filters['data'], $date_values, true)) {
            $filters['data'] = '';
        }

        $venue_values = $this->degree_db->distinct_venues();
        if ('' !== $filters['sede'] && !in_array($filters['sede'], $venue_values, true)) {
            $filters['sede'] = '';
        }
        $has_filters = '' !== $filters['aa_id'] || $filters['mese'] > 0 || '' !== $filters['data'] || '' !== $filters['sede'];

        $aa_options = array_map(static function (string $value): array {
            return array('value' => $value, 'label' => Calendari_Format::academic_year_label($value));
        }, $aa_values);
        $month_options = array_map(static function (int $value): array {
            return array('value' => (string) $value, 'label' => Calendari_Format::month_name($value, true));
        }, $month_values);
        $date_options = array_map(static function (string $value): array {
            return array('value' => $value, 'label' => Calendari_Format::format_date($value));
        }, $date_values);
        $venue_options = array_map(static function (string $value): array {
            return array('value' => $value, 'label' => $value);
        }, $venue_values);

        $page = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 25;
        $total = $this->degree_db->count($filters);
        $records = $this->degree_db->list($filters, $per_page, ($page - 1) * $per_page);
        ?>
        <div class="calendari-card">
            <div class="calendari-section-heading calendari-repository-heading">
                <h2><?php esc_html_e('Database Sessioni di Laurea', 'calendari'); ?></h2>
                <button type="button" class="button button-primary calendari-degree-add-button" data-degree-session-create>
                    <i class="ti ti-plus" aria-hidden="true"></i>
                    <span><?php esc_html_e('Nuova Sessione', 'calendari'); ?></span>
                </button>
            </div>
            <form method="get" class="calendari-filters" data-ajax-filters>
                <input type="hidden" name="page" value="calendari">
                <input type="hidden" name="tab" value="database_laurea">
                <?php $this->render_searchable_filter('aa_id', 'Anno accademico', '', 'Tutti gli anni', $aa_options, $filters['aa_id']); ?>
                <?php $this->render_searchable_filter('mese', 'Mese', '0', 'Tutti i mesi', $month_options, (string) $filters['mese']); ?>
                <?php $this->render_searchable_filter('data', 'Data', '', 'Tutte le date', $date_options, $filters['data']); ?>
                <?php $this->render_searchable_filter('sede', 'Sede', '', 'Tutte le sedi', $venue_options, $filters['sede']); ?>
                <button type="button" class="button calendari-icon-button calendari-filter-submit" data-clear-filters aria-label="<?php esc_attr_e('Cancella filtri', 'calendari'); ?>" title="<?php esc_attr_e('Cancella filtri', 'calendari'); ?>" <?php disabled(!$has_filters); ?>>
                    <i class="ti ti-filter-x" aria-hidden="true"></i>
                </button>
                <span class="screen-reader-text" data-filter-status role="status" aria-live="polite"></span>
            </form>
            <?php if ($notice) : ?>
                <?php $this->render_notice($notice); ?>
            <?php endif; ?>
            <div class="calendari-filter-results" data-filter-results aria-live="polite">
                <?php $this->render_degree_session_results($records, $total, $page, $filters); ?>
            </div>
            <dialog class="calendari-degree-modal" id="calendari-degree-session-modal" aria-labelledby="calendari-degree-modal-title">
                <form method="post" class="calendari-degree-modal-form">
                    <?php wp_nonce_field('calendari_degree_save', 'calendari_degree_nonce'); ?>
                    <input type="hidden" id="calendari-degree-modal-id" name="id" value="0">
                    <input type="hidden" name="calendari_degree_save" value="1">
                    <header class="calendari-degree-modal-header">
                        <div>
                            <p class="calendari-degree-modal-eyebrow"><?php esc_html_e('Database Sessioni di Laurea', 'calendari'); ?></p>
                            <h2 id="calendari-degree-modal-title"><?php esc_html_e('Nuova Sessione', 'calendari'); ?></h2>
                        </div>
                    </header>
                    <div class="calendari-degree-modal-fields">
                        <label for="calendari-degree-modal-aa-id">
                            <span>AA_ID</span>
                            <input type="text" id="calendari-degree-modal-aa-id" name="aa_id" placeholder="2026" required>
                        </label>
                        <label for="calendari-degree-modal-date">
                            <span>DATA</span>
                            <input type="date" id="calendari-degree-modal-date" name="data" required>
                        </label>
                        <label for="calendari-degree-modal-venue">
                            <span>SEDE</span>
                            <input type="text" id="calendari-degree-modal-venue" name="sede" required>
                        </label>
                    </div>
                    <footer class="calendari-degree-modal-actions">
                        <button type="button" class="button calendari-degree-cancel-button" data-degree-modal-cancel>
                            <i class="ti ti-x" aria-hidden="true"></i>
                            <span><?php esc_html_e('Annulla', 'calendari'); ?></span>
                        </button>
                        <button type="submit" class="button button-primary calendari-degree-save-button">
                            <i class="ti ti-device-floppy" aria-hidden="true"></i>
                            <span><?php esc_html_e('Salva', 'calendari'); ?></span>
                        </button>
                    </footer>
                </form>
            </dialog>
        </div>
        <?php
    }

    private function render_degree_session_results(array $records, int $total, int $page, array $filters): void {
        ?>
        <div class="calendari-admin-table-wrap">
            <table class="calendari-admin-table calendari-degree-table">
                <colgroup><col><col><col><col><col class="calendari-actions-column"></colgroup>
                <thead><tr><th>AA_ID</th><th><?php esc_html_e('Mese', 'calendari'); ?></th><th>DATA</th><th>SEDE</th><th class="calendari-actions-column"><?php esc_html_e('Azioni', 'calendari'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($records as $record) : ?>
                        <?php $is_hidden = !empty($record['is_hidden']); ?>
                        <tr class="<?php echo $is_hidden ? 'calendari-record-hidden' : ''; ?>">
                            <td><?php echo esc_html(Calendari_Format::academic_year_label((string) $record['aa_id'])); ?></td>
                            <td><?php echo esc_html(Calendari_Format::month_name_with_year((string) $record['data'])); ?></td>
                            <td><?php echo esc_html(Calendari_Format::format_date($record['data'])); ?></td>
                            <td><?php echo esc_html($record['sede']); ?></td>
                            <td class="calendari-actions-column">
                                <div class="calendari-degree-actions">
                                    <a class="calendari-icon-button calendari-icon-button-visibility<?php echo $is_hidden ? ' is-hidden' : ''; ?>" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'calendari', 'tab' => 'database_laurea', 'calendari_degree_visibility' => $record['id'], 'calendari_degree_hidden' => $is_hidden ? '0' : '1'), admin_url('admin.php')), 'calendari_degree_visibility_' . $record['id'])); ?>" aria-label="<?php echo esc_attr($is_hidden ? __('Mostra la sessione nei calendari pubblici', 'calendari') : __('Nascondi la sessione dai calendari pubblici', 'calendari')); ?>" title="<?php echo esc_attr($is_hidden ? __('Mostra', 'calendari') : __('Nascondi', 'calendari')); ?>">
                                        <i class="ti <?php echo $is_hidden ? 'ti-eye-off' : 'ti-eye'; ?>" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="calendari-icon-button calendari-icon-button-edit" data-degree-session-edit data-id="<?php echo esc_attr($record['id']); ?>" data-aa-id="<?php echo esc_attr($record['aa_id']); ?>" data-date="<?php echo esc_attr($record['data']); ?>" data-venue="<?php echo esc_attr($record['sede']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Modifica sessione %s', 'calendari'), $record['aa_id'])); ?>" title="<?php esc_attr_e('Modifica', 'calendari'); ?>">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </button>
                                    <a class="calendari-icon-button calendari-icon-button-delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'calendari', 'tab' => 'database_laurea', 'calendari_degree_delete' => $record['id']), admin_url('admin.php')), 'calendari_degree_delete_' . $record['id'])); ?>" aria-label="<?php echo esc_attr(sprintf(__('Elimina sessione %s', 'calendari'), $record['aa_id'])); ?>" title="<?php esc_attr_e('Elimina', 'calendari'); ?>" onclick="return confirm('<?php echo esc_js(__('Eliminare questa sessione?', 'calendari')); ?>');">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        echo wp_kses_post(paginate_links(array(
            'base' => add_query_arg(array('page' => 'calendari', 'tab' => 'database_laurea', 'paged' => '%#%'), admin_url('admin.php')),
            'format' => '',
            'current' => $page,
            'total' => max(1, (int) ceil($total / 25)),
            'add_args' => array_filter($filters),
        )));
    }

    private function render_graphics_settings(?array $notice = null): void {
        $settings = $this->get_graphics_settings();
        ?>
        <div class="calendari-card">
            <div class="calendari-section-heading">
                <h2><?php esc_html_e('Formato', 'calendari'); ?></h2>
            </div>
            <form method="post" class="calendari-settings-form">
                <?php wp_nonce_field('calendari_save_graphics', 'calendari_graphics_nonce'); ?>
                <div class="calendari-setting-control"><label><span><?php esc_html_e('BORDER RADIUS', 'calendari'); ?></span><input type="number" name="border_radius" min="0" max="40" value="<?php echo esc_attr($settings['border_radius']); ?>"></label><button type="submit" name="calendari_reset_graphics" value="border_radius" class="button"><?php esc_html_e('Ripristina', 'calendari'); ?></button></div>
                <div class="calendari-setting-control"><label><span><?php esc_html_e('FONT', 'calendari'); ?></span><select name="font_family">
                    <option value="Nunito" <?php selected($settings['font_family'], 'Nunito'); ?>>Nunito</option>
                    <option value="system-ui" <?php selected($settings['font_family'], 'system-ui'); ?>><?php esc_html_e('Font di sistema', 'calendari'); ?></option>
                    <option value="Arial" <?php selected($settings['font_family'], 'Arial'); ?>>Arial</option>
                    <option value="Georgia" <?php selected($settings['font_family'], 'Georgia'); ?>>Georgia</option>
                </select></label><button type="submit" name="calendari_reset_graphics" value="font_family" class="button"><?php esc_html_e('Ripristina', 'calendari'); ?></button></div>
                <div class="calendari-setting-control"><?php $this->render_color_setting('accent_color', 'ACCENT', $settings['accent_color']); ?><button type="submit" name="calendari_reset_graphics" value="accent_color" class="button"><?php esc_html_e('Ripristina', 'calendari'); ?></button></div>
                <div class="calendari-setting-control"><label><span><?php esc_html_e('Formato data', 'calendari'); ?></span><select name="date_format">
                    <?php foreach (Calendari_Format::date_formats() as $format => $label) : ?>
                        <option value="<?php echo esc_attr($format); ?>" <?php selected($settings['date_format'], $format); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select></label><button type="submit" name="calendari_reset_graphics" value="date_format" class="button"><?php esc_html_e('Ripristina', 'calendari'); ?></button></div>
                <button type="submit" name="calendari_save_graphics" class="button button-primary"><?php esc_html_e('Salva impostazioni', 'calendari'); ?></button>
                <?php if ($notice) : ?>
                    <?php $this->render_notice($notice); ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    private function render_color_setting(string $name, string $label, string $value): void {
        ?>
        <label><span><?php esc_html_e($label, 'calendari'); ?></span><input type="color" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>"></label>
        <?php
    }

    private function handle_graphics_save(): void {
        check_admin_referer('calendari_save_graphics', 'calendari_graphics_nonce');
        $defaults = $this->get_graphics_settings();
        $settings = array(
            'border_radius' => max(0, min(40, absint($_POST['border_radius'] ?? $defaults['border_radius']))),
            'font_family' => in_array($_POST['font_family'] ?? '', array('Nunito', 'system-ui', 'Arial', 'Georgia'), true) ? sanitize_text_field($_POST['font_family']) : $defaults['font_family'],
            'accent_color' => $this->sanitize_color($_POST['accent_color'] ?? '', $defaults['accent_color']),
            'date_format' => Calendari_Format::sanitize_date_format(wp_unslash($_POST['date_format'] ?? '')) ?: $defaults['date_format'],
        );
        update_option('calendari_graphics', $settings);
        $this->redirect_with_notice(array('messages' => array(__('Impostazioni di formato salvate.', 'calendari'))), array('tab' => 'grafica'));
    }

    private function handle_graphics_reset(): void {
        check_admin_referer('calendari_save_graphics', 'calendari_graphics_nonce');
        $field = sanitize_key($_POST['calendari_reset_graphics'] ?? '');
        $defaults = $this->get_graphics_defaults();
        if (!isset($defaults[$field])) {
            return;
        }

        $settings = $this->get_graphics_settings();
        $settings[$field] = $defaults[$field];
        update_option('calendari_graphics', $settings);
        $this->redirect_with_notice(array('messages' => array(__('Campo ripristinato.', 'calendari'))), array('tab' => 'grafica'));
    }

    private function get_graphics_settings(): array {
        return wp_parse_args(get_option('calendari_graphics', array()), $this->get_graphics_defaults());
    }

    private function get_graphics_defaults(): array {
        return array(
            'border_radius' => 10,
            'font_family' => 'Nunito',
            'accent_color' => '#037aa4',
            'date_format' => 'd/m/Y',
        );
    }

    private function sanitize_color(string $value, string $fallback): string {
        $value = sanitize_hex_color($value);

        return $value ?: $fallback;
    }

    private function handle_import(): void {
        check_admin_referer('calendari_import', 'calendari_nonce');
        if (empty($_FILES['calendari_file']['tmp_name'])) {
            $this->redirect_with_notice(array('messages' => array(__('Seleziona un file CSV o XLSX.', 'calendari'))), array('tab' => 'importazione'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload(
            $_FILES['calendari_file'],
            array(
                'test_form' => false,
                'mimes' => array(
                    'csv' => 'text/csv',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            )
        );

        if (!empty($upload['error'])) {
            $this->redirect_with_notice(array('messages' => array($upload['error'])), array('tab' => 'importazione'));
        }

        $result = (new Calendari_Importer($this->db))->import_file($upload['file']);
        wp_delete_file($upload['file']);

        if (is_wp_error($result)) {
            $this->redirect_with_notice(array('messages' => array($result->get_error_message())), array('tab' => 'importazione'));
        }

        $messages = array(
            sprintf(__('Righe elaborate: %1$d; inserite: %2$d; aggiornate: %3$d.', 'calendari'), $result['rows'], $result['inserted'], $result['updated']),
        );
        if ($result['errors']) {
                $messages[] = sprintf(__('Righe non valide ignorate: %d.', 'calendari'), count($result['errors']));
        }

        $this->redirect_with_notice(array('messages' => array_merge($messages, $result['errors'])), array('tab' => 'importazione'));
    }

    private function handle_save(): void {
        check_admin_referer('calendari_save', 'calendari_nonce');
        $id = absint($_POST['id'] ?? 0);
        $values = array();
        foreach (Calendari_Importer::HEADERS as $header) {
            $values[$header] = sanitize_text_field(wp_unslash($_POST[strtolower($header)] ?? ''));
        }

        $validation = Calendari_Importer::validate_values($values);
        if ($validation['errors']) {
            $this->redirect_with_notice(array('messages' => $validation['errors']), array('tab' => 'database_esami'));
        }

        $record = $validation['record'];
        $booking_dates_tbd = wp_unslash($_POST['booking_dates_tbd'] ?? '');
        $record['booking_dates_tbd'] = is_scalar($booking_dates_tbd) && '1' === (string) $booking_dates_tbd ? 1 : 0;
        $saved = $id ? $this->db->update($id, $record) : $this->db->upsert($record);
        if (false === $saved) {
            $this->redirect_with_notice(array('messages' => array(__('Impossibile salvare il record.', 'calendari'))), array('tab' => 'database_esami'));
        }

        $this->redirect_with_notice(array('messages' => array(__('Record calendario salvato.', 'calendari'))), array('tab' => 'database_esami'));
    }

    private function handle_delete(): void {
        $id = absint($_GET['calendari_delete']);
        check_admin_referer('calendari_delete_' . $id);
        if ($id && false !== $this->db->delete($id)) {
            $this->redirect_with_notice(array('messages' => array(__('Record calendario eliminato.', 'calendari'))), array('tab' => 'database_esami'));
        }

        $this->redirect_with_notice(array('messages' => array(__('Impossibile eliminare il record.', 'calendari'))), array('tab' => 'database_esami'));
    }

    private function handle_visibility(): void {
        $id = absint($_GET['calendari_visibility'] ?? 0);
        $requested_state = wp_unslash($_GET['calendari_hidden'] ?? '');
        if (!$id || !is_scalar($requested_state) || !in_array((string) $requested_state, array('0', '1'), true)) {
            $this->redirect_with_notice(array('messages' => array(__('Impostazione di visibilità dell’appello non valida.', 'calendari'))), array('tab' => 'database_esami'));
        }
        check_admin_referer('calendari_visibility_' . $id);

        $record = $this->db->find($id);
        $hidden = '1' === (string) $requested_state;
        if ($record && false !== $this->db->set_hidden($id, $hidden)) {
            $message = $hidden ? __('Appello nascosto dai calendari pubblici.', 'calendari') : __('Appello ripristinato nei calendari pubblici.', 'calendari');
            $this->redirect_with_notice(array('messages' => array($message)), array('tab' => 'database_esami'));
        }

        $this->redirect_with_notice(array('messages' => array(__('Impossibile aggiornare la visibilità dell’appello.', 'calendari'))), array('tab' => 'database_esami'));
    }

    private function handle_degree_save(): void {
        check_admin_referer('calendari_degree_save', 'calendari_degree_nonce');
        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $record = array(
            'aa_id' => Calendari_Format::normalize_academic_year_id(sanitize_text_field(wp_unslash($_POST['aa_id'] ?? ''))),
            'data' => Calendari_Importer::normalize_date(wp_unslash($_POST['data'] ?? '')),
            'sede' => sanitize_text_field(wp_unslash($_POST['sede'] ?? '')),
        );
        $errors = array();

        if ('' === $record['aa_id']) {
            $errors[] = __('AA_ID è obbligatorio.', 'calendari');
        }
        if ('' === $record['data']) {
            $errors[] = __('DATA deve essere una data valida.', 'calendari');
        } else {
            $record['mese'] = Calendari_Format::month_from_date($record['data']);
            if (null === $record['mese']) {
                $errors[] = __('DATA deve essere una data valida.', 'calendari');
            }
        }
        if ('' === $record['sede']) {
            $errors[] = __('SEDE è obbligatoria.', 'calendari');
        }

        if ($errors) {
            $this->redirect_with_notice(array('messages' => $errors), array('tab' => 'database_laurea'));
        }

        $result = $id ? $this->degree_db->update($id, $record) : $this->degree_db->insert($record);
        if (false === $result) {
            $this->redirect_with_notice(
                array('messages' => array(__('Impossibile salvare la sessione di laurea.', 'calendari'))),
                array('tab' => 'database_laurea')
            );
        }

        $message = $id ? __('Sessione di laurea aggiornata.', 'calendari') : __('Sessione di laurea inserita.', 'calendari');
        $this->redirect_with_notice(array('messages' => array($message)), array('tab' => 'database_laurea'));
    }

    private function handle_degree_delete(): void {
        $id = absint($_GET['calendari_degree_delete']);
        check_admin_referer('calendari_degree_delete_' . $id);
        if ($id && false !== $this->degree_db->delete($id)) {
            $this->redirect_with_notice(array('messages' => array(__('Sessione di laurea eliminata.', 'calendari'))), array('tab' => 'database_laurea'));
        }

        $this->redirect_with_notice(array('messages' => array(__('Impossibile eliminare la sessione di laurea.', 'calendari'))), array('tab' => 'database_laurea'));
    }

    private function handle_degree_visibility(): void {
        $id = absint($_GET['calendari_degree_visibility'] ?? 0);
        $requested_state = wp_unslash($_GET['calendari_degree_hidden'] ?? '');
        if (!$id || !is_scalar($requested_state) || !in_array((string) $requested_state, array('0', '1'), true)) {
            $this->redirect_with_notice(array('messages' => array(__('Impostazione di visibilità della sessione non valida.', 'calendari'))), array('tab' => 'database_laurea'));
        }
        check_admin_referer('calendari_degree_visibility_' . $id);

        $record = $this->degree_db->find($id);
        $hidden = '1' === (string) $requested_state;
        if ($record && false !== $this->degree_db->set_hidden($id, $hidden)) {
            $message = $hidden ? __('Sessione di laurea nascosta dai calendari pubblici.', 'calendari') : __('Sessione di laurea ripristinata nei calendari pubblici.', 'calendari');
            $this->redirect_with_notice(array('messages' => array($message)), array('tab' => 'database_laurea'));
        }

        $this->redirect_with_notice(array('messages' => array(__('Impossibile aggiornare la visibilità della sessione di laurea.', 'calendari'))), array('tab' => 'database_laurea'));
    }

    private function render_import_form(?array $notice = null): void {
        ?>
        <div class="calendari-card calendari-import-card">
            <div class="calendari-section-heading">
                <h2><?php esc_html_e('Importa calendario esami', 'calendari'); ?></h2>
                <p><?php esc_html_e('Upload a CSV or XLSX file. AA_ID is the first year only, for example 2026; the interface displays the full academic year. TIPO_CORSO accepts a course code or full course name without regard to capitalization. CDS_IDS is optional and accepts comma-separated study-course codes. GRUPPO is optional and creates a separate table for matching events.', 'calendari'); ?></p>
                <a href="<?php echo esc_url(CALENDARI_URL . 'examples/calendar-sample.csv'); ?>" download><?php esc_html_e('Scarica il modello CSV', 'calendari'); ?></a>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('calendari_import', 'calendari_nonce'); ?>
                <input type="file" id="calendari_file" name="calendari_file" accept=".csv,.xlsx" required>
                <button type="submit" name="calendari_import" class="button button-primary"><?php esc_html_e('Importa', 'calendari'); ?></button>
                <?php if ($notice) : ?>
                    <?php $this->render_notice($notice); ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    private function render_searchable_filter(string $name, string $label, string $all_value, string $all_label, array $options, string $selected_value): void {
        $select_id = 'calendari-filter-' . sanitize_key($name);
        $panel_id = $select_id . '-panel';
        $search_id = $select_id . '-search';
        $list_id = $select_id . '-options';
        $empty_id = $select_id . '-empty';
        $selected_label = $all_label;
        foreach ($options as $option) {
            if ((string) $option['value'] === $selected_value) {
                $selected_label = $option['label'];
                break;
            }
        }
        ?>
        <div class="calendari-filter-dropdown" data-searchable-dropdown>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($selected_value); ?>" data-dropdown-value data-default-value="<?php echo esc_attr($all_value); ?>">
            <button type="button" class="calendari-filter-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?php echo esc_attr($panel_id); ?>">
                <span data-dropdown-current><?php echo esc_html($selected_label); ?></span>
            </button>
            <div id="<?php echo esc_attr($panel_id); ?>" class="calendari-filter-panel" aria-label="<?php echo esc_attr($label); ?>" hidden>
                <input type="search" id="<?php echo esc_attr($search_id); ?>" data-dropdown-search aria-label="<?php echo esc_attr(sprintf(__('Cerca %s', 'calendari'), strtolower($label))); ?>" placeholder="<?php echo esc_attr(sprintf(__('Cerca %s', 'calendari'), strtolower($label))); ?>" autocomplete="off" aria-controls="<?php echo esc_attr($list_id); ?>" aria-expanded="true">
                <div id="<?php echo esc_attr($list_id); ?>" class="calendari-filter-options" role="listbox" aria-label="<?php echo esc_attr($label); ?>">
                    <button type="button" class="calendari-filter-option" role="option" data-value="<?php echo esc_attr($all_value); ?>" aria-selected="<?php echo $selected_value === $all_value ? 'true' : 'false'; ?>"><?php echo esc_html($all_label); ?></button>
                <?php foreach ($options as $option) : ?>
                    <button type="button" class="calendari-filter-option" role="option" data-value="<?php echo esc_attr((string) $option['value']); ?>" aria-selected="<?php echo $selected_value === (string) $option['value'] ? 'true' : 'false'; ?>"><?php echo esc_html($option['label']); ?></button>
                <?php endforeach; ?>
                </div>
                <p id="<?php echo esc_attr($empty_id); ?>" class="calendari-filter-empty" aria-live="polite" hidden><?php esc_html_e('Nessun valore trovato.', 'calendari'); ?></p>
            </div>
        </div>
        <?php
    }

    private function render_repository(?array $notice = null): void {
        $filters = array(
            'aa_id' => Calendari_Format::normalize_academic_year_id(sanitize_text_field($_GET['aa_id'] ?? '')) ,
            'tipo_corso' => Calendari_Format::normalize_course_type(sanitize_text_field($_GET['tipo_corso'] ?? '')),
            'gruppo' => sanitize_text_field($_GET['gruppo'] ?? ''),
            'tipo_prova' => sanitize_text_field($_GET['tipo_prova'] ?? ''),
            'mese' => absint($_GET['mese'] ?? 0),
        );
        $aa_values = $this->db->distinct_aa_ids();
        if ('' !== $filters['aa_id'] && !in_array($filters['aa_id'], $aa_values, true)) {
            $filters['aa_id'] = '';
        }

        $course_options = Calendari_Format::course_type_options($this->db->distinct_course_types($filters['aa_id']));
        $course_values = array_column($course_options, 'value');
        if ('' !== $filters['tipo_corso'] && !in_array($filters['tipo_corso'], $course_values, true)) {
            $filters['tipo_corso'] = '';
        }

        $group_values = $this->db->distinct_group_names($filters['aa_id'], $filters['tipo_corso']);
        if ('' !== $filters['gruppo'] && !in_array($filters['gruppo'], $group_values, true)) {
            $filters['gruppo'] = '';
        }

        $exam_type_options = array();
        foreach ($this->db->distinct_exam_types() as $exam_type) {
            $option_value = '' === $exam_type ? '__empty' : $exam_type;
            $option_label = '' === $exam_type ? __('Non specificato', 'calendari') : $exam_type;
            $exam_type_options[] = array('value' => $option_value, 'label' => $option_label);
        }
        $exam_type_values = array_column($exam_type_options, 'value');
        if ('' !== $filters['tipo_prova'] && !in_array($filters['tipo_prova'], $exam_type_values, true)) {
            $filters['tipo_prova'] = '';
        }

        $month_options = array();
        foreach ($this->db->distinct_months() as $month) {
            $month_options[] = array(
                'value' => (string) $month,
                'label' => Calendari_Format::month_name($month, true),
            );
        }
        $month_values = array_map('intval', array_column($month_options, 'value'));
        if ($filters['mese'] && !in_array($filters['mese'], $month_values, true)) {
            $filters['mese'] = 0;
        }
        $has_filters = '' !== $filters['aa_id'] || '' !== $filters['tipo_corso'] || '' !== $filters['gruppo'] || '' !== $filters['tipo_prova'] || $filters['mese'] > 0;

        $aa_options = array_map(static function (string $value): array {
            return array('value' => $value, 'label' => Calendari_Format::academic_year_label($value));
        }, $aa_values);
        $group_options = array();
        foreach ($group_values as $group_value) {
            $group_options[] = array('value' => $group_value, 'label' => $group_value);
        }

        $page = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 25;
        $total = $this->db->count($filters);
        $records = $this->db->list($filters, $per_page, ($page - 1) * $per_page);
        ?>
        <div class="calendari-card">
            <div class="calendari-section-heading calendari-repository-heading">
                <h2><?php esc_html_e('Database Appelli d\'Esame', 'calendari'); ?></h2>
                <button type="button" class="button button-primary calendari-degree-add-button" data-exam-session-create>
                    <i class="ti ti-plus" aria-hidden="true"></i>
                    <span><?php esc_html_e('Nuovo Appello', 'calendari'); ?></span>
                </button>
            </div>
            <form method="get" class="calendari-filters" data-ajax-filters>
                <input type="hidden" name="page" value="calendari">
                <input type="hidden" name="tab" value="database_esami">
                <?php $this->render_searchable_filter('aa_id', 'Anno accademico', '', 'Tutti gli anni', $aa_options, $filters['aa_id']); ?>
                <?php $this->render_searchable_filter('tipo_corso', 'Tipo corso', '', 'Tutti i tipi di corso', $course_options, $filters['tipo_corso']); ?>
                <?php $this->render_searchable_filter('gruppo', 'Gruppo', '', 'Tutti i gruppi', $group_options, $filters['gruppo']); ?>
                <?php $this->render_searchable_filter('tipo_prova', 'Tipo prova', '', 'Tutti i tipi di prova', $exam_type_options, $filters['tipo_prova']); ?>
                <?php $this->render_searchable_filter('mese', 'Mese', '0', 'Tutti i mesi', $month_options, (string) $filters['mese']); ?>
                <button type="button" class="button calendari-icon-button calendari-filter-submit" data-clear-filters aria-label="<?php esc_attr_e('Cancella filtri', 'calendari'); ?>" title="<?php esc_attr_e('Cancella filtri', 'calendari'); ?>" <?php disabled(!$has_filters); ?>>
                    <i class="ti ti-filter-x" aria-hidden="true"></i>
                </button>
                <span class="screen-reader-text" data-filter-status role="status" aria-live="polite"></span>
            </form>
            <?php if ($notice) : ?>
                <?php $this->render_notice($notice); ?>
            <?php endif; ?>
            <div class="calendari-filter-results" data-filter-results aria-live="polite">
                <?php $this->render_exam_repository_results($records, $total, $page, $filters); ?>
            </div>
            <dialog class="calendari-degree-modal calendari-exam-modal" id="calendari-exam-modal" aria-labelledby="calendari-exam-modal-title">
                <form method="post" class="calendari-degree-modal-form">
                    <?php wp_nonce_field('calendari_save', 'calendari_nonce'); ?>
                    <input type="hidden" id="calendari-exam-modal-id" name="id" value="0">
                    <input type="hidden" name="calendari_save" value="1">
                    <header class="calendari-degree-modal-header">
                        <div>
                            <p class="calendari-degree-modal-eyebrow"><?php esc_html_e('Database Appelli d\'Esame', 'calendari'); ?></p>
                            <h2 id="calendari-exam-modal-title"><?php esc_html_e('Nuovo Appello', 'calendari'); ?></h2>
                        </div>
                    </header>
                    <div class="calendari-degree-modal-fields calendari-exam-modal-fields">
                        <label for="calendari-exam-modal-aa-id"><span>AA_ID</span><input type="text" id="calendari-exam-modal-aa-id" name="aa_id" placeholder="2026" required></label>
                        <label for="calendari-exam-modal-course"><span><?php esc_html_e('Tipo di corso', 'calendari'); ?></span><select id="calendari-exam-modal-course" name="tipo_corso" required>
                            <option value=""><?php esc_html_e('Seleziona un tipo di corso', 'calendari'); ?></option>
                            <?php foreach (Calendari_Format::course_type_labels() as $course_code => $course_label) : ?>
                                <option value="<?php echo esc_attr($course_code); ?>"><?php echo esc_html($course_label); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label for="calendari-exam-modal-cds-ids"><span>CDS_IDS (facoltativo)</span><input type="text" id="calendari-exam-modal-cds-ids" name="cds_ids" placeholder="M072,M070"></label>
                        <label for="calendari-exam-modal-group"><span><?php esc_html_e('Gruppo (facoltativo)', 'calendari'); ?></span><input type="text" id="calendari-exam-modal-group" name="gruppo" list="calendari-exam-group-options"><datalist id="calendari-exam-group-options"><?php foreach ($this->db->distinct_group_names() as $group_name) : ?><option value="<?php echo esc_attr($group_name); ?>"></option><?php endforeach; ?></datalist></label>
                        <label for="calendari-exam-modal-booking-open"><span>DATA_APERTURA_PRENOTAZIONI</span><input type="date" id="calendari-exam-modal-booking-open" name="data_apertura_prenotazioni" required></label>
                        <label for="calendari-exam-modal-booking-close"><span>DATA_CHIUSURA_PRENOTAZIONI</span><input type="date" id="calendari-exam-modal-booking-close" name="data_chiusura_prenotazioni" required></label>
                        <label for="calendari-exam-modal-booking-tbd">
                            <span><?php esc_html_e('Date prenotazioni:', 'calendari'); ?></span>
                            <select id="calendari-exam-modal-booking-tbd" name="booking_dates_tbd">
                                <option value="0"><?php esc_html_e('Mostra', 'calendari'); ?></option>
                                <option value="1"><?php esc_html_e('Nascondi', 'calendari'); ?></option>
                            </select>
                        </label>
                        <label for="calendari-exam-modal-date"><span>DATA_ESAMI</span><input type="date" id="calendari-exam-modal-date" name="data_esami" required></label>
                        <label for="calendari-exam-modal-pw-deadline"><span><?php esc_html_e('Termine consegna Project Work', 'calendari'); ?></span><input type="date" id="calendari-exam-modal-pw-deadline" name="data_termine_pw"></label>
                        <label for="calendari-exam-modal-type" data-exam-type-field><span>TIPO_PROVA (solo CDS)</span><select id="calendari-exam-modal-type" name="tipo_prova">
                            <option value=""><?php esc_html_e('Non specificato', 'calendari'); ?></option>
                            <option value="Orale"><?php esc_html_e('Orale', 'calendari'); ?></option>
                            <option value="Scritta"><?php esc_html_e('Scritta', 'calendari'); ?></option>
                        </select></label>
                        <label for="calendari-exam-modal-venue"><span>SEDE</span><input type="text" id="calendari-exam-modal-venue" name="sede"></label>
                    </div>
                    <footer class="calendari-degree-modal-actions">
                        <button type="button" class="button calendari-degree-cancel-button" data-exam-modal-cancel><i class="ti ti-x" aria-hidden="true"></i><span><?php esc_html_e('Annulla', 'calendari'); ?></span></button>
                        <button type="submit" class="button button-primary calendari-degree-save-button"><i class="ti ti-device-floppy" aria-hidden="true"></i><span><?php esc_html_e('Salva', 'calendari'); ?></span></button>
                    </footer>
                </form>
            </dialog>
        </div>
        <?php
    }

    private function render_exam_repository_results(array $records, int $total, int $page, array $filters): void {
        if (!$records) {
            echo '<p>' . esc_html__('Nessun record trovato.', 'calendari') . '</p>';
            return;
        }
        ?>
        <div class="calendari-admin-table-wrap">
            <table class="calendari-admin-table">
                <colgroup><col><col><col class="calendari-course-codes-column"><col><col><col><col><col><col><col class="calendari-actions-column"></colgroup>
                <thead><tr><th>AA_ID</th><th><?php esc_html_e('Tipo corso', 'calendari'); ?></th><th>CDS_IDS</th><th><?php esc_html_e('Gruppo', 'calendari'); ?></th><th><?php esc_html_e('Mese', 'calendari'); ?></th><th><?php esc_html_e('Data esame', 'calendari'); ?></th><th><?php esc_html_e('Termine consegna PW', 'calendari'); ?></th><th><?php esc_html_e('Tipo prova', 'calendari'); ?></th><th><?php esc_html_e('Sede', 'calendari'); ?></th><th class="calendari-actions-column"><?php esc_html_e('Azioni', 'calendari'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($records as $record) : ?>
                        <?php $is_hidden = !empty($record['is_hidden']); ?>
                        <tr class="<?php echo $is_hidden ? 'calendari-record-hidden' : ''; ?>">
                            <td><?php echo esc_html(Calendari_Format::academic_year_label((string) $record['aa_id'])); ?></td>
                            <td><?php echo esc_html(Calendari_Format::course_type_label((string) $record['tipo_corso'])); ?></td>
                            <td class="calendari-course-codes-cell">
                                <div class="calendari-course-codes-list">
                                    <?php $course_ids = preg_split('/[\s,;]+/u', (string) ($record['cds_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY); ?>
                                    <?php foreach ($course_ids ?: array() as $course_id) : ?>
                                        <span><?php echo esc_html($course_id); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><?php echo esc_html($record['gruppo'] ?? ''); ?></td>
                            <td><?php echo esc_html(Calendari_Format::month_name_with_year((string) $record['data_esami'])); ?></td>
                            <td><?php echo esc_html(Calendari_Format::format_date($record['data_esami'])); ?></td>
                            <td><?php echo !empty($record['data_termine_pw']) ? esc_html(Calendari_Format::format_date($record['data_termine_pw'])) : '—'; ?></td>
                            <td><?php echo esc_html($record['tipo_prova'] ?? ''); ?></td>
                            <td><?php echo $record['sede'] ? wp_kses($this->format_venue($record['sede']), array('br' => array())) : '—'; ?></td>
                            <td class="calendari-actions-column">
                                <div class="calendari-degree-actions">
                                    <a class="calendari-icon-button calendari-icon-button-visibility<?php echo $is_hidden ? ' is-hidden' : ''; ?>" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'calendari', 'tab' => 'database_esami', 'calendari_visibility' => $record['id'], 'calendari_hidden' => $is_hidden ? '0' : '1'), admin_url('admin.php')), 'calendari_visibility_' . $record['id'])); ?>" aria-label="<?php echo esc_attr($is_hidden ? __('Mostra negli elenchi pubblici', 'calendari') : __('Nascondi dagli elenchi pubblici', 'calendari')); ?>" title="<?php echo esc_attr($is_hidden ? __('Mostra', 'calendari') : __('Nascondi', 'calendari')); ?>">
                                        <i class="ti <?php echo $is_hidden ? 'ti-eye-off' : 'ti-eye'; ?>" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="calendari-icon-button calendari-icon-button-edit" data-exam-session-edit data-id="<?php echo esc_attr($record['id']); ?>" data-aa-id="<?php echo esc_attr($record['aa_id']); ?>" data-course="<?php echo esc_attr($record['tipo_corso']); ?>" data-course-ids="<?php echo esc_attr($record['cds_ids'] ?? ''); ?>" data-group="<?php echo esc_attr($record['gruppo'] ?? ''); ?>" data-booking-open="<?php echo esc_attr($record['data_apertura_prenotazioni']); ?>" data-booking-close="<?php echo esc_attr($record['data_chiusura_prenotazioni']); ?>" data-booking-dates-tbd="<?php echo !empty($record['booking_dates_tbd']) ? '1' : '0'; ?>" data-exam-date="<?php echo esc_attr($record['data_esami']); ?>" data-pw-deadline="<?php echo esc_attr($record['data_termine_pw'] ?? ''); ?>" data-exam-type="<?php echo esc_attr($record['tipo_prova'] ?? ''); ?>" data-venue="<?php echo esc_attr($record['sede']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Modifica appello %s', 'calendari'), $record['aa_id'])); ?>" title="<?php esc_attr_e('Modifica', 'calendari'); ?>">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </button>
                                    <a class="calendari-icon-button calendari-icon-button-delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'calendari', 'tab' => 'database_esami', 'calendari_delete' => $record['id']), admin_url('admin.php')), 'calendari_delete_' . $record['id'])); ?>" aria-label="<?php echo esc_attr(sprintf(__('Elimina appello %s', 'calendari'), $record['aa_id'])); ?>" title="<?php esc_attr_e('Elimina', 'calendari'); ?>" onclick="return confirm('<?php echo esc_js(__('Eliminare questo record?', 'calendari')); ?>');">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        echo wp_kses_post(paginate_links(array(
            'base' => add_query_arg(array('page' => 'calendari', 'tab' => 'database_esami', 'paged' => '%#%'), admin_url('admin.php')),
            'format' => '',
            'current' => $page,
            'total' => max(1, (int) ceil($total / 25)),
            'add_args' => array_filter($filters),
        )));
    }

    private function render_notice(array $notice): void {
        echo '<div class="calendari-builder-validation calendari-status-notice" role="status" aria-live="polite" data-calendari-auto-dismiss="5000"><p>';
        echo esc_html(implode(' ', array_slice($notice['messages'] ?? array(), 0, 1)));
        echo '</p>';
        if (count($notice['messages'] ?? array()) > 1) {
            echo '<ul>';
            foreach (array_slice($notice['messages'], 1, 20) as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    private function format_venue(string $venue): string {
        $venues = preg_split('/(?:\r\n|\r|\n)|\s*[;,]\s*/u', trim($venue), -1, PREG_SPLIT_NO_EMPTY);
        $venues = array_unique(array_map('trim', $venues ?: array()));

        return implode(', ', array_map('esc_html', $venues));
    }

    private function redirect_with_notice(array $notice, array $args = array()): void {
        $section = sanitize_key($args['tab'] ?? 'shortcode');
        set_transient($this->notice_key($section), $notice, MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg(array_merge(array('page' => 'calendari'), $args), admin_url('admin.php')));
        exit;
    }

    private function pull_notice(string $section): ?array {
        $key = $this->notice_key($section);
        $notice = get_transient($key);
        if (false !== $notice) {
            delete_transient($key);
        }

        return is_array($notice) ? $notice : null;
    }

    private function notice_key(string $section): string {
        return 'calendari_notice_' . get_current_user_id() . '_' . sanitize_key($section);
    }
}

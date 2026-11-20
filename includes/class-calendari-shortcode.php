<?php

defined('ABSPATH') || exit;

final class Calendari_Shortcode {
    private Calendari_DB $db;

    private Calendari_Degree_DB $degree_db;

    public function __construct(Calendari_DB $db, Calendari_Degree_DB $degree_db) {
        $this->db = $db;
        $this->degree_db = $degree_db;
    }

    public function render(array $attributes = array()): string {
        wp_enqueue_style('calendari-public', CALENDARI_URL . 'assets/css/calendari.css', array(), CALENDARI_VERSION);
        wp_enqueue_script('calendari-public', CALENDARI_URL . 'assets/js/calendari.js', array(), CALENDARI_VERSION, true);
        wp_enqueue_style('calendari-nunito', 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap', array(), null);
        $this->enqueue_graphics_styles();

        $attributes = shortcode_atts(
            array(
                'tipologia' => 'esami',
                'aa_id' => '',
                'tipo_corso' => '',
                'gruppo' => '',
                'mostra_tipo_prova' => 'nascondi',
                'filtro_tipo_prova' => 'tutti',
                'view' => 'table',
            ),
            $attributes,
            'calendari'
        );

        $tipologia = strtolower(sanitize_key($attributes['tipologia']));
        $aa_id = Calendari_Format::normalize_academic_year_id(sanitize_text_field($attributes['aa_id']));
        $gruppo = sanitize_text_field($attributes['gruppo']);
        $view = strtolower(sanitize_text_field($attributes['view']));

        if (!in_array($tipologia, array('esami', 'laurea'), true)) {
            return '<p class="calendari-message">' . esc_html__('La tipologia di calendario non è valida.', 'calendari') . '</p>';
        }

        if ('laurea' === $tipologia) {
            $view = in_array($view, array('table', 'grid', 'both'), true) ? $view : 'table';
            return $this->render_degree_calendar($aa_id, $view);
        }

        $tipo_corso = strtolower(sanitize_text_field($attributes['tipo_corso']));
        $mostra_tipo_prova = strtolower(trim(sanitize_text_field($attributes['mostra_tipo_prova'])));
        $is_cds_course = 'cds' === $tipo_corso;
        $show_tipo_prova = $is_cds_course && 'mostra' === $mostra_tipo_prova;
        $filtro_tipo_prova = strtolower(trim(sanitize_text_field($attributes['filtro_tipo_prova'])));
        if (!$is_cds_course) {
            $filtro_tipo_prova = 'tutti';
        }
        $allowed_views = $is_cds_course ? array('table', 'grid', 'both', 'tabs') : array('table', 'grid', 'both');
        $view = in_array($view, $allowed_views, true) ? $view : 'table';
        $tipo_prova_filter = match ($filtro_tipo_prova) {
            'orali' => 'Orale',
            'scritte' => 'Scritta',
            default => '',
        };
        if ('' === $aa_id || '' === $tipo_corso) {
            return '<p class="calendari-message">' . esc_html__('Inserisci aa_id e tipo_corso nello shortcode.', 'calendari') . '</p>';
        }

        $records = $this->db->find_calendar($aa_id, $tipo_corso, $tipo_prova_filter, $gruppo);
        if (!$records) {
            return '<p class="calendari-message">' . esc_html__('Nessun record di calendario corrisponde ai filtri selezionati.', 'calendari') . '</p>';
        }

        $calendar_groups = array();
        foreach ($records as $record) {
            $group_name = trim((string) ($record['gruppo'] ?? ''));
            $group_key = '' === $group_name ? '__ungrouped' : $group_name;
            $calendar_groups[$group_key][] = $record;
        }

        ob_start();
        ?>
        <?php $title_id = 'calendari-title-' . md5($aa_id . $tipo_corso . $gruppo . $mostra_tipo_prova . $filtro_tipo_prova); ?>
        <section class="calendari-calendar" aria-labelledby="<?php echo esc_attr($title_id); ?>">
            <div class="calendari-heading">
                <h2 id="<?php echo esc_attr($title_id); ?>">
                    <?php echo esc_html(sprintf(__('Calendario esami %1$s A.A. %2$s', 'calendari'), $this->format_course_title($tipo_corso), $this->format_academic_year($aa_id))); ?>
                </h2>
            </div>
            <?php foreach ($calendar_groups as $group_key => $group_records) : ?>
                <?php $this->render_exam_group($group_records, '__ungrouped' === $group_key ? '' : $group_key, $show_tipo_prova, $view, $tipo_corso); ?>
            <?php endforeach; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function render_degree_calendar(string $aa_id, string $view): string {
        if ('' === $aa_id) {
            return '<p class="calendari-message">' . esc_html__('Inserisci aa_id nello shortcode per il calendario delle sessioni di laurea.', 'calendari') . '</p>';
        }

        $records = $this->degree_db->find_calendar($aa_id);
        if (!$records) {
            return '<p class="calendari-message">' . esc_html__('Nessuna sessione di laurea corrisponde all’anno accademico selezionato.', 'calendari') . '</p>';
        }

        $title_id = wp_unique_id('calendari-degree-title-');
        ob_start();
        ?>
        <section class="calendari-calendar" aria-labelledby="<?php echo esc_attr($title_id); ?>">
            <div class="calendari-heading">
                <h2 id="<?php echo esc_attr($title_id); ?>">
                    <?php echo esc_html(sprintf(__('Calendario sessioni di laurea A.A. %s', 'calendari'), $this->format_academic_year($aa_id))); ?>
                </h2>
            </div>
            <?php if (in_array($view, array('table', 'both'), true)) : ?>
                <?php $this->render_degree_table($records); ?>
            <?php endif; ?>
            <?php if (in_array($view, array('grid', 'both'), true)) : ?>
                <?php $this->render_degree_grid($records); ?>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function render_exam_group(array $records, string $group_name, bool $show_tipo_prova, string $view, string $tipo_corso): void {
        $group_dom_id = wp_unique_id('calendari-group-');
        $show_codes = $this->should_show_cds_pills($tipo_corso);
        $course_codes = $show_codes ? $this->unique_cds_ids($records) : array();
        $has_pw_deadline = (bool) array_filter($records, static function (array $record): bool {
            return !empty($record['data_termine_pw']);
        });
        $group_note = '';
        foreach ($records as $record) {
            if (!empty($record['nota_pubblica_snapshot'])) {
                $group_note = (string) $record['nota_pubblica_snapshot'];
                break;
            }
        }
        ?>
        <div class="calendari-appeal calendari-course-group" id="<?php echo esc_attr($group_dom_id); ?>">
            <?php if ('' !== $group_name || $course_codes) : ?>
                <div class="calendari-course-group-heading">
                <?php if ('' !== $group_name) : ?>
                    <h3><?php echo esc_html($group_name); ?></h3>
                <?php endif; ?>
                <?php if ($course_codes) : ?>
                    <div class="calendari-cds-list" aria-label="<?php esc_attr_e('Codici dei corsi inclusi', 'calendari'); ?>">
                        <?php foreach ($course_codes as $course_code) : ?>
                            <span class="calendari-cds-pill"><?php echo esc_html($course_code); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ('tabs' === $view) : ?>
                <?php $tabbed_view_id = wp_unique_id('calendari-exam-tabs-'); ?>
                <?php $written_records = array_values(array_filter($records, static function (array $record): bool { return 'scritta' === strtolower(trim((string) ($record['tipo_prova'] ?? ''))); })); ?>
                <?php $oral_records = array_values(array_filter($records, static function (array $record): bool { return 'orale' === strtolower(trim((string) ($record['tipo_prova'] ?? ''))); })); ?>
                <div class="calendari-tabbed-view" data-calendari-tabbed-view>
                    <div class="calendari-view-tabs" role="tablist" aria-label="<?php esc_attr_e('Tipo di prova', 'calendari'); ?>">
                        <button type="button" class="calendari-view-tab" id="<?php echo esc_attr($tabbed_view_id . '-written-tab'); ?>" role="tab" aria-selected="true" aria-controls="<?php echo esc_attr($tabbed_view_id . '-written-panel'); ?>" tabindex="0"><?php esc_html_e('Prova scritta', 'calendari'); ?></button>
                        <button type="button" class="calendari-view-tab" id="<?php echo esc_attr($tabbed_view_id . '-oral-tab'); ?>" role="tab" aria-selected="false" aria-controls="<?php echo esc_attr($tabbed_view_id . '-oral-panel'); ?>" tabindex="-1"><?php esc_html_e('Prova orale', 'calendari'); ?></button>
                    </div>
                    <div class="calendari-view-tabpanel" id="<?php echo esc_attr($tabbed_view_id . '-written-panel'); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr($tabbed_view_id . '-written-tab'); ?>" tabindex="0">
                        <?php $this->render_table($written_records, $show_tipo_prova, $has_pw_deadline); ?>
                    </div>
                    <div class="calendari-view-tabpanel" id="<?php echo esc_attr($tabbed_view_id . '-oral-panel'); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr($tabbed_view_id . '-oral-tab'); ?>" tabindex="0" hidden>
                        <?php $this->render_table($oral_records, $show_tipo_prova, $has_pw_deadline); ?>
                    </div>
                </div>
            <?php elseif (in_array($view, array('table', 'both'), true)) : ?>
                <?php $this->render_table($records, $show_tipo_prova, $has_pw_deadline); ?>
            <?php endif; ?>
            <?php if (in_array($view, array('grid', 'both'), true)) : ?>
                <?php $this->render_grid($records, $show_tipo_prova); ?>
            <?php endif; ?>
            <?php if ('' !== $group_note) : ?>
                <aside class="calendari-group-note"><p><?php echo wp_kses_post(wpautop(esc_html($group_note))); ?></p></aside>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_degree_table(array $records): void {
        ?>
        <div class="calendari-table-wrap">
            <table class="calendari-table">
                <caption><?php esc_html_e('Sessioni di laurea', 'calendari'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Mese', 'calendari'); ?></th>
                        <th scope="col"><?php esc_html_e('Data', 'calendari'); ?></th>
                        <th scope="col"><?php esc_html_e('Sede', 'calendari'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $index => $record) : ?>
                        <tr class="<?php echo 0 === $index % 2 ? 'calendari-row-alt' : ''; ?>">
                            <td class="calendari-month-cell"><?php echo esc_html(Calendari_Format::month_name_with_year((string) $record['data'])); ?></td>
                            <td class="calendari-exam-date"><strong><?php echo esc_html(Calendari_Format::format_date($record['data'])); ?></strong></td>
                            <td class="calendari-venue"><?php echo $record['sede'] ? wp_kses($this->format_venue($record['sede']), array('br' => array())) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_degree_grid(array $records): void {
        $groups = array();
        foreach ($records as $record) {
            $month = substr($record['data'], 0, 7);
            $groups[$month][] = $record;
        }
        ?>
        <div class="calendari-grid" aria-label="<?php esc_attr_e('Calendario sessioni di laurea per data', 'calendari'); ?>">
            <?php foreach ($groups as $month => $month_records) : ?>
                <div class="calendari-month">
                    <h3><?php echo esc_html(Calendari_Format::month_heading($month)); ?></h3>
                    <div class="calendari-events">
                        <?php foreach ($month_records as $record) : ?>
                            <article class="calendari-event">
                                <time datetime="<?php echo esc_attr($record['data']); ?>" class="calendari-event-date">
                                    <?php echo esc_html(Calendari_Format::format_date($record['data'])); ?>
                                </time>
                                <?php if ('' !== $record['sede']) : ?>
                                    <span class="calendari-event-venue"><?php echo wp_kses($this->format_venue($record['sede']), array('br' => array())); ?></span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_grid(array $records, bool $show_tipo_prova): void {
        $groups = array();
        foreach ($records as $record) {
            $month = substr($record['data_esami'], 0, 7);
            $groups[$month][] = $record;
        }
        ?>
        <div class="calendari-grid" aria-label="<?php esc_attr_e('Calendario per data d’esame', 'calendari'); ?>">
            <?php foreach ($groups as $month => $month_records) : ?>
                <div class="calendari-month">
                    <h3><?php echo esc_html(Calendari_Format::month_heading($month)); ?></h3>
                    <div class="calendari-events">
                        <?php foreach ($month_records as $record) : ?>
                            <article class="calendari-event">
                                <time datetime="<?php echo esc_attr($record['data_esami']); ?>" class="calendari-event-date">
                                    <?php echo esc_html(Calendari_Format::format_date($record['data_esami'])); ?>
                                </time>
                                <span><?php echo esc_html__('Prenotazioni', 'calendari'); ?></span>
                                <?php if (!empty($record['booking_dates_tbd'])) : ?>
                                    <small><?php esc_html_e('DA DEFINIRE', 'calendari'); ?></small>
                                <?php else : ?>
                                    <small><?php echo esc_html(Calendari_Format::format_date($record['data_apertura_prenotazioni'])); ?> – <?php echo esc_html(Calendari_Format::format_date($record['data_chiusura_prenotazioni'])); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($record['data_termine_pw'])) : ?>
                                    <span class="calendari-event-deadline"><?php esc_html_e('Termine Project Work', 'calendari'); ?>: <?php echo esc_html(Calendari_Format::format_date($record['data_termine_pw'])); ?></span>
                                <?php endif; ?>
                                <?php if ($show_tipo_prova && '' !== ($record['tipo_prova'] ?? '')) : ?>
                                    <span class="calendari-event-type"><?php echo esc_html($record['tipo_prova']); ?></span>
                                <?php endif; ?>
                                <?php if ($record['sede']) : ?>
                                    <span class="calendari-event-venue"><?php echo wp_kses($this->format_venue($record['sede']), array('br' => array())); ?></span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function enqueue_graphics_styles(): void {
        $settings = wp_parse_args(get_option('calendari_graphics', array()), array(
            'border_radius' => 10,
            'font_family' => 'Nunito',
            'accent_color' => '#037aa4',
        ));
        $font_family = in_array($settings['font_family'], array('Nunito', 'system-ui', 'Arial', 'Georgia'), true) ? $settings['font_family'] : 'Nunito';
        $colors = Calendari_Colors::derive($settings['accent_color']);
        $css = sprintf(
            '.calendari-calendar { --calendari-table-radius: %dpx; --calendari-font-family: %s, system-ui, sans-serif; --calendari-header: %s; --calendari-row-soft: %s; --calendari-surface: %s; --calendari-card: %s; --calendari-ink: %s; --calendari-muted: %s; --calendari-accent: %s; --calendari-accent-deep: %s; --calendari-accent-soft: %s; --calendari-line: %s; --calendari-border: %s; --calendari-contrast: %s; }',
            max(0, min(40, absint($settings['border_radius']))),
            $font_family,
            $colors['header'],
            $colors['row'],
            $colors['surface'],
            $colors['card'],
            $colors['ink'],
            $colors['muted'],
            $colors['accent'],
            $colors['deep'],
            $colors['soft'],
            $colors['line'],
            $colors['border'],
            $colors['contrast']
        );
        wp_add_inline_style('calendari-public', $css);
    }

    private function render_table(array $records, bool $show_tipo_prova, bool $show_pw_deadline = false): void {
        $table_groups = $this->build_table_groups($records);
        ?>
        <div class="calendari-table-wrap">
            <table class="calendari-table">
                <caption><?php esc_html_e('Record calendario', 'calendari'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Mese', 'calendari'); ?></th>
                        <?php if ($show_tipo_prova) : ?>
                            <th scope="col"><?php esc_html_e('Tipo prova', 'calendari'); ?></th>
                        <?php endif; ?>
                        <th scope="col"><?php esc_html_e('Prenotazioni', 'calendari'); ?></th>
                        <?php if ($show_pw_deadline) : ?>
                            <th scope="col"><?php esc_html_e('Termine consegna PW', 'calendari'); ?></th>
                        <?php endif; ?>
                        <th scope="col"><?php esc_html_e('Data esame', 'calendari'); ?></th>
                        <th scope="col"><?php esc_html_e('Sede', 'calendari'); ?></th>
                    </tr>
                </thead>
                <?php foreach ($table_groups as $group_index => $group) : ?>
                    <?php $booking_groups = $this->build_booking_groups($group['records']); ?>
                    <tbody class="calendari-table-group">
                        <?php $group_record_index = 0; ?>
                        <?php foreach ($booking_groups as $booking_group) : ?>
                            <?php foreach ($booking_group as $booking_record_index => $record) : ?>
                            <tr class="<?php echo 0 === $group_index % 2 ? 'calendari-row-alt' : ''; ?>">
                                <?php if (0 === $group_record_index) : ?>
                                    <td class="calendari-month-cell" role="rowheader" rowspan="<?php echo esc_attr(count($group['records'])); ?>"><?php echo esc_html(Calendari_Format::month_name_with_year((string) $record['data_esami'])); ?></td>
                                    <?php if ($show_tipo_prova) : ?>
                                        <td class="calendari-type-cell" role="rowheader" rowspan="<?php echo esc_attr(count($group['records'])); ?>"><?php echo esc_html($record['tipo_prova'] ?? ''); ?></td>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (0 === $booking_record_index) : ?>
                                    <td class="calendari-booking-cell" rowspan="<?php echo esc_attr(count($booking_group)); ?>"><?php if (!empty($record['booking_dates_tbd'])) : ?><?php esc_html_e('DA DEFINIRE', 'calendari'); ?><?php else : ?><?php echo esc_html__('dal', 'calendari'); ?> <?php echo esc_html(Calendari_Format::format_date($record['data_apertura_prenotazioni'])); ?><br><?php echo esc_html__('al', 'calendari'); ?> <?php echo esc_html(Calendari_Format::format_date($record['data_chiusura_prenotazioni'])); ?><?php endif; ?></td>
                                <?php endif; ?>
                                <?php if ($show_pw_deadline) : ?>
                                    <td class="calendari-pw-deadline"><?php echo !empty($record['data_termine_pw']) ? esc_html(Calendari_Format::format_date($record['data_termine_pw'])) : '—'; ?></td>
                                <?php endif; ?>
                                <td class="calendari-exam-date"><strong><?php echo esc_html(Calendari_Format::format_date($record['data_esami'])); ?></strong></td>
                                <td class="calendari-venue"><?php echo $record['sede'] ? wp_kses($this->format_venue($record['sede']), array('br' => array())) : '—'; ?></td>
                            </tr>
                            <?php $group_record_index++; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    private function build_table_groups(array $records): array {
        $groups = array();
        foreach ($records as $record) {
            $month_key = substr($record['data_esami'], 0, 7);
            $group_key = $month_key . '|' . ($record['tipo_prova'] ?? '');
            $last_group = count($groups) - 1;

            if ($last_group < 0 || $groups[$last_group]['key'] !== $group_key) {
                $groups[] = array(
                    'key' => $group_key,
                    'month_key' => $month_key,
                    'records' => array($record),
                );
            } else {
                $groups[$last_group]['records'][] = $record;
            }
        }

        return $groups;
    }

    private function build_booking_groups(array $records): array {
        $groups = array();
        foreach ($records as $record) {
            $booking_key = !empty($record['booking_dates_tbd'])
                ? '__booking_dates_tbd'
                : $record['data_apertura_prenotazioni'] . '|' . $record['data_chiusura_prenotazioni'];
            $last_group = count($groups) - 1;

            if ($last_group < 0 || $groups[$last_group]['key'] !== $booking_key) {
                $groups[] = array(
                    'key' => $booking_key,
                    'records' => array($record),
                );
            } else {
                $groups[$last_group]['records'][] = $record;
            }
        }

        return array_map(static function (array $group): array {
            return $group['records'];
        }, $groups);
    }

    private function format_venue(string $venue): string {
        $venues = preg_split('/(?:\r\n|\r|\n)|\s*[;,]\s*/u', trim($venue), -1, PREG_SPLIT_NO_EMPTY);
        $venues = array_unique(array_map('trim', $venues ?: array()));

        return implode('<br>', array_map('esc_html', $venues));
    }

    private function format_academic_year(string $aa_id): string {
        return Calendari_Format::academic_year_label($aa_id);
    }

    private function format_course_title(string $tipo_corso): string {
        return match (strtoupper(trim($tipo_corso))) {
            'CP' => __('Corsi di perfezionamento', 'calendari'),
            'CO' => __('Corsi online', 'calendari'),
            'MASTER' => __('Master', 'calendari'),
            'CDS' => __('Corsi di studio', 'calendari'),
            default => strtoupper(trim($tipo_corso)),
        };
    }

    private function unique_cds_ids(array $records): array {
        $latest_date = '';
        foreach ($records as $record) {
            $latest_date = max($latest_date, (string) ($record['data_esami'] ?? ''));
        }

        $ids = array();
        foreach ($records as $record) {
            if ((string) ($record['data_esami'] ?? '') !== $latest_date) {
                continue;
            }
            $record_codes = preg_split('/[\s,;]+/u', (string) ($record['course_codes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($record_codes as $raw_code) {
                $id = trim((string) $raw_code);
                if ('' !== $id) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    private function should_show_cds_pills(string $tipo_corso): bool {
        return in_array(strtoupper(trim($tipo_corso)), array('MASTER', 'CO', 'CP'), true);
    }
}

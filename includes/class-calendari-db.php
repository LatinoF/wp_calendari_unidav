<?php

defined('ABSPATH') || exit;

final class Calendari_DB {
    private wpdb $wpdb;

    private string $table;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'calendari_records';
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'calendari_records';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            aa_id varchar(100) NOT NULL,
            tipo_corso varchar(100) NOT NULL,
            cds_ids text NOT NULL,
            gruppo_id bigint(20) unsigned NULL,
            gruppo varchar(191) NULL,
            mese tinyint(3) unsigned NOT NULL,
            data_apertura_prenotazioni date NOT NULL,
            data_chiusura_prenotazioni date NOT NULL,
            data_esami date NOT NULL,
            data_termine_pw date NULL,
            tipo_prova varchar(20) NOT NULL DEFAULT '',
            sede varchar(191) NOT NULL DEFAULT '',
            nota_pubblica_snapshot text NULL,
            is_hidden tinyint(1) unsigned NOT NULL DEFAULT 0,
            booking_dates_tbd tinyint(1) unsigned NOT NULL DEFAULT 0,
            event_key char(64) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_identity (event_key),
            KEY calendar_lookup (aa_id, tipo_corso, gruppo_id, data_esami, tipo_prova),
            KEY exam_date_lookup (data_esami)
        ) {$charset_collate};";

        dbDelta($sql);
        $record_courses = $wpdb->prefix . 'calendari_record_courses';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($record_courses))) === $record_courses) {
            $wpdb->query(
                "UPDATE {$table} r
                 INNER JOIN (
                     SELECT record_id, GROUP_CONCAT(DISTINCT cds_id ORDER BY cds_id SEPARATOR ',') AS cds_ids
                     FROM {$record_courses}
                     GROUP BY record_id
                 ) c ON c.record_id = r.id
                 SET r.cds_ids = c.cds_ids
                 WHERE r.cds_ids = ''"
            );
        }
        $record_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (in_array('cds_id', $record_columns, true)) {
            $wpdb->query("UPDATE {$table} SET cds_ids = cds_id WHERE cds_ids = '' AND cds_id IS NOT NULL");
        }
        $migration_succeeded = true;
        $legacy_tables = array(
            $wpdb->prefix . 'calendari_course_groups',
            $wpdb->prefix . 'calendari_course_group_members',
            $wpdb->prefix . 'calendari_group_changes',
        );
        foreach ($legacy_tables as $legacy_table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_table))) !== $legacy_table) {
                continue;
            }
            $legacy_columns = $wpdb->get_col("SHOW COLUMNS FROM {$legacy_table}", 0);
            if (!in_array('aa_id', $legacy_columns, true)) {
                continue;
            }
            $legacy_rows = $wpdb->get_results("SELECT id, aa_id FROM {$legacy_table}", ARRAY_A);
            foreach ($legacy_rows ?: array() as $legacy_row) {
                $legacy_year_id = Calendari_Format::normalize_academic_year_id((string) $legacy_row['aa_id']);
                if ($legacy_year_id !== (string) $legacy_row['aa_id']
                    && false === $wpdb->update($legacy_table, array('aa_id' => $legacy_year_id), array('id' => (int) $legacy_row['id']), array('%s'), array('%d'))
                ) {
                    $migration_succeeded = false;
                }
            }
        }
        $records = $wpdb->get_results(
            "SELECT id, aa_id, tipo_corso, gruppo, data_esami, tipo_prova, sede FROM {$table}",
            ARRAY_A
        );
        foreach ($records ?: array() as $record) {
            $year_id = Calendari_Format::normalize_academic_year_id((string) $record['aa_id']);
            if ($year_id !== (string) $record['aa_id']) {
                $updated_year = $wpdb->update(
                    $table,
                    array('aa_id' => $year_id),
                    array('id' => (int) $record['id']),
                    array('%s'),
                    array('%d')
                );
                if (false === $updated_year) {
                    $migration_succeeded = false;
                    continue;
                }
            }
            $identity = array(
                $year_id,
                (string) $record['tipo_corso'],
                (string) ($record['gruppo'] ?? ''),
                (string) $record['data_esami'],
                (string) $record['tipo_prova'],
                (string) $record['sede'],
            );
            if (false === $wpdb->update(
                $table,
                array('event_key' => hash('sha256', wp_json_encode($identity))),
                array('id' => (int) $record['id']),
                array('%s'),
                array('%d')
            )) {
                $migration_succeeded = false;
            }
        }
        if ($migration_succeeded) {
            update_option('calendari_db_version', CALENDARI_VERSION);
        }
    }

    public static function maybe_upgrade(): void {
        if (CALENDARI_VERSION !== get_option('calendari_db_version')) {
            self::install();
        }
    }

    public function upsert(array $record): int|false {
        $exam_date = (string) ($record['data_esami'] ?? '');
        $month = Calendari_Format::month_from_date($exam_date);
        if (null === $month) {
            return false;
        }

        $aa_id = Calendari_Format::normalize_academic_year_id((string) ($record['aa_id'] ?? ''));
        $tipo_corso = Calendari_Format::normalize_course_type((string) ($record['tipo_corso'] ?? ''));
        $gruppo = trim((string) ($record['gruppo'] ?? ''));
        $cds_ids = self::normalize_course_ids((string) ($record['cds_ids'] ?? ''));
        $now = current_time('mysql', true);
        $identity = array(
            (string) $aa_id,
            $tipo_corso,
            $gruppo,
            $exam_date,
            (string) ($record['tipo_prova'] ?? ''),
            (string) ($record['sede'] ?? ''),
        );
        $event_key = hash('sha256', wp_json_encode($identity));
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table}
                (aa_id, tipo_corso, cds_ids, gruppo_id, gruppo, mese, data_apertura_prenotazioni, data_chiusura_prenotazioni, data_esami, data_termine_pw, tipo_prova, sede, event_key, created_at, updated_at)
             VALUES (%s, %s, %s, NULL, NULLIF(%s, ''), %d, %s, %s, %s, NULLIF(%s, ''), %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                cds_ids = IF(data_esami < CURRENT_DATE, cds_ids, VALUES(cds_ids)),
                gruppo_id = NULL,
                gruppo = IF(data_esami < CURRENT_DATE, gruppo, VALUES(gruppo)),
                mese = VALUES(mese),
                data_apertura_prenotazioni = VALUES(data_apertura_prenotazioni),
                data_chiusura_prenotazioni = VALUES(data_chiusura_prenotazioni),
                data_esami = VALUES(data_esami),
                data_termine_pw = VALUES(data_termine_pw),
                tipo_prova = VALUES(tipo_prova),
                sede = VALUES(sede),
                updated_at = VALUES(updated_at)",
            $aa_id,
            $tipo_corso,
            $cds_ids,
            $gruppo ?: null,
            $month,
            $record['data_apertura_prenotazioni'],
            $record['data_chiusura_prenotazioni'],
            $exam_date,
            (string) ($record['data_termine_pw'] ?? ''),
            (string) ($record['tipo_prova'] ?? ''),
            (string) ($record['sede'] ?? ''),
            $event_key,
            $now,
            $now
        );

        $result = $this->wpdb->query($sql);
        if (false === $result) {
            return false;
        }
        return $result;
    }

    public function update(int $id, array $record): int|false {
        $month = Calendari_Format::month_from_date((string) ($record['data_esami'] ?? ''));
        $existing = $this->find($id);
        if (null === $month || !$existing) {
            return false;
        }

        $aa_id = Calendari_Format::normalize_academic_year_id((string) ($record['aa_id'] ?? ''));
        $tipo_corso = Calendari_Format::normalize_course_type((string) ($record['tipo_corso'] ?? ''));
        $gruppo = trim((string) ($record['gruppo'] ?? ''));
        $cds_ids = self::normalize_course_ids((string) ($record['cds_ids'] ?? ''));
        $identity = array(
            (string) $aa_id,
            $tipo_corso,
            $gruppo,
            (string) $record['data_esami'],
            (string) ($record['tipo_prova'] ?? ''),
            (string) ($record['sede'] ?? ''),
        );

        $this->wpdb->query('START TRANSACTION');
        $updated = $this->wpdb->update(
            $this->table,
            array(
                'aa_id' => $aa_id,
                'tipo_corso' => $tipo_corso,
                'cds_ids' => $cds_ids,
                'gruppo_id' => null,
                'gruppo' => $gruppo ?: null,
                'mese' => $month,
                'data_apertura_prenotazioni' => $record['data_apertura_prenotazioni'],
                'data_chiusura_prenotazioni' => $record['data_chiusura_prenotazioni'],
                'data_esami' => $record['data_esami'],
                'data_termine_pw' => $record['data_termine_pw'] ?: null,
                'tipo_prova' => (string) ($record['tipo_prova'] ?? ''),
                'sede' => (string) ($record['sede'] ?? ''),
                'event_key' => hash('sha256', wp_json_encode($identity)),
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        if (false === $updated) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }
        $this->wpdb->query('COMMIT');
        return $updated;
    }

    public function set_hidden(int $id, bool $is_hidden): int|false {
        return $this->wpdb->update($this->table, array('is_hidden' => $is_hidden ? 1 : 0), array('id' => $id), array('%d'), array('%d'));
    }

    public function delete(int $id): int|false {
        return $this->wpdb->delete($this->table, array('id' => $id), array('%d'));
    }

    public function find(int $id): ?array {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $record = $this->wpdb->get_row($sql, ARRAY_A);
        return $record ?: null;
    }

    public function distinct_aa_ids(): array {
        return array_values(array_filter(array_map('strval', $this->wpdb->get_col("SELECT DISTINCT aa_id FROM {$this->table} WHERE aa_id <> '' ORDER BY aa_id DESC"))));
    }

    public function distinct_course_types(string $aa_id = ''): array {
        $aa_id = Calendari_Format::normalize_academic_year_id($aa_id);
        $sql = "SELECT DISTINCT tipo_corso FROM {$this->table} WHERE tipo_corso <> ''";
        if ('' !== $aa_id) {
            $sql = $this->wpdb->prepare($sql . ' AND aa_id = %s', $aa_id);
        }
        $sql .= ' ORDER BY tipo_corso ASC';
        return array_values(array_filter(array_map('strval', $this->wpdb->get_col($sql) ?: array())));
    }

    public function distinct_group_names(string $aa_id = '', string $tipo_corso = ''): array {
        $aa_id = Calendari_Format::normalize_academic_year_id($aa_id);
        $where = array("gruppo IS NOT NULL", "gruppo <> ''");
        $params = array();
        if ('' !== $aa_id) {
            $where[] = 'aa_id = %s';
            $params[] = $aa_id;
        }
        if ('' !== $tipo_corso) {
            $this->append_course_type_condition($tipo_corso, $where, $params);
        }
        $sql = "SELECT DISTINCT gruppo FROM {$this->table} WHERE " . implode(' AND ', $where) . ' ORDER BY gruppo ASC';
        if ($params) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }
        return array_values(array_filter(array_map('strval', $this->wpdb->get_col($sql) ?: array())));
    }

    public function distinct_exam_types(): array {
        return array_values(array_filter(array_map('strval', $this->wpdb->get_col("SELECT DISTINCT tipo_prova FROM {$this->table} ORDER BY tipo_prova ASC") ?: array())));
    }

    public function distinct_months(): array {
        return array_values(array_map('intval', $this->wpdb->get_col("SELECT DISTINCT MONTH(data_esami) FROM {$this->table} WHERE data_esami <> '0000-00-00' ORDER BY MONTH(data_esami) ASC") ?: array()));
    }

    public function find_calendar(string $aa_id, string $tipo_corso, string $tipo_prova = '', string $gruppo = ''): array {
        $aa_id = Calendari_Format::normalize_academic_year_id($aa_id);
        $where = array('e.aa_id = %s', 'e.is_hidden = 0');
        $params = array($aa_id);
        $this->append_course_type_condition($tipo_corso, $where, $params, 'e.tipo_corso');
        if ('' !== $tipo_prova) {
            $where[] = 'e.tipo_prova = %s';
            $params[] = $tipo_prova;
        }
        if ('__ungrouped' !== $gruppo && '' !== $gruppo) {
            $where[] = 'e.gruppo = %s';
            $params[] = $gruppo;
        }
        $sql = $this->wpdb->prepare(
            "SELECT e.*, e.cds_ids AS course_codes
             FROM {$this->table} e
             WHERE " . implode(' AND ', $where) . "
             ORDER BY COALESCE(e.gruppo, '') ASC, e.data_esami ASC, e.tipo_prova ASC, e.id ASC",
            ...$params
        );
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function count(array $filters = array()): int {
        [$where, $params] = $this->build_where($filters);
        return (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $params));
    }

    public function list(array $filters = array(), int $limit = 25, int $offset = 0): array {
        [$where, $params] = $this->build_where($filters);
        $params[] = $limit;
        $params[] = $offset;
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY data_esami ASC, aa_id ASC, tipo_corso ASC, COALESCE(gruppo, '') ASC, id ASC LIMIT %d OFFSET %d";
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
    }

    private function build_where(array $filters): array {
        $where = array('1=1');
        $params = array();
        foreach (array('aa_id') as $field) {
            if (isset($filters[$field]) && '' !== (string) $filters[$field]) {
                $where[] = "{$field} = %s";
                $params[] = Calendari_Format::normalize_academic_year_id((string) $filters[$field]);
            }
        }
        if (isset($filters['tipo_corso']) && '' !== (string) $filters['tipo_corso']) {
            $this->append_course_type_condition((string) $filters['tipo_corso'], $where, $params);
        }
        if (isset($filters['gruppo']) && '__ungrouped' !== $filters['gruppo'] && '' !== (string) $filters['gruppo']) {
            $where[] = 'gruppo = %s';
            $params[] = (string) $filters['gruppo'];
        }
        if (isset($filters['tipo_prova']) && '__empty' === $filters['tipo_prova']) {
            $where[] = 'tipo_prova = %s';
            $params[] = '';
        } elseif (isset($filters['tipo_prova']) && '' !== (string) $filters['tipo_prova']) {
            $where[] = 'tipo_prova = %s';
            $params[] = (string) $filters['tipo_prova'];
        }
        if (isset($filters['mese']) && (int) $filters['mese'] > 0) {
            $where[] = 'MONTH(data_esami) = %d';
            $params[] = (int) $filters['mese'];
        }
        return array(implode(' AND ', $where), $params);
    }

    private function append_course_type_condition(string $value, array &$where, array &$params, string $column = 'tipo_corso'): void {
        $course_type = Calendari_Format::normalize_course_type($value);
        $storage_values = array($course_type);
        if ('M' === $course_type) {
            $storage_values[] = 'MASTER';
        }
        $labels = Calendari_Format::course_type_labels();
        if (isset($labels[$course_type])) {
            $storage_values[] = $labels[$course_type];
        }
        $storage_values = array_values(array_unique(array_map('strtoupper', $storage_values)));
        $placeholders = implode(', ', array_fill(0, count($storage_values), '%s'));
        $where[] = 'UPPER(' . $column . ') IN (' . $placeholders . ')';
        foreach ($storage_values as $storage_value) {
            $params[] = $storage_value;
        }
    }

    public static function normalize_course_ids(string $value): string {
        $ids = preg_split('/[\s,;]+/u', strtoupper(trim($value)), -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_values(array_unique(array_filter(array_map('sanitize_text_field', $ids ?: array()))));
        return implode(',', $ids);
    }
}

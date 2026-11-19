<?php

defined('ABSPATH') || exit;

final class Calendari_Degree_DB {
    private wpdb $wpdb;

    private string $table;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'calendari_degree_sessions';
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'calendari_degree_sessions';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            aa_id varchar(100) NOT NULL,
            mese tinyint(3) unsigned NOT NULL,
            data date NOT NULL,
            sede varchar(191) NOT NULL DEFAULT '',
            is_hidden tinyint(1) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY academic_year_month (aa_id, mese),
            KEY session_date (data)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option('calendari_degree_db_version', CALENDARI_VERSION);
    }

    public static function maybe_upgrade(): void {
        if (CALENDARI_VERSION !== get_option('calendari_degree_db_version')) {
            self::install();
        }
    }

    public function insert(array $record): int|false {
        $date = (string) ($record['data'] ?? '');
        $month = Calendari_Format::month_from_date($date);
        if (null === $month) {
            return false;
        }

        $now = current_time('mysql', true);
        $result = $this->wpdb->insert(
            $this->table,
            array(
                'aa_id' => $record['aa_id'],
                'mese' => $month,
                'data' => $date,
                'sede' => $record['sede'],
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );

        return false === $result ? false : (int) $this->wpdb->insert_id;
    }

    public function update(int $id, array $record): int|false {
        $date = (string) ($record['data'] ?? '');
        $month = Calendari_Format::month_from_date($date);
        if (null === $month) {
            return false;
        }

        return $this->wpdb->update(
            $this->table,
            array(
                'aa_id' => $record['aa_id'],
                'mese' => $month,
                'data' => $date,
                'sede' => $record['sede'],
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => $id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );
    }

    public function delete(int $id): int|false {
        return $this->wpdb->delete($this->table, array('id' => $id), array('%d'));
    }

    public function set_hidden(int $id, bool $is_hidden): int|false {
        return $this->wpdb->update(
            $this->table,
            array('is_hidden' => $is_hidden ? 1 : 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
    }

    public function find(int $id): ?array {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
        $record = $this->wpdb->get_row($sql, ARRAY_A);

        return $record ?: null;
    }

    public function find_calendar(string $aa_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE aa_id = %s AND is_hidden = 0
             ORDER BY data ASC, mese ASC, sede ASC, id ASC",
            $aa_id
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function distinct_aa_ids(): array {
        $values = $this->wpdb->get_col("SELECT DISTINCT aa_id FROM {$this->table} WHERE aa_id <> '' ORDER BY aa_id DESC");

        return array_values(array_filter(array_map('strval', $values ?: array()), static function (string $value): bool {
            return '' !== $value;
        }));
    }

    public function distinct_months(): array {
        $values = $this->wpdb->get_col("SELECT DISTINCT mese FROM {$this->table} WHERE mese BETWEEN 1 AND 12 ORDER BY mese ASC");

        return array_values(array_map('intval', $values ?: array()));
    }

    public function distinct_dates(): array {
        $values = $this->wpdb->get_col("SELECT DISTINCT data FROM {$this->table} WHERE data <> '' ORDER BY data ASC");

        return array_values(array_filter(array_map('strval', $values ?: array()), static function (string $value): bool {
            return '' !== $value;
        }));
    }

    public function distinct_venues(): array {
        $values = $this->wpdb->get_col("SELECT DISTINCT sede FROM {$this->table} WHERE sede <> '' ORDER BY sede ASC");

        return array_values(array_filter(array_map('strval', $values ?: array()), static function (string $value): bool {
            return '' !== $value;
        }));
    }

    public function count(array $filters = array()): int {
        [$where, $params] = $this->build_where($filters);
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";

        return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $params));
    }

    public function list(array $filters = array(), int $limit = 25, int $offset = 0): array {
        [$where, $params] = $this->build_where($filters);
        $params[] = $limit;
        $params[] = $offset;
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE {$where}
             ORDER BY data ASC, aa_id ASC, mese ASC, id ASC
             LIMIT %d OFFSET %d",
            $params
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    private function build_where(array $filters): array {
        $where = array('1=1');
        $params = array();

        foreach (array('aa_id', 'data', 'sede') as $field) {
            if (isset($filters[$field]) && '' !== (string) $filters[$field]) {
                $where[] = "{$field} = %s";
                $params[] = (string) $filters[$field];
            }
        }

        if (isset($filters['mese']) && (int) $filters['mese'] > 0) {
            $where[] = 'mese = %d';
            $params[] = (int) $filters['mese'];
        }

        return array(implode(' AND ', $where), $params);
    }
}

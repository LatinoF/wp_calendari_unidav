<?php

defined('ABSPATH') || exit;

final class Calendari_Importer {
    public const HEADERS = array(
        'AA_ID',
        'TIPO_CORSO',
        'CDS_IDS',
        'GRUPPO',
        'DATA_APERTURA_PRENOTAZIONI',
        'DATA_CHIUSURA_PRENOTAZIONI',
        'DATA_ESAMI',
        'DATA_TERMINE_PW',
        'TIPO_PROVA',
        'SEDE',
    );

    private Calendari_DB $db;

    public function __construct(Calendari_DB $db) {
        $this->db = $db;
    }

    public function import_file(string $path): array|WP_Error {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ('csv' === $extension) {
            $rows = $this->read_csv($path);
        } elseif ('xlsx' === $extension) {
            $rows = $this->read_xlsx($path);
        } else {
            return new WP_Error('calendari_file_type', __('Sono supportati solo file CSV e XLSX.', 'calendari'));
        }

        if (is_wp_error($rows)) {
            return $rows;
        }

        if (count($rows) < 2) {
            return new WP_Error('calendari_empty_file', __('Il file deve contenere una riga di intestazione e almeno una riga dati.', 'calendari'));
        }

        $headers = array_map(array($this, 'normalize_header'), array_shift($rows));
        $required_headers = array_values(array_diff(self::HEADERS, array('CDS_IDS', 'GRUPPO', 'DATA_TERMINE_PW', 'SEDE', 'TIPO_PROVA')));
        $allowed_headers = array_merge(self::HEADERS, array('MESE'));
        if (count($headers) !== count(array_unique($headers)) || array_diff($required_headers, $headers) || array_diff($headers, $allowed_headers)) {
            return new WP_Error(
                'calendari_headers',
                sprintf(
                    /* translators: 1: required header list, 2: optional header list. */
                    __('Intestazioni non valide. Colonne obbligatorie: %1$s. Colonne facoltative: %2$s.', 'calendari'),
                    implode(', ', $required_headers),
                    'CDS_IDS, GRUPPO, DATA_TERMINE_PW, TIPO_PROVA, SEDE'
                )
            );
        }

        $positions = array_flip($headers);
        $valid_records = array();
        $errors = array();

        foreach ($rows as $index => $row) {
            $row_number = $index + 2;
            if ($this->is_empty_row($row)) {
                continue;
            }

            $values = array();
            foreach (self::HEADERS as $header) {
                $values[$header] = isset($positions[$header]) ? ($row[$positions[$header]] ?? '') : '';
            }

            $validation = self::validate_values($values, $row_number);
            if ($validation['errors']) {
                $errors = array_merge($errors, $validation['errors']);
                continue;
            }

            $valid_records[] = $validation['record'];
        }

        if (!$valid_records) {
            return array(
                'rows' => count($rows),
                'inserted' => 0,
                'updated' => 0,
                'errors' => $errors ?: array(__('Non sono stati trovati record validi.', 'calendari')),
            );
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $inserted = 0;
        $updated = 0;

        foreach ($valid_records as $record) {
            $result = $this->db->upsert($record);
            if (false === $result) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('calendari_database', __('Impossibile salvare l’importazione. La transazione del database è stata annullata.', 'calendari'));
            }

            if (1 === (int) $result) {
                $inserted++;
            } else {
                $updated++;
            }
        }

        $wpdb->query('COMMIT');

        return array(
            'rows' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
        );
    }

    public static function validate_values(array $values, int $row_number = 0): array {
        $prefix = $row_number ? sprintf(__('Riga %d:', 'calendari'), $row_number) . ' ' : '';
        $errors = array();
        $record = array(
            'aa_id' => Calendari_Format::normalize_academic_year_id((string) ($values['AA_ID'] ?? '')),
            'tipo_corso' => Calendari_Format::normalize_course_type((string) ($values['TIPO_CORSO'] ?? '')),
            'cds_ids' => Calendari_DB::normalize_course_ids((string) ($values['CDS_IDS'] ?? '')),
            'gruppo' => sanitize_text_field((string) ($values['GRUPPO'] ?? '')),
            'data_apertura_prenotazioni' => self::normalize_date($values['DATA_APERTURA_PRENOTAZIONI'] ?? ''),
            'data_chiusura_prenotazioni' => self::normalize_date($values['DATA_CHIUSURA_PRENOTAZIONI'] ?? ''),
            'data_esami' => self::normalize_date($values['DATA_ESAMI'] ?? ''),
            'data_termine_pw' => '' === trim((string) ($values['DATA_TERMINE_PW'] ?? '')) ? '' : self::normalize_date($values['DATA_TERMINE_PW']),
            'tipo_prova' => self::normalize_tipo_prova($values['TIPO_PROVA'] ?? ''),
            'sede' => sanitize_text_field((string) ($values['SEDE'] ?? '')),
        );

        foreach (array('aa_id' => 'AA_ID', 'tipo_corso' => 'TIPO_CORSO') as $key => $label) {
            if ('' === $record[$key]) {
                $errors[] = $prefix . sprintf(__('%s è obbligatorio.', 'calendari'), $label);
            }
        }
        if ('' !== $record['tipo_corso'] && !isset(Calendari_Format::course_type_labels()[$record['tipo_corso']])) {
            $errors[] = $prefix . __('TIPO_CORSO deve corrispondere a un tipo di corso riconosciuto.', 'calendari');
        }

        foreach (array(
            'data_apertura_prenotazioni' => 'DATA_APERTURA_PRENOTAZIONI',
            'data_chiusura_prenotazioni' => 'DATA_CHIUSURA_PRENOTAZIONI',
            'data_esami' => 'DATA_ESAMI',
        ) as $key => $label) {
            if (!$record[$key]) {
                $errors[] = $prefix . sprintf(__('%s deve essere una data valida nel formato DD/MM/YYYY o YYYY-MM-DD.', 'calendari'), $label);
            }
        }

        $record['mese'] = Calendari_Format::month_from_date($record['data_esami']) ?? 0;

        if ('' !== trim((string) ($values['TIPO_PROVA'] ?? '')) && '' === $record['tipo_prova']) {
            $errors[] = $prefix . __('TIPO_PROVA deve essere orale o scritta.', 'calendari');
        }
        if ('' !== $record['tipo_prova'] && 'cds' !== strtolower($record['tipo_corso'])) {
            $errors[] = $prefix . __('TIPO_PROVA è disponibile solo per il tipo corso CDS.', 'calendari');
        }
        if ('' !== (string) ($values['DATA_TERMINE_PW'] ?? '') && '' === $record['data_termine_pw']) {
            $errors[] = $prefix . __('DATA_TERMINE_PW deve essere una data valida nel formato DD/MM/YYYY o YYYY-MM-DD.', 'calendari');
        }

        return array('record' => $record, 'errors' => $errors);
    }

    public static function normalize_tipo_prova(mixed $value): string {
        $value = strtolower(trim((string) $value));
        if ('orale' === $value) {
            return 'Orale';
        }
        if ('scritta' === $value) {
            return 'Scritta';
        }

        return '';
    }

    public static function normalize_date(mixed $value): string {
        if (is_numeric($value) && (float) $value > 20000) {
            $base = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));
            return $base->modify('+' . (int) $value . ' days')->format('Y-m-d');
        }

        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        foreach (array('!d/m/Y', '!Y-m-d', '!d-m-Y') as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $last_errors = DateTimeImmutable::getLastErrors();
            $has_errors = is_array($last_errors) && ($last_errors['warning_count'] || $last_errors['error_count']);
            if ($date && !$has_errors && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return '';
    }

    private function read_csv(string $path): array|WP_Error {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return new WP_Error('calendari_read_csv', __('Impossibile aprire il file CSV.', 'calendari'));
        }

        $first_line = fgets($handle);
        if (false === $first_line) {
            fclose($handle);
            return array();
        }

        $delimiter = $this->detect_delimiter($first_line);
        rewind($handle);
        $rows = array();
        while (false !== ($row = fgetcsv($handle, 0, $delimiter))) {
            $rows[] = array_map(array($this, 'normalize_text'), $row);
        }
        fclose($handle);

        return $rows;
    }

    private function read_xlsx(string $path): array|WP_Error {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('calendari_xlsx_zip', __('L’importazione XLSX richiede l’estensione PHP ZipArchive.', 'calendari'));
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($path)) {
            return new WP_Error('calendari_xlsx_open', __('Impossibile aprire il file XLSX.', 'calendari'));
        }

        $shared_strings = array();
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if (false !== $shared_xml) {
            $shared = simplexml_load_string($shared_xml);
            if ($shared) {
                $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                foreach ($shared->children($namespace)->si as $item) {
                    $texts = $item->xpath('.//*[local-name()="t"]');
                    $shared_strings[] = implode('', array_map('strval', $texts ?: array()));
                }
            }
        }

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (false === $sheet_xml) {
            return new WP_Error('calendari_xlsx_sheet', __('Impossibile leggere il primo foglio di lavoro.', 'calendari'));
        }

        $sheet = simplexml_load_string($sheet_xml);
        if (!$sheet) {
            return new WP_Error('calendari_xlsx_xml', __('Il foglio XLSX non contiene XML valido.', 'calendari'));
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rows = array();
        foreach ($sheet->children($namespace)->sheetData->children($namespace)->row as $xml_row) {
            $cells = array();
            foreach ($xml_row->children($namespace)->c as $cell) {
                $attributes = $cell->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                preg_match('/^([A-Z]+)/', $reference, $matches);
                $column = $this->column_index($matches[1] ?? 'A');
                $type = (string) ($attributes['t'] ?? '');
                $cell_children = $cell->children($namespace);
                $value = '';

                if ('inlineStr' === $type) {
                    $texts = $cell_children->is->xpath('.//*[local-name()="t"]');
                    $value = implode('', array_map('strval', $texts ?: array()));
                } else {
                    $value = (string) ($cell_children->v ?? '');
                    if ('s' === $type && '' !== $value) {
                        $value = $shared_strings[(int) $value] ?? '';
                    }
                }

                $cells[$column] = $this->normalize_text($value);
            }

            if ($cells) {
                ksort($cells);
                $max = max(array_keys($cells));
                $row = array_fill(0, $max + 1, '');
                foreach ($cells as $column => $value) {
                    $row[$column] = $value;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function detect_delimiter(string $line): string {
        $counts = array(
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        );
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function column_index(string $letters): int {
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + ord($letters[$i]) - 64;
        }

        return max(0, $index - 1);
    }

    private function normalize_header(string $value): string {
        return strtoupper(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value)));
    }

    private function normalize_text(string $value): string {
        $value = trim($value);
        if (function_exists('mb_convert_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $value;
    }

    private function is_empty_row(array $row): bool {
        return '' === trim(implode('', $row));
    }
}

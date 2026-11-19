<?php

defined('ABSPATH') || exit;

final class Calendari_Format {
    private const MONTH_NAMES = array(
        1 => 'gennaio',
        2 => 'febbraio',
        3 => 'marzo',
        4 => 'aprile',
        5 => 'maggio',
        6 => 'giugno',
        7 => 'luglio',
        8 => 'agosto',
        9 => 'settembre',
        10 => 'ottobre',
        11 => 'novembre',
        12 => 'dicembre',
    );

    private const DATE_FORMATS = array(
        'd/m/Y' => '25/08/1998 (DD/MM/YYYY)',
        'd-m-Y' => '25-08-1998 (DD-MM-YYYY)',
        'Y-m-d' => '1998-08-25 (YYYY-MM-DD)',
        'm/d/Y' => '08/25/1998 (MM/DD/YYYY)',
        'long_italian' => '25 agosto 1998 (DD month YYYY)',
    );

    public static function date_formats(): array {
        return self::DATE_FORMATS;
    }

    public static function selected_date_format(): string {
        $settings = get_option('calendari_graphics', array());
        $format = is_array($settings) ? ($settings['date_format'] ?? 'd/m/Y') : 'd/m/Y';

        return isset(self::DATE_FORMATS[$format]) ? $format : 'd/m/Y';
    }

    public static function sanitize_date_format(mixed $format): string {
        $format = sanitize_text_field((string) $format);

        return isset(self::DATE_FORMATS[$format]) ? $format : '';
    }

    public static function format_date(string $value): string {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
            return $value;
        }

        $format = self::selected_date_format();
        if ('long_italian' === $format) {
            return $date->format('j') . ' ' . self::month_name((int) $date->format('n')) . ' ' . $date->format('Y');
        }

        return $date->format($format);
    }

    public static function month_heading(string $year_month): string {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $year_month . '-01', wp_timezone());
        if (!$date || $date->format('Y-m') !== $year_month) {
            return $year_month;
        }

        return self::month_name((int) $date->format('n')) . ' ' . $date->format('Y');
    }

    public static function month_from_date(string $date_value): ?int {
        $date = self::parse_date($date_value);

        return $date ? (int) $date->format('n') : null;
    }

    public static function month_name_from_date(string $date_value, bool $standalone = false): string {
        $month = self::month_from_date($date_value);

        return null === $month ? $date_value : self::month_name($month, $standalone);
    }

    public static function month_name_with_year(string $date_value): string {
        $date = self::parse_date($date_value);
        if (!$date) {
            return $date_value;
        }

        return self::month_name((int) $date->format('n'), true) . ' ' . $date->format('Y');
    }

    private static function parse_date(string $date_value): ?DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $date_value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $date_value) {
            return null;
        }

        return $date;
    }

    public static function month_name(int $month, bool $standalone = false): string {
        $name = self::MONTH_NAMES[max(1, min(12, $month))];

        return $standalone ? ucfirst($name) : $name;
    }
}

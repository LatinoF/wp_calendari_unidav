<?php

defined('ABSPATH') || exit;

final class Calendari_Colors {
    public static function derive(string $accent): array {
        $accent = sanitize_hex_color($accent) ?: '#037aa4';
        $hex = ltrim($accent, '#');
        if (3 === strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 255000;
        $gray = (int) round((($red * 299) + ($green * 587) + ($blue * 114)) / 1000);
        $mix = static function (int $target_red, int $target_green, int $target_blue, float $amount) use ($red, $green, $blue): string {
            return sprintf(
                '#%02x%02x%02x',
                (int) round($red + (($target_red - $red) * $amount)),
                (int) round($green + (($target_green - $green) * $amount)),
                (int) round($blue + (($target_blue - $blue) * $amount))
            );
        };

        return array(
            'accent' => '#' . $hex,
            'header' => '#' . $hex,
            'deep' => $mix(0, 0, 0, .3),
            'soft' => $mix(255, 255, 255, .86),
            'row' => $mix(255, 255, 255, .9),
            'surface' => $mix(255, 255, 255, .95),
            'card' => $mix(255, 255, 255, .985),
            'ink' => $mix(0, 0, 0, .82),
            'muted' => $mix($gray, $gray, $gray, .55),
            'line' => $mix(255, 255, 255, .82),
            'border' => $mix(255, 255, 255, .2),
            'contrast' => $luminance > .55 ? $mix(0, 0, 0, .88) : $mix(255, 255, 255, .96),
        );
    }
}

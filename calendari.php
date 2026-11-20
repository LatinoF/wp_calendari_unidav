<?php
/**
 * Plugin Name: Calendari
 * Description: Importa i calendari accademici e visualizza calendari filtrabili tramite shortcode.
 * Version: 4.0.5
 * Update URI: https://github.com/LatinoF/wp_calendari_unidav
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: LatinoF
 * Text Domain: calendari
 */

defined('ABSPATH') || exit;

if (defined('CALENDARI_FILE')) {
    if (is_admin()) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>' . esc_html__('Può essere attiva una sola copia di Calendari. Disattiva le altre copie e mantieni attiva la versione più recente.', 'calendari') . '</p></div>';
        });
    }
    return;
}

define('CALENDARI_VERSION', '4.0.5');
define('CALENDARI_FILE', __FILE__);
define('CALENDARI_DIR', plugin_dir_path(__FILE__));
define('CALENDARI_URL', plugin_dir_url(__FILE__));

require_once CALENDARI_DIR . 'includes/class-calendari-db.php';
require_once CALENDARI_DIR . 'includes/class-calendari-updater.php';
require_once CALENDARI_DIR . 'includes/class-calendari-degree-db.php';
require_once CALENDARI_DIR . 'includes/class-calendari-importer.php';
require_once CALENDARI_DIR . 'includes/class-calendari-colors.php';
require_once CALENDARI_DIR . 'includes/class-calendari-format.php';
require_once CALENDARI_DIR . 'includes/class-calendari-shortcode.php';
require_once CALENDARI_DIR . 'includes/class-calendari-admin.php';
require_once CALENDARI_DIR . 'includes/class-calendari-plugin.php';

register_activation_hook(CALENDARI_FILE, static function () {
    Calendari_DB::install();
    Calendari_Degree_DB::install();
});

add_action('plugins_loaded', static function () {
    Calendari_Plugin::instance()->boot();
    if (is_admin()) {
        new Calendari_Updater('LatinoF/wp_calendari_unidav', CALENDARI_FILE);
    }
});

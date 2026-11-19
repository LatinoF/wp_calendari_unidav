<?php

defined('ABSPATH') || exit;

final class Calendari_Plugin {
    private static ?Calendari_Plugin $instance = null;

    private Calendari_DB $db;

    private Calendari_Degree_DB $degree_db;

    private function __construct() {
        $this->db = new Calendari_DB();
        $this->degree_db = new Calendari_Degree_DB();
    }

    public static function instance(): Calendari_Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        Calendari_DB::maybe_upgrade();
        Calendari_Degree_DB::maybe_upgrade();
        $shortcode = new Calendari_Shortcode($this->db, $this->degree_db);
        add_shortcode('calendari', array($shortcode, 'render'));

        if (is_admin()) {
            new Calendari_Admin($this->db, $this->degree_db);
        }
    }
}

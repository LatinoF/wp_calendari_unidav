<?php

defined('ABSPATH') || exit;

final class Calendari_Updater {
    private string $repository;

    private string $plugin_file;

    private bool $was_active_before_upgrade = false;

    private bool $was_network_active_before_upgrade = false;

    public function __construct(string $repository, string $plugin_file) {
        $this->repository = $repository;
        $this->plugin_file = $plugin_file;
        $this->synchronize_update_caches();
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('upgrader_pre_install', array($this, 'remember_activation_before_upgrade'), 9, 2);
        add_action('upgrader_process_complete', array($this, 'restore_activation_after_upgrade'), 10, 2);
    }

    public function remember_activation_before_upgrade(mixed $response, array $hook_extra): mixed {
        if (is_wp_error($response)) {
            return $response;
        }

        $plugin_basename = plugin_basename($this->plugin_file);
        $updated_plugin = (string) ($hook_extra['plugin'] ?? '');
        if ($plugin_basename !== $updated_plugin) {
            return $response;
        }

        $this->was_network_active_before_upgrade = is_plugin_active_for_network($plugin_basename);
        $this->was_active_before_upgrade = $this->was_network_active_before_upgrade || is_plugin_active($plugin_basename);

        return $response;
    }

    public function restore_activation_after_upgrade(mixed $upgrader, array $options): void {
        if (!$this->was_active_before_upgrade
            || 'update' !== ($options['action'] ?? '')
            || 'plugin' !== ($options['type'] ?? '')
        ) {
            return;
        }

        $plugin_basename = plugin_basename($this->plugin_file);
        $updated_plugins = array_filter(array(
            (string) ($options['plugin'] ?? ''),
            ...(array) ($options['plugins'] ?? array()),
        ));
        if (!in_array($plugin_basename, $updated_plugins, true)) {
            return;
        }

        $network_wide = $this->was_network_active_before_upgrade;
        $this->was_active_before_upgrade = false;
        $this->was_network_active_before_upgrade = false;

        if (is_plugin_active($plugin_basename) || is_plugin_active_for_network($plugin_basename)) {
            return;
        }

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        activate_plugin($plugin_basename, '', $network_wide);
    }

    private function synchronize_update_caches(): void {
        $cached_version = (string) get_option('calendari_update_cache_version', '');
        if (CALENDARI_VERSION === $cached_version) {
            return;
        }

        delete_site_transient('update_plugins');
        delete_transient('calendari_github_latest_release');
        update_option('calendari_update_cache_version', CALENDARI_VERSION);
    }

    public function check_for_update(mixed $transient): mixed {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $plugin_basename = plugin_basename($this->plugin_file);
        if (!isset($transient->checked[$plugin_basename])) {
            return $transient;
        }

        if (!isset($transient->no_update) || !is_array($transient->no_update)) {
            $transient->no_update = array();
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }
        $existing_response = $transient->response[$plugin_basename] ?? null;
        $existing_version = is_object($existing_response)
            ? (string) ($existing_response->new_version ?? '')
            : (is_array($existing_response) ? (string) ($existing_response['new_version'] ?? '') : '');
        if ('' === $existing_version || !version_compare($existing_version, CALENDARI_VERSION, '>')) {
            unset($transient->response[$plugin_basename]);
        }
        $transient->no_update[$plugin_basename] = $this->build_update_data($plugin_basename, CALENDARI_VERSION, '');

        $force_check = isset($_GET['force-check']);
        if ($force_check) {
            delete_transient('calendari_github_latest_release');
        }
        $release = $force_check ? false : get_transient('calendari_github_latest_release');
        if (false === $release) {
            $response = wp_remote_get(
                'https://api.github.com/repos/' . rawurlencode(strtok($this->repository, '/')) . '/'. rawurlencode(substr($this->repository, strpos($this->repository, '/') + 1)) . '/releases/latest',
                array(
                    'timeout' => 8,
                    'headers' => array(
                        'Accept' => 'application/vnd.github+json',
                        'User-Agent' => 'Calendari-WordPress-Plugin',
                        'X-GitHub-Api-Version' => '2022-11-28',
                    ),
                )
            );
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                return $transient;
            }
            $release = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($release) || empty($release['tag_name']) || empty($release['assets'])) {
                return $transient;
            }
            set_transient('calendari_github_latest_release', $release, HOUR_IN_SECONDS);
        }

        $version = ltrim(sanitize_text_field((string) ($release['tag_name'] ?? '')), 'vV');
        if ('' === $version || !version_compare($version, CALENDARI_VERSION, '>')) {
            unset($transient->response[$plugin_basename]);
            return $transient;
        }

        $package = '';
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            $asset_name = (string) ($asset['name'] ?? '');
            if (preg_match('/^Calendari[ .-]+' . preg_quote($version, '/') . '\.zip$/i', $asset_name)) {
                $package = esc_url_raw((string) ($asset['browser_download_url'] ?? ''));
                break;
            }
        }
        if ('' === $package) {
            unset($transient->response[$plugin_basename]);
            return $transient;
        }

        unset($transient->no_update[$plugin_basename]);
        $transient->response[$plugin_basename] = $this->build_update_data($plugin_basename, $version, $package);

        return $transient;
    }

    private function build_update_data(string $plugin_basename, string $version, string $package): object {
        return (object) array(
            'id' => 'https://github.com/' . $this->repository,
            'slug' => dirname($plugin_basename),
            'plugin' => $plugin_basename,
            'new_version' => $version,
            'url' => 'https://github.com/' . $this->repository,
            'package' => $package,
            'tested' => '',
            'requires' => '6.0',
            'requires_php' => '8.0',
        );
    }
}

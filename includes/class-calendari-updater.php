<?php

defined('ABSPATH') || exit;

final class Calendari_Updater {
    private string $repository;

    private string $plugin_file;

    public function __construct(string $repository, string $plugin_file) {
        $this->repository = $repository;
        $this->plugin_file = $plugin_file;
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
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
            return $transient;
        }

        unset($transient->no_update[$plugin_basename]);
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }
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

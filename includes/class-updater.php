<?php
/**
 * GitHub Updater for Marrison Exporter
 * 
 * This class handles automatic updates from GitHub releases
 */

if (!class_exists('Marrison_Exporter_Updater')) {
    class Marrison_Exporter_Updater {
        
        private $plugin_slug = 'marrison-exporter';
        private $github_repo = 'marrisonlab/marrison-exporter';
        private $github_api_url = 'https://api.github.com/repos';
        private $cache_key = 'marrison_exporter_update_info';
        private $cache_duration = 3600; // 1 ora
        
        public function __construct() {
            add_filter('site_transient_update_plugins', array($this, 'check_for_updates'));
            add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
            add_action('admin_init', array($this, 'force_check_updates'));
            add_filter('plugin_action_links_' . plugin_basename(MARRISON_EXPORTER_PLUGIN_DIR . 'marrison-exporter.php'), array($this, 'add_force_check_link'), 10, 2);
            add_action('upgrader_process_complete', array($this, 'clear_update_cache'), 10, 2);
        }
        
        /**
         * Controlla gli aggiornamenti disponibili
         */
        public function check_for_updates($transient) {
            if (empty($transient)) {
                return $transient;
            }
            
            // Ottieni informazioni sull'ultima release
            $release_info = $this->get_latest_release();
            
            if (!$release_info || empty($release_info['tag_name'])) {
                return $transient;
            }
            
            $latest_version = $release_info['tag_name'];
            $current_version = MARRISON_EXPORTER_VERSION;
            
            // Confronta versioni
            if (version_compare($latest_version, $current_version, '>')) {
                $plugin_info = new stdClass();
                $plugin_info->slug = $this->plugin_slug;
                $plugin_info->new_version = $latest_version;
                $plugin_info->url = $release_info['html_url'];
                $plugin_info->package = $release_info['zipball_url'];
                $plugin_info->plugin = 'marrison-exporter/marrison-exporter.php';
                
                $transient->response[$plugin_info->plugin] = $plugin_info;
            }
            
            return $transient;
        }
        
        /**
         * Ottiene informazioni sulla release più recente da GitHub
         */
        private function get_latest_release() {
            $cached = get_transient($this->cache_key);
            
            if ($cached !== false) {
                return $cached;
            }
            
            $api_url = $this->github_api_url . '/' . $this->github_repo . '/releases/latest';
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'Marrison-Exporter-Updater'
                )
            ));
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $release_data = json_decode($body, true);
            
            if (empty($release_data) || !isset($release_data['tag_name'])) {
                return false;
            }
            
            // Cache per 1 ora
            set_transient($this->cache_key, $release_data, $this->cache_duration);
            
            return $release_data;
        }
        
        /**
         * Fornisce informazioni sul plugin per l'update screen
         */
        public function plugin_info($res, $action, $args) {
            if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
                return $res;
            }
            
            $release_info = $this->get_latest_release();
            
            if (!$release_info) {
                return $res;
            }
            
            $plugin_info = new stdClass();
            $plugin_info->name = 'Marrison Exporter';
            $plugin_info->slug = $this->plugin_slug;
            $plugin_info->version = $release_info['tag_name'];
            $plugin_info->author = '<a href="https://marrisonlab.com">Marrison</a>';
            $plugin_info->author_profile = 'https://marrisonlab.com';
            $plugin_info->requires = '5.0';
            $plugin_info->tested = '6.4';
            $plugin_info->requires_php = '7.4';
            $plugin_info->downloaded = 0;
            $plugin_info->last_updated = $release_info['published_at'];
            $plugin_info->sections = array(
                'description' => 'Plugin per esportare gli ordini di WooCommerce in formato CSV con selezione di date e colonne.',
                'changelog' => $this->format_changelog($release_info['body'])
            );
            $plugin_info->download_link = $release_info['zipball_url'];
            $plugin_info->homepage = 'https://github.com/marrisonlab/marrison-exporter';
            
            return $plugin_info;
        }
        
        /**
         * Formatta il changelog dalla release
         */
        private function format_changelog($body) {
            if (empty($body)) {
                return 'Nessuna informazione sul changelog disponibile.';
            }
            
            // Converti markdown in HTML semplice
            $changelog = nl2br(esc_html($body));
            
            // Converti links markdown
            $changelog = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $changelog);
            
            return $changelog;
        }
        
        /**
         * Forza il controllo degli aggiornamenti
         */
        public function force_check_updates() {
            if (isset($_GET['force-check']) && $_GET['force-check'] === 'marrison-exporter') {
                delete_transient($this->cache_key);
                
                // Reindirizza alla pagina dei plugin
                wp_redirect(admin_url('plugins.php'));
                exit;
            }
        }
        
        /**
         * Aggiunge link per forzare controllo aggiornamenti
         */
        public function add_force_check_link($links) {
            $links[] = '<a href="' . esc_url(admin_url('plugins.php?force-check=marrison-exporter')) . '">Forza controllo</a>';
            return $links;
        }
        
        /**
         * Pulisce la cache quando il plugin viene aggiornato
         */
        public function clear_update_cache() {
            delete_transient($this->cache_key);
        }
    }
}

// Inizializza l'updater
new Marrison_Exporter_Updater();

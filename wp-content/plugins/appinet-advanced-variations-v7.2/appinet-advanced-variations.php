<?php
/**
 * Plugin Name: Appinet Advanced Variations
 * Description: Rozbudowana obsługa dodatkowych pól i SEO URL dla wariantów WooCommerce.
 * Version: 1.9.21
 * Author: Appinet
 * Text Domain: appinet-advanced-variations
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AAV_PATH', plugin_dir_path(__FILE__));
define('AAV_URL', plugin_dir_url(__FILE__));
define('AAV_VERSION', '1.9.21');

if (!class_exists('Appinet_Advanced_Variations')) {
    final class Appinet_Advanced_Variations
    {
        public function __construct()
        {
            register_activation_hook(__FILE__, [$this, 'activate']);
            register_deactivation_hook(__FILE__, [$this, 'deactivate']);
            add_action('plugins_loaded', [$this, 'init']);
        }

        public function activate()
        {
            $this->load_files();
            AAV_Variation_URLs::register_rewrite_rules();
            flush_rewrite_rules();
        }

        public function deactivate()
        {
            flush_rewrite_rules();
        }

        public function init()
        {
            if (!class_exists('WooCommerce')) {
                return;
            }

            $this->load_files();

            new AAV_Admin();
            new AAV_Frontend();
            $variation_urls = new AAV_Variation_URLs();
            add_action('init', [$this, 'maybe_refresh_rewrite_rules'], 20);
        }

        public function maybe_refresh_rewrite_rules()
        {
            $installed_version = get_option('aav_version');
            if ($installed_version === AAV_VERSION) {
                return;
            }

            AAV_Variation_URLs::register_rewrite_rules();
            flush_rewrite_rules(false);
            update_option('aav_version', AAV_VERSION);
        }

        private function load_files()
        {
            require_once AAV_PATH . 'includes/class-aav-admin.php';
            require_once AAV_PATH . 'includes/class-aav-frontend.php';
            require_once AAV_PATH . 'includes/class-aav-variation-urls.php';
        }
    }

    new Appinet_Advanced_Variations();
}

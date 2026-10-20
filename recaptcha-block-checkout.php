<?php
/**
 * Plugin Name: reCAPTCHA for WooCommerce Block Checkout
 * Plugin URI: https://olivierbigras.com/recaptcha-block-checkout
 * Description: Adds Google reCAPTCHA v2 protection to WooCommerce block-based checkout to prevent spam orders.
 * Version: 1.0.0
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 * Text Domain: recaptcha-block-checkout
 * Domain Path: /languages
 *
 * @package Recaptcha_Block_Checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RBC_VERSION', '1.0.0');
define('RBC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RBC_PLUGIN_URL', plugin_dir_url(__FILE__));

class Recaptcha_Block_Checkout {
    
    private static $instance = null;
    private $site_key;
    private $secret_key;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->site_key = get_option('rbc_site_key', '');
        $this->secret_key = get_option('rbc_secret_key', '');
        
        // Load translations
        add_action('init', [$this, 'load_textdomain']);
        
        // Admin settings
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Settings link in plugins list
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        
        // Only load if keys are configured
        if ($this->site_key && $this->secret_key) {
            // Enqueue scripts
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            
            // Register Store API endpoint data
            add_action('woocommerce_blocks_loaded', [$this, 'register_endpoint_data']);
            
            // Validate on checkout
            add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'validate_checkout'], 10, 2);
        }
        
        // HPOS compatibility
        add_action('before_woocommerce_init', function() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('recaptcha-block-checkout', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=recaptcha-block-checkout')) . '">' . esc_html__('Settings', 'recaptcha-block-checkout') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('reCAPTCHA Block Checkout', 'recaptcha-block-checkout'),
            __('reCAPTCHA Checkout', 'recaptcha-block-checkout'),
            'manage_options',
            'recaptcha-block-checkout',
            [$this, 'settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('rbc_settings', 'rbc_site_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('rbc_settings', 'rbc_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('rbc_settings', 'rbc_theme', ['sanitize_callback' => 'sanitize_text_field']);
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('reCAPTCHA for Block Checkout', 'recaptcha-block-checkout'); ?></h1>
            <p><?php esc_html_e('Add Google reCAPTCHA v2 to your WooCommerce block checkout to prevent spam orders.', 'recaptcha-block-checkout'); ?></p>
            
            <?php if ($this->site_key && $this->secret_key): ?>
                <div class="notice notice-success"><p><strong><?php esc_html_e('✓ reCAPTCHA is active and protecting your checkout.', 'recaptcha-block-checkout'); ?></strong></p></div>
            <?php else: ?>
                <div class="notice notice-warning"><p><strong><?php esc_html_e('Please configure your reCAPTCHA API keys below.', 'recaptcha-block-checkout'); ?></strong></p></div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('rbc_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Key', 'recaptcha-block-checkout'); ?></th>
                        <td>
                            <input type="text" name="rbc_site_key" value="<?php echo esc_attr(get_option('rbc_site_key')); ?>" class="regular-text" />
                            <p class="description"><?php printf(
                                /* translators: %s: URL to Google reCAPTCHA admin */
                                esc_html__('Get your keys from %s. Use reCAPTCHA v2 "I\'m not a robot" Checkbox.', 'recaptcha-block-checkout'),
                                '<a href="https://www.google.com/recaptcha/admin/create" target="_blank">Google reCAPTCHA Admin</a>'
                            ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Secret Key', 'recaptcha-block-checkout'); ?></th>
                        <td>
                            <input type="text" name="rbc_secret_key" value="<?php echo esc_attr(get_option('rbc_secret_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Theme', 'recaptcha-block-checkout'); ?></th>
                        <td>
                            <select name="rbc_theme">
                                <option value="light" <?php selected(get_option('rbc_theme', 'light'), 'light'); ?>><?php esc_html_e('Light', 'recaptcha-block-checkout'); ?></option>
                                <option value="dark" <?php selected(get_option('rbc_theme'), 'dark'); ?>><?php esc_html_e('Dark', 'recaptcha-block-checkout'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        // Google reCAPTCHA API with explicit render
        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js?onload=rbcRecaptchaOnLoad&render=explicit',
            [],
            null,
            true
        );
        
        // Our custom script
        wp_enqueue_script(
            'rbc-checkout-js',
            plugins_url('js/checkout.js', __FILE__),
            ['google-recaptcha', 'wp-data'],
            RBC_VERSION,
            true
        );
        
        // Pass config to JS
        wp_localize_script('rbc-checkout-js', 'rbcConfig', [
            'siteKey' => $this->site_key,
            'theme' => get_option('rbc_theme', 'light'),
        ]);
    }
    
    public function register_endpoint_data() {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        
        woocommerce_store_api_register_endpoint_data([
            'endpoint' => 'checkout',
            'namespace' => 'recaptcha-block-checkout',
            'schema_callback' => function() {
                return [
                    'token' => [
                        'description' => 'reCAPTCHA token',
                        'type' => 'string',
                        'readonly' => false,
                    ],
                ];
            },
        ]);
    }
    
    public function validate_checkout($order, $request) {
        if ($request->get_method() !== 'POST') {
            return $order;
        }
        
        $extensions = $request->get_param('extensions');
        $token = '';
        
        if (!empty($extensions['recaptcha-block-checkout']['token'])) {
            $token = sanitize_text_field($extensions['recaptcha-block-checkout']['token']);
        }
        
        if (empty($token)) {
            throw new \Exception(esc_html__('Please complete the reCAPTCHA verification.', 'recaptcha-block-checkout'));
        }
        
        // Verify with Google
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $this->secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception(esc_html__('reCAPTCHA verification failed. Please try again.', 'recaptcha-block-checkout'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['success'])) {
            throw new \Exception(esc_html__('reCAPTCHA verification failed. Please complete the challenge again.', 'recaptcha-block-checkout'));
        }
        
        return $order;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        Recaptcha_Block_Checkout::get_instance();
    }
});

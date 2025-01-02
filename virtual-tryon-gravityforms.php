<?php
/**
 * Plugin Name: Virtual Try-on for GravityForms
 * Description: Adds virtual try-on capabilities to GravityForms using Replicate API
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: virtual-tryon-gravityforms
 */

if (!defined('ABSPATH')) exit;

define('GF_VIRTUAL_TRYON_VERSION', '1.0.0');
define('GF_VIRTUAL_TRYON_PATH', plugin_dir_path(__FILE__));
define('GF_VIRTUAL_TRYON_URL', plugin_dir_url(__FILE__));

// Load plugin textdomain
add_action('init', 'gf_virtual_tryon_load_textdomain');
function gf_virtual_tryon_load_textdomain() {
    load_plugin_textdomain('virtual-tryon-gravityforms', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Load admin assets
add_action('admin_enqueue_scripts', 'gf_virtual_tryon_admin_assets');
function gf_virtual_tryon_admin_assets($hook) {
    if ('toplevel_page_gf_edit_forms' !== $hook) return;
    
    wp_enqueue_style(
        'gf-virtual-tryon-admin',
        GF_VIRTUAL_TRYON_URL . 'assets/css/admin.css',
        array(),
        GF_VIRTUAL_TRYON_VERSION
    );
    
    wp_enqueue_script(
        'gf-virtual-tryon-admin',
        GF_VIRTUAL_TRYON_URL . 'assets/js/admin.js',
        array('jquery'),
        GF_VIRTUAL_TRYON_VERSION,
        true
    );
}

// Initialize the addon
add_action('gform_loaded', array('GF_Virtual_Tryon_Bootstrap', 'load'), 5);

class GF_Virtual_Tryon_Bootstrap {
    public static function load() {
        if (!method_exists('GFForms', 'include_addon_framework')) {
            return;
        }
        
        require_once GF_VIRTUAL_TRYON_PATH . 'includes/class-gf-virtual-tryon-addon.php';
        GFAddOn::register('GF_Virtual_Tryon_Addon');
    }
}

function gf_virtual_tryon_addon() {
    return GF_Virtual_Tryon_Addon::get_instance();
}
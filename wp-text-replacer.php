<?php
/*
Plugin Name: WP Text Replacer
Description: Professional WordPress plugin for dynamic text replacement on your website in real-time
Version: 1.1.4
Author: ROinfo
Author URI: https://m2w.com.ua
Text Domain: wp-text-replacer
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit; // Protection from direct access

// Load text domain for localization
function wptr_load_textdomain() {
    load_plugin_textdomain('wp-text-replacer', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'wptr_load_textdomain');

// Include admin files
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';

// Register scripts and styles
function wptr_enqueue_scripts() {
    // Enqueue admin styles for the plugin settings page
    wp_enqueue_style('wptr-admin-styles', 
        plugin_dir_url(__FILE__) . 'assets/css/admin-styles.css', 
        array(), 
        '1.1.0'
    );

    wp_enqueue_script('wptr-text-replacer', 
        plugin_dir_url(__FILE__) . 'assets/js/text-replacer.js', 
        array('jquery'), 
        '1.1.2', 
        true
    );

    // Pass settings to JavaScript with translation support
    $plugin_options = get_option('wptr_plugin_options', array(
        'rules' => array(),
        'search_mode' => 'onload',
        'search_iterations' => 1
    ));
    wp_localize_script('wptr-text-replacer', 'WPTextReplacerSettings', array(
        'rules' => $plugin_options['rules'] ?? array(),
        'searchMode' => $plugin_options['search_mode'] ?? 'onload',
        'searchIterations' => $plugin_options['search_iterations'] ?? 1,
        'translations' => array(
            'searchPlaceholder' => __('Find', 'wp-text-replacer'),
            'replacePlaceholder' => __('Replace with', 'wp-text-replacer')
        )
    ));
}
add_action('admin_enqueue_scripts', 'wptr_enqueue_scripts');
add_action('wp_enqueue_scripts', 'wptr_enqueue_scripts');

// Registration of activation and deactivation hooks
function wptr_activate() {
    // Initialization of settings during activation
    if (!get_option('wptr_replacement_rules')) {
        update_option('wptr_replacement_rules', array());
    }
}
register_activation_hook(__FILE__, 'wptr_activate');

function wptr_deactivate() {
    // Clearing settings during deactivation (optional)
    delete_option('wptr_replacement_rules');
}
register_deactivation_hook(__FILE__, 'wptr_deactivate'); 
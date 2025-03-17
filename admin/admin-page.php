<?php
if (!defined('ABSPATH')) exit;

// Register styles for the admin page
function wptr_enqueue_admin_styles() {
    wp_enqueue_style('wptr-admin-styles', 
        plugin_dir_url(__FILE__) . '../assets/css/admin-styles.css', 
        array(), 
        '1.1.0'
    );
}
add_action('admin_enqueue_scripts', 'wptr_enqueue_admin_styles');

// Add permission check
function wptr_check_permissions() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-text-replacer'));
    }
}

// Add settings page to admin menu
function wptr_add_admin_menu() {
    add_options_page(
        __('Text Replacement Settings', 'wp-text-replacer'), 
        __('Text Replacement', 'wp-text-replacer'), 
        'manage_options', 
        'wp-text-replacer', 
        'wptr_admin_page'
    );
}
add_action('admin_menu', 'wptr_add_admin_menu');

// Advanced settings sanitization
function wptr_sanitize_settings($input) {
    $new_input = array();
    
    // Sanitize text replacement rules
    if (isset($input['rules']) && is_array($input['rules'])) {
        foreach ($input['rules'] as $key => $rules) {
            if (is_array($rules)) {
                foreach ($rules as $rule_key => $rule_value) {
                    $new_input['rules'][$key][$rule_key] = sanitize_text_field($rule_value);
                }
            }
        }
    }
    
    // Sanitize search mode
    if (isset($input['search_mode'])) {
        $new_input['search_mode'] = in_array($input['search_mode'], ['onload', 'continuous']) 
            ? $input['search_mode'] 
            : 'onload';
    }
    
    // Sanitize number of iterations
    if (isset($input['search_iterations'])) {
        $new_input['search_iterations'] = absint($input['search_iterations']);
        if ($new_input['search_iterations'] < 1) {
            $new_input['search_iterations'] = 1;
        }
    }
    
    return $new_input;
}

// Register all settings
function wptr_register_settings() {
    register_setting(
        'wptr_settings_group', 
        'wptr_plugin_options', 
        'wptr_sanitize_settings'
    );
}
add_action('admin_init', 'wptr_register_settings');

// Updated page settings function
function wptr_admin_page() {
    // Get current settings
    $options = get_option('wptr_plugin_options', array(
        'rules' => array(array('search' => '', 'replace' => '')),
        'search_mode' => 'onload',
        'search_iterations' => 1
    ));
    
    $rules = $options['rules'] ?? array(array('search' => '', 'replace' => ''));
    $search_mode = $options['search_mode'] ?? 'onload';
    $search_iterations = $options['search_iterations'] ?? 1;

    // Check access rights
    wptr_check_permissions();
    ?>
    <div class="wptr-container">
        <h1><?php echo esc_html__('Text Replacement Settings', 'wp-text-replacer'); ?></h1>
        <form method="post" action="options.php" class="wptr-form">
            <?php 
            settings_fields('wptr_settings_group');
            do_settings_sections('wptr_settings_group');
            ?>
            
            <div class="wptr-section">
                <h3><?php echo esc_html__('Replacement Rules', 'wp-text-replacer'); ?></h3>
                <div id="replacement-rules-container" class="wptr-rules-container">
                    <?php 
                    foreach ($rules as $index => $rule) {
                    ?>
                        <div class="wptr-rule">
                            <div class="wptr-form-group">
                                <label><?php echo esc_html__('Find:', 'wp-text-replacer'); ?></label>
                                <input type="text" name="wptr_plugin_options[rules][<?php echo esc_attr($index); ?>][search]" 
                                       value="<?php echo esc_attr($rule['search']); ?>" />
                            </div>
                            <div class="wptr-form-group">
                                <label><?php echo esc_html__('Replace with:', 'wp-text-replacer'); ?></label>
                                <input type="text" name="wptr_plugin_options[rules][<?php echo esc_attr($index); ?>][replace]" 
                                       value="<?php echo esc_attr($rule['replace']); ?>" />
                            </div>
                            <button type="button" class="wptr-btn wptr-btn-danger remove-rule">
                                <?php echo esc_html__('Delete', 'wp-text-replacer'); ?>
                            </button>
                        </div>
                    <?php } ?>
                </div>
                
                <button type="button" id="add-rule" class="wptr-btn">
                    <?php echo esc_html__('Add Rule', 'wp-text-replacer'); ?>
                </button>
            </div>
            
            <div class="wptr-section">
                <h3><?php echo esc_html__('Search Settings', 'wp-text-replacer'); ?></h3>
                <div class="wptr-form-group">
                    <label><?php echo esc_html__('Search Mode:', 'wp-text-replacer'); ?></label>
                    <select name="wptr_plugin_options[search_mode]">
                        <option value="onload" <?php selected($search_mode, 'onload'); ?>>
                            <?php echo esc_html__('Once on page load', 'wp-text-replacer'); ?>
                        </option>
                        <option value="continuous" <?php selected($search_mode, 'continuous'); ?>>
                            <?php echo esc_html__('Continuous search', 'wp-text-replacer'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="wptr-form-group">
                    <label><?php echo esc_html__('Number of iterations (for continuous mode):', 'wp-text-replacer'); ?></label>
                    <input type="number" name="wptr_plugin_options[search_iterations]" 
                           min="1" value="<?php echo esc_attr($search_iterations); ?>" />
                </div>
            </div>
            
            <?php submit_button('Save Changes', 'wptr-btn'); ?>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        let ruleIndex = <?php echo count($rules); ?>;
        
        $('#add-rule').on('click', function() {
            const newRule = `
                <div class="wptr-rule">
                    <div class="wptr-form-group">
                        <label>${WPTextReplacerSettings.translations.searchPlaceholder}:</label>
                        <input type="text" name="wptr_plugin_options[rules][${ruleIndex}][search]" />
                    </div>
                    <div class="wptr-form-group">
                        <label>${WPTextReplacerSettings.translations.replacePlaceholder}:</label>
                        <input type="text" name="wptr_plugin_options[rules][${ruleIndex}][replace]" />
                    </div>
                    <button type="button" class="wptr-btn wptr-btn-danger remove-rule">
                        <?php echo esc_html__('Delete', 'wp-text-replacer'); ?>
                    </button>
                </div>
            `;
            
            $('#replacement-rules-container').append(newRule);
            ruleIndex++;
        });
        
        $(document).on('click', '.remove-rule', function() {
            $(this).closest('.wptr-rule').remove();
        });
    });
    </script>
    <?php
} 
<?php
/**
 * Plugin Name: Squirrel
 * Plugin URI: https://kindleman.com.au/squirrel
 * Description: Wordpress Logging and debug.
 * Version: 1.0.0
 * Author: Kindleman 
 * Author URI: https://kindleman.com.au
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: squirrel-plugin
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants with uppercase names
define('SQUIRREL_VERSION', '1.0.0');
define('SQUIRREL_DIR', plugin_dir_path(__FILE__));
define('SQUIRREL_URL', plugin_dir_url(__FILE__));

class Squirrel {
    /**
     * Initialize the plugin
     */
    public function init() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Load admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));      
         add_action('admin_init', array($this, 'handle_cache_clearing'));

      

         add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'squirrel_settings',    // Option group
            'squirrel_options',     // Option name
            array(                  
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        // Register sections and fields
        $this->setup_settings_sections();
    }
    
    /**
     * Setup settings sections and fields
     */
    private function setup_settings_sections() {
    
      // Add Cache Management section
    add_settings_section(
        'squirrel_cache_section',
        __('Cache Management', 'squirrel-plugin'),
        array($this, 'cache_section_callback'),
        'squirrel-settings'
    );

    // Add Clear Caches field
    add_settings_field(
        'clear_caches',
        __('Clear Caches', 'squirrel-plugin'),
        array($this, 'clear_caches_callback'),
        'squirrel-settings',
        'squirrel_cache_section'
    );

    // Add Debug Settings section
    add_settings_section(
        'squirrel_debug_section',
        __('Cache Bust Configuration', 'squirrel-plugin'),
        array($this, 'debug_section_callback'),
        'squirrel-settings'
    );

   

 

    // Add API Key field
    add_settings_field(
        'squirrel_api_key',
        __('API Key', 'squirrel-plugin'),
        array($this, 'api_key_field_callback'),
        'squirrel-settings',
        'squirrel_api_section'
    );

    // Add Developer Settings section
    add_settings_section(
        'squirrel_developer_section',
        __('Developer Settings', 'squirrel-plugin'),
        array($this, 'developer_section_callback'),
        'squirrel-settings'
    );


    
}

/**
 * Cache Section callback
 */
public function cache_section_callback() {
    echo '<p>' . __('Clear various caches with one click.', 'squirrel-plugin') . '</p>';
}

/**
 * Clear Caches button callback
 */
public function clear_caches_callback() {
    // Get current URL without any cache-clearing parameters
    $base_url = remove_query_arg(array(
        'cleartimber',
        'cleartransients',
        'clearobjectcache',
        'clearwoocache',
        'clearwprocket',
        'clearbrowsercache'
    ));
    ?>
    <div class="clear-caches-wrapper">
        <?php if (class_exists('Timber')): ?>
        <p>
            <a href="<?php echo esc_url(add_query_arg('cleartimber', '1', $base_url)); ?>" 
               class="button button-secondary">
                <?php _e('Clear Timber Cache', 'squirrel-plugin'); ?>
            </a>
        </p>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo esc_url(add_query_arg('cleartransients', '1', $base_url)); ?>" 
               class="button button-secondary">
                <?php _e('Clear All Transients', 'squirrel-plugin'); ?>
            </a>
        </p>
        
        <p>
            <a href="<?php echo esc_url(add_query_arg('clearobjectcache', '1', $base_url)); ?>" 
               class="button button-secondary">
                <?php _e('Clear Object Cache', 'squirrel-plugin'); ?>
            </a>
        </p>
        
        <?php if (function_exists('wc_delete_product_transients')): ?>
        <p>
            <a href="<?php echo esc_url(add_query_arg('clearwoocache', '1', $base_url)); ?>" 
               class="button button-secondary">
                <?php _e('Clear WooCommerce Cache', 'squirrel-plugin'); ?>
            </a>
        </p>
        <?php endif; ?>
        
        <?php if (function_exists('rocket_clean_domain')): ?>
        <p>
            <a href="<?php echo esc_url(add_query_arg('clearwprocket', '1', $base_url)); ?>" 
               class="button button-secondary">
                <?php _e('Clear WP Rocket Cache', 'squirrel-plugin'); ?>
            </a>
        </p>
        <?php endif; ?>
        
        <?php $this->maybe_show_cache_clear_messages(); ?>
    </div>
    <?php
}

/**
 * Maybe show cache clear status messages
 */
private function maybe_show_cache_clear_messages() {
    if (isset($_GET['cleartimber']) && $_GET['cleartimber']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('Timber cache cleared.', 'squirrel-plugin');
        echo '</p></div>';
    }
    
    if (isset($_GET['cleartransients']) && $_GET['cleartransients']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('All transients cleared.', 'squirrel-plugin');
        echo '</p></div>';
    }
    
    if (isset($_GET['clearobjectcache']) && $_GET['clearobjectcache']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('Object cache flushed.', 'squirrel-plugin');
        echo '</p></div>';
    }
    
    if (isset($_GET['clearwoocache']) && $_GET['clearwoocache']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('WooCommerce transients cleared.', 'squirrel-plugin');
        echo '</p></div>';
    }
    
    if (isset($_GET['clearwprocket']) && $_GET['clearwprocket']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('WP Rocket cache cleared.', 'squirrel-plugin');
        echo '</p></div>';
    }
    
    if (isset($_GET['clearbrowsercache']) && $_GET['clearbrowsercache']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('Browser cache headers reset.', 'squirrel-plugin');
        echo '</p></div>';
    }
    
    // Additional cache types can be added here following the same pattern
}



/**
 * Handle cache clearing requests
 */
public function handle_cache_clearing() {
    // Only process if we're on our settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'squirrel-settings') {
        return;
    }
    
    // Clear Timber cache if requested
    if (isset($_GET['cleartimber']) && $_GET['cleartimber'] && class_exists('Timber')) {
        add_filter('timber/cache/mode', function() { return 'none'; });
        
        if (method_exists('Timber\Loader', 'clear_cache_timber')) {
            $loader = new Timber\Loader();
            $loader->clear_cache_timber();
        }
    }
    
    // Clear transients if requested
    if (isset($_GET['cleartransients']) && $_GET['cleartransients']) {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_site_transient_%'");        
    }
 

    // Object Cache
    if (isset($_GET['clearobjectcache'])) {
        wp_cache_flush();
        add_settings_error('squirrel_messages', 'squirrel_message', 
            __('Object cache cleared', 'squirrel-plugin'), 'success');
    }

    // WooCommerce
    if (isset($_GET['clearwoocache']) && function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients();
        add_settings_error('squirrel_messages', 'squirrel_message', 
            __('WooCommerce transients cleared', 'squirrel-plugin'), 'success');
    }

    // WP Rocket
    if (isset($_GET['clearwprocket']) && function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
        add_settings_error('squirrel_messages', 'squirrel_message', 
            __('WP Rocket cache cleared', 'squirrel-plugin'), 'success');
    }
}
    
   


 

    
    /**
     * Add admin menu item under Settings
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            __('Squirrel Settings', 'squirrel-plugin'),
            __('Squirrel', 'squirrel-plugin'),
            'manage_options',
            'squirrel-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render settings page
     */
  public function render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form action="options.php" method="post">
            <?php
            settings_fields('squirrel_settings');
            do_settings_sections('squirrel-settings');
            submit_button('Save Settings');
            ?>
        </form>
        
        <?php $this->debug_log_section_callback(); ?>
    </div>
    <?php
}
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_squirrel-settings') {
            return;
        }
        
        wp_enqueue_style(
            'squirrel-plugin-admin',
            SQUIRREL_URL . 'assets/css/admin.css',
            array(),
            SQUIRREL_VERSION
        );
        
        wp_enqueue_script(
            'squirrel-plugin-admin',
            SQUIRREL_URL . 'assets/js/admin.js',
            array('jquery'),
            SQUIRREL_VERSION,
            true
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
      $sanitized = array();
      
      // Sanitize API key
      if (isset($input['api_key'])) {
          $sanitized['api_key'] = sanitize_text_field(trim($input['api_key']));
      }
      
      // Sanitize debug log size
      if (isset($input['debug_log_max_size'])) {
          $allowed_sizes = array('1kb', '1mb', '1gb');
          $sanitized['debug_log_max_size'] = in_array($input['debug_log_max_size'], $allowed_sizes) 
              ? $input['debug_log_max_size'] 
              : '1mb';
      }
      
      // Sanitize developer email
      if (isset($input['developer_email'])) {
          $sanitized['developer_email'] = sanitize_email(trim($input['developer_email']));
      }
      
      return $sanitized;
  }

    /**
     * Debug Log Size field callback
     */
    public function debug_log_size_callback() {
        $options = get_option('squirrel_options');
        $current_size = isset($options['debug_log_max_size']) ? $options['debug_log_max_size'] : '1mb';
        ?>
        <select id="debug_log_max_size" name="squirrel_options[debug_log_max_size]" class="regular-text">
            <option value="1kb" <?php selected($current_size, '1kb'); ?>><?php _e('1 KB', 'squirrel-plugin'); ?></option>
            <option value="1mb" <?php selected($current_size, '1mb'); ?>><?php _e('1 MB', 'squirrel-plugin'); ?></option>
            <option value="1gb" <?php selected($current_size, '1gb'); ?>><?php _e('1 GB', 'squirrel-plugin'); ?></option>
        </select>
        <p class="description">
            <?php _e('Maximum allowed size for debug.log file before rotation.', 'squirrel-plugin'); ?>
        </p>
        <?php
    }
    /**
     * Developer Section callback
     */
    public function developer_section_callback() {
        echo '<p>' . __('Configure developer notifications and alerts.', 'squirrel-plugin') . '</p>';
    }




/**
 * Show admin notice when log was recently cleared
 */
public function admin_notices() {
    $cleanup_info = get_option('squirrel_last_cleanup');
    if (!$cleanup_info || (time() - strtotime($cleanup_info['time']) > 3600)) {
        return;
    }
    
    $size = size_format($cleanup_info['size']);
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <?php printf(
                __('Squirrel: debug.log was automatically cleared at %1$s. The file had reached %2$s in size.', 'squirrel-plugin'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cleanup_info['time'])),
                $size
            ); ?>
        </p>
        <details style="margin-top:10px;">
            <summary><?php _e('Show last 10 lines', 'squirrel-plugin'); ?></summary>
            <pre style="background:#f6f7f7;padding:10px;overflow:auto;"><?php 
                echo esc_html(implode("\n", array_slice(explode("\n", $cleanup_info['last_lines']), -10)));
            ?></pre>
        </details>
    </div>
    <?php
    // Clear the transient after displaying
    delete_option('squirrel_last_cleanup');
}




}

// Initialize the plugin
if (class_exists('Squirrel')) {
    $squirrel = new Squirrel();
    $squirrel->init();
}
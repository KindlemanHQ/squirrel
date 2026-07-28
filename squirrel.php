<?php
/**
 * Plugin Name: Squirrel
 * Plugin URI: https://kindleman.com.au/squirrel
 * Description: Wordpress Cache Buster.
 * Version: 1.0.1
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
define('SQUIRREL_VERSION', '1.0.1');
define('SQUIRREL_DIR', plugin_dir_path(__FILE__));
define('SQUIRREL_URL', plugin_dir_url(__FILE__));

// Sucuri auto cache-purge settings
define('SQUIRREL_LOG_OPTION', 'squirrel_activity_log');
define('SQUIRREL_LAST_PURGE_TRANSIENT', 'squirrel_last_purge');
define('SQUIRREL_PURGE_RATE_LIMIT', 5 * MINUTE_IN_SECONDS);
define('SQUIRREL_WATCHDOG_HOOK', 'squirrel_watchdog_check');
define('SQUIRREL_WATCHDOG_SCHEDULE', 'squirrel_fifteen_minutes');
// Fallback only, used when no per-site value has been saved in Settings > Squirrel.
// butterfly.org.au's compiled style.css is ~186KB uncompressed; default sits
// well below that (with headroom for edits) but far above a truncated response.
define('SQUIRREL_CSS_MIN_BYTES_DEFAULT', 100000);

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

        // Sucuri auto cache-purge triggers
        add_action('upgrader_process_complete', array($this, 'on_upgrader_process_complete'), 10, 2);
        add_action('breeze_after_clear_cache', array($this, 'on_breeze_cache_cleared'));
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        add_action(SQUIRREL_WATCHDOG_HOOK, array($this, 'watchdog_check'));
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

 

    // Add API Settings section
    add_settings_section(
        'squirrel_api_section',
        __('Settings', 'squirrel-plugin'),
        array($this, 'api_section_callback'),
        'squirrel-settings'
    );

    // Add Sucuri API Key field
    add_settings_field(
        'sucuri_api_key',
        __('Sucuri API Key', 'squirrel-plugin'),
        array($this, 'sucuri_api_key_field_callback'),
        'squirrel-settings',
        'squirrel_api_section'
    );

    // Add Sucuri Site field
    add_settings_field(
        'sucuri_site',
        __('Sucuri Site key', 'squirrel-plugin'),
        array($this, 'sucuri_site_field_callback'),
        'squirrel-settings',
        'squirrel_api_section'
    );

    // Add Watchdog section
    add_settings_section(
        'squirrel_watchdog_section',
        __('Sucuri Auto Cache-Purge', 'squirrel-plugin'),
        array($this, 'watchdog_section_callback'),
        'squirrel-settings'
    );

    // Add CSS minimum size field
    add_settings_field(
        'sucuri_css_min_bytes',
        __('Minimum Stylesheet Size (bytes)', 'squirrel-plugin'),
        array($this, 'sucuri_css_min_bytes_field_callback'),
        'squirrel-settings',
        'squirrel_watchdog_section'
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
        'clearsucuri',
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

        <?php 
        $options = get_option('squirrel_options');
        if (!empty($options['sucuri_api_key'])): 
            $sucuri_url = 'https://waf.sucuri.net/api?k=' . urlencode($options['sucuri_api_key']) . '&s=' . urlencode($options['sucuri_site']) . '&a=clearcache';
        ?>
        <p>
            <a href="<?php echo esc_url($sucuri_url); ?>" 
               class="button button-secondary"
               target="_blank">
                <?php _e('Clear Sucuri Cache', 'squirrel-plugin'); ?>
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
    
    if (isset($_GET['clearsucuri']) && $_GET['clearsucuri']) {
        echo '<div class="notice notice-success inline"><p>';
        _e('Sucuri cache cleared.', 'squirrel-plugin');
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
 * API Section callback
 */
public function api_section_callback() {
    echo '<p>' . __('Configure your API connection settings.', 'squirrel-plugin') . '</p>';
}

/**
 * Sucuri API Key field callback
 */
public function sucuri_api_key_field_callback() {
    $options = get_option('squirrel_options');
    $api_key = isset($options['sucuri_api_key']) ? esc_attr($options['sucuri_api_key']) : '';
    ?>
    <input type="text" 
           id="sucuri_api_key" 
           name="squirrel_options[sucuri_api_key]" 
           value="<?php echo $api_key; ?>" 
           class="regular-text">
    <p class="description">
        <?php _e('Enter your Sucuri API key for cache busting of the firewall.', 'squirrel-plugin'); ?>
    </p>
    <?php
}

/**
 * Sucuri Site field callback
 */
public function sucuri_site_field_callback() {
    $options = get_option('squirrel_options');
    $site = isset($options['sucuri_site']) ? esc_attr($options['sucuri_site']) : '';
    ?>
    <input type="text"
           id="sucuri_site"
           name="squirrel_options[sucuri_site]"
           value="<?php echo $site; ?>"
           class="regular-text"
           placeholder="eg 123456789abcdefghijklmnopqrstuvw">
    <p class="description">
        <?php _e('Enter the Sucuri site key.  Should be a 32 character long string', 'squirrel-plugin'); ?>
    </p>
    <?php
}

/**
 * Watchdog Section callback
 */
public function watchdog_section_callback() {
    echo '<p>' . __('Sucuri cache is purged automatically after WordPress updates and Breeze cache clears, and checked every 15 minutes by a self-healing watchdog. Configure the watchdog below.', 'squirrel-plugin') . '</p>';
}

/**
 * Sucuri CSS minimum size field callback
 */
public function sucuri_css_min_bytes_field_callback() {
    $options = get_option('squirrel_options');
    $min_bytes = isset($options['sucuri_css_min_bytes']) && $options['sucuri_css_min_bytes'] !== ''
        ? absint($options['sucuri_css_min_bytes'])
        : SQUIRREL_CSS_MIN_BYTES_DEFAULT;
    ?>
    <input type="number"
           id="sucuri_css_min_bytes"
           name="squirrel_options[sucuri_css_min_bytes]"
           value="<?php echo esc_attr($min_bytes); ?>"
           class="small-text"
           min="0"
           step="1">
    <p class="description">
        <?php _e('The watchdog fetches the theme stylesheet directly; a 200 response smaller than this (bytes) is treated as broken and triggers a Sucuri cache purge. Set this a little below your theme\'s real compiled stylesheet size.', 'squirrel-plugin'); ?>
    </p>
    <?php
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

        <?php $this->render_activity_log(); ?>

    </div>
    <?php
}

/**
 * Render a read-only view of the Squirrel activity log (newest first).
 */
private function render_activity_log() {
    $log = array_reverse(get_option(SQUIRREL_LOG_OPTION, array()));
    ?>
    <h2><?php _e('Activity Log', 'squirrel-plugin'); ?></h2>
    <?php if (empty($log)): ?>
        <p><?php _e('No activity logged yet.', 'squirrel-plugin'); ?></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'squirrel-plugin'); ?></th>
                    <th><?php _e('Message', 'squirrel-plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $entry): ?>
                <tr>
                    <td><?php echo esc_html($entry['time']); ?></td>
                    <td><?php echo esc_html($entry['message']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
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
      
      // Sanitize Sucuri API key
      if (isset($input['sucuri_api_key'])) {
          $sanitized['sucuri_api_key'] = sanitize_text_field(trim($input['sucuri_api_key']));
      }
      
      // Sanitize Sucuri site (domain only, strip protocol and trailing slash)
      if (isset($input['sucuri_site'])) {
          $sanitized['sucuri_site'] = sanitize_text_field(trim($input['sucuri_site']));
      }

      // Sanitize watchdog CSS minimum size (bytes)
      if (isset($input['sucuri_css_min_bytes']) && $input['sucuri_css_min_bytes'] !== '') {
          $sanitized['sucuri_css_min_bytes'] = absint($input['sucuri_css_min_bytes']);
      }

      $this->sync_watchdog_schedule($sanitized);

      return $sanitized;
  }

    
    /**
     * Developer Section callback
     */
    public function developer_section_callback() {
        echo '<p>' . __('Configure developer notifications and alerts.', 'squirrel-plugin') . '</p>';
    }

    /**
     * Append a message to Squirrel's activity log (capped, non-autoloaded option)
     * and mirror it to the PHP error log.
     */
    public function log($message) {
        $log = get_option(SQUIRREL_LOG_OPTION, array());
        $log[] = array(
            'time'    => current_time('mysql'),
            'message' => $message,
        );

        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        update_option(SQUIRREL_LOG_OPTION, $log, false);

        error_log('[Squirrel] ' . $message);
    }

    /**
     * Fire a Sucuri edge cache purge, rate-limited so multiple triggers
     * firing close together only result in one request.
     */
    public function purge_sucuri_cache($reason = '') {
        $options = get_option('squirrel_options');
        $key = isset($options['sucuri_api_key']) ? $options['sucuri_api_key'] : '';
        $site = isset($options['sucuri_site']) ? $options['sucuri_site'] : '';

        if (empty($key) || empty($site)) {
            $this->log(sprintf('Sucuri purge skipped (%s): API key/site not configured.', $reason));
            return false;
        }

        if (get_transient(SQUIRREL_LAST_PURGE_TRANSIENT)) {
            $this->log(sprintf('Sucuri purge skipped (%s): rate limited.', $reason));
            return false;
        }

        set_transient(SQUIRREL_LAST_PURGE_TRANSIENT, time(), SQUIRREL_PURGE_RATE_LIMIT);

        $url = 'https://waf.sucuri.net/api?k=' . urlencode($key) . '&s=' . urlencode($site) . '&a=clearcache';

        $response = wp_remote_get($url, array(
            'timeout'  => 10,
            'blocking' => true,
        ));

        if (is_wp_error($response)) {
            $this->log(sprintf('Sucuri purge failed (%s): %s', $reason, $response->get_error_message()));
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $this->log(sprintf('Sucuri purge triggered (%s): HTTP %s', $reason, $code));

        return $code === 200;
    }

    /**
     * Purge after any plugin/theme/core update completes.
     */
    public function on_upgrader_process_complete($upgrader, $hook_extra) {
        $this->purge_sucuri_cache('wp_update');
    }

    /**
     * Purge after Breeze clears its own local page/asset cache.
     */
    public function on_breeze_cache_cleared() {
        $this->purge_sucuri_cache('breeze_cache_clear');
    }

    /**
     * Register a 15-minute cron schedule for the watchdog.
     */
    public function add_cron_schedules($schedules) {
        if (!isset($schedules[SQUIRREL_WATCHDOG_SCHEDULE])) {
            $schedules[SQUIRREL_WATCHDOG_SCHEDULE] = array(
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __('Every 15 Minutes', 'squirrel-plugin'),
            );
        }
        return $schedules;
    }

    /**
     * Anonymously check the homepage and the theme stylesheet for signs of a
     * broken cached render, and purge Sucuri if either check fails.
     */
    public function watchdog_check() {
        $options = get_option('squirrel_options');

        $home_response = wp_remote_get(home_url('/'), array(
            'timeout' => 15,
            'cookies' => array(),
        ));

        if (is_wp_error($home_response)) {
            $this->log('Watchdog: failed to fetch homepage - ' . $home_response->get_error_message());
            $this->purge_sucuri_cache('watchdog_homepage_unreachable');
            return;
        }

        $body = wp_remote_retrieve_body($home_response);
        $stylesheet_url = get_stylesheet_uri();
        $stylesheet_filename = basename(wp_parse_url($stylesheet_url, PHP_URL_PATH));

        if (empty($body) || strpos($body, $stylesheet_filename) === false) {
            $this->log(sprintf('Watchdog: homepage missing stylesheet reference (%s).', $stylesheet_filename));
            $this->purge_sucuri_cache('watchdog_missing_stylesheet_link');
            return;
        }

        $css_response = wp_remote_get($stylesheet_url, array(
            'timeout' => 15,
            'cookies' => array(),
        ));

        if (is_wp_error($css_response)) {
            $this->log('Watchdog: failed to fetch stylesheet - ' . $css_response->get_error_message());
            $this->purge_sucuri_cache('watchdog_stylesheet_unreachable');
            return;
        }

        $min_bytes = isset($options['sucuri_css_min_bytes']) && $options['sucuri_css_min_bytes'] !== ''
            ? absint($options['sucuri_css_min_bytes'])
            : SQUIRREL_CSS_MIN_BYTES_DEFAULT;

        $css_code = wp_remote_retrieve_response_code($css_response);
        $css_size = strlen(wp_remote_retrieve_body($css_response));

        if ((int) $css_code !== 200 || $css_size < $min_bytes) {
            $this->log(sprintf('Watchdog: stylesheet check failed (HTTP %s, %d bytes, min %d).', $css_code, $css_size, $min_bytes));
            $this->purge_sucuri_cache('watchdog_broken_stylesheet');
            return;
        }

        $this->log('Watchdog: homepage and stylesheet check passed.');
    }

    /**
     * Plugin activation: schedule the watchdog cron event.
     */
    public function activate() {
        $this->sync_watchdog_schedule(get_option('squirrel_options'));
    }

    /**
     * Schedule or unschedule the watchdog cron event to match whether Sucuri
     * credentials are configured, so sites without Sucuri never run it at all.
     */
    private function sync_watchdog_schedule($options) {
        $configured = !empty($options['sucuri_api_key']) && !empty($options['sucuri_site']);
        $scheduled = wp_next_scheduled(SQUIRREL_WATCHDOG_HOOK);

        if ($configured && !$scheduled) {
            wp_schedule_event(time(), SQUIRREL_WATCHDOG_SCHEDULE, SQUIRREL_WATCHDOG_HOOK);
        } elseif (!$configured && $scheduled) {
            wp_unschedule_event($scheduled, SQUIRREL_WATCHDOG_HOOK);
        }
    }

    /**
     * Plugin deactivation: clear the watchdog cron event.
     */
    public function deactivate() {
        $timestamp = wp_next_scheduled(SQUIRREL_WATCHDOG_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, SQUIRREL_WATCHDOG_HOOK);
        }
    }






}

// Initialize the plugin
if (class_exists('Squirrel')) {
    $squirrel = new Squirrel();
    $squirrel->init();
    register_activation_hook(__FILE__, array($squirrel, 'activate'));
    register_deactivation_hook(__FILE__, array($squirrel, 'deactivate'));
}

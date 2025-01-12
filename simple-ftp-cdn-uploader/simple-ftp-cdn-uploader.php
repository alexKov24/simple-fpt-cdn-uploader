<?php

/**
 * Plugin Name: Simple FTP CDN Uploader
 * Description: Upload media files to CDN via FTP with admin configuration
 * Version: 1.0
 * Author: Alex Kovalev
 * Author URI: https://github.com/alexKov24/
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SimpleFtpCdnUploader
{
    private $options;
    private $default_options = [
        'ftp_server' => '',
        'ftp_user' => '',
        'ftp_pass' => '',
        'cdn_url' => '',
        'cdn_base_path' => '',
        'cdn_prefix' => '',
        'file_types' => ['mp4'],
        'delete_local' => false
    ];
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_test_ftp_connection', [$this, 'testFtpConnection']);
        // Load options
        $saved_options = get_option('simple_ftp_cdn_settings', []);
        $this->options = wp_parse_args($saved_options, $this->default_options);

        // Initialize hooks
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('add_attachment', [$this, 'uploadToCdn']);
        add_filter('wp_get_attachment_url', [$this, 'modifyAttachmentUrl'], 10, 2);
        add_filter('manage_media_columns', [$this, 'addCdnColumn']);
        add_action('manage_media_custom_column', [$this, 'displayCdnColumn'], 10, 2);
        add_action('admin_notices', [$this, 'displayAdminNotices']);
    }

    public function testFtpConnection()
    {
        check_ajax_referer('test_ftp_connection');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        $ftp_connection = @ftp_connect($this->options['ftp_server'], 21, 10);

        if (!$ftp_connection) {
            wp_send_json_error('Could not connect to FTP server');
        }

        if (!@ftp_login($ftp_connection, $this->options['ftp_user'], $this->options['ftp_pass'])) {
            ftp_close($ftp_connection);
            wp_send_json_error('Login failed');
        }

        // Try to set passive mode
        if (!@ftp_pasv($ftp_connection, true)) {
            ftp_close($ftp_connection);
            wp_send_json_error('Could not set passive mode');
        }

        // Try to change to the base directory
        if (!@ftp_chdir($ftp_connection, $this->options['cdn_base_path'])) {
            ftp_close($ftp_connection);
            wp_send_json_error('Could not change to base directory');
        }

        ftp_close($ftp_connection);
        wp_send_json_success('Connection successful');
    }

    public function addAdminMenu()
    {
        add_options_page(
            'FTP CDN Settings',
            'FTP CDN',
            'manage_options',
            'simple-ftp-cdn',
            [$this, 'displaySettingsPage']
        );
    }

    public function registerSettings()
    {
        register_setting('simple_ftp_cdn_settings', 'simple_ftp_cdn_settings', [$this, 'sanitizeSettings']);
    }
    public function sanitizeSettings($input)
    {
        $sanitized = [];

        foreach ($this->default_options as $key => $default) {
            if ($key === 'file_types') {
                // Handle file types array
                $types = isset($input['file_types']) ? $input['file_types'] : '';
                $types = array_map('trim', explode(',', $types));
                $types = array_filter($types); // Remove empty values
                $sanitized['file_types'] = !empty($types) ? $types : ['mp4'];
            } elseif ($key === 'delete_local') {
                // Sanitize checkbox
                $sanitized['delete_local'] = isset($input['delete_local']) ? true : false;
            } else {
                // Handle other string fields
                $sanitized[$key] = isset($input[$key]) ?
                    sanitize_text_field($input[$key]) :
                    $default;
            }
        }

        return $sanitized;
    }

    public function displaySettingsPage()
    {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
?>
        <div class="wrap">
            <h2>FTP CDN Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('simple_ftp_cdn_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th>FTP Server</th>
                        <td>
                            <input type="text" name="simple_ftp_cdn_settings[ftp_server]"
                                value="<?php echo esc_attr($this->options['ftp_server']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>FTP Username</th>
                        <td>
                            <input type="text" name="simple_ftp_cdn_settings[ftp_user]"
                                value="<?php echo esc_attr($this->options['ftp_user']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>FTP Password</th>
                        <td>
                            <input type="password" name="simple_ftp_cdn_settings[ftp_pass]"
                                value="<?php echo esc_attr($this->options['ftp_pass']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>CDN URL</th>
                        <td>
                            <input type="text" name="simple_ftp_cdn_settings[cdn_url]"
                                value="<?php echo esc_attr($this->options['cdn_url']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>CDN Base Path</th>
                        <td>
                            <input type="text" name="simple_ftp_cdn_settings[cdn_base_path]"
                                value="<?php echo esc_attr($this->options['cdn_base_path']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>CDN Prefix (e.g., shapira)</th>
                        <td>
                            <input type="text" name="simple_ftp_cdn_settings[cdn_prefix]"
                                value="<?php echo esc_attr($this->options['cdn_prefix']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>File Types</th>
                        <td>
                            <input type="text" name="simple_ftp_cdn_settings[file_types]"
                                value="<?php echo esc_attr(implode(',', $this->options['file_types'])); ?>"
                                class="regular-text">
                            <p class="description">Comma-separated list of file extensions (e.g., mp4,mov,avi)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>File Management</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="simple_ftp_cdn_settings[delete_local]"
                                    value="1"
                                    <?php checked($this->options['delete_local'], 1); ?>>
                                Delete local files after successful CDN upload
                            </label>
                            <p class="description">Warning: This will remove files from your server after they are uploaded to CDN</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <div class="card">
                <h3>Test Connection</h3>
                <p>
                    <button class="button" id="test-ftp-connection">Test FTP Connection</button>
                    <span id="connection-result"></span>
                </p>
            </div>
            <script>
                jQuery(document).ready(function($) {
                    $('#test-ftp-connection').on('click', function() {
                        var button = $(this);
                        var resultSpan = $('#connection-result');

                        button.prop('disabled', true);
                        resultSpan.html('<span style="color: blue;">Testing connection...</span>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'test_ftp_connection',
                                nonce: '<?php echo wp_create_nonce('test_ftp_connection'); ?>',
                                _ajax_nonce: '<?php echo wp_create_nonce('test_ftp_connection'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    resultSpan.html('<span style="color: green;">✓ Connection successful!</span>');
                                } else {
                                    resultSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                console.error('Ajax error:', {
                                    status: textStatus,
                                    error: errorThrown,
                                    response: jqXHR.responseText
                                });
                                resultSpan.html('<span style="color: red;">✗ Ajax request failed - ' + errorThrown + '</span>');
                            },
                            complete: function() {
                                button.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
        </div>
<?php
    }

    public function uploadToCdn($post_id)
    {
        $file = get_attached_file($post_id);
        $file_name = basename($file);

        // Check file type
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->options['file_types'])) {
            return;
        }

        // Create new filename
        $new_filename = pathinfo($file_name, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

        // Set up remote path
        $remote_path = $this->options['cdn_base_path'] . '/' .
            str_replace(wp_upload_dir()['basedir'] . '/', '', $file);
        $remote_path = str_replace($file_name, $new_filename, $remote_path);

        // Connect to FTP
        $ftp_connection = @ftp_connect($this->options['ftp_server'], 21, 10);
        if (!$ftp_connection || !@ftp_login($ftp_connection, $this->options['ftp_user'], $this->options['ftp_pass'])) {
            update_post_meta($post_id, 'cdn_upload_error', 'FTP connection failed');
            return;
        }

        ftp_pasv($ftp_connection, true);
        if (!@ftp_put($ftp_connection, $remote_path, $file, FTP_BINARY)) {
            update_post_meta($post_id, 'cdn_upload_error', 'Upload failed');
            ftp_close($ftp_connection);
            return;
        }

        // Store CDN info
        $cdn_url = $this->options['cdn_url'] . $this->options['cdn_prefix'] . '/' . $remote_path;
        update_post_meta($post_id, 'cdn_url', $cdn_url);
        update_post_meta($post_id, 'cdn_filename', $new_filename);
        delete_post_meta($post_id, 'cdn_upload_error');

        if ($this->options['delete_local']) {
            @unlink($file);
        }

        ftp_close($ftp_connection);
    }

    public function modifyAttachmentUrl($url, $post_id)
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->options['file_types'])) {
            return $url;
        }

        $cdn_url = get_post_meta($post_id, 'cdn_url', true);
        return !empty($cdn_url) ? $cdn_url : $url;
    }

    public function addCdnColumn($columns)
    {
        $columns['cdn_status'] = 'CDN Status';
        return $columns;
    }

    public function displayCdnColumn($column_name, $post_id)
    {
        if ($column_name !== 'cdn_status') {
            return;
        }

        $cdn_url = get_post_meta($post_id, 'cdn_url', true);
        $error = get_post_meta($post_id, 'cdn_upload_error', true);

        if (!empty($cdn_url)) {
            echo '<span style="color: green;">✓ On CDN</span>';
        } elseif (!empty($error)) {
            echo '<span style="color: red;">✗ ' . esc_html($error) . '</span>';
        }
    }

    public function displayAdminNotices()
    {
        if (!$this->options['ftp_server'] || !$this->options['ftp_user'] || !$this->options['ftp_pass']) {
            echo '<div class="notice notice-warning"><p>FTP CDN Uploader: Please configure your FTP settings.</p></div>';
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', ['SimpleFtpCdnUploader', 'getInstance']);

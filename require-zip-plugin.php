<?php
/**
 * Plugin name: Require Zip Plugin
 * Description: Warns users that a plugin is required and downloads it from a ZIP URL. The warning will be displayed until the plugin is activated.
 * Version: 1.0
 * Author: Cau Guanabara
 * Author URI: mailto:cauguanabara@gmail.com
 * License: Wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequireZipPlugin {

    public $required = [];

    public function __construct() {
        $this->me_first();
        add_action('admin_notices', [$this, 'add_notices']);
    }

    /**
     * Add required plugin to queue
     *
     * @param [type] $dependent     Name of the script that are requiring
     * @param [type] $required      Name of the requested plugin
     * @param [type] $zip_url       URL to the ZIP file
     * @param [type] $plugin_id     Plugin ID (plugin_directory_name/plugin_file_name.php)
     * @return void
     */
    public function require($dependent, $required, $zip_url, $plugin_id) {
        $this->required[] = compact('dependent', 'required', 'zip_url', 'plugin_id');
    }

    public function add_notices() {
        foreach ($this->required as $info) {
            $downloaded = false;
            if (!$this->check($info['plugin_id'])) {
                $downloaded = $this->download($info['zip_url'], $info['plugin_id']);
            }
            global $pagenow;
            if (!is_plugin_active($info['plugin_id'])) {
                print '<div class="notice notice-error"><p>';
                printf(__('<strong>%s</strong> depends on <strong>%s</strong> plugin', 'vuewp'), $info['dependent'], $info['required']);
                if ($pagenow == 'plugins.php') {
                    if ($downloaded) {
                        printf(__('.<br>We have downloaded <strong>%s</strong> for you, but since you are on the plugins page, you must %sreload the page%s to see it.', 'vuewp'), $info['required'], '<a href="javascript:location.reload()">', '</a>');
                    } else {
                        _e(', please activate it.', 'vuewp');
                    }
                } else {
                    if ($downloaded) {
                        printf(__('.<br>We have downloaded <strong>%s</strong> for you, just activate it in %splugins page%s.'), $info['required'], '<a href="plugins.php">', '</a>');
                    } else {
                        printf(__(', please activate it in %splugins page%s.'), '<a href="plugins.php">', '</a>');
                    }
                }
                print '</p></div>';
            }
        }
    }

    private function check($plugin_id) {
        return file_exists(ABSPATH . "/wp-content/plugins/{$plugin_id}");
    }

    private function download($zip_url, $plugin_id) {
        $dir_name = explode("/", $plugin_id)[0];
        $parts = explode("/", $zip_url);
        $name = $parts[count($parts) - 1];
        $local_file = ABSPATH . "wp-content/uploads/{$name}";
        $wp_filesystem = filesystem();
    
        $data = wp_remote_get($zip_url);
        $zip = $data['body'];
        $wp_filesystem->put_contents($local_file, $zip);
    
        $unzip_dir = ABSPATH . "wp-content/uploads/{$dir_name}/";
        if (unzip_file($local_file, $unzip_dir)) {
            $dirs = array_values(array_diff(scandir($unzip_dir), ['.', '..']));
            $dir = $dirs[0];
            $wp_filesystem->move($unzip_dir . $dir, ABSPATH . "/wp-content/plugins/{$dir_name}");
            unlink($local_file);
            rmdir($unzip_dir);
            return true;
        } else {
            return false;
        }
    }

    public function me_first() {
        $plugins = get_option('active_plugins');
        $ind = array_search('require-zip-plugin/require-zip-plugin.php', $plugins);
        if ($ind) {
            unset($plugins[$ind]);
            array_unshift($plugins, 'require-zip-plugin/require-zip-plugin.php');
            update_option('active_plugins', $plugins);
        }
    }
}

global $require_zip_plugin;
$require_zip_plugin = new RequireZipPlugin();
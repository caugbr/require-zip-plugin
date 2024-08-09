<?php
/**
 * Plugin name: Require Zip Plugin
 * Description: Warns users that some plugin or theme is required and downloads it from a ZIP URL. The warning will be displayed until the plugin is active.
 * Version: 1.0
 * Author: Cau Guanabara
 * Author URI: mailto:cauguanabara@gmail.com
 * Text Domain: rzp
 * Domain Path: /langs/
 * License: Wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequireZipPlugin {

    public $required = [];
    public $strings = [];

    public function __construct() {
        $this->me_first();

        // For translators (valid for all strings below)
        // %1$s - requesting script
        // %2$s - required plugin/theme
        // %3$s - open link (if there is a link)
        // %4$s - close link (if there is a link)
        $this->strings['not_downloaded_not_plugins'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> plugin, please activate it in %3$splugins page%4$s.', 'rzp');
        $this->strings['downloaded_not_plugins'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> plugin.<br>We just downloaded <strong>%1$s</strong> for you, please activate it in %3$splugins page%4$s.', 'rzp');
        $this->strings['not_downloaded_plugins'] = __('<strong>%1$s</strong> depends on WP <strong>%2$s</strong> plugin, please activate it.', 'rzp');
        $this->strings['downloaded_plugins'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> plugin.<br>We just downloaded <strong>%1$s</strong> for you, but since you are in the plugins page, you must %3$sreload the page%4$s to see it.', 'rzp');
        $this->strings['not_downloaded_not_themes'] = __('<strong>%1$s</strong> depends on WP <strong>%2$s</strong> theme, please activate it in %3$sthemes page%4$s.', 'rzp');
        $this->strings['downloaded_not_themes'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> theme.<br>We just downloaded <strong>%1$s</strong> for you, please activate it in %3$sthemes page%4$s.', 'rzp');
        $this->strings['not_downloaded_themes'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> theme, please activate it.', 'rzp');
        $this->strings['downloaded_themes'] = __('<strong>%1$s</strong> depends on WP <strong>%2$s</strong> theme.<br>We just downloaded <strong>%1$s</strong> for you, but since you are in the themes page, you must %3$sreload the page%4$s to see it.', 'rzp');

        add_action('init', [$this, 'load_translations']);
        add_action('admin_notices', [$this, 'add_notices']);
    }
    
    /**
     * Add required plugin to queue
     *
     * @param [type] $dependent     Name of the script that are requiring
     * @param [type] $required      Name of the requested plugin/theme
     * @param [type] $zip_url       URL to the ZIP file
     * @param [type] $plugin_id     Plugin ID (plugin_directory_name/plugin_file_name.php or theme_directory_name/style.css)
     * @return void
     */
    public function require($dependent, $required, $zip_url, $plugin_id, $type = 'plugin') {
        $this->required[] = compact('dependent', 'required', 'zip_url', 'plugin_id', 'type');
    }
    
    public function load_translations() {
        load_plugin_textdomain('rzp', false, dirname(plugin_basename(__FILE__)) . '/langs');
    }

    private function check($item) {
        if ($item['type'] == 'theme') {
            return file_exists(ABSPATH . "/wp-content/themes/{$item['plugin_id']}");
        }
        return file_exists(ABSPATH . "/wp-content/plugins/{$item['plugin_id']}");
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

    public function add_notices() {
        foreach ($this->required as $info) {
            $this->{"add_{$info['type']}_notice"}($info);
        }
    }

    public function add_theme_notice($info) {
        $downloaded = false;
        if (!$this->check($info)) {
            $downloaded = $this->download($info);
        }
        global $pagenow;
        print "THEME: " . wp_get_theme() . "\n";
        if (wp_get_theme() != $info['required']) {
            $params = [$info['dependent'], $info['required']];
            print '<div class="notice notice-error"><p>';
            if ($pagenow == 'themes.php') {
                if ($downloaded) {
                    $params = array_merge($params, ['<a href="javascript:location.reload()">', '</a>']);
                    printf($this->strings['downloaded_themes'], ...$params);
                } else {
                    printf($this->strings['not_downloaded_themes'], ...$params);
                }
            } else {
                $params = array_merge($params, ['<a href="themes.php">', '</a>']);
                if ($downloaded) {
                    printf($this->strings['downloaded_not_themes'], ...$params);
                } else {
                    printf($this->strings['not_downloaded_not_themes'], ...$params);
                }
            }
            print '</p></div>';
        }
    }

    public function add_plugin_notice($info) {
        $downloaded = false;
        if (!$this->check($info)) {
            $downloaded = $this->download($info);
        }
        global $pagenow;
        if (!is_plugin_active($info['plugin_id'])) {
            $params = [$info['dependent'], $info['required']];
            print '<div class="notice notice-error"><p>';
            if ($pagenow == 'plugins.php') {
                if ($downloaded) {
                    $params = array_merge($params, ['<a href="javascript:location.reload()">', '</a>']);
                    printf($this->strings['downloaded_plugins'], ...$params);
                } else {
                    printf($this->strings['not_downloaded_plugins'], ...$params);
                }
            } else {
                $params = array_merge($params, ['<a href="plugins.php">', '</a>']);
                if ($downloaded) {
                    printf($this->strings['downloaded_not_plugins'], ...$params);
                } else {
                    printf($this->strings['not_downloaded_not_plugins'], ...$params);
                }
            }
            print '</p></div>';
        }
    }

    private function download($item) {
        $dir_name = explode("/", $item['plugin_id'])[0];
        $parts = explode("/", $item['zip_url']);
        $name = $parts[count($parts) - 1];
        $local_file = ABSPATH . "wp-content/uploads/{$name}";
        $wp_filesystem = filesystem();
    
        $data = wp_remote_get($item['zip_url']);
        $zip = $data['body'];
        $wp_filesystem->put_contents($local_file, $zip);
    
        $unzip_dir = ABSPATH . "wp-content/uploads/{$dir_name}/";
        if (unzip_file($local_file, $unzip_dir)) {
            $dirs = array_values(array_diff(scandir($unzip_dir), ['.', '..']));
            $dir = $dirs[0] ?? '';
            if (!empty($dir) && is_dir($unzip_dir . $dir)) {
                $wp_filesystem->move($unzip_dir . $dir, ABSPATH . "/wp-content/{$item['type']}s/{$dir_name}");
                unlink($local_file);
                rmdir($unzip_dir);
                return true;
            }
        }
        return false;
    }
}

global $require_zip_plugin;
$require_zip_plugin = new RequireZipPlugin();
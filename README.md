# Require ZIP plugin
Requires one or more plugins to be active. If some required plugin does not exist in the plugins folder, downloads and installs it from a URL that points to a ZIP file, such as the Github download URLs, for example.

### Require a plugin
To add some plugin to the required plugins queue, just use `$require_zip_plugin->require`. The plugin creates this global instance and you can use the `require` method, that takes four parameters:

- `$dependent` - Name of the requesting script
- `$required` - Name of the reuired plugin
- `$zip_url` - URL pointing to the ZIP file
- `$plugin_id` - ID of the required plugin after installed, as used internally by WP (folder_name/file_name.php)

### Example
The plugin **Inline Edit** depends on some functionalities from **WP Helper**, hosted in my Github. **Inline Edite** does not initialize if **WP Hepler** is not active and there will be a warn in administration until it is active.

    class InlineEdit {
        public function __construct() {
	        global $wp_helper;
	        global $require_zip_plugin;
	        if ($require_zip_plugin) {
	            $require_zip_plugin->require(
	                'Inline Edit', 
	                'WP Helper', 
	                'https://github.com/caugbr/wp-helper/archive/refs/heads/main.zip', 
	                'wp-helper/wp-helper.php'
	            );
	        }
	        if ($wp_helper) {
	            // ...
	        }
	    }

### The displayed message
First we tell the user that script X is dependent on plugin Y. If it doesn't exist we download it and tell them so. Then we ask them to activate the plugin.
You can change the messages in the translation file (../langs/rzp-[your-language-code].po), preserving the order of replacements (`%1$s`, `%2$s`, ...).

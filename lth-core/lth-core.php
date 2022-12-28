<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://http://lth90.42web.io/
 * @since             2.0.0
 * @package           Lth_Core
 *
 * @wordpress-plugin
 * Plugin Name:       LTH Core
 * Plugin URI:        https://http://lth90.42web.io/
 * Description:       This is a description of the plugin.
 * Version:           2.0.0
 * Author:            LTH
 * Author URI:        https://http://lth90.42web.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       lth-core
 * Domain Path:       /languages
 * GitHub Theme URI: https://github.com/TrHuan/lth/blob/main/plugin-core/updater/lth-core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 2.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('LTH_CORE_VERSION', '2.0.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-lth-core-activator.php
 */
function activate_lth_core()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-lth-core-activator.php';
	Lth_Core_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-lth-core-deactivator.php
 */
function deactivate_lth_core()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-lth-core-deactivator.php';
	Lth_Core_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_lth_core');
register_deactivation_hook(__FILE__, 'deactivate_lth_core');

// Update plugin
if (!class_exists('lth_core_update')) {
	class lth_core_update
	{

		public $plugin_slug;
		public $version;
		public $cache_key;
		public $cache_allowed;

		public function __construct()
		{

			$this->plugin_slug = plugin_basename(__DIR__);
			$this->version = '2.0.0';
			$this->cache_key = 'lth-core';
			$this->cache_allowed = false;

			add_filter('plugins_api', array($this, 'info'), 20, 3);
			add_filter('site_transient_update_plugins', array($this, 'update'));
			add_action('upgrader_process_complete', array($this, 'purge'), 10, 2);
		}

		public function request()
		{

			$remote = get_transient($this->cache_key);

			if (false === $remote || !$this->cache_allowed) {

				$remote = wp_remote_get(
					'https://github.com/TrHuan/lth/tree/main/plugin-core/updater/info.json', // đường dẫn đến thư mục chứa file plugin, json
					array(
						'timeout' => 10,
						'headers' => array(
							'Accept' => 'application/json'
						)
					)
				);

				if (
					is_wp_error($remote)
					|| 200 !== wp_remote_retrieve_response_code($remote)
					|| empty(wp_remote_retrieve_body($remote))
				) {
					return false;
				}

				set_transient($this->cache_key, $remote, DAY_IN_SECONDS);
			}

			$remote = json_decode(wp_remote_retrieve_body($remote));

			return $remote;
		}


		function info($res, $action, $args)
		{

			// print_r( $action );
			// print_r( $args );

			// do nothing if you're not getting plugin information right now
			if ('plugin_information' !== $action) {
				return $res;
			}

			// do nothing if it is not our plugin
			if ($this->plugin_slug !== $args->slug) {
				return $res;
			}

			// get updates
			$remote = $this->request();

			if (!$remote) {
				return $res;
			}

			$res = new stdClass();

			$res->name = $remote->name;
			$res->slug = $remote->slug;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = $remote->author;
			$res->author_profile = $remote->author_profile;
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->requires_php = $remote->requires_php;
			$res->last_updated = $remote->last_updated;

			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
			);

			if (!empty($remote->banners)) {
				$res->banners = array(
					'low' => $remote->banners->low,
					'high' => $remote->banners->high
				);
			}

			return $res;
		}

		public function update($transient)
		{

			if (empty($transient->checked)) {
				return $transient;
			}

			$remote = $this->request();

			if (
				$remote
				&& version_compare($this->version, $remote->version, '<')
				&& version_compare($remote->requires, get_bloginfo('version'), '<=')
				&& version_compare($remote->requires_php, PHP_VERSION, '<')
			) {
				$res = new stdClass();
				$res->slug = $this->plugin_slug;
				$res->plugin = plugin_basename(__FILE__);
				$res->new_version = $remote->version;
				$res->tested = $remote->tested;
				$res->package = $remote->download_url;

				$transient->response[$res->plugin] = $res;
			}

			return $transient;
		}

		public function purge($upgrader, $options)
		{

			if (
				$this->cache_allowed
				&& 'update' === $options['action']
				&& 'plugin' === $options['type']
			) {
				// just clean the cache when new plugin version is installed
				delete_transient($this->cache_key);
			}
		}
	}

	new lth_core_update();
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-lth-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    2.0.0
 */
function run_lth_core()
{

	$plugin = new Lth_Core();
	$plugin->run();
}
run_lth_core();

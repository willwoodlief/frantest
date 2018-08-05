<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Fran_Test
 *
 * @wordpress-plugin
 * Plugin Name:       Franchise Test
 * Plugin URI:        mailto:willwoodlief@gmail.com
 * Description:       Offers a survey and pushes results to hubspot
 * Version:           1.0.1
 * Author:            Will Woodlief
 * Author URI:        willwoodlief@gmail.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fran-test
 * Domain Path:       /languages
 * Requires at least: 4.6
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PLUGIN_NAME_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-fran-test-activator.php
 */
function activate_fran_test() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-fran-test-activator.php';
	Fran_Test_Activator::activate();
}

function fran_test_update_db_check() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fran-test-activator.php';
	Fran_Test_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-fran-test-deactivator.php
 */
function deactivate_fran_test() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-fran-test-deactivator.php';
	Fran_Test_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_fran_test' );
register_deactivation_hook( __FILE__, 'deactivate_fran_test' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-fran-test.php';




add_filter( 'do_parse_request', 'fran_test_help_survey_route', 1, 3 );
function fran_test_help_survey_route( $continue, WP $wp, $extra_query_vars ) {
	if ( preg_match( '~(.*)\\/survey-(.+)$~', $_SERVER['REQUEST_URI'],$matches ) ) {
		header("Location: " . $matches[1] . '?survey-step='. $matches[2]);
		//convert it to a query string
		die();
	}

	return $continue;
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_fran_test() {

	$plugin = new Fran_Test();
	$plugin->run();

}
run_fran_test();

<?php
/**
 * @author  Pressbooks <code@pressbooks.com>
 * @license GPLv3 (or any later version)
 */

namespace Pressbooks\Admin\Diagnostics;

use function Pressbooks\Utility\check_epubcheck_install;
use function Pressbooks\Utility\check_prince_install;
use function Pressbooks\Utility\check_saxonhe_install;
use function Pressbooks\Utility\check_xmllint_install;
use Pressbooks\Book;
use Pressbooks\Container;
use Pressbooks\Modules\ThemeOptions\Admin;
use Pressbooks\Theme\Lock;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Os;
use Sinergi\BrowserDetector\UserAgent;

/**
 * Add the diagnostics menu (with parent page set to null)
 */

function add_menu() {
	add_submenu_page(
		'options.php',
		__( 'Diagnostics', 'pressbooks' ),
		__( 'Diagnostics', 'pressbooks' ),
		'edit_posts',
		'pressbooks_diagnostics',
		__NAMESPACE__ . '\render_page'
	);
}

/**
 * Render the diagnostics page (adapted from https://github.com/WordImpress/Give/blob/master/includes/admin/system-info.php)
 */
function render_page() {
	global $wpdb;
	$browser = new Browser;
	$os = new Os;
	$user_agent = new UserAgent;
	$is_book = Book::isBook();
	$lock = Lock::init();
	$regenerate_webbook_stylesheet_url = wp_nonce_url( get_admin_url( get_current_blog_id(), '/admin-post.php?action=pb_regenerate_webbook_stylesheet' ), 'pb-regenerate-webbook-stylesheet' );
	$output = "### System Information\n\n";
	if ( Book::isBook() ) {
		$output .= "#### Book Info\n\n";
		$output .= 'Book ID: ' . get_current_blog_id() . "\n";
		$output .= 'Book URL: ' . trailingslashit( get_bloginfo( 'url' ) ) . "\n";
		$output .= 'Book Privacy: ' . ( get_bloginfo( 'blog_public' ) ? 'Public' : 'Private' ) . "\n\n";
	} else {
		$output .= "#### Root Blog Info\n\n";
		$output .= 'Root Blog ID: ' . get_current_blog_id() . "\n";
		$output .= 'Root Blog URL: ' . trailingslashit( get_bloginfo( 'url' ) ) . "\n\n";
	}
	$output .= "#### Browser\n\n";
	$output .= 'Platform: ' . $os->getName() . "\n";
	$output .= 'Browser Name: ' . $browser->getName() . "\n";
	$output .= 'Browser Version: ' . $browser->getVersion() . "\n";
	$output .= 'User Agent String: ' . $user_agent->getUserAgentString() . "\n\n";
	$output .= '#### WordPress Configuration' . "\n\n";
	$output .= 'Network URL: ' . network_home_url() . "\n";
	$output .= 'Network Type: ' . ( is_subdomain_install() ? 'Subdomain' : 'Subdirectory' ) . "\n";
	$output .= 'Version: ' . get_bloginfo( 'version' ) . "\n";
	$output .= 'Language: ' . ( defined( 'WPLANG' ) && WPLANG ? WPLANG : 'en_US' ) . "\n";
	$output .= 'WP_ENV: ' . ( defined( 'WP_ENV' ) ? WP_ENV : 'Not set' ) . "\n";
	$output .= 'WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
	$output .= 'Memory Limit: ' . WP_MEMORY_LIMIT . "\n\n";
	$output .= "#### Pressbooks Configuration\n\n";
	$output .= 'Version: ' . PB_PLUGIN_VERSION . "\n";
	if ( $is_book ) {
		switch_to_blog( $GLOBALS['current_site']->blog_id );
		$root_theme = wp_get_theme();
		restore_current_blog();
		if ( $lock->isLocked() ) {
			$theme = wp_get_theme();
			$data = $lock->getLockData();
			$datetime = strftime( '%x', $data['timestamp'] ) . ' at ' . strftime( '%X', $data['timestamp'] );
			$output .= 'Book Theme: ' . $data['name'] . " (LOCKED on $datetime)\n";
			$output .= 'Book Theme Version: ' . $data['version'] . " (LOCKED on $datetime &mdash; Current Version " . $theme->get( 'Version' ) . ")\n";
		} else {
			$theme = wp_get_theme();
			$output .= 'Book Theme: ' . $theme->get( 'Name' ) . "\n";
			$output .= 'Book Theme Version: ' . $theme->get( 'Version' ) . "\n";
		}
	} else {
		$root_theme = wp_get_theme();
	}
	$output .= 'Root Theme: ' . $root_theme->get( 'Name' ) . "\n";
	$output .= 'Root Theme Version: ' . $root_theme->get( 'Version' ) . "\n\n";
	$output .= "#### Pressbooks Dependencies\n\n";
	$output .= 'Epubcheck: ' . ( check_epubcheck_install() ? 'Installed' : 'Not Installed' ) . "\n"; // TODO: version
	$output .= 'xmllint: ' . ( check_xmllint_install() ? 'Installed' : 'Not Installed' ) . "\n"; // TODO: version
	$output .= 'PrinceXML: ' . ( check_prince_install() ? 'Installed' : 'Not Installed' ) . "\n"; // TODO: version
	$output .= 'Saxon-HE: ' . ( check_saxonhe_install() ? 'Installed' : 'Not Installed' ) . "\n\n"; // TODO: version
	$muplugins = get_mu_plugins();
	if ( count( $muplugins ) > 0 ) {
		$output .= '#### Must-Use Plugins' . "\n\n";
		foreach ( $muplugins as $plugin => $plugin_data ) {
			$output .= $plugin_data['Name'] . ': ' . ( $plugin_data['Version'] ? $plugin_data['Version'] : 'n/a' ) . "\n";
		}
	}
	$output .= "\n#### Network Active Plugins\n\n";
	$plugins = get_plugins();
	foreach ( $plugins as $plugin_path => $plugin ) {
		if ( ! is_plugin_active_for_network( $plugin_path ) ) {
			continue;
		}
		$output .= $plugin['Name'] . ': ' . $plugin['Version'] . "\n";
	}
	if ( $is_book ) {
		$output .= "\n#### Book Active Plugins\n\n";
	} else {
		$output .= "\n#### Root Blog Active Plugins\n\n";
	}
	$plugins = get_plugins();
	foreach ( $plugins as $plugin_path => $plugin ) {
		if ( ! is_plugin_active( $plugin_path ) ) {
			continue;
		}
		$output .= $plugin['Name'] . ': ' . $plugin['Version'] . "\n";
	}
	$output .= "\n#### Inactive Plugins\n\n";
	foreach ( $plugins as $plugin_path => $plugin ) {
		if ( is_plugin_active_for_network( $plugin_path ) || is_plugin_active( $plugin_path ) ) {
			continue;
		}
		$output .= $plugin['Name'] . ': ' . $plugin['Version'] . "\n";
	}
	$output .= "\n#### Server Configuration\n\n";
	$output .= 'PHP Version: ' . PHP_VERSION . "\n";
	$output .= 'MySQL Version: ' . $wpdb->db_version() . "\n";
	$output .= 'Webserver Info: ' . $_SERVER['SERVER_SOFTWARE'] . "\n\n";
	$output .= "#### PHP Configuration\n\n";
	$output .= 'Safe Mode: ' . ( ini_get( 'safe_mode' ) ? 'Enabled' : 'Disabled' . "\n" );
	$output .= 'Memory Limit: ' . ini_get( 'memory_limit' ) . "\n";
	$output .= 'Upload Max Size: ' . ini_get( 'upload_max_filesize' ) . "\n";
	$output .= 'Post Max Size: ' . ini_get( 'post_max_size' ) . "\n";
	$output .= 'Upload Max Filesize: ' . ini_get( 'upload_max_filesize' ) . "\n";
	$output .= 'Time Limit: ' . ini_get( 'max_execution_time' ) . "\n";
	$output .= 'Max Input Vars: ' . ini_get( 'max_input_vars' ) . "\n";
	$output .= 'URL-aware fopen: ' . ( ini_get( 'allow_url_fopen' ) ? 'On (' . ini_get( 'allow_url_fopen' ) . ')' : 'N/A' ) . "\n";
	$output .= 'Display Errors: ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n\n";
	$output .= "#### PHP Extensions\n\n";
	$output .= 'OPcache: ';
	$opcache = false;
	if ( extension_loaded( 'Zend OPcache' ) ) {
		$output .= 'Zend,';
		$opcache = true;
	}
	if ( extension_loaded( 'apc' ) ) {
		$output .= 'APC,';
		$opcache = true;
	}
	if ( $opcache ) {
		$output = rtrim( $output, ',' ) . "\n";
	} else {
		$output .= 'Disabled' . "\n";
	}

	$output .= 'XDebug: ' . ( extension_loaded( 'xdebug' ) ? 'Enabled' : 'Disabled' ) . "\n";
	$output .= 'cURL: ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";
	if ( function_exists( 'curl_init' ) && function_exists( 'curl_version' ) ) {
		$curl_values = curl_version(); // @codingStandardsIgnoreLine
		$output .= 'cURL Version: ' . $curl_values['version'] . "\n";
	}
	$output .= 'imagick: ' . ( extension_loaded( 'imagick' ) ? 'Installed' : 'Not Installed' ) . "\n";
	$output .= 'xsl: ' . ( extension_loaded( 'xsl' ) ? 'Installed' : 'Not Installed' );
	$blade = \Pressbooks\Container::get( 'Blade' );
	echo $blade->render(
		'admin.diagnostics',
		[
			'output' => $output,
			'regenerate_webbook_stylesheet_url' => $regenerate_webbook_stylesheet_url,
			'is_book' => $is_book,
		]
	);
}

/**
 * @since 5.7.0
 *
 * Handle form submission on the diagnostics page which triggers regeneration of the webbook stylesheet.
 *
 * @return null
 */
function handle_stylesheet_regeneration() {
	if ( check_admin_referer( 'pb-regenerate-webbook-stylesheet' ) ) {
		( new Admin() )->clearCache();
		Container::get( 'Styles' )->updateWebBookStyleSheet();

		// Ok!
		\Pressbooks\add_notice( __( 'Stylesheet regenerated.', 'pressbooks' ) );
	}
	\Pressbooks\Redirect\location( admin_url( 'options.php?page=pressbooks_diagnostics' ) );
}

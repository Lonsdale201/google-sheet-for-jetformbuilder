<?php
/**
 * Plugin Name: Google Sheet for JetFormBuilder
 * Plugin URI:  https://github.com/Lonsdale201/google-sheet-for-jetformbuilder
 * Description: Send JetFormBuilder form submissions to Google Sheets.
 * Author:      Soczó Kristóf
 * Author URI:  https://github.com/Lonsdale201?tab=repositories
 * Version:     1.0
 * Text Domain: google-sheet-for-jetformbuilder
 * Requires PHP: 8.0
 * Requires Plugins: jetformbuilder
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use GoogleSheet\JetFormBuilder\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSJFB_PLUGIN_FILE', __FILE__ );
define( 'GSJFB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GSJFB_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

const GSJFB_MIN_PHP_VERSION = '8.0';
const GSJFB_MIN_WP_VERSION  = '6.0';

$update_checker_bootstrap = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

if ( file_exists( $update_checker_bootstrap ) ) {
	require_once $update_checker_bootstrap;
}

$autoload = GSJFB_PLUGIN_PATH . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require $autoload;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'GoogleSheet\\JetFormBuilder\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', '/', $relative_class ) . '.php';
		$file           = GSJFB_PLUGIN_PATH . 'includes/' . $relative_path;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'init',
	static function () {
		$domain = 'google-sheet-for-jetformbuilder';
		$locale = determine_locale();
		$mofile = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';

		if ( file_exists( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		}

		load_plugin_textdomain(
			$domain,
			false,
			dirname( plugin_basename( GSJFB_PLUGIN_FILE ) ) . '/languages'
		);
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		$errors = gsjfb_requirement_errors();

		if ( empty( $errors ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( plugin_basename( __FILE__ ) );
		unset( $_GET['activate'] );

		$GLOBALS['gsjfb_activation_errors'] = $errors;

		add_action( 'admin_notices', 'gsjfb_activation_admin_notice' );
	}
);

if ( ! function_exists( 'gsjfb_requirement_errors' ) ) {
	function gsjfb_requirement_errors( bool $include_plugin_checks = true ): array {
		$errors = array();

		if ( version_compare( PHP_VERSION, GSJFB_MIN_PHP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				__( 'Google Sheet for JetFormBuilder requires PHP version %1$s or higher. Current version: %2$s.', 'google-sheet-for-jetformbuilder' ),
				GSJFB_MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		global $wp_version;

		if ( version_compare( $wp_version, GSJFB_MIN_WP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				__( 'Google Sheet for JetFormBuilder requires WordPress version %1$s or higher. Current version: %2$s.', 'google-sheet-for-jetformbuilder' ),
				GSJFB_MIN_WP_VERSION,
				$wp_version
			);
		}

		if ( ! $include_plugin_checks ) {
			return $errors;
		}

		if ( ! function_exists( 'jet_form_builder' ) && ! class_exists( '\Jet_Form_Builder\Plugin' ) ) {
			$errors[] = __( 'Google Sheet for JetFormBuilder requires the JetFormBuilder plugin to be installed and active.', 'google-sheet-for-jetformbuilder' );
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			$errors[] = __( 'Google Sheet for JetFormBuilder requires the PHP OpenSSL extension for Google API authentication.', 'google-sheet-for-jetformbuilder' );
		}

		return $errors;
	}
}

if ( ! function_exists( 'gsjfb_activation_admin_notice' ) ) {
	function gsjfb_activation_admin_notice(): void {
		if ( empty( $GLOBALS['gsjfb_activation_errors'] ) || ! is_array( $GLOBALS['gsjfb_activation_errors'] ) ) {
			return;
		}

		$errors = $GLOBALS['gsjfb_activation_errors'];

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p><ul><li>%s</li></ul></div>',
			esc_html__( 'Google Sheet for JetFormBuilder could not be activated.', 'google-sheet-for-jetformbuilder' ),
			implode( '</li><li>', array_map( 'esc_html', $errors ) )
		);

		unset( $GLOBALS['gsjfb_activation_errors'] );
	}
}

if ( ! function_exists( 'gsjfb_admin_notice' ) ) {
	function gsjfb_admin_notice(): void {
		$errors = $GLOBALS['gsjfb_runtime_errors'] ?? gsjfb_requirement_errors();

		if ( empty( $errors ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><ul><li>%s</li></ul></div>',
			esc_html__( 'Google Sheet for JetFormBuilder cannot run:', 'google-sheet-for-jetformbuilder' ),
			implode( '</li><li>', array_map( 'esc_html', $errors ) )
		);
	}
}

$initial_environment_errors = gsjfb_requirement_errors( false );

if ( ! empty( $initial_environment_errors ) ) {
	$GLOBALS['gsjfb_runtime_errors'] = $initial_environment_errors;

	if ( is_admin() ) {
		add_action( 'admin_notices', 'gsjfb_admin_notice' );
	}

	return;
}

add_action(
	'plugins_loaded',
	static function () {
		$errors = gsjfb_requirement_errors();

		if ( ! empty( $errors ) ) {
			$GLOBALS['gsjfb_runtime_errors'] = $errors;

			if ( is_admin() ) {
				add_action( 'admin_notices', 'gsjfb_admin_notice' );
			}

			return;
		}

		Plugin::instance( GSJFB_PLUGIN_FILE );
	}
);

<?php
/**
 * Plugin Name: TC Lib PDF for WordPress
 * Plugin URI: https://github.com/lweruo0/wp-tc-lib-pdf
 * Description: Bootstrap plugin for tc-lib-pdf via Composer.
 * Version: 2026.07.18
 * Author: Oliver Ruoß
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

final class Tc_Lib_Pdf_Wp_Bootstrap {
	private static $initialized = false;

	public static function init() {
		if (self::$initialized) {
			return;
		}

		self::$initialized = true;

		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			add_action('admin_notices', array(__CLASS__, 'render_php_notice'));
			return;
		}

		self::load_autoloader();
		self::define_font_path();

	}

	public static function load_autoloader() {
		$candidates = array(
			WP_CONTENT_DIR . '/vendor/autoload.php',
			dirname(__FILE__) . '/vendor/autoload.php',
			dirname(__FILE__) . '/../vendor/autoload.php',
		);

		foreach ($candidates as $candidate) {
			if (is_file($candidate)) {
				require_once $candidate;
				return;
			}
		}

		if (!class_exists('\Com\Tecnick\Pdf\Tcpdf')) {
			add_action('admin_notices', array(__CLASS__, 'render_missing_dependency_notice'));
		}
	}

	public static function define_font_path() {
		if (defined('K_PATH_FONTS') && is_dir(constant('K_PATH_FONTS')) && self::has_font_definitions(constant('K_PATH_FONTS'))) {
			return;
		}

		$font_paths = array(
			dirname(__FILE__) . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core',
			dirname(__FILE__) . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts',
			WP_CONTENT_DIR . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core',
			WP_CONTENT_DIR . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts',
		);

		foreach ($font_paths as $path) {
			if (self::has_font_definitions($path)) {
				if (!defined('K_PATH_FONTS')) {
					define('K_PATH_FONTS', $path);
				}
				return;
			}
		}
	}

	private static function has_font_definitions($path) {
		if (!is_dir($path)) {
			return false;
		}

		$matches = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.json');
		return is_array($matches) && !empty($matches);
	}

	public static function render_missing_dependency_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__('TC Lib PDF for WordPress: Composer autoload was not found. Install tc-lib-pdf with Composer first.', 'tc-lib-pdf-wp');
		echo '</p></div>';
	}

	public static function render_php_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__('TC Lib PDF for WordPress requires PHP 8.2 or newer.', 'tc-lib-pdf-wp');
		echo '</p></div>';
	}

	public static function create_pdf() {
		self::init();

		if (!class_exists('\Com\Tecnick\Pdf\Tcpdf')) {
			return null;
		}

		return new \Com\Tecnick\Pdf\Tcpdf();
	}

	public static function activate() {
		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			wp_die(__('TC Lib PDF for WordPress requires PHP 8.2 or newer.', 'tc-lib-pdf-wp'));
		}
	}

	public static function deactivate() {
		// Nothing to do here yet.
	}
}

register_activation_hook(__FILE__, array('Tc_Lib_Pdf_Wp_Bootstrap', 'activate'));
register_deactivation_hook(__FILE__, array('Tc_Lib_Pdf_Wp_Bootstrap', 'deactivate'));

add_action('plugins_loaded', array('Tc_Lib_Pdf_Wp_Bootstrap', 'init'), 10);

// Load PDF Dispatcher for handling PDF rendering requests
add_action('plugins_loaded', function() {
	if (file_exists(__DIR__ . '/include/dispatch.php')) {
		require_once __DIR__ . '/include/dispatch.php';
	}
}, 11);

if (!function_exists('tc_lib_pdf_wp_create_pdf')) {
	function tc_lib_pdf_wp_create_pdf() {
		return Tc_Lib_Pdf_Wp_Bootstrap::create_pdf();
	}
}
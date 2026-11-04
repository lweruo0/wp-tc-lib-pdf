<?php
/**
 * WordPress-Bootstrap.
 *
 * Registriert Hooks, lädt Composer-Autoloader und Fonts bei Bedarf, stellt
 * den URL-Builder und die globalen Hilfsfunktionen bereit. Wird nur von
 * wp-tc-lib-pdf.php eingebunden.
 *
 * @package WordPress Plugin Template/WP
 */

defined('ABSPATH') || exit;

final class Tc_Lib_Pdf_Wp_Bootstrap {
	private static $initialized = false;

	/** Whether the Composer autoloader and the font path have been loaded. */
	private static $booted = false;

	/**
	 * Cheap per-request initialisation: only checks the PHP version and
	 * registers hooks. The Composer autoloader and the font path are loaded
	 * lazily via boot() the first time a PDF is actually created.
	 */
	public static function init() {
		if (self::$initialized) {
			return;
		}

		self::$initialized = true;

		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			add_action('admin_notices', array(__CLASS__, 'render_php_notice'));
			return;
		}

		// In the backend boot early so that the "missing dependency" notice
		// stays visible. Frontend requests boot on demand only.
		if (is_admin()) {
			add_action('admin_init', static function (): void {
				self::boot();
			});
		}
	}

	/**
	 * Load the Composer autoloader and define the font path.
	 *
	 * Idempotent. Call this before touching any \Com\Tecnick\Pdf class.
	 * PdfRegistry::create() and create_pdf() call it automatically.
	 *
	 * @return bool True when tc-lib-pdf is available.
	 */
	public static function boot() {
		if (self::$booted) {
			return class_exists('\Com\Tecnick\Pdf\Tcpdf');
		}

		self::$booted = true;

		if (version_compare(PHP_VERSION, '8.2.0', '<')) {
			return false;
		}

		self::load_autoloader();
		self::define_font_path();

		return class_exists('\Com\Tecnick\Pdf\Tcpdf');
	}

	public static function load_autoloader() {
		$candidates = array(
			WP_CONTENT_DIR . '/vendor/autoload.php',
			TC_LIB_PDF_DIR . '/vendor/autoload.php',
			dirname(TC_LIB_PDF_DIR) . '/vendor/autoload.php',
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
			TC_LIB_PDF_DIR . '/font/core',
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
		// A single stat() instead of a directory scan. Helvetica is the base
		// font of every template, so its metrics file must be present.
		return is_file(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'helvetica.json');
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
		if (!self::boot()) {
			return null;
		}

		return new \Com\Tecnick\Pdf\Tcpdf();
	}

	/**
	 * Build a signed PDF URL. Uses WordPress functions only, so it does not
	 * load tc-lib-pdf.
	 */
	public static function build_pdf_url($template_id, array $query_args = array(), $expires = null) {
		if (!is_string($template_id) || $template_id === '') {
			return '';
		}

		$template_id = sanitize_key($template_id);
		if ($template_id === '') {
			return '';
		}

		$query_args['get_pdf'] = $template_id;
		$nr = (string) ($query_args['nr'] ?? '');
		$jahr = (string) ($query_args['jahr'] ?? '');

		if ($expires !== null && $expires !== '') {
			$expires_date = self::normalize_long_term_expiry($expires);
			if ($expires_date === '') {
				return '';
			}

			$query_args['key'] = wp_hash($template_id . $expires_date . $nr, 'auth');
			$query_args['expires'] = $expires_date;
		} else {
			$query_args['nonce'] = wp_create_nonce($template_id . $nr . $jahr);
		}

		return add_query_arg($query_args, home_url('/'));
	}

	private static function normalize_long_term_expiry($expires) {
		if ($expires instanceof DateTimeInterface) {
			return $expires->format('Y-m-d');
		}

		if (is_int($expires)) {
			return gmdate('Y-m-d', $expires);
		}

		if (is_string($expires) && $expires !== '') {
			$timestamp = strtotime($expires);
			if ($timestamp !== false) {
				return gmdate('Y-m-d', $timestamp);
			}
		}

		return '';
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

register_activation_hook(TC_LIB_PDF_WP_FILE, array('Tc_Lib_Pdf_Wp_Bootstrap', 'activate'));
register_deactivation_hook(TC_LIB_PDF_WP_FILE, array('Tc_Lib_Pdf_Wp_Bootstrap', 'deactivate'));

add_action('plugins_loaded', array('Tc_Lib_Pdf_Wp_Bootstrap', 'init'), 10);

// Template-Registrierung und Dispatcher für PDF-Anfragen.
add_action('plugins_loaded', static function (): void {
	require_once __DIR__ . '/templates.php';
	require_once __DIR__ . '/class-wp-dispatcher.php';
	Pdf_Dispatcher::get_instance();
}, 11);

if (!function_exists('tc_lib_pdf_wp_create_pdf_url')) {
	function tc_lib_pdf_wp_create_pdf_url($template_id, array $params = array(), $expires = null) {
		return Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url($template_id, $params, $expires);
	}
}

if (!function_exists('tc_lib_pdf_wp_create_pdf')) {
	function tc_lib_pdf_wp_create_pdf() {
		return Tc_Lib_Pdf_Wp_Bootstrap::create_pdf();
	}
}

// Dashboard widget: backend only. The class file registers its own hooks.
if (is_admin()) {
	require_once __DIR__ . '/class-dashboard-pdf-links.php';
}

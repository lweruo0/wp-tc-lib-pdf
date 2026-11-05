<?php
/**
 * WordPress-Implementierung der Plattform-Schnittstelle.
 *
 * Kapselt alle WordPress-Aufrufe, die die Engine braucht. Wird in
 * bootstrap.php über PdfRuntime::setPlatform() gesetzt.
 *
 * @package WordPress Plugin Template/WP
 */

defined('ABSPATH') || exit;

require_once TC_LIB_PDF_DIR . '/include/platform/class-pdf-runtime.php';

/**
 * Plattform WordPress.
 */
final class Tc_Lib_Pdf_Wp_Platform implements PdfPlatformInterface {

	/**
	 * {@inheritDoc}
	 */
	public function getStorageBaseDir(): string {
		$uploadDir = wp_upload_dir();
		return rtrim((string) $uploadDir['basedir'], '/\\');
	}

	/**
	 * {@inheritDoc}
	 */
	public function createDirectory(string $path): bool {
		return is_dir($path) || wp_mkdir_p($path);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Schreibt .htaccess und index.php, falls sie fehlen. Unverändert aus dem
	 * früheren PdfDataTrait::createStorageFolder() übernommen.
	 */
	public function protectDirectory(string $path): void {
		/*
		 * "Require all denied" ist Apache 2.4; der IfModule-Zweig fängt 2.2 ab.
		 * Ohne diese Doppelung gäbe es auf der jeweils anderen Version einen 500er.
		 */
		$htaccess = $path . '/.htaccess';
		if (!file_exists($htaccess)) {
			file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
		}

		/* Verhindert das Auflisten des Verzeichnisses, falls Indexes aktiv sind. */
		$index = $path . '/index.php';
		if (!file_exists($index)) {
			file_put_contents($index, "<?php\n// Silence is golden.\n");
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * WordPress versieht $_GET mit Escape-Schrägstrichen (wp_magic_quotes);
	 * wp_unslash() entfernt sie wieder.
	 */
	public function getQueryParams(): array {
		$params = wp_unslash($_GET);
		return is_array($params) ? $params : [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTimezone(): DateTimeZone {
		return wp_timezone();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSecret(): string {
		return wp_salt('auth');
	}

	/**
	 * {@inheritDoc}
	 */
	public function fail(string $message, int $status = 500): never {
		wp_die(
			esc_html($message),
			esc_html__('PDF Generation Error', 'tc-lib-pdf-wp'),
			['response' => $status]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPluginDir(): string {
		return rtrim(TC_LIB_PDF_DIR, '/\\');
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadLibrary(): bool {
		return Tc_Lib_Pdf_Wp_Bootstrap::boot();
	}
}

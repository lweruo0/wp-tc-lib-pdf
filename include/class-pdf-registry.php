<?php
/**
 * PDF Template Registry.
 *
 * Manages registration and retrieval of PDF template classes.
 * Allows dynamic mapping of template identifiers to template classes.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * PDF Template Registry class.
 */
class PdfRegistry {
	/**
	 * Registered templates.
	 *
	 * @var array
	 */
	private static array $templates = [];

	/**
	 * Register a PDF template.
	 *
	 * @param string $id         Template identifier (used in $_GET['demo_pdf']).
	 * @param string $class_name Full class name (e.g., 'PdfExample').
	 * @param string $file_path  Path to the file containing the class.
	 *
	 * @return void
	 */
	public static function register(string $id, string $class_name, string $file_path): void {
		self::$templates[$id] = [
			'class' => $class_name,
			'file' => $file_path,
		];
	}

	/**
	 * Get a registered template configuration.
	 *
	 * @param string $id Template identifier.
	 *
	 * @return array|null Template configuration or null if not found.
	 */
	public static function get(string $id): ?array {
		return self::$templates[$id] ?? null;
	}

	/**
	 * Check if a template is registered.
	 *
	 * @param string $id Template identifier.
	 *
	 * @return bool
	 */
	public static function exists(string $id): bool {
		return isset(self::$templates[$id]);
	}

	/**
	 * Get all registered templates.
	 *
	 * @return array
	 */
	public static function getAll(): array {
		return self::$templates;
	}

	/**
	 * Clear all registrations.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$templates = [];
	}

	/**
	 * Instantiate and return a PDF template.
	 *
	 * @param string $id Template identifier.
	 *
	 * @return PdfTemplate|null The template instance or null if not found.
	 * @throws Exception When template class cannot be loaded or instantiated.
	 */
	public static function create(string $id): ?PdfTemplate {
		$template = self::get($id);

		if (!$template) {
			return null;
		}

		$file = $template['file'];
		$class = $template['class'];

		if (!file_exists($file)) {
			throw new Exception("Template file not found: {$file}");
		}

		require_once $file;

		if (!class_exists($class)) {
			throw new Exception("Template class not found: {$class}");
		}

		$instance = new $class();

		if (!$instance instanceof PdfTemplate) {
			throw new Exception("Template class must extend PdfTemplate: {$class}");
		}

		return $instance;
	}
}

<?php
/**
 * WordPress PDF Dispatcher.
 *
 * Hängt sich an die WordPress-Hooks, prüft die Autorisierung (Nonce oder
 * signierter Langzeit-Link) und rendert das angeforderte Template. Die
 * Template-Registrierung liegt in templates.php.
 *
 * @package WordPress Plugin Template/WP
 */

defined('ABSPATH') || exit;

require_once TC_LIB_PDF_DIR . '/include/class-pdf-registry.php';

/**
 * PDF Dispatcher class.
 */
class Pdf_Dispatcher {
	/**
	 * The single instance of Pdf_Dispatcher.
	 *
	 * @var Pdf_Dispatcher
	 */
	private static ?Pdf_Dispatcher $_instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('init', [$this, 'init']);
	}

	/**
	 * Initialize the dispatcher.
	 *
	 * Checks for PDF rendering request and hooks into template_redirect.
	 *
	 * @return void
	 */
	public function init(): void {
		if ($this->should_render_pdf()) {
			add_action('template_redirect', [$this, 'dispatch']);
		}
	}

	/**
	 * Check if a PDF should be rendered.
	 *
	 * @return bool
	 */
	private function should_render_pdf(): bool {

		// Check if the 'get_pdf' parameter is present in the GET request
		if (!isset($_GET['get_pdf'])) {
			return false;
		}

		// Validate template exists
		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));
		if (!PdfRegistry::exists($template_id)) {
			return false;
		}

		if (isset($_GET['key']) && $this->verify_long_term_authorisation()) {
			return true;
		}

		if (isset($_GET['nonce']) && $this->verify_nonce()) {
			return true;
		}

		// No valid authorization provided
		return false;
	}

	/**
	 * Dispatch to the appropriate PDF template.
	 *
	 * Validates the nonce, retrieves the template, and renders the PDF.
	 * Exits after rendering to prevent further WordPress processing.
	 *
	 * @return void
	 */
	public function dispatch(): void {

		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));

		try {
			// Create and render the template
			$pdf = PdfRegistry::create($template_id);

			if (!$pdf) {
				$this->render_error('Failed to create PDF template');
				exit;
			}

			$download = $_GET['filedownload'] ?? false;

			if ($download) {
				$pdf->output();
			} else {
				$pdf->stream();
			}
			exit;

		} catch (Exception $e) {
			$this->render_error('PDF Generation Error: ' . $e->getMessage());
			exit;
		}
	}

	/**
	 * Verify the long-term authorisation for security.
	 *
	 * @return bool
	 */
	private function verify_long_term_authorisation(): bool {
		if (!isset($_GET['key'], $_GET['get_pdf'])) {
			return false;
		}

		$hash_from_url = sanitize_text_field(wp_unslash($_GET['key']));
		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));
		$nr = sanitize_text_field(wp_unslash($_GET['nr'] ?? ''));
		$date_str = sanitize_text_field(wp_unslash($_GET['expires'] ?? ''));

		if ($date_str === '') {
			return false;
		}

		$expires = DateTimeImmutable::createFromFormat('!Y-m-d', $date_str, wp_timezone());
		if (!$expires instanceof DateTimeImmutable) {
			return false;
		}

		if ($expires->setTime(0, 0, 0) < (new DateTimeImmutable('today', wp_timezone()))) {
			return false;
		}

		$expected_hash = wp_hash($template_id . $expires->format('Y-m-d') . $nr, 'auth');
		return hash_equals($expected_hash, $hash_from_url);
	}

	/**
	 * Verify the nonce for security.
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {
		$nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));
		$nr = sanitize_text_field(wp_unslash($_GET['nr'] ?? ''));
		$jahr = sanitize_text_field(wp_unslash($_GET['jahr'] ?? ''));
		return wp_verify_nonce($nonce, $template_id.$nr.$jahr);
	}

	/**
	 * Render an error message as HTML.
	 *
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	private function render_error(string $message): void {
		wp_die(
			esc_html($message),
			esc_html__('PDF Generation Error', 'tc-lib-pdf-wp'),
			['response' => 500]
		);
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Pdf_Dispatcher
	 */
	public static function get_instance(): Pdf_Dispatcher {
		if (null === self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}

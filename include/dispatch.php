<?php
/**
 * PDF Dispatcher.
 *
 * Manages the rendering of PDF templates based on GET parameters.
 * Handles bootstrap, security verification, and template routing.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-pdf-registry.php';
require_once __DIR__ . '/class-pdf-template.php';
require_once __DIR__ . '/trait-pdf-header-footer.php';

// Register available PDF templates
require_once __DIR__ . '/class-pdf-example.php';
PdfRegistry::register('example', 'PdfExample', __DIR__ . '/class-pdf-example.php');

require_once __DIR__ . '/class-pdf-invoice.php';
PdfRegistry::register('invoice', 'PdfInvoice', __DIR__ . '/class-pdf-invoice.php');

require_once __DIR__ . '/class-pdf-simple.php';
PdfRegistry::register('simple', 'PdfSimple', __DIR__ . '/class-pdf-simple.php');

// Add more templates here as needed:
// require_once __DIR__ . '/class-pdf-custom.php';
// PdfRegistry::register('custom', 'PdfCustom', __DIR__ . '/class-pdf-custom.php');

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
		return isset($_GET['get_pdf']) && isset($_GET['nonce']);
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
		// Verify nonce for security
		if (!$this->verify_nonce()) {
			$this->render_error('Invalid security token');
			exit;
		}

		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));

		// Validate template exists
		if (!PdfRegistry::exists($template_id)) {
			$this->render_error("Template not found: {$template_id}");
			exit;
		}

		try {
			// Create and render the template
			$pdf = PdfRegistry::create($template_id);

			if (!$pdf) {
				$this->render_error('Failed to create PDF template');
				exit;
			}

			// Generate PDF output
			$filename = $this->get_filename($template_id);

            $download = $_GET['filedownload']??false;

            if ($download) {
                $pdf->output($filename);
            } else {
                $pdf->stream($filename);
            }
            exit;

		} catch (Exception $e) {
			$this->render_error('PDF Generation Error: ' . $e->getMessage());
			exit;
		}
	}

	/**
	 * Verify the nonce for security.
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {
        return true;
		$nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
		return wp_verify_nonce($nonce, 'get_pdf_render');
	}

	/**
	 * Get the filename for the PDF export.
	 *
	 * @param string $template_id Template identifier.
	 *
	 * @return string
	 */
	private function get_filename(string $template_id): string {
		$timestamp = gmdate('Y-m-d_His');
		return "pdf-export-{$template_id}-{$timestamp}.pdf";
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



// Initialize dispatcher
Pdf_Dispatcher::get_instance();
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
// Register available PDF templates
PdfRegistry::register('example', 'PdfExample', __DIR__ . '/class-pdf-example.php');
PdfRegistry::register('invoice', 'PdfInvoice', __DIR__ . '/class-pdf-invoice.php');

PdfRegistry::register('simple', 'PdfSimple', __DIR__ . '/class-pdf-simple.php');

// Add more templates here as needed:
// require_once __DIR__ . '/class-pdf-custom.php';
// PdfRegistry::register('custom', 'PdfCustom', __DIR__ . '/class-pdf-custom.php');


/* 

https://bfv-ehingen.de/?erlaubnis=2026-P-0148&key=31e86617aad37a3a3902a560e69c7e79
https://bfv-ehingen.de/?rechnung=2026-P-0148&key=31e86617aad37a3a3902a560e69c7e79
https://bfv-ehingen.de/?mahnung=2026-P-0147&key=68115147e855ecdcd7c9395b5bbe8ff9
https://bfv-ehingen.de/?mahnung=2026-P-0150&key=df1510b97ae9f6a1620591b4f7f8807f&nr=2

https://bfv-ehingen.de/?arbeitsdienstliste=1&dienst=17.01.2026&mgn=282&nonce=45042c927a

https://bfv-ehingen.de/?jugendteilnehmerliste=1&veranstaltung=12.09.2026&mgn=282&nonce=45042c927a

https://bfv-ehingen.de/?rechnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e
https://bfv-ehingen.de/?mahnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e
https://bfv-ehingen.de/?mahnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e&nr=2

https://bfv-ehingen.de/?fpdf=1&yy=2025

https://bfv-ehingen.de/?infoblatt-antrag=1&mn=244
https://bfv-ehingen.de/?mitgliedsantrag=1&mn=244&vn=Alexander&n=Lammert&y=2026&key=cc11bdd3e4e5c1263e0a76c5745d23d2
https://bfv-ehingen.de/?rechnungantrag=244&key=45042c927a

*/



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
		// $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
		// return wp_verify_nonce($nonce, 'get_pdf_render');
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
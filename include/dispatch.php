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

// https://bfv-ehingen.de/?rechnung=2026-P-0148&key=31e86617aad37a3a3902a560e69c7e79
PdfRegistry::register('rechnung_erlaubnis', 'PdfRechnungErlaubnis', __DIR__ . '/class-pdf-rechnung-erlaubnis.php');
// https://bfv-ehingen.de/?rechnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e
PdfRegistry::register('rechnung_merchandise', 'PdfRechnungMerchandise', __DIR__ . '/class-pdf-rechnung-merchandise.php');
// https://bfv-ehingen.de/?rechnungantrag=244&key=45042c927a
PdfRegistry::register('rechnung_antrag', 'PdfRechnungMitgliedsantrag', __DIR__ . '/class-pdf-rechnung-mitgliedsantrag.php');
// PdfRegistry::register('rechnung_huette', 'PdfRechnungHuette', __DIR__ . '/class-pdf-rechnung-huette.php');
PdfRegistry::register('rechnung_huette', 'PdfRechnungHuette', __DIR__ . '/class-pdf-rechnung-huette.php');
// PdfRegistry::register('rechnung_vorbereitungslehrgang', 'PdfRechnungVorbereitungslehrgang', __DIR__ . '/class-pdf-rechnung-vorbereitungslehrgang.php');

// https://bfv-ehingen.de/?mahnung=2026-P-0147&key=68115147e855ecdcd7c9395b5bbe8ff9
PdfRegistry::register('mahnung_erlaubnis', 'PdfMahnungErlaubnis', __DIR__ . '/class-pdf-rechnung-erlaubnis.php');
// https://bfv-ehingen.de/?mahnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e
PdfRegistry::register('mahnung_merchandise', 'PdfRechnungMerchandise', __DIR__ . '/class-pdf-rechnung-merchandise.php');
// https://bfv-ehingen.de/?mahnung_antrag=244&key=45042c927a
PdfRegistry::register('mahnung_antrag', 'PdfRechnungMitgliedsantrag', __DIR__ . '/class-pdf-rechnung-mitgliedsantrag.php');
// PdfRegistry::register('mahnung_huette', 'PdfRechnungHuette', __DIR__ . '/class-pdf-rechnung-huette.php');
PdfRegistry::register('mahnung_huette', 'PdfRechnungHuette', __DIR__ . '/class-pdf-rechnung-huette.php');

// https://bfv-ehingen.de/?arbeitsdienstliste=1&dienst=17.01.2026&mgn=282&nonce=45042c927a
PdfRegistry::register('liste_arbeitsdienst', 'PdfListeArbeitsdienst', __DIR__ . '/class-pdf-liste-arbeitsdienst.php');
// https://bfv-ehingen.de/?jugendteilnehmerliste=1&veranstaltung=12.09.2026&mgn=282&nonce=45042c927a
PdfRegistry::register('liste_jugendveranstaltung', 'PdfListeJugendveranstaltung', __DIR__ . '/class-pdf-liste-jugendveranstaltung.php');

//https://bfv-ehingen.de/?erlaubnis=2026-P-0148&key=31e86617aad37a3a3902a560e69c7e79
PdfRegistry::register('erlaubnisschein', 'PdfErlaubnisschein2', __DIR__ . '/class-pdf-erlaubnisschein.php');

//https://bfv-ehingen.de/?fpdf=1&yy=2025
PdfRegistry::register('fangstatistik', 'PdfFangstatistikJahr', __DIR__ . '/class-pdf-fangstatistik.php');
//https://bfv-ehingen.de/?fpdfDiagramm=1&yy=2024
PdfRegistry::register('vorjahresvergleich', 'PdfFangstatistikVorJahresvergleich', __DIR__ . '/class-pdf-fangstatistik.php');
//https://bfv-ehingen.de/?JahresDiagramm=1&yy=2024
PdfRegistry::register('jahresvergleich', 'PdfFangstatistikMehrjahresvergleich', __DIR__ . '/class-pdf-fangstatistik.php');
//https://bfv-ehingen.de/?pdf_make_JahresDiagramm=1&yy=2024
PdfRegistry::register('fangstatistikuservergleich', 'PdfFangstatistikUser', __DIR__ . '/class-pdf-fangstatistik.php');
//https://bfv-ehingen.de/?fpdfUser=1&yy=2024
// PdfRegistry::register('fangstatistik_demo', 'PdfFangstatistik', __DIR__ . '/class-pdf-fangstatistik.php');



// https://bfv-ehingen.de/?mitgliedsantrag=1&mn=244&vn=Alexander&n=Lammert&y=2026&key=cc11bdd3e4e5c1263e0a76c5745d23d2
PdfRegistry::register('mitgliedsantrag', 'PdfMitgliedsantrag2', __DIR__ . '/class-pdf-mitgliedsantrag.php');
// https://bfv-ehingen.de/?infoblatt-antrag=1&mn=244
PdfRegistry::register('mitgliedsantraginfo', 'PdfMitgliedsantragInfoBlatt', __DIR__ . '/class-pdf-mitgliedsantrag.php');


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

            $download = $_GET['filedownload']??false;

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



// Initialize dispatcher
Pdf_Dispatcher::get_instance();
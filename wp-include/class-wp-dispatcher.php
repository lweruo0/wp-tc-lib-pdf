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

		if (!is_string($_GET['key']) || !is_string($_GET['get_pdf']) || !is_string($_GET['expires'] ?? '')) {
			return false;
		}

		$hash_from_url = sanitize_text_field(wp_unslash($_GET['key']));
		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));
		$date_str = sanitize_text_field(wp_unslash($_GET['expires'] ?? ''));

		if ($date_str === '' || $hash_from_url === '') {
			return false;
		}

		$expires = DateTimeImmutable::createFromFormat('!Y-m-d', $date_str, wp_timezone());
		if (!$expires instanceof DateTimeImmutable) {
			return false;
		}

		if ($expires->setTime(0, 0, 0) < (new DateTimeImmutable('today', wp_timezone()))) {
			return false;
		}

		$params = $this->signed_params_from_request();
		$payload = Tc_Lib_Pdf_Wp_Bootstrap::signature_payload($template_id, $params, $expires->format('Y-m-d'));
		if ($payload === null) {
			return false;
		}

		$expected_hash = wp_hash($payload, 'auth');
		if (hash_equals($expected_hash, $hash_from_url)) {
			return true;
		}

		return $this->verify_legacy_long_term_key($template_id, $params, $expires->format('Y-m-d'), $hash_from_url);
	}

	/**
	 * Langzeit-Links im alten Format (Signatur nur über get_pdf, expires, nr).
	 *
	 * Bereits verschickte Links für Erlaubnisscheine und Rechnungen wählen das
	 * Dokument allein über nr aus und waren daher nie angreifbar. Sie bleiben
	 * gültig, solange der Request außer nr keinen weiteren signierten
	 * Parameter enthält. Damit sind alte Links mit mnr, jahr, dienst usw.
	 * (die eigentliche Lücke) und nachträglich angehängte Schalter wie
	 * ohnemahngebuehr abgewiesen.
	 *
	 * Abschalten, sobald keine alten Links mehr im Umlauf sind:
	 * define('TC_LIB_PDF_LEGACY_KEYS', false) in wp-config.php.
	 *
	 * @param string               $template_id
	 * @param array<string, mixed> $params      Ergebnis von signed_params_from_request().
	 * @param string               $expires     Y-m-d.
	 * @param string               $hash_from_url
	 *
	 * @return bool
	 */
	private function verify_legacy_long_term_key(string $template_id, array $params, string $expires, string $hash_from_url): bool {
		if (defined('TC_LIB_PDF_LEGACY_KEYS') && !constant('TC_LIB_PDF_LEGACY_KEYS')) {
			return false;
		}

		$nr = (string) ($params['nr'] ?? '');
		unset($params['nr']);
		if ($nr === '' || $params !== []) {
			return false;
		}

		return hash_equals(wp_hash($template_id . $expires . $nr, 'auth'), $hash_from_url);
	}

	/**
	 * Verify the nonce for security.
	 *
	 * Die Nonce-Aktion enthält dieselben Parameter wie der Langzeit-Link
	 * (Tc_Lib_Pdf_Wp_Bootstrap::SIGNED_PARAMS).
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {
		if (!is_string($_GET['nonce']) || !is_string($_GET['get_pdf'])) {
			return false;
		}

		$nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
		$template_id = sanitize_text_field(wp_unslash($_GET['get_pdf']));

		$payload = Tc_Lib_Pdf_Wp_Bootstrap::signature_payload($template_id, $this->signed_params_from_request(), '');
		if ($payload === null) {
			return false;
		}

		return (bool) wp_verify_nonce($nonce, $payload);
	}

	/**
	 * Die signierten Parameter aus $_GET lesen.
	 *
	 * Die Templates lesen $_GET ungefiltert. Damit ein Wert, der erst nach
	 * sanitize_text_field() dem signierten Wert entspricht (z. B. "233 " oder
	 * "2<b>33"), nicht an der Signatur vorbei ein anderes Ergebnis liefert,
	 * muss der Rohwert bereits normalisiert sein. Sonst wird ein Array
	 * zurückgegeben, das signature_payload() ablehnt.
	 *
	 * @return array<string, mixed>
	 */
	private function signed_params_from_request(): array {
		$params = [];
		foreach (Tc_Lib_Pdf_Wp_Bootstrap::SIGNED_PARAMS as $name) {
			if (!isset($_GET[$name])) {
				continue;
			}
			$raw = wp_unslash($_GET[$name]);
			if (!is_string($raw) || sanitize_text_field($raw) !== $raw) {
				return [$name => ['invalid']];
			}
			$params[$name] = $raw;
		}
		return $params;
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

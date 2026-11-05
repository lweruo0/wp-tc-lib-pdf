<?php
/**
 * WordPress PDF Dispatcher.
 *
 * Hängt sich an die WordPress-Hooks und prüft die kurzlebige Autorisierung
 * (Nonce). Routing, signierte Langzeit-Links und das Rendern liegen im
 * plattformneutralen PdfDispatcherCore. Die Template-Registrierung liegt in
 * templates.php.
 *
 * @package WordPress Plugin Template/WP
 */

defined('ABSPATH') || exit;

require_once TC_LIB_PDF_DIR . '/include/class-pdf-registry.php';
require_once TC_LIB_PDF_DIR . '/include/class-pdf-dispatcher-core.php';

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
	 * Geprüfte Template-ID des aktuellen Requests (gesetzt in init()).
	 *
	 * @var string|null
	 */
	private ?string $template_id = null;

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
	 * Ohne gültige Autorisierung passiert nichts; der Request läuft als
	 * normaler WordPress-Request weiter.
	 *
	 * @return void
	 */
	public function init(): void {
		$query = PdfRuntime::platform()->getQueryParams();

		$template_id = PdfDispatcherCore::resolveTemplateId($query);
		if ($template_id === null) {
			return;
		}

		if (isset($query['key']) && PdfDispatcherCore::verifyLongTermKey($template_id, $query)) {
			$this->template_id = $template_id;
		} elseif (isset($query['nonce']) && $this->verify_nonce($template_id, $query)) {
			$this->template_id = $template_id;
		}

		if ($this->template_id !== null) {
			add_action('template_redirect', [$this, 'dispatch']);
		}
	}

	/**
	 * Render the authorised template and exit.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		if ($this->template_id === null) {
			return;
		}
		$query = PdfRuntime::platform()->getQueryParams();
		PdfDispatcherCore::render($this->template_id, !empty($query['filedownload']));
	}

	/**
	 * Verify the nonce (WordPress, an die Benutzersitzung gebunden).
	 *
	 * Die Nonce-Aktion enthält dieselben Parameter wie der Langzeit-Link
	 * (PdfDispatcherCore::SIGNED_PARAMS).
	 *
	 * @param string               $template_id
	 * @param array<string, mixed> $query
	 *
	 * @return bool
	 */
	private function verify_nonce(string $template_id, array $query): bool {
		$nonce = $query['nonce'] ?? null;
		if (!is_string($nonce) || $nonce === '') {
			return false;
		}

		$params = PdfDispatcherCore::signedParamsFromQuery($query);
		if ($params === null) {
			return false;
		}

		$payload = PdfDispatcherCore::signaturePayload($template_id, $params, '');
		if ($payload === null) {
			return false;
		}

		return (bool) wp_verify_nonce($nonce, $payload);
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

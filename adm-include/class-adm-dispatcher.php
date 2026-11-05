<?php
/**
 * Dispatcher Admidio: prüft den Zugriff und übergibt an PdfDispatcherCore.
 *
 * @package WordPress Plugin Template/Admidio
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

final class Tc_Lib_Pdf_Adm_Dispatcher {

	/**
	 * URL-Parameter des Requests (nur skalare Werte).
	 *
	 * @return array<string, mixed>
	 */
	private static function query(): array {
		return array_filter($_GET, static fn ($value): bool => is_scalar($value));
	}

	/**
	 * Trägt der Request einen gültigen, nicht abgelaufenen signierten Link für ein registriertes Template?
	 */
	public static function hasValidSignature(): bool {
		$query      = self::query();
		$templateId = PdfDispatcherCore::resolveTemplateId($query);
		return $templateId !== null && isset($query['key']) && tc_lib_pdf_adm_unavailable_reason() === ''
			&& PdfDispatcherCore::verifyLongTermKey($templateId, $query);
	}

	/**
	 * Zugriff prüfen und das PDF ausliefern. Ohne signierten Link muss pdf.php vorher login_valid.php
	 * eingebunden haben; dann sind nur Administratoren zugelassen.
	 */
	public static function handle(): never {
		$platform   = PdfRuntime::platform();
		$query      = self::query();
		$templateId = PdfDispatcherCore::resolveTemplateId($query);
		if ($templateId === null) {
			$platform->fail('Unbekanntes Dokument', 404);
		}

		$reason = tc_lib_pdf_adm_unavailable_reason();
		if ($reason !== '') {
			$platform->fail($reason, 503);
		}

		if (self::hasValidSignature()) {
			// signierter Link: nur die signierten Parameter, kein Neu-Erzeugen ("new" entfällt)
			$params = PdfDispatcherCore::signedParamsFromQuery($query) ?? [];
			$params['get_pdf'] = $templateId;
			if ($platform instanceof Tc_Lib_Pdf_Adm_Platform) {
				$platform->setQueryParams($params);
			}
		} elseif (empty($GLOBALS['gValidLogin']) || !isset($GLOBALS['gCurrentUser']) || !$GLOBALS['gCurrentUser']->isAdministrator()) {
			$platform->fail('Keine Berechtigung', 403);
		}

		PdfDispatcherCore::render($templateId, !empty($query['filedownload']));
	}
}

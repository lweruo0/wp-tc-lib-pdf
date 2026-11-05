<?php
/**
 * Admidio-Bootstrap der PDF-Engine (Host adm-include/).
 *
 * Wird auf zwei Wegen geladen:
 *  - von adm-include/pdf.php (eigener Request für signierte Links und Administratoren) und
 *  - im laufenden Request eines anderen Admidio-Plugins (z. B. Mitgliedsbeitrag), das ein PDF
 *    erzeugen und ablegen will: require_once …/wp-tc-lib-pdf/adm-include/bootstrap.php und danach
 *    tc_lib_pdf_adm_save().
 *
 * Voraussetzung in beiden Fällen: Admidio ist geladen (system/common.php). Ohne Admidio
 * (z. B. derselbe Ordner in WordPress) tut die Datei nichts.
 *
 * Die tc-lib-pdf-Bibliothek (vendor/, PHP 8.5) wird erst geladen, wenn tatsächlich ein PDF entsteht.
 * Siehe KONZEPT-PLATTFORM.md, Abschnitt 2.6, und KONZEPT-ADMIDIO-RECHNUNGEN.md.
 *
 * @package WordPress Plugin Template/Admidio
 */

if (!defined('ADMIDIO_PATH')) {
	// Nicht in Admidio (z. B. in WordPress über die URL aufgerufen): nichts tun.
	return;
}
if (defined('TC_LIB_PDF_ADM_BOOTSTRAPPED')) {
	return;
}
define('TC_LIB_PDF_ADM_BOOTSTRAPPED', true);

defined('TC_LIB_PDF_DIR') || define('TC_LIB_PDF_DIR', dirname(__DIR__));
defined('TC_LIB_PDF_BOOTSTRAPPED') || define('TC_LIB_PDF_BOOTSTRAPPED', true);

require_once TC_LIB_PDF_DIR . '/include/platform/class-pdf-runtime.php';
require_once TC_LIB_PDF_DIR . '/include/class-pdf-registry.php';
require_once TC_LIB_PDF_DIR . '/include/class-pdf-dispatcher-core.php';
require_once __DIR__ . '/class-adm-platform.php';

if (!PdfRuntime::hasPlatform()) {
	PdfRuntime::setPlatform(new Tc_Lib_Pdf_Adm_Platform(tc_lib_pdf_adm_config()));
}

require_once __DIR__ . '/templates.php';

/**
 * Pfad der Konfigurationsdatei (Vorlage adm-include/config_sample.php).
 *
 * Bevorzugt adm_my_files/plugins/wp-tc-lib-pdf/config.php: der Ordner ist per .htaccess gesperrt und
 * bleibt bei Updates der Bibliothek erhalten. adm-include/config.php gilt nur noch als Rückfall.
 * Ergebnis leer, wenn keine der beiden Dateien existiert.
 */
function tc_lib_pdf_adm_config_file(): string {
	$candidates = [
		ADMIDIO_PATH . FOLDER_DATA . '/plugins/wp-tc-lib-pdf/config.php',
		__DIR__ . '/config.php',
	];
	foreach ($candidates as $file) {
		if (is_file($file)) {
			return $file;
		}
	}
	return '';
}

/**
 * Konfiguration laden (nicht versioniert, enthält das Secret).
 *
 * @return array<string, mixed>
 */
function tc_lib_pdf_adm_config(): array {
	static $config = null;
	if ($config === null) {
		$file   = tc_lib_pdf_adm_config_file();
		$loaded = $file !== '' ? require $file : [];
		$config = is_array($loaded) ? $loaded : [];
	}
	return $config;
}

/**
 * Kann in diesem Request ein PDF erzeugt werden? (PHP 8.5, vendor/, Secret)
 *
 * @return string Leer, wenn alles bereit ist, sonst ein Grund für die Anzeige.
 */
function tc_lib_pdf_adm_unavailable_reason(): string {
	if (PHP_VERSION_ID < 80500) {
		return 'PDF-Bibliothek benötigt PHP 8.5 (läuft: ' . PHP_VERSION . ')';
	}
	if (!is_file(TC_LIB_PDF_DIR . '/vendor/autoload.php')) {
		return 'PDF-Bibliothek unvollständig (vendor/ fehlt)';
	}
	if (trim((string) (tc_lib_pdf_adm_config()['secret'] ?? '')) === '') {
		return 'PDF-Bibliothek nicht eingerichtet (adm_my_files/plugins/wp-tc-lib-pdf/config.php mit secret fehlt)';
	}
	return '';
}

/**
 * PDF eines Templates im laufenden Request erzeugen und ablegen.
 *
 * Das Template liest seine Auswahl (z. B. nr) über getUrl() wie bei einem HTTP-Aufruf; dafür setzt die
 * Plattform die Parameter für die Dauer des Aufrufs. Fehler werden als Ausnahme geworfen (nie exit).
 *
 * @param string               $templateId Registrierte Template-ID, z. B. adm_rechnung_beitrag.
 * @param array<string, mixed> $params     Auswahlparameter, z. B. ['nr' => '2026-B-0012'].
 *
 * @return string Absoluter Pfad der abgelegten Datei.
 * @throws RuntimeException
 */
function tc_lib_pdf_adm_save(string $templateId, array $params): string {
	$reason = tc_lib_pdf_adm_unavailable_reason();
	if ($reason !== '') {
		throw new RuntimeException($reason);
	}
	$platform = PdfRuntime::platform();
	if (!$platform instanceof Tc_Lib_Pdf_Adm_Platform) {
		throw new RuntimeException('Falsche Plattform für Admidio');
	}
	return $platform->runWithParams($params, static function () use ($templateId): string {
		$pdf = PdfRegistry::create($templateId);
		if (!$pdf instanceof PdfTemplateBase) {
			throw new RuntimeException('Unbekanntes Template: ' . $templateId);
		}
		if (!$pdf->save()) {
			throw new RuntimeException('PDF konnte nicht gespeichert werden (' . $templateId . ')');
		}
		$file = $pdf->getFileNameAbs();
		unset($pdf);
		return $file;
	});
}

/**
 * Link auf ein PDF.
 *
 * Mit $expires entsteht ein signierter Langzeit-Link (gültig bis einschließlich diesem Tag, ohne
 * Admidio-Anmeldung), ohne $expires ein Link, der nur für angemeldete Administratoren funktioniert.
 *
 * @param string                              $templateId
 * @param array<string, mixed>                $params Auswahlparameter (müssen in PdfDispatcherCore::SIGNED_PARAMS stehen).
 * @param string|int|DateTimeInterface|null   $expires z. B. '+1 year', '2027-12-31', DateTime.
 *
 * @return string Leer bei ungültigen Angaben.
 */
function tc_lib_pdf_adm_create_pdf_url(string $templateId, array $params = [], string|int|DateTimeInterface|null $expires = null): string {
	if (!PdfRegistry::exists($templateId)) {
		return '';
	}
	$params = PdfDispatcherCore::normalizeParamsForLink($params);
	if ($params === null) {
		return '';
	}
	$query = ['get_pdf' => $templateId] + $params;

	if ($expires !== null && $expires !== '') {
		$timezone = PdfRuntime::platform()->getTimezone();
		if ($expires instanceof DateTimeInterface) {
			$date = DateTimeImmutable::createFromInterface($expires)->setTimezone($timezone);
		} elseif (is_int($expires)) {
			$date = (new DateTimeImmutable('@' . $expires))->setTimezone($timezone);
		} else {
			try {
				$date = new DateTimeImmutable($expires, $timezone);
			} catch (Exception $e) {
				return '';
			}
		}
		$expiresDate = $date->format('Y-m-d');
		$key = PdfDispatcherCore::longTermKey($templateId, $params, $expiresDate);
		if ($key === null) {
			return '';
		}
		$query['key']     = $key;
		$query['expires'] = $expiresDate;
	}

	return ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(TC_LIB_PDF_DIR) . '/adm-include/pdf.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

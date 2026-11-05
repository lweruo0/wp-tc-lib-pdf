<?php
/**
 * Dispatcher-Kern.
 *
 * Plattformneutraler Teil der PDF-Auslieferung: Template-ID prüfen,
 * signierte Langzeit-Links erzeugen und prüfen, Template rendern. Der
 * Host-Dispatcher (wp-include/class-wp-dispatcher.php, später
 * adm-include/) liefert nur noch den Einstieg und die kurzlebige
 * Autorisierung (WordPress: Nonce; Admidio: Login + Rolle).
 *
 * Siehe KONZEPT-PLATTFORM.md, Abschnitt 2.3.
 *
 * Diese Datei ist Teil der plattformneutralen Engine und darf keine
 * WordPress- oder Admidio-Funktionen aufrufen.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once __DIR__ . '/platform/class-pdf-runtime.php';
require_once __DIR__ . '/class-pdf-registry.php';

/**
 * Routing und Signaturprüfung, ohne Host-Abhängigkeiten.
 */
final class PdfDispatcherCore {

	/**
	 * URL-Parameter, die ein Template auswählen oder den Inhalt fachlich
	 * verändern. Sie werden in fester Reihenfolge signiert (Langzeit-Link)
	 * bzw. in die Nonce-Aktion aufgenommen. Wer einen dieser Parameter in
	 * einem Link verändert, ergänzt oder entfernt, macht den Link ungültig.
	 *
	 * Nicht signiert sind nur Darstellungs-Schalter (new, filedownload,
	 * layout, page, SortByGew) sowie key, nonce und expires selbst.
	 *
	 * Reihenfolge nie ändern, nur hinten ergänzen: jede Änderung macht alle
	 * bestehenden Links ungültig.
	 */
	public const SIGNED_PARAMS = [
		'nr',
		'mnr',
		'jahr',
		'dienst',
		'veranstaltung',
		'Mitgliedsnummer',
		'verein',
		'zweitemahnung',
		'ohnemahngebuehr',
	];

	// -----------------------------------------------------------------------
	// Routing
	// -----------------------------------------------------------------------

	/**
	 * Template-ID aus den URL-Parametern lesen und gegen die Registry prüfen.
	 *
	 * @param array<string, mixed> $query URL-Parameter (ohne Escape-Schrägstriche).
	 *
	 * @return string|null Registrierte Template-ID oder null.
	 */
	public static function resolveTemplateId(array $query): ?string {
		$id = $query['get_pdf'] ?? null;
		if (!is_string($id) || $id === '' || self::normalizeValue($id) !== $id) {
			return null;
		}
		return PdfRegistry::exists($id) ? $id : null;
	}

	/**
	 * Template erzeugen und ausliefern, danach den Request beenden.
	 *
	 * Fehler gehen an PdfPlatformInterface::fail().
	 *
	 * @param string $templateId Bereits geprüfte Template-ID.
	 * @param bool   $download   true: als Download (attachment), sonst inline.
	 *
	 * @return never
	 */
	public static function render(string $templateId, bool $download = false): never {
		$platform = PdfRuntime::platform();
		try {
			$pdf = PdfRegistry::create($templateId);
			if ($pdf === null) {
				$platform->fail('Failed to create PDF template');
			}
			if ($download) {
				$pdf->output();
			} else {
				$pdf->stream();
			}
		} catch (Exception $e) {
			$platform->fail('PDF Generation Error: ' . $e->getMessage());
		}
		exit;
	}

	// -----------------------------------------------------------------------
	// Signatur
	// -----------------------------------------------------------------------

	/**
	 * Wert so normalisieren, wie er signiert wird.
	 *
	 * Entspricht für übliche Werte WordPress' sanitize_text_field(): Tags
	 * entfernen, Zeilenumbrüche/Tabs/mehrfache Leerzeichen zu einem
	 * Leerzeichen, %-kodierte Bytes entfernen, trimmen. Ungültiges UTF-8
	 * ergibt ''.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public static function normalizeValue(string $value): string {
		if (!mb_check_encoding($value, 'UTF-8')) {
			return '';
		}
		if (str_contains($value, '<')) {
			$value = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $value);
			$value = strip_tags($value);
		}
		$value = (string) preg_replace('/[\r\n\t ]+/', ' ', $value);
		$value = trim($value);
		$found = false;
		while (preg_match('/%[a-f0-9]{2}/i', $value, $match)) {
			$value = str_replace($match[0], '', $value);
			$found = true;
		}
		if ($found) {
			$value = trim((string) preg_replace('/ +/', ' ', $value));
		}
		return $value;
	}

	/**
	 * Signierte Parameter aus den URL-Parametern lesen.
	 *
	 * Die Templates lesen die URL-Parameter ungefiltert. Damit ein Wert, der
	 * erst nach normalizeValue() dem signierten Wert entspricht (z. B. "233 "
	 * oder "2<b>33"), nicht an der Signatur vorbei ein anderes Ergebnis
	 * liefert, muss der Rohwert bereits normalisiert sein.
	 *
	 * @param array<string, mixed> $query URL-Parameter (ohne Escape-Schrägstriche).
	 *
	 * @return array<string, string>|null null bei ungültigem Wert.
	 */
	public static function signedParamsFromQuery(array $query): ?array {
		$params = [];
		foreach (self::SIGNED_PARAMS as $name) {
			if (!isset($query[$name])) {
				continue;
			}
			$raw = $query[$name];
			if (!is_string($raw) || self::normalizeValue($raw) !== $raw) {
				return null;
			}
			$params[$name] = $raw;
		}
		return $params;
	}

	/**
	 * Parameter für einen neuen Link normalisieren.
	 *
	 * Nur die Parameter aus SIGNED_PARAMS werden verändert; andere bleiben,
	 * wie sie sind.
	 *
	 * @param array<string, mixed> $params
	 *
	 * @return array<string, mixed>|null null, wenn ein signierter Parameter kein Skalar ist.
	 */
	public static function normalizeParamsForLink(array $params): ?array {
		foreach (self::SIGNED_PARAMS as $name) {
			if (array_key_exists($name, $params)) {
				if (!is_scalar($params[$name])) {
					return null;
				}
				$params[$name] = self::normalizeValue((string) $params[$name]);
			}
		}
		return $params;
	}

	/**
	 * Zu signierende Zeichenkette aus Template-ID, Ablaufdatum und allen
	 * SIGNED_PARAMS bilden.
	 *
	 * Werte werden normalisiert und rawurlencode()-kodiert, damit der Trenner
	 * '|' nie aus einem Wert stammen kann ("nr=1|mnr=2" ist eindeutig).
	 * Fehlende Parameter gehen als leerer Wert ein, damit auch das Hinzufügen
	 * eines Parameters auffällt.
	 *
	 * Das Format ist seit 2026-11-05 in signierten Links auf dem Server im
	 * Umlauf und darf sich nicht ändern.
	 *
	 * @param string               $templateId Template-ID (get_pdf).
	 * @param array<string, mixed> $params     URL-Parameter.
	 * @param string               $expires    Ablaufdatum Y-m-d, '' bei Nonce.
	 *
	 * @return string|null null, wenn ein signierter Parameter kein Skalar ist.
	 */
	public static function signaturePayload(string $templateId, array $params, string $expires = ''): ?string {
		$parts = ['v2', $templateId, $expires];
		foreach (self::SIGNED_PARAMS as $name) {
			$value = $params[$name] ?? '';
			if (!is_scalar($value)) {
				return null;
			}
			$parts[] = $name . '=' . rawurlencode(self::normalizeValue((string) $value));
		}
		return implode('|', $parts);
	}

	/**
	 * HMAC wie WordPress' wp_hash($data, 'auth'):
	 * hash_hmac('md5', $data, wp_salt('auth')). Das Secret liefert die
	 * Plattform; in WordPress ist es wp_salt('auth'), daher bleiben alle
	 * bestehenden Links gültig.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	public static function hmac(string $data): string {
		return hash_hmac('md5', $data, PdfRuntime::platform()->getSecret());
	}

	/**
	 * Schlüssel für einen signierten Langzeit-Link.
	 *
	 * @param string               $templateId
	 * @param array<string, mixed> $params      Bereits mit normalizeParamsForLink() normalisiert.
	 * @param string               $expires     Y-m-d.
	 *
	 * @return string|null null bei ungültigen Parametern.
	 */
	public static function longTermKey(string $templateId, array $params, string $expires): ?string {
		$payload = self::signaturePayload($templateId, $params, $expires);
		return $payload === null ? null : self::hmac($payload);
	}

	/**
	 * Signierten Langzeit-Link prüfen (key + expires).
	 *
	 * @param string               $templateId Bereits über resolveTemplateId() geprüft.
	 * @param array<string, mixed> $query      URL-Parameter (ohne Escape-Schrägstriche).
	 *
	 * @return bool
	 */
	public static function verifyLongTermKey(string $templateId, array $query): bool {
		$key     = $query['key'] ?? null;
		$dateStr = $query['expires'] ?? null;
		if (!is_string($key) || $key === '' || !is_string($dateStr) || $dateStr === '') {
			return false;
		}

		$timezone = PdfRuntime::platform()->getTimezone();
		$expires  = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr, $timezone);
		if (!$expires instanceof DateTimeImmutable || $expires->format('Y-m-d') !== $dateStr) {
			return false;
		}
		if ($expires < new DateTimeImmutable('today', $timezone)) {
			return false;
		}

		$params = self::signedParamsFromQuery($query);
		if ($params === null) {
			return false;
		}

		$expected = self::longTermKey($templateId, $params, $dateStr);
		if ($expected !== null && hash_equals($expected, $key)) {
			return true;
		}

		return self::verifyLegacyLongTermKey($templateId, $params, $dateStr, $key);
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
	 * define('TC_LIB_PDF_LEGACY_KEYS', false) (WordPress: wp-config.php).
	 *
	 * @param string                $templateId
	 * @param array<string, string> $params  Ergebnis von signedParamsFromQuery().
	 * @param string                $expires Y-m-d.
	 * @param string                $key
	 *
	 * @return bool
	 */
	private static function verifyLegacyLongTermKey(string $templateId, array $params, string $expires, string $key): bool {
		if (defined('TC_LIB_PDF_LEGACY_KEYS') && !constant('TC_LIB_PDF_LEGACY_KEYS')) {
			return false;
		}

		$nr = $params['nr'] ?? '';
		unset($params['nr']);
		if ($nr === '' || $params !== []) {
			return false;
		}

		return hash_equals(self::hmac($templateId . $expires . $nr), $key);
	}
}

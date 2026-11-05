<?php
/**
 * Plattform-Schnittstelle der PDF-Engine.
 *
 * Alles, was die Engine vom Host (WordPress, Admidio, CLI-Test) braucht,
 * läuft über diese Schnittstelle. Engine-Dateien in include/ rufen keine
 * Host-Funktionen direkt auf, sondern PdfRuntime::platform()->…
 *
 * Siehe KONZEPT-PLATTFORM.md, Abschnitt 2.2.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

/**
 * Host-Funktionen für die PDF-Engine.
 */
interface PdfPlatformInterface {

	/**
	 * Basisordner, unter dem die Templates ihre Speicherordner anlegen.
	 *
	 * WordPress: wp_upload_dir()['basedir'].
	 * Admidio:   ADMIDIO_PATH . FOLDER_DATA . '/plugins/wp-tc-lib-pdf'.
	 *
	 * @return string Absoluter Pfad ohne abschließenden Schrägstrich.
	 */
	public function getStorageBaseDir(): string;

	/**
	 * Ordner rekursiv anlegen.
	 *
	 * @param string $path Absoluter Pfad.
	 *
	 * @return bool true, wenn der Ordner danach existiert.
	 */
	public function createDirectory(string $path): bool;

	/**
	 * Ordner gegen direkten Abruf über HTTP schützen.
	 *
	 * WordPress: .htaccess + index.php. Admidio: nichts (adm_my_files ist
	 * bereits gesperrt).
	 *
	 * @param string $path Absoluter Pfad eines bestehenden Ordners.
	 *
	 * @return void
	 */
	public function protectDirectory(string $path): void;

	/**
	 * URL-Parameter des aktuellen Requests, ohne Escape-Schrägstriche.
	 *
	 * Die Autorisierung (Nonce, signierter Link) hat der Host-Dispatcher
	 * bereits geprüft. Die Werte sind nicht weiter gefiltert; Templates
	 * behandeln sie als Benutzereingabe.
	 *
	 * @return array<string, mixed>
	 */
	public function getQueryParams(): array;

	/**
	 * Zeitzone des Vereins für Datumsangaben in den PDFs.
	 *
	 * @return DateTimeZone
	 */
	public function getTimezone(): DateTimeZone;

	/**
	 * Geheimnis für signierte Langzeit-Links.
	 *
	 * WordPress: wp_salt('auth'), damit bestehende Links gültig bleiben.
	 *
	 * @return string
	 */
	public function getSecret(): string;

	/**
	 * Fehler ausgeben und den Request beenden.
	 *
	 * @param string $message Meldung für den Benutzer (wird vom Host escaped).
	 * @param int    $status  HTTP-Status.
	 *
	 * @return never
	 */
	public function fail(string $message, int $status = 500): never;

	/**
	 * Wurzelordner des Plugins (Repo-Root).
	 *
	 * @return string Absoluter Pfad ohne abschließenden Schrägstrich.
	 */
	public function getPluginDir(): string;

	/**
	 * tc-lib-pdf (Composer-Autoloader) und Font-Pfad laden.
	 *
	 * Idempotent. Wird von PdfRegistry::create() vor dem Laden eines
	 * Templates aufgerufen.
	 *
	 * @return bool true, wenn \Com\Tecnick\Pdf\Tcpdf verfügbar ist.
	 */
	public function loadLibrary(): bool;
}

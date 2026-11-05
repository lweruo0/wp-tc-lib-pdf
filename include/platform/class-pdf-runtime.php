<?php
/**
 * Statischer Halter der aktiven Plattform.
 *
 * Der Host setzt seine Plattform einmal beim Bootstrap:
 *
 *   PdfRuntime::setPlatform(new Tc_Lib_Pdf_Wp_Platform());
 *
 * Die Engine liest sie über PdfRuntime::platform().
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once __DIR__ . '/interface-pdf-platform.php';

/**
 * Zugriff auf die Plattform des Hosts.
 */
final class PdfRuntime {

	/** Die vom Host gesetzte Plattform. */
	private static ?PdfPlatformInterface $platform = null;

	/**
	 * Plattform setzen (einmal je Request, im Host-Bootstrap).
	 *
	 * @param PdfPlatformInterface $platform
	 *
	 * @return void
	 */
	public static function setPlatform(PdfPlatformInterface $platform): void {
		self::$platform = $platform;
	}

	/**
	 * Aktive Plattform.
	 *
	 * @return PdfPlatformInterface
	 *
	 * @throws LogicException Wenn der Host keine Plattform gesetzt hat.
	 */
	public static function platform(): PdfPlatformInterface {
		if (self::$platform === null) {
			throw new LogicException('wp-tc-lib-pdf: Keine Plattform gesetzt (PdfRuntime::setPlatform() im Host-Bootstrap aufrufen).');
		}
		return self::$platform;
	}

	/**
	 * Ist eine Plattform gesetzt?
	 *
	 * @return bool
	 */
	public static function hasPlatform(): bool {
		return self::$platform !== null;
	}
}

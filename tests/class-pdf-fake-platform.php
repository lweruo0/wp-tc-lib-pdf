<?php
/**
 * Plattform für CLI-Tests ohne WordPress und Admidio.
 *
 * Speicher in einem temporären Ordner, feste Zeitzone und festes Secret,
 * URL-Parameter frei setzbar, fail() wirft eine Ausnahme statt den Prozess
 * zu beenden.
 *
 * @package WordPress Plugin Template/Tests
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/platform/class-pdf-runtime.php';

/**
 * Testplattform.
 */
final class PdfFakePlatform implements PdfPlatformInterface {

	/** @var array<string, mixed> */
	private array $query = [];

	private string $storage;

	/**
	 * @param string|null $storage Speicherordner; null = neuer temporärer Ordner.
	 */
	public function __construct(?string $storage = null) {
		$this->storage = $storage ?? (sys_get_temp_dir() . '/tc-lib-pdf-test-' . getmypid());
	}

	/**
	 * URL-Parameter für den nächsten Template-Aufruf setzen.
	 *
	 * @param array<string, mixed> $query
	 *
	 * @return void
	 */
	public function setQueryParams(array $query): void {
		$this->query = $query;
	}

	public function getStorageBaseDir(): string {
		return $this->storage;
	}

	public function createDirectory(string $path): bool {
		return is_dir($path) || mkdir($path, 0777, true);
	}

	public function protectDirectory(string $path): void {
		// In Tests nicht nötig.
	}

	public function getQueryParams(): array {
		return $this->query;
	}

	public function getTimezone(): DateTimeZone {
		return new DateTimeZone('Europe/Berlin');
	}

	public function getSecret(): string {
		return 'test-secret';
	}

	public function fail(string $message, int $status = 500): never {
		throw new RuntimeException("fail({$status}): {$message}");
	}

	public function getPluginDir(): string {
		return rtrim(TC_LIB_PDF_DIR, '/\\');
	}

	public function loadLibrary(): bool {
		if (!class_exists('\Com\Tecnick\Pdf\Tcpdf')) {
			$autoload = TC_LIB_PDF_DIR . '/vendor/autoload.php';
			if (is_file($autoload)) {
				require_once $autoload;
			}
		}
		if (!defined('K_PATH_FONTS')) {
			define('K_PATH_FONTS', TC_LIB_PDF_DIR . '/font/core');
		}
		return class_exists('\Com\Tecnick\Pdf\Tcpdf');
	}

	/** @var array<string, mixed> Vereinsdaten wie Tc_Lib_Pdf_Adm_Platform::getAddressData() */
	private array $addressData = [];

	/**
	 * Vereinsdaten für Admidio-Templates setzen.
	 *
	 * @param array<string, mixed> $data
	 */
	public function setAddressData(array $data): void {
		$this->addressData = $data;
	}

	/**
	 * Vereinsdaten (wie Tc_Lib_Pdf_Adm_Platform).
	 *
	 * @return array<string, mixed>
	 */
	public function getAddressData(): array {
		return $this->addressData;
	}
}

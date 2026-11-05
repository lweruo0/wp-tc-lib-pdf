<?php
/**
 * Plattform Admidio für die PDF-Engine.
 *
 * Ablage unter adm_my_files/plugins/wp-tc-lib-pdf (per HTTP gesperrt), Zeitzone aus $gTimezone,
 * Secret für signierte Links aus adm_my_files/plugins/wp-tc-lib-pdf/config.php.
 *
 * Für das Erzeugen im laufenden Request eines anderen Plugins setzt runWithParams() die
 * URL-Parameter, die das Template liest; fail() wirft dann eine Ausnahme statt den Request zu beenden.
 *
 * @package WordPress Plugin Template/Admidio
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/platform/class-pdf-runtime.php';

final class Tc_Lib_Pdf_Adm_Platform implements PdfPlatformInterface {

	/** @var array<string, mixed>|null URL-Parameter für den laufenden Aufruf (null = $_GET) */
	private ?array $queryOverride = null;

	private bool $libraryLoaded = false;

	/**
	 * @param array<string, mixed> $config Inhalt der Konfigurationsdatei (siehe tc_lib_pdf_adm_config_file()).
	 */
	public function __construct(private readonly array $config) {
	}

	/**
	 * Callback mit festen URL-Parametern ausführen (Erzeugen im laufenden Request).
	 *
	 * @template T
	 * @param array<string, mixed> $params
	 * @param callable(): T        $callback
	 *
	 * @return T
	 */
	public function runWithParams(array $params, callable $callback): mixed {
		$previous            = $this->queryOverride;
		$this->queryOverride = $params;
		try {
			return $callback();
		} finally {
			$this->queryOverride = $previous;
		}
	}

	/**
	 * Parameter für den Dispatcher setzen (z. B. ohne "new" für signierte Links).
	 *
	 * @param array<string, mixed> $params
	 */
	public function setQueryParams(array $params): void {
		$this->queryOverride = $params;
	}

	public function getStorageBaseDir(): string {
		$dir = trim((string) ($this->config['storage_dir'] ?? ''));
		if ($dir === '') {
			$dir = ADMIDIO_PATH . FOLDER_DATA . '/plugins/wp-tc-lib-pdf';
		}
		return rtrim($dir, '/\\');
	}

	public function createDirectory(string $path): bool {
		if (is_dir($path)) {
			return true;
		}
		if (!@mkdir($path, 0770, true) && !is_dir($path)) {
			return false;
		}
		$this->protectDirectory($path);
		return true;
	}

	public function protectDirectory(string $path): void {
		// adm_my_files ist bereits gesperrt; zusätzlich für einen abweichenden storage_dir.
		$htaccess = rtrim($path, '/\\') . '/.htaccess';
		if (is_dir($path) && !file_exists($htaccess)) {
			@file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
		}
	}

	public function getQueryParams(): array {
		$params = $this->queryOverride ?? $_GET;
		// nur skalare Werte (Arrays aus der URL ignorieren)
		return array_filter($params, static fn ($value): bool => is_scalar($value));
	}

	public function getTimezone(): DateTimeZone {
		$name = (string) ($GLOBALS['gTimezone'] ?? '');
		try {
			return new DateTimeZone($name !== '' ? $name : date_default_timezone_get());
		} catch (Exception $e) {
			return new DateTimeZone('Europe/Berlin');
		}
	}

	public function getSecret(): string {
		$secret = trim((string) ($this->config['secret'] ?? ''));
		if (strlen($secret) < 32) {
			throw new RuntimeException('wp-tc-lib-pdf: secret in der config.php (adm_my_files/plugins/wp-tc-lib-pdf) fehlt oder ist zu kurz (mindestens 32 Zeichen).');
		}
		return $secret;
	}

	public function fail(string $message, int $status = 500): never {
		if (!defined('TC_LIB_PDF_ADM_DISPATCH')) {
			// im laufenden Request eines anderen Plugins: der Aufrufer entscheidet
			throw new RuntimeException($message);
		}
		if (!headers_sent()) {
			http_response_code($status);
		}
		if (isset($GLOBALS['gMessage']) && is_object($GLOBALS['gMessage'])) {
			$GLOBALS['gMessage']->show(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
		} else {
			echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
		}
		exit;
	}

	public function getPluginDir(): string {
		return rtrim(TC_LIB_PDF_DIR, '/\\');
	}

	public function loadLibrary(): bool {
		if ($this->libraryLoaded) {
			return class_exists('\Com\Tecnick\Pdf\Tcpdf');
		}
		$this->libraryLoaded = true;
		if (PHP_VERSION_ID < 80500) {
			return false;
		}
		$autoload = TC_LIB_PDF_DIR . '/vendor/autoload.php';
		if (!is_file($autoload)) {
			return false;
		}
		require_once $autoload;
		if (!defined('K_PATH_FONTS')) {
			define('K_PATH_FONTS', TC_LIB_PDF_DIR . '/font/core');
		}
		return class_exists('\Com\Tecnick\Pdf\Tcpdf');
	}

	/**
	 * Vereinsdaten aus config.php (Schlüssel wie bfv_adressen in WordPress).
	 *
	 * @return array<string, mixed>
	 */
	public function getAddressData(): array {
		$data = $this->config['verein'] ?? [];
		return is_array($data) ? $data : [];
	}

	/**
	 * Ordner des Plugins Mitgliedsbeitrag unter adm_plugins (Server: MembershipFee).
	 */
	public function getMembershipFeeDir(): string {
		$dir = basename((string) ($this->config['membershipfee_dir'] ?? 'MembershipFee'));
		return ADMIDIO_PATH . FOLDER_PLUGINS . '/' . ($dir !== '' ? $dir : 'MembershipFee');
	}
}

<?php
/**
 * Vorhandene PDF-Dateien zu einem Dokument zusammenfügen (Druckstapel).
 *
 * Plattformneutral: kennt weder Rechnungen noch WordPress oder Admidio.
 * Rechte, CSRF-Schutz, Auswahl der Dateien und die Auslieferung macht der
 * Aufrufer (KONZEPT-ADMIDIO-RECHNUNGEN.md 5.3).
 *
 * Sicherheit im Joiner selbst (gilt auch, wenn ein Aufrufer unsauber ist):
 * - Jede Datei muss nach realpath() unterhalb des Wurzelordners liegen
 *   (Standard: Ablageordner der Plattform). ../, Symlinks nach außen und
 *   fremde Ordner werden abgelehnt.
 * - Nur reguläre Dateien mit Endung .pdf, die mit "%PDF-" beginnen.
 * - Grenzen für Dateianzahl, Dateigröße, Gesamtgröße und Seitenzahl.
 * - Fehlermeldungen nennen nur den Dateinamen, nie den Pfad.
 * - Nichts wird gespeichert; das Ergebnis ist ein String.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once __DIR__ . '/platform/class-pdf-runtime.php';

/**
 * Fehler beim Zusammenfügen, bezogen auf eine Datei (oder auf den Aufruf).
 */
class PdfJoinException extends RuntimeException {

	/** Dateiname ohne Pfad, leer wenn sich der Fehler nicht auf eine Datei bezieht. */
	private string $fileName;

	public function __construct(string $message, string $fileName = '') {
		parent::__construct($message);
		$this->fileName = $fileName;
	}

	/**
	 * Dateiname ohne Pfad.
	 *
	 * @return string
	 */
	public function getFileName(): string {
		return $this->fileName;
	}
}

/**
 * Eine Grenze (Anzahl, Größe, Seiten) ist überschritten. Wird auch mit
 * skip_invalid nie übersprungen.
 */
class PdfJoinLimitException extends PdfJoinException {
}

/**
 * Ergebnis von PdfJoiner::join().
 */
final class PdfJoinResult {

	/**
	 * @param string       $pdf     Zusammengefügtes PDF (Binärstring).
	 * @param int          $pages   Seitenzahl inklusive Leerseiten.
	 * @param int          $files   Anzahl übernommener Dateien.
	 * @param list<string> $skipped Übersprungene Dateien: "dateiname.pdf: Grund".
	 */
	public function __construct(
		public readonly string $pdf,
		public readonly int $pages,
		public readonly int $files,
		public readonly array $skipped,
	) {
	}
}

/**
 * PDF-Join.
 *
 * ```php
 * $result = PdfJoiner::join($pfade, ['duplex' => true, 'title' => 'Druckstapel']);
 * // $result->pdf ausliefern; $result->skipped dem Benutzer melden.
 * ```
 */
final class PdfJoiner {

	/** Obergrenzen; per Option nur kleiner einstellbar. */
	public const MAX_FILES       = 500;
	public const MAX_FILE_BYTES  = 20 * 1024 * 1024;
	public const MAX_TOTAL_BYTES = 200 * 1024 * 1024;
	public const MAX_PAGES       = 2000;

	/**
	 * Fügt vorhandene PDF-Dateien in der übergebenen Reihenfolge zusammen.
	 *
	 * Optionen:
	 * - duplex (bool, false): nach Dokumenten mit ungerader Seitenzahl eine
	 *   Leerseite, damit jedes Dokument auf einer Vorderseite beginnt.
	 * - title (string, 'Druckstapel'): Titel in den PDF-Metadaten.
	 * - skip_invalid (bool, false): ungültige Dateien überspringen statt eine
	 *   Ausnahme zu werfen; sie stehen dann in PdfJoinResult::$skipped.
	 *   Grenzen werden nie übersprungen.
	 * - root (string): Wurzelordner, unter dem alle Dateien liegen müssen.
	 *   Standard: Ablageordner der Plattform (getStorageBaseDir()).
	 * - max_files, max_file_bytes, max_total_bytes, max_pages (int): kleinere
	 *   Grenzen als die Konstanten.
	 *
	 * @param array<mixed>         $files   Pfade der PDF-Dateien.
	 * @param array<string, mixed> $options Siehe oben.
	 *
	 * @return PdfJoinResult
	 *
	 * @throws PdfJoinLimitException Grenze überschritten.
	 * @throws PdfJoinException      Ungültiger Aufruf, ungültige Datei (ohne
	 *                               skip_invalid) oder keine gültige Datei.
	 */
	public static function join(array $files, array $options = []): PdfJoinResult {
		$duplex      = (bool) ($options['duplex'] ?? false);
		$title       = (string) ($options['title'] ?? 'Druckstapel');
		$skipInvalid = (bool) ($options['skip_invalid'] ?? false);
		$maxFiles    = self::limit($options, 'max_files', self::MAX_FILES);
		$maxFile     = self::limit($options, 'max_file_bytes', self::MAX_FILE_BYTES);
		$maxTotal    = self::limit($options, 'max_total_bytes', self::MAX_TOTAL_BYTES);
		$maxPages    = self::limit($options, 'max_pages', self::MAX_PAGES);

		if ($files === []) {
			throw new PdfJoinException('Keine Dateien übergeben.');
		}
		if (count($files) > $maxFiles) {
			throw new PdfJoinLimitException(sprintf('Zu viele Dateien: %d, erlaubt sind %d.', count($files), $maxFiles));
		}

		$platform = PdfRuntime::platform();
		$root     = self::resolveRoot((string) ($options['root'] ?? $platform->getStorageBaseDir()));

		// 1. Alle Pfade prüfen, bevor etwas gelesen wird.
		$valid   = [];
		$skipped = [];
		$total   = 0;
		foreach ($files as $file) {
			try {
				[$real, $size] = self::checkFile($file, $root, $maxFile);
			} catch (PdfJoinLimitException $e) {
				throw $e;
			} catch (PdfJoinException $e) {
				if (!$skipInvalid) {
					throw $e;
				}
				$skipped[] = self::describe($e);
				continue;
			}
			$total += $size;
			if ($total > $maxTotal) {
				throw new PdfJoinLimitException('Dateien zusammen größer als ' . self::formatBytes($maxTotal) . '.');
			}
			$valid[] = $real;
		}

		if (!$platform->loadLibrary()) {
			throw new PdfJoinException('PDF-Bibliothek nicht verfügbar.');
		}

		// 2. Zusammenfügen.
		$pdf = new \Com\Tecnick\Pdf\Tcpdf();
		$pdf->enableDefaultPageContent(false);
		$pdf->setTitle($title);
		$pdf->setSubject('');
		$pdf->setAuthor('');
		$pdf->setCreator('wp-tc-lib-pdf');

		$pages = 0;
		$count = 0;
		foreach ($valid as $real) {
			$name = basename($real);
			$data = file_get_contents($real);
			if ($data === false || !str_starts_with($data, '%PDF-')) {
				$e = new PdfJoinException('keine PDF-Datei', $name);
				if (!$skipInvalid) {
					throw $e;
				}
				$skipped[] = self::describe($e);
				continue;
			}

			try {
				$sourceId  = $pdf->setImportSourceData($data, ['strict_limits' => true]);
				$pageCount = $pdf->getSourcePageCount($sourceId);
			} catch (Throwable $t) {
				$e = new PdfJoinException('PDF nicht lesbar', $name);
				if (!$skipInvalid) {
					throw $e;
				}
				$skipped[] = self::describe($e);
				continue;
			}
			unset($data);

			if ($pageCount < 1) {
				$e = new PdfJoinException('PDF ohne Seiten', $name);
				if (!$skipInvalid) {
					throw $e;
				}
				$skipped[] = self::describe($e);
				continue;
			}

			$blank = $duplex && $pageCount % 2 === 1;
			if ($pages + $pageCount + ($blank ? 1 : 0) > $maxPages) {
				throw new PdfJoinLimitException(sprintf('Mehr als %d Seiten.', $maxPages));
			}

			$width  = 0.0;
			$height = 0.0;
			try {
				for ($n = 1; $n <= $pageCount; $n++) {
					$tpl    = $pdf->addPageFromImport($sourceId, $n);
					$width  = $pdf->toUnit($tpl->getWidth());
					$height = $pdf->toUnit($tpl->getHeight());
				}
			} catch (Throwable $t) {
				// Seiten können schon angehängt sein; das Ergebnis wäre unvollständig.
				throw new PdfJoinException('PDF nicht lesbar', $name);
			}
			$pages += $pageCount;

			if ($blank) {
				$pdf->addPage([
					'format'      => '',
					'width'       => $width,
					'height'      => $height,
					'orientation' => $width > $height ? 'L' : 'P',
				]);
				$pages++;
			}
			$count++;
		}

		if ($count === 0) {
			throw new PdfJoinException('Keine gültige PDF-Datei.');
		}

		return new PdfJoinResult($pdf->getOutPDFString(), $pages, $count, $skipped);
	}

	/**
	 * Wurzelordner auflösen und prüfen.
	 *
	 * @return string Realer Pfad ohne abschließenden Trenner.
	 *
	 * @throws PdfJoinException
	 */
	private static function resolveRoot(string $root): string {
		if (trim($root) === '') {
			throw new PdfJoinException('Kein Ablageordner konfiguriert.');
		}
		$real = realpath($root);
		if ($real === false || !is_dir($real)) {
			throw new PdfJoinException('Ablageordner nicht vorhanden.');
		}
		$real = rtrim($real, '/\\');
		// Ein Laufwerks- oder Dateisystem-Wurzelordner ist nie ein Ablageordner.
		if ($real === '' || dirname($real) === $real || preg_match('/^[A-Za-z]:$/', $real) === 1) {
			throw new PdfJoinException('Ablageordner ungültig.');
		}
		return $real;
	}

	/**
	 * Eine Datei prüfen (Lage, Typ, Endung, Größe). Liest nur den Anfang.
	 *
	 * @param mixed  $file
	 * @param string $root    Realer Wurzelordner.
	 * @param int    $maxFile Größte erlaubte Datei in Byte.
	 *
	 * @return array{0: string, 1: int} Realer Pfad und Größe.
	 *
	 * @throws PdfJoinException
	 */
	private static function checkFile(mixed $file, string $root, int $maxFile): array {
		if (!is_string($file) || $file === '' || str_contains($file, "\0")) {
			throw new PdfJoinException('ungültiger Pfad');
		}
		$name = basename(str_replace('\\', '/', $file));

		$real = realpath($file);
		if ($real === false) {
			throw new PdfJoinException('nicht gefunden', $name);
		}
		if (!self::isInside($real, $root)) {
			throw new PdfJoinException('liegt nicht im Ablageordner', $name);
		}
		if (!is_file($real) || !is_readable($real)) {
			throw new PdfJoinException('nicht lesbar', $name);
		}
		if (strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'pdf') {
			throw new PdfJoinException('keine .pdf-Datei', $name);
		}

		$size = filesize($real);
		if ($size === false || $size === 0) {
			throw new PdfJoinException('leer', $name);
		}
		if ($size > $maxFile) {
			throw new PdfJoinLimitException('größer als ' . self::formatBytes($maxFile), $name);
		}

		$handle = fopen($real, 'rb');
		$head   = $handle === false ? false : fread($handle, 5);
		if ($handle !== false) {
			fclose($handle);
		}
		if ($head !== '%PDF-') {
			throw new PdfJoinException('keine PDF-Datei', $name);
		}

		return [$real, $size];
	}

	/**
	 * Liegt der (reale) Pfad unterhalb des Wurzelordners?
	 */
	private static function isInside(string $real, string $root): bool {
		$sep = DIRECTORY_SEPARATOR;
		if (PHP_OS_FAMILY === 'Windows') {
			$real = strtolower(str_replace('/', '\\', $real));
			$root = strtolower(str_replace('/', '\\', $root));
			$sep  = '\\';
		}
		return str_starts_with($real, $root . $sep);
	}

	/**
	 * Option als Grenze lesen: nur positive Werte, nie größer als die Konstante.
	 *
	 * @param array<string, mixed> $options
	 */
	private static function limit(array $options, string $key, int $max): int {
		$value = isset($options[$key]) && is_numeric($options[$key]) ? (int) $options[$key] : $max;
		return ($value > 0 && $value < $max) ? $value : $max;
	}

	/**
	 * Größe lesbar: "20 MB", "512 KB", "100 Byte".
	 */
	private static function formatBytes(int $bytes): string {
		if ($bytes >= 1024 * 1024) {
			return round($bytes / (1024 * 1024), 1) . ' MB';
		}
		return $bytes >= 1024 ? round($bytes / 1024, 1) . ' KB' : $bytes . ' Byte';
	}

	/**
	 * Eintrag für die Liste der übersprungenen Dateien.
	 */
	private static function describe(PdfJoinException $e): string {
		$name = $e->getFileName();
		return ($name !== '' ? $name . ': ' : '') . $e->getMessage();
	}
}

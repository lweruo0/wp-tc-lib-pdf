<?php
/**
 * Rendert alle Rechnungs- und Mahnungs-Templates ohne WordPress und
 * vergleicht den Text mit gespeicherten Snapshots.
 *
 * Aufruf:
 *   php tests/render-rechnungen.php            vergleichen (Exit 1 bei Abweichung)
 *   php tests/render-rechnungen.php --update   Snapshots neu schreiben
 *
 * Voraussetzungen: PHP 8.5 (vendor/), `pdftotext` (poppler-utils) für den
 * Textvergleich. Ohne pdftotext werden nur die PDFs erzeugt.
 *
 * Ausgabe: PDFs und Texte in tests/out/ (gitignored), Snapshots in
 * tests/snapshots/*.txt (versioniert).
 *
 * Die Templates aus wp-include/ rufen get_option() auf; dafür stellt dieses
 * Skript eine Minimalfassung bereit, die aus tests/fixtures-rechnungen.php
 * liest. Alle übrigen Host-Zugriffe laufen über PdfFakePlatform.
 *
 * Datumswerte, die vom heutigen Tag abhängen (Mahnungsdatum, neue Frist),
 * werden im Text durch <HEUTE> und <HEUTE+n> ersetzt.
 *
 * @package WordPress Plugin Template/Tests
 */

declare(strict_types=1);

// Nur auf der Kommandozeile; nie über den Webserver ausführen.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

define('TC_LIB_PDF_DIR', dirname(__DIR__));
define('TC_LIB_PDF_BOOTSTRAPPED', true);
define('ABSPATH', TC_LIB_PDF_DIR . '/'); // Die WordPress-Templates prüfen ABSPATH.

$update   = in_array('--update', $argv, true);
$fixtures = require __DIR__ . '/fixtures-rechnungen.php';

// --- Minimal-WordPress für die Templates in wp-include/templates/ -------------
if (!function_exists('get_option')) {
	function get_option(string $name, mixed $default = false): mixed {
		global $fixtures;
		return $fixtures['options'][$name] ?? $default;
	}
}

require_once __DIR__ . '/class-pdf-fake-platform.php';
require_once TC_LIB_PDF_DIR . '/include/class-pdf-registry.php';

$outDir   = __DIR__ . '/out';
$platform = new PdfFakePlatform($outDir . '/storage');
PdfRuntime::setPlatform($platform);

// Registrierung wie in WordPress.
require TC_LIB_PDF_DIR . '/wp-include/templates.php';

$pdftotext = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where pdftotext 2>NUL' : 'command -v pdftotext 2>/dev/null'));
if ($pdftotext === '') {
	fwrite(STDOUT, "Hinweis: pdftotext nicht gefunden, es werden nur PDFs erzeugt (kein Textvergleich).\n");
}

/**
 * Datumsangaben relativ zu heute durch Platzhalter ersetzen.
 */
function normalize_text(string $text, DateTimeZone $tz): string {
	$today = new DateTimeImmutable('today', $tz);
	$map   = [];
	foreach ([0, 7, 14] as $days) {
		$map[$today->modify("+{$days} days")->format('d.m.Y')] = $days === 0 ? '<HEUTE>' : "<HEUTE+{$days}>";
	}
	$text = strtr($text, $map);
	// Zeilenenden und Leerraum am Zeilenende vereinheitlichen.
	$text = preg_replace("/[ \t]+\n/", "\n", str_replace("\r\n", "\n", $text));
	return rtrim((string) $text) . "\n";
}

$cases = $fixtures['cases'];
foreach ($fixtures['mahnungen'] as $id => $rechnungId) {
	$cases[$id] = $cases[$rechnungId];
}
ksort($cases);

if (!is_dir($outDir)) {
	mkdir($outDir, 0777, true);
}

$failed = 0;
foreach ($cases as $id => [$query, $formdata]) {
	$platform->setQueryParams($query);
	try {
		$pdf = PdfRegistry::create($id);
		if ($pdf === null) {
			throw new RuntimeException('nicht registriert');
		}
		$pdf->setFormdata($formdata);
		if (!$pdf->save()) {
			throw new RuntimeException('save() fehlgeschlagen');
		}
		$file = $pdf->getFileNameAbs();
		$target = "{$outDir}/{$id}.pdf";
		copy($file, $target);
	} catch (Throwable $e) {
		fwrite(STDOUT, sprintf("FEHLER   %-32s %s\n", $id, $e->getMessage()));
		$failed++;
		continue;
	}

	if ($pdftotext === '') {
		fwrite(STDOUT, sprintf("PDF      %-32s %s\n", $id, $target));
		continue;
	}

	$cmd  = escapeshellarg($pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg($target) . ' -';
	$text = normalize_text((string) shell_exec($cmd), $platform->getTimezone());
	file_put_contents("{$outDir}/{$id}.txt", $text);

	$snapshot = __DIR__ . "/snapshots/{$id}.txt";
	if ($update || !is_file($snapshot)) {
		file_put_contents($snapshot, $text);
		fwrite(STDOUT, sprintf("NEU      %-32s Snapshot geschrieben\n", $id));
		continue;
	}

	if (file_get_contents($snapshot) === $text) {
		fwrite(STDOUT, sprintf("OK       %-32s\n", $id));
		continue;
	}

	$failed++;
	fwrite(STDOUT, sprintf("ANDERS   %-32s vergleiche tests/snapshots/%s.txt mit tests/out/%s.txt\n", $id, $id, $id));
	$a = explode("\n", (string) file_get_contents($snapshot));
	$b = explode("\n", $text);
	$shown = 0;
	for ($i = 0, $max = max(count($a), count($b)); $i < $max && $shown < 8; $i++) {
		if (($a[$i] ?? '') !== ($b[$i] ?? '')) {
			fwrite(STDOUT, sprintf("         Zeile %d\n         - %s\n         + %s\n", $i + 1, trim($a[$i] ?? ''), trim($b[$i] ?? '')));
			$shown++;
		}
	}
}

fwrite(STDOUT, $failed === 0 ? "Alle Rechnungs-Templates unverändert.\n" : "{$failed} Abweichung(en) oder Fehler.\n");
exit($failed === 0 ? 0 : 1);

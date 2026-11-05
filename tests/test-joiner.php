<?php
/**
 * Prüft PdfJoiner: normaler Join, Duplex-Leerseiten und alle Ablehnungen
 * (../, Symlink nach außen, fremder Ordner, falsche Endung, Nicht-PDF,
 * Grenzen, keine Pfade in Fehlermeldungen).
 *
 * Aufruf: php tests/test-joiner.php   (Exit 1 bei Fehler)
 *
 * @package WordPress Plugin Template/Tests
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

define('TC_LIB_PDF_DIR', dirname(__DIR__));
define('TC_LIB_PDF_BOOTSTRAPPED', true);

require_once __DIR__ . '/class-pdf-fake-platform.php';
require_once TC_LIB_PDF_DIR . '/include/class-pdf-joiner.php';

// --- Testordner ---------------------------------------------------------------
$base    = sys_get_temp_dir() . '/tc-lib-pdf-test-joiner-' . getmypid();
$storage = $base . '/storage';
$outside = $base . '/outside';
foreach ([$storage . '/beitrag', $outside] as $dir) {
	mkdir($dir, 0777, true);
}

$platform = new PdfFakePlatform($storage);
PdfRuntime::setPlatform($platform);
if (!$platform->loadLibrary()) {
	fwrite(STDOUT, "tc-lib-pdf nicht verfügbar (vendor/).\n");
	exit(1);
}

/** Einfaches PDF mit $pages Seiten. */
function make_pdf(int $pages): string {
	$pdf = new \Com\Tecnick\Pdf\Tcpdf();
	$pdf->enableDefaultPageContent(false);
	for ($i = 1; $i <= $pages; $i++) {
		$pdf->addPage();
	}
	return $pdf->getOutPDFString();
}

/** Seitenzahl eines PDF-Strings. */
function count_pages(string $data): int {
	$pdf = new \Com\Tecnick\Pdf\Tcpdf();
	return $pdf->getSourcePageCount($pdf->setImportSourceData($data));
}

$one   = $storage . '/beitrag/rechnung_2026-B-0001.pdf';
$two   = $storage . '/beitrag/rechnung_2026-B-0002.pdf';
$three = $storage . '/beitrag/mahnung_2026-B-0003.pdf';
file_put_contents($one, make_pdf(1));
file_put_contents($two, make_pdf(2));
file_put_contents($three, make_pdf(3));

$foreign = $outside . '/geheim.pdf';
file_put_contents($foreign, make_pdf(1));
$fake = $storage . '/beitrag/falsch.pdf';
file_put_contents($fake, "<?php echo 'kein PDF';");
$txt = $storage . '/beitrag/rechnung.txt';
copy($one, $txt);
$empty = $storage . '/beitrag/leer.pdf';
touch($empty);
$broken = $storage . '/beitrag/kaputt.pdf';
file_put_contents($broken, "%PDF-1.7\nkein gültiger Inhalt\n%%EOF\n");

$link = $storage . '/beitrag/link.pdf';
$hasLink = @symlink($foreign, $link);

// --- Hilfen ------------------------------------------------------------------
$failed = 0;
$checks = 0;

function check(bool $ok, string $label): void {
	global $failed, $checks;
	$checks++;
	fwrite(STDOUT, ($ok ? 'OK       ' : 'FEHLER   ') . $label . "\n");
	if (!$ok) {
		$failed++;
	}
}

/**
 * Erwartet eine Ausnahme der Klasse $class; prüft, dass keine Pfade in der
 * Meldung stehen.
 *
 * @param array<mixed>         $files
 * @param array<string, mixed> $options
 */
function expect_reject(array $files, array $options, string $class, string $label, string $fileName = ''): void {
	global $base;
	try {
		PdfJoiner::join($files, $options);
		check(false, "{$label} (keine Ausnahme)");
	} catch (PdfJoinException $e) {
		$ok = $e instanceof $class
			&& !str_contains($e->getMessage(), $base)
			&& !str_contains($e->getFileName(), '/')
			&& ($fileName === '' || $e->getFileName() === $fileName);
		check($ok, "{$label}: " . ($e->getFileName() !== '' ? $e->getFileName() . ': ' : '') . $e->getMessage());
	}
}

// --- Normaler Join -------------------------------------------------------------
$r = PdfJoiner::join([$one, $two, $three]);
check($r->pages === 6 && $r->files === 3 && $r->skipped === [], 'Join 1+2+3 Seiten = 6');
check(str_starts_with($r->pdf, '%PDF-') && count_pages($r->pdf) === 6, 'Ergebnis ist ein PDF mit 6 Seiten');
check(str_contains($r->pdf, 'Druckstapel'), 'Titel in den Metadaten');

$r = PdfJoiner::join([$one, $two, $three], ['duplex' => true]);
check($r->pages === 8 && count_pages($r->pdf) === 8, 'Duplex: 1+Leer, 2, 3+Leer = 8');

$r = PdfJoiner::join([$storage . '/beitrag/../beitrag/' . basename($two)]);
check($r->pages === 2, '../ innerhalb des Ablageordners ist erlaubt');

$r = PdfJoiner::join([$one, $fake, $foreign, $two], ['skip_invalid' => true]);
check(
	$r->files === 2
	&& $r->skipped === ['falsch.pdf: keine PDF-Datei', 'geheim.pdf: liegt nicht im Ablageordner'],
	'skip_invalid überspringt und meldet nur Dateinamen'
);

// --- Ablehnungen -------------------------------------------------------------
expect_reject([$foreign], [], PdfJoinException::class, 'Datei außerhalb des Ablageordners', 'geheim.pdf');
expect_reject([$storage . '/beitrag/../../outside/geheim.pdf'], [], PdfJoinException::class, '../ aus dem Ablageordner heraus', 'geheim.pdf');
if ($hasLink) {
	expect_reject([$link], [], PdfJoinException::class, 'Symlink nach außen', 'link.pdf');
} else {
	fwrite(STDOUT, "HINWEIS  Symlink-Test übersprungen (symlink() nicht möglich)\n");
}
expect_reject([$fake], [], PdfJoinException::class, 'Nicht-PDF mit Endung .pdf', 'falsch.pdf');
expect_reject([$txt], [], PdfJoinException::class, 'PDF mit falscher Endung', 'rechnung.txt');
expect_reject([$empty], [], PdfJoinException::class, 'Leere Datei', 'leer.pdf');
expect_reject([$broken], [], PdfJoinException::class, 'Kaputtes PDF', 'kaputt.pdf');
expect_reject([$storage . '/beitrag/fehlt.pdf'], [], PdfJoinException::class, 'Datei fehlt', 'fehlt.pdf');
expect_reject([$storage . '/beitrag'], [], PdfJoinException::class, 'Ordner statt Datei');
expect_reject(["{$one}\0.pdf"], [], PdfJoinException::class, 'Nullbyte im Pfad');
expect_reject([42], [], PdfJoinException::class, 'Pfad ist kein String');
expect_reject([], [], PdfJoinException::class, 'Leere Liste');
expect_reject([$fake], ['skip_invalid' => true], PdfJoinException::class, 'Nur ungültige Dateien');
expect_reject([$one], ['root' => $base . '/gibtsnicht'], PdfJoinException::class, 'Wurzelordner fehlt');
expect_reject([$one], ['root' => '/'], PdfJoinException::class, 'Dateisystem-Wurzel als Wurzelordner');
$other = $storage . '/andere.pdf';
copy($one, $other);
expect_reject([$other], ['root' => $storage . '/beitrag'], PdfJoinException::class, 'Option root schränkt auf Unterordner ein', 'andere.pdf');

// --- Grenzen -----------------------------------------------------------------
expect_reject([$one, $two], ['max_files' => 1], PdfJoinLimitException::class, 'Grenze Dateianzahl');
expect_reject([$one, $two, $three], ['max_pages' => 5], PdfJoinLimitException::class, 'Grenze Seiten');
expect_reject([$one, $two], ['max_pages' => 3, 'duplex' => true], PdfJoinLimitException::class, 'Grenze Seiten mit Duplex');
expect_reject([$one], ['max_file_bytes' => 100], PdfJoinLimitException::class, 'Grenze Dateigröße');
expect_reject([$one, $two], ['max_total_bytes' => (int) filesize($one) + 10], PdfJoinLimitException::class, 'Grenze Gesamtgröße');
expect_reject([$one, $one, $two], ['max_files' => 2, 'skip_invalid' => true], PdfJoinLimitException::class, 'Grenzen auch mit skip_invalid');
$r = PdfJoiner::join([$one], ['max_files' => 999999, 'max_pages' => -5]);
check($r->pages === 1, 'Optionen können Grenzen nicht erhöhen oder aufheben');

// --- Aufräumen -----------------------------------------------------------------
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
	$f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
rmdir($base);

fwrite(STDOUT, $failed === 0 ? "Alle {$checks} Joiner-Prüfungen bestanden.\n" : "{$failed} von {$checks} Prüfungen fehlgeschlagen.\n");
exit($failed === 0 ? 0 : 1);

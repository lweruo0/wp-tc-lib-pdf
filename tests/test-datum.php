<?php
/**
 * Prüft die Umrechnung von Zeitstempeln in die Vereinszeitzone
 * (PdfRechnungBase::localDateTime(), ::now()).
 *
 * Aufruf: php tests/test-datum.php   (Exit 1 bei Fehler)
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
$platform = new PdfFakePlatform(sys_get_temp_dir() . '/tc-lib-pdf-test-datum');
PdfRuntime::setPlatform($platform);
if (!$platform->loadLibrary()) {
	fwrite(STDOUT, "tc-lib-pdf nicht verfügbar (vendor/).\n");
	exit(1);
}
require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/** Testklasse, die die geschützten Hilfsfunktionen öffnet. */
final class PdfDatumTest extends PdfRechnungBase {
	protected function loadData(): void {
	}
	public function local(mixed $v): ?string {
		return $this->localDateTime($v)?->format('d.m.Y H:i');
	}
	public function today(): string {
		return $this->now()->format('d.m.Y');
	}
}

$t = new PdfDatumTest();
$cases = [
	// [Eingabe, erwartet (Europe/Berlin)]
	['2026-10-02 22:01:00', '03.10.2026 00:01'],       // Quform UTC, Sommerzeit
	['2025-10-24 22:19:00', '25.10.2025 00:19'],       // Mitgliedsantrag eid 12706
	['2026-01-14 23:30:00', '15.01.2026 00:30'],       // Winterzeit
	['2026-07-01 10:00:00', '01.07.2026 12:00'],       // tagsüber, gleiches Datum
	['2026-05-01T14:00:00+02:00', '01.05.2026 14:00'], // mit Offset (Hütte)
	['2026-05-01T22:30:00Z', '02.05.2026 00:30'],      // UTC mit Z
	['2026-07-01', '01.07.2026 02:00'],                // nur Datum: UTC-Mitternacht
	['', null],
	['kein Datum', null],
	[null, null],
];

$failed = 0;
foreach ($cases as [$in, $expected]) {
	$got = $t->local($in);
	$ok  = $got === $expected;
	$failed += $ok ? 0 : 1;
	fwrite(STDOUT, sprintf("%s  %-28s → %-17s%s\n", $ok ? 'OK    ' : 'FEHLER', var_export($in, true), var_export($got, true), $ok ? '' : ' erwartet ' . var_export($expected, true)));
}

$expectedToday = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('d.m.Y');
$okToday = $t->today() === $expectedToday;
$failed += $okToday ? 0 : 1;
fwrite(STDOUT, ($okToday ? 'OK      ' : 'FEHLER  ') . "now() = {$t->today()} (Europe/Berlin)\n");

fwrite(STDOUT, $failed === 0 ? "Alle Datumsprüfungen bestanden.\n" : "{$failed} Fehler.\n");
exit($failed === 0 ? 0 : 1);

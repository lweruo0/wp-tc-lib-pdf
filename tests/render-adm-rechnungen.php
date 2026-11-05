<?php
/**
 * Rendert die Admidio-Rechnungstemplates (adm-include/templates/) ohne Admidio und vergleicht den Text
 * mit Snapshots – wie tests/render-rechnungen.php für WordPress.
 *
 * Aufruf:
 *   php tests/render-adm-rechnungen.php            vergleichen (Exit 1 bei Abweichung)
 *   php tests/render-adm-rechnungen.php --update   Snapshots neu schreiben
 *
 * Die Daten, die sonst das Plugin Mitgliedsbeitrag liefert (InvoicePdfData::load()), kommen aus
 * tests/fixtures-adm-rechnungen.php.
 *
 * @package WordPress Plugin Template/Tests
 */

declare(strict_types=1);

namespace Plugins\MembershipFee\classes\Invoices {
	// Ersatz für die Klasse des Plugins Mitgliedsbeitrag
	final class InvoicePdfData {
		/** @var array<string, array<string, mixed>> */
		public static array $data = [];

		public static function load(string $nr): ?array {
			return self::$data[$nr] ?? null;
		}
	}
}

namespace {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}

	define('TC_LIB_PDF_DIR', dirname(__DIR__));
	define('TC_LIB_PDF_BOOTSTRAPPED', true);

	$update   = in_array('--update', $argv, true);
	$fixtures = require __DIR__ . '/fixtures-adm-rechnungen.php';

	require_once __DIR__ . '/class-pdf-fake-platform.php';
	require_once TC_LIB_PDF_DIR . '/include/class-pdf-registry.php';

	$outDir   = __DIR__ . '/out';
	$platform = new PdfFakePlatform($outDir . '/storage');
	$platform->setAddressData($fixtures['verein']);
	PdfRuntime::setPlatform($platform);

	require TC_LIB_PDF_DIR . '/adm-include/templates.php';
	\Plugins\MembershipFee\classes\Invoices\InvoicePdfData::$data = $fixtures['invoices'];

	$pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
	$failed    = 0;
	foreach ($fixtures['cases'] as $name => [$templateId, $nr]) {
		$platform->setQueryParams(['nr' => $nr]);
		$pdf = PdfRegistry::create($templateId);
		if (!$pdf instanceof PdfTemplateBase || !$pdf->save()) {
			echo "FEHLER  {$name}: nicht erzeugt\n";
			$failed++;
			continue;
		}
		$file   = $pdf->getFileNameAbs();
		$target = $outDir . '/' . $name . '.pdf';
		copy($file, $target);
		if ($pdftotext === '') {
			echo "PDF     {$name}: {$target}\n";
			continue;
		}
		$text     = (string) shell_exec(escapeshellarg($pdftotext) . ' -layout ' . escapeshellarg($target) . ' -');
		$snapshot = __DIR__ . '/snapshots/adm_' . $name . '.txt';
		if ($update || !is_file($snapshot)) {
			file_put_contents($snapshot, $text);
			echo "NEU     {$name}\n";
		} elseif (file_get_contents($snapshot) !== $text) {
			file_put_contents($outDir . '/' . $name . '.txt', $text);
			echo "ANDERS  {$name} (siehe tests/out/{$name}.txt)\n";
			$failed++;
		} else {
			echo "OK      {$name}\n";
		}
	}
	exit($failed > 0 ? 1 : 0);
}

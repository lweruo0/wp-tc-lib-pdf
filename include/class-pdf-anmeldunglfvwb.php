<?php
/**
 * PDF Anmeldung LFVBW – raw PDF template with text replacement.
 *
 * Loads a pre-built PDF (in.pdf) and replaces placeholder strings
 * with actual form data. Does not use tc-lib-pdf.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/trait-pdf-data.php';
require_once __DIR__ . '/interface-pdf-output.php';

/**
 * PDF Anmeldung LFVBW class.
 */
class PdfTemplateLFVBW implements PdfOutputInterface {
	use PdfDataTrait;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->initializeUrlData();
	}

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$rechnungsnummer = $this->getUrl('nr', '');
		$name = $this->getUrl('nn', '');
		$vorname = $this->getUrl('vn', '');

		$this->createStorageFolder('bfv_vorbereitungslehrgang');

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfvvorbereitungslehrgang')) {
				$instance = bfvvorbereitungslehrgang();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
		}

		$formdata['substitutions'] = [
			"kursbeginn"       => "xx.xx.xxxx",
			"kursende"         => "yy.yy.yyyy",
			"pruefungsdatum"   => "zz.zz.zzzz...........",
			"geburtstag"       => "gg.gg.gggg........................................",
			"name_vorname"     => "nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn",
			"strasse"          => "sssssssssssssssssssssssssssssssssss",
			"plz_ort"          => "plzplzplzplzplzplzplzplzplzplzplpplzplzplz",
			"telefon"          => "99999999999999999999999999999999",
			"email"            => "email@nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn.de",
			"name_vorname_erz" => "n2nnnnnnnnnnnnnnnnnnnnnnnnnnnnn",
			"strasse_erz"      => "s2sssssssssssssssssssssssssssssss",
			"plz_ort_erz"      => "plz2plzplzplzplzplzplzplzplzplzplzplz",
			"telefon_erz"      => "888888888888888888888888888",
			"email_erz"        => "email2@nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn.de",
		];

		$this->setFormdata($formdata);
		$this->setFileName("{$name}-{$vorname}-{$rechnungsnummer}_lfvwb.pdf");
	}

	/**
	 * Generate the raw PDF by loading in.pdf and replacing placeholder text.
	 *
	 * @return string The raw PDF content.
	 */
	private function generateRawPdf(): string {
		$template = file_get_contents(plugin_dir_path(__FILE__) . '/images/in.pdf');
		$formdata = $this->getAllFormdata();
		$substitutions = $formdata['substitutions'] ?? [];

		foreach ($substitutions as $k => $v) {
			$vnew = isset($formdata[$k]) ? mb_convert_encoding($formdata[$k], "ISO-8859-15", "UTF-8") : '';
			$vnew = str_pad($vnew, strlen($v), " ", STR_PAD_RIGHT);
			$vnew = substr($vnew, 0, strlen($v));
			$template = str_replace($v, $vnew, $template);
		}

		return $template;
	}

	/**
	 * Send common caching headers.
	 *
	 * @return void
	 */
	private function sendCacheHeaders(): void {
		header("Expires: 0");
		header("Cache-Control: must-revalidate");
		header('Cache-Control: pre-check=0, post-check=0, max-age=0', false);
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header("Pragma: public");
		header("Content-type: application/pdf");
	}

	/**
	 * Serve a cached file if it exists.
	 *
	 * @param string $filename   Absolute path to the cached PDF.
	 * @param string $disposition "attachment" or "inline".
	 *
	 * @return bool True if the file was served, false otherwise.
	 */
	private function serveCachedFile(string $filename, string $disposition): bool {
		$new = $this->getUrl('new', '0');
		if ($filename === '' || !file_exists($filename) || !is_file($filename) || $new === '1') {
			return false;
		}

		$this->sendCacheHeaders();
		header("Content-Disposition: {$disposition}; filename=\"" . basename($filename) . "\"");
		readfile($filename);
		exit();
	}

	/**
	 * {@inheritdoc}
	 */
	public function output(): void {
		$this->loadData();

		$filename = $this->getFileNameAbs();
		if ($this->serveCachedFile($filename, 'attachment')) {
			return;
		}

		$rawpdf = $this->generateRawPdf();

		if ($filename !== '') {
			file_put_contents($filename, $rawpdf);
			chmod($filename, 0700);
		}

		$this->sendCacheHeaders();
		header('Content-Disposition: attachment; filename="' . basename($this->getFileName()) . '"');
		header('Content-Length: ' . strlen($rawpdf));
		echo $rawpdf;
		exit();
	}

	/**
	 * {@inheritdoc}
	 */
	public function stream(): void {
		$this->loadData();

		$filename = $this->getFileNameAbs();
		if ($this->serveCachedFile($filename, 'inline')) {
			return;
		}

		$rawpdf = $this->generateRawPdf();

		if ($filename !== '') {
			file_put_contents($filename, $rawpdf);
			chmod($filename, 0700);
		}

		$this->sendCacheHeaders();
		header('Content-Disposition: inline; filename="' . basename($this->getFileName()) . '"');
		header('Content-Length: ' . strlen($rawpdf));
		echo $rawpdf;
		exit();
	}

	/**
	 * Save the PDF to the filesystem.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save(): bool {
		$this->loadData();
		$filename = $this->getFileNameAbs();
		if ($filename === '') {
			return false;
		}

		$dir = dirname($filename);
		if (!is_dir($dir)) {
			if (!wp_mkdir_p($dir)) {
				return false;
			}
		}

		$rawpdf = $this->generateRawPdf();
		$result = file_put_contents($filename, $rawpdf);
		if ($result !== false) {
			chmod($filename, 0700);
		}
		return $result !== false;
	}
}

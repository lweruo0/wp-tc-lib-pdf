<?php
/**
 * Rechnung und Mahnung Hüttenmiete.
 *
 * Auf Basis von PdfRechnungBase. Die Rechnungsposten liefert das Plugin
 * bfv-fischerhuette im Formdata-Array.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/**
 * Rechnung Hüttenmiete.
 */
class PdfRechnungHuette extends PdfRechnungBase {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$rechnungsnummer = $this->getUrl('nr', '');

		$this->setOptions(get_option('bfv_jubilaeumsruten') ?: []);
		$this->setAddressdata(get_option('bfv_adressen') ?: []);
		$this->setAddress('header_name', 'Bezirksfischerei-Verein e.V. Ehingen/Donau');
		$this->setAddress('url_verein', 'https://bfv-ehingen.de');
		$this->createStorageFolder('bfv_huette');
		$this->setFileName("rechnung_huette_{$rechnungsnummer}.pdf");

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfvfischerhuette')) {
				$instance = bfvfischerhuette();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
		}

		$formdata = $this->prepareRechnung($formdata, 'Rechnung Hüttenmiete');

		// Das Plugin liefert RFC3339 mit Offset (Europe/Berlin). DateTimeImmutable
		// behält den Offset bei; date(strtotime()) würde nach UTC umrechnen und
		// bei Mitternacht den Vortag zeigen.
		$von = !empty($formdata['von']) ? (new DateTimeImmutable((string) $formdata['von']))->format('d.m.Y') : '';
		$bis = !empty($formdata['bis']) ? (new DateTimeImmutable((string) $formdata['bis']))->format('d.m.Y') : '';
		if ((int) ($formdata['dauer'] ?? 1) === 1) {
			$textBefore = "für die Hüttenbuchung am {$von} berechnen wir Ihnen:";
		} else {
			$textBefore = "für die Hüttenbuchung vom {$von} bis {$bis} berechnen wir Ihnen:";
		}
		$formdata['texts_before'] = [$textBefore];

		$this->setFormdata($formdata);
	}
}

/**
 * Mahnung Hüttenmiete.
 */
class PdfMahnungHuette extends PdfRechnungHuette {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		parent::loadData();

		$rechnungsnummer = $this->getUrl('nr', '');
		$this->setFileName("mahnung_huette_{$rechnungsnummer}.pdf");

		$this->setFormdata($this->prepareMahnung($this->getAllFormdata(), 'Mahnung'));
	}
}

<?php
/**
 * Rechnung und Mahnung Mitgliedsantrag.
 *
 * Eine Rechnung je Dokument, ausgewählt über die Mitgliedsnummer (mnr).
 * Aufbau und Mahnungslogik kommen aus PdfRechnungBase. Die Rechnungsposten
 * liefert das Plugin bfv-mitgliedsantrag im Formdata-Array.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/**
 * Rechnung Mitgliedsantrag.
 */
class PdfRechnungMitgliedsantrag extends PdfRechnungBase {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$mitgliedsnummer = trim((string) $this->getUrl('mnr', ''));

		$this->setOptions(get_option('bfv_mitgliedsantrag_einstellungen') ?: []);
		$this->setAddressdata(get_option('bfv_adressen') ?: []);
		$this->setAddress('header_name', 'Bezirksfischerei-Verein e.V. Ehingen/Donau');
		$this->setAddress('url_verein', 'https://bfv-ehingen.de');
		$this->createStorageFolder('bfv_mitgliedsantrag');

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfvmitgliedsantrag') && $mitgliedsnummer !== '') {
				$instance = bfvmitgliedsantrag();
				$formdata = $instance->get_formdata_by_mitgliedsnummer($mitgliedsnummer);
			} else {
				$formdata = [];
			}
		}

		$formdata = $this->prepareRechnung($formdata, 'Rechnung Mitgliedsantrag');

		$formdata['texts_before'] = [
			'für Ihren Mitgliedsantrag vom ' . $formdata['date'] . ' berechnen wir Ihnen:',
		];

		$this->setFormdata($formdata);
		$this->setFileName($this->dateiname('rechnung', $mitgliedsnummer));
	}

	/**
	 * Dateiname wie bei den anderen Rechnungen aus der Rechnungsnummer; fällt
	 * auf die Mitgliedsnummer zurück, wenn die Daten keine Nummer enthalten.
	 *
	 * @param string $prefix          'rechnung' oder 'mahnung'.
	 * @param string $mitgliedsnummer
	 *
	 * @return string
	 */
	protected function dateiname(string $prefix, string $mitgliedsnummer): string {
		$nr = trim((string) $this->getForm('rechnungsnummer', ''));
		if ($nr !== '') {
			return "{$prefix}_{$nr}.pdf";
		}
		return "{$prefix}_antrag_{$mitgliedsnummer}.pdf";
	}
}

/**
 * Mahnung Mitgliedsantrag.
 */
class PdfMahnungMitgliedsantrag extends PdfRechnungMitgliedsantrag {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		parent::loadData();

		$this->setFormdata($this->prepareMahnung($this->getAllFormdata(), 'Mahnung Mitgliedsantrag'));
		$this->setFileName($this->dateiname('mahnung', trim((string) $this->getUrl('mnr', ''))));
	}
}

<?php
/**
 * Rechnung und Mahnung Merchandise.
 *
 * Auf Basis von PdfRechnungBase. Die Rechnungsposten liefern die Plugins
 * bfv-jubilaeumsruten bzw. bfv-merchandise im Formdata-Array.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/**
 * Rechnung Merchandise.
 */
class PdfRechnungMerchandise extends PdfRechnungBase {

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
		$this->createStorageFolder('bfv_merchandise');
		$this->setFileName("rechnung_merchandise_{$rechnungsnummer}.pdf");

		$formdata = $this->getAllFormdata();
		if (empty($formdata) && function_exists('bfvjubilaeumsruten')) {
			$formdata = bfvjubilaeumsruten()->get_formdata_by_rechnungsnummer($rechnungsnummer);
		}
		if (empty($formdata) && function_exists('bfvmerchandise')) {
			$formdata = bfvmerchandise()->get_formdata_by_rechnungsnummer($rechnungsnummer);
		}
		if (!is_array($formdata)) {
			$formdata = [];
		}

		$formdata = $this->prepareRechnung($formdata, 'Rechnung Merchandise');

		$formdata['texts_before'] = [
			'für die am ' . $formdata['date'] . ' bestellten Merchandise-Artikel berechnen wir Ihnen:',
		];

		$this->setFormdata($formdata);
	}
}

/**
 * Mahnung Merchandise.
 */
class PdfMahnungMerchandise extends PdfRechnungMerchandise {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		parent::loadData();

		$rechnungsnummer = $this->getUrl('nr', '');
		$this->setFileName("mahnung_merchandise_{$rechnungsnummer}.pdf");

		$this->setFormdata($this->prepareMahnung($this->getAllFormdata(), 'Mahnung'));
	}
}

<?php
/**
 * Rechnung und Mahnung Erlaubnisschein.
 *
 * Auf Basis von PdfRechnungBase. Enthält nur noch die Datenbeschaffung
 * (WordPress-Optionen, bfv-erlaubnisscheine), die Rechnungsposten und den
 * Anschreibentext.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/**
 * Rechnung Erlaubnisschein.
 */
class PdfRechnungErlaubnis extends PdfRechnungBase {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$rechnungsnummer = $this->getUrl('nr', '');

		$this->setOptions(get_option('bfv_erlaubnisschein') ?: []);
		$this->setAddressdata(get_option('bfv_adressen') ?: []);
		$this->setAddress('header_name', 'Bezirksfischerei-Verein e.V. Ehingen/Donau');
		$this->setAddress('url_verein', 'https://bfv-ehingen.de');
		$this->createStorageFolder('bfv_erlaubnisschein');
		$this->setFileName("rechnung_{$rechnungsnummer}.pdf");

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfverlaubnisscheine')) {
				$instance = bfverlaubnisscheine();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
		}

		$formdata = $this->prepareRechnung($formdata, 'Rechnung Erlaubnisschein');

		$formdata['texts_before'] = [
			'für den von uns am ' . $formdata['date'] . ' bezogenen Erlaubnisschein berechnen wir Ihnen:',
		];

		$tage        = (int) ($formdata['tage'] ?? 1);
		$einzelpreis = (float) ($formdata['einzel_preis_netto'] ?? 0);
		$preis       = (float) ($formdata['preis_netto'] ?? 0);

		$posten = [
			$this->posten(
				'Erlaubnisschein ' . ($formdata['vorname'] ?? '') . ' ' . ($formdata['name'] ?? ''),
				'', '', ''
			),
		];
		if ($tage > 1) {
			$posten[] = $this->posten(
				'vom ' . ($formdata['von'] ?? '') . ' bis ' . ($formdata['bis'] ?? ''),
				$tage . ' Tage', $einzelpreis, $preis
			);
		} else {
			$posten[] = $this->posten('am ' . ($formdata['von'] ?? ''), '1 Tag', $einzelpreis, $preis);
		}
		$formdata['rechnungsposten'] = $posten;

		$this->setFormdata($formdata);
	}
}

/**
 * Mahnung Erlaubnisschein.
 */
class PdfMahnungErlaubnis extends PdfRechnungErlaubnis {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		parent::loadData();

		$rechnungsnummer = $this->getUrl('nr', '');
		$this->setFileName("mahnung_{$rechnungsnummer}.pdf");

		$this->setFormdata($this->prepareMahnung($this->getAllFormdata(), 'Mahnung Erlaubnisschein'));
	}
}

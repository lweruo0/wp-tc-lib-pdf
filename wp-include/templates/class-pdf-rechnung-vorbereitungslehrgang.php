<?php
/**
 * Rechnung und Mahnung Vorbereitungslehrgang.
 *
 * Erstes Template auf Basis von PdfRechnungBase. Enthält nur noch die
 * Datenbeschaffung (WordPress-Optionen, bfv-Plugin), die Rechnungsposten
 * und die lehrgangsspezifischen Texte.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/**
 * Rechnung Vorbereitungslehrgang.
 */
class PdfRechnungVorbereitungsLehrgang2 extends PdfRechnungBase {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$rechnungsnummer = $this->getUrl('nr', '');

		$this->setOptions(get_option('bfv_vorbereitungslehrgang') ?: []);
		$this->setAddressdata(get_option('bfv_adressen') ?: []);
		$this->setAddress('header_name', 'Bezirksfischerei-Verein e.V. Ehingen/Donau');
		$this->setAddress('url_verein', 'https://bfv-ehingen.de');
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

		$formdata = $this->prepareRechnung($formdata, 'Rechnung Vorbereitungslehrgang');

		$formdata['texts_before'] = [
			'für den am ' . $formdata['date'] . ' angemeldeten Vorbereitungslehrgang berechnen wir Ihnen:',
		];

		$preisTheorie   = (float) ($formdata['preis_theorie'] ?? 0);
		$preisPraxis    = (float) ($formdata['preis_praxistag'] ?? 0);
		$preisPruefung  = (float) ($formdata['preis_pruefungsgebuehr'] ?? 0);
		$preisGetraenke = (float) ($formdata['preis_getraenkepauschale'] ?? 0);

		$posten = [
			$this->posten('Theorieunterricht', '1x', $preisTheorie, $preisTheorie),
			$this->posten('Praxistag', '1x', $preisPraxis, $preisPraxis),
			$this->posten('Prüfungsgebühr', '1x', $preisPruefung, $preisPruefung),
			$this->posten('Getränkepauschale', '4x', $preisGetraenke, $preisGetraenke * 4),
		];

		for ($i = 1; $i <= 4; $i++) {
			if (!empty($formdata["Essen{$i}_bestellt"])) {
				$preisEssen = (float) ($formdata["preis_Essen{$i}"] ?? 0);
				$posten[] = $this->posten(
					(string) ($formdata["Essen{$i}"] ?? "Essen Tag {$i}"),
					'1x',
					$preisEssen,
					$preisEssen
				);
			}
		}
		$formdata['rechnungsposten'] = $posten;

		if (($formdata['steuersatz_getraenke'] ?? 0) > 0 && ($formdata['steuersatz_essen'] ?? 0) > 0) {
			$formdata['bezeichnung_steuer'] = 'Umsatzsteuer ' . $formdata['steuersatz_essen']
				. '% (Verzehr) / ' . $formdata['steuersatz_getraenke'] . '% (Getränke)';
		}

		$this->setFormdata($formdata);
		$this->setFileName("{$formdata['last_name']}-{$formdata['first_name']}-{$rechnungsnummer}_rechnung.pdf");
	}

	/**
	 * Lehrgangsspezifischer Hinweis unter dem Rechnungsblock.
	 *
	 * @param array<string, mixed> $formdata
	 *
	 * @return string
	 */
	protected function rechnungTextBelow(array $formdata): string {
		return "Die Anmeldung zum Lehrgang wird erst mit Zahlungseingang verbindlich.\n"
			. 'Aufgrund der begrenzten Teilnehmerzahl erfolgt die Platzvergabe in der Reihenfolge der eingehenden Zahlungen.'
			. ' Bitte erwerben Sie vor Beginn des Lehrgangs den aktuellen Fragenkatalog zur Fischerprüfung. https://shop-lfvbw.de/produkt-kategorie/lehrmaterial';
	}
}

/**
 * Mahnung Vorbereitungslehrgang.
 */
class PdfMahnungVorbereitungslehrgang extends PdfRechnungVorbereitungsLehrgang2 {

	/**
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		parent::loadData();

		$rechnungsnummer = $this->getUrl('nr', '');
		$this->setFileName("mahnung_{$rechnungsnummer}.pdf");

		$this->setFormdata($this->prepareMahnung($this->getAllFormdata(), 'Mahnung Vorbereitungslehrgang'));
	}

	/**
	 * Die Mahnung behält den Lehrgangshinweis unter dem Rechnungsblock.
	 *
	 * @param array<string, mixed> $formdata
	 *
	 * @return string
	 */
	protected function mahnungTextBelow(array $formdata): string {
		return $this->rechnungTextBelow($formdata);
	}
}

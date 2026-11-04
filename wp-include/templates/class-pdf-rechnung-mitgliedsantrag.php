<?php
/**
 * Rechnung und Mahnung Mitgliedsantrag.
 *
 * Eine Rechnung je Dokument, ausgewählt über die Mitgliedsnummer (mnr).
 * Aufbau und Mahnungslogik kommen aus PdfRechnungBase. Die Antragsdaten
 * liefert bfvmitgliedschaft()->mitgliedsantrag->get_formdata_by_mitgliedsnummer()
 * mit den Keys des Antragsformulars (vorname, name, strasse, …); sie werden hier
 * auf die Keys der Rechnungsbasis abgebildet. Die Rechnungsposten berechnet
 * PdfMitgliedsantragKostenTrait, dieselbe Berechnung wie im Antrag.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';
require_once __DIR__ . '/trait-pdf-mitgliedsantrag-kosten.php';

/**
 * Rechnung Mitgliedsantrag.
 */
class PdfRechnungMitgliedsantrag extends PdfRechnungBase {
	use PdfMitgliedsantragKostenTrait;

	/**
	 * Load data for this template.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Wenn zur Mitgliedsnummer kein Antrag gefunden wird.
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
			if (function_exists('bfvmitgliedschaft') && $mitgliedsnummer !== '' && is_object(bfvmitgliedschaft()->mitgliedsantrag)) {
				$instance = bfvmitgliedschaft()->mitgliedsantrag;
				$formdata = $instance->get_formdata_by_mitgliedsnummer($mitgliedsnummer);
			} else {
				$formdata = [];
			}
		}

		if (empty($formdata)) {
			throw new RuntimeException('Kein Mitgliedsantrag zur Mitgliedsnummer ' . $mitgliedsnummer . ' gefunden.');
		}

		$formdata = $this->mapAntragAufRechnung($formdata, $mitgliedsnummer);
		$formdata = $this->prepareRechnung($formdata, 'Rechnung Mitgliedsantrag');

		$formdata['texts_before'] = [
			'für Ihren Mitgliedsantrag vom ' . $formdata['date'] . ' berechnen wir Ihnen:',
		];

		$this->setFormdata($formdata);
		$this->setFileName($this->dateiname('rechnung', $mitgliedsnummer));
	}

	/**
	 * Antragsdaten auf die Keys der Rechnungsbasis abbilden.
	 *
	 * Empfänger aus den Antragstellerfeldern, Rechnungsnummer = Mitgliedsnummer,
	 * Rechnungsposten aus der Kostenberechnung. Vereinsbeiträge sind
	 * umsatzsteuerfrei, daher netto = brutto ohne Steuerzeile. Bereits
	 * vorhandene Rechnungs-Keys werden nicht überschrieben.
	 *
	 * @param array<string, mixed> $antrag          Daten aus get_formdata_by_mitgliedsnummer().
	 * @param string               $mitgliedsnummer Mitgliedsnummer aus der URL.
	 *
	 * @return array<string, mixed>
	 */
	protected function mapAntragAufRechnung(array $antrag, string $mitgliedsnummer): array {
		$antrag['first_name']      = $antrag['first_name'] ?? (string) ($antrag['vorname'] ?? '');
		$antrag['last_name']       = $antrag['last_name'] ?? (string) ($antrag['name'] ?? '');
		$antrag['street']          = $antrag['street'] ?? (string) ($antrag['strasse'] ?? '');
		$antrag['zip']             = $antrag['zip'] ?? (string) ($antrag['plz'] ?? '');
		$antrag['city']            = $antrag['city'] ?? (string) ($antrag['ort'] ?? '');
		$antrag['rechnung_anrede'] = $antrag['rechnung_anrede'] ?? (string) ($antrag['anrede'] ?? '');

		$nr = trim((string) ($antrag['mitgliedsnummer'] ?? ''));
		$antrag['rechnungsnummer'] = $antrag['rechnungsnummer'] ?? ($nr !== '' ? $nr : $mitgliedsnummer);

		if (!isset($antrag['rechnungsposten'])) {
			$posten = [];
			$summe  = 0.0;
			foreach ($this->mitgliedsantragKosten($antrag) as [$bezeichnung, $betrag]) {
				$posten[] = $this->posten($bezeichnung, '1', $betrag, $betrag);
				$summe   += $betrag;
			}
			$antrag['rechnungsposten'] = $posten;
			$antrag['netto']           = $summe;
			$antrag['brutto']          = $summe;
		}

		return $antrag;
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

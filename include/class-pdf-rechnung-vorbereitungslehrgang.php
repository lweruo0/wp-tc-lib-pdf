<?php
/**
 * Example PDF Template class.
 *
 * Demonstrates how to create a PDF template with header/footer.
 * Uses PdfHeaderFooterTrait for header and footer functionality.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-pdf-template.php';
require_once __DIR__ . '/trait-pdf-header-footer.php';
require_once __DIR__ . '/trait-pdf-adress.php';
require_once __DIR__ . '/trait-pdf-falzmarken.php';
require_once __DIR__ . '/trait-pdf-absender.php';
require_once __DIR__ . '/trait-pdf-rechnungsdaten.php';

/**
 * Example PDF Template with header and footer.
 */
class PdfRechnungVorbereitungsLehrgang2 extends PdfTemplate {
	use PdfHeaderFooterTrait;
	use PdfAdressTrait;
	use PdfFalzmarkenTrait;
	use PdfAbsenderTrait;
	use PdfRechnungsdatenTrait;

	/** Height of each row (mm). */
	private const ROW_HEIGHT = 6;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(true); // Enable default header/footer and page content
		$this->initializeUrlData(); // Load $_GET parameters into $this->urldata
	}

	/**
	 * Load data for this template.
	 *
	 * Override this in subclasses or call setOptions()/setFormdata()/setAddressdata()
	 * from the dispatcher before rendering to inject dynamic data.
	 *
	 * @return void
	 */
	protected function loadData(): void {

		$rechnungsnummer = $this->getUrl('nr', '');
		$options = get_option('bfv_vorbereitungslehrgang');
		$this->setOptions($options);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('bfv_vorbereitungslehrgang');
		//$this->setFileName("rechnung_$rechnungsnummer.pdf");

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfvvorbereitungslehrgang')) {
				$instance = bfvvorbereitungslehrgang();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
		}

		$name = $this->getAddress ( 'name_verein',  '' );
		$addr = $this->getAddress ( 'addr_verein',  '' );
		$city = $this->getAddress ( 'ort_verein',  '' );

		$formdata['documenttype'] = 'Rechnung Vorbereitungslehrgang';
		$vorname = $formdata['first_name'] = $formdata['rechnung_vorname'] ?? '';
		$name = $formdata['last_name'] = $formdata['rechnung_name'] ?? '';
		$formdata['street'] = $formdata['rechnung_strasse'] ?? '';
		$formdata['zip'] = $formdata['rechnung_plz'] ?? '';
		$formdata['city'] = $formdata['rechnung_ort'] ?? '';
		$formdata['email'] = $formdata['rechnung_email'] ?? '';
		$formdata['returnme'] = $formdata['returnme'] ?? 'falls unzustellbar, bitte zurück';
		$formdata['sender'] = $this->getAddress('sender', "$name, $addr, $city");
		$formdata['date'] = isset($formdata['created_at']) ? date("d.m.Y", strtotime($formdata['created_at'])) : date("d.m.Y");
		$formdata['zahlungsfrist'] = $formdata['zahlungsfrist_original'] ?? date ( "d.m.Y", strtotime('+7 days') );
		

		$text_below = 'Die Anmeldung zum Lehrgang wird erst mit Zahlungseingang verbindlich.\n';
		$text_below .= ' Aufgrund der begrenzten Teilnehmerzahl erfolgt die Platzvergabe in der Reihenfolge der eingehenden Zahlungen.';
		$text_below .= ' Bitte erwerben Sie vor Beginn des Lehrgangs den aktuellen Fragenkatalog zur Fischerprüfung. https://shop-lfvbw.de/produkt-kategorie/lehrmaterial';
		$formdata['text_below'] = $text_below;
		
        $text_before = 'für den am ' . $formdata['date']. ' angemeldeten Vorbereitungslehrgang berechnen wir Ihnen:';


		$formdata['texts_before'] = [$text_before];

		$formdata['rechnungsposten'] = [];
		$formdata['rechnungsposten'][] = ['bezeichnung' => 'Theorieunterricht',
					'anzahl' => '1x',
					'einzelpreis' => number_format($formdata ['preis_theorie'], 2, ',', '') . ' €',
					'gesamtpreis' => number_format($formdata ['preis_theorie'], 2, ',', '') . ' €',
					];
		$formdata['rechnungsposten'][] = ['bezeichnung' => 'Praxistag',
					'anzahl' => '1x',
					'einzelpreis' => number_format($formdata ['preis_praxistag'], 2, ',', '') . ' €',
					'gesamtpreis' => number_format($formdata ['preis_praxistag'], 2, ',', '') . ' €',
					];
		$formdata['rechnungsposten'][] = ['bezeichnung' => 'Prüfungsgebühr',
					'anzahl' => '1x',
					'einzelpreis' => number_format($formdata ['preis_pruefungsgebuehr'], 2, ',', '') . ' €',
					'gesamtpreis' => number_format($formdata ['preis_pruefungsgebuehr'], 2, ',', '') . ' €',
					];
		$formdata['rechnungsposten'][] = ['bezeichnung' => 'Getränkepauschale',
					'anzahl' => '4x',
					'einzelpreis' => number_format($formdata ['preis_getraenkepauschale'], 2, ',', '') . ' €',
					'gesamtpreis' => number_format($formdata ['preis_getraenkepauschale']*4, 2, ',', '') . ' €',
					];
		if (!empty($formdata['Essen1_bestellt'])) {
			$formdata['rechnungsposten'][] = ['bezeichnung' => $formdata ['Essen1'] ?? 'Essen erster Tag',
						'anzahl' => '1x',
						'einzelpreis' => number_format($formdata ['preis_Essen1'], 2, ',', '') . ' €',
						'gesamtpreis' => number_format($formdata ['preis_Essen1'], 2, ',', '') . ' €',
						];
		}
		if (!empty($formdata['Essen2_bestellt'])) {
			$formdata['rechnungsposten'][] = ['bezeichnung' => $formdata ['Essen2'] ?? 'Essen zweiter Tag',
						'anzahl' => '1x',
						'einzelpreis' => number_format($formdata ['preis_Essen2'], 2, ',', '') . ' €',
						'gesamtpreis' => number_format($formdata ['preis_Essen2'], 2, ',', '') . ' €',
						];
		}
		if (!empty($formdata['Essen3_bestellt'])) {
			$formdata['rechnungsposten'][] = ['bezeichnung' => $formdata ['Essen3'] ?? 'Essen dritter Tag',
						'anzahl' => '1x',
						'einzelpreis' => number_format($formdata ['preis_Essen3'], 2, ',', '') . ' €',
						'gesamtpreis' => number_format($formdata ['preis_Essen3'], 2, ',', '') . ' €',
						];
		}
		if (!empty($formdata['Essen4_bestellt'])) {
			$formdata['rechnungsposten'][] = ['bezeichnung' => $formdata ['Essen4'] ?? 'Essen vierter Tag',
						'anzahl' => '1x',
						'einzelpreis' => number_format($formdata ['preis_Essen4'], 2, ',', '') . ' €',
						'gesamtpreis' => number_format($formdata ['preis_Essen4'], 2, ',', '') . ' €',
						];
		}

		if (($formdata ['steuersatz']??0) > 0) {
			$formdata['bezeichnung_steuer'] = 'Umsatzsteuer ' . $formdata ['steuersatz'] . '%';
		}

        if (($formdata ['steuersatz_getraenke']??0) > 0 && ($formdata ['steuersatz_essen']??0) > 0) {
			//$formdata['steuer'] = 
			$formdata['bezeichnung_steuer'] = 'Umsatzsteuer ' . $formdata ['steuersatz_essen'] . '% (Verzehr) / ' . $formdata ['steuersatz_getraenke'] . '% (Getränke)';
		}


		$this->setFormdata($formdata);
		$this->setFileName("{$name}-{$vorname}-{$rechnungsnummer}_rechnung.pdf");

		}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->setHeaderText('Bezirksfischerei-Verein e.V. Ehingen/Donau', 'https://bfv-ehingen.de', 'https://bfv-ehingen.de');
		$this->addPage();
		$this->add_adress_field();
		$this->add_falzmarken();
		$this->add_absender();
		$this->add_rechnungsdaten();
		$y = $this->add_anschreiben_rechnung(105);
		$y += self::ROW_HEIGHT*0.5;
		$this->add_rechnung_block($y);
	}
}

class PdfMahnungVorbereitungslehrgang extends PdfRechnungVorbereitungsLehrgang2 {

	/**
	 * Load data for this template.
	 *
	 * Override this in subclasses or call setOptions()/setFormdata()/setAddressdata()
	 * from the dispatcher before rendering to inject dynamic data.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		parent::loadData();

		$rechnungsnummer = $this->getUrl('nr', '');
		$this->setFileName("mahnung_$rechnungsnummer.pdf");

		$formdata = $this->getAllFormdata();
		$betrag = number_format ( $formdata ['brutto'], 2, ',', '' );
		if ($betrag == '0,00' ) {
			return;
		}

		$formdata['documenttype'] = 'Mahnung Vorbereitungslehrgang';
		$formdata['zahlungsfrist'] = $formdata['zahlungsfrist_original'] ?? date ( "d.m.Y", strtotime('+7 days') );
		$zweitemahnung = $this->getUrl('zweitemahnung', '');

		$betrag = number_format ( $formdata ['brutto'], 2, ',', '' );
		if ($betrag == '0,00' ) {
			return;
		}

		$mahngebuehr = number_format($this->getOption('mahngebuehr', 0.0), 2, ',', '');
		if ($mahngebuehr == '0,00') {
			$ohnemahngebuehr = 'true';
		} else {
			$ohnemahngebuehr = $this->getUrl('ohnemahngebuehr', '');
		}

		if ($zweitemahnung != '') {
			$formdata ['text_zahlungserinnerung'] = '2. Zahlungserinnerung';
		} else {
			$formdata ['text_zahlungserinnerung'] = 'Zahlungserinnerung';
		}

		if ($ohnemahngebuehr != ''){
			$formdata ['mahngebuehr'] = 0;
		} else {
			if ($zweitemahnung != '') {
				$formdata ['mahngebuehr'] = (float)$this->getOption('mahngebuehr', 0.0) * 2;
				$formdata ['brutto'] += $formdata ['mahngebuehr'];
			} else {
				$formdata ['mahngebuehr'] = $this->getOption('mahngebuehr', 0.0);
				$formdata ['brutto'] += (float)$formdata ['mahngebuehr'];
			}
		}

		$text_below = 'Die Anmeldung zum Lehrgang wird erst mit Zahlungseingang verbindlich.\n';
		$text_below .= ' Aufgrund der begrenzten Teilnehmerzahl erfolgt die Platzvergabe in der Reihenfolge der eingehenden Zahlungen.';
		$text_below .= ' Bitte erwerben Sie vor Beginn des Lehrgangs den aktuellen Fragenkatalog zur Fischerprüfung. https://shop-lfvbw.de/produkt-kategorie/lehrmaterial';
		$formdata['text_below'] = $text_below;

		$formdata['texts_before'] = [];
		$formdata['texts_before'][]= "die Bezahlung der Rechnung Nr. " . $formdata ['rechnungsnummer'] . " war am " . $formdata ['zahlungsfrist_original'] . " fällig.";
		$formdata['texts_before'][]= "";
		$formdata['texts_before'][] = "Leider konnten wir bisher keinen Zahlungseingang verbuchen. ";

		if ($formdata ['mahngebuehr'] > 0.0) {
			$formdata['texts_before'][] = "Aufgrund der uns zusätzlich entstehenden Kosten und Aufwände sehen wir uns leider";
			$formdata['texts_before'][] = "gezwungen Mahngebühren in Rechnung zu stellen. Der offene Betrag setzt sich wie";
			$formdata['texts_before'][] = "folgt zusammen: ";
		}

		$this->setFormdata($formdata);
	}
}
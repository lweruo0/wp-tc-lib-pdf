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
class PdfRechnungMerchandise extends PdfTemplate {
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
		$options = get_option('bfv_jubilaeumsruten');
		$this->setOptions($options);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('bfv_merchandise');
		$this->setFileName("rechnung_merchandise_{$rechnungsnummer}.pdf");

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfvjubilaeumsruten')) {
				$instance = bfvjubilaeumsruten();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
		}
		if (empty($formdata)) {
			if (function_exists('bfvmerchandise')) {
				$instance = bfvmerchandise();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
		}

		$name = $this->getAddress ( 'name_verein',  '' );
		$addr = $this->getAddress ( 'addr_verein',  '' );
		$city = $this->getAddress ( 'ort_verein',  '' );

		$formdata['documenttype'] = 'Rechnung Merchandise';
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
		

		$brutto = number_format ( $formdata ['brutto'], 2, ',', '' );
		$frist = $formdata['zahlungsfrist'];
		$text_below = "Der Rechnungsbetrag von {$brutto} € ist spätestens zum {$frist} fällig. ";
		$text_below .= "\nNach § 286 Abs. 3 BGB tritt Verzug auch ohne Mahnung ein, wenn die Zahlung nicht innerhalb von 30 Tagen erfolgt. Soweit nicht anders angegeben, entspricht das Rechnungsdatum dem Leistungsdatum.";
		$formdata['text_below'] = $text_below;
		
        $text_before = 'für die am ' . $formdata['date']. ' bestellten Merchandise-Artikel berechnen wir Ihnen:';
		$formdata['texts_before'] = [$text_before];

		if (($formdata ['steuersatz']??0) > 0) {
			$formdata['bezeichnung_steuer'] = 'Umsatzsteuer ' . $formdata ['steuersatz'] . '%';
		}


		$this->setFormdata($formdata);

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

class PdfMahnungMerchandise extends PdfRechnungMerchandise {

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
		//$this->setFileName("mahnung_merchandise_{$rechnungsnummer}.pdf");

		$formdata = $this->getAllFormdata();
		$betrag = number_format ( $formdata ['brutto'], 2, ',', '' );
		if ($betrag == '0,00' ) {
			return;
		}

		$formdata['documenttype'] = 'Mahnung';
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

		$brutto = number_format ( $formdata ['brutto'], 2, ',', '' );
		$frist = $formdata ['zahlungsfrist'];
		$text_below = "Der offene Betrag von {$brutto} € ist spätestens zum {$frist} fällig.";
		$text_below .= "\nSollte bis dahin kein Zahlungseingang erfolgen, müssten wir weitere Schritte prüfen.";
		$text_below .= "\nFalls Sie die Zahlung bereits veranlasst haben, betrachten Sie dieses Schreiben bitte als gegenstandslos.";
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
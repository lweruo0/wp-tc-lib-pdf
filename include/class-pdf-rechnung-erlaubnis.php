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
class PdfRechnungErlaubnis extends PdfTemplate {
	use PdfHeaderFooterTrait;
	use PdfAdressTrait;
	use PdfFalzmarkenTrait;
	use PdfAbsenderTrait;
	use PdfRechnungsdatenTrait;

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
		$options = get_option('bfv_erlaubnisschein');
		$this->setOptions($options);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('bfv_erlaubnisschein');
		$this->setFileName("rechnung_$rechnungsnummer.pdf");

		if (function_exists('bfverlaubnisscheine')) {
			$instance = bfverlaubnisscheine();
			$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
		} else {
			$formdata = [];
		}

		$name = $this->getAddress ( 'name_verein',  '' );
		$street = $this->getAddress ( 'street_verein',  '' );
		$city = $this->getAddress ( 'ort_verein',  '' );

		$formdata['documenttype'] = 'Rechnung Erlaubnisschein';
		$formdata['first_name'] = $formdata['rechnung_vorname'] ?? '';
		$formdata['last_name'] = $formdata['rechnung_name'] ?? '';
		$formdata['street'] = $formdata['rechnung_strasse'] ?? '';
		$formdata['zip'] = $formdata['rechnung_plz'] ?? '';
		$formdata['city'] = $formdata['rechnung_ort'] ?? '';
		$formdata['email'] = $formdata['rechnung_email'] ?? '';
		$formdata['returnme'] = $formdata['returnme'] ?? 'falls unzustellbar, bitte zurück';
		$formdata['sender'] = $this->getAddress('sender', "$name, $street, $city");

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

		/* DIN 5008 Form B Textfeld: Rechnungszeilen */
		$this->add_Zeile(25, 100, 6, 100.0, 20.0, 22.5, 22.5, 'Bezeichnung', 'Anzahl', 'Einzelpreis', 'Gesamtpreis', 230);
		$this->add_Zeile(25, 106, 6, 100.0, 20.0, 22.5, 22.5, 'Erlaubnisschein Bruno Karitzky', '1 Tag', '25,00 €', '25,00 €', 245);
		$this->add_Zeile(25, 112, 6, 100.0, 20.0, 22.5, 22.5, 'am 04.07.2026', '', '', '', 230);
		$this->add_Zeile(25, 118, 6, 100.0, 20.0, 22.5, 22.5, 'Nettobetrag', '', '', '25,00 €', 245);
		$this->add_Zeile(25, 124, 6, 100.0, 20.0, 22.5, 22.5, 'Rechnungsbetrag', '', '', '25,00 €', 230, 'BU');
	}
}

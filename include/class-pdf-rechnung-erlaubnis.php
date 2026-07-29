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
		$addr = $this->getAddress ( 'addr_verein',  '' );
		$city = $this->getAddress ( 'ort_verein',  '' );

		$formdata['documenttype'] = 'Rechnung Erlaubnisschein';
		$formdata['first_name'] = $formdata['rechnung_vorname'] ?? '';
		$formdata['last_name'] = $formdata['rechnung_name'] ?? '';
		$formdata['street'] = $formdata['rechnung_strasse'] ?? '';
		$formdata['zip'] = $formdata['rechnung_plz'] ?? '';
		$formdata['city'] = $formdata['rechnung_ort'] ?? '';
		$formdata['email'] = $formdata['rechnung_email'] ?? '';
		$formdata['returnme'] = $formdata['returnme'] ?? 'falls unzustellbar, bitte zurück';
		$formdata['sender'] = $this->getAddress('sender', "$name, $addr, $city");

		$formdata['date'] = $formdata['date_original'] ?? date ( "d.m.Y" );
		$formdata['zahlungsfrist'] = $formdata['zahlungsfrist_original'] ?? date ( "d.m.Y", strtotime('+7 days') );

		 error_log(print_r($formdata, TRUE));
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

		$rechnung_name = $this->getForm('rechnung_name', '');
		$rechnung_vorname = $this->getForm('rechnung_vorname', '');
		$rechnung_anrede = $this->getForm('rechnung_anrede', 'Herr');

		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', 14);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');

		$text = 'Ehingen, den ' . $this->getForm('date', '');

		$y = 110;
		$out .= $this->getTextCell(
			txt: $text,
			posx: 120.0,
			posy: $y,
			width: 165.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$y += 10;
		$greetingText = ($rechnung_anrede == 'Frau') 
			? "Sehr geehrte Frau " . $rechnung_name . ','
			: "Sehr geehrter Herr " . $rechnung_name . ',';

		$out .= $this->getTextCell(
			txt: $greetingText,
			posx: 20.0,
			posy: $y,
			width: 165.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);


		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);

		$text = 'für den von uns am ' . $this->getForm('date', '') . ' bezogenen Erlaubnisschein berechnen wir Ihnen:';
		$y += 10;
		$out .= $this->getTextCell(
			txt: $text,
			posx: 20.0,
			posy: $y,
			width: 165.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$y += 30;
		/* DIN 5008 Form B Textfeld: Rechnungszeilen */
		$this->add_Zeile(25, 100, self::ROW_HEIGHT, 100.0, 20.0, 22.5, 22.5, 'Bezeichnung', 'Anzahl', 'Einzelpreis', 'Gesamtpreis', 230);
		$y += self::ROW_HEIGHT;
		$this->add_Zeile(25, 106, self::ROW_HEIGHT, 100.0, 20.0, 22.5, 22.5, 'Erlaubnisschein Bruno Karitzky', '1 Tag', '25,00 €', '25,00 €', 245);
		$y += self::ROW_HEIGHT;
		$this->add_Zeile(25, 112, self::ROW_HEIGHT, 100.0, 20.0, 22.5, 22.5, 'am 04.07.2026', '', '', '', 230);
		$y += self::ROW_HEIGHT;
		$this->add_Zeile(25, 118, self::ROW_HEIGHT, 100.0, 20.0, 22.5, 22.5, 'Nettobetrag', '', '', '25,00 €', 245);
		$y += self::ROW_HEIGHT;
		$this->add_Zeile(25, 124, self::ROW_HEIGHT, 100.0, 20.0, 22.5, 22.5, 'Rechnungsbetrag', '', '', '25,00 €', 230, 'BU');
	}
}

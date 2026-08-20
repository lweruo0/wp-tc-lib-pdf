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

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfverlaubnisscheine')) {
				$instance = bfverlaubnisscheine();
				$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
			} else {
				$formdata = [];
			}
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
		$formdata['date'] = isset($formdata['created_at']) ? date("d.m.Y", strtotime($formdata['created_at'])) : date("d.m.Y");
		$formdata['zahlungsfrist'] = $formdata['zahlungsfrist_original'] ?? date ( "d.m.Y", strtotime('+7 days') );
		
		$text_below = 'Der Rechnungsbetrag von ' . number_format ( $formdata['brutto'] ?? 0, 2, ',', '' ) . ' € ist spätestens zum ' . $this->getForm('zahlungsfrist', '') . ' fällig.';
		$text_below .= ' Nach § 286 Abs. 3 BGB tritt Verzug auch ohne Mahnung ein, wenn die Zahlung nicht ';
		$text_below .= 'innerhalb von 30 Tagen erfolgt. Soweit nicht anders angegeben, entspricht das ';
		$text_below .= 'Rechnungsdatum dem Leistungsdatum.';
		$formdata['text_below'] = $text_below;
		
		$text_before = 'für den von uns am ' . $formdata['date'] . ' bezogenen Erlaubnisschein berechnen wir Ihnen:';

		$formdata['texts_before'] = [$text_before];


		$this->setFormdata($formdata);
	}

	protected function add_anschreiben_rechnung($y): float {

		$rechnung_name = $this->getForm('rechnung_name', '');
		$rechnung_vorname = $this->getForm('rechnung_vorname', '');
		$rechnung_anrede = $this->getForm('rechnung_anrede', 'Herr');
		$rechnungsnummer = $this->getForm('rechnungsnummer', '');

		$out = $this->graph->getStartTransform();
		$out .= $this->color->getPdfColor('#000000');


		$font = $this->font->insert($this->pon, 'helvetica', '', 11);
		$out .= $font['out'];

		$out .= $this->getTextCell(
			txt: 'Ehingen, den ' . $this->getForm('date', ''),
			posx: 125.0,
			posy: $y,
			width: 165.0,
			height: $font['size'],
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);


		// optionalen Text für Zahlungserinnerung einfügen, falls vorhanden
		$text_zahlungserinnerung = $this->getForm('text_zahlungserinnerung', '');
		if (!empty($text_zahlungserinnerung)) {
			$font = $this->font->insert($this->pon, 'helvetica', 'B', 17);
			$lineHeight = $font['height'];
			$out .= $font['out'];
			$out .= $this->getTextCell(
				txt: $text_zahlungserinnerung,
				posx: 25.0,
				posy: $y,
				width: 165.0,
				height: 5.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
			$y += self::ROW_HEIGHT*2;
		} else {
			$y += self::ROW_HEIGHT;
		}

		$font = $this->font->insert($this->pon, 'helvetica', 'B', 11);
		$out .= $font['out'];
		$out .= $this->getTextCell(
			txt: 'Rechnung Nr. ' . $rechnungsnummer,
			posx: 25.0,
			posy: $y,
			width: 165.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);


		$font = $this->font->insert($this->pon, 'helvetica', '', 11);
		$out .= $font['out'];

		$y += self::ROW_HEIGHT*2;
		$greetingText = ($rechnung_anrede == 'Frau') 
			? "Sehr geehrte Frau " . $rechnung_name . ','
			: "Sehr geehrter Herr " . $rechnung_name . ',';

		$out .= $this->getTextCell(
			txt: $greetingText,
			posx: 25.0,
			posy: $y,
			width: 165.0,
			height: $font['size'],
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		$y += $font['size']*0.5;

		foreach ($this->getForm('texts_before', []) as $text) {
			$out .= $this->getTextCell(
				txt: $text,
				posx: 25.0,
				posy: $y,
				width: 165.0,
				height: $font['size'],
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
			$y += $font['size']*0.5;
		}
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y;
	}

	protected function add_rechnung_block($y): float {

		$out = $this->graph->getStartTransform();
		$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'Bezeichnung', 'Anzahl', 'Einzelpreis', 'Gesamtpreis', 230);

		$y += self::ROW_HEIGHT * 0.5;
		$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'Erlaubnisschein ' . $this->getForm('vorname', '') . ' ' . $this->getForm('name', ''), '', '', '', 255);

		if ($this->getForm('tage', 0) > 1) {
			$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'vom ' . $this->getForm('von', '') . ' bis ' . $this->getForm('bis', ''), $this->getForm('tage', 0) . ' Tage', number_format($this->getForm('einzel_preis_netto', 0), 2, ',', '') . ' €', number_format($this->getForm('preis_netto', 0), 2, ',', '') . ' €', 255);
		} else {
			$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'am ' . $this->getForm('von', ''), '1 Tag', number_format($this->getForm('einzel_preis_netto', 0), 2, ',', '') . ' €', number_format($this->getForm('preis_netto', 0), 2, ',', '') . ' €', 255);
		}
		if ($this->getForm('preis_versand_netto', 0) > 0) {
			$y += self::ROW_HEIGHT * 0.2; // Add a small gap before the next line
			$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'Druck-/Versandkostenpauschale', '', '', number_format($this->getForm('preis_versand_netto', 0), 2, ',', '') . ' €', 255);
		}

		if ($this->getForm('rabatt_netto', 0) > 0) {
			$y += self::ROW_HEIGHT * 0.2; // Add a small gap before the next line
			$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 125, 5, 5, 30, 'Rabatt (' . $this->getForm('rabattcode', '') . ')', '', '', '-' . number_format($this->getForm('rabatt_netto', 0), 2, ',', '') . ' €', 255);
		}
	
		$y += self::ROW_HEIGHT;
		$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'Nettobetrag', '', '', number_format($this->getForm('netto', 0), 2, ',', '') . ' €', 255);

		if ($this->getForm('steuersatz', 0) > 0) {
			$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 75, 30, 30, 30, 'Umsatzsteuer ' . $this->getForm('steuersatz', 0) . '%', '', '', number_format($this->getForm('steuer', 0), 2, ',', '') . ' €', 255);
		}

		if ($this->getForm('mahngebuehr', 0) > 0) {
			$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 100, 5, 30, 30, 'Mahngebühr', '', '', number_format($this->getForm('mahngebuehr', 0), 2, ',', '') . ' €', 255);
		}

		$y = $this->add_Zeile(25, $y, self::ROW_HEIGHT, 100, 5, 30, 30, 'Rechnungsbetrag', '', '', number_format($this->getForm('brutto', 0), 2, ',', '') . ' €', 230, 'BU');

		$y += self::ROW_HEIGHT*0.5;
		$font = $this->font->insert($this->pon, 'helvetica', '', 11);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');

		$out .= $this->getTextCell(
			txt: $this->getForm('text_below', ''),
			posx: 25.0,
			posy: $y,
			width: 165.0,
			height: self::ROW_HEIGHT * 5,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + self::ROW_HEIGHT * 5;
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

class PdfMahnungErlaubnis extends PdfRechnungErlaubnis {

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

		$formdata['documenttype'] = 'Mahnung Erlaubnisschein';
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

		$text_below = 'Der offene Betrag von ' . number_format ( $formdata ['brutto'], 2, ',', '' ) . ' € ist spätestens zum ' . $formdata ['zahlungsfrist'] . ' fällig.';
		$text_below .= ' Sollte bis dahin kein Zahlungseingang erfolgen, müssten wir weitere Schritte prüfen.';
		$text_below .= ' Falls Sie die Zahlung bereits veranlasst haben, betrachten Sie dieses Schreiben bitte als gegenstandslos.';
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
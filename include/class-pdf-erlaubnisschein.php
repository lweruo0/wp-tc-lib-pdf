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

/**
 * Example PDF Template with header and footer.
 */
class PdfErlaubnisschein2 extends PdfTemplate {
	private const PAGE_W = 297.0;
	private const PAGE_H = 210.0;
	private const MARGIN = 5.0;
	private const H1 = 17;
	private const TEXT_LETTER = 12;
	private const TEXT = 9;
	private const TEXT_TINY = 7;


	/** Cached image instance ID for the images */
	private ?int $headerLogoImageId = null;
	private ?int $stempelImageId = null;
	private ?int $gewaesserImageId = null;
	private ?int $unterschriftImageId = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(false);
		$this->initializeUrlData();
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
		$this->setOptions($options ?: []);

		$adressData = get_option('bfv_adressen');
		$this->setAddressdata($adressData ?: []);
		$this->createStorageFolder('bfv_erlaubnisschein');
		$this->setFileName("erlaubnisschein_$rechnungsnummer.pdf");

		if (function_exists('bfverlaubnisscheine')) {
			$instance = bfverlaubnisscheine();
			$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
		} else {
			$formdata = [];
		}

		$formdata['statistik_options'] = get_option ( 'bfv_erlaubnisschein_statistik' );
		$formdata['schonzeiten'] = get_option ( 'bfv_erlaubnisschein_schonzeit' );




		$formdata['documenttype'] = 'Erlaubnisschein';
		$formdata['returnme'] = 'falls unzustellbar, bitte zurück';
		$this->setFormdata($formdata);
	}




	public function erste_Seite(): void {
		$this->addPage([
			'orientation' => 'L',
			'format' => 'A4',
		]);

		$out = $this->graph->getStartTransform();
		$out .= $this->gen_fangstatistik_block();
		$out .= $this->gen_erlaubnis_block();
		$out .= $this->gen_hinweis_block();
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function zweite_Seite(): void {
		$this->addPage([
			'orientation' => 'L',
			'format' => 'A4',
		]);
		$out = $this->graph->getStartTransform();
		$out .= $this->gen_gewaesser_block();
		$out .= $this->gen_adress_block(self::PAGE_W * 0.5, 0.0);
		$out .= $this->gen_schonzeiten_block();
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function gen_erlaubnis_block(): string {

		$xpos = self::PAGE_W * 0.5;
		$ypos = 0;


		$rand = self::MARGIN;
		$x = $xpos + $rand;
		$y = $ypos + $rand + 1.0;

		$font_h1 = $this->font->insert($this->pon, 'helvetica', 'B', self::H1);
		$font_letter = $this->font->insert($this->pon, 'helvetica', '', self::TEXT_LETTER);
		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$font_tiny = $this->font->insert($this->pon, 'helvetica', '', self::TEXT_TINY);

		$out = "";
		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Bezirksfischerei-Verein e.V. Ehingen/Donau',
			posx: $x,
			posy: $y,
			width: 150.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$y += 9.0;

		$out .= $font_letter['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Erlaubnisschein',
			posx: $x + 2.0,
			posy: $y,
			width: 50.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: 'zum Fischfang in unseren Vereinsgewässern',
			posx: $x + 2.0,
			posy: $y,
			width: 90.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;

		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');

		$tage = (int) $this->getForm('tage', 0);
		if ($tage > 1) {
			$out .= $this->getTextCell(
				txt: 'vom ' . $this->getForm('von', '') . ' bis ' . $this->getForm('bis', '') . ' (' . $tage . ' Tage).',
				posx: $x + 2.0,
				posy: $y,
				width: 110.0,
				height: 5.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		} else {
			$out .= $this->getTextCell(
				txt: 'am ' . $this->getForm('von', '') . ' (1 Tag).',
				posx: $x + 2.0,
				posy: $y,
				width: 110.0,
				height: 5.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}
		$y += 5.0;

		if ((int) $this->getForm('no_hinweis_begleitet', 0) !== 1) {
			$out .= $this->getTextCell(
				txt: 'Nur in Begleitung eines aktiven Vereinsmitglieds gültig.',
				posx: $x + 2.0,
				posy: $y,
				width: 110.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 4.0;
			$out .= $this->getTextCell(
				txt: 'Verfügt das begleitende Vereinsmitglied über Bootsstempel,',
				posx: $x + 2.0,
				posy: $y,
				width: 110.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 4.0;
			$out .= $this->getTextCell(
				txt: 'dann ist das Probemitlied zur Mitnutzung des Boots berechtigt.',
				posx: $x + 2.0,
				posy: $y,
				width: 110.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 4.0;
			$out .= $this->getTextCell(
				txt: 'Verantwortliches Vereinsmitglied: ' . $this->getForm('mitgliedsname', ''),
				posx: $x + 2.0,
				posy: $y,
				width: 110.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		} else {
			$y += 8.0;
		}
		$out .= $font_letter['out'];
		$out .= $this->color->getPdfColor('#000000');
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: (string) $this->getForm('anrede', ''),
			posx: $x + 2.0,
			posy: $y,
			width: 110.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: trim((string) $this->getForm('vorname', '') . ' ' . $this->getForm('name', '')),
			posx: $x + 2.0,
			posy: $y,
			width: 110.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: (string) $this->getForm('strasse', ''),
			posx: $x + 2.0,
			posy: $y,
			width: 110.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: trim((string) $this->getForm('plz', '') . ' ' . $this->getForm('ort', '')),
			posx: $x + 2.0,
			posy: $y,
			width: 110.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);


		// x=255.0, 64.0, 30.0, 0.0
		$imageFile = __DIR__ . '/images/unterschrift.jpg';
		if (is_file($imageFile)) {
			if ($this->unterschriftImageId === null) {
				$this->unterschriftImageId = $this->image->add($imageFile);
			}
			$logoKey = $this->image->getKey($imageFile);
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, 30, 0, true);
			$out .= $this->image->getSetImage(
				$this->unterschriftImageId,
				255.0,
				64.0,
				$logoDim['width'],
				$logoDim['height'],
				self::PAGE_H,
			);
		}

		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: (string) $this->getAddress('name_1v', ''),
			posx: 255.0,
			posy: 77.0,
			width: 35.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: '1. Vorsitzender',
			posx: 255.0,
			posy: 81.0,
			width: 35.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $font_tiny['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Der Erlaubnisschein ist nur gültig in Verbindung mit dem amtlichen Fischereischein und der Ordnung zur Hege',
			posx: self::PAGE_W * 0.5 + 5.0,
			posy: 93.0,
			width: 130.0,
			height: 3.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'und Pflege der Vereinsgewässer (Gewässerordnung). Für Jugendliche gilt zusätzlich die Jugendordnung.',
			posx: self::PAGE_W * 0.5 + 8.0,
			posy: 96.0,
			width: 130.0,
			height: 3.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		// x= 226.0, 58.0, 30.0, 30.0
		$imageFile = __DIR__ . '/images/st_rund_blau300px.png';
		if (is_file($imageFile)) {
			if ($this->stempelImageId === null) {
				$this->stempelImageId = $this->image->add($imageFile);
			}
			$logoKey = $this->image->getKey($imageFile);
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, 30, 0, true);
			$out .= $this->image->getSetImage(
				$this->stempelImageId,
				226.0,
				58.0,
				$logoDim['width'],
				$logoDim['height'],
				self::PAGE_H,
			);
		}



		// x=259.0, y=23.0, w=20.0, h=0.0
		$imageFile = __DIR__ . '/images/logo_bfv2.png';
		if (is_file($imageFile)) {
			if ($this->headerLogoImageId === null) {
				$this->headerLogoImageId = $this->image->add($imageFile);
			}
			$logoKey = $this->image->getKey($imageFile);
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, 20, 0, true);
			$out .= $this->image->getSetImage(
				$this->headerLogoImageId,
				259.0,
				23.0,
				$logoDim['width'],
				$logoDim['height'],
				self::PAGE_H,
			);
		}


		$out .= $this->graph->getRoundedRect(153.0, 18.0, 138.0, 82.0, 12.5, 12.5, '1111', 'D', [[
			'lineWidth' => 0.5,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#000000',
		]]);
		return $out;
	}

	public function gen_gewaesser_block(): string {
		$font_h1 = $this->font->insert($this->pon, 'helvetica', 'B', self::H1);
		$out = "";

		// 5.0, 12.0, self::PAGE_W * 0.5 - 10.0,
		$imageFile = __DIR__ . '/images/gewaesser.jpg';
		if (is_file($imageFile)) {
			if ($this->gewaesserImageId === null) {
				$this->gewaesserImageId = $this->image->add($imageFile);
			}
			$logoKey = $this->image->getKey($imageFile);
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, (int) (self::PAGE_W * 0.5 - 10.0), 0, true);
			$out .= $this->image->getSetImage(
				$this->gewaesserImageId,
				5.0,
				12.0,
				$logoDim['width'],
				$logoDim['height'],
				self::PAGE_H,
			);
		}


		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Vereinsgewässer',
			posx: 5.0,
			posy: 6.0,
			width: 40.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);



		return $out;
	}




	public function gen_hinweis_block(): string {

		$xpos = self::PAGE_W * 0.5;
		$ypos = self::PAGE_H * 0.5;

		$font_h1 = $this->font->insert($this->pon, 'helvetica', 'B', self::H1);
		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$font_tiny = $this->font->insert($this->pon, 'helvetica', '', self::TEXT_TINY);

		$out = '';

		$qrsize = 45.0;
		$x = $xpos + self::PAGE_W * 0.5 - self::MARGIN - $qrsize - 1.0;
		$y = $ypos + self::PAGE_H * 0.5 - self::MARGIN - $qrsize - 9.0;

		$url = (string) $this->getForm('url_erlaubnisschein', '');
		if ($url !== '') {
			$out .= $this->graph->getStartTransform();
			$out .= $this->getBarcode(type: 'QRCODE,M', code: $url, posx: $xpos + self::PAGE_W * 0.5 - self::MARGIN - $qrsize - 3.0, posy: $y, width: (int) $qrsize, height: (int) $qrsize, padding: [0, 0, 0, 0], style: []);
			$out .= $this->graph->getStopTransform();
			$out .= $font_tiny['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $url,
				posx: $x - 2.0,
				posy: $y + $qrsize + 3.0,
				width: 40.0,
				height: 3.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Lieber Angler!',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 1.0,
			width: 35.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Gefangene Fische müssen nach dem waidgerechten Töten sofort in die Fangstatistik',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 8.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'eingetragen werden. Dies wird zur Fangmengenkontrolle gegebenenfalls von unseren',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 12.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'Gewässerkontrolleuren überprüft.',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 16.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'Nach Ablauf der Gültigkeit des Erlaubnisscheins muss die Fangstatistik innerhalb von',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 22.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: '2 Wochen an den ' . $this->getAddress('name_verein', '') . ' zurückgesendet werden.',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 26.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: '(' . $this->getAddress('addr_verein', '') . ', ' . $this->getAddress('ort_verein', '') . ')',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 30.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'Alternativ hierzu können die gefangenen Fische online',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 38.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'gemeldet werden:',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 42.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'Meldeformular:',
			posx: $xpos + self::MARGIN + 2.0,
			posy: $ypos + self::MARGIN + 46.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: 'www.bfv-ehingen.de/fangbuch',
			posx: $xpos + self::MARGIN + 32.0,
			posy: $ypos + self::MARGIN + 46.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->color->getPdfColor('#0000AA');
		$out .= $this->getTextCell(
			txt: 'www.bfv-ehingen.de/fangbuch',
			posx: $xpos + self::MARGIN + 32.0,
			posy: $ypos + self::MARGIN + 46.0,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		if (!empty($this->getForm('username', ''))) {
			$out .= $this->getTextCell(
				txt: 'Benutzername:',
				posx: $xpos + self::MARGIN + 2.0,
				posy: $ypos + self::MARGIN + 50.0,
				width: 130.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->getTextCell(
				txt: (string) $this->getForm('username', ''),
				posx: $xpos + self::MARGIN + 32.0,
				posy: $ypos + self::MARGIN + 50.0,
				width: 130.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->getTextCell(
				txt: 'Passwort:',
				posx: $xpos + self::MARGIN + 2.0,
				posy: $ypos + self::MARGIN + 54.0,
				width: 130.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->getTextCell(
				txt: (string) $this->getForm('password', ''),
				posx: $xpos + self::MARGIN + 32.0,
				posy: $ypos + self::MARGIN + 54.0,
				width: 130.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		return $out;
	}

	public function gen_schonzeiten_block(): void {
		$xpos = self::PAGE_W * 0.5;
		$ypos = self::PAGE_H * 0.5;

		$rand = self::MARGIN;
		$x = $xpos + $rand;
		$y = $ypos + $rand + 1.0;

		$font_h1 = $this->font->insert($this->pon, 'helvetica', 'B', self::H1);
		$out = "";
		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Mindestmaße und Schonzeiten der Fische',
			posx: 5.0,
			posy: 6.0,
			width: 40.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);


	}

	public function gen_adress_block(): string {
		$xpos = self::PAGE_W * 0.5;
		$ypos = 0;
		$font_h1 = $this->font->insert($this->pon, 'helvetica', 'B', self::H1);
		$out = "";
		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Adressen',
			posx: $xpos + self::MARGIN,
			posy: $ypos + self::MARGIN + 1,
			width: 20.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		return $out;
	}

	public function gen_fangstatistik_block(): string {
		$xpos = 0;
		$ypos = 0;

		$data = $this->getForm('statistik_options', []);
		$data['zurueck'] = 'zurückgesetzt';

		$breite1 = empty($data['breite1']) ? 15 : (float) $data['breite1'];
		$breite2 = empty($data['breite2']) ? 10 : (float) $data['breite2'];
		$breite3 = empty($data['breite3']) ? 15 : (float) $data['breite3'];
		$breite4 = empty($data['breite4']) ? 15 : (float) $data['breite4'];
		$breite5 = empty($data['breite5']) ? 10 : (float) $data['breite5'];
		$breite_fisch = empty($data['breite_fisch']) ? 4.5 : (float) $data['breite_fisch'];
		$zeilenhoehe = empty($data['zeilenhoehe']) ? 6 : (float) $data['zeilenhoehe'];
		$zeile1hoehe = empty($data['zeile1hoehe']) ? 25 : (float) $data['zeile1hoehe'];

		$font_h1 = $this->font->insert($this->pon, 'helvetica', 'B', self::H1);
		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$out = '';
		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Fangstatistik',
			posx: $xpos + self::MARGIN,
			posy: $ypos + self::MARGIN + 1,
			width: 20.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$lineStyle = [
			'width' => 0.2,
			'cap'   => 'butt',
			'join'  => 'miter',
			'dash'  => 0,
			'color' => [0, 0, 0],   // Schwarz als RGB
		];
		$fillStyle = [
			'width' => 0.2,
			'cap'   => 'butt',
			'join'  => 'miter',
			'dash'  => 0,
			'color' => [0, 0, 0],          // Schwarz
			'fill'  => [240, 240, 240],    // #F0F0F0 als RGB
		];
		$whiteFillStyle = [
			'width' => 0.2,
			'cap'   => 'butt',
			'join'  => 'miter',
			'dash'  => 0,
			'color' => [0, 0, 0],
			'fill'  => [255, 255, 255],
		];



$style4 = [
    'all' => [
        'lineWidth' => 0.5,
        'lineCap' => 'butt',
        'lineJoin' => 'miter',
        'miterLimit' => 0.5,
        'dashArray' => [],
        'dashPhase' => 0,
        'lineColor' => 'black',
        'fillColor' => 'aliceblue',
    ],
    // TOP
    0 => [
        'lineWidth' => 0.25,
        'lineCap' => 'butt',
        'lineJoin' => 'miter',
        'dashArray' => [],
        'dashPhase' => 0,
        'lineColor' => 'red',
        'fillColor' => 'powderblue',
    ],
    // RIGHT
    1 => [
        'lineWidth' => 0.25,
        'lineCap' => 'butt',
        'lineJoin' => 'miter',
        'dashArray' => [],
        'dashPhase' => 0,
        'lineColor' => 'green',
        'fillColor' => 'powderblue',
    ],
    // BOTTOM
    2 => [
        'lineWidth' => 0.50,
        'lineCap' => 'round',
        'lineJoin' => 'miter',
        'dashArray' => [],
        'dashPhase' => 0,
        'lineColor' => 'blue',
        'fillColor' => 'powderblue',
    ],
    // LEFT
    3 => [
        'lineWidth' => 0.75,
        'lineCap' => 'square',
        'lineJoin' => 'miter',
        'dashArray' => [6, 3, 2, 3],
        'dashPhase' => 0,
        'lineColor' => 'yellow',
        'fillColor' => 'powderblue',
    ],
];


		$tableX = 5.0;
		$tableY = 14.0;
		$tableW = $breite1 + $breite2 + $breite3 + $breite4 + $breite5 + ($breite_fisch * 16.0);
		$tableH = $zeile1hoehe + ($zeilenhoehe * 27.0);
		$headerRowY = $tableY;
		$rowY = $tableY + $zeile1hoehe;

		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->graph->getRect($tableX, $tableY, $tableW, $tableH, 'D', $lineStyle);

		$columnWidths = [
			$breite1,
			$breite2,
			$breite3,
			$breite4,
			$breite5,
		];
		for ($i = 1; $i <= 15; $i++) {
			$columnWidths[] = $breite_fisch;
		}
		$columnWidths[] = $breite_fisch;

		$headerText = [
			(string) ($data['text_1'] ?? ''),
			(string) ($data['text_2'] ?? ''),
			(string) ($data['text_3'] ?? ''),
			(string) ($data['text_4'] ?? ''),
			(string) ($data['text_5'] ?? ''),
		];
		for ($i = 1; $i <= 15; $i++) {
			$headerText[] = (string) ($data['fisch_' . $i] ?? '');
		}
		$headerText[] = (string) ($data['zurueck'] ?? 'zurückgesetzt');

		$cursorX = $tableX;
		foreach ($columnWidths as $index => $columnW) {
			$style = $index === 0 ? $fillStyle : $fillStyle;
			$out .= $this->graph->getRect($cursorX, $headerRowY, $columnW, $zeile1hoehe, 'DF', $style4);
			$out .= $this->graph->getStartTransform();
			$centerX = $cursorX + ($columnW * 0.5);
			$centerY = $headerRowY + ($zeile1hoehe * 0.5);
			$out .= $this->graph->getRotation(90.0, $centerX, $centerY);
			$out .= $this->getTextCell(
				txt: $headerText[$index] ?? '',
				posx: $cursorX + 1.0,
				posy: $headerRowY + 1.0,
				width: max(1.0, $columnW - 1.5),
				height: max(1.0, $zeile1hoehe - 1.5),
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->graph->getStopTransform();
			$cursorX += $columnW;
		}

		$verticalSeparatorX = $tableX;
		for ($i = 0; $i < count($columnWidths); $i++) {
			$verticalSeparatorX += $columnWidths[$i];
			$out .= $this->graph->getLine($verticalSeparatorX, $tableY, $verticalSeparatorX, $rowY + (27 * $zeilenhoehe), $lineStyle);
		}

		$horizontalY = $rowY;
		for ($row = 0; $row <= 27; $row++) {
			$drawStyle = ($row % 2 === 0) ? $whiteFillStyle : $fillStyle;
			$out .= $this->graph->getLine($tableX, $horizontalY, $tableX + $tableW, $horizontalY, $lineStyle);
			$out .= $this->graph->getRect($tableX, $horizontalY, $tableW, $zeilenhoehe, 'D', $drawStyle);
			$horizontalY += $zeilenhoehe;
		}

		$rowY = $tableY + $zeile1hoehe;
		for ($row = 1; $row <= 27; $row++) {
			$fillStyleRow = ($row % 2 === 0) ? $fillStyle : $whiteFillStyle;
			$cursorX = $tableX;
			$out .= $this->graph->getRect($cursorX, $rowY, $breite1, $zeilenhoehe, 'DF', $fillStyleRow);
			$cursorX += $breite1;
			$out .= $this->graph->getRect($cursorX, $rowY, $breite2, $zeilenhoehe, 'DF', $fillStyleRow);
			$cursorX += $breite2;
			$out .= $this->graph->getRect($cursorX, $rowY, $breite3, $zeilenhoehe, 'DF', $fillStyleRow);
			$cursorX += $breite3;
			$out .= $this->graph->getRect($cursorX, $rowY, $breite4, $zeilenhoehe, 'DF', $fillStyleRow);
			$cursorX += $breite4;
			$out .= $this->graph->getRect($cursorX, $rowY, $breite5, $zeilenhoehe, 'DF', $fillStyleRow);
			$cursorX += $breite5;
			$out .= $this->graph->getRect($cursorX, $rowY, ($breite_fisch * 16.0), $zeilenhoehe, 'DF', $fillStyleRow);
			$rowY += $zeilenhoehe;
		}

		return $out;
	}


	public function gen_fangstatistik_block_old($xpos) {
		$this->SetFontSize ( $this->FontSize_H1 );
		$this->SetXY ( $xpos + 5, 6 );
		$this->Cell ( 1, 0, "Fangstatistik", $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
		$this->SetFontSize ( $this->FontSize_TEXT );

		$data = $this->getForm('statistik_options', array());

		$breite1 = empty ( $data ['breite1'] ) ? 15 : $data ['breite1'];
		$breite2 = empty ( $data ['breite2'] ) ? 10 : $data ['breite2'];
		$breite3 = empty ( $data ['breite3'] ) ? 15 : $data ['breite3'];
		$breite4 = empty ( $data ['breite4'] ) ? 15 : $data ['breite4'];
		$breite5 = empty ( $data ['breite5'] ) ? 10 : $data ['breite5'];
		$breite_fisch = empty ( $data ['breite_fisch'] ) ? 4.5 : $data ['breite_fisch'];
		$zeilenhoehe = empty ( $data ['zeilenhoehe'] ) ? 6 : $data ['zeilenhoehe'];
		$zeile1hoehe = empty ( $data ['zeile1hoehe'] ) ? 25 : $data ['zeile1hoehe'];
		$data ['zurueck'] = 'zurückgesetzt';

		$this->SetFillColor ( 240, 240, 240 );

		$rand_x = 5;
		$rand_y = 14;

		$style = array (
				'width' => 0.05,
				'dash' => 0,
				'color' => array (
						0,
						0,
						0
				)
		);
		$this->SetLineStyle ( $style );

		$x = $xpos + $rand_x;
		$y = $rand_y;

		$this->SetXY ( $x, $y + $zeile1hoehe );
		$this->StartTransform ();
		$this->Rotate ( 90 );
		$this->Cell ( $zeile1hoehe, $breite1, ' ' . $data ['text_1'], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
		$this->StopTransform ();
		$this->SetXY ( $x, $y - $zeile1hoehe );
		$x += $breite1;

		$this->SetXY ( $x, $y + $zeile1hoehe );
		$this->StartTransform ();
		$this->Rotate ( 90 );
		$this->Cell ( $zeile1hoehe, $breite2, ' ' . $data ['text_2'], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
		$this->StopTransform ();
		$this->SetXY ( $x, $y - $zeile1hoehe );
		$x += $breite2;

		$this->SetXY ( $x, $y + $zeile1hoehe );
		$this->StartTransform ();
		$this->Rotate ( 90 );
		$this->Cell ( $zeile1hoehe, $breite3, ' ' . $data ['text_3'], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
		$this->StopTransform ();
		$this->SetXY ( $x, $y - $zeile1hoehe );
		$x += $breite3;

		$this->SetXY ( $x, $y + $zeile1hoehe );
		$this->StartTransform ();
		$this->Rotate ( 90 );
		$this->Cell ( $zeile1hoehe, $breite4, ' ' . $data ['text_4'], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
		$this->StopTransform ();
		$this->SetXY ( $x, $y - $zeile1hoehe );
		$x += $breite4;

		$this->SetXY ( $x, $y + $zeile1hoehe );
		$this->StartTransform ();
		$this->Rotate ( 90 );
		$this->Cell ( $zeile1hoehe, $breite5, ' ' . $data ['text_5'], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
		$this->StopTransform ();
		$this->SetXY ( $x, $y - $zeile1hoehe );
		$x += $breite5;

		for($i = 1; $i <= 15; $i ++) {
			$key = 'fisch_' . $i;

			$this->SetXY ( $x, $y + $zeile1hoehe );
			$this->StartTransform ();
			$this->Rotate ( 90 );
			$this->Cell ( $zeile1hoehe, $breite_fisch, ' ' . $data [$key], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
			$this->StopTransform ();
			$this->SetXY ( $x, $y - $zeile1hoehe );
			$x += $breite_fisch;
		}

		$this->SetXY ( $x, $y + $zeile1hoehe );
		$this->StartTransform ();
		$this->Rotate ( 90 );
		$this->Cell ( $zeile1hoehe, $breite_fisch, ' ' . $data ['zurueck'], $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'C' );
		$this->StopTransform ();
		$this->SetXY ( $x, $y - $zeile1hoehe );
		$x += $breite_fisch;

		$this->SetFontSize ( 1 );
		$y += $zeile1hoehe;

		$yy = $y;
		for($zn = 1; $zn <= 27; $zn ++) {

			if (($zn % 2) == 0) {
				$this->SetFillColor ( 240, 240, 240 );
			} else {
				$this->SetFillColor ( 255, 255, 255 );
			}
			$x = $xpos + $rand_x;
			$x += $breite1;
			$x += $breite2;
			$x += $breite3;
			$x += $breite4;
			$x += $breite5;

			$this->SetXY ( $x, $yy );

			$this->Cell ( $breite_fisch * 16, $zeilenhoehe, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$yy += $zeilenhoehe;
		}

		for($f = 1; $f <= 16; $f ++) {
			$xx = $xpos + $rand_x + $breite1 + $breite2 + $breite3 + $breite4 + $breite5 + $breite_fisch * $f - $breite_fisch * 0.5;
			$this->Line ( $xx, $y, $xx, $y + 27 * $zeilenhoehe, $style );
		}

		for($zn = 1; $zn <= 27; $zn ++) {

			if (($zn % 2) == 0) {
				$this->SetFillColor ( 240, 240, 240 );
			} else {
				$this->SetFillColor ( 255, 255, 255 );
			}

			$x = $xpos + $rand_x;
			$this->SetXY ( $x, $y );
			$this->Cell ( $breite1, $zeilenhoehe, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$x += $breite1;

			$this->SetXY ( $x, $y );
			$this->Cell ( $breite2, $zeilenhoehe, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$x += $breite2;

			$this->SetXY ( $x, $y );
			$this->Cell ( $breite3, $zeilenhoehe, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$x += $breite3;

			$this->SetXY ( $x, $y );
			$this->Cell ( $breite4, $zeilenhoehe, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$x += $breite4;

			$this->SetXY ( $x, $y );
			$this->Cell ( $breite5, $zeilenhoehe, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$x += $breite5;

			$breite = $breite_fisch - 2.2;

			$y += ($zeilenhoehe - $breite) * 0.5;
			for($i = 1; $i <= 15; $i ++) {
				$key = 'fisch_' . $i;
				$x += ($breite_fisch - $breite) * 0.5;
				$this->SetXY ( $x, $y );
				$this->Cell ( $breite, $breite, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
				$x += $breite_fisch - ($breite_fisch - $breite) * 0.5;
			}
			$x += ($breite_fisch - $breite) * 0.5;
			$this->SetXY ( $x, $y );
			$this->Cell ( $breite, $breite, '', $border = 1, $ln = 0, $align = '', $fill = 1, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'T' );
			$x += $breite_fisch - ($breite_fisch - $breite) * 0.5;
			$y += $zeilenhoehe - ($zeilenhoehe - $breite) * 0.5;
		}
		$this->SetFontSize ( $this->FontSize_TEXT );
	}



















	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->erste_Seite();
		$this->zweite_Seite();
	}
}

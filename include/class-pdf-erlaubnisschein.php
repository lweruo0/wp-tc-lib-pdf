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
	private const MARGIN_TOP = 6.0;
	private const H1 = 17;
	private const TEXT_LETTER = 12;
	private const TEXT = 8;
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
		$formdata['gewaesser'] = get_option ( 'bfv_gewaesser' );

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
		$out .= $this->gen_ueberschriften1();
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
		$out .= $this->gen_adress_block();
		$out .= $this->gen_schonzeiten_block();
		$out .= $this->gen_ueberschriften2();
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}


	public function gen_erlaubnis_block(): string {


		$font_letter = $this->font->insert($this->pon, 'helvetica', '', self::TEXT_LETTER);
		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$font_tiny = $this->font->insert($this->pon, 'helvetica', '', self::TEXT_TINY);


		$x = self::PAGE_W * 0.5 + self::MARGIN + 6.0;
		$y = self::MARGIN + 29.0;
		$out = "";
		$out .= $font_letter['out'];
		$out .= $this->color->getPdfColor('#000000');

		$out .= $this->getTextCell(
			txt: 'zum Fischfang in unseren Vereinsgewässern',
			posx: $x,
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



		$tage = (int) $this->getForm('tage', 0);
		if ($tage > 1) {
			$out .= $this->getTextCell(
				txt: 'vom ' . $this->getForm('von', '') . ' bis ' . $this->getForm('bis', '') . ' (' . $tage . ' Tage).',
				posx: $x,
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
				posx: $x,
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
		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');

		if ((int) $this->getForm('no_hinweis_begleitet', 0) !== 1) {
			$out .= $this->getTextCell(
				txt: 'Nur in Begleitung eines aktiven Vereinsmitglieds gültig.',
				posx: $x,
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
				posx: $x,
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
				posx: $x,
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
				posx: $x,
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
			posx: $x,
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
			posx: $x,
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
			posx: $x,
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
			posx: $x,
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
			posx: self::PAGE_W * 0.5 + 6.0,
			posy: 93.0,
			width: 130.0,
			height: 3.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
			drawcell: true,
		);
		$out .= $this->getTextCell(
			txt: 'und Pflege der Vereinsgewässer (Gewässerordnung). Für Jugendliche gilt zusätzlich die Jugendordnung.',
			posx: self::PAGE_W * 0.5 + 6.0,
			posy: 96.0,
			width: 130.0,
			height: 3.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
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
				225.0,
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

			$fillStyle = [
				'lineWidth' => 0.5,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => '#ffffff',
			];

			$styles = [
				'all' => $fillStyle,
				0 => $fillStyle,
				1 => $fillStyle,
				2 => $fillStyle,
				3 => $fillStyle,
			];


		$out .= $this->graph->getRoundedRect(153.0, 18.0, 138.0, 82.0, 12.5, 12.5, '1111', 'D', $fillStyle);
		return $out;
	}

	public function gen_gewaesser_block(): string {
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
				self::MARGIN,
				self::MARGIN+8,
				$logoDim['width'],
				$logoDim['height'],
				self::PAGE_H,
			);
		}



		$font = $this->font->insert($this->pon, 'helvetica', '', 8);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');
		

		//
		//
		// Gewässerbeschreibungen
		//
		//
		$x = self::MARGIN;
		$y_addr = self::PAGE_H * 0.5 + self::MARGIN + 6.0;

		$x_spalte2 = $x + 6;
		$x_spalte3 = $x_spalte2 + 31;

		$gewaesser  = $this->getForm('gewaesser', []);

		for($i = 1; $i <= 20; $i ++) {
			$k_name = 'name_g' . $i;
			$k_num = 'nummer_g' . $i;
			$k_besch1 = 'beschr1_g' . $i;
			$k_besch2 = 'beschr2_g' . $i;
			$k_besch3 = 'beschr3_g' . $i;

			if (! empty ( $gewaesser [$k_name] ) || ! empty ( $gewaesser [$k_num] )) {

				$out .= $this->getTextCell(
					txt: $gewaesser [$k_num],
					posx: $x,
					posy: $y_addr,
					width: 6.0,
					height: 6.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Center,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);

				$out .= $this->getTextCell(
					txt: $gewaesser [$k_name],
					posx: $x_spalte2,
					posy: $y_addr,
					width: 120.0,
					height: 6.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Center,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);

				$out .= $this->getTextCell(
					txt: $gewaesser [$k_besch1],
					posx: $x_spalte3,
					posy: $y_addr,
					width: 120.0,
					height: 6.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Center,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);


				if (! empty ( $gewaesser [$k_besch2] )) {
					$y_addr += 3.5;
					$out .= $this->getTextCell(
						txt: $gewaesser [$k_besch2],
						posx: $x_spalte3,
						posy: $y_addr,
						width: 120.0,
						height: 6.0,
						offset: 0,
						linespace: 0,
						valign: \Com\Tecnick\Pdf\TextVAlign::Center,
						halign: \Com\Tecnick\Pdf\TextHAlign::Left,
						drawcell: false,
					);				
				}

				if (! empty ( $gewaesser [$k_besch3] )) {
					$y_addr += 3.5;
					$out .= $this->getTextCell(
						txt: $gewaesser [$k_besch3],
						posx: $x_spalte3,
						posy: $y_addr,
						width: 120.0,
						height: 6.0,
						offset: 0,
						linespace: 0,
						valign: \Com\Tecnick\Pdf\TextVAlign::Center,
						halign: \Com\Tecnick\Pdf\TextHAlign::Left,
						drawcell: false,
					);
				}
				$y_addr += 4;
			}
		}






		return $out;
	}

	public function gen_ueberschriften1(): string {
		$out = '';
		$font_h1 = $this->font->insert($this->pon, 'helvetica', '', self::H1);
		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');

		$out .= $this->getTextCell(
			txt: 'Fangstatistik',
			posx: self::MARGIN,
			posy: self::MARGIN_TOP,
			width: 140.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $this->getTextCell(
			txt: 'Bezirksfischerei-Verein e.V. Ehingen/Donau',
			posx: self::PAGE_W * 0.5 + self::MARGIN,
			posy: self::MARGIN_TOP,
			width: 140.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $this->getTextCell(
			txt: 'Erlaubnisschein',
			posx: self::PAGE_W * 0.5 + self::MARGIN + 6.0,
			posy: self::MARGIN_TOP + 18.0,
			width: 140.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $this->getTextCell(
			txt: 'Lieber Angler!',
			posx: self::PAGE_W * 0.5 + self::MARGIN,
			posy: self::PAGE_H * 0.5 + self::MARGIN_TOP,
			width: 140.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		return $out;
	}

	public function gen_ueberschriften2(): string {
		$out = '';
		$font_h1 = $this->font->insert($this->pon, 'helvetica', '', self::H1);
		$out .= $font_h1['out'];
		$out .= $this->color->getPdfColor('#000000');

		$out .= $this->getTextCell(
			txt: 'Vereinsgewässer',
			posx: self::MARGIN,
			posy: self::MARGIN_TOP,
			width: 140.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $this->getTextCell(
			txt: 'Adressen',
			posx: self::PAGE_W * 0.5 + self::MARGIN,
			posy: self::MARGIN_TOP,
			width: 140.0,
			height: 5.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);

		$out .= $this->getTextCell(
			txt: 'Mindestmaße und Schonzeiten der Fische',
			posx: self::PAGE_W * 0.5 + self::MARGIN,
			posy: self::PAGE_H * 0.5 + self::MARGIN_TOP,
			width: 140.0,
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

		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$font_tiny = $this->font->insert($this->pon, 'helvetica', '', 2);

		$out = '';

			//$out .= $this->graph->getStopTransform();
			$out .= $font_tiny['out'];
			$out .= $this->color->getPdfColor('#000000');

		$url = (string) $this->getForm('url_erlaubnisschein', '');
		if ($url !== '') {
			//$out .= $this->graph->getStartTransform();

			$qrsize = 40.0;
			$qrX = $xpos + self::PAGE_W * 0.5 - self::MARGIN - $qrsize - 1.0;
			$qrY = $ypos + self::PAGE_H * 0.5 - self::MARGIN - $qrsize - 9.0;
			$qrContent = $url;

			$out .= $this->getBarcode(
				type: 'QRCODE,M',
				code: $qrContent,
				posx: $qrX,
				posy: $qrY,
				width: (int) $qrsize,
				height: (int) $qrsize,
				padding: [0, 0, 0, 0],
				style: [],
			);

			//$out .= $this->graph->getStopTransform();
			$out .= $font_tiny['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $url,
				posx: $qrX,
				posy: $qrY + $qrsize + 1.0,
				width: 40.0,
				height: 3.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}


		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');
		$y = $ypos + self::MARGIN + 12.0;
		$out .= $this->getTextCell(
			txt: 'Gefangene Fische müssen nach dem waidgerechten Töten sofort in die Fangstatistik',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 4.0;
		$out .= $this->getTextCell(
			txt: 'eingetragen werden. Dies wird zur Fangmengenkontrolle gegebenenfalls von unseren',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 4.0;
		$out .= $this->getTextCell(
			txt: 'Gewässerkontrolleuren überprüft.',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: 'Nach Ablauf der Gültigkeit des Erlaubnisscheins muss die Fangstatistik innerhalb von',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 4.0;
		$out .= $this->getTextCell(
			txt: '2 Wochen an den ' . $this->getAddress('name_verein', '') . ' zurückgesendet werden.',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 4.0;
		$out .= $this->getTextCell(
			txt: '(' . $this->getAddress('addr_verein', '') . ', ' . $this->getAddress('ort_verein', '') . ')',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 7.0;
		$out .= $this->getTextCell(
			txt: 'Alternativ hierzu können die gefangenen Fische online',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 4.0;
		$out .= $this->getTextCell(
			txt: 'gemeldet werden:',
			posx: $xpos + self::MARGIN,
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 5.0;
		$out .= $this->getTextCell(
			txt: 'Meldeformular:',
			posx: $xpos + self::MARGIN,
			posy: $y,
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
			posy: $y,
			width: 130.0,
			height: 4.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$y += 4.0;

		if (!empty($this->getForm('username', ''))) {
			$out .= $this->getTextCell(
				txt: 'Benutzername:',
				posx: $xpos + self::MARGIN,
				posy: $y,
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
				posy: $y,
				width: 130.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 4.0;
		}

		if (!empty($this->getForm('password', ''))) {
			$out .= $this->getTextCell(
				txt: 'Passwort:',
				posx: $xpos + self::MARGIN,
				posy: $y,
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
				posy: $y,
				width: 130.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 4.0;
		} else {
			if (!empty($this->getForm('otp_password1', ''))) {
				$out .= $this->getTextCell(
					txt: 'Einmaliges Passwort:',
					posx: $xpos + self::MARGIN,
					posy: $y,
					width: 130.0,
					height: 4.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Top,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);
				$out .= $this->getTextCell(
					txt: (string) $this->getForm('otp_password1', ''),
					posx: $xpos + self::MARGIN + 32.0,
					posy: $y,
					width: 130.0,
					height: 4.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Top,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);
				$y += 4.0;
			} 			
			if (!empty($this->getForm('otp_password2', ''))) {
				$out .= $this->getTextCell(
					txt: 'Einmaliges Passwort:',
					posx: $xpos + self::MARGIN,
					posy: $y,
					width: 130.0,
					height: 4.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Top,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);
				$out .= $this->getTextCell(
					txt: (string) $this->getForm('otp_password2', ''),
					posx: $xpos + self::MARGIN + 32.0,
					posy: $y,
					width: 130.0,
					height: 4.0,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Top,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
					drawcell: false,
				);
				$y += 4.0;
			}
		}

		return $out;
	}




	public function gen_schonzeiten_block(): string {
		$xpos = self::PAGE_W * 0.5;
		$ypos = self::PAGE_H * 0.5;

		$x = $xpos + self::MARGIN;
		$y = $ypos + self::MARGIN + 3.0;

		$schonzeiten = $this->getForm('schonzeiten', []);

		$font_text = $this->font->insert($this->pon, 'helvetica', '', 8);
		$font_tiny = $this->font->insert($this->pon, 'helvetica', '', self::TEXT_TINY);
		$out = '';

		$y += 5.0;

		$out .= $font_text['out'];
		$rowHeight = 3.5;

		$buildCellStyles = static function (string $stylestring, string $fillColor): array {
			$fillStyle = [
				'lineWidth' => 0,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => $fillColor,
			];

			$styles = [
				'all' => $fillStyle,
				0 => $fillStyle,
				1 => $fillStyle,
				2 => $fillStyle,
				3 => $fillStyle,
			];

			if (\strpos($stylestring, 'T') !== false) {
				$styles[0] = \array_merge($fillStyle, ['lineWidth' => 0.1, 'lineColor' => '#000000']);
			}
			if (\strpos($stylestring, 'R') !== false) {
				$styles[1] = \array_merge($fillStyle, ['lineWidth' => 0.1, 'lineColor' => '#000000']);
			}
			if (\strpos($stylestring, 'B') !== false) {
				$styles[2] = \array_merge($fillStyle, ['lineWidth' => 0.1, 'lineColor' => '#000000']);
			}
			if (\strpos($stylestring, 'L') !== false) {
				$styles[3] = \array_merge($fillStyle, ['lineWidth' => 0.1, 'lineColor' => '#000000']);
			}

			return $styles;
		};

		$out .= $this->getTextCell(
			txt: 'Fischart',
			posx: $x,
			posy: $y,
			width: 34.0,
			height: $rowHeight,
			offset: 0.5,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $buildCellStyles('LTRB', '#f0f0f0'),
			drawcell: true,
		);
		$x += 34.0;
		$out .= $this->getTextCell(
			txt: 'Schonzeit',
			posx: $x,
			posy: $y,
			width: 34.0,
			height: $rowHeight,
			offset: -4,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
			styles: $buildCellStyles('LTRB', '#f0f0f0'),
			drawcell: true,
		);
		$x += 34.0;
		$out .= $this->getTextCell(
			txt: 'Mindestmaß',
			posx: $x,
			posy: $y,
			width: 34.0,
			height: $rowHeight,
			offset: -4,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
			styles: $buildCellStyles('LTRB', '#f0f0f0'),
			drawcell: true,
		);
		$x += 34.0;
		$out .= $this->getTextCell(
			txt: 'tägliche Fangmenge',
			posx: $x,
			posy: $y,
			width: 34.0,
			height: $rowHeight,
			offset: -5,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
			styles: $buildCellStyles('LTRB', '#f0f0f0'),
			drawcell: true,
		);
		$y += $rowHeight;

		$fischnummer = [
			'0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29',
		];

		foreach ($fischnummer as $nr) {
			$t1 = $schonzeiten['fisch_' . $nr] ?? '';
			$t2 = $schonzeiten['schonzeit_' . $nr] ?? '';
			$t3 = $schonzeiten['schonmass_' . $nr] ?? '';
			$t4 = $schonzeiten['fangmenge_' . $nr] ?? '';

			if ($t1 === '' && $t2 === '' && $t3 === '' && $t4 === '') {
				continue;
			} 
			$t1 = $t1 ==='' ? ' ' : $t1;
			$t2 = $t2 ==='' ? ' ' : $t2;
			$t3 = $t3 ==='' ? ' ' : $t3;
			$t4 = $t4 ==='' ? ' ' : $t4;

			$borderString = $schonzeiten['border_' . $nr] ?? 'LTRB';
			$borderParts = preg_split('/\s+/', $borderString, -1, PREG_SPLIT_NO_EMPTY);
			$borderParts = array_pad($borderParts, 4, 'LTRB');

			$xRow = $xpos + self::MARGIN;
			$out .= $this->getTextCell(
				txt: $t1,
				posx: $xRow,
				posy: $y,
				width: 34.0,
				height: $rowHeight,
				offset: 0.5,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				styles: $buildCellStyles($borderParts[0], '#ffffff'),
				drawcell: true,
			);
			$xRow += 34.0;
			$out .= $this->getTextCell(
				txt: $t2,
				posx: $xRow,
				posy: $y,
				width: 34.0,
				height: $rowHeight,
				offset: -4,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Center,
				styles: $buildCellStyles($borderParts[1], '#ffffff'),
				drawcell: true,
			);
			$xRow += 34.0;
			$out .= $this->getTextCell(
				txt: $t3,
				posx: $xRow,
				posy: $y,
				width: 34.0,
				height: $rowHeight,
				offset: -4,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Center,
				styles: $buildCellStyles($borderParts[2], '#ffffff'),
				drawcell: true,
			);
			$xRow += 34.0;
			$out .= $this->getTextCell(
				txt: $t4,
				posx: $xRow,
				posy: $y,
				width: 34.0,
				height: $rowHeight,
				offset: -5,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Center,
				styles: $buildCellStyles($borderParts[3], '#ffffff'),
				drawcell: true,
			);
			$y += $rowHeight;
		}

		$out .= $font_tiny['out'];
		$noteHeight = 3.0;
		$y += 1.0;
		$zeilennummer = ['1', '2', '3', '4'];
		foreach ($zeilennummer as $nr) {
			$txt = $schonzeiten['zeile_' . $nr] ?? '';
			$out .= $this->getTextCell(
				txt: $txt,
				posx: $xpos + self::MARGIN,
				posy: $y,
				width: 140.0,
				height: $noteHeight,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += $noteHeight;
		}

		return $out;
	}


	public function gen_adress_block(): string {
		$xpos = self::PAGE_W * 0.5;
		$ypos = 0.0;

		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$out = '';


		$out .= $font_text['out'];
		$out .= $this->color->getPdfColor('#000000');
		$x_label = $xpos + self::MARGIN;
		$x_value = $xpos + self::MARGIN + 32.0;
		$y = $ypos + self::MARGIN + 9.0;

		$rows = [
			[
				'label' => '1. Vorsitzender',
				'name' => 'name_1v',
				'strasse' => 'strasse_1v',
				'ort' => 'ort_1v',
				'tel' => 'tel_1v',
			],
			[
				'label' => '2. Vorsitzender',
				'name' => 'name_2v',
				'strasse' => 'strasse_2v',
				'ort' => 'ort_2v',
				'tel' => 'tel_2v',
			],
			[
				'label' => 'Ehrenvorsitzender',
				'name' => 'name_ev',
				'strasse' => 'strasse_ev',
				'ort' => 'ort_ev',
				'tel' => 'tel_ev',
			],
			[
				'label' => 'Kassier',
				'name' => 'name_kassier',
				'strasse' => 'strasse_kassier',
				'ort' => 'ort_kassier',
				'tel' => 'tel_kassier',
			],
			[
				'label' => 'Schriftführer',
				'name' => 'name_schriftfuehrer',
				'strasse' => 'strasse_schriftfuehrer',
				'ort' => 'ort_schriftfuehrer',
				'tel' => 'tel_schriftfuehrer',
			],
		];

		foreach ($rows as $row) {
			$name = trim((string) $this->getAddress($row['name'], ''));
			$strasse = trim((string) $this->getAddress($row['strasse'], ''));
			$ort = trim((string) $this->getAddress($row['ort'], ''));
			$tel = trim((string) $this->getAddress($row['tel'], ''));
			$text = implode(', ', array_filter([$name, $strasse, $ort, $tel], static fn(string $value): bool => $value !== ''));
			if ($text === '') {
				continue;
			}

			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $x_label,
				posy: $y,
				width: 30.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->getTextCell(
				txt: $text,
				posx: $x_value,
				posy: $y,
				width: 90.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 5.0;
		}

		for ($i = 1; $i <= 10; $i++) {
			$k_name = 'name_b' . $i;
			$k_funk = 'funktion_b' . $i;
			$k_str = 'strasse_b' . $i;
			$k_ort = 'ort_b' . $i;
			$k_tel = 'tel_b' . $i;
			$name = trim((string) $this->getAddress($k_name, ''));
			$funktion = trim((string) $this->getAddress($k_funk, ''));
			$strasse = trim((string) $this->getAddress($k_str, ''));
			$ort = trim((string) $this->getAddress($k_ort, ''));
			$tel = trim((string) $this->getAddress($k_tel, ''));
			$text = implode(', ', array_filter([$name, $strasse, $ort, $tel], static fn(string $value): bool => $value !== ''));
			if ($name === '' || $funktion === '') {
				continue;
			}

			$out .= $this->getTextCell(
				txt: $funktion,
				posx: $x_label,
				posy: $y,
				width: 30.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->getTextCell(
				txt: $text,
				posx: $x_value,
				posy: $y,
				width: 90.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 5.0;
		}

		$nameVerein = trim((string) $this->getAddress('name_verein', ''));
		$addrVerein = trim((string) $this->getAddress('addr_verein', ''));
		$ortVerein = trim((string) $this->getAddress('ort_verein', ''));
		if ($nameVerein !== '' || $addrVerein !== '' || $ortVerein !== '') {
			$out .= $this->getTextCell(
				txt: 'Vereinsanschrift',
				posx: $x_label,
				posy: $y,
				width: 30.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->getTextCell(
				txt: implode(', ', array_filter([$nameVerein, $addrVerein, $ortVerein], static fn(string $value): bool => $value !== '')),
				posx: $x_value,
				posy: $y,
				width: 90.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$y += 5.0;
		}

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
		$zeile1hoehe = empty($data['zeile1hoehe']) ? 27 : (float) $data['zeile1hoehe'];

		$font_text = $this->font->insert($this->pon, 'helvetica', '', self::TEXT);
		$out = '';




		$lineStyle = [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => 'black',
		];
		$fillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => 'black',
				'fillColor' => '#efefef',
			],
		];
		$whiteFillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => 'black',
				'fillColor' => 'white',
			],
		];

		$testFillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => 'black',
				'fillColor' => 'orange',
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
			$out .= $this->graph->getRect($cursorX, $headerRowY, $columnW, $zeile1hoehe, 'DF', $fillStyle);
			$out .= $this->graph->getStartTransform();
			$out .= $this->graph->getTranslation($cursorX, $headerRowY + $zeile1hoehe - 1.0);
			$out .= $this->graph->getRotation(90.0, 0.0, 0.0);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $headerText[$index] ?? '',
				posx: 0.0,
				posy: 0.0,
				width: $zeile1hoehe,
				height: $columnW,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->graph->getStopTransform();
			$cursorX += $columnW;
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

		$verticalSeparatorX = $tableX;
		for ($i = 0; $i < count($columnWidths); $i++) {
			$horizontalY = $tableY + $zeile1hoehe;
			$verticalSeparatorX += $columnWidths[$i];
			if ($i < 5) {
				continue; // Skip the first 6 columns for vertical separators
			}

			$out .= $this->graph->getLine($verticalSeparatorX-$columnWidths[$i]*0.5, $horizontalY, $verticalSeparatorX-$columnWidths[$i]*0.5, $horizontalY + (27 * $zeilenhoehe), $lineStyle);
			for ($row = 0; $row <= 26; $row++) {
				$out .= $this->graph->getRect($verticalSeparatorX-$columnWidths[$i]*0.5-1.5, $horizontalY+$zeilenhoehe*0.5-1.5, 3, 3, 'DF', $whiteFillStyle);
				$horizontalY += $zeilenhoehe;
			}
		}
		return $out;
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

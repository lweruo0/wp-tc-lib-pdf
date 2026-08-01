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
class PdfErlaubnisschein extends PdfTemplate {
	private const PAGE_W = 297.0;
	private const PAGE_H = 210.0;
	private const MARGIN = 5.0;
	private const H1 = 17;
	private const TEXT_LETTER = 12;
	private const TEXT = 9;
	private const TEXT_TINY = 7;

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
		$this->setFileName("rechnung_$rechnungsnummer.pdf");

		if (function_exists('bfverlaubnisscheine')) {
			$instance = bfverlaubnisscheine();
			$formdata = $instance->get_formdata_by_rechnungsnummer($rechnungsnummer);
		} else {
			$formdata = [];
		}

		$formdata['documenttype'] = 'Erlaubnisschein';
		$formdata['returnme'] = 'falls unzustellbar, bitte zurück';
		$this->setFormdata($formdata);
	}

	private function drawText(float $x, float $y, string $text, int $fontSize = self::TEXT, string $fontStyle = '', string $halign = 'L', float $width = 0.0, float $height = 4.0, string $color = '#000000'): void {
		$font = $this->font->insert($this->pon, 'helvetica', $fontStyle, $fontSize);
		$out = $this->graph->getStartTransform();
		$out .= $font['out'];
		$out .= $this->color->getPdfColor($color);
		$w = $width > 0.0 ? $width : 150.0;
		$out .= $this->getTextCell(
			txt: $text,
			posx: $x,
			posy: $y,
			width: $w,
			height: $height,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: $halign === 'L' ? \Com\Tecnick\Pdf\TextHAlign::Left : ( $halign === 'C' ? \Com\Tecnick\Pdf\TextHAlign::Center : \Com\Tecnick\Pdf\TextHAlign::Right ),
			drawcell: false,
		);
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	private function drawImage(string $file, float $x, float $y, float $w, float $h): void {
		if (!is_file($file)) {
			return;
		}
		$imageId = $this->image->add($file);
		$out = $this->graph->getStartTransform();
		$out .= $this->image->getSetImage($imageId, $x, $y, $w, $h, self::PAGE_H);
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	private function drawRoundedRect(float $x, float $y, float $w, float $h, float $rx, float $ry, string $corners = '1111', string $style = 'D', array $styles = []): void {
		$out = $this->graph->getStartTransform();
		$out .= $this->graph->getRoundedRect($x, $y, $w, $h, $rx, $ry, $corners, $style, $styles);
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function erste_Seite(): void {
		$this->addPage([
			'orientation' => 'L',
			'format' => 'A4',
		]);

		$this->gen_fangstatistik_block(0.0);
		$this->gen_erlaubnis_block(self::PAGE_W * 0.5, 0.0);
		$this->gen_hinweis_block(self::PAGE_W * 0.5, self::PAGE_H * 0.5);
	}

	public function zweite_Seite(): void {
		$this->addPage([
			'orientation' => 'L',
			'format' => 'A4',
		]);

		$this->gen_gewaesser_block(0.0);
		$this->gen_adress_block(self::PAGE_W * 0.5, 0.0);
		$this->gen_schonzeiten_block(self::PAGE_W * 0.5, self::PAGE_H * 0.5);
	}

	public function gen_erlaubnis_block(float $xpos, float $ypos): void {
		$rand = self::MARGIN;
		$x = $xpos + $rand;
		$y = $ypos + $rand + 1.0;

		$this->drawText($x, $y, 'Bezirksfischerei-Verein e.V. Ehingen/Donau', self::H1, 'B', 'L', 150.0, 5.0);
		$y += 9.0;

		$this->drawText($x + 2.0, $y, 'Erlaubnisschein', self::TEXT_LETTER, '', 'L', 50.0, 5.0);
		$y += 5.0;
		$this->drawText($x + 2.0, $y, 'zum Fischfang in unseren Vereinsgewässern', self::TEXT_LETTER, '', 'L', 90.0, 5.0);
		$y += 5.0;

		$tage = (int) $this->getForm('tage', 0);
		if ($tage > 1) {
			$this->drawText($x + 2.0, $y, 'vom ' . $this->getForm('von', '') . ' bis ' . $this->getForm('bis', '') . ' (' . $tage . ' Tage).', self::TEXT, '', 'L', 110.0, 5.0);
		} else {
			$this->drawText($x + 2.0, $y, 'am ' . $this->getForm('von', '') . ' (1 Tag).', self::TEXT, '', 'L', 110.0, 5.0);
		}
		$y += 5.0;

		if ((int) $this->getForm('no_hinweis_begleitet', 0) !== 1) {
			$this->drawText($x + 2.0, $y, 'Nur in Begleitung eines aktiven Vereinsmitglieds gültig.', self::TEXT, '', 'L', 110.0, 5.0);
			$y += 4.0;
			$this->drawText($x + 2.0, $y, 'Verfügt das begleitende Vereinsmitglied über Bootsstempel,', self::TEXT, '', 'L', 110.0, 5.0);
			$y += 4.0;
			$this->drawText($x + 2.0, $y, 'dann ist das Probemitlied zur Mitnutzung des Boots berechtigt.', self::TEXT, '', 'L', 110.0, 5.0);
			$y += 4.0;
			$this->drawText($x + 2.0, $y, 'Verantwortliches Vereinsmitglied: ' . $this->getForm('mitgliedsname', ''), self::TEXT, '', 'L', 110.0, 5.0);
		} else {
			$y += 8.0;
		}

		$y += 5.0;
		$this->drawText($x + 2.0, $y, (string) $this->getForm('anrede', ''), self::TEXT_LETTER, '', 'L', 110.0, 5.0);
		$y += 5.0;
		$this->drawText($x + 2.0, $y, trim((string) $this->getForm('vorname', '') . ' ' . $this->getForm('name', '')), self::TEXT_LETTER, '', 'L', 110.0, 5.0);
		$y += 5.0;
		$this->drawText($x + 2.0, $y, (string) $this->getForm('strasse', ''), self::TEXT_LETTER, '', 'L', 110.0, 5.0);
		$y += 5.0;
		$this->drawText($x + 2.0, $y, trim((string) $this->getForm('plz', '') . ' ' . $this->getForm('ort', '')), self::TEXT_LETTER, '', 'L', 110.0, 5.0);

		$this->drawText(255.0, 77.0, (string) $this->getAddress('name_1v', ''), self::TEXT, '', 'L', 35.0, 4.0);
		$this->drawText(255.0, 81.0, '1. Vorsitzender', self::TEXT, '', 'L', 35.0, 4.0);

		$this->drawText(self::PAGE_W * 0.5 + 5.0, 93.0, 'Der Erlaubnisschein ist nur gültig in Verbindung mit dem amtlichen Fischereischein und der Ordnung zur Hege', self::TEXT_TINY, '', 'L', 130.0, 3.0);
		$this->drawText(self::PAGE_W * 0.5 + 8.0, 96.0, 'und Pflege der Vereinsgewässer (Gewässerordnung). Für Jugendliche gilt zusätzlich die Jugendordnung.', self::TEXT_TINY, '', 'L', 130.0, 3.0);

		$logoFile = __DIR__ . '/images/logo_fischereiverein300x370.png';
		$this->drawImage($logoFile, 259.0, 23.0, 20.0, 0.0);
		$this->drawImage(__DIR__ . '/images/st_rund_blau300px.png', 226.0, 58.0, 30.0, 30.0);
		$this->drawImage(__DIR__ . '/images/unterschrift.jpg', 255.0, 64.0, 30.0, 0.0);

		$this->drawRoundedRect(153.0, 18.0, 138.0, 82.0, 12.5, 12.5, '1111', 'D', [[
			'lineWidth' => 0.5,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#000000',
		]]);
	}

	public function gen_gewaesser_block(float $xpos): void {
		$this->drawText($xpos + 5.0, 6.0, 'Vereinsgewässer', self::H1, 'B', 'L', 40.0, 5.0);
		$this->drawImage(__DIR__ . '/images/gewaesser.jpg', $xpos + 5.0, 12.0, self::PAGE_W * 0.5 - 10.0, 0.0);
	}

	public function gen_hinweis_block(float $xpos, float $ypos): void {
		$qrsize = 45.0;
		$x = $xpos + self::PAGE_W * 0.5 - self::MARGIN - $qrsize - 1.0;
		$y = $ypos + self::PAGE_H * 0.5 - self::MARGIN - $qrsize - 9.0;

		$url = (string) $this->getForm('url_erlaubnisschein', '');
		if ($url !== '') {
			$out = $this->graph->getStartTransform();
			$out .= $this->getBarcode(type: 'QRCODE,M', code: $url, posx: $xpos + self::PAGE_W * 0.5 - self::MARGIN - $qrsize - 3.0, posy: $y, width: (int) $qrsize, height: (int) $qrsize, padding: [0, 0, 0, 0], style: []);
			$out .= $this->graph->getStopTransform();
			$this->page->addContent($out);
		}

		$this->drawText($x - 2.0, $y + $qrsize + 3.0, $url, 3, '', 'L', 40.0, 3.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 1.0, 'Lieber Angler!', self::H1, 'B', 'L', 35.0, 5.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 8.0, 'Gefangene Fische müssen nach dem waidgerechten Töten sofort in die Fangstatistik', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 12.0, 'eingetragen werden. Dies wird zur Fangmengenkontrolle gegebenenfalls von unseren', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 16.0, 'Gewässerkontrolleuren überprüft.', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 22.0, 'Nach Ablauf der Gültigkeit des Erlaubnisscheins muss die Fangstatistik innerhalb von', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 26.0, '2 Wochen an den ' . $this->getAddress('name_verein', '') . ' zurückgesendet werden.', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 30.0, '(' . $this->getAddress('addr_verein', '') . ', ' . $this->getAddress('ort_verein', '') . ')', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 38.0, 'Alternativ hierzu können die gefangenen Fische online', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 42.0, 'gemeldet werden:', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 46.0, 'Meldeformular:', self::TEXT, '', 'L', 130.0, 4.0);
		$this->drawText($xpos + self::MARGIN + 32.0, $ypos + self::MARGIN + 46.0, 'www.bfv-ehingen.de/fangbuch', self::TEXT, '', 'L', 130.0, 4.0, '#0000AA');

		if (!empty($this->getForm('username', ''))) {
			$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 50.0, 'Benutzername:', self::TEXT, '', 'L', 130.0, 4.0);
			$this->drawText($xpos + self::MARGIN + 32.0, $ypos + self::MARGIN + 50.0, (string) $this->getForm('username', ''), self::TEXT, '', 'L', 130.0, 4.0);
			$this->drawText($xpos + self::MARGIN + 2.0, $ypos + self::MARGIN + 54.0, 'Passwort:', self::TEXT, '', 'L', 130.0, 4.0);
			$this->drawText($xpos + self::MARGIN + 32.0, $ypos + self::MARGIN + 54.0, (string) $this->getForm('password', ''), self::TEXT, '', 'L', 130.0, 4.0);
		}
	}

	public function gen_schonzeiten_block(float $xpos, float $ypos): void {
		$rand = self::MARGIN;
		$x = $xpos + $rand;
		$y = $ypos + $rand + 1.0;
		$this->drawText($x, $y, 'Mindestmaße und Schonzeiten der Fische', self::H1, 'B', 'L', 80.0, 5.0);
	}

	public function gen_adress_block(float $xpos, float $ypos): void {
		$rand = self::MARGIN;
		$this->drawText($xpos + $rand, $ypos + $rand + 1.0, 'Adressen', self::H1, 'B', 'L', 20.0, 5.0);
	}

	public function gen_fangstatistik_block(float $xpos): void {
		$this->drawText($xpos + 5.0, 6.0, 'Fangstatistik', self::H1, 'B', 'L', 30.0, 5.0);
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

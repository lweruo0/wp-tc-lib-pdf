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
require_once __DIR__ . '/trait-pdf-header.php';
require_once __DIR__ . '/trait-pdf-falzmarken.php';



/**
 * Example PDF Template with header and footer.
 */
class PdfFangstatistik extends PdfTemplate {
	use PdfHeaderTrait;

	private const PAGE_W = 297.0;
	private const PAGE_H = 210.0;
	/** print layout */
	private bool $print_layout = false;
	private const TEXT_H1 = 17;
    private const TEXT_TABLE = 10;

	public $layout = '';
    public $modifiedTime = '';
    public $headertext = '';
    public $TitleFontSizePt = 18;
    public $TabelleFontSizePt = 10;
    public string $textcolorheader = '#fff6ab';
    public string $fillcolorheader = '#444444';
    public string $fillcolorstripe = '#eeeeee';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(true); // Enable default header/footer and page content
		$this->initializeUrlData(); // Load $_GET parameters into $this->urldata
		//$this->setHeaderTitlePosition(20.0, 10.0);
		//$this->setHeaderSubtitlePosition(20.0, 22.0);
		//$this->setHeaderLogoPosition(0.0, 10.0);
		//$this->setHeaderLogoWidth(50.0);
		//$this->enableFooter(false); // Disable footer for this template
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
		$verein = $this->getUrl('verein', '');
        $year = (int) $this->getUrl('year', date('Y') - 1);
		$year = 2024;
        $this->print_layout = $this->getUrl('layout', '') === 'print';

		if (function_exists('bfvfangbuch')) {
			$instance = bfvfangbuch();
			$gewaesserDetail = $instance->get_gewaesser_details();
		    $gewaesserStatistik = $instance->get_gewaesser_statistik($year, $verein);
			$jahresStatistik = $instance->get_jahres_statistik();
			$this->setFooterModifiedTime((string) $instance->get_modifiedTime($year));
		} else {
			$gewaesserDetail = [];
			$gewaesserStatistik = [];
			$jahresStatistik = [];
		}
error_log(print_r($gewaesserDetail, TRUE));
		$this->setOptions($gewaesserDetail);
		$this->setOption('gewaesser_detail', $gewaesserDetail);
		$this->setFormdata($gewaesserStatistik);
		$this->setForm('jahres_statistik', $jahresStatistik);
		$this->setForm('gewaesser_statistik', $gewaesserStatistik);
		$this->setForm('year', $year);
		$this->setForm('verein', $verein);
		$this->setHeaderText('Fangstatistik', $year > 0 ? (string) $year : '');
		$this->setHeaderLogoImage($this->print_layout ? __DIR__ . '/images/logo_bfv2_print.png' : __DIR__ . '/images/logo_bfv2.png');
		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('statistik');
	}

	public function set_modifiedTime($x) {
		if (empty($x)) {
			$this->modifiedTime = '';
		} else {
			$this->modifiedTime = date('d.m.Y \u\m H:i', strtotime($x));
		}
		$this->setFooterModifiedTime((string) $x); // sync with trait footer
	}

	public function set_layout($x) {
		if (!empty($x)) {
			$this->layout = $x;
		}

		if ($this->layout !== 'print') {
			$this->textcolorheader = '#fff6ab';
			$this->fillcolorheader = '#444444';
		} else {
			$this->textcolorheader = '#000000';
			$this->fillcolorheader = '#eeeeee';
		}
	}

	public function set_headerText($x) {
		$this->headertext = $x;
		$this->setHeaderText($x); // sync with trait
	}

	public function No__null($myvalue) {
		return ($myvalue === null) ? 0 : $myvalue;
	}

	public function No__ZERO($myZero, $mySTR) {
		return ($myZero == $mySTR) ? '' : $mySTR;
	}

	public function SetFontSize_Title($x) {
		$this->TitleFontSizePt = $x;
	}

	public function SetFontSize_Tabelle($x) {
		$this->TabelleFontSizePt = $x;
	}

    public function Statistik_Jahresvergleiche(array $data, string $typ = 'Anzahl'): void {

		$headerfillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => $this->fillcolorheader,
			],
		];
		$greyfillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => $this->fillcolorstripe,
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
				'lineColor' => '#000000',
				'fillColor' => '#ffffff',
			],
		];

		$this->addPage(['orientation' => 'L', 'format' => 'A4']);
		$fontTable = $this->font->insert($this->pon, 'helvetica', '', $this->TabelleFontSizePt);
		$fontBar   = $this->font->insert($this->pon, 'helvetica', '', 8);

		// data preparation
		if ($typ === 'Anzahl') {
			$this->set_headerText('Fangstatistik Jahresvergleiche nach Anzahl');
			$faktor      = 1;
			$einheit     = '';
			$formatierung = '%s';
		} else {
			$this->set_headerText('Fangstatistik Jahresvergleiche nach Gewicht');
			$faktor      = 0.001;
			$einheit     = 'kg';
			$formatierung = '%.1f';
		}

		$datas = [];
		foreach ($data as $k => $v) {
			$datas[$k] = $v[$typ] * $faktor;
		}
		ksort($datas);
		$data = [];
		foreach ($datas as $k => $v) {
			$data[str_replace('Ae', 'Ä', $k)] = $v;
		}

		if (empty($data) || max($data) <= 0) {
			return;
		}

		$NbVal  = count($data);
		$nbDiv  = 10;
		$hBar   = 8.0;
		$eBaton = 6.0;

		$legends_every = [];
		$legends_first = [];
		$wLegend_first = 0.0;
		foreach ($data as $l => $val) {
			$legends_every[] = sprintf($formatierung, $val);
			$label           = str_replace('Regenbogenforelle', 'Regenbogenf.', $l);
			$legends_first[] = $label;
			$wLegend_first   = max($this->getStringWidth($label), $wLegend_first);
		}

		$ml    = 10.0;
		$mt    = 35.0;
		$XDiag = $ml + $wLegend_first + 2.0;
		$YDiag = $mt + 5.0;
		$hDiag = $hBar * ($NbVal + 1);

		$maxVal       = ceil(max($data) * 0.011) * 100;
		$valIndRepere = ceil($maxVal / $nbDiv);
		$maxVal       = $valIndRepere * $nbDiv;
		$lRepere      = floor((self::PAGE_W - $ml - $XDiag) / $nbDiv);
		$lDiag        = (float)($lRepere * $nbDiv);
		$unit         = $lDiag / $maxVal;

		$ls = ['lineWidth' => 0.2, 'lineCap' => 'butt', 'lineJoin' => 'miter', 'dashArray' => [], 'dashPhase' => 0, 'lineColor' => '#000000'];
		$barStyle = ['all' => ['lineWidth' => 0.1, 'lineCap' => 'butt', 'lineJoin' => 'miter', 'dashArray' => [], 'dashPhase' => 0, 'lineColor' => '#ff0000', 'fillColor' => '#ff0000']];

		$out = $this->graph->getStartTransform();
		$out .= $fontTable['out'];

		// outer box
		$out .= $this->graph->getRect($XDiag, $YDiag, $lDiag, $hDiag, 'D', $ls);

		// scale: vertical division lines + labels below
		for ($i = 0; $i <= $nbDiv; $i++) {
			$xpos = $XDiag + $lRepere * $i;
			$out .= $this->graph->getLine($xpos, $YDiag, $xpos, $YDiag + $hDiag, $ls);
			$lbl = $i * $valIndRepere . $einheit;
			$lw  = $this->getStringWidth($lbl);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(txt: $lbl, posx: $xpos - $lw / 2 - 1.0, posy: $YDiag + $hDiag + 1.0, width: $lw + 2.0, height: $hBar, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
		}

		// bars, value labels, legend labels
		$i = 0;
		foreach ($data as $val) {
			$lval    = (int)($val * $unit);
			$yval    = $YDiag + ($i + 1) * $hBar - $eBaton / 2;
			$barTopY = $yval + 0.5 * ($hBar - $eBaton);

			// bar
			if ($lval > 0) {
				$out .= $this->graph->getRect($XDiag, $barTopY, $lval, $eBaton, 'DF', $barStyle);
			}

			// value label with white background
			$out .= $fontBar['out'];
			$labelTxt = $legends_every[$i] . $einheit;
			$lw = $this->getStringWidth($labelTxt) + 2.0;
			$out .= $this->graph->getRect($XDiag + $lval + 0.1, $barTopY, $lw, $eBaton, 'F', $whiteFillStyle);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(txt: $labelTxt, posx: $XDiag + $lval + 0.1, posy: $barTopY, width: $lw, height: $eBaton, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);

			// legend label right-aligned to the left of the chart
			$out .= $fontTable['out'];
			$out .= $this->getTextCell(txt: $legends_first[$i], posx: $ml, posy: $yval - 2.0, width: $wLegend_first, height: $hBar * 2, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Right);

			$i++;
		}

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}
	public function Statistik_gesamt(array $data, array $gewaesserDetail, string $typ = 'Anzahl'): void {
		$typidx = ($typ === 'Anzahl') ? 5 : 4;
		$w0     = 32.0; // Fischart column
		$w      = 14.0; // per-Gewässer column
		$hh     = 42.0; // rotated header height
		$zh     =  6.0; // data row height
		$ml     = 10.0; // left margin
		$mt     = 10.0; // top margin

		$headerfillStyle = ['all' => ['lineWidth' => 0.1, 'lineCap' => 'butt', 'lineJoin' => 'miter', 'miterLimit' => 0.5, 'dashArray' => [], 'dashPhase' => 0, 'lineColor' => '#000000', 'fillColor' => $this->fillcolorheader]];
		$greyfillStyle   = ['all' => ['lineWidth' => 0.1, 'lineCap' => 'butt', 'lineJoin' => 'miter', 'miterLimit' => 0.5, 'dashArray' => [], 'dashPhase' => 0, 'lineColor' => '#000000', 'fillColor' => $this->fillcolorstripe]];
		$whiteFillStyle  = ['all' => ['lineWidth' => 0.1, 'lineCap' => 'butt', 'lineJoin' => 'miter', 'miterLimit' => 0.5, 'dashArray' => [], 'dashPhase' => 0, 'lineColor' => '#000000', 'fillColor' => '#ffffff']];

		$this->addPage(['orientation' => 'L', 'format' => 'A4']);
		$fontTitle = $this->font->insert($this->pon, 'helvetica', '', $this->TitleFontSizePt);
		$fontTable = $this->font->insert($this->pon, 'helvetica', '', $this->TabelleFontSizePt);

		// reorder '20s' between 20 and 21
		if (array_key_exists('20s', $data)) {
			$data['20.5'] = $data['20s'];
			unset($data['20s']);
			ksort($data);
			$b = [];
			foreach ($data as $gnr => $gd) {
				$b[$gnr === '20.5' ? '20s' : $gnr] = $gd;
			}
			$data = $b;
		}

		// collect all fish species and resolve Gewässer names
		$allefische = [];
		foreach ($data as $gewaessernr => $gew_data) {
			ksort($gew_data);
			foreach ($gew_data as $art => $zeile) {
				$allefische[$art] = 0;
			}
		}
		ksort($allefische);

		$title = $typ === 'Anzahl' ? 'Gesamtstatistik  (nach Anzahl)' : 'Gesamtstatistik  (nach Gewicht in kg)';

		$nbGew  = count($data);
		$tableW = $w0 + ($nbGew + 1) * $w; // +1 for Summe column

		$out = $this->graph->getStartTransform();
		$out .= $fontTitle['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(txt: $title, posx: $ml, posy: $mt, width: $tableW, height: 8.0, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Left);

		$y = $mt + 9.0;

		// --- rotated column headers ---
		// "Fischart" label cell (full header height)
		$out .= $fontTable['out'];

		$cx = $ml + $w0;
		foreach ($data as $gewaessernr => $gew_data) {
			$gewaessername = $gewaesserDetail[$gewaessernr]['Name'] ?? ('Nr. ' . $gewaessernr);
			// header cell background
			$out .= $this->graph->getRect($cx, $y, $w, $hh, 'DF', $headerfillStyle);
			$out .= $this->graph->getStartTransform();
			$textposX = $cx;        // visual left edge of this column
			$textposY = $y + $hh;   // visual bottom edge of the rotated header band
			$out .= $this->graph->getTranslation($textposX, $textposY);
			$out .= $this->graph->getRotation(90.0, 0.0, 0.0);
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(txt: $gewaessername, posx: 1.0, posy: 0.0, width: $hh - 2.0, height: $w, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Left);
			$out .= $this->graph->getStopTransform();
			$cx += $w;
		}

		// second header row (Nr. xx below rotated names)
		$y += $hh;
		$cx = $ml + $w0;

		// "Fischart" header cell (not rotated)
		$out .= $this->graph->getRect($ml, $y, $w0, $zh, 'DF', $headerfillStyle);
		$out .= $this->color->getPdfColor($this->textcolorheader);
		$out .= $this->getTextCell(txt: 'Fischart', posx: $ml+1, posy: $y, width: $w0, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Left);

		foreach ($data as $gewaessernr => $gew_data) {
			$out .= $this->graph->getRect($cx, $y, $w, $zh, 'DF', $headerfillStyle);
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(txt: 'Nr. ' . $gewaessernr, posx: $cx, posy: $y, width: $w, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
			$cx += $w;
		}
		// "Summe" header cell (not rotated)
		$out .= $this->graph->getRect($cx, $y, $w, $zh, 'DF', $headerfillStyle);
		$out .= $this->color->getPdfColor($this->textcolorheader);
		$out .= $this->getTextCell(txt: 'Summe', posx: $cx, posy: $y, width: $w, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);



		$y += $zh;

		// --- data rows ---
		$fill    = false;
		$sum_gew = [];
		foreach ($allefische as $art => $muell) {
			$fillstyle = $fill ? $greyfillStyle : $whiteFillStyle;
			$out .= $this->graph->getRect($ml, $y, $tableW, $zh, 'DF', $fillstyle);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(txt: $art, posx: $ml + 1.0, posy: $y, width: $w0 - 1.0, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Left);
			$sum_art = 0;
			$cx      = $ml + $w0;
			foreach ($data as $gewaessernr => $gew_data) {
				$anz = isset($gew_data[$art]) ? $gew_data[$art][$typidx] : 0;
				$sum_art += $anz;
				$sum_gew[$gewaessernr] = ($sum_gew[$gewaessernr] ?? 0) + $anz;
				$cell = $typ === 'Anzahl'
					? $this->No__ZERO('0', number_format($anz, 0, ',', ''))
					: $this->No__ZERO('0,00', number_format($anz * 0.001, 2, ',', ''));
				$out .= $this->getTextCell(txt: $cell, posx: $cx, posy: $y, width: $w, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
				$cx += $w;
			}
			$sumCell = $typ === 'Anzahl' ? strval($sum_art) : number_format($sum_art * 0.001, 2, ',', '');
			$out .= $this->getTextCell(txt: $sumCell, posx: $cx, posy: $y, width: $w, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
			$y   += $zh;
			$fill = !$fill;
		}

		// --- summary row ---
		$out .= $this->graph->getRect($ml, $y, $tableW, $zh, 'DF', $headerfillStyle);
		$out .= $this->color->getPdfColor($this->textcolorheader);
		$out .= $this->getTextCell(txt: 'Summe', posx: $ml, posy: $y, width: $w0, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
		$gesamtSumme = 0;
		$cx = $ml + $w0;
		foreach ($data as $gewaessernr => $gew_data) {
			$s = $sum_gew[$gewaessernr] ?? 0;
			$cell = $typ === 'Anzahl' ? strval($s) : number_format($s * 0.001, 2, ',', '');
			$out .= $this->getTextCell(txt: $cell, posx: $cx, posy: $y, width: $w, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
			$gesamtSumme += $s;
			$cx += $w;
		}
		$gesCell = $typ === 'Anzahl' ? strval($gesamtSumme) : number_format($gesamtSumme * 0.001, 2, ',', '');
		$out .= $this->getTextCell(txt: $gesCell, posx: $cx, posy: $y, width: $w, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function Statistik_Gewaesser(array $data, array $gewaesserDetail): void {

		$ml = 10.0; // left margin
		$mt = 25.0; // top margin
		$mb	= 15.0; // bottom margin

		$w0 = 42.0; // width of first column (Fischart)
		$w  = 35.0; // width of other columns (measurements and Anzahl)
		$hh = 6.0;  // height of header rows
		$zh = 6.0;  // height of data rows

		$headerfillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => $this->fillcolorheader,
			],
		];
		$greyfillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => $this->fillcolorstripe,
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
				'lineColor' => '#000000',
				'fillColor' => '#ffffff',
			],
		];

		$this->addPage([
			'orientation' => 'L', 
			'format' => 'A4'
		]);

		$fontTitle = $this->font->insert($this->pon, 'helvetica', '', $this->TitleFontSizePt);
		$fontTable = $this->font->insert($this->pon, 'helvetica', '', $this->TabelleFontSizePt);
		$header1   = ['Minimale', 'Maximale', 'Minimales', 'Maximales', 'Gesamt-'];
		$header2   = ['Länge',    'Länge',    'Gewicht',   'Gewicht',   'Gewicht'];

		$out = $this->graph->getStartTransform();
		$y   = $mt;

		foreach ($data as $gewaessernr => $gew_data) {
			ksort($gew_data);
			$name  = $gewaesserDetail[$gewaessernr]['Name'] ?? ('Gewässer ' . $gewaessernr);
			$block = $hh + 2.0 + 2 * $hh + count($gew_data) * $zh + $zh;

			if ($y + $block > (self::PAGE_H - $mb) && $y > $mt) {
				$out .= $this->graph->getStopTransform();
				$this->page->addContent($out);
				$this->addPage(['orientation' => 'L', 'format' => 'A4']);
				$out = $this->graph->getStartTransform();
				$y   = $mt;
			}

			// title
			$out .= $fontTitle['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(txt: $name . '  (Gewässer Nr. ' . $gewaessernr . ')', posx: $ml, posy: $y, width: self::PAGE_W - 2 * $ml, height: $hh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Left);
			$y += $hh + 2.0;

			// header: Fischart (2 rows tall)
			$out .= $fontTable['out'];
			$out .= $this->graph->getRect($ml, $y, $w0, 2 * $hh, 'DF', $headerfillStyle);
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(txt: 'Fischart', posx: $ml, posy: $y, width: $w0, height: 2 * $hh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);

			// header: measurement columns (2 × hh each)
			$cx = $ml + $w0;
			foreach ($header1 as $idx => $h1) {
				$out .= $this->graph->getRect($cx, $y, $w, 2 * $hh, 'DF', $headerfillStyle);
				$out .= $this->color->getPdfColor($this->textcolorheader);
				$out .= $this->getTextCell(txt: $h1,            posx: $cx, posy: $y,       width: $w, height: $hh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
				$out .= $this->getTextCell(txt: $header2[$idx], posx: $cx, posy: $y + $hh, width: $w, height: $hh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
				$cx += $w;
			}

			// header: Anzahl (2 rows tall)
			$out .= $this->graph->getRect($cx, $y, $w, 2 * $hh, 'DF', $headerfillStyle);
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(txt: 'Anzahl', posx: $cx, posy: $y, width: $w, height: 2 * $hh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);

			$y += 2 * $hh;
			$rowW = $w0 + 6 * $w;

			// data rows
			$fill    = false;
			$gew_sum = 0;
			$anz_sum = 0;

			foreach ($gew_data as $art => $row) {
				$fillstyle = $fill ? $greyfillStyle : $whiteFillStyle;
				$out .= $this->color->getPdfColor('#000000');
				$cx = $ml;
				foreach ([
					[1, $w0, $art,                                                                                                         \Com\Tecnick\Pdf\TextHAlign::Left],
					[0, $w,  $this->No__ZERO('0 cm',    number_format((int) $this->No__null($row[0]), 0, '', '') . ' cm'),                 \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0 cm',    number_format((int) $this->No__null($row[1]), 0, '', '') . ' cm'),                 \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0,00 kg', number_format($this->No__null($row[2]) * 0.001, 2, ',', '') . ' kg'),              \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0,00 kg', number_format($this->No__null($row[3]) * 0.001, 2, ',', '') . ' kg'),              \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0,00 kg', number_format($this->No__null($row[4]) * 0.001, 2, ',', '') . ' kg'),              \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  number_format((int) $this->No__null($row[5]), 0, ',', ''),                                                    \Com\Tecnick\Pdf\TextHAlign::Center],
				] as [$offset, $cw, $ctxt, $chalign]) {
					$out .= $this->graph->getRect($cx, $y, $cw, $zh, 'DF', $fillstyle);
					$out .= $this->color->getPdfColor('#000000');
					$out .= $this->getTextCell(txt: $ctxt, posx: $cx, posy: $y, width: $cw, height: $zh, offset: $offset, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: $chalign);
					$cx += $cw;
				}
				$anz_sum += $row[5];
				$gew_sum += $row[4];
				$y += $zh;
				$fill = !$fill;
				$fillstyle = $fill ? $greyfillStyle : $whiteFillStyle;
			}

			// summary row

			$out .= $this->graph->getRect($ml, $y, $rowW, $zh, 'DF', $fillstyle);
			$out .= $this->color->getPdfColor('#000000');
			$cx = $ml;
			foreach ([$w0, $w, $w, $w] as $cw) {
				$out .= $this->getTextCell(txt: '', posx: $cx, posy: $y, width: $cw, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
				$cx += $cw;
			}
			foreach ([
				[$w, 'Summe'],
				[$w, number_format($gew_sum * 0.001, 2, ',', '') . ' kg'],
				[$w, strval($anz_sum)],
			] as [$sw, $stxt]) {
				$out .= $this->graph->getRect($cx, $y, $sw, $zh, 'DF', $headerfillStyle);
				$out .= $this->color->getPdfColor($this->textcolorheader);
				$out .= $this->getTextCell(txt: $stxt, posx: $cx, posy: $y, width: $sw, height: $zh, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Center, halign: \Com\Tecnick\Pdf\TextHAlign::Center);
				$cx += $sw;
			}

			$y += $zh + 5.0;
		}

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {

		$this->set_layout($this->getUrl('layout', ''));

		$year               = $this->getForm('year', date('Y') - 1);
		$gewaesserStatistik = $this->getForm('gewaesser_statistik', []);
		$jahres_statistik = $this->getForm('jahres_statistik', []);
		$gewaesserDetail    = $this->getOption('gewaesser_detail', []);

		$this->set_headerText('Fangstatistik ' . $year);

		$this->Statistik_gesamt($gewaesserStatistik, $gewaesserDetail, 'Anzahl');
		$this->Statistik_gesamt($gewaesserStatistik, $gewaesserDetail, 'Gewicht');
		if (!empty($gewaesserStatistik) && !empty($gewaesserDetail)) {
			$this->Statistik_Gewaesser($gewaesserStatistik, $gewaesserDetail);
		}
	}

}

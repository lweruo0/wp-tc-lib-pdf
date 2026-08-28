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

	private const FONT_SIZE_TITLE = 18;
	private const FONT_SIZE_TABLE = 10;

	/** print layout */
	private bool $print_layout = false;

	public $layout = '';
    public $modifiedTime = '';
    public $headertext = '';
    public string $textcolorheader = '#fff6ab';
    public string $fillcolorheader = '#444444';
    public string $fillcolorstripe = '#eeeeee';

    public string $fillcolorbar1 = '#628fc3';
    public string $fillcolorbar2 = '#c7dbf2';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		if (function_exists('setlocale')) {
			setlocale(LC_COLLATE, 'de_DE.UTF-8', 'de_DE.utf8', 'German_Germany.utf8');
		}
		$this->enableDefaultPageContent(true); // Enable default header/footer and page content
		$this->initializeUrlData(); // Load $_GET parameters into $this->urldata
		$this->setHeaderTitlePosition(120.0, 5.0);
		$this->setHeaderSubtitlePosition(20.0, 22.0);
		$this->setHeaderLogoPosition(270.0, 6.0);
		$this->setHeaderLogoWidth(20.0);
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
        $year = (int) $this->getUrl('jahr', date('Y') - 1);
		$mnr = $this->getUrl('Mitgliedsnummer', null);
		$SortByGew = $this->getUrl('SortByGew', null);

        $this->print_layout = $this->getUrl('layout', '') === 'print';

		if (function_exists('bfvfangbuch')) {
			$instance = bfvfangbuch();
			$gewaesserStatistikYears = $instance->get_gewaesser_statistik_years_v2($verein);
			$gewaesserDetail = $instance->get_gewaesser_details();
		    $gewaesserStatistik = $instance->get_gewaesser_statistik_v2($year, $verein);
			$jahresStatistik = $instance->get_jahres_statistik_v2($year, $verein);
			$jahresStatistikOld = $instance->get_jahres_statistik_v2($year-1, $verein);
			$this->setFooterModifiedTime((string) $instance->get_modifiedTime($year));
			$user_statistik = $instance->get_user_statistik_v2($year, $mnr, $SortByGew);
		} else {
			$gewaesserDetail = [];
			$gewaesserStatistik = [];
			$jahresStatistik = [];
			$jahresStatistikOld = [];
			$user_statistik = [];
			$gewaesserStatistikYears = [];
		}

		$this->setForm('gewaesser_detail', $gewaesserDetail);
		$this->setForm('jahres_statistik', $jahresStatistik);
		$this->setForm('jahres_statistik_old', $jahresStatistikOld);
		$this->setForm('user_statistik', $user_statistik);
		$this->setForm('gewaesser_statistik', $gewaesserStatistik);
		$this->setForm('gewaesser_statistik_years', $gewaesserStatistikYears);
		$this->setForm('year', $year);
		$this->setForm('verein', $verein);


		$this->setHeaderText('Fangstatistik', $year > 0 ? (string) $year : '');
		$this->setHeaderLogoImage($this->print_layout ? __DIR__ . '/images/logo_bfv2_print.png' : __DIR__ . '/images/logo_bfv2.png');
		$this->setFileName("fangstatistik_{$year}.pdf");
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
			$this->fillcolorbar1 = '#cab70e';
			$this->fillcolorbar2 = '#fff6ab';	
			$this->setHeaderLogoImage(__DIR__ . '/images/logo_bfv2.png');

		} else {
			$this->textcolorheader = '#000000';
			$this->fillcolorheader = '#eeeeee';
			$this->fillcolorbar1 = '#909090';
			$this->fillcolorbar2 = '#eeeeee';
			$this->setHeaderLogoImage(__DIR__ . '/images/logo_bfv2.png_print');
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

	/**
	 * generates one A4 page with the Gesamtstatistik table, either by Anzahl or by Gewicht
	 *
	 *
	 * @return void
	 */
	public function Statistik_gesamt(array $data, array $gewaesserDetail, string $typ = 'Anzahl'): void {
		$typidx = ($typ === 'Anzahl') ? 5 : 4;
		$w0     = 32.0; // Fischart column
		$w      = 14.0; // per-Gewässer column
		$hh     = 42.0; // rotated header height
		$zh     =  6.0; // data row height
		$ml     = 10.0; // left margin
		$mt     = 10.0; // top margin

		$this->addPage(['orientation' => 'L', 'format' => 'A4']);

		// reorder '20s' between 20 and 21
		if (array_key_exists('20s', $data)) {
			$data['20.5'] = $data['20s'];
			unset($data['20s']);
			uksort($data, 'strcoll');

			$b = [];
			foreach ($data as $gnr => $gd) {
				$b[$gnr === '20.5' ? '20s' : $gnr] = $gd;
			}
			$data = $b;
		}

		// collect all fish species and resolve Gewässer names
		$allefische = [];
		foreach ($data as $gewaessernr => $gew_data) {
			uksort($gew_data, 'strcoll');
			foreach ($gew_data as $art => $zeile) {
				$allefische[$art] = 0;
			}
		}
		uksort($allefische, 'strcoll');

		$nbGew  = count($data);
		$tableW = $w0 + ($nbGew + 1) * $w; // +1 for Summe column

		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TITLE);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(	txt: 'Gesamtstatistik', 
									posx: $ml, 
									posy: $mt, 
									width: $tableW, 
									height: 8.0, 
									offset: 0, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left);
		$width = $this->font->getOrdArrWidth(
			$this->uniconv->strToOrdArr('Gesamtstatistik')
		) * 25.4 / 72;

		$title = $typ === 'Anzahl' ? '(nach Anzahl)' : '(nach Gewicht in kg)';
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
		$out .= $font['out'];
		$out .= $this->getTextCell(	txt: $title, 
									posx: $ml +$width +2, 
									posy: $mt+0.5, 
									width: $tableW, 
									height: 8.0, 
									offset: 0, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left);

		$y = $mt + 9.0;

		// --- rotated column headers ---
		// "Fischart" label cell (full header height)
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
		$out .= $font['out'];

		$cx = $ml + $w0;
		foreach ($data as $gewaessernr => $gew_data) {
			$gewaessername = $gewaesserDetail[$gewaessernr]['Name'] ?? ('Nr. ' . $gewaessernr);
			// header cell background
			$out .= $this->graph->getStartTransform();
			$textposX = $cx;        // visual left edge of this column
			$textposY = $y + $hh;   // visual bottom edge of the rotated header band
			$out .= $this->graph->getTranslation($textposX, $textposY);
			$out .= $this->graph->getRotation(90.0, 0.0, 0.0);
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(	txt: $gewaessername, 
										posx: -1.0,
										posy: 0.0, 
										width: $hh+1.0,
										height: $w, 
										offset: 2, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left,
										styles: $this->buildFillStyle('TRB', $this->fillcolorheader),
										drawcell: true,
										);
			$out .= $this->graph->getStopTransform();
			$cx += $w;
		}

		// second header row (Nr. xx below rotated names)
		$y += $hh;
		$cx = $ml + $w0;

		// "Fischart" header cell (not rotated)
		$out .= $this->color->getPdfColor($this->textcolorheader);
		$out .= $this->getTextCell(	txt: 'Fischart', 
									posx: $ml, 
									posy: $y, 
									width: $w0, 
									height: $zh, 
									offset: 1, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left,
									styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
									drawcell: true,
									);

		foreach ($data as $gewaessernr => $gew_data) {
			//$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(	txt: 'Nr. ' . $gewaessernr, 
										posx: $cx, 
										posy: $y, 
										width: $w, 
										height: $zh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $this->buildFillStyle('LRB', $this->fillcolorheader),
										drawcell: true,
										);
			$cx += $w;
		}
		// "Summe" header cell (not rotated)
		//$out .= $this->color->getPdfColor($this->textcolorheader);
		$out .= $this->getTextCell(	txt: 'Summe', 
									posx: $cx, 
									posy: $y, 
									width: $w, 
									height: $zh, 
									offset: 0, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Center,
									styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
									drawcell: true,
									);

		$y += $zh;

		// --- data rows ---
		$fill    = false;
		$sum_gew = [];
		$out .= $this->color->getPdfColor('#000000');
		foreach ($allefische as $art => $muell) {
			$fillstyle = $this->buildFillStyle('LR', $fill ? $this->fillcolorstripe : '#ffffff');
			$out .= $this->getTextCell(	txt: $art, 
										posx: $ml, 
										posy: $y, 
										width: $w0, 
										height: $zh, 
										offset: 1, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left,
										styles: $fillstyle,
										drawcell: true,
			);
			$sum_art = 0;
			$cx      = $ml + $w0;
			foreach ($data as $gewaessernr => $gew_data) {
				$anz = isset($gew_data[$art]) ? $gew_data[$art][$typidx] : 0;
				$sum_art += $anz;
				$sum_gew[$gewaessernr] = ($sum_gew[$gewaessernr] ?? 0) + $anz;
				$cell = $typ === 'Anzahl'
					? $this->No__ZERO('0', number_format($anz, 0, ',', ''))
					: $this->No__ZERO('0,00', number_format($anz * 0.001, 2, ',', ''));
				$out .= $this->getTextCell(	txt: $cell==''?' ':$cell, 
											posx: $cx, 
											posy: $y, 
											width: $w, 
											height: $zh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $fillstyle,
											drawcell: true);
				$cx += $w;
			}
			$sumCell = $typ === 'Anzahl' ? strval($sum_art) : number_format($sum_art * 0.001, 2, ',', '');
			$out .= $this->getTextCell(	txt: $sumCell, 
										posx: $cx, 
										posy: $y, 
										width: $w, 
										height: $zh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $fillstyle,
										drawcell: true);
			$y   += $zh;
			$fill = !$fill;
		}

		// --- summary row ---
		$out .= $this->color->getPdfColor($this->textcolorheader);
		$out .= $this->getTextCell(	txt: 'Summe', 
									posx: $ml, 
									posy: $y, 
									width: $w0, 
									height: $zh, 
									offset: 1, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left,
									styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
									drawcell: true,
									);
		$gesamtSumme = 0;
		$cx = $ml + $w0;
		foreach ($data as $gewaessernr => $gew_data) {
			$s = $sum_gew[$gewaessernr] ?? 0;
			$cell = $typ === 'Anzahl' ? strval($s) : number_format($s * 0.001, 2, ',', '');
			$out .= $this->getTextCell(	txt: $cell, 
										posx: $cx, 
										posy: $y, 
										width: $w, 
										height: $zh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
										drawcell: true,
									);
			$gesamtSumme += $s;
			$cx += $w;
		}
		$gesCell = $typ === 'Anzahl' ? strval($gesamtSumme) : number_format($gesamtSumme * 0.001, 2, ',', '');
		$out .= $this->getTextCell(	txt: $gesCell, 
									posx: $cx, 
									posy: $y, 
									width: $w, 
									height: $zh, 
									offset: 0, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Center,
									styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
									drawcell: true,
									);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	/**
	 * generates multiple A4 pages with the Statistics for each Gewässer
	 *
	 *
	 * @return void
	 */
	public function Statistik_Gewaesser(array $data, array $gewaesserDetail): void {

		$ml = 10.0; // left margin
		$mt = 15.0; // top margin
		$mb	= 15.0; // bottom margin

		$w0 = 42.0; // width of first column (Fischart)
		$w  = 35.0; // width of other columns (measurements and Anzahl)
		$hh = 6.0;  // height of header rows
		$zh = 6.0;  // height of data rows


		$this->addPage([
			'orientation' => 'L', 
			'format' => 'A4'
		]);

		$header1   = ['Minimale', 'Maximale', 'Minimales', 'Maximales', 'Gesamt-'];
		$header2   = ['Länge',    'Länge',    'Gewicht',   'Gewicht',   'Gewicht'];

		$out = $this->graph->getStartTransform();
		$y   = $mt;

		foreach ($data as $gewaessernr => $gew_data) {
			uksort($gew_data, 'strcoll');
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
			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TITLE);
			$out .= $font['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(	txt: $name, 
										posx: $ml, 
										posy: $y, 
										width: self::PAGE_W - 2 * $ml, 
										height: $hh, offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);

			$width = $this->font->getOrdArrWidth(
				$this->uniconv->strToOrdArr(strval($name)	)
			) * 25.4 / 72;

			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
			$out .= $font['out'];
			$subtitle = '(Gewässer Nr. ' . $gewaessernr . ')';
			$out .= $this->getTextCell(	txt: $subtitle, 
										posx: $ml + $width + 4, 
										posy: $y+1.0, 
										width: self::PAGE_W - 2 * $ml, 
										height: $hh, offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);


			$y += $hh + 2.0;

			// header: Fischart (2 rows tall)
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(	txt: 'Fischart', 
										posx: $ml, 
										posy: $y, 
										width: $w0, 
										height: 2 * $hh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
										drawcell: true,
									);

			// header: measurement columns (2 × hh each)
			$cx = $ml + $w0;
			foreach ($header1 as $idx => $h1) {
				//$out .= $this->color->getPdfColor($this->textcolorheader);
				$out .= $this->getTextCell(	txt: $h1,            
											posx: $cx, 
											posy: $y,       
											width: $w, 
											height: $hh+0.2, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $this->buildFillStyle('LTR', $this->fillcolorheader),
											drawcell: true,
										);
				$out .= $this->getTextCell(	txt: $header2[$idx], 
											posx: $cx, 
											posy: $y + $hh, 
											width: $w, 
											height: $hh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $this->buildFillStyle('LRB', $this->fillcolorheader),
											drawcell: true,
										);
				$cx += $w;
			}

			// header: Anzahl (2 rows tall)
			// $out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(	txt: 'Anzahl', 
										posx: $cx, 
										posy: $y, 
										width: $w, 
										height: 2 * $hh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
										drawcell: true,
										);

			$y += 2 * $hh;
			$rowW = $w0 + 6 * $w;

			// data rows
			$fill    = false;
			$gew_sum = 0;
			$anz_sum = 0;
			$out .= $this->color->getPdfColor('#000000');
			uksort($gew_data, 'strcoll');

			foreach ($gew_data as $art => $row) {
				$fillstyle = $this->buildFillStyle('LR', $fill ? $this->fillcolorstripe : '#ffffff');
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
					$out .= $this->color->getPdfColor('#000000');
					$out .= $this->getTextCell(	txt: $ctxt == '' ? ' ' : $ctxt, 
												posx: $cx, 
												posy: $y, 
												width: $cw, 
												height: $zh, 
												offset: $offset, 
												linespace: 0, 
												valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
												halign: $chalign,
												styles: $fillstyle,
												drawcell: true,
												);
					$cx += $cw;
				}
				$anz_sum += $row[5];
				$gew_sum += $row[4];
				$y += $zh;
				$fill = !$fill;
			}

			// summary row
			$out .= $this->color->getPdfColor('#000000');
			$cx = $ml;
			foreach ([$w0, $w, $w, $w] as $cw) {
				$out .= $this->getTextCell(	txt: ' ', 
											posx: $cx, 
											posy: $y, 
											width: $cw, 
											height: $zh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center, 
											styles: $this->buildFillStyle('T', '#ffffff'),
											drawcell: true,
											);
				$cx += $cw;
			}
			foreach ([
				[$w, 'Summe'],
				[$w, number_format($gew_sum * 0.001, 2, ',', '') . ' kg'],
				[$w, strval($anz_sum)],
			] as [$sw, $stxt]) {
				$out .= $this->color->getPdfColor($this->textcolorheader);
				$out .= $this->getTextCell(	txt: $stxt, 
											posx: $cx, 
											posy: $y, 
											width: $sw, 
											height: $zh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
											drawcell: true,
											);
				$cx += $sw;
			}

			$y += $zh + 5.0;
		}

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	/**
	 * generates multiple A4 pages with the Statistics for each User (Mitgliedsnummer)
	 *
	 *
	 * @return void
	 */
	public function Statistik_User(array $data, mixed $mit_Mitgliedsnummer = null): void {
		$ml = 10.0;
		$mt = 15.0;
		$mb = 20.0;
		$w0 = 40.0;
		$w  = 35.0;
		$hh =  6.0;
		$zh =  6.0;
		$rowW = $w0 + 6 * $w;
		$contentBottom = self::PAGE_H - $mb;

		$header1 = ['Minimale', 'Maximale', 'Minimales', 'Maximales', 'Gesamt-'];
		$header2 = ['Länge',    'Länge',    'Gewicht',   'Gewicht',   'Gewicht'];


		$this->addPage(['orientation' => 'L', 'format' => 'A4']);

		$out = $this->graph->getStartTransform();
		$y   = $mt;

		foreach ($data as $Mitgliedsnummer => $gew_data) {
			uksort($gew_data, 'strcoll');
			$block  = 3 * $hh + count($gew_data) * $zh + $zh;

			if ($y + $block > $contentBottom && $y > $mt) {
				$out .= $this->graph->getStopTransform();
				$this->page->addContent($out);
				$this->addPage(['orientation' => 'L', 'format' => 'A4']);
				$out = $this->graph->getStartTransform();
				$y   = $mt;
			}
			$title = sprintf('%s', $Mitgliedsnummer);
			// title
			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TITLE);
			$out .= $font['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(	txt: $title, 
										posx: $ml, 
										posy: $y, 
										width: self::PAGE_W - 2 * $ml, 
										height: $hh, offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);

			$width = $this->font->getOrdArrWidth(
				$this->uniconv->strToOrdArr(strval($title)	)
			) * 25.4 / 72;

			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
			$out .= $font['out'];
			$subtitle = ' ';
			$out .= $this->getTextCell(	txt: $subtitle, 
										posx: $ml + $width + 4, 
										posy: $y+1.0, 
										width: self::PAGE_W - 2 * $ml, 
										height: $hh, offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);
			$y += $hh + 2.0;

			// header: Fischart (2 rows tall)
			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
			$out .= $font['out'];
			$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(	txt: 'Fischart', 
										posx: $ml, 
										posy: $y, 
										width: $w0, 
										height: 2 * $hh, 
										offset: 1, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left,
										styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
										drawcell: true,
									);

			// header: measurement columns (2 × hh each)
			$cx = $ml + $w0;
			//$out .= $this->color->getPdfColor($this->textcolorheader);
			foreach ($header1 as $idx => $h1) {
				$out .= $this->getTextCell(	txt: $h1,            
											posx: $cx, 
											posy: $y,       
											width: $w, 
											height: $hh+0.2, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $this->buildFillStyle('LTR', $this->fillcolorheader),
											drawcell: true,
										);
				$out .= $this->getTextCell(	txt: $header2[$idx], 
											posx: $cx, 
											posy: $y + $hh, 
											width: $w, 
											height: $hh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $this->buildFillStyle('LRB', $this->fillcolorheader),
											drawcell: true,
										);
				$cx += $w;
			}

			// header: Anzahl (2 rows tall)
			//$out .= $this->color->getPdfColor($this->textcolorheader);
			$out .= $this->getTextCell(	txt: 'Anzahl', 
										posx: $cx, 
										posy: $y, 
										width: $w, 
										height: 2 * $hh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
										drawcell: true,
									);

			$y += 2 * $hh;

			// data rows
			$fill    = false;
			$gew_sum = 0;
			$anz_sum = 0;
			$out .= $this->color->getPdfColor('#000000');
			uksort($gew_data, 'strcoll');
			foreach ($gew_data as $art => $row) {
				$fillstyle = $this->buildFillStyle('LR', $fill ? $this->fillcolorstripe : '#ffffff');
				//$out .= $this->color->getPdfColor('#000000');
				$cx = $ml;
				foreach ([
					[1, $w0, $art,                                                                                                          \Com\Tecnick\Pdf\TextHAlign::Left],
					[0, $w,  $this->No__ZERO('0 cm',    number_format((int) $this->No__null($row[0]), 0, '', '') . ' cm'),                  \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0 cm',    number_format((int) $this->No__null($row[1]), 0, '', '') . ' cm'),                  \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0,00 kg', number_format($this->No__null($row[2]) * 0.001, 2, ',', '') . ' kg'),               \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0,00 kg', number_format($this->No__null($row[3]) * 0.001, 2, ',', '') . ' kg'),               \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  $this->No__ZERO('0,00 kg', number_format($this->No__null($row[4]) * 0.001, 2, ',', '') . ' kg'),               \Com\Tecnick\Pdf\TextHAlign::Center],
					[0, $w,  number_format((int) $this->No__null($row[5]), 0, ',', ''),                                                     \Com\Tecnick\Pdf\TextHAlign::Center],
				] as [$offset, $cw, $ctxt, $chalign]) {
					//$out .= $this->color->getPdfColor('#000000');
					$out .= $this->getTextCell(	txt: $ctxt == '' ? ' ' : $ctxt,  
												posx: $cx, 
												posy: $y, 
												width: $cw, 
												height: $zh, 
												offset: $offset, 
												linespace: 0, 
												valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
												halign: $chalign,
												styles: $fillstyle,
												drawcell: true,
											);
					$cx += $cw;
				}
				$anz_sum += $row[5];
				$gew_sum += $row[4];
				$y    += $zh;
				$fill  = !$fill;
			}

			// summary row
			$cx = $ml;
			foreach ([$w0, $w, $w, $w] as $cw) {
				$out .= $this->getTextCell(	txt: ' ', 
											posx: $cx, 
											posy: $y, 
											width: $cw, 
											height: $zh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center, 
											styles: $this->buildFillStyle('T', '#ffffff'),
											drawcell: true,
											);
				$cx += $cw;
			}
			$out .= $this->color->getPdfColor($this->textcolorheader);
			foreach ([
				[$w, 'Summe'],
				[$w, number_format($gew_sum * 0.001, 2, ',', '') . ' kg'],
				[$w, strval($anz_sum)],
			] as [$sw, $stxt]) {
				$out .= $this->getTextCell( txt: $stxt, 
											posx: $cx, 
											posy: $y, 
											width: $sw, 
											height: $zh, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center,
											styles: $this->buildFillStyle('LTRB', $this->fillcolorheader),
											drawcell: true,
											);
				$cx += $sw;
			}

			$y += $zh + 5.0;
		}

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}





    public function Statistik_Mehrjahresvergleich(array $data, string $typ = 'Anzahl'): void {



		if (empty($data) || max($data) <= 0) {
			return;
		}
		$ml = 10.0;
		$mt = 35.0;
		$mb = 20.0;
		$hh =  6.0;
		$hBar   = 8.0;
		$heightBar = 6.0;

		$whiteFillStyle = [
			'all' => [
				'lineWidth' => 0.1,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'miterLimit' => 0.5,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#000000',
				'fillColor' => '#ff00ff',
			],
		];

		$this->addPage(['orientation' => 'L', 'format' => 'A4']);

		
		$title = 'Jahresvergleich';
		// data preparation
		if ($typ === 'Anzahl') {
			$subtitle = '(nach Anzahl)';
			$faktor      = 1;
			$einheit     = '';
			$formatierung = '%s';
		} else {
			$subtitle = '(nach Gewicht in kg)';
			$faktor      = 0.001;
			$einheit     = 'kg';
			$formatierung = '%.1fkg';
		}
		$out = '';

		$y   = $mt;
		// title
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TITLE);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(	txt: $title, 
									posx: $ml, 
									posy: $y, 
									width: self::PAGE_W - 2 * $ml, 
									height: $hh, 
									offset: 0, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left);

		$width = $this->font->getOrdArrWidth(
			$this->uniconv->strToOrdArr(strval($title)	)
		) * 25.4 / 72;

		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
		$out .= $font['out'];
		$out .= $this->getTextCell(	txt: $subtitle, 
									posx: $ml + $width + 4, 
									posy: $y+1.0, 
									width: self::PAGE_W - 2 * $ml, 
									height: $hh, 
									offset: 0, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left);
		$y += $hh + 2.0;


		foreach ($data as $k => $v) {
			$data[$k] = $v[$typ] * $faktor;
		}
		uksort($data, 'strcoll');

		$NbVal  = count($data);

		$legends_right = [];
		$legends_left = [];
		$wLegend_left = 0.0;
		foreach ($data as $label => $val) {
			$legends_right[] = sprintf($formatierung, $val);
			$legends_left[] = $label;
			$wLegend_left   = max($this->getStringWidth($label), $wLegend_left);
		}


		$XDiag = $ml + $wLegend_left + 2.0;
		$hDiag = $hBar * ($NbVal + 1);

		$maxVal       = ceil(max($data) / 450) * 500;

		$nbDiv  = (int)($maxVal/500);

		$valIndRepere = ceil($maxVal / $nbDiv);
		$maxVal       = $valIndRepere * $nbDiv;
		$lRepere      = floor((self::PAGE_W - $ml - $XDiag) / $nbDiv);
		$lDiag        = (float)($lRepere * $nbDiv);
		$unit         = $lDiag / $maxVal;

		$LineStyle = [	'lineWidth' => 0.2, 
						'lineCap' => 'butt', 
						'lineJoin' => 'miter', 
						'dashArray' => [], 
						'dashPhase' => 0, 
						'lineColor' => '#000000'];


		$barStyle = ['all' => [	'lineWidth' => 0.1, 
								'lineCap' => 'butt', 
								'lineJoin' => 'miter', 
								'dashArray' => [], 
								'dashPhase' => 0, 
								'lineColor' => '#000000', 
								'fillColor' => $this->fillcolorbar1,
							]];



		$out .= $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
		$out .= $font['out'];

		// outer box
		$out .= $this->graph->getRect($XDiag, $y, $lDiag, $hDiag, 'D', $LineStyle);

		// scale: vertical division lines + labels below
		for ($i = 0; $i <= $nbDiv; $i++) {
			$xpos = $XDiag + $lRepere * $i;
			$out .= $this->graph->getLine($xpos, $y, $xpos, $y + $hDiag, $LineStyle);
			$lbl = $i * $valIndRepere . $einheit;
			$lw  = $this->getStringWidth($lbl);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(	txt: $lbl, 
										posx: $xpos - $lw / 2 - 1.0, 
										posy: $y + $hDiag + 1.0,
										width: $lw + 2.0, 
										height: $hBar, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center);
		}

		// bars, value labels, legend labels
		$i = 0;
		foreach ($data as $val) {
			$lval    = (int)($val * $unit);
			$yval    = $y + ($i + 1) * $hBar - $heightBar / 2;
			$barTopY = $yval + 0.5 * ($hBar - $heightBar);

			// bar
			if ($lval > 0) {
				$out .= $this->graph->getRect($XDiag, $barTopY, $lval, $heightBar, 'DF', $barStyle);
			}

			// value label with white background
			$font = $this->font->insert($this->pon, 'helvetica', '', 8);
			$out .= $font['out'];
			$lw = $this->getStringWidth($legends_right[$i]) + 2.0;

			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(	txt: $legends_right[$i], 
										posx: $XDiag + $lval + 0.5, 
										posy: $barTopY, 
										width: $lw, 
										height: $heightBar, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Center,
										styles: $this->buildFillStyle('', '#ffffff'),
										drawcell: true,
										);


			// legend label right-aligned to the left of the chart
			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
			$out .= $font['out'];
			$out .= $this->getTextCell(	txt: $legends_left[$i], 
										posx: $ml, 
										posy: $barTopY, 
										width: $wLegend_left, 
										height: $heightBar,
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Right);

			$i++;
		}

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}


   //
    // Diagramm Vorjahresvergleich erstellen
    //
    public function Statistik_Vorjahresvergleich(array $data, array $data_prev, int $jahr, string $typ = 'Anzahl'): void
    {

		$ml = 10.0;
		$mt = 15.0;
		$mb = 10.0;
		$hBar = 3.8;
		$heightBar = 3.0;
		$hh =  6.0;


        $normalizeSpeciesKey = static function (string $art): string {
            $art = trim($art);
            $art = strtr($art, [
                'Ä' => 'Ae', 'ä' => 'ae',
                'Ö' => 'Oe', 'ö' => 'oe',
                'Ü' => 'Ue', 'ü' => 'ue',
                'ß' => 'ss',
            ]);

            return $art;
        };

        $restoreSpeciesLabel = static function (string $art): string {
            return strtr($art, [
                'Ae' => 'Ä', 'ae' => 'ä',
                'Oe' => 'Ö', 'oe' => 'ö',
                'Ue' => 'Ü', 'ue' => 'ü',
                'ss' => 'ß',
            ]);
        };

        $readMetric = static function (array $row, string $typ): float {
            $metricKeys = $typ === 'Anzahl'
                ? ['Anzahl', 'anzahl', 'COUNT', 'count', 'Count', 5, '5']
                : ['Gewicht', 'gewicht', 'Gewicht kg', 'gewicht_kg', 'Weight', 'weight', 4, '4'];

            foreach ($metricKeys as $key) {
                if (array_key_exists($key, $row)) {
                    return (float) $row[$key];
                }
            }

            $index = $typ === 'Anzahl' ? 5 : 4;
            if (array_key_exists($index, $row)) {
                return (float) $row[$index];
            }

            return 0.0;
        };
		$title = 'Vorjahresvergleich ' . $jahr;
        $YEAR = $jahr;
            $this->set_headerText('');
            if ($typ === 'Anzahl') {
                $typidx = 5;
				$subtitle = '(nach Anzahl)';
                $faktor = 1.0;
                $digits = 0;
                $einheit = '';
                $formatierung = '%s';
            } else {
                $typidx = 4;
				$subtitle = '(nach Gewicht in kg)';
                $faktor = 0.001;
                $digits = 1;
                $einheit = 'kg';
                $formatierung = '%.1f';
            }

            $data_diagramm_gew = [];
            $data_diagramm_gew_old = [];

            foreach ($data_prev as $gew_data) {
				uksort($gew_data, 'strcoll');
                foreach ($gew_data as $art => $row) {
                    $speciesKey = $normalizeSpeciesKey((string) $art);
                    $value = $readMetric((array) $row, $typ);
                    $data_diagramm_gew_old[$speciesKey] = isset($data_diagramm_gew_old[$speciesKey]) ? $data_diagramm_gew_old[$speciesKey] + $value : $value;
                }
            }
            foreach ($data_diagramm_gew_old as $k => $v) {
                if ($v > 0) {
                    $data_diagramm_gew_old[$k] = round($v * $faktor, $digits);
                } else {
                    unset($data_diagramm_gew_old[$k]);
                }
            }

            foreach ($data as $gew_data) {
				uksort($gew_data, 'strcoll');
                foreach ($gew_data as $art => $row) {
                    $speciesKey = $normalizeSpeciesKey((string) $art);
                    $value = $readMetric((array) $row, $typ);
                    $data_diagramm_gew[$speciesKey] = isset($data_diagramm_gew[$speciesKey]) ? $data_diagramm_gew[$speciesKey] + $value : $value;
                }
            }
            foreach ($data_diagramm_gew as $k => $v) {
                if ($v > 0) {
                    $data_diagramm_gew[$k] = round($v * $faktor, $digits);
                } else {
                    unset($data_diagramm_gew[$k]);
                }
            }

            $datas = [];
            foreach ($data_diagramm_gew as $k => $v) {
                $datas[$k] = $v;
                $datas[$k . ' '] = 0;
            }
            foreach ($data_diagramm_gew_old as $k => $v) {
                $datas[$k] = isset($datas[$k]) ? $datas[$k] : 0;
                $datas[$k . ' '] = $v;
            }
			uksort($datas, 'strcoll');
            $chartData = [];
            foreach ($datas as $k => $v) {
                $chartData[$restoreSpeciesLabel($k)] = $v;
            }

            if (empty($chartData) || max($chartData) <= 0) {
                return;
            }

            $this->addPage(['orientation' => 'L', 'format' => 'A4']);

			$out = '';
			$y = $mt;
			// title
			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TITLE);
			$out .= $font['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(	txt: $title, 
										posx: $ml, 
										posy: $y, 
										width: self::PAGE_W - 2 * $ml, 
										height: $hh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);

			$width = $this->font->getOrdArrWidth(
				$this->uniconv->strToOrdArr(strval($title)	)
			) * 25.4 / 72;

			$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
			$out .= $font['out'];
			$out .= $this->getTextCell(	txt: $subtitle, 
										posx: $ml + $width + 4, 
										posy: $y+1.0, 
										width: self::PAGE_W - 2 * $ml, 
										height: $hh, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);
			$y += $hh + 2.0;


            $legends_right = [];
            $legends_left = [];
            $wLegend_first = 0.0;
            foreach ($chartData as $label => $val) {
                $legends_right[] = sprintf($formatierung, $val);
                $cleanLabel = str_replace('Regenbogenforelle', 'Regenbogenf.', $label);
                $legends_left[] = $cleanLabel;
                $wLegend_first = max($this->getStringWidth($cleanLabel), $wLegend_first);
            }


            $XDiag = $ml + $wLegend_first + 2.0;
            $lDiag = self::PAGE_W - $XDiag - 12.0;
            //$hDiag = $hBar * (count($chartData) + 1);
			
			$hDiag = self::PAGE_H - $mt - $mb - $wLegend_first - 2.0;
			$hBar = $hDiag / (count($chartData) + 1);


            $chartEndX = $XDiag + $lDiag;
            $maxVal = (float) max($chartData);
            //$maxVal = max(1.0, ceil($maxVal * 0.011) * 100);

			$maxVal = max(1.0, ceil(max($chartData) / 490) * 500);
			$nbDiv  = (int)($maxVal/500);

            $valIndRepere = (float) ceil($maxVal / $nbDiv);
            $maxVal = $valIndRepere * $nbDiv;
            $lRepere = floor($lDiag / $nbDiv);
            $lDiag = $lRepere * $nbDiv;
            $unit = $lDiag / $maxVal;

            $LineStyle = [	'lineWidth' => 0.2, 
							'lineCap' => 'butt', 
							'lineJoin' => 'miter', 
							'dashArray' => [], 
							'dashPhase' => 0, 
							'lineColor' => '#000000'];

            $oldBarStyle = ['all' => [	'lineWidth' => 0.1, 
										'lineCap' => 'butt', 
										'lineJoin' => 'miter', 
										'dashArray' => [], 
										'dashPhase' => 0, 
										'lineColor' => '#000000', 
										'fillColor' => $this->fillcolorbar2,
									]];
            $newBarStyle = ['all' => [	'lineWidth' => 0.1, 
										'lineCap' => 'butt', 
										'lineJoin' => 'miter', 
										'dashArray' => [], 
										'dashPhase' => 0, 
										'lineColor' => '#000000', 
										'fillColor' => $this->fillcolorbar1,
									]];

            $out .= $this->graph->getStartTransform();
            $font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
            $out .= $font['out'];

			$out .= $this->graph->getLine($XDiag, $y, $XDiag + $lDiag-22, $y, $LineStyle);
			$out .= $this->graph->getLine($XDiag, $y+$hDiag, $XDiag + $lDiag, $y+$hDiag, $LineStyle);

            for ($i = 0; $i <= $nbDiv; $i++) {
                $xpos = $XDiag + $lRepere * $i;
				if ($i === $nbDiv) {
                	$out .= $this->graph->getLine($xpos, $y+17, $xpos, $y + $hDiag, $LineStyle);
				} else {
                	$out .= $this->graph->getLine($xpos, $y, $xpos, $y + $hDiag, $LineStyle);
				}
                $lbl = sprintf('%s%s', $i * $valIndRepere, $einheit);
                $lw = $this->getStringWidth($lbl);
                $out .= $this->color->getPdfColor('#000000');
                $out .= $this->getTextCell(	txt: $lbl, 
											posx: $xpos - $lw / 2.0 - 1.0, 
											posy: $y + $hDiag + 1.0, 
											width: $lw + 2.0, 
											height: 4.0, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Center);
            }

            $chartValues = array_values($chartData);
            $chartLabels = array_keys($chartData);

            foreach ($chartValues as $i => $val) {
                $xval = $XDiag;
                $lval = (int) ($val * $unit);
                $yval = $y + ($i + 1) * $hBar - $heightBar / 2.0;

				if ($i % 2) {
					$yBase = $yval - 0.5 * ($hBar - $heightBar);
					$barStyle = $oldBarStyle;
				} else {
					$yBase = $yval + 0.5 * ($hBar - $heightBar);
					$barStyle = $newBarStyle;
				}
                $out .= $this->graph->getRect($xval, $yBase, $lval, $heightBar, 'DF', $barStyle);

                $labelTxt = sprintf($formatierung, $val) . $einheit;
                $labelW = $this->getStringWidth($labelTxt);
                $labelX = $xval + $lval;
				$font = $this->font->insert($this->pon, 'helvetica', '', 7);
				$out .= $font['out'];
                //$out .= $this->graph->getRect($labelX, $yBase, $labelW, $heightBar, 'F', $whiteFillStyle);
                $out .= $this->color->getPdfColor('#000000');
                $out .= $this->getTextCell(	txt: $labelTxt, 
											posx: $labelX+0.5, 
											posy: $yBase+0.4, 
											width: $labelW, 
											height: $heightBar - 1.0, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Left,
											styles: $this->buildFillStyle('', '#ffffff'),
											drawcell: true,
											);

				$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TABLE);
				$out .= $font['out'];

				if ($i % 2 === 0) {
			
                	$speciesLabel = str_replace('Regenbogenforelle', 'Regenbogenf.', $chartLabels[$i]);
					$out .= $this->getTextCell(	txt: $speciesLabel, 
											posx: $ml, 
											posy: $yBase - ($hBar-$heightBar+0.2), 
											width: $wLegend_first, 
											height: $hBar * 2.0, 
											offset: 0, 
											linespace: 0, 
											valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
											halign: \Com\Tecnick\Pdf\TextHAlign::Right,
											styles: $this->buildFillStyle('', '#ffffff'),
											drawcell: false,
											);
            
				}
			}

            $val2 = sprintf('%d', $YEAR);
            $val1 = sprintf('%d', $YEAR - 1);
            $maxw = max($this->getStringWidth($val2), $this->getStringWidth($val1));

            $legendBoxX = $chartEndX - $maxw * 2.5;
            $legendBoxY1 = $y + $hDiag - 2.0 - $hBar;
            $legendBoxY2 = $y + $hDiag - 2.0 - $hBar * 2.5;

            $out .= $this->graph->getRect($legendBoxX, $legendBoxY1, $hBar, $hBar, 'DF', $oldBarStyle);
            $out .= $this->graph->getRect($legendBoxX, $legendBoxY2, $hBar, $hBar, 'DF', $newBarStyle);
            $out .= $this->color->getPdfColor('#000000');
            $out .= $this->getTextCell(	txt: $val1, 
										posx: $legendBoxX + $hBar + 2.0, 
										posy: $legendBoxY1, 
										width: $maxw, 
										height: $hBar, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);
            $out .= $this->getTextCell(	txt: $val2, 
										posx: $legendBoxX + $hBar + 2.0, 
										posy: $legendBoxY2, 
										width: $maxw, 
										height: $hBar, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);
/*
            $sum = array_sum($data_diagramm_gew);
            $sum_old = array_sum($data_diagramm_gew_old);

            if ($typ === 'Anzahl') {
                $t1 = 'Anzahl ' . $YEAR . ': ' . $sum . $einheit;
                $t2 = 'Anzahl ' . ($YEAR - 1) . ': ' . $sum_old . $einheit;
            } else {
                $t1 = 'Gesamtgewicht ' . $YEAR . ':  ' . $sum . $einheit;
                $t2 = 'Gesamtgewicht ' . ($YEAR - 1) . ':  ' . $sum_old . $einheit;
            }

            $out .= $this->color->getPdfColor('#000000');
            $out .= $this->getTextCell(	txt: $t1, 
										posx: $XDiag, 
										posy: $y + $hDiag + 5.0, 
										width: 90.0, 
										height: 6.0, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);
            $out .= $this->getTextCell(	txt: $t2, 
										posx: $XDiag + $lRepere * $nbDiv * 0.5, 
										posy: $y + $hDiag + 10.0, 
										width: 90.0, 
										height: 6.0, 
										offset: 0, 
										linespace: 0, 
										valign: \Com\Tecnick\Pdf\TextVAlign::Center, 
										halign: \Com\Tecnick\Pdf\TextHAlign::Left);
*/
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
		$gewaesserDetail    = $this->getForm('gewaesser_detail', []);
		$jahres_statistik_old = $this->getForm('jahres_statistik_old', []);
		$user_statistik = $this->getForm('user_statistik', []);

		$this->set_headerText('Fangstatistik ' . $year);

		$this->Statistik_gesamt($gewaesserStatistik, $gewaesserDetail, 'Anzahl');
		$this->Statistik_gesamt($gewaesserStatistik, $gewaesserDetail, 'Gewicht');
		if (!empty($gewaesserStatistik) && !empty($gewaesserDetail)) {
			$this->Statistik_Gewaesser($gewaesserStatistik, $gewaesserDetail);
		}

		$this->Statistik_User($user_statistik, true);
		$this->Statistik_Mehrjahresvergleich($jahres_statistik, 'Anzahl');
		$this->Statistik_Mehrjahresvergleich($jahres_statistik, 'Gewicht');

		$gewaesser_statistik_years = $this->getForm('gewaesser_statistik_years', []);

        $years = array_keys($gewaesser_statistik_years);
        rsort($years, SORT_NUMERIC);

        foreach ($years as $jahr) {
			$data = $gewaesser_statistik_years[$jahr];
			$prevJahr = (int) $jahr - 1;
			$data_prev = $gewaesser_statistik_years[$prevJahr] ?? [];
			if (empty($data_prev)) {
				continue;
			}
			$this->Statistik_Vorjahresvergleich($data, $data_prev, (int) $jahr, 'Anzahl');
			$this->Statistik_Vorjahresvergleich($data, $data_prev, (int) $jahr, 'Gewicht');
		}

	}
}


/**
 * Example PDF Template with header and footer.
 */
class PdfFangstatistikJahr extends PdfFangstatistik {

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
        $year = (int) $this->getUrl('jahr', date('Y') - 1);

		if (function_exists('bfvfangbuch')) {
			$instance = bfvfangbuch();
			$gewaesserDetail = $instance->get_gewaesser_details();
		    $gewaesserStatistik = $instance->get_gewaesser_statistik_v2($year, $verein);
		} else {
			$gewaesserDetail = [];
			$gewaesserStatistik = [];
		}
		/* daten für render bereitstellen */
		$this->setForm('gewaesser_detail', $gewaesserDetail);
		$this->setForm('gewaesser_statistik', $gewaesserStatistik);
		$this->setForm('year', $year);
		$this->setForm('verein', $verein);
		$this->createStorageFolder('statistik');
		$this->setFileName("fangstatistik_{$year}.pdf");
		$this->setHeaderText('Fangstatistik '. (string) $year , '');
		$this->set_layout($this->getUrl('layout', ''));
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$gewaesserStatistik = $this->getForm('gewaesser_statistik', []);
		$gewaesserDetail    = $this->getForm('gewaesser_detail', []);
		$this->Statistik_gesamt($gewaesserStatistik, $gewaesserDetail, 'Anzahl');
		$this->Statistik_gesamt($gewaesserStatistik, $gewaesserDetail, 'Gewicht');
		if (!empty($gewaesserStatistik) && !empty($gewaesserDetail)) {
			$this->Statistik_Gewaesser($gewaesserStatistik, $gewaesserDetail);
		}
	}
}


/**
 * Example PDF Template with header and footer.
 */
class PdfFangstatistikMehrjahresvergleich extends PdfFangstatistik {

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {

		$this->set_layout($this->getUrl('layout', ''));

		$jahres_statistik = $this->getForm('jahres_statistik', []);
		$this->set_headerText('Fangstatistik');
		$this->Statistik_Mehrjahresvergleich($jahres_statistik, 'Anzahl');
		$this->Statistik_Mehrjahresvergleich($jahres_statistik, 'Gewicht');
	
		//$this->set_headerText('Fangstatistik');
		$gewaesser_statistik_years = $this->getForm('gewaesser_statistik_years', []);

        $years = array_keys($gewaesser_statistik_years);
        rsort($years, SORT_NUMERIC);

        foreach ($years as $jahr) {
			$data = $gewaesser_statistik_years[$jahr];
			$prevJahr = (int) $jahr - 1;
			$data_prev = $gewaesser_statistik_years[$prevJahr] ?? [];
			if (empty($data_prev)) {
				continue;
			}
			$this->Statistik_Vorjahresvergleich($data, $data_prev, (int) $jahr, 'Anzahl');
			$this->Statistik_Vorjahresvergleich($data, $data_prev, (int) $jahr, 'Gewicht');
		}
	}
}


/**
 * Example PDF Template with header and footer.
 */
class PdfFangstatistikVorJahresvergleich extends PdfFangstatistik {

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {

		$this->set_layout($this->getUrl('layout', ''));

		$gewaesser_statistik_years = $this->getForm('gewaesser_statistik_years', []);

		$this->set_headerText('Fangstatistik Jahresvergleich');
        foreach ($gewaesser_statistik_years as $jahr => $data) {
			$this->Statistik_Vorjahresvergleich($data, $jahr, 'Anzahl');
			$this->Statistik_Vorjahresvergleich($data, $jahr, 'Gewicht');
		}
	}
}


/**
 * Example PDF Template with header and footer.
 */
class PdfFangstatistikUser extends PdfFangstatistik {

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {

		$this->set_layout($this->getUrl('layout', ''));

		$year               = $this->getForm('year', date('Y') - 1);
		$user_statistik = $this->getForm('user_statistik', []);
		$jahres_statistik = $this->getForm('jahres_statistik', []);
		$gewaesserDetail    = $this->getForm('gewaesser_detail', []);

		$this->set_headerText('Fangstatistik ' . $year);
		$this->Statistik_User($user_statistik, true);

	}
}


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
abstract class PdfMitgliedsantragData extends PdfTemplate {
	use PdfHeaderTrait;
	protected const FONT_SIZE_TEXT = 11;
	protected const FONT_SIZE_TEXT_LETTER = 12;

	protected const COLOR_BOX = '#aaa';
	protected const COLOR_BOX_BACKGROUND = '#fefefe';
	protected const COLOR_BOX_BACKGROUND_DARK = '#eee';
	protected const COLOR_GROUPED = '#eee';
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(false); // Enable default header/footer and page content
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

		$options = get_option('bfv_mitgliedsantrag_einstellungen');
		$options['daten_erklaerung'] = get_option ( 'bfv_mitgliedsantrag_daten' );
		$options['foto_erklaerung'] = get_option ( 'bfv_mitgliedsantrag_fotos' );
		$this->setOptions($options);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('bfv_mitgliedsantrag');

		$formdata = $this->getAllFormdata();
		if (empty($formdata)) {
			if (function_exists('bfvmitgliedsantrag')) {
				$instance = bfvmitgliedsantrag();
				$mitgliedsnummern = preg_split("/[,-]/", $this->getUrl('mnr', ''), -1, PREG_SPLIT_NO_EMPTY);
				foreach ( $mitgliedsnummern as $mitgliedsnummer ) {
					$formddaten  = $instance->get_formdata_by_mitgliedsnummer ( $mitgliedsnummer );
					$formddaten['documenttype'] = 'Mitgliedsantrag';
					$formdata[] = $formddaten;
				}
			} else {
				$formdata = [];
			}
		}

		$formdata['documenttype'] = 'Mitgliedsantrag';

		$this->setFormdata($formdata);
	}


	public function seite_attachments(): void {
		$attachments = $this->getForm('attachments', []);
		if (!is_array($attachments)) {
			return;
		}
		foreach ($attachments as $attachment) {
			$path = is_array($attachment) ? (string) ($attachment['path'] ?? '') : '';
			if ($path === '' || !is_file($path) || !is_readable($path)) {
				continue;
			}

			if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
				$pdfData = file_get_contents($path);
				if ($pdfData === false || $pdfData === '') {
					continue;
				}

				$sourceId = $this->setImportSourceData($pdfData);
				$pageCount = $this->getSourcePageCount($sourceId);
				for ($pageNumber = 1; $pageNumber <= $pageCount; ++$pageNumber) {
					$this->addPageFromImport($sourceId, $pageNumber);
				}
				continue;
			}

			$imageData = file_get_contents($path);
			if ($imageData === false || $imageData === '') {
				continue;
			}
			$imageSource = '@' . $imageData;
			$imageId = $this->image->add($imageSource);
			$key = $this->image->getKey($imageSource);
			$dimensions = $this->image->getImageDimensionsByKey($key, 210, 297, true);
			$this->addPage(['orientation' => ($dimensions['width'] > $dimensions['height'] ? 'L' : 'P'), 'format' => 'A4']);
			$out = $this->graph->getStartTransform();
			$out .= $this->image->getSetImage(
				$imageId,
				0.0,
				0.0,
				$dimensions['width'],
				$dimensions['height'],
				$dimensions['width'] > $dimensions['height'] ? 210.0 : 297.0,
			);
			$out .= $this->graph->getStopTransform();
			$this->page->addContent($out);
		}
	}
}


/**
 * Example PDF Template with header and footer.
 */
class PdfMitgliedsantrag2 extends PdfMitgliedsantragData {
	use PdfHeaderTrait;
	use PdfFalzmarkenTrait;
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(true); // Enable default header/footer and page content^

		$this->setHeaderText('Bezirksfischerei-Verein e.V. Ehingen/Donau');

	}
	protected function addApplicationTitle(string $title, string $title2 = '', string $title3 = ''): string {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', 'B', 21);
		$out .= $font['out'] . $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: $title,
			posx: 25.0,
			posy: 35.0,
			width: 165.0,
			height: 8.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		if ($title2 !== '') {
			$out .= $this->getTextCell(
				txt: $title2,
				posx: 25.0,
				posy: 44.0,
				width: 165.0,
				height: 5.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
		}
		if ($title3 !== '') {
			$out .= $this->getTextCell(
				txt: $title3,
				posx: 25.0,
				posy: 45.0,
				width: 165.0,
				height: 5.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
		}
		$out .= $this->graph->getStopTransform();
		return $out;
	}

	protected function addApplicationAddress(): string {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$values = [
			(string) $this->getAddress('email_verein', ''),
			(string) $this->getAddress('name_verein', ''),
			(string) $this->getAddress('addr_verein', ''),
			(string) $this->getAddress('ort_verein', ''),
		];
		foreach ($values as $idx => $text) {
			$out .= $this->color->getPdfColor($idx == 0 ? '#999999' : '#000000');
			$out .= $this->getTextCell(
				txt: $text,
				posx: 25.0,
				posy: 60.0 + ($idx * 6.0),
				width: 80.0,
				height: 4.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);	
		}
		$out .= $this->graph->getStopTransform();
		return $out;
	}

	protected function addApplicationParagraphs(array $data): string {
		$text = '';
		foreach (['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9'] as $key) {
			if (isset($data[$key])) {
				$text .= (string) $data[$key] . "\n\n";
			}
		}
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);

		return $font['out'] . $this->color->getPdfColor('#000000') . $this->getTextCell(
			txt: $text,
			posx: 25.0,
			posy: 100.0,
			width: 160.0,
			height: 145.0,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
	}

	protected function getBox(float $x, float $y, float $box_height, float $box_width, float $label_height,  string $label): string {
		$out = $this->graph->getRect($x, $y, $box_width, $box_height+$label_height, 'DF', $this->buildFillStyle('TRBL', self::COLOR_BOX_BACKGROUND, self::COLOR_BOX, 0.2));
		$out .= $this->color->getPdfColor('#ffffff');
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT_LETTER*0.6);
		$out .= $font['out'];
		$out .= $this->getTextCell(
			txt: $label,
			posx: $x,
			posy: $y + $box_height,
			width: $box_width,
			height: $label_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', self::COLOR_BOX, self::COLOR_BOX, 0.2),
			drawcell: true,
		);
		$out .= $this->color->getPdfColor('#000000');
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT_LETTER);
		$out .= $font['out'];
		return $out;
	}

	protected function getFilledBox(float $x, float $y, float $box_height, float $box_width, float $label_height, string $label, string $text): string {
		$out = $this->getBox($x, $y, $box_height, $box_width, $label_height, $label);
		$out .= $this->getTextCell(
			txt: $text,
			posx: $x+1.0,
			posy: $y,
			width: $box_width-5.5,
			height: $box_height,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		return $out;
	}

	protected function getFragenAntwort(float $x_question, float $x_answer, float $y, float $label_height, string $question, string $answer): string {
		$out = $this->getTextCell(
			txt: $question,
			posx: $x_question+1.0,
			posy: $y,
			width: 120,
			height: $label_height,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		$out .= $this->getTextCell(
			txt: $answer,
			posx: $x_answer+1.0,
			posy: $y,
			width: 20,
			height: $label_height,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			drawcell: false,
		);
		return $out;
	}


	protected function addApplicationSignature(int|float $ypos, string $label, string $signatureKey): string {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT_LETTER);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$box_height = $row_height * 4;
		$box_width = 160.0;

		$x=25.0;
		$y=$ypos;

		$out .= $this->getBox($x, $y, $box_height, $box_width, $row_height, $label);

		$out .= $this->getTextCell(
			txt: date('d.m.Y') . ', ',
			posx: $x,
			posy: $y + (0.5*$box_height),
			width: 35.0,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$signature = (string) $this->getForm($signatureKey, '');
		if ($signature !== '') {
			$encodedImage = preg_replace('#^data:image/[^;]+;base64,#', '', $signature);
			$imageData = $encodedImage === null ? false : base64_decode($encodedImage, true);
			if ($imageData !== false && $imageData !== '') {
				$imageSource = '@' . $imageData;
				$imageId = $this->image->add($imageSource);
				$imageKey = $this->image->getKey($imageSource);
				$imageDataDimensions = $this->image->getImageDataByKey($imageKey);
				$imageHeight = $box_height ;
				$imageWidth = $imageDataDimensions['height'] > 0
					? $imageHeight * $imageDataDimensions['width'] / $imageDataDimensions['height']
					: 0.0;
				$out .= $this->image->getSetImage(
					$imageId,
					45.0,
					$y,
					$imageWidth,
					$imageHeight,
					297.0,
				);
			}
		}

		$out .= $this->graph->getStopTransform();
		return $out;
	}

	public function seite_daten_antragsteller(): void {
		$this->addPage(['orientation' => 'P', 'format' => 'A4']);
		$this->add_falzmarken();
		$out = $this->addApplicationTitle('Antrag Mitgliedschaft');
		$out .= $this->addApplicationAddress();
		$out .= $this->color->getPdfColor('#000000');
		//$out .= $this->getTextCell(
		//	txt: 'Antragsteller', posx: 25.0, posy: 90.0, width: 165.0, height: 5.0, offset: 0, linespace: 0,
		//	valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		//	styles: $this->buildFillStyle('', '#f8f8f8'), drawcell: true,
		//);

		$x = 25.0;
		$y = 90.0;
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT_LETTER);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$box_height = $row_height*1.6;
		$distance = 1.5;
		$box_width = 80.0 - $distance*1.5;
		$label_height = $row_height * 0.6;

		$geburtstag = (string) $this->getForm('geburtstag', '');
		$geburtstagAnzeige = $geburtstag === '' ? '' : date('d.m.Y', strtotime($geburtstag));


		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Angaben zum Antragsteller',
			posx: $x, 
			posy: $y, 
			width: 160.0, 
			height: $row_height*27.1, 
			offset: 1, 
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		//	styles: $this->buildFillStyle('T', self::COLOR_BOX_BACKGROUND_DARK, self::COLOR_BOX, 0.4),
			styles: $this->buildFillStyle('',self::COLOR_GROUPED, self::COLOR_BOX, 0.4),
			drawcell: true,
		);

		$xd = $x+$distance;


		$y += $row_height+$distance; 



		$out .= $this->getFilledBox($xd, $y, $row_height, $box_width, $label_height, "Vorname Antragsteller", (string) $this->getForm('vorname', ''));
		$out .= $this->getFilledBox($xd, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "Nachname Antragsteller", (string) $this->getForm('name', ''));
		$out .= $this->getFilledBox($xd, $y+2*($box_height+$distance), $row_height, $box_width, $label_height, "Straße Antragsteller", (string) $this->getForm('strasse', ''));
		$out .= $this->getFilledBox($xd, $y+3*($box_height+$distance), $row_height, $box_width, $label_height, "Ort Antragsteller", (string) $this->getForm('plz', ''). ' ' . $this->getForm('ort', ''));
		$out .= $this->getFilledBox($xd, $y+4*($box_height+$distance), $row_height, $box_width, $label_height, "Geburtstag Antragsteller", $geburtstagAnzeige);

		$out .= $this->getFilledBox($xd+$box_width+$distance, $y, $row_height, $box_width, $label_height, "E-Mail Antragsteller", (string) $this->getForm('email', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "Handy Antragsteller", (string) $this->getForm('tel_mobil', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y+2*($box_height+$distance), $row_height, $box_width, $label_height, "Telefon Antragsteller", (string) $this->getForm('tel_priv', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y+3*($box_height+$distance), $row_height, $box_width, $label_height, "Beruf Antragsteller", (string) $this->getForm('beruf', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y+4*($box_height+$distance), $row_height, $box_width, $label_height, "Nationalität Antragsteller", (string) $this->getForm('nationalitaet', ''));
		
		$y += 5*($box_height+$distance); // 5 Zeilen runter


		// Vorstellung
		$out .= $this->getFilledBox($xd, $y, $row_height*8.4, 160 - 2* $distance, $label_height, "Vorstellung", (string) $this->getForm('vorstellung', ''));
		$y += $row_height*9+$distance;


		// Fragen
		$fragen = [
			"Ich bin wegen Jagd- oder Fischereivergehen vorbestraft:" => (string) $this->getForm('vorbestraft', ''),
			'Ich habe die Fischerprüfung bestanden am:' => empty($this->getForm('datum_fischerpruefung', '')) ? '' : date('d.m.Y', strtotime((string) $this->getForm('datum_fischerpruefung', ''))),
			"Mein Fischereischein ist gültig bis:" => empty($this->getForm('fischereischein', '')) ? '' : date('Y', strtotime((string) $this->getForm('fischereischein', ''))),
			"Ich wurde aus einem Angelverein ausgeschlossen:" => (string) $this->getForm('ausgeschlossen', ''),
			'Ich war bereits Mitglied im BFV Ehingen/Do.:' => (string) $this->getForm('ehemalig', ''),
			'Ich bin Mitglied des Angelvereins:' => (string) $this->getForm('mitglied_verein', ''),
			'Ich war Mitglied des Angelvereins:' => (string) $this->getForm('ehemaliges_mitglied_verein', ''),
		];

		$idx = 0;
		foreach ($fragen as $label => $value) {
			//$out .= $this->getFragenAntwort($x, $x + 140, $y, $label_height, $label, $value);
			$strype_style = $idx%2 ? $this->buildFillStyle('TRBL', self::COLOR_BOX_BACKGROUND_DARK, self::COLOR_BOX, 0.2) : $this->buildFillStyle('TRBL', self::COLOR_BOX_BACKGROUND, self::COLOR_BOX, 0.2);
			$idx++;
			$out .= $this->getTextCell(
				txt: $label,
				posx: $xd,
				posy: $y,
				width: 160 - 2* $distance,
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				styles: $strype_style,
				drawcell: true,
			);
			$out .= $this->getTextCell(
				txt: $value,
				posx: $xd + 67 - $distance,
				posy: $y,
				width: 92 - 2* $distance,
				height: $row_height,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: $idx > 5 ? \Com\Tecnick\Pdf\TextHAlign::Left : \Com\Tecnick\Pdf\TextHAlign::Right,
				drawcell: true,
			);

			$y += $row_height;
		}
		$y += $distance*3;

		if ((int) $this->getForm('alter', 0) < 18) {
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: 'Angaben zum gesetzlichen Vertreter',
				posx: $x, 
				posy: $y, 
				width: 160.0, 
				height: $row_height*5, 
				offset: 1, 
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				styles: $this->buildFillStyle('',self::COLOR_GROUPED, self::COLOR_BOX, 0.4),
				drawcell: true,
			);
			$y += $row_height+$distance; 
			// Angaben zum gesetzlichen Vertreter
			$out .= $this->getFilledBox($xd, $y, $row_height, $box_width, $label_height, "Vorname gesetzl. Vertreter", (string) $this->getForm('erziehungberechtiger_vorname', ''));
			$out .= $this->getFilledBox($xd, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "Nachname gesetzl. Vertreter", (string) $this->getForm('erziehungberechtiger_name', ''));
			$out .= $this->getFilledBox($xd+$box_width+$distance, $y, $row_height, $box_width, $label_height, "E-Mail gesetzl. Vertreter", (string) $this->getForm('erziehungberechtiger_email', ''));
			$out .= $this->getFilledBox($xd+$box_width+$distance, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "Handy gesetzl. Vertreter", (string) $this->getForm('erziehungberechtiger_tel', ''));
			$y += 2*($box_height+$distance);
		}


		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function seite_einwilligung_daten(): void {
		if (!in_array($this->getForm('einwilligung_daten', ''), ['Ja', 'ja', '1', 1], true)) {
			return;
		}
		$this->addPage(['orientation' => 'P', 'format' => 'A4']);
		$this->add_falzmarken();
		$data = $this->getOption('daten_erklaerung', []);
		$out = $this->addApplicationTitle((string) ($data['title'] ?? ''), (string) ($data['title2'] ?? ''), (string) ($data['title3'] ?? ''));
		$out .= $this->addApplicationAddress();
		$out .= $this->addApplicationParagraphs(is_array($data) ? $data : []);
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function seite_einwilligung_fotos(): void {
		if (!in_array($this->getForm('einwilligung_foto', ''), ['Ja', 'ja', '1', 1], true)) {
			return;
		}
		$this->addPage(['orientation' => 'P', 'format' => 'A4']);
		$this->add_falzmarken();
		$data = $this->getOption('foto_erklaerung', []);
		$out = $this->addApplicationTitle((string) ($data['title'] ?? ''), (string) ($data['title2'] ?? ''), (string) ($data['title3'] ?? ''));
		$out .= $this->addApplicationAddress();
		$out .= $this->addApplicationParagraphs(is_array($data) ? $data : []);
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function seite_sepa(): void {
		$this->addPage(['orientation' => 'P', 'format' => 'A4']);
		$this->add_falzmarken();
		$out = $this->addApplicationTitle('SEPA-Lastschriftmandat');
		$out .= $this->addApplicationAddress();


		$x = 25.0;
		$y = 90.0;
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT_LETTER);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$box_height = $row_height*1.6;
		$distance = 1.5;
		$box_width = 80.0 - $distance*1.5;
		$xd = $x+$distance;
		$label_height = $row_height * 0.6;

		// Kontoinhaber
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Kontoinhaber',
			posx: $x, 
			posy: $y, 
			width: 160.0, 
			height: $row_height*5, 
			offset: 1, 
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('',self::COLOR_GROUPED, self::COLOR_BOX, 0.4),
			drawcell: true,
		);
		$y += $row_height+$distance; 
		$out .= $this->getFilledBox($xd, $y, $row_height, $box_width, $label_height, "Vorname Kontoinhaber", (string) $this->getForm('kontoinhaber_vorname', ''));
		$out .= $this->getFilledBox($xd, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "Nachname Kontoinhaber", (string) $this->getForm('kontoinhaber_name', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y, $row_height, $box_width, $label_height, "Straße Kontoinhaber", (string) $this->getForm('kontoinhaber_strasse', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "PLZ und Ort Kontoinhaber", $this->getForm('kontoinhaber_plz', '') . ' ' . $this->getForm('kontoinhaber_ort', ''));
		$y += 2*($box_height+$distance);
		$y += $distance*2;

		// Bankverbindung
		$out .= $this->getTextCell(
			txt: 'Bankverbindung',
			posx: $x, 
			posy: $y, 
			width: 160.0, 
			height: $row_height*5, 
			offset: 1, 
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('',self::COLOR_GROUPED, self::COLOR_BOX, 0.4),
			drawcell: true,
		);
		$y += $row_height+$distance;
		$out .= $this->getFilledBox($xd, $y, $row_height, $box_width, $label_height, "IBAN", (string) $this->getForm('kontoinhaber_iban', ''));
		$out .= $this->getFilledBox($xd, $y+1*($box_height+$distance), $row_height, $box_width, $label_height, "BIC", (string) $this->getForm('kontoinhaber_bic', ''));
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y, $row_height, $box_width, $label_height, "Bankname", (string) $this->getForm('kontoinhaber_bankname', ''));
		$y += 2*($box_height+$distance);
		$y += $distance*2;

		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;


		$out .= $this->getTextCell(
			txt: 'Kontoinhaber: ' . $this->getForm('kontoinhaber_vorname', '') . ' ' . $this->getForm('kontoinhaber_name', ''),
			posx: $x, posy: $y, width: 165.0, height: 5.0, offset: 0, linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Gläubiger-Identifikationsnummer und Mandatsreferenz
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Gläubiger-Identifikationsnummer und Mandatsreferenz',
			posx: $x, 
			posy: $y, 
			width: 160.0, 
			height: $row_height*3+$distance, 
			offset: 1, 
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('',self::COLOR_GROUPED, self::COLOR_BOX, 0.4),
			drawcell: true,
		);
		$y += $row_height+$distance; 
		$out .= $this->getFilledBox($xd, $y, $row_height, $box_width, $label_height, "Gläubiger-Identifikationsnummer", 'DE18ZZZ00001367248');
		$out .= $this->getFilledBox($xd+$box_width+$distance, $y, $row_height, $box_width, $label_height, "Mandatsreferenz", 'wird separat mitgeteilt');
		$y += ($box_height+$distance);
		$y += $distance*2;



		$out .= $this->getTextCell(
			txt: "Ich (Kontoinhaber) ermächtige den Bezirksfischerei-Verein e.V. Ehingen / Donau,\n"
				. "Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein \n"
				. "Kreditinstitut an, die vom Bezirksfischerei-Verein e.V. Ehingen / Donau auf mein Konto\n"
				. "gezogenen Lastschriften einzulösen.\n\n"
				. "Hinweis:\n"
				. "Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die\n"
				. "Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem\n"
				. "Kreditinstitut vereinbarten Bedingungen. ",
			posx: 25.0, posy: $y, width: 165.0, height: 55.0, offset: 0, linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		$y += 55;
		$y += $distance*2;
		$out .= $this->addApplicationSignature($y, 'Datum, Unterschrift Kontoinhaber', 'kontoinhaber_unterschrift');
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	public function seite_antrag(): void {
		$this->addPage(['orientation' => 'P', 'format' => 'A4']);
		$this->add_falzmarken();
		$out = $this->addApplicationTitle('Antrag');
		$out .= $this->color->getPdfColor('#000000');

		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT_LETTER);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;

		$x = 25.0;
		$y = 50.0;
		$width = 160.0;

		$minderjaehrig = (int) $this->getForm('alter', 0) < 18;
		$aktive_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Aktiv';
		$jugendliche_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Jugend';
		$passive_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Passiv';
		$foerder_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Förder';
		$option_2angel = in_array($this->getForm('option_jugend', ''), ['2.Angel'], true);
		$option_boot = in_array($this->getForm('option_boot', ''), ['Boot', 'boot'], true);
		$einwilligung_daten = in_array($this->getForm('einwilligung_daten', ''), ['Ja', 'ja', '1', 1], true);
		$einwilligung_foto = in_array($this->getForm('einwilligung_foto', ''), ['Ja', 'ja', '1', 1], true);

		if ($minderjaehrig) {
			$text = "Hiermit beantrage ich (Erziehungsberechtigter) die Mitgliedschaft für mein Kind (Antragsteller) im Bezirksfischerei-Verein Ehingen / Donau. Ich werde mich für die Ziele und Aufgaben des Vereins, insbesondere bei der Erhaltung und Pflege der Gewässer aktiv einsetzen. Mir ist bekannt, dass unwahre Angaben zum Vereinsausschluss führen können.";
			$out .= $this->getTextCell(txt: $text, posx: $x, posy: $y, width: $width, height: 0, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height * 5.5;
		} else {
			$text = "Hiermit beantrage ich (Antragsteller) die Mitgliedschaft im Bezirksfischerei-Verein Ehingen / Donau. Ich werde mich für die Ziele und Aufgaben des Vereins, insbesondere bei der Erhaltung und Pflege der Gewässer aktiv einsetzen. Mir ist bekannt, dass unwahre Angaben zum Vereinsausschluss führen können.";
			$out .= $this->getTextCell(txt: $text, posx: $x, posy: $y, width: $width, height: 0, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height * 4.5;
		}

		if ($einwilligung_daten) {
			$out .= $this->getTextCell(
				txt: 'Mit der vorseitigen "Einverständniserklärung in die Erhebung und Verarbeitung von Daten" bin ich einverstanden.',
				posx: $x, posy: $y, width: $width, height: 0, offset: 0, linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false,
			);
			$y += $row_height * 2.5;
		}

		if ($einwilligung_foto) {
			$out .= $this->getTextCell(
				txt: 'Mit der vorseitigen "Einwilligungserklärung zur Veröffentlichung von Fotos und Namen" bin ich einverstanden.',
				posx: $x, posy: $y, width: $width, height: 0, offset: 0, linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false,
			);
			$y += $row_height * 2.5;
		}

		$text = 'Ich beantrage die ';
		if ($aktive_mitgliedschaft) {
			$text .= 'aktive Mitgliedschaft ';
		} elseif ($jugendliche_mitgliedschaft) {
			$text .= 'jugendliche Mitgliedschaft ';
		} elseif ($passive_mitgliedschaft) {
			$text .= 'passive Mitgliedschaft ';
		} elseif ($foerder_mitgliedschaft) {
			$text .= 'Fördermitgliedschaft ';
		}

		if (in_array($this->getForm('mitgliedschaft_ab', ''), ['nächstmöglich', 'naechstmoeglich'], true)) {
			$text .= 'zum nächstmöglichen Termin.';
		} else {
			$text .= 'zum ' . date('d.m.Y', mktime(0, 0, 0, 1, 1, (int) date('Y') + 1));
		}

		$gebuchte_optionen = [];
		if ($aktive_mitgliedschaft && $option_boot) {
			$gebuchte_optionen[] = 'Boot';
		} elseif ($jugendliche_mitgliedschaft && $option_2angel) {
			$gebuchte_optionen[] = '2.Angel';
		}

		if (!empty($gebuchte_optionen)) {
			$text .= ',';
			$out .= $this->getTextCell(txt: $text, posx: $x, posy: $y, width: $width, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height;

			$text2 = count($gebuchte_optionen) > 1 ? 'sowie die kostenpflichtigen Optionen: ' : 'sowie die kostenpflichtige Option: ';
			$text2 .= implode(', ', $gebuchte_optionen) . '.';
			$out .= $this->getTextCell(txt: $text2, posx: $x, posy: $y, width: $width, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height * 2;
		} else {
			$text .= '.';
			$out .= $this->getTextCell(txt: $text, posx: $x, posy: $y, width: $width, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height * 2;
		}

		if ($minderjaehrig) {
			$out .= $this->getTextCell(txt: 'Nur für Jugendliche gemäß § 5 der Vereinssatzung:', posx: $x, posy: $y, width: $width, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height;
			$out .= $this->getTextCell(txt: 'Hiermit erkläre ich, dass mein Kind schwimmen kann.', posx: $x, posy: $y, width: $width, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$y += $row_height * 2;
		}

		$text = 'Die Satzung und die Gewässerordnung werden mir mit dem Erlaubnisschein nach der Gewässerbegehung überreicht. Mit einer vorläufigen Probemitgliedschaft (zwei Jahre) bin ich einverstanden.';
		$out .= $this->getTextCell(txt: $text, posx: $x, posy: $y, width: $width, height: 0, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
		$y += $row_height * 4.;

		// Kostenübersicht
		$w1 = 140.0;
		$w2 = $width - $w1;
		$x1 = $x;
		$x2 = $x1 + $w1;

		$summe = 0.0;
		$bearbeitungsgebuehr = (float) $this->getOption('betrag_bearbeitungsgebuehr', 0);
		$erstbesatz = (float) $this->getOption('betrag_erstbesatz', 0);
		$betrag_boot = (float) $this->getOption('betrag_boot', 0);
		$betrag_2angel = (float) $this->getOption('betrag_2angel', 0);
		$betrag_aktiv = (float) $this->getOption('betrag_aktiv', 0);
		$betrag_jugend = (float) $this->getOption('betrag_jugend', 0);
		$betrag_passiv = (float) $this->getOption('betrag_passiv', 0);
		$beitrag_foerder = (float) $this->getForm('beitrag_foerder', 0);

		$kostenzeilen = [];
		if ($aktive_mitgliedschaft || $passive_mitgliedschaft || $jugendliche_mitgliedschaft) {
			$summe += $bearbeitungsgebuehr;
			$kostenzeilen[] = ['Bearbeitungsgebühr:', $bearbeitungsgebuehr];
		}
		if ($aktive_mitgliedschaft) {
			$summe += $erstbesatz;
			$kostenzeilen[] = ['Erstbesatz:', $erstbesatz];
			$summe += $betrag_aktiv;
			$kostenzeilen[] = ['jährlicher Mitgliedsbeitrag:', $betrag_aktiv];
			if ($option_boot) {
				$summe += $betrag_boot;
				$kostenzeilen[] = ['Option Boot:', $betrag_boot];
			}
		}
		if ($jugendliche_mitgliedschaft) {
			$summe += $betrag_jugend;
			$kostenzeilen[] = ['jährlicher Mitgliedsbeitrag:', $betrag_jugend];
			if ($option_2angel) {
				$summe += $betrag_2angel;
				$kostenzeilen[] = ['Option 2.Angel:', $betrag_2angel];
			}
		}
		if ($passive_mitgliedschaft) {
			$summe += $betrag_passiv;
			$kostenzeilen[] = ['jährlicher Mitgliedsbeitrag:', $betrag_passiv];
		}
		if ($foerder_mitgliedschaft) {
			$summe += $beitrag_foerder;
			$kostenzeilen[] = ['jährlicher Mitgliedsbeitrag:', $beitrag_foerder];
		}
		$anz_zeilen = count($kostenzeilen);
		$out .= $this->getBox($x, $y, $row_height * $anz_zeilen + $row_height, $width, $row_height, 'Kosten bei Vereinseintritt');


		$box_top = $y;
		foreach ($kostenzeilen as [$kostenlabel, $betrag]) {
			$out .= $this->getTextCell(txt: $kostenlabel, posx: $x1+1, posy: $y, width: $w1, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
			$out .= $this->getTextCell(txt: number_format($betrag, 2, ',', '') . '€', posx: $x2, posy: $y, width: $w2, height: $row_height, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Right, drawcell: false);
			$y += $row_height;
		}


		//$out .= $this->graph->getRect($x, $box_top - 0.5 * $row_height, $width, $row_height * $zeilen + $row_height, 'D', $this->buildFillStyle('TRBL', self::COLOR_BOX_BACKGROUND, self::COLOR_BOX, 0.1));


		$out .= $this->getTextCell(txt: 'Summe:', 
									posx: $x1, 
									posy: $y, 
									width: $w1+1, 
									height: $row_height, 
									offset: 1, 
									linespace: 0, 
									valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
									halign: \Com\Tecnick\Pdf\TextHAlign::Left, 
									styles: $this->buildFillStyle('L', self::COLOR_BOX_BACKGROUND_DARK, self::COLOR_BOX, 0.2), 
									drawcell: true);

		$out .= $this->getTextCell(txt: number_format($summe, 2, ',', '') . '€', 
						posx: $x2, 
						posy: $y, 
						width: $w2, 
						height: $row_height, 
						offset: 0, linespace: 0,
						valign: \Com\Tecnick\Pdf\TextVAlign::Top, 
						halign: \Com\Tecnick\Pdf\TextHAlign::Right, 
						styles: $this->buildFillStyle('R', self::COLOR_GROUPED, self::COLOR_BOX, 0.2), drawcell: true);


		$y += $row_height * 0.6+ 1.5;
		$y += $row_height * 1.5;

		$text = 'Die bei Vereinseintritt entstehenden Kosten sind mir hiermit bekannt und ich sorge für eine ausreichende Deckung meines Kontos.';
		$out .= $this->getTextCell(txt: $text, posx: $x, posy: $y, width: $width, height: 0, offset: 0, linespace: 0, valign: \Com\Tecnick\Pdf\TextVAlign::Top, halign: \Com\Tecnick\Pdf\TextHAlign::Left, drawcell: false);
		$y += $row_height * 3;
		$out .= $this->addApplicationSignature($y, 'Datum, Unterschrift', 'unterschrift');
		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$allformdata = $this->getAllFormdata();
		foreach ($allformdata as $idx => $formdata) {
			if (!is_array($formdata)) {
				continue;
			}
			$this->setFormdata($formdata);

			$this->seite_daten_antragsteller ();
			$this->seite_einwilligung_daten ();
			$this->seite_einwilligung_fotos ();
			$this->seite_antrag ();
			$this->seite_sepa ();
			//$this->enableDefaultPageContent(false);
			//$this->seite_attachments ();
		}
	}
}

/**
 * Example PDF Template with header and footer.
 */
class PdfMitgliedsantragInfoBlatt extends PdfMitgliedsantragData {

	public function gen_registerkarte1($y) {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$x = 10.0;
		$labelgrey = '#b4b4b4';
		$boxStyle = ['all' => [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#d9d9d9',
			'fillColor' => '#fefefe',
		]];

		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190, $row_height * 5, 'DF', $boxStyle);
		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190 * 0.5, $row_height * 5, 'DF', $boxStyle);

		$w1 = 25;
		$w2 = 190 * 0.5 - $w1;
		$w3 = 25;
		$w4 = 190 - $w1 - $w2 - $w3;
		$x1 = $x;
		$x2 = $x1 + $w1;
		$x3 = $x2 + $w2;
		$x4 = $x3 + $w3;

		$data = [
			['label' => 'Mitgliedsnr:', 'value' => (string) $this->getForm('mitgliedsnummer', ''), 'x' => $x1, 'w' => $w1, 'y' => $y, 'col' => $labelgrey],
			['label' => 'Anrede:', 'value' => (string) $this->getForm('anrede', ''), 'x' => $x3, 'w' => $w3, 'y' => $y, 'col' => $labelgrey],
			['label' => 'Vorname:', 'value' => (string) $this->getForm('vorname', ''), 'x' => $x1, 'w' => $w1, 'y' => $y + $row_height, 'col' => $labelgrey],
			['label' => 'Nachname:', 'value' => (string) $this->getForm('name', ''), 'x' => $x3, 'w' => $w3, 'y' => $y + $row_height, 'col' => $labelgrey],
			['label' => 'Straße:', 'value' => (string) $this->getForm('strasse', ''), 'x' => $x1, 'w' => $w1, 'y' => $y + 2 * $row_height, 'col' => $labelgrey],
			['label' => 'PLZ:', 'value' => (string) $this->getForm('plz', ''), 'x' => $x1, 'w' => $w1, 'y' => $y + 3 * $row_height, 'col' => $labelgrey],
			['label' => 'Ort', 'value' => (string) $this->getForm('ort', ''), 'x' => $x3, 'w' => $w3, 'y' => $y + 3 * $row_height, 'col' => $labelgrey],
		];

		foreach ($data as $item) {
			$out .= $this->color->getPdfColor($item['col']);
			$out .= $this->getTextCell(
				txt: $item['label'],
				posx: $item['x'],
				posy: $item['y'],
				width: $item['w'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$out .= $this->color->getPdfColor('#000000');
		$values = [
			['txt' => (string) $this->getForm('mitgliedsnummer', ''), 'posx' => $x2, 'posy' => $y, 'width' => $w2, 'height' => $row_height],
			['txt' => (string) $this->getForm('anrede', ''), 'posx' => $x4, 'posy' => $y, 'width' => $w4, 'height' => $row_height],
			['txt' => (string) $this->getForm('vorname', ''), 'posx' => $x2, 'posy' => $y + $row_height, 'width' => $w2, 'height' => $row_height],
			['txt' => (string) $this->getForm('name', ''), 'posx' => $x4, 'posy' => $y + $row_height, 'width' => $w4, 'height' => $row_height],
			['txt' => (string) $this->getForm('strasse', ''), 'posx' => $x2, 'posy' => $y + 2 * $row_height, 'width' => $w2, 'height' => $row_height],
			['txt' => (string) $this->getForm('plz', ''), 'posx' => $x2, 'posy' => $y + 3 * $row_height, 'width' => $w2, 'height' => $row_height],
			['txt' => (string) $this->getForm('ort', ''), 'posx' => $x4, 'posy' => $y + 3 * $row_height, 'width' => $w4, 'height' => $row_height],
		];

		foreach ($values as $value) {
			$out .= $this->getTextCell(
				txt: $value['txt'],
				posx: $value['posx'],
				posy: $value['posy'],
				width: $value['width'],
				height: $value['height'],
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$y += $row_height * 4.5;

		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Winner Registerkarte 1',
			posx: $x,
			posy: $y,
			width: 190,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', '#d9d9d9'),
			drawcell: true,
		);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + $row_height*1.5;
	}

	public function gen_registerkarte2($y) {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$x = 10.0;
		$labelgrey = '#b4b4b4';
		$boxStyle = ['all' => [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#d9d9d9',
			'fillColor' => '#fefefe',
		]];

		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190, $row_height * 2, 'DF', $boxStyle);
		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190 * 0.5, $row_height * 2, 'DF', $boxStyle);

		$w1 = 25;
		$w2 = 190 * 0.5 - $w1;
		$w3 = 25;
		$w4 = 190 - $w1 - $w2 - $w3;
		$x1 = $x;
		$x2 = $x1 + $w1;
		$x3 = $x2 + $w2;
		$x4 = $x3 + $w3;

		$v1 = empty($this->getForm('geburtstag', '')) ? '' : date('d.m.Y', strtotime((string) $this->getForm('geburtstag', '')));
		$v1 .= empty($this->getForm('alter', '')) ? '' : '         (' . $this->getForm('alter', '') . ')';
		$rows = [
			['label' => 'Geburtstag:', 'labelX' => $x1, 'labelW' => $w1, 'value' => $v1, 'valueX' => $x2, 'valueW' => $w2, 'rightLabel' => 'Beruf:', 'rightLabelX' => $x3, 'rightLabelW' => $w3, 'rightValue' => (string) $this->getForm('beruf', ''), 'rightValueX' => $x4, 'rightValueW' => $w4],
		];

		foreach ($rows as $row) {
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $row['labelX'],
				posy: $y,
				width: $row['labelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['value'],
				posx: $row['valueX'],
				posy: $y,
				width: $row['valueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['rightLabel'],
				posx: $row['rightLabelX'],
				posy: $y,
				width: $row['rightLabelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['rightValue'],
				posx: $row['rightValueX'],
				posy: $y,
				width: $row['rightValueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$y += $row_height;
		$y += $row_height * 0.5;
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Winner Registerkarte 2',
			posx: $x,
			posy: $y,
			width: 190,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', '#d9d9d9'),
			drawcell: true,
		);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + $row_height*1.5;
	}
	public function gen_registerkarte6($y) {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$x = 10.0;
		$labelgrey = '#b4b4b4';
		$boxStyle = ['all' => [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#d9d9d9',
			'fillColor' => '#fefefe',
		]];

		$aktive_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Aktiv';
		$jugendliche_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Jugend';
		$passive_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Passiv';
		$foerder_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Förder';
		$option_2angel = in_array($this->getForm('option_jugend', ''), ['2.Angel'], true);
		$option_boot = in_array($this->getForm('option_boot', ''), ['Boot', 'boot'], true);

		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190, $row_height * 6, 'DF', $boxStyle);
		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190 * 0.5, $row_height * 6, 'DF', $boxStyle);

		$w1 = 25;
		$w2 = 190 * 0.5 - $w1;
		$w3 = 25;
		$w4 = 190 - $w1 - $w2 - $w3;
		$x1 = $x;
		$x2 = $x1 + $w1;
		$x3 = $x2 + $w2;
		$x4 = $x3 + $w3;

		if (in_array($this->getForm('mitgliedschaft_ab', ''), ['nächstmöglich', 'naechstmoeglich'], true)) {
			$v1 = date('d.m.Y', mktime(0, 0, 0, 1, 1, (int) date('Y')));
		} else {
			$v1 = date('d.m.Y', mktime(0, 0, 0, 1, 1, (int) date('Y') + 1));
		}
		$rows = [
			['label' => 'ab:', 'value' => $v1, 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'Abteilung:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('mitgliedschaft', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
		];

		if ($aktive_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '12 - Bearbeitungsgebühr', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => '1 - AktivBeitrag', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} elseif ($passive_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '12 - Bearbeitungsgebühr', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => '2 - PassivBeitrag', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} elseif ($jugendliche_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '12 - Bearbeitungsgebühr', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => '3 - Jugendbeitrag', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} elseif ($foerder_mitgliedschaft) {
			$beitrag_foerder = $this->getForm('beitrag_foerder', '');
			$val = ($beitrag_foerder == 12) ? '412 - Förderbeitrag 12€ ' : '?? - Förderbeitrag ' . $beitrag_foerder . '€ ';
			$rows[] = ['label' => 'BS:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => $val, 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} else {
			$rows[] = ['label' => 'BS:', 'value' => '???????', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => '???????', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		}

		if ($aktive_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS', 'rightLabelX' => $x3, 'rightValue' => '9 - Erstbesatz', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} elseif ($passive_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} elseif ($jugendliche_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS', 'rightLabelX' => $x3, 'rightValue' => $option_2angel ? '30 - Beitrag 2.Angel' : '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} elseif ($foerder_mitgliedschaft) {
			$rows[] = ['label' => 'BS:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		} else {
			$rows[] = ['label' => 'BS:', 'value' => '???????', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS', 'rightLabelX' => $x3, 'rightValue' => '???????', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		}

		if ((int) date('Y') - (int) date('Y', strtotime((string) $this->getForm('geburtstag', ''))) >= 60) {
			$arbeitsV1 = 'Arbeitsdienst befreit!';
			$arbeitsV2 = '';
		} elseif ($aktive_mitgliedschaft) {
			if (empty($this->getForm('schwerbehindert', ''))) {
				$arbeitsV1 = '15h Arbeitsdienst';
			} else {
				$arbeitsV1 = 'Arbeitsdienst befreit! (GdS > 50%)';
			}
			$arbeitsV2 = $option_boot ? '200 - Bootsbeitrag' : '';
		} elseif ($jugendliche_mitgliedschaft) {
			$arbeitsV1 = '5h Arbeitsdienst';
			$arbeitsV2 = '';
		} else {
			$arbeitsV1 = '';
			$arbeitsV2 = '';
		}
		$rows[] = ['label' => 'Arbeits.:', 'value' => $arbeitsV1, 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => $arbeitsV2, 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];
		$rows[] = ['label' => '', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BS:', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4];

		foreach ($rows as $idx => $row) {
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $row['labelX'],
				posy: $y + $idx * $row_height,
				width: $row['labelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['value'],
				posx: $row['valueX'],
				posy: $y + $idx * $row_height,
				width: $row['valueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['rightLabel'],
				posx: $row['rightLabelX'],
				posy: $y + $idx * $row_height,
				width: $row['rightLabelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['rightValue'],
				posx: $row['rightValueX'],
				posy: $y + $idx * $row_height,
				width: $row['rightValueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$y += (count($rows) * $row_height) + ($row_height * 0.5);
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Winner Registerkarte 6',
			posx: $x,
			posy: $y,
			width: 190,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', '#d9d9d9'),
			drawcell: true,
		);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + $row_height*1.5;
	}
	public function gen_registerkarte7($y) {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$x = 10.0;
		$labelgrey = '#b4b4b4';
		$boxStyle = ['all' => [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#d9d9d9',
			'fillColor' => '#fefefe',
		]];

		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190, $row_height * 4, 'DF', $boxStyle);
		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190 * 0.5, $row_height * 4, 'DF', $boxStyle);

		$w1 = 25;
		$w2 = 190 * 0.5 - $w1;
		$w3 = 25;
		$w4 = 190 - $w1 - $w2 - $w3;
		$x1 = $x;
		$x2 = $x1 + $w1;
		$x3 = $x2 + $w2;
		$x4 = $x3 + $w3;

		$rows = [
			['label' => 'IBAN:', 'value' =>$this->iban_to_human_format((string) $this->getForm('kontoinhaber_iban', '')), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'BIC:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('kontoinhaber_bic', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'ab:', 'value' => date('d.m.Y', strtotime((string) $this->getForm('created_at', ''))), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => '', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Name:', 'value' => ((string) $this->getForm('kontoinhaber_name', '') . ', ' . (string) $this->getForm('kontoinhaber_vorname', '')), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => '', 'rightLabelX' => $x3, 'rightValue' => '!!  LS  !!    ' . ((string) $this->getForm('beitrag_summe', '')) . '€', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
		];

		foreach ($rows as $idx => $row) {
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $row['labelX'],
				posy: $y + $idx * $row_height,
				width: $row['labelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['value'],
				posx: $row['valueX'],
				posy: $y + $idx * $row_height,
				width: $row['valueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['rightLabel'],
				posx: $row['rightLabelX'],
				posy: $y + $idx * $row_height,
				width: $row['rightLabelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['rightValue'],
				posx: $row['rightValueX'],
				posy: $y + $idx * $row_height,
				width: $row['rightValueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$y += count($rows) * $row_height;
		$y += $row_height * 0.5;

		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Winner Registerkarte 7',
			posx: $x,
			posy: $y,
			width: 190,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', '#d9d9d9'),
			drawcell: true,
		);



		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + $row_height*1.5;
	}
	public function gen_registerkarte9($y) {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$x = 10.0;
		$labelgrey = '#b4b4b4';
		$boxStyle = ['all' => [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#d9d9d9',
			'fillColor' => '#fefefe',
		]];

		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190, $row_height * 4, 'DF', $boxStyle);
		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190 * 0.5, $row_height * 4, 'DF', $boxStyle);

		$w1 = 25;
		$w2 = 190 * 0.5 - $w1;
		$w3 = 25;
		$w4 = 190 - $w1 - $w2 - $w3;
		$x1 = $x;
		$x2 = $x1 + $w1;
		$x3 = $x2 + $w2;
		$x4 = $x3 + $w3;

		$rows = [
			['label' => 'Tel:', 'value' => (string) $this->getForm('tel_priv', ''), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'Tel Erz.:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('erziehungberechtiger_tel', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Handy:', 'value' => (string) $this->getForm('tel_mobil', ''), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => '', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Email:', 'value' => (string) $this->getForm('email', ''), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'Email Erz.:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('erziehungberechtiger_email', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
		];

		foreach ($rows as $idx => $row) {
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $row['labelX'],
				posy: $y + $idx * $row_height,
				width: $row['labelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['value'],
				posx: $row['valueX'],
				posy: $y + $idx * $row_height,
				width: $row['valueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['rightLabel'],
				posx: $row['rightLabelX'],
				posy: $y + $idx * $row_height,
				width: $row['rightLabelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['rightValue'],
				posx: $row['rightValueX'],
				posy: $y + $idx * $row_height,
				width: $row['rightValueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$y += count($rows) * $row_height;
		$y += $row_height * 0.5;

		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Winner Registerkarte 9',
			posx: $x,
			posy: $y,
			width: 190,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', '#d9d9d9'),
			drawcell: true,
		);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + $row_height*1.5;
	}
	public function gen_registerkarteD($y) {
		$out = $this->graph->getStartTransform();
		$font = $this->font->insert($this->pon, 'helvetica', '', self::FONT_SIZE_TEXT);
		$out .= $font['out'];
		$row_height = $font['height']*0.4;
		$x = 10.0;
		$labelgrey = '#b4b4b4';
		$boxStyle = ['all' => [
			'lineWidth' => 0.1,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#d9d9d9',
			'fillColor' => '#ffffff',
		]];

		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190, $row_height * 7, 'DF', $boxStyle);
		$out .= $this->graph->getRect($x, $y - 0.5 * $row_height, 190 * 0.5, $row_height * 7, 'DF', $boxStyle);

		$w1 = 25;
		$w2 = 190 * 0.5 - $w1;
		$w3 = 25;
		$w4 = 190 - $w1 - $w2 - $w3;
		$x1 = $x;
		$x2 = $x1 + $w1;
		$x3 = $x2 + $w2;
		$x4 = $x3 + $w3;

		$aktive_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Aktiv';
		$jugendliche_mitgliedschaft = $this->getForm('mitgliedschaft', '') === 'Jugend';
		$option_2angel = in_array($this->getForm('option_jugend', ''), ['2.Angel'], true);
		$option_boot = in_array($this->getForm('option_boot', ''), ['Boot', 'boot'], true);
		$gebuchte_optionen = [];
		if ($aktive_mitgliedschaft && $option_boot) {
			$gebuchte_optionen[] = 'Boot';
		} elseif ($jugendliche_mitgliedschaft) {
			$gebuchte_optionen[] = 'Boot';
			if ($option_2angel) {
				$gebuchte_optionen[] = '2.Angel';
			}
		}

		$rows = [
			['label' => 'Einw. Foto:', 'value' => (string) $this->getForm('einwilligung_foto', ''), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'Geburtsort:', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Fischereischein:', 'value' => empty($this->getForm('fischereischein', '')) ? '' : date('Y', strtotime((string) $this->getForm('fischereischein', ''))), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'Schwerbehindert:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('schwerbehindert', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Fischereischein:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'Stempel:', 'rightLabelX' => $x3, 'rightValue' => implode('/', $gebuchte_optionen), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Prüfung:', 'value' => empty($this->getForm('datum_fischerpruefung', '')) ? '' : date('d.m.Y', strtotime((string) $this->getForm('datum_fischerpruefung', ''))), 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'VDSF:', 'rightLabelX' => $x3, 'rightValue' => '', 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Prüfung:', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'password:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('password', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
			['label' => 'Lehrgang', 'value' => '', 'labelX' => $x1, 'valueX' => $x2, 'labelW' => $w1, 'valueW' => $w2, 'rightLabel' => 'username:', 'rightLabelX' => $x3, 'rightValue' => (string) $this->getForm('username', ''), 'rightValueX' => $x4, 'rightLabelW' => $w3, 'rightValueW' => $w4],
		];

		foreach ($rows as $idx => $row) {
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $row['labelX'],
				posy: $y + $idx * $row_height,
				width: $row['labelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['value'],
				posx: $row['valueX'],
				posy: $y + $idx * $row_height,
				width: $row['valueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor($labelgrey);
			$out .= $this->getTextCell(
				txt: $row['rightLabel'],
				posx: $row['rightLabelX'],
				posy: $y + $idx * $row_height,
				width: $row['rightLabelW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['rightValue'],
				posx: $row['rightValueX'],
				posy: $y + $idx * $row_height,
				width: $row['rightValueW'],
				height: $row_height,
				offset: 1,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
		}

		$y += count($rows) * $row_height;
		$y += $row_height * 0.5;
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Winner Registerkarte D',
			posx: $x,
			posy: $y,
			width: 190,
			height: $row_height,
			offset: 1,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			styles: $this->buildFillStyle('', '#d9d9d9'),
			drawcell: true,
		);

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
		return $y + $row_height*1.5;
	}
	public function render(): void {

		$allformdata = $this->getAllFormdata();
		foreach ($allformdata as $idx => $formdata) {
			if (!is_array($formdata)) {
				continue;
			}
			$this->setFormdata($formdata);

			$this->addPage([
				'orientation' => 'P', 
				'format' => 'A4'
			]);
			$y = 10;
			$y = $this->gen_registerkarte1 ($y);
			$y = $this->gen_registerkarte2 ($y);
			$y = $this->gen_registerkarte6 ($y);
			$y = $this->gen_registerkarte7 ($y);
			$y = $this->gen_registerkarte9 ($y);
			$y = $this->gen_registerkarteD ($y);
		}
	}
}
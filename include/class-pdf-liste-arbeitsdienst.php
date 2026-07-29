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
require_once __DIR__ . '/trait-pdf-teilnehmerliste.php';

/**
 * Example PDF Template with header and footer.
 */
class PdfListeArbeitsdienst extends PdfTemplate {
	use PdfHeaderTrait;
	use PdfTeilnehmerlisteTrait;

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
		$this->setOptions([
			'accent_color' => '#1a3a6b',
			'text_color'   => '#555555',
		]);

		$dienst = $this->getUrl('dienst', '');
		
		if (function_exists('bfvarbeitsdienst')) {
			$instance = bfvarbeitsdienst();
			$dienst = $this->getUrl('dienst', '');
			$anmeldungen = $instance->get_Anmeldungen_dienst($dienst, [
				'limit' => 1000,
			]);
		} else {
			$anmeldungen = [];
		}

		$this->setFormdata([
			'documenttype' => 'Liste Arbeitsdienst',
			'anmeldungen' => $anmeldungen,
		]);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		//$this->createStorageFolder( 'bfv_arbeitsdienst' );
	}

   public function generate_header(int $pid): string {
		$page = $this->page->getPage($pid);
		$ph = $page['height'];

		$out = $this->graph->getStartTransform();

		$lineH = 5.0;
		$textSize = 10;

		// "Arbeitsdienst am: ..."
		$font = $this->font->insert($this->pon, 'helvetica', '', 14);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Arbeitsdienst am: ' . $this->getUrl('dienst', ''),
			posx: 20.0,
			posy: 10.0,
			width: 170.0,
			height: $lineH,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// "Arbeitsdienstleiter: ..."
		$out .= $this->getTextCell(
			txt: 'Arbeitsdienstleiter: ________________________________',
			posx: 20.0,
			posy: 18.0,
			width: 170.0,
			height: $lineH,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// "Sicherheitshinweis:" (underlined)
		$fontU = $this->font->insert($this->pon, 'helvetica', 'U', $textSize);
		$out .= $fontU['out'];
		$out .= $this->getTextCell(
			txt: 'Sicherheitshinweis:',
			posx: 20.0,
			posy: 28.0,
			width: 170.0,
			height: $lineH,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Safety text lines
		$fontN = $this->font->insert($this->pon, 'helvetica', '', $textSize);
		$out .= $fontN['out'];
		$safetyLines = [
			'Während Sägearbeiten ist die zugehörige Sicherheitsausrüstung zu tragen.',
			'(z.B. Schnittschutzkleidung incl. Helm mit Sicht- und Gehörschutz.)',
			'Beim Arbeitsdienst werden Sicherheitsschuhe getragen!',
			'Anweisungen der Arbeitsdienstsleiter sowie Einsatzleiter ist Folge zu leisten!',
			'Absolutes Alkoholverbot während des Arbeitsdienstes!',
		];
		$y = 28.0 + $lineH + 1.0;
		foreach ($safetyLines as $line) {
			$out .= $this->getTextCell(
				txt: $line,
				posx: 20.0,
				posy: $y,
				width: 170.0,
				height: $lineH,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
			$y += $lineH;
		}

		// Confirmation lines (underlined)
		$out .= $fontU['out'];
		$confirmLines = [
			'Mit meiner Unterschrift bestätige ich, dass ich diese Sicherheitsanweisungen zur',
			'Kenntnis genommen habe und sie befolgen werde.',
		];
		foreach ($confirmLines as $line) {
			$out .= $this->getTextCell(
				txt: $line,
				posx: 20.0,
				posy: $y,
				width: 170.0,
				height: $lineH,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
			$y += $lineH;
		}

		$out .= $this->graph->getStopTransform();
		return $out;
	}


	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {


		$anmeldungen = $this->getForm('anmeldungen', []);

		// Sort by 'mnr' key in ascending order
		usort($anmeldungen, function ($a, $b) {
			$mnrA = (int) ($a['mnr'] ?? 0);
			$mnrB = (int) ($b['mnr'] ?? 0);
			return $mnrA <=> $mnrB;
		});

		$this->AddPage();
		$x = 20; // Starting X position for the table
		$y = 73; // Starting Y position for the table
		$h = 8; // Height of each row
		$this->add_Zeile8($x, $y, $h, 7.0, 42.0, 43, 11.0, 15.0, 15.0, 12.0, 30.0, "", "Name, Vorname", "Beruf", "MNr.", "Beginn", "Ende", "Std.", "Unterschrift", 230);
		$y+=$h;
		$nr = 0;

        foreach ($anmeldungen as $anmeldung) {
            if ($nr == 25) {
                $this->AddPage();
				$x = 20; // Starting X position for the table
				$y = 73; // Starting Y position for the table
				$h = 8; // Height of each row
				$this->add_Zeile8($x, $y, $h, 7.0, 42.0, 43, 11.0, 15.0, 15.0, 12.0, 30.0, "", "Name, Vorname", "Beruf", "MNr.", "Beginn", "Ende", "Std.", "Unterschrift", 255);
				$y+=$h;
				$nr = 0;
			}
			$nr++;
			$this->add_Zeile8($x, $y, $h, 7.0, 42.0, 43, 11.0, 15.0, 15.0, 12.0, 30.0, $nr, $anmeldung['name'], $anmeldung['beruf'], $anmeldung['mnr'], " ", " ", " ", " ", 255);
			$y += $h;
		}
        $Anzahl_leerzeilen = 25 - $nr;
        for ($i = 1; $i <= $Anzahl_leerzeilen; $i++) {
			$nr++;
			$this->add_Zeile8($x, $y, $h, 7.0, 42.0, 43, 11.0, 15.0, 15.0, 12.0, 30.0, $nr, " ", " ", " ", " ", " ", " ", " ", 255);
			$y += $h;
        }					


	}
}

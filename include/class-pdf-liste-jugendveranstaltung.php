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
class PdfListeJugendveranstaltung extends PdfTemplate {
	use PdfHeaderTrait;
	use PdfTeilnehmerlisteTrait;

	/** Default X position for participant list area (mm). */
	private const TABLE_POSITION_X = 20;
	/** Height of each row (mm). */
	private const ROW_HEIGHT = 8;
	/** Maximum number of rows per page. */
	private const ROWS_PER_PAGE = 25;

	/* column widths for the table */
	private const COL_WIDTHS = [
		7.0,  // Nr.
		10.0, // MNr.
		34.0, // Name, Vorname
		34.0, // Tel.
		34.0, // Tel. Erz
		60.0, // Anwesenheit/Fänge
		0, // 
		0, // 
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(true); // Enable default header/footer and page content
		$this->enableFooter(false); // Disable footer for this template
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
		$this->setOptions([		]);
	
		if (function_exists('bfvjugend')) {
			$instance = bfvjugend();
			$veranstaltung = $this->getUrl('veranstaltung', '');
			$anmeldungen = $instance->get_Anmeldungen_Veranstaltung($veranstaltung, [
				'limit' => 1000,
			]);
		} else {
			$anmeldungen = [];
		}

		$this->setFormdata([
			'documenttype' => 'Liste Jugendveranstaltung',
			'anmeldungen' => $anmeldungen,
		]);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		//$this->createStorageFolder( 'bfv_jugendveranstaltung' );
	}



   protected function add_text(): void {

		$out = $this->graph->getStartTransform();
		$lineH = 10.0;

		// "Jugendveranstaltung am: ..."
		$font = $this->font->insert($this->pon, 'helvetica', '', 14);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: 'Jugendveranstaltung am: ' . $this->getUrl('veranstaltung', ''),
			posx: 20.0,
			posy: 10.0,
			width: 170.0,
			height: $lineH,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);


		// text lines
		$fontN = $this->font->insert($this->pon, 'helvetica', '', 14);
		$out .= $fontN['out'];
		$safetyLines = [
			'Jugendleiter: __________________________________________',
			'_____________________________________________________',
			'_____________________________________________________',
			'_____________________________________________________',
			'_____________________________________________________',
		];
		$y = 22.0;
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

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}

	/**
	 * Add a header line for the table.
	 *
	 * @param float $y The Y position for the header line.
	 * @return void
	 */
	protected function add_line_table_header(float $y): void {
		$this->add_Zeile8(self::TABLE_POSITION_X, 
						  $y, 
						  self::ROW_HEIGHT, 
						  self::COL_WIDTHS[0], 
						  self::COL_WIDTHS[1],
						  self::COL_WIDTHS[2],
						  self::COL_WIDTHS[3],
						  self::COL_WIDTHS[4],
						  self::COL_WIDTHS[5],
						  self::COL_WIDTHS[6],
						  self::COL_WIDTHS[7], 
						  "", 
						  "MNr.", 
						  "Teilnehmer", 
						  "Tel.", 
						  "Tel. Erz.", 
						  "Anwesenheit/Fänge", 
						  "", 
						  "",
						  230);
	}

	/**
	 * Add a header line for the table.
	 *
	 * @param float $y The Y position for the header line.
	 * @return void
	 */
	protected function add_line_table(float $y, int|string $nr, int|string $mnr, string $text2, string $text3, string $text4): void {
		$this->add_Zeile8(self::TABLE_POSITION_X, 
						  $y, 
						  self::ROW_HEIGHT,
						  self::COL_WIDTHS[0], 
						  self::COL_WIDTHS[1],
						  self::COL_WIDTHS[2],
						  self::COL_WIDTHS[3],
						  self::COL_WIDTHS[4],
						  self::COL_WIDTHS[5],
						  self::COL_WIDTHS[6],
						  self::COL_WIDTHS[7],  
						  (string) $nr,
						  (string) $mnr,
						  $text2, 
						  $text3, 
						  $text4, 
						  " ", " ", " ", 255);
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
		//  Generate the header for the Jugendveranstaltung list
		$this->add_text();

		$y = 73.0; // Starting Y position for the table
		$this->add_line_table_header($y);
		$y += self::ROW_HEIGHT;
		$nr = 0;
        foreach ($anmeldungen as $anmeldung) {
            if ($nr == self::ROWS_PER_PAGE) {
                $this->AddPage();
				//  Generate the header for the Jugendveranstaltung list
				$this->add_text();
				$y = 73.0; // Starting Y position for the table
				$this->add_line_table_header($y);
				$y += self::ROW_HEIGHT;
				$nr = 0;
			}
			$nr++;
			$this->add_line_table($y, $nr, $anmeldung['mnr'], $anmeldung['name'], $anmeldung['tel'], $anmeldung['tel_erz']);
			$y += self::ROW_HEIGHT;
		}
        $Anzahl_leerzeilen = self::ROWS_PER_PAGE - $nr;
        for ($i = 1; $i <= $Anzahl_leerzeilen; $i++) {
			$nr++;
			$this->add_line_table($y, $nr, " "," ", " ", " ");
			$y += self::ROW_HEIGHT;

		}					
	}
}

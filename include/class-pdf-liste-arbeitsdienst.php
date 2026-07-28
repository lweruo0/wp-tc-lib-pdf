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


			$anmeldungen = $instance->get_Anmeldungen_dienst($_REQUEST ['dienst'], [
				'limit' => 1000,
			]);

		}

		
		$this->setFormdata([
			'documenttype' => 'Liste Arbeitsdienst',
			'brutto' => 25.00,
			'zahlungsfrist' => '10.07.2026',
			'rechnungsnummer' => '2026-P-0151',
			'first_name' => 'Bruno',
			'last_name'  => 'Kasssssler',
			'street'     => 'Lindenstraße 94',
			'zip'        => '89099',
			'city'       => 'Ulm',
			'email'      => 'kassler@example.com',
			'sender'	 => 'Bezirksfischerei-Verein e.V. Ehingen/Donau, Postfach 1340, 89573 Ehingen',
			'returnme'	 => 'falls unzustellbar, bitte zurück',
		]);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('bfv_mitgliedsantrag');
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->addPage();

		$x = 20; // Starting X position for the table
		$y = 100; // Starting Y position for the table
		$h = 6; // Height of each row
		$this->add_Zeile8($x, $y, $h, 7.0, 42.0, 43, 11.0, 15.0, 15.0, 12.0, 30.0, "", "Name, Vorname", "Beruf", "MNr.", "Beginn", "Ende", "Std.", "Unterschrift", 230);
		$y+=$h;
		$this->add_Zeile8($x, $y, $h, 7.0, 42.0, 43, 11.0, 15.0, 15.0, 12.0, 30.0, "", "", "", "", "", "", "", "", 230);

	}
}

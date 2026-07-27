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
class PdfMitgliedsantrag extends PdfTemplate {

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

		$this->setFormdata([

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
	}
}

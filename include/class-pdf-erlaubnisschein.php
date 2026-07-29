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

		$rechnungsnummer = $this->getUrl('nr', '');
		$options = get_option ( 'bfv_erlaubnisschein' );
		$this->setOptions($options);

		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
		$this->createStorageFolder('bfv_erlaubnisschein');
		//$this->setFileName("rechnung_$rechnungsnummer.pdf");

		if (function_exists('bfverlaubnisscheine')) {
			$instance = bfverlaubnisscheine ();
			$formdata = $instance->get_formdata_by_rechnungsnummer ( $rechnungsnummer );
		} else {
			$formdata = [];
		}

		$formdata['documenttype'] = 'Erlaubnisschein';
		$formdata['returnme'] = 'falls unzustellbar, bitte zurück';
		$this->setFormdata($formdata);


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

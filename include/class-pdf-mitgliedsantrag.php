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
class PdfMitgliedsantrag2 extends PdfTemplate {

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
					$formdata[] = $instance->get_formdata_by_mitgliedsnummer ( $mitgliedsnummer );
				}
			} else {
				$formdata = [];
			}
		}

		$formdata['documenttype'] = 'Mitgliedsantrag';

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

<?php
/**
 * Abstract PDF Template class.
 *
 * Base class for all PDF templates. Provides common functionality and
 * requires subclasses to implement their specific rendering logic.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/trait-pdf-data.php';

/**
 * Abstract PDF Template class.
 */
abstract class PdfTemplate extends \Com\Tecnick\Pdf\Tcpdf {
	use PdfDataTrait;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->configureTrustedFilePaths();
	}

	/**
	 * Allow tc-lib-pdf to read plugin-local files such as images.
	 *
	 * @return void
	 */
	protected function configureTrustedFilePaths(): void {
		$pluginRoot = realpath(__DIR__ . '/..');
		if ($pluginRoot === false) {
			return;
		}

		$allowedPaths = $this->defaultFileAllowedPaths();
		$allowedPaths[] = $pluginRoot;
		$allowedPaths = array_values(array_unique($allowedPaths));

		$this->file->setAllowedPaths($allowedPaths);
		$this->markupFile->setAllowedPaths($allowedPaths);
	}

	/**
	 * Initialize the PDF document.
	 *
	 * @return void
	 */
	protected function initialize(): void {

		$author = $this->getAddress ( 'name_verein' );
		$this->SetCreator($author);
		$this->SetAuthor($author);

		$documenttype = $this->getForm('documenttype');
		if ($documenttype === null) {
			$documenttype = 'PDF Document';
		}
		$this->SetTitle ( $documenttype );
		$this->SetSubject ( $documenttype );
		$this->SetKeywords ( $documenttype );
	}

	/**
	 * Load template data into options, formdata and addressdata.
	 *
	 * Implement this method in every template to define where the data
	 * comes from (POST submission, WP options, DB query, etc.).
	 * Called automatically before render().
	 *
	 * @return void
	 */
	abstract protected function loadData(): void;

	/**
	 * Render the PDF document.
	 *
	 * This method should be implemented by subclasses to define
	 * the specific PDF content and layout.
	 *
	 * @return void
	 */
	abstract protected function render(): void;

	/**
	 * Generate and send the PDF as a browser download (Content-Disposition: attachment).
	 *
	 * @param string $filename Optional. Filename for download. Default empty.
	 *
	 * @return void
	 */
	public function output(string $filename = ''): void {
		if ($filename !== '') {
			$this->setPDFFilename($filename);
		}

		$this->loadData();
		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();
		$this->downloadPDF($rawpdf);
	}

	/**
	 * Generate and stream the PDF inline to the browser (Content-Disposition: inline).
	 *
	 * @param string $filename Optional. Filename hint for the browser. Default empty.
	 *
	 * @return void
	 */
	public function stream(string $filename = ''): void {
		if ($filename !== '') {
			$this->setPDFFilename($filename);
		}
		$this->loadData();
		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();
		$this->renderPDF($rawpdf);
	}

}

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

		$author = $this->getAddress ( 'name_verein',  '' );
		$this->SetCreator($author);
		$this->SetAuthor($author);

		$documenttype = $this->getForm('documenttype', '');
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
	 * If a filename is set via setFileNameAbs() and the file exists on the filesystem,
	 * it will be served from the cache. Otherwise, it will be generated on-the-fly.
	 *
	 * @return void
	 */
	public function output(): void {
		$filename = $this->getFileNameAbs();
		if ($filename !== '' && file_exists($filename)) {
			header ( "Expires: 0" );
			header ( "Cache-Control: must-revalidate" );
			header ( 'Cache-Control: pre-check=0, post-check=0, max-age=0', false );
			header ( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); // Date in the past
			header ( 'Last-Modified: ' . gmdate ( 'D, d M Y H:i:s' ) . ' GMT' );
			header ( "Pragma: public" );
			header ( "Content-type: application/pdf" );
			header ( "Content-Disposition: attachment; filename=\"" . basename ( $filename ) . "\"" );
			readfile ( "{$filename}" );
			exit ();
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
	 * If a filename is set via setFileNameAbs() and the file exists on the filesystem,
	 * it will be served from the cache. Otherwise, it will be generated on-the-fly.
	 *
	 * @return void
	 */
	public function stream(): void {
		$filename = $this->getFileNameAbs();
		if ($filename !== '' && file_exists($filename)) {
			header ( "Expires: 0" );
			header ( "Cache-Control: must-revalidate" );
			header ( 'Cache-Control: pre-check=0, post-check=0, max-age=0', false );
			header ( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); // Date in the past
			header ( 'Last-Modified: ' . gmdate ( 'D, d M Y H:i:s' ) . ' GMT' );
			header ( "Pragma: public" );
			header ( "Content-type: application/pdf" );
			header ( "Content-Disposition: inline; filename=\"" . basename ( $filename ) . "\"" );
			readfile ( "{$filename}" );
			exit ();
		}

		$this->loadData();
		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();
		$this->renderPDF($rawpdf);
	}

	/**
	 * Save the PDF to the filesystem.
	 *
	 * Creates the storage folder if it doesn't exist and saves the rendered PDF.
	 * The filename must be set via setFileNameAbs() or setFileNameAbsWithFolder().
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save(): bool {
		$filename = $this->getFileNameAbs();
		if ($filename === '') {
			return false;
		}

		// Create directory if it doesn't exist
		$dir = dirname($filename);
		if (!is_dir($dir)) {
			if (!wp_mkdir_p($dir)) {
				return false;
			}
		}

		// Generate PDF
		$this->loadData();
		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();

		// Save to file
		return file_put_contents($filename, $rawpdf) !== false;
	}

}

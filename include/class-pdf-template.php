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
abstract class PdfTemplateBase extends \Com\Tecnick\Pdf\Tcpdf {
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
		$this->loadData();
		$filename = $this->getFileNameAbs();
		$new = $this->getUrl('new', false);

		if ($filename !== '' && file_exists($filename) && is_file($filename) && !$new) {
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

		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();

		$storagePath = $this->getStoragePath();
		if ($storagePath !== '') {
			//file_put_contents($filename, $rawpdf);
		}
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
		$this->loadData();

		$filename = $this->getFileNameAbs();
		$new = $this->getUrl('new', false);

		if ($filename !== '' && file_exists($filename) && is_file($filename) && !$new) {
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

		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();
		$storagePath = $this->getStoragePath();
		if ($storagePath !== '') {
			//file_put_contents($filename, $rawpdf);
		}

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
		$this->loadData();
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
		$this->initialize();
		$this->render();
		$rawpdf = $this->getOutPDFString();

		// Save to file
		return file_put_contents($filename, $rawpdf) !== false;
	}
}


/**
 * Abstract PDF Template class.
 */
abstract class PdfTemplate extends PdfTemplateBase {

	/*
	 * Builds a fill style array for the PDF.
	 *
	 * @param string $stylestring The sides to apply the line style to (T, R, B, L).
	 * @param string $fillColor The fill color.
	 * @param string $lineColor The line color.
	 * @param float $lineWidth The line width.
	 * @return array The fill style array.
	 */

	public function buildFillStyle(string $stylestring, string $fillColor, string $lineColor = '#000000', float $lineWidth = 0.1): array {
		$fillStyle = [
			'lineWidth' => 0.0,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => $fillColor,
			'fillColor' => $fillColor,
		];

		/* if all sides are specified, apply the line style to all sides */
		if (\strpos($stylestring, 'T') !== false && 
		    \strpos($stylestring, 'R') !== false && 
		    \strpos($stylestring, 'B') !== false && 
		    \strpos($stylestring, 'L') !== false) {
			$fillStyle['lineWidth'] = $lineWidth;
			$fillStyle['lineColor'] = $lineColor;
			return array('all' => $fillStyle);
		}
		/* if not all sides are specified, apply the line style individually */
		$styles = [
			'all' => $fillStyle,
			0 => $fillStyle,
			1 => $fillStyle,
			2 => $fillStyle,
			3 => $fillStyle,
		];

		if (\strpos($stylestring, 'T') !== false) {
			$styles[0] = \array_merge($fillStyle, ['lineWidth' => $lineWidth, 'lineColor' => $lineColor]);
		}
		if (\strpos($stylestring, 'R') !== false) {
			$styles[1] = \array_merge($fillStyle, ['lineWidth' => $lineWidth, 'lineColor' => $lineColor]);
		}
		if (\strpos($stylestring, 'B') !== false) {
			$styles[2] = \array_merge($fillStyle, ['lineWidth' => $lineWidth, 'lineColor' => $lineColor]);
		}
		if (\strpos($stylestring, 'L') !== false) {
			$styles[3] = \array_merge($fillStyle, ['lineWidth' => $lineWidth, 'lineColor' => $lineColor]);
		}

		return $styles;
	}


}
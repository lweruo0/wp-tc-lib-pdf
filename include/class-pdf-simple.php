<?php
/**
 * Simple PDF Template class.
 *
 * A minimal PDF template without header/footer for simple documents.
 * Shows how to create a template without using traits.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-pdf-template.php';

/**
 * Simple PDF Template without header and footer.
 */
class PdfSimple extends PdfTemplate {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->title = 'Simple Document';
		$this->subject = 'A simple PDF document';
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->addPage();

		// Main title
		$this->setFontSize(24);
		$this->color->setPdfColor('#1a3a6b');
		$out = $this->getTextCell(
			txt: 'Simple PDF Document',
			posx: 10,
			posy: 20,
			width: 190,
			height: 20,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
		);
		echo $out; // phpcs:ignore

		// Content
		$this->setFontSize(12);
		$this->color->setPdfColor('#333333');
		$content = "This is a simple PDF template without header and footer.\n\n"
			. "It demonstrates how to create a basic PDF document with custom content.\n\n"
			. "You can extend PdfTemplate and override the render() method to create "
			. "your own PDF layouts and content structures.";

		$out = $this->getTextCell(
			txt: $content,
			posx: 10,
			posy: 50,
			width: 190,
			height: 100,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		echo $out; // phpcs:ignore

		// Footer text
		$this->setFontSize(10);
		$this->color->setPdfColor('#999999');
		$footer = "Generated on " . date('Y-m-d H:i:s');

		$out = $this->getTextCell(
			txt: $footer,
			posx: 10,
			posy: 270,
			width: 190,
			height: 10,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
		);
		echo $out; // phpcs:ignore
	}
}

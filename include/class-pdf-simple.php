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

		$out = '';

		// Main title
		$fontBig = $this->font->insert($this->pon, 'helvetica', 'B', 24);
		$out .= $fontBig['out'];
		$out .= $this->color->getPdfColor('#1a3a6b');
		$out .= $this->getTextCell(
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

		// Content
		$fontNormal = $this->font->insert($this->pon, 'helvetica', '', 12);
		$out .= $fontNormal['out'];
		$out .= $this->color->getPdfColor('#333333');
		$out .= $this->getTextCell(
			txt: "This is a simple PDF template without header and footer.\n\n"
				. "It demonstrates how to create a basic PDF document with custom content.\n\n"
				. "You can extend PdfTemplate and override the render() method to create "
				. "your own PDF layouts and content structures.",
			posx: 10,
			posy: 50,
			width: 190,
			height: 100,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Footer text
		$fontSmall = $this->font->insert($this->pon, 'helvetica', '', 10);
		$out .= $fontSmall['out'];
		$out .= $this->color->getPdfColor('#999999');
		$out .= $this->getTextCell(
			txt: 'Generated on ' . gmdate('Y-m-d H:i:s'),
			posx: 10,
			posy: 270,
			width: 190,
			height: 10,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
		);

		$this->page->addContent($out);
	}
}

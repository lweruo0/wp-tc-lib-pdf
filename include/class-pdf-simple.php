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
	 * Load data for this template.
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$this->setOptions([
			'accent_color' => '#1a3a6b',
			'text_color'   => '#333333',
			'footer_color' => '#999999',
		]);

		$this->setFormdata([
			'headline' => 'Simple PDF Document',
			'body'     => "This is a simple PDF template without header and footer.\n\n"
				. "It demonstrates how to create a basic PDF document with custom content.\n\n"
				. "You can extend PdfTemplate and override the render() method to create "
				. "your own PDF layouts and content structures.",
		]);
		$adressData = get_option ( 'bfv_adressen' );
		$this->setAddressdata($adressData);
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->addPage();

		$out = '';

		$accentColor = (string) $this->getOption('accent_color', '#1a3a6b');
		$textColor   = (string) $this->getOption('text_color', '#333333');
		$footerColor = (string) $this->getOption('footer_color', '#999999');

		// Main title
		$fontBig = $this->font->insert($this->pon, 'helvetica', 'B', 24);
		$out .= $fontBig['out'];
		$out .= $this->color->getPdfColor($accentColor);
		$out .= $this->getTextCell(
			txt: (string) $this->getForm('headline', ''),
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
		$out .= $this->color->getPdfColor($textColor);
		$out .= $this->getTextCell(
			txt: (string) $this->getForm('body', ''),
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
		$out .= $this->color->getPdfColor($footerColor);
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

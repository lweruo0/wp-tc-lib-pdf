<?php
/**
 * Invoice PDF Template class.
 *
 * Demonstrates a more complex PDF template with invoice-specific content.
 * Uses both header/footer and custom styling.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-pdf-template.php';
require_once __DIR__ . '/trait-pdf-header-footer.php';

/**
 * Invoice PDF Template with header and footer.
 */
class PdfInvoice extends PdfTemplate {
	use PdfHeaderFooterTrait;

	/**
	 * Invoice number.
	 *
	 * @var string
	 */
	protected string $invoice_number = 'INV-001';

	/**
	 * Invoice date.
	 *
	 * @var string
	 */
	protected string $invoice_date = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->title = 'Invoice';
		$this->subject = 'Invoice Document';
		$this->invoice_date = date('Y-m-d');
		$this->enableDefaultPageContent(true);
	}

	/**
	 * Set invoice number.
	 *
	 * @param string $number Invoice number.
	 *
	 * @return void
	 */
	public function setInvoiceNumber(string $number): void {
		$this->invoice_number = $number;
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->setHeaderText('Invoice', $this->invoice_date);
		$this->addPage();

		$out = '';

		$fontBig = $this->font->insert($this->pon, 'helvetica', 'B', 18);
		$fontNormal = $this->font->insert($this->pon, 'helvetica', '', 11);
		$fontSmall = $this->font->insert($this->pon, 'helvetica', '', 10);
		$fontMid = $this->font->insert($this->pon, 'helvetica', 'B', 12);

		// Invoice heading
		$out .= $fontBig['out'];
		$out .= $this->color->getPdfColor('#1a3a6b');
		$out .= $this->getTextCell(
			txt: 'INVOICE',
			posx: 10,
			posy: 50,
			width: 190,
			height: 15,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Invoice details
		$out .= $fontNormal['out'];
		$out .= $this->color->getPdfColor('#555555');
		$out .= $this->getTextCell(
			txt: "Invoice Number: {$this->invoice_number}\nDate: {$this->invoice_date}\nStatus: Pending",
			posx: 10,
			posy: 70,
			width: 95,
			height: 30,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Bill to
		$out .= $fontNormal['out'];
		$out .= $this->color->getPdfColor('#1a3a6b');
		$out .= $this->getTextCell(
			txt: 'BILL TO:',
			posx: 10,
			posy: 110,
			width: 190,
			height: 8,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$out .= $this->color->getPdfColor('#555555');
		$out .= $this->getTextCell(
			txt: "Company Name\n123 Main Street\nCity, State 12345\nCountry",
			posx: 10,
			posy: 120,
			width: 95,
			height: 25,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Items header
		$out .= $fontSmall['out'];
		$out .= $this->color->getPdfColor('#1a3a6b');
		$out .= $this->getTextCell(
			txt: 'Description                    Amount',
			posx: 10,
			posy: 160,
			width: 190,
			height: 8,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$out .= $this->color->getPdfColor('#555555');
		$out .= $this->getTextCell(
			txt: "Item 1 - Sample Product        99.99\nItem 2 - Service               49.99\nItem 3 - Support               29.99",
			posx: 10,
			posy: 170,
			width: 190,
			height: 20,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Total
		$out .= $fontMid['out'];
		$out .= $this->color->getPdfColor('#1a3a6b');
		$out .= $this->getTextCell(
			txt: 'TOTAL: 179.97',
			posx: 10,
			posy: 200,
			width: 190,
			height: 10,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Right,
		);

		$this->page->addContent($out);
	}
}

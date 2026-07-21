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

		// Invoice header
		$this->setFontSize(18);
		$this->color->setPdfColor('#1a3a6b');
		$out = $this->getTextCell(
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
		echo $out; // phpcs:ignore

		// Invoice details
		$this->setFontSize(11);
		$this->color->setPdfColor('#555555');
		$details = "Invoice Number: " . $this->invoice_number . "\n"
			. "Date: " . $this->invoice_date . "\n"
			. "Status: Pending";

		$out = $this->getTextCell(
			txt: $details,
			posx: 10,
			posy: 70,
			width: 95,
			height: 30,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		echo $out; // phpcs:ignore

		// Bill to section
		$this->setFontSize(11);
		$this->color->setPdfColor('#1a3a6b');
		$out = $this->getTextCell(
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
		echo $out; // phpcs:ignore

		$this->color->setPdfColor('#555555');
		$address = "Company Name\n123 Main Street\nCity, State 12345\nCountry";

		$out = $this->getTextCell(
			txt: $address,
			posx: 10,
			posy: 120,
			width: 95,
			height: 25,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		echo $out; // phpcs:ignore

		// Items table (simplified)
		$this->setFontSize(10);
		$this->color->setPdfColor('#1a3a6b');
		$table_header = "Description\t\t\tAmount";
		$out = $this->getTextCell(
			txt: $table_header,
			posx: 10,
			posy: 160,
			width: 190,
			height: 8,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		echo $out; // phpcs:ignore

		$this->color->setPdfColor('#555555');
		$items = "Item 1 - Sample Product\t\t$99.99\n"
			. "Item 2 - Service\t\t\t$49.99\n"
			. "Item 3 - Support\t\t\t$29.99";

		$out = $this->getTextCell(
			txt: $items,
			posx: 10,
			posy: 170,
			width: 190,
			height: 20,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);
		echo $out; // phpcs:ignore

		// Total
		$this->setFontSize(12);
		$this->color->setPdfColor('#1a3a6b');
		$out = $this->getTextCell(
			txt: "TOTAL: $179.97",
			posx: 10,
			posy: 200,
			width: 190,
			height: 10,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Right,
		);
		echo $out; // phpcs:ignore
	}
}

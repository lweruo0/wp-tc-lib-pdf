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
	 * Load data for this template.
	 *
	 * Populate from a WooCommerce order, custom DB query, or $_POST data
	 * by overriding this method or calling setFormdata()/setAddressdata()
	 * on the instance before calling output()/stream().
	 *
	 * @return void
	 */
	protected function loadData(): void {
		$this->setOptions([
			'accent_color' => '#1a3a6b',
			'text_color'   => '#555555',
			'currency'     => 'EUR',
		]);

		$this->setFormdata([
			'invoice_number' => $this->invoice_number,
			'invoice_date'   => $this->invoice_date,
			'status'         => 'Pending',
			'items'          => [
				['label' => 'Item 1 - Sample Product', 'amount' => '99.99'],
				['label' => 'Item 2 - Service',         'amount' => '49.99'],
				['label' => 'Item 3 - Support',         'amount' => '29.99'],
			],
			'total' => '179.97',
		]);

		$this->setAddressdata([
			'name'    => 'Company Name',
			'street'  => '123 Main Street',
			'city'    => 'City, State 12345',
			'country' => 'Country',
		]);
	}

	/**
	 * Render the PDF document.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->setHeaderText('Invoice', (string) $this->getForm('invoice_date', $this->invoice_date));
		$this->addPage();

		$out = '';

		$accentColor = (string) $this->getOption('accent_color', '#1a3a6b');
		$textColor   = (string) $this->getOption('text_color', '#555555');

		$fontBig    = $this->font->insert($this->pon, 'helvetica', 'B', 18);
		$fontNormal = $this->font->insert($this->pon, 'helvetica', '', 11);
		$fontSmall  = $this->font->insert($this->pon, 'helvetica', '', 10);
		$fontMid    = $this->font->insert($this->pon, 'helvetica', 'B', 12);

		// Invoice heading
		$out .= $fontBig['out'];
		$out .= $this->color->getPdfColor($accentColor);
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
		$invoiceNumber = (string) $this->getForm('invoice_number', 'INV-001');
		$invoiceDate   = (string) $this->getForm('invoice_date', $this->invoice_date);
		$status        = (string) $this->getForm('status', 'Pending');

		$out .= $fontNormal['out'];
		$out .= $this->color->getPdfColor($textColor);
		$out .= $this->getTextCell(
			txt: "Invoice Number: {$invoiceNumber}\nDate: {$invoiceDate}\nStatus: {$status}",
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
		$name    = (string) $this->getAddress('name', '');
		$street  = (string) $this->getAddress('street', '');
		$city    = (string) $this->getAddress('city', '');
		$country = (string) $this->getAddress('country', '');

		$out .= $fontNormal['out'];
		$out .= $this->color->getPdfColor($accentColor);
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

		$out .= $this->color->getPdfColor($textColor);
		$out .= $this->getTextCell(
			txt: "{$name}\n{$street}\n{$city}\n{$country}",
			posx: 10,
			posy: 120,
			width: 95,
			height: 25,
			offset: 0,
			linespace: 5,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		// Items
		$out .= $fontSmall['out'];
		$out .= $this->color->getPdfColor($accentColor);
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

		$itemLines = '';
		$items = $this->getForm('items', []);
		if (is_array($items)) {
			foreach ($items as $item) {
				if (is_array($item)) {
					$label  = (string) ($item['label'] ?? '');
					$amount = (string) ($item['amount'] ?? '');
					$itemLines .= str_pad($label, 32) . $amount . "\n";
				}
			}
		}

		$out .= $this->color->getPdfColor($textColor);
		$out .= $this->getTextCell(
			txt: rtrim($itemLines),
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
		$total = (string) $this->getForm('total', '0.00');
		$out .= $fontMid['out'];
		$out .= $this->color->getPdfColor($accentColor);
		$out .= $this->getTextCell(
			txt: "TOTAL: {$total}",
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

<?php
/**
 * PDF Rechnungsdaten Trait.
 *
 * Provides reusable rendering for invoice metadata blocks.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait for invoice data rendering.
 *
 * @mixin PdfTemplate
 * @phpstan-require-extends PdfTemplate
 */
trait PdfRechnungsdatenTrait {
	/** Default X position for invoice data area (mm). */
	private const RECHNUNG_X = 125.0;

	/** Default Y position for invoice data area (mm). */
	private const RECHNUNG_Y = 98.0;

	/** Default width for invoice data area (mm). */
	private const RECHNUNG_W = 75.0;

	/** Default height for invoice data area (mm). */
	private const RECHNUNG_H = 38.0;

	/**
	 * Build invoice data rows from form data.
	 *
	 * Recognized keys:
	 * - invoice_number
	 * - invoice_date
	 * - customer_number
	 * - due_date
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	public function getRechnungsdatenRows(): array {


		$bic_verein = trim((string) $this->getAddress('bic_verein', ''));
		$iban_verein = trim((string) $this->getAddress('iban_verein', ''));
		$bank_verein = trim((string) $this->getAddress('bank_verein', ''));


		$rows = [
			['label' => 'Rechnungsnr.', 'value' => trim((string) $this->getForm('invoice_number', ''))],
			['label' => 'Rechnungsdatum', 'value' => trim((string) $this->getForm('invoice_date', ''))],
			['label' => 'Kundennummer', 'value' => trim((string) $this->getForm('customer_number', ''))],
			['label' => 'Faelligkeit', 'value' => trim((string) $this->getForm('due_date', ''))],
		];

		return array_values(array_filter($rows, static fn(array $row): bool => $row['value'] !== ''));
	}

	/**
	 * Render invoice data block and return raw PDF stream.
	 *
	 * @param float                                       $x         Left position in mm.
	 * @param float                                       $y         Top position in mm.
	 * @param float                                       $width     Block width in mm.
	 * @param float                                       $height    Block height in mm.
	 * @param bool                                        $drawFrame Whether to draw a visible frame.
	 * @param ?array<int, array{label: string, value: string}> $rows  Optional custom rows.
	 *
	 * @return string
	 */
	public function generate_rechnungsdaten(
		float $x = self::RECHNUNG_X,
		float $y = self::RECHNUNG_Y,
		float $width = self::RECHNUNG_W,
		float $height = self::RECHNUNG_H,
		bool $drawFrame = false,
		?array $rows = null,
	): string {
		$dataRows = $rows ?? $this->getRechnungsdatenRows();
		$out = $this->graph->getStartTransform();

		if ($drawFrame) {
			$frameStyle = [[
				'lineWidth' => 0.35,
				'lineCap' => 'butt',
				'lineJoin' => 'miter',
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => '#666666',
			]];
			$out .= $this->graph->getRect($x, $y, $width, $height, 'D', $frameStyle);
		}

		$innerX = $x + 2.0;
		$innerY = $y + 2.0;
		$innerW = max(0.0, $width - 4.0);
		$innerH = max(0.0, $height - 4.0);
		$rowH = 5.0;
		$labelW = min(26.0, $innerW * 0.45);

		$labelFont = $this->font->insert($this->pon, 'helvetica', 'B', 9);
		$valueFont = $this->font->insert($this->pon, 'helvetica', '', 9);

		$cursorY = $innerY;
		$maxY = $innerY + $innerH;

		foreach ($dataRows as $row) {
			if ($cursorY + $rowH > $maxY) {
				break;
			}

			$out .= $labelFont['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $innerX,
				posy: $cursorY,
				width: $labelW,
				height: $rowH,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);

			$out .= $valueFont['out'];
			$out .= $this->color->getPdfColor('#333333');
			$out .= $this->getTextCell(
				txt: $row['value'],
				posx: $innerX + $labelW,
				posy: $cursorY,
				width: max(0.0, $innerW - $labelW),
				height: $rowH,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);

			$cursorY += $rowH;
		}

		$out .= $this->graph->getStopTransform();
		return $out;
	}

	/**
	 * Draw invoice data block directly on the current page.
	 *
	 * @param float                                       $x         Left position in mm.
	 * @param float                                       $y         Top position in mm.
	 * @param float                                       $width     Block width in mm.
	 * @param float                                       $height    Block height in mm.
	 * @param bool                                        $drawFrame Whether to draw a visible frame.
	 * @param ?array<int, array{label: string, value: string}> $rows  Optional custom rows.
	 *
	 * @return void
	 */
	public function add_rechnungsdaten(
		float $x = self::RECHNUNG_X,
		float $y = self::RECHNUNG_Y,
		float $width = self::RECHNUNG_W,
		float $height = self::RECHNUNG_H,
		bool $drawFrame = false,
		?array $rows = null,
	): void {
		$this->page->addContent($this->generate_rechnungsdaten($x, $y, $width, $height, $drawFrame, $rows));
	}
}

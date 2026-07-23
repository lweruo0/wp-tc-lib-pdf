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
	private const RECHNUNG_Y = 71.0;

	/** Default width for invoice data area (mm). */
	private const RECHNUNG_W = 75.0;

	/** Default height for invoice data area (mm). */
	private const RECHNUNG_H = 24.0;

	/**
	 * Build invoice data rows from form data.
	 *
	 * Recognized keys:
	 * - invoice_number
	 * - invoice_date
	 * - customer_number
	 * - due_date
	 *
	 * @return array
	 */
	public function getRechnungsdaten(): array {
		/* addressdata */
		$name_verein = trim((string) $this->getAddress('name_verein', ''));
		$bic_verein = trim((string) $this->getAddress('bic_verein', ''));
		$iban_verein = trim((string) $this->getAddress('iban_verein', ''));
		$bank_verein = trim((string) $this->getAddress('bank_verein', ''));
		/* formdata */
		$zahlungsfrist = trim((string) $this->getForm('zahlungsfrist', ''));
		$rechnungsnummer = trim((string) $this->getForm('rechnungsnummer', ''));
		$brutto = $this->getForm('brutto', 0.0);

		/* Fotoueberweisung */
		/* https://de.wikipedia.org/wiki/EPC-QR-Code */
		$qr_content = "BCD" . PHP_EOL;
		$qr_content .= "002" . PHP_EOL;
		$qr_content .= "1" . PHP_EOL;
		$qr_content .= "SCT" . PHP_EOL;
		$qr_content .= $bic_verein . PHP_EOL;
		$qr_content .= $name_verein . PHP_EOL;
		$qr_content .= $iban_verein . PHP_EOL;
		$qr_content .= "EUR" . number_format ( $brutto, 2, '.', '' ) . PHP_EOL;
		$qr_content .= PHP_EOL;
		$qr_content .= PHP_EOL;
		$qr_content .= $rechnungsnummer . PHP_EOL;
		$qr_content .= PHP_EOL;

		return array(
			'qr_content' => $qr_content,
			'name_verein' => $name_verein,
			'bic_verein' => $bic_verein,
			'iban_verein' => $iban_verein,
			'bank_verein' => $bank_verein,
			'zahlungsfrist' => $zahlungsfrist,
			'rechnungsnummer' => $rechnungsnummer,
			'brutto' => number_format ( $brutto, 2, ',', '' ) . ' €',
		);
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
		$data = $rows ?? $this->getRechnungsdaten();
		$out = $this->graph->getStartTransform();
		$out .= $this->color->getPdfColor('#f1f1f1');
		$out .= $this->graph->getRect($x, $y, $width, $height, 'F');

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

		$innerX = $x + 1.0;
		$innerW = max(0.0, $width - 2.0);
		$rowH = 4.0;

		$labelFont = $this->font->insert($this->pon, 'helvetica', '', 11);
		$valueFont = $this->font->insert($this->pon, 'helvetica', '', 11);

		$cursorY = $y + 1.0;;



		$dataRows = [
			['label' => 'IBAN:', 'value' => $data['iban_verein'], 'w_korrektur' => -0.5],
			['label' => 'BIC:', 'value' => $data['bic_verein'], 'w_korrektur' => 0.0],
			['label' => 'Rechnungsbetrag:', 'value' => $data['brutto'], 'w_korrektur' => 0.0],
			['label' => 'Zahlungsfrist:', 'value' => $data['zahlungsfrist'], 'w_korrektur' => 0.0],
			['label' => 'Verwendungszweck:', 'value' => $data['rechnungsnummer'], 'w_korrektur' => 0.0],

		];


		foreach ($dataRows as $row) {

			$out .= $labelFont['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $row['label'],
				posx: $innerX,
				posy: $cursorY,
				width: $innerW,
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
				posx: $innerX,
				posy: $cursorY,
				width: $innerW + $row['w_korrektur'],
				height: $rowH,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Right,
			);

			$cursorY += $rowH*1.1;
		}

		$qrContent = (string) ($data['qr_content'] ?? '');
		if ($qrContent !== '') {
			$qrX = $x - $height - 4.0;
			$qrY = $y;
			$out .= $this->getBarcode(
				type: 'QRCODE,M',
				code: $qrContent,
				posx: $qrX,
				posy: $qrY,
				width: (int) $height,
				height: (int) $height,
				padding: [0, 0, 0, 0],
				style: [],
			);

			$qrCaptionFont = $this->font->insert($this->pon, 'helvetica', '', 8);
			$out .= $qrCaptionFont['out'];
			$out .= $this->color->getPdfColor('#333333');
			$out .= $this->getTextCell(
				txt: 'Fotoüberweisung',
				posx: $qrX,
				posy: $qrY + $height + 0.6,
				width: $height,
				height: 3.0,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				drawcell: false,
			);
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

	public function generiere_Zeile(
		float $y,
		float $w1,
		float $w2,
		float $w3,
		float $w4,
		string $t1,
		string $t2,
		string $t3,
		string $t4,
		int $grey,
	): string {
		$x = 25.0; // Rand nach DIN 5008 Typ B
		$rowHeight = 4.0;

		$grey = max(0, min(255, $grey));
		$greyHex = sprintf('#%02x%02x%02x', $grey, $grey, $grey);

		$cells = [
			['w' => $w1, 'txt' => $t1, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w2, 'txt' => $t2, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w3, 'txt' => $t3, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Right],
			['w' => $w4, 'txt' => $t4, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Right],
		];

		$out = $this->graph->getStartTransform();
		$cursorX = $x;

		foreach ($cells as $cell) {
			$cellW = (float) $cell['w'];
			if ($cellW <= 0.0) {
				continue;
			}

			$out .= $this->color->getPdfColor($greyHex);
			$out .= $this->graph->getRect($cursorX, $y, $cellW, $rowHeight, 'F');

			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: (string) $cell['txt'],
				posx: $cursorX,
				posy: $y,
				width: $cellW,
				height: $rowHeight,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: $cell['halign'],
				drawcell: false,
			);

			$cursorX += $cellW;
		}

		$out .= $this->graph->getStopTransform();
		return $out;
	}

	public function add_Zeile(
		float $y,
		float $w1,
		float $w2,
		float $w3,
		float $w4,
		string $t1,
		string $t2,
		string $t3,
		string $t4,
		int $grey,
	): void {
		$this->page->addContent($this->generiere_Zeile($y, $w1, $w2, $w3, $w4, $t1, $t2, $t3, $t4, $grey));
	}




}

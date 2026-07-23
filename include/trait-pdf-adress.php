<?php
/**
 * PDF Address Field Trait.
 *
 * Provides a reusable address-window renderer for tc-lib-pdf templates.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait for rendering a DIN-style address field.
 */
trait PdfAdressTrait {
	/** Default X position for the address field (mm). */
	private const ADDRESS_FIELD_X = 25.0;

	/** Default Y position for the address field (mm). */
	private const ADDRESS_FIELD_Y = 50.0;

	/** Default width of the address field (mm). */
	private const ADDRESS_FIELD_W = 80.0;

	/** Default height of the address field (mm). */
	private const ADDRESS_FIELD_H = 40.0;

	/**
	 * Build address lines from addressdata.
	 *
	 * Recognized keys:
	 * - company
	 * - name
	 * - street
	 * - zip
	 * - city
	 * - country
	 *
	 * @return array<int, string>
	 */
	public function getAdressFieldLines(): array {
		$last_name = trim((string) $this->getForm('last_name', ''));
        $first_name = trim((string) $this->getForm('first_name', ''));
		$street = trim((string) $this->getForm('street', ''));
		$zip = trim((string) $this->getForm('zip', ''));
		$city = trim((string) $this->getForm('city', ''));
		$email = trim((string) $this->getForm('email', ''));

        $name = trim($first_name . ($first_name !== '' && $last_name !== '' ? ' ' : '') . $last_name);
		$cityLine = trim($zip . ($zip !== '' && $city !== '' ? ' ' : '') . $city);

		$lines = [];
		foreach ([$email, $name, $street, $cityLine] as $line) {
			if ($line !== '') {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	public function getAdressFieldLinesBack(): array {
		$sender = trim((string) $this->getForm('sender', ''));
        $returnme = trim((string) $this->getForm('returnme', ''));

		$lines = [];
		foreach ([$sender, $returnme] as $line) {
			if ($line !== '') {
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * Render the address field and return raw PDF output.
	 *
	 * @param float     $x         Left position in mm.
	 * @param float     $y         Top position in mm.
	 * @param float     $width     Field width in mm.
	 * @param float     $height    Field height in mm.
	 * @param bool      $drawFrame Whether to draw a visible frame.
	 * @param ?string[] $lines     Optional custom lines. Null uses addressdata.
	 *
	 * @return string Raw PDF stream for the address field.
	 */
	public function generate_adress_field(
		float $x = self::ADDRESS_FIELD_X,
		float $y = self::ADDRESS_FIELD_Y,
		float $width = self::ADDRESS_FIELD_W,
		float $height = self::ADDRESS_FIELD_H,
		bool $drawFrame = false,
		?array $lines = null,
	): string {
		$backLines = $this->getAdressFieldLinesBack();
		$addressLines = $lines ?? $this->getAdressFieldLines();
		$backText = implode("\n", $backLines);

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

		$backBlockH = 0.0;
		if ($backText !== '') {
			$backFont = $this->font->insert($this->pon, 'helvetica', 'B', 6);
			$out .= $backFont['out'];
			//$out .= $this->color->getPdfColor('#666666');
			$backBlockH = min($innerH, (count($backLines) * 3.0) + 0.5);
			$out .= $this->getTextCell(
				txt: $backText,
				posx: $innerX,
				posy: $innerY,
				width: $innerW,
				height: $backBlockH,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
		}

		if ($addressLines !== []) {
			$gap = $backBlockH > 0.0 ? 5.0 : 2.0;
			$addressY = $innerY + $backBlockH + $gap;
			$addressH = max(0.0, $innerH - $backBlockH - $gap);
			$lineH = 4.0;
			$maxY = $addressY + $addressH;
			$cursorY = $addressY;

			$addressFont = $this->font->insert($this->pon, 'helvetica', '', 11);
			$out .= $addressFont['out'];

			foreach ($addressLines as $line) {
				if ($cursorY + $lineH > $maxY) {
					break;
				}

				$addressColor = str_contains((string) $line, '@') ? '#666666' : '#000000';
				$out .= $this->color->getPdfColor($addressColor);
				$out .= $this->getTextCell(
					txt: (string) $line,
					posx: $innerX,
					posy: $cursorY,
					width: $innerW,
					height: $lineH,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Top,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				);

				$cursorY += $lineH*1.1;
			}
		}

		$out .= $this->graph->getStopTransform();
        return $out;
	}

	/**
	 * Draw Address field directly on the current page.
	 *
	 * @param float     $x         Left position in mm.
	 * @param float     $y         Top position in mm.
	 * @param float     $width     Field width in mm.
	 * @param float     $height    Field height in mm.
	 * @param bool      $drawFrame Whether to draw a visible frame.
	 * @param ?string[] $lines     Optional custom lines. Null uses addressdata.
	 *
	 * @return void
	 */
	public function add_adress_field(
		float $x = self::ADDRESS_FIELD_X,
		float $y = self::ADDRESS_FIELD_Y,
		float $width = self::ADDRESS_FIELD_W,
		float $height = self::ADDRESS_FIELD_H,
		bool $drawFrame = false,
		?array $lines = null,
	): void {
        $this->page->addContent($this->generate_adress_field($x, $y, $width, $height, $drawFrame, $lines));
	}

}

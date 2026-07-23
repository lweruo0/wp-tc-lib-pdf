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
	private const ADDRESS_FIELD_X = 20.0;

	/** Default Y position for the address field (mm). */
	private const ADDRESS_FIELD_Y = 45.0;

	/** Default width of the address field (mm). */
	private const ADDRESS_FIELD_W = 85.0;

	/** Default height of the address field (mm). */
	private const ADDRESS_FIELD_H = 45.0;

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
	 * @return void
	 */
	public function generate_adress_field(
		float $x = self::ADDRESS_FIELD_X,
		float $y = self::ADDRESS_FIELD_Y,
		float $width = self::ADDRESS_FIELD_W,
		float $height = self::ADDRESS_FIELD_H,
		bool $drawFrame = false,
		?array $lines = null,
	): void {
		$backLines = $this->getAdressFieldLinesBack();
		$addressLines = $lines ?? $this->getAdressFieldLines();
		$backText = implode("\n", $backLines);
		$addressText = implode("\n", $addressLines);

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
			$backFont = $this->font->insert($this->pon, 'helvetica', '', 8);
			$out .= $backFont['out'];
			$out .= $this->color->getPdfColor('#666666');
			$backBlockH = min($innerH, (count($backLines) * 3.0) + 0.5);
			$out .= $this->getTextCell(
				txt: $backText,
				posx: $innerX,
				posy: $innerY,
				width: $innerW,
				height: $backBlockH,
				offset: 0,
				linespace: 2,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
		}

		if ($addressText !== '') {
			$gap = $backBlockH > 0.0 ? 2.0 : 0.0;
			$addressY = $innerY + $backBlockH + $gap;
			$addressH = max(0.0, $innerH - $backBlockH - $gap);

			$addressFont = $this->font->insert($this->pon, 'helvetica', '', 12);
			$out .= $addressFont['out'];
			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $addressText,
				posx: $innerX,
				posy: $addressY,
				width: $innerW,
				height: $addressH,
				offset: 0,
				linespace: 4,
				valign: \Com\Tecnick\Pdf\TextVAlign::Top,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
		}

		$out .= $this->graph->getStopTransform();
		$this->page->addContent($out);
	}
}

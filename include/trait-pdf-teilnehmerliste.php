<?php
/**
 * PDF Teilnehmerliste Trait.
 *
 * Provides reusable rendering for participant list blocks.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait for participant list rendering.
 *
 * @mixin PdfTemplate
 * @phpstan-require-extends PdfTemplate
 */
trait PdfTeilnehmerlisteTrait {

	/** Default Y position for participant list area (mm). */
	private const TEILNEHMERLISTE_Y = 71.0;

	/** Default width for participant list area (mm). */
	private const TEILNEHMERLISTE_W = 75.0;

	/** Default height for participant list area (mm). */
	private const TEILNEHMERLISTE_H = 24.0;

	public function generiere_Zeile8(
		float $x,
		float $y,
		float $h,
		float $w1,
		float $w2,
		float $w3,
		float $w4,
		float $w5,
		float $w6,
		float $w7,
		float $w8,
		string $t1,
		string $t2,
		string $t3,
		string $t4,
		string $t5,
		string $t6,
		string $t7,
		string $t8,
		int $grey,
		string $fontstyle = '',
	): string {

		$grey = max(0, min(255, $grey));
		$greyHex = sprintf('#%02x%02x%02x', $grey, $grey, $grey);

		$cells = [
			['w' => $w1, 'txt' => $t1, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w2, 'txt' => $t2, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w3, 'txt' => $t3, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w4, 'txt' => $t4, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w5, 'txt' => $t5, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w6, 'txt' => $t6, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w7, 'txt' => $t7, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
			['w' => $w8, 'txt' => $t8, 'halign' => \Com\Tecnick\Pdf\TextHAlign::Left],
		];

		$textFont = $this->font->insert($this->pon, 'helvetica', $fontstyle, 11);
		$out = $this->graph->getStartTransform();
		$out .= $textFont['out'];
		$cursorX = $x;

		// Draw cell backgrounds and text
		foreach ($cells as $cell) {
			$cellW = (float) $cell['w'];
			if ($cellW <= 0.0) {
				continue;
			}

			$innerX = $cursorX + 1.0;
			$innerW = max(0.0, $cellW - 2.0);

			// Draw cell background
			$out .= $this->color->getPdfColor($greyHex);
			$out .= $this->graph->getRect($cursorX, $y, $cellW, $h, 'F');

			// Draw cell text only if not empty
			$cellText = (string) $cell['txt'];
			$cellText = $cellText !== '' ? $cellText : ' ';

			$out .= $this->color->getPdfColor('#000000');
			$out .= $this->getTextCell(
				txt: $cellText,
				posx: $innerX,
				posy: $y,
				width: $innerW,
				height: $h,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: $cell['halign'],
				drawcell: false,
			);
	

			$cursorX += $cellW;
		}

		// Draw cell borders (after text to preserve line width)
		$out .= $this->color->getPdfColor('#000000');
		$cursorX = $x;
		foreach ($cells as $cell) {
			$cellW = (float) $cell['w'];
			if ($cellW <= 0.0) {
				continue;
			}

			$out .= $this->graph->getRect($cursorX, $y, $cellW, $h, 'S', ['lineWidth' => 0.5]);
			$cursorX += $cellW;
		}

		$out .= $this->graph->getStopTransform();
		return $out;
	}


	public function add_Zeile8(
		float $x,
		float $y,
		float $h,
		float $w1,
		float $w2,
		float $w3,
		float $w4,
		float $w5,
		float $w6,
		float $w7,
		float $w8,
		string $t1,
		string $t2,
		string $t3,
		string $t4,
		string $t5,
		string $t6,
		string $t7,
		string $t8,
		int $grey,
		string $fontstyle = '',
	): int {
		$this->page->addContent($this->generiere_Zeile8($x, $y, $h, $w1, $w2, $w3, $w4, $w5, $w6, $w7, $w8, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $grey, $fontstyle));
		return $y + $h;
	}


}

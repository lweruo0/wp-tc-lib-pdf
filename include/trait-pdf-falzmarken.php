<?php
/**
 * PDF Falzmarken Trait.
 *
 * Provides helper methods to draw DIN A4 fold marks (falz marks).
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait for fold mark rendering.
 */
trait PdfFalzmarkenTrait {
	/** X start position for left-side fold marks (mm). */
	private const FALZ_X_START = 0.0;

	/** X end position for left-side fold marks (mm). */
	private const FALZ_X_END = 7.0;

	/** Standard DIN A4 fold mark Y positions (mm from top). */
	private const FALZ_DEFAULT_Y = [105.0, 210.0];

	/**
	 * Build raw PDF output for fold marks.
	 *
	 * @param float[]|null $yPositions Optional custom Y positions in mm.
	 * @param string       $color      Line color in hex.
	 * @param float        $lineWidth  Line width in mm.
	 *
	 * @return string Raw PDF stream.
	 */
	public function generate_falzmarken(?array $yPositions = null, string $color = '#666666', float $lineWidth = 0.25): string {
		$marks = $yPositions ?? self::FALZ_DEFAULT_Y;

		$lineStyle = [
			'lineWidth' => $lineWidth,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => $color,
		];

		$out = $this->graph->getStartTransform();

		foreach ($marks as $y) {
			$posY = (float) $y;
			$out .= $this->graph->getLine(
				self::FALZ_X_START,
				$posY,
				self::FALZ_X_END,
				$posY,
				$lineStyle,
			);
		}

		$out .= $this->graph->getStopTransform();
		return $out;
	}

	/**
	 * Draw fold marks directly on the current page.
	 *
	 * @param float[]|null $yPositions Optional custom Y positions in mm.
	 * @param string       $color      Line color in hex.
	 * @param float        $lineWidth  Line width in mm.
	 *
	 * @return void
	 */
	public function add_falzmarken_to_page(?array $yPositions = null, string $color = '#666666', float $lineWidth = 0.25): void {
		$this->page->addContent($this->generate_falzmarken($yPositions, $color, $lineWidth));
	}
}

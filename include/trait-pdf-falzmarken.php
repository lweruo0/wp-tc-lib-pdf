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
	 * Draw DIN letter helper guides on the current page.
	 *
	 * This is a tc-lib-pdf port of the old TCPDF helper layout.
	 *
	 * @return string Raw PDF stream to be added to the page content.
	 */
	public function generate_DIN_5008_helpers(): string {
		$pid = $this->page->getPageId();
		if ($pid < 0) {
			return '';
		}

		$style = [
			'lineWidth' => 0.5,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'miterLimit' => 10,
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#777777',
			'fillColor' => '#fff',
		];

		$out = $this->graph->getStartTransform();

		/* DIN 5008 Form B Anschrift */
		$out .= $this->graph->getRect(20.0, 45.0, 85.0, 45.0, 'D', [$style]);
		/* DIN 5008 Form B Absender */
		$out .= $this->graph->getRect(125.0, 50.0, 75.0, 40.0, 'D', [$style]);
		/* DIN 5008 Form B Textfeld */
		$out .= $this->graph->getRect(25.0, 98.46, 165.0, 160.0, 'D', [$style]);
		/* DIN 5008 Form B Firmenangaben */
		$out .= $this->graph->getRect(25.0, 265.0, 165.0, 25.0, 'D', [$style]);

		$out .= $this->graph->getStopTransform();
		return $out;
	}

	/**
	 * Draw fold marks directly on the current page.
	 *
	 * @return void
	 */
	public function add_DIN_5008_helpers(): void {
		$this->page->addContent($this->generate_DIN_5008_helpers());
	}

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
	public function add_falzmarken(?array $yPositions = null, string $color = '#666666', float $lineWidth = 0.25): void {
		$this->page->addContent($this->generate_falzmarken($yPositions, $color, $lineWidth));
	}
}

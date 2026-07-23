<?php
/**
 * PDF Absender Block Trait.
 *
 * Provides reusable rendering for a sender block.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait for sender block rendering.
 *
 * @mixin PdfTemplate
 * @phpstan-require-extends PdfTemplate
 */
trait PdfAbsenderTrait {
	/** Default X position for sender area (mm). */
	private const ABSENDER_X = 125.0;

	/** Default Y position for sender area (mm). */
	private const ABSENDER_Y = 50.0;

	/** Default width for sender area (mm). */
	private const ABSENDER_W = 85.0;

	/** Default height for sender area (mm). */
	private const ABSENDER_H = 40.0;

	/**
	 * Build sender lines from form data.
	 *
	 * Recognized keys:
	 * - sender_name
	 * - sender_street
	 * - sender_zip
	 * - sender_city
	 * - sender_phone
	 * - sender_email
	 *
	 * @return array<int, string>
	 */
	public function getAbsenderLines(): array {

		$name_verein = trim((string) $this->getAddress('name_verein', ''));
		$bic_verein = trim((string) $this->getAddress('bic_verein', ''));
		$iban_verein = trim((string) $this->getAddress('iban_verein', ''));
		$bank_verein = trim((string) $this->getAddress('bank_verein', ''));
		$ort_verein = trim((string) $this->getAddress('ort_verein', ''));
		$addr_verein = trim((string) $this->getAddress('addr_verein', ''));
		$email_verein = trim((string) $this->getAddress('email_verein', ''));

		$steuernummer_verein = trim((string) $this->getAddress('steuernummer_verein', ''));

		if ($steuernummer_verein !== '') {
			if (substr($steuernummer_verein, -2) == 'DE') {
				$steuernummer_verein = 'UST-ID-Nr. :' . $steuernummer_verein;
			} else {
				$steuernummer_verein = 'Steuernummer: ' . $steuernummer_verein;
			}
		}

		$lines = [];
		foreach ([$name_verein, $addr_verein, $ort_verein, $email_verein, $steuernummer_verein] as $line) {
			if ($line !== '') {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Render sender block and return raw PDF stream.
	 *
	 * @param float     $x         Left position in mm.
	 * @param float     $y         Top position in mm.
	 * @param float     $width     Block width in mm.
	 * @param float     $height    Block height in mm.
	 * @param bool      $drawFrame Whether to draw a visible frame.
	 * @param ?string[] $lines     Optional custom lines. Null uses form data.
	 *
	 * @return string
	 */
	public function generate_absender(
		float $x = self::ABSENDER_X,
		float $y = self::ABSENDER_Y,
		float $width = self::ABSENDER_W,
		float $height = self::ABSENDER_H,
		bool $drawFrame = false,
		?array $lines = null,
	): string {
		$senderLines = $lines ?? $this->getAbsenderLines();
		$text = implode("\n", $senderLines);

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

		$font = $this->font->insert($this->pon, 'helvetica', '', 11);
		$out .= $font['out'];
		$out .= $this->color->getPdfColor('#000000');
		$out .= $this->getTextCell(
			txt: $text,
			posx: $x,
			posy: $y,
			width: max(0.0, $width),
			height: max(0.0, $height),
			offset: 0,
			linespace: -0.2,
			valign: \Com\Tecnick\Pdf\TextVAlign::Top,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$out .= $this->graph->getStopTransform();
		return $out;
	}

	/**
	 * Draw sender block directly on the current page.
	 *
	 * @param float     $x         Left position in mm.
	 * @param float     $y         Top position in mm.
	 * @param float     $width     Block width in mm.
	 * @param float     $height    Block height in mm.
	 * @param bool      $drawFrame Whether to draw a visible frame.
	 * @param ?string[] $lines     Optional custom lines. Null uses form data.
	 *
	 * @return void
	 */
	public function add_absender(
		float $x = self::ABSENDER_X,
		float $y = self::ABSENDER_Y,
		float $width = self::ABSENDER_W,
		float $height = self::ABSENDER_H,
		bool $drawFrame = false,
		?array $lines = null,
	): void {
		$this->page->addContent($this->generate_absender($x, $y, $width, $height, $drawFrame, $lines));
	}

}

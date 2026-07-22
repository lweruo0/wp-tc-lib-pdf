<?php
/**
 * PDF Header Footer Trait.
 *
 * Provides header and footer functionality for PDF documents.
 * Can be combined with other traits via multiple inheritance simulation.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}


/**
 * Trait for header and footer functionality.
 */
trait PdfHeaderFooterTrait {
	/** Horizontal margin used by the header and footer bands (mm). */
	private const HF_MARGIN = 10.0;

	/** Height of the header band (mm). */
	private const HEADER_H = 12.0;

	/** Width reserved for the header logo (mm). */
	private const HEADER_LOGO_W = 24.0;

	/** Height of the footer band (mm). */
	private const FOOTER_H = 10.0;

	/** Document title shown left-aligned in the header. */
	private string $headerTitle = '';

	/** Subtitle / date shown right-aligned in the header. */
	private string $headerSubtitle = '';

	/** Cached image instance ID for the header logo. */
	private ?int $headerLogoImageId = null;

	/**
	 * Set the text displayed in the page header.
	 *
	 * @param string $title    Left-aligned title.
	 * @param string $subtitle Right-aligned subtitle (e.g. date or company name).
	 *
	 * @return void
	 */
	public function setHeaderText(string $title, string $subtitle = ''): void {
		$this->headerTitle = $title;
		$this->headerSubtitle = $subtitle;
	}

	/**
	 * Generates the repeating header and footer for every page.
	 *
	 * This method is called automatically by setPageContext() whenever a new
	 * page is added, provided enableDefaultPageContent(true) has been called.
	 *
	 * @param int $pid Page index (0-based).
	 *
	 * @return string Raw PDF stream prepended to the page content.
	 */
	public function defaultPageContent(int $pid = -1): string {
		if ($pid < 0) {
			$pid = $this->page->getPageId();
		}

		// Insert the default font once and cache it for subsequent pages.
		if (!isset($this->defaultfont)) {
			$this->defaultfont = $this->font->insert($this->pon, 'helvetica', '', 9);
		}

		$page = $this->page->getPage($pid);
		$pw = $page['width'];
		$ph = $page['height'];

		$lm = self::HF_MARGIN; // left margin x
		$rm = $pw - self::HF_MARGIN; // right margin x
		$tw = $pw - (2 * self::HF_MARGIN); // usable band width

		$lineStyle = [
			'lineWidth' => 0.25,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#555555',
		];

		$out = '';

		// ---- HEADER ------------------------------------------------

		$headerY = self::HF_MARGIN;
		$headerOut = $this->graph->getStartTransform();
		$headerOut .= $this->defaultfont['out'];
		$headerLogoLeft = $rm - self::HEADER_LOGO_W;

		// Title – left-aligned, bold
		if ($this->headerTitle !== '') {
			$bfontBold = $this->font->insert($this->pon, 'helvetica', 'B', 10);
			$headerOut .= $bfontBold['out'];
			$headerOut .= $this->color->getPdfColor('#1a3a6b');
			$headerOut .= $this->getTextCell(
				txt: $this->headerTitle,
				posx: $lm,
				posy: $headerY,
				width: $tw * 0.65,
				height: self::HEADER_H,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
			$headerOut .= $this->defaultfont['out'];
		}

		// Subtitle – right-aligned
		if ($this->headerSubtitle !== '') {
			$subtitleX = $lm + ($tw * 0.65);
			$subtitleW = max(0.0, $headerLogoLeft - 1.5 - $subtitleX);
			$headerOut .= $this->color->getPdfColor('#555555');
			if ($subtitleW > 0.0) {
				$headerOut .= $this->getTextCell(
					txt: $this->headerSubtitle,
					posx: $subtitleX,
					posy: $headerY,
					width: $subtitleW,
					height: self::HEADER_H,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Center,
					halign: \Com\Tecnick\Pdf\TextHAlign::Right,
				);
			}
		}

		$headerLogoFile = __DIR__ . '/images/logo.png';
		if (is_file($headerLogoFile)) {
			if ($this->headerLogoImageId === null) {
				$this->headerLogoImageId = $this->image->add($headerLogoFile);
			}

			$logoKey = $this->image->getKey($headerLogoFile);
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, self::HEADER_LOGO_W, self::HEADER_H - 2.0, true);
			$headerOut .= $this->image->getSetImage(
				$this->headerLogoImageId,
				$rm - $logoDim['width'],
				$headerY + ((self::HEADER_H - $logoDim['height']) / 2),
				$logoDim['width'],
				$logoDim['height'],
				$ph,
			);
		}

		// Separator line below the header
		$headerLineY = $headerY + self::HEADER_H;
		$headerOut .= $this->graph->getLine($lm, $headerLineY, $rm, $headerLineY, $lineStyle);
		$headerOut .= $this->graph->getStopTransform();

		$out .= $this->beginArtifact('Pagination', 'Header');
		$out .= $headerOut;
		$out .= $this->endArtifact();

		// ---- FOOTER ------------------------------------------------

		$footerLineY = $ph - self::HF_MARGIN - self::FOOTER_H;
		$footerOut = $this->graph->getStartTransform();
		$footerOut .= $this->defaultfont['out'];

		// Separator line above the footer
		$footerOut .= $this->graph->getLine($lm, $footerLineY, $rm, $footerLineY, $lineStyle);

		// Page number – centred
		$footerOut .= $this->color->getPdfColor('#555555');
		$footerOut .= $this->getTextCell(
			txt: 'Page ' . ($pid + 1),
			posx: $lm,
			posy: $footerLineY,
			width: $tw,
			height: self::FOOTER_H,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Center,
		);
		$footerOut .= $this->graph->getStopTransform();

		$out .= $this->beginArtifact('Pagination', 'Footer');
		$out .= $footerOut;
		$out .= $this->endArtifact();

		return $out;
	}
}

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
	private const HEADER_LOGO_W = 65;

	/** Maximum height reserved for the header logo (mm). */
	private const HEADER_LOGO_H = 28;

	/** Height of the footer band (mm). */
	private const FOOTER_H = 10.0;

	/** Document title shown left-aligned in the header. */
	private string $headerTitle = '';

	/** Subtitle / date shown right-aligned in the header. */
	private string $headerSubtitle = '';

	/** URL for the header logo link. */
	private string $headerUrl = '';

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
	public function setHeaderText(string $title, string $subtitle = '', string $url = ''): void {
		$this->headerTitle = $title;
		$this->headerSubtitle = $subtitle;
		$this->headerUrl = $url;
	}

	/**
	 * Draw DIN letter helper guides on the current page.
	 *
	 * This is a tc-lib-pdf port of the old TCPDF helper layout.
	 *
	 * @return void
	 */
	public function gen_hilfslinien(): void {
		$pid = $this->page->getPageId();
		if ($pid < 0) {
			return;
		}

		$style = [
			'lineWidth' => 0.5,
			'lineCap' => 'butt',
			'lineJoin' => 'miter',
			'miterLimit' => 10,
			'dashArray' => [],
			'dashPhase' => 0,
			'lineColor' => '#777777',
			'fillColor' => '#777777',
		];


		$rowHeight = 4.0;

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
		$this->page->addContent($out);
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
			$this->defaultfont = $this->font->insert($this->pon, 'helvetica', '', 12);
		}

		$this->gen_hilfslinien();

		$page = $this->page->getPage($pid);
		$pw = $page['width'];
		$ph = $page['height'];

		$lm = self::HF_MARGIN; // left margin x
		$rm = $pw - self::HF_MARGIN; // right margin x
		$tw = $pw - (2 * self::HF_MARGIN); // usable band width

		$out = '';

		// ---- HEADER ------------------------------------------------

		$headerY = self::HF_MARGIN;
		$headerTitleX = 20.0;
		$headerTitleW = $tw - ($headerTitleX - $lm);
		$headerOut = $this->graph->getStartTransform();
		$headerOut .= $this->defaultfont['out'];
		$headerLogoLeft = $rm - self::HEADER_LOGO_W;

		// Title – left-aligned, regular
		if ($this->headerTitle !== '') {
			$titleFont = $this->font->insert($this->pon, 'helvetica', '', 21);
			$headerOut .= $titleFont['out'];
			$headerOut .= $this->color->getPdfColor('#000');
			$headerOut .= $this->getTextCell(
				txt: $this->headerTitle,
				posx: $headerTitleX,
				posy: $headerY,
				width: $headerTitleW,
				height: self::HEADER_H,
				offset: 0,
				linespace: 0,
				valign: \Com\Tecnick\Pdf\TextVAlign::Center,
				halign: \Com\Tecnick\Pdf\TextHAlign::Left,
			);
			$headerOut .= $this->defaultfont['out'];
		}

		// Subtitle – below title, left-aligned
		if ($this->headerSubtitle !== '') {
			$subtitleX = $headerTitleX;
			$subtitleY = $headerY + self::HEADER_H;
			$subtitleW = max(0.0, $headerLogoLeft - 1.5 - $subtitleX);
			$subtitleH = 6.0;
			$headerOut .= $this->color->getPdfColor('#11417a');
			if ($subtitleW > 0.0) {
				$subtitleFont = $this->font->insert($this->pon, 'helvetica', '', 12);
				$headerOut .= $subtitleFont['out'];
				$linkTextW = min($subtitleW, $this->getStringWidth($this->headerSubtitle));
				if ($this->headerUrl !== '') {
					$linkTextW = max(0.0, $linkTextW);
				}

				$headerOut .= $this->getTextCell(
					txt: $this->headerSubtitle,
					posx: $subtitleX,
					posy: $subtitleY,
					width: $subtitleW,
					height: $subtitleH,
					offset: 0,
					linespace: 0,
					valign: \Com\Tecnick\Pdf\TextVAlign::Center,
					halign: \Com\Tecnick\Pdf\TextHAlign::Left,
				);

				if ($this->headerUrl !== '') {
					$underlineStyle = [
						'lineWidth' => 0.25,
						'lineCap' => 'butt',
						'lineJoin' => 'miter',
						'dashArray' => [],
						'dashPhase' => 0,
						'lineColor' => '#11417a',
					];
					if ($linkTextW > 0.0) {
						$underlineY = $subtitleY + $subtitleH - 0.8;
						$headerOut .= $this->graph->getLine(
							$subtitleX,
							$underlineY,
							$subtitleX + $linkTextW + 0.9,
							$underlineY,
							$underlineStyle,
						);
					}

					$headerOut .= $this->defaultfont['out'];
				}

				if ($this->headerUrl !== '') {
					$annotationId = $this->setLink(
						posx: $subtitleX,
						posy: $subtitleY,
						width: max(0.1, $linkTextW),
						height: $subtitleH,
						link: $this->headerUrl,
					);
					$this->page->addAnnotRef($annotationId);
				}
			}
		}

		$headerLogoFile = __DIR__ . '/images/logo_bfv2.png';
		if (is_file($headerLogoFile)) {
			if ($this->headerLogoImageId === null) {
				$this->headerLogoImageId = $this->image->add($headerLogoFile);
			}

			$logoKey = $this->image->getKey($headerLogoFile);
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, self::HEADER_LOGO_W, self::HEADER_LOGO_H, true);
			$headerOut .= $this->image->getSetImage(
				$this->headerLogoImageId,
				$rm - $logoDim['width'],
				$headerY + ((self::HEADER_LOGO_H - $logoDim['height']) / 2),
				$logoDim['width'],
				$logoDim['height'],
				$ph,
			);
		}

		$headerOut .= $this->graph->getStopTransform();

		$out .= $this->beginArtifact('Pagination', 'Header');
		$out .= $headerOut;
		$out .= $this->endArtifact();

		// ---- FOOTER ------------------------------------------------

		$footerLineY = $ph - self::HF_MARGIN - self::FOOTER_H;
		$footerOut = $this->graph->getStartTransform();
		$footerOut .= $this->defaultfont['out'];

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

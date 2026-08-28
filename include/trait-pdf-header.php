<?php
/**
 * PDF HeaderTrait.
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
trait PdfHeaderTrait {
	/** Horizontal margin used by the header and footer bands (mm). */
	private const HF_MARGIN = 10.0;

	/** Height of the header band (mm). */
	private const HEADER_H = 12.0;

	/** Width reserved for the header logo (mm). */
	private const HEADER_LOGO_W = 65;

	/** Document title shown left-aligned in the header. */
	private string $headerTitle = '';

	/** Subtitle / date shown right-aligned in the header. */
	private string $headerSubtitle = '';

	/** URL for the header logo link. */
	private string $headerUrl = '';

	/** Cached image instance ID for the header logo. */
	private ?int $headerLogoImageId = null;

	/** X-position of the header title. */
	private float $headerTitleX = 20.0;

	/** Y-position of the header title. */
	private float $headerTitleY = 10.0;

	/** X-position of the header subtitle. */
	private float $headerSubtitleX = 20.0;

	/** Y-position of the header subtitle. */
	private float $headerSubtitleY = 22.0;

	/** X-position of the header logo. Use a negative value to align it automatically to the right. */
	private float $headerLogoX = -1.0;

	/** Y-position of the header logo. */
	private float $headerLogoY = 10.0;

	/** Width of the header logo in mm. */
	private float $headerLogoWidth = 65.0;

	/** Maximum height of the header logo in mm. */
	private float $headerLogoHeight = 28.0;

	/** Optional explicit logo file path. */
	private string $headerLogoImage = '';

	/** Left-aligned copyright/info line shown in the footer. */
	private string $footerText = '';

	/** "Letzte Änderung"-Zeitstempel für die Fußzeile (leer = Erstellungszeit). */
	private string $footerModifiedTime = '';

	/** Y-position of the footer band measured from the page bottom (mm). */
	private float $footerMarginBottom = 15.0;

	/** Font size for footer text (pt). */
	private float $footerFontSize = 8.0;

	/** Whether the footer is rendered on each page. */
	private bool $footerEnabled = true;

	public function enableFooter(bool $enabled): void {
		$this->footerEnabled = $enabled;
	}

	/**
	 * Set the static left-aligned text shown in every page footer.
	 * Leave empty to use the default BFV copyright line.
	 */
	public function setFooterText(string $text): void {
		$this->footerText = $text;
	}

	/**
	 * Set the "last modified" timestamp shown in the footer.
	 * Pass an empty string to show the current generation time instead.
	 */
	public function setFooterModifiedTime(string $dateTimeString): void {
		if ($dateTimeString === '') {
			$this->footerModifiedTime = '';
		} else {
			$tz = get_option('timezone_string');
			date_default_timezone_set($tz !== '' ? $tz : 'Europe/Berlin');
			$this->footerModifiedTime = date('d.m.Y \u\m H:i', strtotime($dateTimeString));
		}
	}

	/**
	 * Set the distance of the footer band from the bottom of the page (mm).
	 */
	public function setFooterMarginBottom(float $mm): void {
		$this->footerMarginBottom = max(0.0, $mm);
	}

	public function generate_footer(int $pid): string {
		$page = $this->page->getPage($pid);
		$pw   = $page['width'];
		$ph   = $page['height'];

		$lm = self::HF_MARGIN;
		$rm = $pw - self::HF_MARGIN;
		$tw = $pw - (2 * self::HF_MARGIN);
		$fh = 5.0; // band height
		$fy = $ph - $this->footerMarginBottom;

		$footerFont = $this->font->insert($this->pon, 'helvetica', 'I', $this->footerFontSize);

		$out = $this->graph->getStartTransform();
		$out .= $footerFont['out'];
		$out .= $this->color->getPdfColor('#000000');

		// build left text
		if ($this->footerText !== '') {
			$leftText = $this->footerText;
		} else {
			$tz = get_option('timezone_string');
			date_default_timezone_set($tz !== '' ? $tz : 'Europe/Berlin');
			if ($this->footerModifiedTime !== '') {
				$leftText = '© Bezirksfischerei-Verein e.V. Ehingen/Donau (Letzte Änderung am ' . $this->footerModifiedTime . ')';
			} else {
				$leftText = '© Bezirksfischerei-Verein e.V. Ehingen/Donau (Erstellt am ' . date('d.m.Y \u\m H:i', time()) . ')';
			}
		}

		// page number: current page only; total page count is not reliably known while
		// the repeating page content is generated, so it must not be displayed here.
		$currentPage = $this->page->getPageID() + 1;
		$pageNum     = (string) $currentPage;

		$pageNumW = max(20.0, $this->getStringWidth($pageNum) + 4.0);
		$leftW    = $tw - $pageNumW;

		$out .= $this->getTextCell(
			txt: $leftText,
			posx: $lm,
			posy: $fy,
			width: $leftW,
			height: $fh,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Left,
		);

		$out .= $this->getTextCell(
			txt: 'Seite ' . $pageNum,
			posx: $rm - $pageNumW,
			posy: $fy,
			width: $pageNumW,
			height: $fh,
			offset: 0,
			linespace: 0,
			valign: \Com\Tecnick\Pdf\TextVAlign::Center,
			halign: \Com\Tecnick\Pdf\TextHAlign::Right,
		);

		$out .= $this->defaultfont['out'];
		$out .= $this->graph->getStopTransform();
		return $out;
	}

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
	 * Configure the title position in the header.
	 */
	public function setHeaderTitlePosition(float $x, float $y): void {
		$this->headerTitleX = $x;
		$this->headerTitleY = $y;
	}

	/**
	 * Configure the subtitle position in the header.
	 */
	public function setHeaderSubtitlePosition(float $x, float $y): void {
		$this->headerSubtitleX = $x;
		$this->headerSubtitleY = $y;
	}

	/**
	 * Configure the logo position in the header.
	 */
	public function setHeaderLogoPosition(float $x, float $y): void {
		$this->headerLogoX = $x;
		$this->headerLogoY = $y;
	}

	/**
	 * Configure the logo width in the header.
	 */
	public function setHeaderLogoWidth(float $width): void {
		$this->headerLogoWidth = max(0.0, $width);
	}

	/**
	 * Configure the logo image file used in the header.
	 */
	public function setHeaderLogoImage(string $file): void {
		$this->headerLogoImage = $file;
	}

	public function generate_header(int $pid) {

		$page = $this->page->getPage($pid);
		$pw = $page['width'];
		$ph = $page['height'];

		$lm = self::HF_MARGIN; // left margin x
		$rm = $pw - self::HF_MARGIN; // right margin x
		$tw = $pw - (2 * self::HF_MARGIN); // usable band width


		$headerY = self::HF_MARGIN;
		$headerTitleX = $this->headerTitleX;
		$headerTitleW = $tw - max(0.0, $headerTitleX - $lm);
		$headerOut = $this->graph->getStartTransform();
		$headerOut .= $this->defaultfont['out'];
		$headerLogoLeft = $rm - $this->headerLogoWidth;

		// Title – left-aligned, regular
		if ($this->headerTitle !== '') {
			$titleFont = $this->font->insert($this->pon, 'helvetica', '', 21);
			$headerOut .= $titleFont['out'];
			$headerOut .= $this->color->getPdfColor('#000');
			$headerOut .= $this->getTextCell(
				txt: $this->headerTitle,
				posx: $headerTitleX,
				posy: $this->headerTitleY,
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
			$subtitleX = $this->headerSubtitleX;
			$subtitleY = $this->headerSubtitleY;
			$subtitleW = max(0.0, $headerLogoLeft - 1.5 - $subtitleX);
			$subtitleH = 6.0;
			$headerOut .= $this->color->getPdfColor('#11417a');
			if ($subtitleW > 0.0) {
				$subtitleFont = $this->font->insert($this->pon, 'helvetica', '', 11);
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

		$headerLogoFile = $this->headerLogoImage !== '' ? $this->headerLogoImage : __DIR__ . '/images/logo_bfv2.png';
		if (is_file($headerLogoFile)) {
			if ($this->headerLogoImageId === null) {
				$this->headerLogoImageId = $this->image->add($headerLogoFile);
			}

			$logoKey = $this->image->getKey($headerLogoFile);
			$logoW = $this->headerLogoWidth > 0.0 ? $this->headerLogoWidth : self::HEADER_LOGO_W;
			$logoDim = $this->image->getImageDimensionsByKey($logoKey, $logoW, $this->headerLogoHeight, true);
			$logoX = $this->headerLogoX >= 0.0 ? $this->headerLogoX : $rm - $logoDim['width'];
			$headerOut .= $this->image->getSetImage(
				$this->headerLogoImageId,
				$logoX,
				$this->headerLogoY,
				$logoDim['width'],
				$logoDim['height'],
				$ph,
			);
		}

		$headerOut .= $this->graph->getStopTransform();	
		return $headerOut;
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

		$out = '';
		// ---- HEADER ------------------------------------------------
		$out .= $this->beginArtifact('Pagination', 'Header');
		$out .= $this->generate_header($pid);
		$out .= $this->endArtifact();

		// ---- FOOTER ------------------------------------------------
		if ($this->footerEnabled) {
			$out .= $this->beginArtifact('Pagination', 'Footer');
			$out .= $this->generate_footer($pid);
			$out .= $this->endArtifact();
		}

		return $out;
	}
}

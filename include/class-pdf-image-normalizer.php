<?php
/**
 * Bildaufbereitung für eingebettete Fotos.
 *
 * Dreht Fotos nach ihrer EXIF-Ausrichtung und verkleinert sie auf eine für
 * A4 ausreichende Auflösung, bevor sie ins PDF eingebettet werden. Ohne die
 * PHP-Erweiterung gd (oder bei Fehlern) werden die Originaldaten
 * unverändert zurückgegeben, das PDF entsteht also in jedem Fall.
 *
 * Diese Datei ist Teil der plattformneutralen Engine und darf keine
 * WordPress- oder Admidio-Funktionen aufrufen.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

/**
 * Fotos für die Einbettung ins PDF normalisieren.
 */
final class PdfImageNormalizer {

	/** Längste Kante in Pixeln. 2000 px entsprechen auf A4 (297 mm) rund 170 dpi. */
	public const MAX_PX = 2000;

	/** JPEG-Qualität beim Neukodieren (0–100). */
	public const JPEG_QUALITY = 80;

	/**
	 * Ist die Bildaufbereitung möglich?
	 *
	 * @return bool true, wenn gd mit JPEG-Unterstützung geladen ist.
	 */
	public static function isGdAvailable(): bool {
		return extension_loaded('gd')
			&& function_exists('imagecreatefromstring')
			&& function_exists('imagejpeg')
			&& (imagetypes() & IMG_JPG) !== 0;
	}

	/**
	 * Kann die EXIF-Ausrichtung gelesen werden?
	 *
	 * @return bool
	 */
	public static function isExifAvailable(): bool {
		return function_exists('exif_read_data');
	}

	/**
	 * Kurzbeschreibung des Zustands, z. B. für eine Admin-Anzeige.
	 *
	 * @return string
	 */
	public static function status(): string {
		$gd   = self::isGdAvailable() ? 'gd verfügbar' : 'gd FEHLT (Fotos werden unverändert eingebettet)';
		$exif = self::isExifAvailable() ? 'exif verfügbar' : 'exif FEHLT (Fotos werden nicht gedreht)';
		return $gd . ', ' . $exif;
	}

	/**
	 * Bilddaten für die Einbettung vorbereiten.
	 *
	 * Nur JPEG wird verarbeitet (Handyfotos). Andere Formate und JPEGs, die
	 * weder gedreht noch verkleinert werden müssen, bleiben unverändert.
	 *
	 * @param string $data Rohdaten der Bilddatei.
	 *
	 * @return string Bilddaten zum Einbetten (neu kodiertes JPEG oder Original).
	 */
	public static function normalize(string $data): string {
		if ($data === '' || !self::isGdAvailable()) {
			return $data;
		}

		$info = @getimagesizefromstring($data);
		if ($info === false || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
			return $data;
		}

		$width       = (int) $info[0];
		$height      = (int) $info[1];
		$orientation = self::readOrientation($data);
		$scale       = min(1.0, self::MAX_PX / max($width, $height, 1));

		if ($scale >= 1.0 && $orientation <= 1) {
			return $data;
		}

		if (!self::hasMemoryFor($width, $height, $scale)) {
			return $data;
		}

		$result = self::process($data, $width, $height, $scale, $orientation);
		if ($result === null) {
			return $data;
		}

		// Nur verkleinert, aber größer geworden (stark komprimiertes Original):
		// Original behalten.
		if ($orientation <= 1 && strlen($result) >= strlen($data)) {
			return $data;
		}

		return $result;
	}

	/**
	 * Skalieren, drehen/spiegeln, als JPEG kodieren.
	 *
	 * Erst verkleinern, dann drehen: das hält den Speicherbedarf der Drehung
	 * klein.
	 *
	 * @return string|null JPEG-Daten oder null bei einem Fehler.
	 */
	private static function process(string $data, int $width, int $height, float $scale, int $orientation): ?string {
		$image = @imagecreatefromstring($data);
		if ($image === false) {
			return null;
		}

		try {
			if ($scale < 1.0) {
				$newWidth  = max(1, (int) round($width * $scale));
				$newHeight = max(1, (int) round($height * $scale));
				$scaled    = imagecreatetruecolor($newWidth, $newHeight);
				if ($scaled === false || !imagecopyresampled($scaled, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
					return null;
				}
				imagedestroy($image);
				$image = $scaled;
			}

			$image = self::applyOrientation($image, $orientation);
			if ($image === null) {
				return null;
			}

			ob_start();
			$ok  = imagejpeg($image, null, self::JPEG_QUALITY);
			$out = (string) ob_get_clean();
			return ($ok && $out !== '') ? $out : null;
		} finally {
			if ($image instanceof \GdImage) {
				imagedestroy($image);
			}
		}
	}

	/**
	 * EXIF-Ausrichtung anwenden (Werte 2–8 nach EXIF-Standard).
	 *
	 * @param \GdImage $image
	 * @param int      $orientation
	 *
	 * @return \GdImage|null
	 */
	private static function applyOrientation(\GdImage $image, int $orientation): ?\GdImage {
		// imagerotate() dreht gegen den Uhrzeigersinn.
		$rotate = match ($orientation) {
			3, 4 => 180,
			5, 6 => -90,
			7, 8 => 90,
			default => 0,
		};
		if ($rotate !== 0) {
			$rotated = imagerotate($image, $rotate, 0);
			if ($rotated === false) {
				return null;
			}
			imagedestroy($image);
			$image = $rotated;
		}

		$flip = match ($orientation) {
			2, 5, 7 => IMG_FLIP_HORIZONTAL,
			4 => IMG_FLIP_VERTICAL,
			default => 0,
		};
		if ($flip !== 0 && function_exists('imageflip')) {
			imageflip($image, $flip);
		}

		return $image;
	}

	/**
	 * EXIF-Ausrichtung lesen.
	 *
	 * @return int 1 (normal) wenn unbekannt.
	 */
	private static function readOrientation(string $data): int {
		if (!self::isExifAvailable()) {
			return 1;
		}
		$exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($data));
		$value = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
		return ($value >= 1 && $value <= 8) ? $value : 1;
	}

	/**
	 * Reicht das memory_limit für Original + verkleinerte Kopie + Drehung?
	 *
	 * gd braucht je Pixel rund 5 Byte (truecolor + Overhead).
	 */
	private static function hasMemoryFor(int $width, int $height, float $scale): bool {
		$limit = self::memoryLimitBytes();
		if ($limit <= 0) {
			return true; // unbegrenzt
		}
		$pixels = $width * $height;
		$small  = $pixels * $scale * $scale;
		$needed = (int) (($pixels + 2 * $small) * 5 * 1.2);
		return memory_get_usage(true) + $needed < $limit;
	}

	/**
	 * memory_limit in Byte, -1/0 = unbegrenzt.
	 */
	private static function memoryLimitBytes(): int {
		$value = trim((string) ini_get('memory_limit'));
		if ($value === '' || $value === '-1') {
			return -1;
		}
		$number = (int) $value;
		switch (strtolower(substr($value, -1))) {
			case 'g':
				$number *= 1024;
				// no break
			case 'm':
				$number *= 1024;
				// no break
			case 'k':
				$number *= 1024;
		}
		return $number;
	}
}

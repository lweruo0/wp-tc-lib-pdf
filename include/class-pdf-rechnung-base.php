<?php
/**
 * Rechnungsbasis.
 *
 * Gemeinsame Grundlage aller Rechnungs- und Mahnungs-Templates. Enthält den
 * Teil, der in jedem Rechnungs-Template identisch ist: Trait-Satz, Aufbau der
 * Seite, Aufbereitung der Standardfelder und die Mahnungslogik.
 *
 * Ein konkretes Template implementiert nur noch loadData(): Rohdaten holen,
 * prepareRechnung() aufrufen, Rechnungsposten und Anschreibentext setzen,
 * Dateinamen setzen. Host-spezifische Aufrufe (WordPress-Optionen, Admidio-
 * Buchungen) gehören in das konkrete Template, nicht hierher.
 *
 * Diese Datei ist Teil der plattformneutralen Engine und darf keine
 * WordPress- oder Admidio-Funktionen aufrufen.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once __DIR__ . '/class-pdf-template.php';
require_once __DIR__ . '/trait-pdf-header-footer.php';
require_once __DIR__ . '/trait-pdf-adress.php';
require_once __DIR__ . '/trait-pdf-falzmarken.php';
require_once __DIR__ . '/trait-pdf-absender.php';
require_once __DIR__ . '/trait-pdf-rechnungsdaten.php';

/**
 * Abstrakte Basis für Rechnungen und Mahnungen im Briefformat.
 *
 * Erwartete Daten (siehe readme.md, Abschnitt Daten-Contract):
 *
 *   addressdata: name_verein, addr_verein, ort_verein, iban_verein, bic_verein,
 *                bank_verein, steuernummer_verein, optional sender, url_verein,
 *                header_name
 *   options:     mahngebuehr (float)
 *   formdata:    rechnungsnummer, brutto, netto, steuer, steuersatz,
 *                rechnung_vorname|first_name, rechnung_name|last_name,
 *                rechnung_strasse|street, rechnung_plz|zip, rechnung_ort|city,
 *                rechnung_email|email, rechnung_anrede, created_at,
 *                zahlungsfrist_original, rechnungsposten[]
 */
abstract class PdfRechnungBase extends PdfTemplate {
	use PdfHeaderFooterTrait;
	use PdfAdressTrait;
	use PdfFalzmarkenTrait;
	use PdfAbsenderTrait;
	use PdfRechnungsdatenTrait;

	/** Zeilenhöhe im Rechnungsblock (mm). Wird auch von PdfRechnungsdatenTrait verwendet. */
	protected const ROW_HEIGHT = 6;

	/** Y-Position, ab der das Anschreiben beginnt (mm). */
	protected const ANSCHREIBEN_Y = 105.0;

	/** Standard-Zahlungsziel, wenn die Rohdaten keines liefern. */
	protected const ZAHLUNGSZIEL = '+7 days';

	/** Rücksendehinweis über dem Anschriftenfeld. */
	protected const RETURNME = 'falls unzustellbar, bitte zurück';

	/**
	 * Konstruktor.
	 *
	 * initializeUrlData() bleibt hier, bis die Registry die URL-Daten setzt
	 * (siehe KONZEPT-PLATTFORM.md, Phase 2).
	 */
	public function __construct() {
		parent::__construct();
		$this->enableDefaultPageContent(true);
		$this->initializeUrlData();
	}

	// -----------------------------------------------------------------------
	// Seitenaufbau
	// -----------------------------------------------------------------------

	/**
	 * Eine Rechnungsseite rendern.
	 *
	 * Templates mit mehreren Rechnungen in einem Dokument rufen
	 * renderRechnungSeite() je Datensatz auf und überschreiben render().
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->renderRechnungSeite();
	}

	/**
	 * Eine vollständige Rechnungsseite aus den aktuellen formdata aufbauen.
	 *
	 * @return float Y-Position unterhalb des Rechnungsblocks (mm).
	 */
	protected function renderRechnungSeite(): float {
		$title = (string) $this->getAddress('header_name', $this->getAddress('name_verein', ''));
		$url   = (string) $this->getAddress('url_verein', '');
		$this->setHeaderText($title, $url, $url);

		$this->addPage();
		$this->add_adress_field();
		$this->add_falzmarken();
		$this->add_absender();
		$this->add_rechnungsdaten();
		$y = $this->add_anschreiben_rechnung(static::ANSCHREIBEN_Y);
		$y += static::ROW_HEIGHT * 0.5;
		return $this->add_rechnung_block($y);
	}

	// -----------------------------------------------------------------------
	// Datenaufbereitung Rechnung
	// -----------------------------------------------------------------------

	/**
	 * Standardfelder einer Rechnung aus den Rohdaten ableiten.
	 *
	 * Setzt documenttype, Empfängerfelder, Rücksendehinweis, Absender,
	 * Rechnungsdatum, Zahlungsfrist, Steuer-Bezeichnung und text_below.
	 * Rechnungsposten und texts_before setzt das konkrete Template danach.
	 *
	 * Empfängerfelder werden bevorzugt aus den rechnung_*-Keys gelesen
	 * (WordPress-Formulare), sonst aus den neutralen Keys (first_name, …).
	 *
	 * @param array<string, mixed> $formdata     Rohdaten.
	 * @param string               $documenttype z. B. 'Rechnung Erlaubnisschein'.
	 *
	 * @return array<string, mixed> Aufbereitete formdata.
	 */
	protected function prepareRechnung(array $formdata, string $documenttype): array {
		$formdata['documenttype'] = $documenttype;

		$formdata['first_name'] = (string) ($formdata['rechnung_vorname'] ?? $formdata['first_name'] ?? '');
		$formdata['last_name']  = (string) ($formdata['rechnung_name']    ?? $formdata['last_name']  ?? '');
		$formdata['street']     = (string) ($formdata['rechnung_strasse'] ?? $formdata['street']     ?? '');
		$formdata['zip']        = (string) ($formdata['rechnung_plz']     ?? $formdata['zip']        ?? '');
		$formdata['city']       = (string) ($formdata['rechnung_ort']     ?? $formdata['city']       ?? '');
		$formdata['email']      = (string) ($formdata['rechnung_email']   ?? $formdata['email']      ?? '');

		// Die Traits lesen Anrede und Namen über die rechnung_*-Keys.
		$formdata['rechnung_vorname'] = $formdata['first_name'];
		$formdata['rechnung_name']    = $formdata['last_name'];

		$formdata['returnme'] = $formdata['returnme'] ?? static::RETURNME;

		$name = (string) $this->getAddress('name_verein', '');
		$addr = (string) $this->getAddress('addr_verein', '');
		$city = (string) $this->getAddress('ort_verein', '');
		$formdata['sender'] = $this->getAddress('sender', "$name, $addr, $city");

		$formdata['date'] = isset($formdata['created_at'])
			? date('d.m.Y', strtotime((string) $formdata['created_at']))
			: date('d.m.Y');

		$formdata['zahlungsfrist'] = $formdata['zahlungsfrist_original']
			?? date('d.m.Y', strtotime(static::ZAHLUNGSZIEL));

		if (($formdata['steuersatz'] ?? 0) > 0) {
			$formdata['bezeichnung_steuer'] = 'Umsatzsteuer ' . $formdata['steuersatz'] . '%';
		}

		$formdata['text_below'] = $this->rechnungTextBelow($formdata);

		return $formdata;
	}

	/**
	 * Text unter dem Rechnungsblock. Überschreiben für abweichende Hinweise.
	 *
	 * @param array<string, mixed> $formdata Aufbereitete formdata.
	 *
	 * @return string
	 */
	protected function rechnungTextBelow(array $formdata): string {
		$brutto = $this->formatBetrag((float) ($formdata['brutto'] ?? 0));
		$frist  = (string) ($formdata['zahlungsfrist'] ?? '');

		$text  = "Der Rechnungsbetrag von {$brutto} ist spätestens zum {$frist} fällig. ";
		$text .= "\nNach § 286 Abs. 3 BGB tritt Verzug auch ohne Mahnung ein, wenn die Zahlung nicht "
			. 'innerhalb von 30 Tagen erfolgt. Soweit nicht anders angegeben, entspricht das '
			. 'Rechnungsdatum dem Leistungsdatum.';

		return $text;
	}

	// -----------------------------------------------------------------------
	// Datenaufbereitung Mahnung
	// -----------------------------------------------------------------------

	/**
	 * Aus einer bereits aufbereiteten Rechnung eine Mahnung ableiten.
	 *
	 * Setzt documenttype, Briefdatum (heute), neue Zahlungsfrist (heute +
	 * ZAHLUNGSZIEL), Zahlungserinnerungs-Überschrift,
	 * Mahngebühr (einfach oder doppelt bei zweiter Mahnung, aus Option
	 * 'mahngebuehr'), erhöht brutto entsprechend und ersetzt texts_before
	 * und text_below.
	 *
	 * Ist der Bruttobetrag 0,00, bleiben die Daten unverändert; das Dokument
	 * wird dann als normale Rechnung gerendert (bisheriges Verhalten).
	 *
	 * @param array<string, mixed> $formdata        Aufbereitete Rechnungsdaten.
	 * @param string               $documenttype    z. B. 'Mahnung Erlaubnisschein'.
	 * @param bool|null            $zweiteMahnung   null = URL-Parameter 'zweitemahnung' auswerten.
	 * @param bool|null            $ohneMahngebuehr null = URL-Parameter 'ohnemahngebuehr' auswerten.
	 *
	 * @return array<string, mixed>
	 */
	protected function prepareMahnung(
		array $formdata,
		string $documenttype,
		?bool $zweiteMahnung = null,
		?bool $ohneMahngebuehr = null,
	): array {
		if ($this->formatBetrag((float) ($formdata['brutto'] ?? 0)) === $this->formatBetrag(0.0)) {
			return $formdata;
		}

		$zweiteMahnung   ??= ($this->getUrl('zweitemahnung', '') !== '');
		$ohneMahngebuehr ??= ($this->getUrl('ohnemahngebuehr', '') !== '');

		$formdata['documenttype']  = $documenttype;

		// Die Mahnung ist ein neues Schreiben: Briefdatum heute, neue Frist ab
		// heute. Die ursprüngliche Fälligkeit bleibt in zahlungsfrist_original
		// und erscheint im Anschreiben ("war am … fällig").
		$formdata['date']          = date('d.m.Y');
		$formdata['zahlungsfrist'] = date('d.m.Y', strtotime(static::ZAHLUNGSZIEL));

		$formdata['text_zahlungserinnerung'] = $zweiteMahnung ? '2. Zahlungserinnerung' : 'Zahlungserinnerung';

		$gebuehr = (float) $this->getOption('mahngebuehr', 0.0);
		if ($gebuehr < 0.005) {
			$ohneMahngebuehr = true;
		}

		if ($ohneMahngebuehr) {
			$formdata['mahngebuehr'] = 0.0;
		} else {
			$formdata['mahngebuehr'] = $zweiteMahnung ? $gebuehr * 2 : $gebuehr;
			$formdata['brutto'] = (float) ($formdata['brutto'] ?? 0) + $formdata['mahngebuehr'];
		}

		$formdata['texts_before'] = $this->mahnungTextsBefore($formdata);
		$formdata['text_below']   = $this->mahnungTextBelow($formdata);

		return $formdata;
	}

	/**
	 * Anschreiben der Mahnung (Zeilen vor dem Rechnungsblock).
	 *
	 * @param array<string, mixed> $formdata Aufbereitete Mahnungsdaten.
	 *
	 * @return array<int, string>
	 */
	protected function mahnungTextsBefore(array $formdata): array {
		$nr    = (string) ($formdata['rechnungsnummer'] ?? '');
		$frist = (string) ($formdata['zahlungsfrist_original'] ?? '');

		$texts = [
			"die Bezahlung der Rechnung Nr. {$nr} war am {$frist} fällig.",
			'Leider konnten wir bisher keinen Zahlungseingang verbuchen. ',
		];

		if ((float) ($formdata['mahngebuehr'] ?? 0) > 0.0) {
			$texts[] = 'Aufgrund der uns zusätzlich entstehenden Kosten und Aufwände sehen wir uns leider';
			$texts[] = 'gezwungen Mahngebühren in Rechnung zu stellen. Der offene Betrag setzt sich wie';
			$texts[] = 'folgt zusammen: ';
		}

		return $texts;
	}

	/**
	 * Text unter dem Rechnungsblock der Mahnung.
	 *
	 * @param array<string, mixed> $formdata Aufbereitete Mahnungsdaten.
	 *
	 * @return string
	 */
	protected function mahnungTextBelow(array $formdata): string {
		$brutto = $this->formatBetrag((float) ($formdata['brutto'] ?? 0));
		$frist  = (string) ($formdata['zahlungsfrist'] ?? '');

		$text  = "Der offene Betrag von {$brutto} ist spätestens zum {$frist} fällig.";
		$text .= "\nSollte bis dahin kein Zahlungseingang erfolgen, müssten wir weitere Schritte prüfen.";
		$text .= "\nFalls Sie die Zahlung bereits veranlasst haben, betrachten Sie dieses Schreiben bitte als gegenstandslos.";

		return $text;
	}

	// -----------------------------------------------------------------------
	// Hilfsfunktionen
	// -----------------------------------------------------------------------

	/**
	 * Betrag im deutschen Format mit Währungszeichen, z. B. "12,50 €".
	 *
	 * @param float $betrag
	 *
	 * @return string
	 */
	protected function formatBetrag(float $betrag): string {
		return number_format($betrag, 2, ',', '') . ' €';
	}

	/**
	 * Eine Zeile für formdata['rechnungsposten'] erzeugen.
	 *
	 * Preise dürfen als Zahl oder bereits formatierter Text übergeben werden;
	 * PdfRechnungsdatenTrait formatiert Zahlen beim Rendern.
	 *
	 * @param string           $bezeichnung
	 * @param string           $anzahl      z. B. '1x', '3 Tage', ''.
	 * @param float|string     $einzelpreis
	 * @param float|string     $gesamtpreis
	 *
	 * @return array{bezeichnung: string, anzahl: string, einzelpreis: float|string, gesamtpreis: float|string}
	 */
	protected function posten(string $bezeichnung, string $anzahl, float|string $einzelpreis, float|string $gesamtpreis): array {
		return [
			'bezeichnung' => $bezeichnung,
			'anzahl'      => $anzahl,
			'einzelpreis' => $einzelpreis,
			'gesamtpreis' => $gesamtpreis,
		];
	}
}

<?php
/**
 * Rechnungen des Admidio-Plugins Mitgliedsbeitrag (Beitrag und Arbeitsdienst).
 *
 * Die Daten liefert das Plugin über \Plugins\MembershipFee\classes\Invoices\InvoicePdfData::load($nr)
 * (neutrales Array, siehe dort). Das Template liest keine Tabellen des Plugins.
 *
 * Zahlart:
 *  - SEPA: kein Zahlungsziel, kein Überweisungsblock und kein QR-Code; der Text kündigt den Einzug an
 *    („Eine Überweisung ist nicht notwendig.“).
 *  - MAN:  Überweisungsblock mit Vereins-IBAN, Zahlungsfrist, Verwendungszweck und EPC-QR-Code.
 * Storniert: eigene Datei rechnung_<nr>_storniert.pdf mit Vermerk; die ausgestellte Datei bleibt.
 *
 * Siehe KONZEPT-ADMIDIO-RECHNUNGEN.md, Abschnitt 7, und docs/konzept-rechnungen-pdf.md des Plugins.
 *
 * @package WordPress Plugin Template/Admidio
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

require_once TC_LIB_PDF_DIR . '/include/class-pdf-rechnung-base.php';

/**
 * Beitragsrechnung (Nummernkreis B).
 */
class PdfAdmRechnungBeitrag extends PdfRechnungBase {

	/** Buchungskreis des Templates (mf = Mitgliedsbeitrag, ad = Arbeitsdienst). */
	protected const KIND = 'mf';

	/** Unterordner der Ablage. */
	protected const FOLDER = 'beitrag';

	/** @var array<string, mixed> Daten aus InvoicePdfData::load() */
	protected array $invoice = [];

	protected function loadData(): void {
		$nr = (string) $this->getUrl('nr', '');
		if (!preg_match('/^\d{4}-(B|AD)-\d{4,}$/', $nr)) {
			PdfRuntime::platform()->fail('Ungültige Rechnungsnummer', 404);
		}
		$data = $this->fetchInvoice($nr);
		if ($data === null || ($data['kind'] ?? '') !== static::KIND) {
			PdfRuntime::platform()->fail('Rechnung nicht gefunden', 404);
		}
		$this->invoice = $data;
		$cancelled     = ($data['status'] ?? '') === 'storniert';

		$platform = PdfRuntime::platform();
		$address  = method_exists($platform, 'getAddressData') ? $platform->getAddressData() : [];
		$creditor = (array) ($data['creditor'] ?? []);
		$address['iban_verein'] = (string) ($creditor['iban'] ?? '');
		$address['bic_verein']  = (string) ($creditor['bic'] ?? '');
		$address['bank_verein'] = (string) ($creditor['bank'] ?? '');
		$this->setAddressdata($address);

		$this->createStorageFolder(static::FOLDER);
		$this->setFileName('rechnung_' . $nr . ($cancelled ? '_storniert' : '') . '.pdf');

		$recipient = (array) ($data['recipient'] ?? []);
		$formdata  = [
			'first_name'      => (string) ($recipient['first_name'] ?? ''),
			'last_name'       => (string) ($recipient['last_name'] ?? ''),
			'street'          => (string) ($recipient['street'] ?? ''),
			'zip'             => (string) ($recipient['zip'] ?? ''),
			'city'            => (string) ($recipient['city'] ?? ''),
			// keine E-Mail-Adresse im Anschriftenfeld des Briefs
			'email'           => '',
			'rechnung_anrede' => (string) ($recipient['salutation'] ?? ''),
			'rechnungsnummer' => $nr,
			'created_at'      => (string) $data['invoice_date'] . ' 12:00:00',
		];
		if (!$this->isSepa() && (string) ($data['due_date'] ?? '') !== '') {
			$formdata['zahlungsfrist_original'] = $this->dmy((string) $data['due_date']);
		}

		$formdata = $this->prepareRechnung($formdata, $this->documentType());

		// Posten: stornierte Positionen mit Vermerk und 0,00 €
		$posten = [];
		$sum    = 0.0;
		foreach ((array) ($data['positions'] ?? []) as $position) {
			$label = (string) $position['label'];
			if (!empty($position['cancelled'])) {
				$posten[] = $this->posten($label . ' (storniert am ' . $this->dmy((string) $position['cancel_date']) . ')', '', '', 0.0);
				continue;
			}
			$amount   = round((float) $position['amount'], 2);
			$sum     += $amount;
			$posten[] = $this->posten($label, '', '', $amount);
		}
		$formdata['rechnungsposten'] = $posten;
		$formdata['netto']           = round($sum, 2);
		$formdata['brutto']          = round($sum, 2);
		$formdata['texts_before']    = $this->textsBefore();
		$formdata['text_below']      = $this->rechnungTextBelow($formdata);

		if ($cancelled) {
			$reason = trim((string) ($data['cancel_reason'] ?? ''));
			// kurzer Vermerk ueber der Rechnungsnummer (die Zeile teilt sich den Platz mit Ort und Datum)
			$formdata['text_zahlungserinnerung'] = 'STORNIERT';
			$formdata['text_below'] = 'Diese Rechnung wurde am ' . $this->dmy((string) $data['cancel_date']) . ' storniert'
				. ($reason !== '' ? ' (' . $reason . ')' : '') . '. Eine Zahlung ist nicht mehr erforderlich.';
		}

		$this->setFormdata($formdata);
	}

	/**
	 * Daten aus dem Plugin Mitgliedsbeitrag laden (im laufenden Request ist es schon geladen).
	 *
	 * @return array<string, mixed>|null
	 */
	protected function fetchInvoice(string $nr): ?array {
		$class = '\Plugins\MembershipFee\classes\Invoices\InvoicePdfData';
		if (!class_exists($class)) {
			$platform = PdfRuntime::platform();
			$dir      = method_exists($platform, 'getMembershipFeeDir') ? $platform->getMembershipFeeDir() : '';
			$common   = $dir . '/system/common_function.php';
			if ($dir === '' || !is_file($common)) {
				$platform->fail('Plugin Mitgliedsbeitrag nicht gefunden (membershipfee_dir in der config.php unter adm_my_files/plugins/wp-tc-lib-pdf prüfen)');
			}
			require_once $common;
		}
		if (!class_exists($class)) {
			PdfRuntime::platform()->fail('Plugin Mitgliedsbeitrag ohne Rechnungen (Version 6.5.0 oder neuer nötig)');
		}
		$data = $class::load($nr);
		return is_array($data) ? $data : null;
	}

	protected function isSepa(): bool {
		return ($this->invoice['method'] ?? '') === 'SEPA';
	}

	protected function documentType(): string {
		return 'Rechnung Mitgliedsbeitrag';
	}

	/**
	 * Einleitung über den Posten.
	 *
	 * @return array<int, string>
	 */
	protected function textsBefore(): array {
		$texts = [];
		if (!empty($this->invoice['admission'])) {
			$texts[] = 'für Ihre Aufnahme in den Verein berechnen wir Ihnen:';
		} else {
			$texts[] = 'für das Beitragsjahr ' . (int) $this->invoice['year'] . ' berechnen wir Ihnen:';
		}
		$memberNumber = trim((string) ($this->invoice['recipient']['member_number'] ?? ''));
		if ($memberNumber !== '') {
			array_unshift($texts, 'Mitgliedsnummer: ' . $memberNumber);
		}
		return $texts;
	}

	/**
	 * Text unter dem Rechnungsblock: Lastschrift-Ankündigung oder Zahlungsaufforderung.
	 *
	 * @param array<string, mixed> $formdata
	 */
	protected function rechnungTextBelow(array $formdata): string {
		$brutto = $this->formatBetrag((float) ($formdata['brutto'] ?? 0));
		if ($this->isSepa()) {
			$due      = (string) ($this->invoice['due_date'] ?? '');
			$when     = $due !== '' ? 'am ' . $this->dmy($due) : 'zum nächsten Einzugstermin';
			$iban     = (string) ($this->invoice['iban_masked'] ?? '');
			$mandate  = (string) ($this->invoice['mandate_id'] ?? '');
			$creditor = (string) ($this->invoice['creditor']['creditor_id'] ?? '');
			return "Der Rechnungsbetrag von {$brutto} wird {$when} per SEPA-Lastschrift"
				. ($iban !== '' ? " von Ihrem Konto {$iban}" : '') . ' eingezogen'
				. ($mandate !== '' ? "\n(Mandatsreferenz {$mandate}" . ($creditor !== '' ? ", Gläubiger-ID {$creditor}" : '') . ')' : '')
				. ".\nEine Überweisung ist nicht notwendig. Bitte sorgen Sie für ausreichende Deckung Ihres Kontos.";
		}
		$frist = (string) ($formdata['zahlungsfrist'] ?? '');
		return "Bitte überweisen Sie den Rechnungsbetrag von {$brutto} bis zum {$frist} unter Angabe der Rechnungsnummer "
			. 'auf das oben angegebene Konto.';
	}

	/**
	 * Lastschrift und Storno: ohne den Überweisungsblock oben rechts (IBAN, Frist, Verwendungszweck, QR-Code).
	 */
	protected function renderRechnungSeite(): float {
		if (!$this->isSepa() && ($this->invoice['status'] ?? '') !== 'storniert') {
			return parent::renderRechnungSeite();
		}
		$title = (string) $this->getAddress('header_name', $this->getAddress('name_verein', ''));
		$url   = (string) $this->getAddress('url_verein', '');
		$this->setHeaderText($title, $url, $url);

		$this->addPage();
		$this->add_adress_field();
		$this->add_falzmarken();
		$this->add_absender();
		$y = $this->add_anschreiben_rechnung(static::ANSCHREIBEN_Y);
		$y += static::ROW_HEIGHT * 0.5;
		return $this->add_rechnung_block($y);
	}

	protected function dmy(string $date): string {
		$value = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
		return $value ? $value->format('d.m.Y') : $date;
	}
}

/**
 * Rechnung Ersatzbeitrag Arbeitsdienst (Nummernkreis AD) mit Soll, Ist, Fehlstunden und Einsätzen.
 */
class PdfAdmRechnungArbeitsdienst extends PdfAdmRechnungBeitrag {

	protected const KIND = 'ad';

	protected const FOLDER = 'arbeitsdienst';

	/** Höchstzahl der aufgeführten Einsätze (eine Seite). */
	protected const MAX_ENTRIES = 12;

	protected function documentType(): string {
		return 'Rechnung Arbeitsdienst';
	}

	protected function textsBefore(): array {
		$texts = [];
		$memberNumber = trim((string) ($this->invoice['recipient']['member_number'] ?? ''));
		if ($memberNumber !== '') {
			$texts[] = 'Mitgliedsnummer: ' . $memberNumber;
		}
		$texts[] = 'für das Leistungsjahr ' . (int) $this->invoice['year'] . ' berechnen wir Ihnen den Ersatzbeitrag für nicht geleistete Arbeitsstunden:';
		return $texts;
	}

	protected function render(): void {
		$y    = $this->renderRechnungSeite();
		$work = $this->invoice['work'] ?? null;
		if (!is_array($work)) {
			return;
		}
		$hours = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',') . ' h';
		$y = $this->add_Zeile(25, $y, static::ROW_HEIGHT, 75, 30, 30, 30, 'Arbeitsdienst ' . (int) $this->invoice['year'], 'Soll', 'Ist', 'Fehlstunden', 230);
		$y = $this->add_Zeile(25, $y, static::ROW_HEIGHT, 75, 30, 30, 30, 'Stundensatz ' . $this->formatBetrag((float) $work['rate']),
			$hours((float) $work['target']), $hours((float) $work['done']), $hours((float) $work['missing']), 255);

		$entries = (array) ($work['entries'] ?? []);
		if ($entries === []) {
			return;
		}
		$y += static::ROW_HEIGHT * 0.5;
		$y = $this->add_Zeile(25, $y, static::ROW_HEIGHT, 30, 105, 0, 30, 'Datum', 'Einsatz', '', 'Stunden', 230);
		foreach (array_slice($entries, 0, static::MAX_ENTRIES) as $entry) {
			$y = $this->add_Zeile(25, $y, static::ROW_HEIGHT, 30, 105, 0, 30, $this->dmy((string) $entry['date']),
				mb_strimwidth((string) $entry['activity'], 0, 60, '…'), '', $hours((float) $entry['hours']), 255);
		}
		if (count($entries) > static::MAX_ENTRIES) {
			$this->add_Zeile(25, $y, static::ROW_HEIGHT, 30, 105, 0, 30, '', '… und ' . (count($entries) - static::MAX_ENTRIES) . ' weitere Einsätze', '', '', 255);
		}
	}
}

<?php
/**
 * Testdaten für tests/render-adm-rechnungen.php (erfundene Personen, erfundener Verein).
 *
 * @package WordPress Plugin Template/Tests
 */

$creditor = ['holder' => 'Muster-Angelverein e.V.', 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'bank' => 'Musterbank', 'creditor_id' => 'DE98ZZZ09999999999'];
$base = [
	'version' => 1, 'year' => 2026, 'invoice_date' => '2026-11-05', 'due_date' => '2026-12-01', 'open_amount' => 0.0,
	'mandate_id' => '', 'iban_masked' => '', 'status' => 'offen', 'cancel_date' => '', 'cancel_reason' => '',
	'dunning_date' => '', 'dunning_due' => '', 'admission' => false, 'creditor' => $creditor, 'work' => null,
	'recipient' => ['salutation' => 'Herr', 'first_name' => 'Max', 'last_name' => 'Mustermann', 'street' => 'Musterweg 7',
		'zip' => '12345', 'city' => 'Musterstadt', 'email' => 'max@example.org', 'member_number' => '745'],
];
$position = static fn (string $label, float $amount, string $status = 'offen', string $cancel = ''): array => [
	'label' => $label, 'text' => $label, 'amount' => $amount, 'status' => $status, 'cancelled' => $status === 'storniert',
	'cancel_date' => $cancel, 'manual' => false,
];

return [
	'verein' => [
		'name_verein' => 'Muster-Angelverein e.V.', 'header_name' => 'Muster-Angelverein e.V.', 'url_verein' => 'https://example.org',
		'addr_verein' => 'Postfach 123', 'ort_verein' => '12345 Musterstadt', 'ort_verein_kurz' => 'Musterstadt',
		'email_verein' => 'kasse@example.org',
	],
	'invoices' => [
		'2026-B-0001' => array_merge($base, ['invoice_no' => '2026-B-0001', 'kind' => 'mf', 'method' => 'SEPA', 'amount' => 330.0,
			'mandate_id' => 'BFV-0745', 'iban_masked' => 'DE02xxxxxxxxxxxxxx2051',
			'positions' => [$position('Aktiv', 310.0), $position('2. Angel', 20.0)]]),
		'2026-B-0002' => array_merge($base, ['invoice_no' => '2026-B-0002', 'kind' => 'mf', 'method' => 'MAN', 'amount' => 580.0, 'admission' => true,
			'recipient' => array_merge($base['recipient'], ['salutation' => '', 'first_name' => 'Erika', 'last_name' => 'Musterfrau']),
			'positions' => [$position('Bearbeitungsgebühr', 20.0), $position('Erstbesatz', 250.0), $position('Aktiv', 310.0)]]),
		'2026-B-0003' => array_merge($base, ['invoice_no' => '2026-B-0003', 'kind' => 'mf', 'method' => 'MAN', 'amount' => 310.0,
			'status' => 'storniert', 'cancel_date' => '2026-11-20', 'cancel_reason' => 'Korrektur',
			'positions' => [$position('Aktiv', 310.0, 'storniert', '2026-11-20')]]),
		'2025-AD-0001' => array_merge($base, ['invoice_no' => '2025-AD-0001', 'kind' => 'ad', 'year' => 2025, 'method' => 'SEPA', 'amount' => 120.0,
			'mandate_id' => 'BFV-0745', 'iban_masked' => 'DE02xxxxxxxxxxxxxx2051',
			'positions' => [$position('Ersatzleistung Arbeitsdienst', 120.0)],
			'work' => ['rate' => 20.0, 'done' => 4.0, 'missing' => 6.0, 'target' => 10.0, 'entries' => [
				['date' => '2025-04-12', 'activity' => 'Uferpflege Weiher', 'hours' => 3.0],
				['date' => '2025-09-20', 'activity' => 'Hüttenputz', 'hours' => 1.0],
			]]]),
	],
	'cases' => [
		'rechnung_beitrag_sepa'        => ['adm_rechnung_beitrag', '2026-B-0001'],
		'rechnung_beitrag_aufnahme'    => ['adm_rechnung_beitrag', '2026-B-0002'],
		'rechnung_beitrag_storniert'   => ['adm_rechnung_beitrag', '2026-B-0003'],
		'rechnung_arbeitsdienst_sepa'  => ['adm_rechnung_arbeitsdienst', '2025-AD-0001'],
	],
];

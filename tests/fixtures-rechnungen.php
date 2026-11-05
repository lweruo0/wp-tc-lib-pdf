<?php
/**
 * Testdaten für tests/render-rechnungen.php.
 *
 * Erfundene Personen und ein erfundener Verein. Werte so gewählt, dass jede
 * Rechnungsart ihre Besonderheiten zeigt (Mehrtagesschein, Steuer, Rabatt,
 * Versand, mehrere Steuersätze, Kostenberechnung Mitgliedsantrag).
 *
 * @package WordPress Plugin Template/Tests
 */

$verein = [
	'name_verein'          => 'Muster-Angelverein e.V.',
	'addr_verein'          => 'Postfach 123',
	'ort_verein'           => '12345 Musterstadt',
	'email_verein'         => 'kasse@example.org',
	'steuernummer_verein'  => '12345/67890',
	'iban_verein'          => 'DE02120300000000202051',
	'bic_verein'           => 'BYLADEM1001',
	'bank_verein'          => 'Musterbank',
	'name_1v'              => 'Anna Erste',
	'strasse_1v'           => 'Am Weiher 1',
	'ort_1v'               => '12345 Musterstadt',
	'tel_1v'               => '0123 456701',
	'name_2v'              => 'Bernd Zweiter',
	'strasse_2v'           => 'Am Weiher 2',
	'ort_2v'               => '12345 Musterstadt',
	'tel_2v'               => '0123 456702',
	'name_kassier'         => 'Carla Kasse',
	'strasse_kassier'      => 'Am Weiher 3',
	'ort_kassier'          => '12345 Musterstadt',
	'tel_kassier'          => '0123 456703',
	'name_schriftfuehrer'  => 'Dieter Schrift',
	'strasse_schriftfuehrer' => 'Am Weiher 4',
	'ort_schriftfuehrer'   => '12345 Musterstadt',
	'tel_schriftfuehrer'   => '0123 456704',
];

$empfaenger = [
	'rechnung_anrede'  => 'Herr',
	'rechnung_vorname' => 'Max',
	'rechnung_name'    => 'Mustermann',
	'rechnung_strasse' => 'Musterweg 7',
	'rechnung_plz'     => '12345',
	'rechnung_ort'     => 'Musterstadt',
	'rechnung_email'   => 'max@example.org',
];

return [
	// WordPress-Optionen, die die Templates per get_option() lesen.
	'options' => [
		'bfv_adressen'                       => $verein,
		'bfv_erlaubnisschein'                => ['mahngebuehr' => 3.5],
		'bfv_jubilaeumsruten'                => ['mahngebuehr' => 3.5],
		'bfv_vorbereitungslehrgang'          => ['mahngebuehr' => 5.0],
		'bfv_mitgliedsantrag_einstellungen'  => [
			'betrag_bearbeitungsgebuehr' => 20,
			'betrag_erstbesatz'          => 250,
			'betrag_aktiv'               => 310,
			'betrag_jugend'              => 40,
			'betrag_passiv'              => 50,
			'betrag_boot'                => 30,
			'betrag_2angel'              => 10,
		],
	],

	// Template-ID => [URL-Parameter, formdata].
	'cases' => [
		'rechnung_erlaubnis' => [
			['nr' => '2026-P-9001'],
			$empfaenger + [
				'rechnungsnummer'        => '2026-P-9001',
				// Quform speichert UTC: 30.06. 22:30 UTC = 01.07. 00:30 Ortszeit.
				// Die Rechnung muss den 01.07.2026 zeigen.
				'created_at'             => '2026-06-30 22:30:00',
				'zahlungsfrist_original' => '08.07.2026',
				'vorname'                => 'Max',
				'name'                   => 'Mustermann',
				'tage'                   => 2,
				'von'                    => '03.07.2026',
				'bis'                    => '04.07.2026',
				'einzel_preis_netto'     => 25.0,
				'preis_netto'            => 50.0,
				'netto'                  => 50.0,
				'brutto'                 => 50.0,
			],
		],
		'rechnung_huette' => [
			['nr' => '2026-H-9002'],
			$empfaenger + [
				'rechnungsnummer'        => '2026-H-9002',
				'created_at'             => '2026-04-04 18:30:00',
				'zahlungsfrist_original' => '25.04.2026',
				'von'                    => '2026-05-01T14:00:00+02:00',
				'bis'                    => '2026-05-01T23:00:00+02:00',
				'dauer'                  => 1,
				'rechnungsposten'        => [
					['bezeichnung' => 'Buchungspauschale kurz', 'anzahl' => '1', 'einzelpreis' => 126.05, 'gesamtpreis' => 126.05],
				],
				'netto'                  => 126.05,
				'steuersatz'             => 19,
				'steuer'                 => 23.95,
				'brutto'                 => 150.0,
			],
		],
		'rechnung_merchandise' => [
			['nr' => '2026-J-90003'],
			$empfaenger + [
				'rechnungsnummer'        => '2026-J-90003',
				// Winterzeit: 14.01. 23:30 UTC = 15.01. 00:30 Ortszeit.
				'created_at'             => '2026-01-14 23:30:00',
				'zahlungsfrist_original' => '05.02.2026',
				'rechnungsposten'        => [
					['bezeichnung' => 'Taschenmesser mit Gravur', 'anzahl' => '2', 'einzelpreis' => 29.41, 'gesamtpreis' => 58.82],
					['bezeichnung' => 'Vereinsmütze', 'anzahl' => '1', 'einzelpreis' => 12.61, 'gesamtpreis' => 12.61],
				],
				'rabattcode'             => 'JUBI',
				'rabatt_netto'           => 5.0,
				'preis_versand_netto'    => 4.2,
				'netto'                  => 70.63,
				'steuersatz'             => 19,
				'steuer'                 => 13.42,
				'brutto'                 => 84.05,
			],
		],
		'rechnung_vorbereitungslehrgang' => [
			['nr' => '2026-L-9004'],
			$empfaenger + [
				'rechnungsnummer'          => '2026-L-9004',
				'first_name'               => 'Max',
				'last_name'                => 'Mustermann',
				'created_at'               => '2026-08-13 20:15:00',
				'preis_theorie'            => 100.0,
				'preis_praxistag'          => 160.0,
				'preis_pruefungsgebuehr'   => 50.0,
				'preis_getraenkepauschale' => 7.0,
				'Essen1_bestellt'          => 1,
				'Essen1'                   => 'Mittagessen Samstag',
				'preis_Essen1'             => 12.5,
				'steuersatz_essen'         => 7,
				'steuersatz_getraenke'     => 19,
				'netto'                    => 345.0,
				'steuer'                   => 5.32,
				'brutto'                   => 350.5,
			],
		],
		'rechnung_antrag' => [
			['mnr' => '9005'],
			[
				'mitgliedsnummer' => '9005',
				'anrede'          => 'Frau',
				'vorname'         => 'Erika',
				'name'            => 'Musterfrau',
				'strasse'         => 'Seestraße 3',
				'plz'             => '12345',
				'ort'             => 'Musterstadt',
				'email'           => 'erika@example.org',
				'mitgliedschaft'  => 'Aktiv',
				'option_boot'     => 'Boot',
				'created_at'      => '2026-06-30 08:00:00',
			],
		],
	],

	// Mahnungen nutzen die Daten der zugehörigen Rechnung.
	'mahnungen' => [
		'mahnung_erlaubnis'    => 'rechnung_erlaubnis',
		'mahnung_huette'       => 'rechnung_huette',
		'mahnung_merchandise'  => 'rechnung_merchandise',
		'mahnung_antrag'       => 'rechnung_antrag',
	],
];

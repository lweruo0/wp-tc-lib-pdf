<?php
/**
 * Vorlage für die Konfiguration (nicht versioniert).
 *
 * Kopieren nach adm_my_files/plugins/wp-tc-lib-pdf/config.php und anpassen (Rechte 600 bzw. 640).
 * Der Ordner liegt im Datenordner von Admidio, ist per .htaccess gesperrt und bleibt bei Updates der
 * Bibliothek erhalten. Rückfall (veraltet): adm-include/config.php im Plugin-Ordner.
 * Die Datei enthält das Secret für signierte Links und darf nicht in das Repository.
 *
 * @package WordPress Plugin Template/Admidio
 */

return [
	// Geheimnis für signierte Links (mindestens 32 Zeichen), z. B. erzeugen mit
	//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
	// Ein neues Secret macht alle bisher verschickten Links ungültig.
	'secret' => '',

	// Ordner des Plugins Mitgliedsbeitrag unter adm_plugins (auf dem Server: MembershipFee)
	'membershipfee_dir' => 'MembershipFee',

	// Ablageordner der PDFs; leer = adm_my_files/plugins/wp-tc-lib-pdf
	'storage_dir' => '',

	// Vereinsdaten für Briefkopf, Absender und Fußzeile (Schlüssel wie bfv_adressen in WordPress).
	// Bankverbindung und Gläubiger-ID kommen aus den Einstellungen des Plugins Mitgliedsbeitrag.
	'verein' => [
		'name_verein'            => 'Bezirksfischerei-Verein e.V. Ehingen/Donau',
		'header_name'            => 'Bezirksfischerei-Verein e.V. Ehingen/Donau',
		'url_verein'             => 'https://bfv-ehingen.de',
		'addr_verein'            => '',
		'ort_verein'             => '',
		'ort_verein_kurz'        => 'Ehingen',
		'email_verein'           => '',
		'steuernummer_verein'    => '',
		'name_1v'                => '',
		'strasse_1v'             => '',
		'ort_1v'                 => '',
		'tel_1v'                 => '',
		'name_2v'                => '',
		'strasse_2v'             => '',
		'ort_2v'                 => '',
		'tel_2v'                 => '',
		'name_kassier'           => '',
		'strasse_kassier'        => '',
		'ort_kassier'            => '',
		'tel_kassier'            => '',
		'name_schriftfuehrer'    => '',
		'strasse_schriftfuehrer' => '',
		'ort_schriftfuehrer'     => '',
		'tel_schriftfuehrer'     => '',
	],
];

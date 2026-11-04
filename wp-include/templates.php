<?php
/**
 * Template-Registrierung für WordPress.
 *
 * Ordnet jeder Template-ID (URL-Parameter get_pdf) Klasse und Datei zu.
 * Die Dateien werden erst bei Bedarf von PdfRegistry::create() geladen.
 *
 * @package WordPress Plugin Template/WP
 */

defined('ABSPATH') || exit;

require_once TC_LIB_PDF_DIR . '/include/class-pdf-registry.php';

$tc_lib_pdf_templates = __DIR__ . '/templates';

// https://bfv-ehingen.de/?rechnung=2026-P-0148&key=31e86617aad37a3a3902a560e69c7e79
PdfRegistry::register('rechnung_erlaubnis', 'PdfRechnungErlaubnis', $tc_lib_pdf_templates . '/class-pdf-rechnung-erlaubnis.php');
// https://bfv-ehingen.de/?rechnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e
PdfRegistry::register('rechnung_merchandise', 'PdfRechnungMerchandise', $tc_lib_pdf_templates . '/class-pdf-rechnung-merchandise.php');
// https://bfv-ehingen.de/?rechnungantrag=244&key=45042c927a
PdfRegistry::register('rechnung_antrag', 'PdfRechnungMitgliedsantrag', $tc_lib_pdf_templates . '/class-pdf-rechnung-mitgliedsantrag.php');
PdfRegistry::register('rechnung_huette', 'PdfRechnungHuette', $tc_lib_pdf_templates . '/class-pdf-rechnung-huette.php');
PdfRegistry::register('rechnung_vorbereitungslehrgang', 'PdfRechnungVorbereitungsLehrgang2', $tc_lib_pdf_templates . '/class-pdf-rechnung-vorbereitungslehrgang.php');

// https://bfv-ehingen.de/?mahnung=2026-P-0147&key=68115147e855ecdcd7c9395b5bbe8ff9
PdfRegistry::register('mahnung_erlaubnis', 'PdfMahnungErlaubnis', $tc_lib_pdf_templates . '/class-pdf-rechnung-erlaubnis.php');
// https://bfv-ehingen.de/?mahnung_merchandise=2026-J-00001&key=eae261c669d3563987d2d449fe8a4b5e
PdfRegistry::register('mahnung_merchandise', 'PdfMahnungMerchandise', $tc_lib_pdf_templates . '/class-pdf-rechnung-merchandise.php');
// https://bfv-ehingen.de/?mahnung_antrag=244&key=45042c927a
PdfRegistry::register('mahnung_antrag', 'PdfMahnungMitgliedsantrag', $tc_lib_pdf_templates . '/class-pdf-rechnung-mitgliedsantrag.php');
PdfRegistry::register('mahnung_huette', 'PdfMahnungHuette', $tc_lib_pdf_templates . '/class-pdf-rechnung-huette.php');

// https://bfv-ehingen.de/?arbeitsdienstliste=1&dienst=17.01.2026&mgn=282&nonce=45042c927a
PdfRegistry::register('liste_arbeitsdienst', 'PdfListeArbeitsdienst', $tc_lib_pdf_templates . '/class-pdf-liste-arbeitsdienst.php');
// https://bfv-ehingen.de/?jugendteilnehmerliste=1&veranstaltung=12.09.2026&mgn=282&nonce=45042c927a
PdfRegistry::register('liste_jugendveranstaltung', 'PdfListeJugendveranstaltung', $tc_lib_pdf_templates . '/class-pdf-liste-jugendveranstaltung.php');

// https://bfv-ehingen.de/?erlaubnis=2026-P-0148&key=31e86617aad37a3a3902a560e69c7e79
PdfRegistry::register('erlaubnisschein', 'PdfErlaubnisschein2', $tc_lib_pdf_templates . '/class-pdf-erlaubnisschein.php');

// https://bfv-ehingen.de/?fpdf=1&yy=2025
PdfRegistry::register('fangstatistik', 'PdfFangstatistikJahr', $tc_lib_pdf_templates . '/class-pdf-fangstatistik.php');
// https://bfv-ehingen.de/?fpdfDiagramm=1&yy=2024
PdfRegistry::register('vorjahresvergleich', 'PdfFangstatistikVorJahresvergleich', $tc_lib_pdf_templates . '/class-pdf-fangstatistik.php');
// https://bfv-ehingen.de/?JahresDiagramm=1&yy=2024
PdfRegistry::register('jahresvergleich', 'PdfFangstatistikMehrjahresvergleich', $tc_lib_pdf_templates . '/class-pdf-fangstatistik.php');
// https://bfv-ehingen.de/?pdf_make_JahresDiagramm=1&yy=2024
PdfRegistry::register('fangstatistikuservergleich', 'PdfFangstatistikUser', $tc_lib_pdf_templates . '/class-pdf-fangstatistik.php');

// https://bfv-ehingen.de/?get_pdf=anmeldung_lfvbw&nr=123&nn=Mustermann&vn=Max&key=...
PdfRegistry::register('anmeldung_lfvbw', 'PdfTemplateLFVBW', $tc_lib_pdf_templates . '/class-pdf-anmeldunglfvwb.php');

// https://bfv-ehingen.de/?mitgliedsantrag=1&mn=244&vn=Alexander&n=Lammert&y=2026&key=cc11bdd3e4e5c1263e0a76c5745d23d2
PdfRegistry::register('mitgliedsantrag', 'PdfMitgliedsantrag2', $tc_lib_pdf_templates . '/class-pdf-mitgliedsantrag.php');
// https://bfv-ehingen.de/?infoblatt-antrag=1&mn=244
PdfRegistry::register('mitgliedsantraginfo', 'PdfMitgliedsantragInfoBlatt', $tc_lib_pdf_templates . '/class-pdf-mitgliedsantrag.php');

// Weitere Templates: Datei nach wp-include/templates/, Zeile hier ergänzen.
// PdfRegistry::register('custom', 'PdfCustom', $tc_lib_pdf_templates . '/class-pdf-custom.php');

unset($tc_lib_pdf_templates);

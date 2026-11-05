<?php
/**
 * Template-Registrierung für Admidio.
 *
 * @package WordPress Plugin Template/Admidio
 */

if (!defined('TC_LIB_PDF_BOOTSTRAPPED')) {
	exit;
}

$tc_lib_pdf_adm_templates = __DIR__ . '/templates';

// Rechnungen des Plugins Mitgliedsbeitrag (Daten über InvoiceService::pdfData() des Plugins)
PdfRegistry::register('adm_rechnung_beitrag', 'PdfAdmRechnungBeitrag', $tc_lib_pdf_adm_templates . '/class-pdf-adm-rechnung.php');
PdfRegistry::register('adm_rechnung_arbeitsdienst', 'PdfAdmRechnungArbeitsdienst', $tc_lib_pdf_adm_templates . '/class-pdf-adm-rechnung.php');

unset($tc_lib_pdf_adm_templates);

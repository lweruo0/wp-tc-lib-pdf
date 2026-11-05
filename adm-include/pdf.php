<?php
/**
 * Einstieg für PDF-Abrufe in Admidio:
 *   /adm_plugins/wp-tc-lib-pdf/adm-include/pdf.php?get_pdf=<template>&nr=…[&key=…&expires=…]
 *
 * Zugriff (KONZEPT-PLATTFORM.md 2.6):
 *  - mit gültigem signiertem Langzeit-Link ohne Anmeldung (z. B. Link in der Rechnungsmail),
 *  - sonst nur für angemeldete Administratoren.
 * Über signierte Links wird immer die abgelegte Datei ausgeliefert; "new" (neu erzeugen) gilt nur für
 * Administratoren.
 *
 * @package WordPress Plugin Template/Admidio
 */

$tcLibPdfAdmRoot = dirname(__DIR__, 3);
if (!is_file($tcLibPdfAdmRoot . '/system/common.php')) {
	// derselbe Ordner in WordPress: hier nichts ausführen
	http_response_code(404);
	exit;
}

define('TC_LIB_PDF_ADM_DISPATCH', true);
require_once $tcLibPdfAdmRoot . '/system/common.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/class-adm-dispatcher.php';

// Ohne gültigen signierten Link ist eine Anmeldung nötig. login_valid.php muss im globalen Gültigkeitsbereich
// eingebunden werden (es liest $gValidLogin) und leitet nicht angemeldete Benutzer zur Anmeldung um.
if (!Tc_Lib_Pdf_Adm_Dispatcher::hasValidSignature()) {
	require_once $tcLibPdfAdmRoot . '/system/login_valid.php';
}

Tc_Lib_Pdf_Adm_Dispatcher::handle();

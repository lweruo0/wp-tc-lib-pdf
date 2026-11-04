<?php
/**
 * Plugin Name: TC Lib PDF for WordPress
 * Plugin URI: https://github.com/lweruo0/wp-tc-lib-pdf
 * Description: Bootstrap plugin for tc-lib-pdf via Composer.
 * Version: 2026.11.04
 * Author: Oliver Ruoß
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

/*
 * Die plattformneutrale Engine in include/ prüft nur TC_LIB_PDF_BOOTSTRAPPED,
 * nicht ABSPATH. Der Admidio-Bootstrap (adm-include/) definiert dieselben
 * Konstanten. Alles WordPress-Spezifische liegt in wp-include/.
 */
defined('TC_LIB_PDF_DIR') || define('TC_LIB_PDF_DIR', __DIR__);
defined('TC_LIB_PDF_BOOTSTRAPPED') || define('TC_LIB_PDF_BOOTSTRAPPED', true);
defined('TC_LIB_PDF_WP_FILE') || define('TC_LIB_PDF_WP_FILE', __FILE__);

require_once __DIR__ . '/wp-include/bootstrap.php';

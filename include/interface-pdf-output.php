<?php
/**
 * PDF Output Interface.
 *
 * Defines the contract for all PDF templates that can be dispatched.
 * Both tc-lib-pdf based templates and raw PDF templates must implement this.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

interface PdfOutputInterface {
	/**
	 * Generate and send the PDF as a browser download (Content-Disposition: attachment).
	 *
	 * @return void
	 */
	public function output(): void;

	/**
	 * Generate and stream the PDF inline to the browser (Content-Disposition: inline).
	 *
	 * @return void
	 */
	public function stream(): void;
}

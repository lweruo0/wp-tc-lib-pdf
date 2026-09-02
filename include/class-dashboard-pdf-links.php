<?php
/**
 * Dashboard PDF Links widget.
 *
 * Provides example links for PDF generation in the WordPress dashboard.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Tc_Lib_Pdf_Dashboard_Pdf_Links {

    public static function init(): void {
        if (is_admin()) {
            add_action('wp_dashboard_setup', array(__CLASS__, 'register_widget'));
        }
    }

    public static function register_widget(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'tc_lib_pdf_dashboard_links',
            'PDF Links',
            array(__CLASS__, 'render')
        );
    }
 

    private static function pdf_link( $label, $typ, $query_args=array(), $expires=''  ): void {
        $url = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url($typ, $query_args, $expires);
        echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
    }   

    public static function render(): void {
        if (!class_exists('Tc_Lib_Pdf_Wp_Bootstrap')) {
            echo '<p>' . esc_html__('PDF-URL-Helper noch nicht verfügbar.', 'tc-lib-pdf-wp') . '</p>';
            return;
        }

        self::pdf_link('✓ Arbeitsdienst Liste 12.09.2026', 'liste_arbeitsdienst', ['dienst' => '12.09.2026'], '2099-12-31');
        self::pdf_link('✓ Jugendveranstaltung Liste 25.09.2026', 'liste_jugendveranstaltung', ['veranstaltung' => '25.09.2026'], '2099-12-31');

        self::pdf_link('✓ Erlaubnis 2026-P-0148', 'erlaubnisschein', ['nr' => '2026-P-0148'], '2099-12-31');
        self::pdf_link('✓ Erlaubnis Rechnung 2026-P-0148', 'rechnung_erlaubnis', ['nr' => '2026-P-0148'], '2099-12-31');
        self::pdf_link('✓ Erlaubnis Mahnung 2026-P-0148', 'mahnung_erlaubnis', ['nr' => '2026-P-0148'], '2099-12-31');

        self::pdf_link('✓ Vorbereitungslehrgang Rechnung', 'rechnung_vorbereitungslehrgang', ['nr' => '2026-L-0012'], '2099-12-31');
        self::pdf_link('✓ Vorbereitungslehrgang Anmeldung LFVBW' , 'anmeldung_lfvbw',  ['nr' => '2026-L-0012']);

        self::pdf_link('✓ Vorbereitungslehrgang Rechnung', 'rechnung_vorbereitungslehrgang', ['nr' => '2026-L-0012'], '2099-12-31');
        self::pdf_link('✓ Vorbereitungslehrgang Anmeldung LFVBW' , 'anmeldung_lfvbw',  ['nr' => '2026-L-0012']);

        self::pdf_link('✓ Statistik 2024', 'fangstatistik', ['jahr' => '2024']);
        self::pdf_link('✓ Jahresvergleich', 'jahresvergleich');
        self::pdf_link('✓ vorjahresvergleich', 'vorjahresvergleich');
        self::pdf_link('✓ Uservergleich 2024', 'fangstatistikuservergleich',  ['jahr' => '2024']);

        self::pdf_link('Mitgliedsantrag 160' , 'mitgliedsantrag', ['mnr' => '160-233-234-235']);
        self::pdf_link('Mitgliedsantrag Rechnung', 'rechnung_antrag', ['mnr' => '233'], '2099-12-31');
        self::pdf_link('Mitgliedsantrag Mahnung 233', 'mahnung_antrag', ['mnr' => '233'], '2099-12-31');

        self::pdf_link('Huette Rechnung' , 'rechnung_huette', ['nr' => '2026-H-0002'], '2099-12-31');
        self::pdf_link('✓ Merchandise Rechnung' , 'rechnung_merchandise', ['nr' => '2026-J-00001'], '2099-12-31');
        
        //self::update_urls();
    }


	/*
	 *
	 *
	 *
	 */
	/** @phpstan-ignore-next-line */
	private static function update_ad() {

		$repository = Quform::getService ( 'repository' );
		$formFactory = Quform::getService ( 'formFactory' );
		$form = $formFactory->create ( $repository->getConfig ( 4 ) );
		$entries = $repository->getEntries ( $form, array (
				'order_by' => 'created_at',
				'order' => 'ASC',
				'limit' => 10000,
		) );

		/* wir suchen die Bestellungen mit der richtigen Rechnungsnummer */
		if (is_array ( $entries )) {
			foreach ( $entries as $entry ) {
                $entryId = $entry ['id'];
                if (isset($entry['element_16']) && $entry['element_16'] === '12.10.2026') {
                    error_log(print_r($entry['element_16'], TRUE));
                    $update_ids = array();
                    $update_ids[16] = '24.10.2026';
                    $repository->saveEntryData($entryId, $update_ids);
                    //error_log(print_r($entry, TRUE));
                }
            }
        }
    }



	/*
	 *
	 *
	 *
	 */
	/** @phpstan-ignore-next-line */
	private static function update_urls() {
		$formdata = array ();

        $options_quform = get_option ( 'bfv_jubilaeumsruten' );

        $uploadDir = wp_upload_dir();
        $folder = $uploadDir['basedir'] . '/bfv_merchandise/';

        // old: rechnung_rute_2025-J-0004.pdf
        // new: rechnung_merchandise_{$rechnungsnummer}.pdf



		$repository = Quform::getService ( 'repository' );
		$formFactory = Quform::getService ( 'formFactory' );
		$form = $formFactory->create ( $repository->getConfig ( $options_quform ['quform_id'] ) );
		$entries = $repository->getEntries ( $form, array (
				'order_by' => 'created_at',
				'order' => 'ASC',
				'limit' => 10000,
		) );

        $id_rechnungsnummer = 'element_' . $options_quform ['rechnungsnummer_id'];
       
		/* wir suchen die Bestellungen mit der richtigen Rechnungsnummer */
		if (is_array ( $entries )) {
			foreach ( $entries as $entry ) {
                $rechnungsnummer = $entry [$id_rechnungsnummer];

                // Datei umbenennen: rechnung_rute_ -> rechnung_merchandise_
                $old_file = $folder . 'rechnung_rute_' . $rechnungsnummer . '.pdf';
                $new_file = $folder . 'rechnung_merchandise_' . $rechnungsnummer . '.pdf';
                if (file_exists($old_file) && !file_exists($new_file)) {
                    rename($old_file, $new_file);
                }

                $url_mahnung_new = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url('mahnung_merchandise', ['nr' => $rechnungsnummer], '+5years');
                $url_rechnung_new = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url('rechnung_merchandise', ['nr' => $rechnungsnummer], '+5years');
                $update_ids = array();
                $update_ids [(int) $options_quform ['mahnung_id']] = $url_mahnung_new;
                $update_ids [(int) $options_quform ['rechnung_id']] = $url_rechnung_new;
                $repository->saveEntryData($entry ['id'], $update_ids);
            }
        }
    }
}


Tc_Lib_Pdf_Dashboard_Pdf_Links::init();
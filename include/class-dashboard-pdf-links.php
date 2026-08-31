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

    private static function long_term_url( $typ, $expires, $nr, $param=array() ) {
        return Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url(
           $typ,
            $param,
            false,
            '',
            $expires,
            $nr
        );
    }   


    private static function long_term_link( $label, $typ, $expires, $nr, $param=array() ): void {
        $url = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url(
           $typ,
            $param,
            false,
            '',
            $expires,
            $nr
        );
        echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
    }   
    private static function shortterm_url( $label, $typ, $param=array() ): void {
        $url = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url(
            $typ,
            $param,
            false,
            '',
            '',
            ''
        );
        echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
    }


    public static function render(): void {
        if (!class_exists('Tc_Lib_Pdf_Wp_Bootstrap')) {
            echo '<p>' . esc_html__('PDF-URL-Helper noch nicht verfügbar.', 'tc-lib-pdf-wp') . '</p>';
            return;
        }
        self::long_term_link('Erlaubnis 2026-P-0148', 'erlaubnisschein', '2099-12-31', '2026-P-0148');
        self::long_term_link('Rechnung Erlaubnis 2026-P-0148', 'rechnung_erlaubnis', '2099-12-31', '2026-P-0148');
        self::long_term_link('TODO Mahnung Erlaubnis 2026-P-0148', 'mahnung_erlaubnis', '2099-12-31', '2026-P-0148');

        self::shortterm_url('TODO Mitgliedsantrag 160' , 'mitgliedsantrag', ['mnr' => '160-233-234-235']);
        self::shortterm_url('TODO Mitgliedsantrag 233' , 'mitgliedsantrag', ['mnr' => '233']);
        self::shortterm_url('TODO Mitgliedsantraginfo 233', 'mitgliedsantraginfo', ['mnr' => '233']);
        self::long_term_link('Rechnung Mitgliedsantrag 233', 'rechnung_antrag', '2099-12-31', '233');
        self::long_term_link('TODO Mahnung Mitgliedsantrag 233', 'mahnung_antrag', '2099-12-31', '233');

        self::long_term_link('Rechnung Vorbereitungslehrgang 12', 'rechnung_vorbereitungslehrgang','2099-12-31', '2026-L-0012');
        self::shortterm_url('TODO anmeldung_lfvbw' , 'anmeldung_lfvbw',  ['nr' => '2026-L-0012']);

        self::shortterm_url('Statistik 2024', 'fangstatistik', ['jahr' => '2024']);
        self::shortterm_url('Jahresvergleich', 'jahresvergleich');
        self::shortterm_url('Uservergleich 2024', 'fangstatistikuservergleich',  ['jahr' => '2024']);



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

        $options_quform = get_option ( 'bfv_erlaubnisschein_quform' );

        
		$repository = Quform::getService ( 'repository' );
		$formFactory = Quform::getService ( 'formFactory' );
		$form = $formFactory->create ( $repository->getConfig ( $options_quform ['quform_id'] ) );
		$entries = $repository->getEntries ( $form, array (
				'order_by' => 'created_at',
				'order' => 'ASC',
				'limit' => 10000,
				'search'
		) );

//        [element_228] => https://bfv-ehingen.de/?rechnung=2023-P-0001&key=7be199174adee8084415f887be90ebf1
//        [element_229] => https://bfv-ehingen.de/?erlaubnis=2023-P-0001&key=7be199174adee8084415f887be90ebf1
//        [element_230] => https://bfv-ehingen.de/?mahnung=2023-P-0001&key=7be199174adee8084415f887be90ebf1


        $id_rechnungsnummer = 'element_' . $options_quform ['id_rechnungsnummer'];
        $id_url_mahnung = 'element_' . $options_quform ['id_url_mahnung'];
        $id_url_rechnung = 'element_' . $options_quform ['id_url_rechnung'];
        $id_url_erlaubnisschein = 'element_' . $options_quform ['id_url_erlaubnisschein'];

		/* wir suchen die Bestellungen mit der richtigen Rechnungsnummer */
		if (is_array ( $entries )) {
			foreach ( $entries as $entry ) {
                $entryId = $entry ['id'];
                

                $old_values = array('id'=>$entryId);
                $old_values[$id_rechnungsnummer] = $entry [$id_rechnungsnummer];
                $old_values[$id_url_mahnung] = $entry [$id_url_mahnung];
                $old_values[$id_url_rechnung] = $entry [$id_url_rechnung];
                $old_values[$id_url_erlaubnisschein] = $entry [$id_url_erlaubnisschein];

                $rechnungsnummer = $entry [$id_rechnungsnummer];

                $url_mahnung_new = self::long_term_url('mahnung_erlaubnis', '2099-12-31', $rechnungsnummer);
                $url_erlaubnisschein_new = self::long_term_url('erlaubnisschein', '2099-12-31', $rechnungsnummer);
                $url_rechnung_new = self::long_term_url('rechnung_erlaubnis', '2099-12-31', $rechnungsnummer);

                $update_ids = array();
                $update_ids [(int) $options_quform ['id_url_mahnung']] = $url_mahnung_new;
                $update_ids [(int) $options_quform ['id_url_rechnung']] = $url_rechnung_new;
                $update_ids [(int) $options_quform ['id_url_erlaubnisschein']] = $url_erlaubnisschein_new;


                $repository->saveEntryData($entryId, $update_ids);

                error_log(print_r($old_values, TRUE));


            }
        }
    }
}


Tc_Lib_Pdf_Dashboard_Pdf_Links::init();
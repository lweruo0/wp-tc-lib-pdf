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
    private static function long_term_url( $label, $typ, $expires, $nr, $param=array() ): void {
        $url = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url(
           $typ,
            $param,
            false,
            '',
            $expires,
            $nr
        );
        echo '<p><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
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
        echo '<p><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
    }


    public static function render(): void {
        if (!class_exists('Tc_Lib_Pdf_Wp_Bootstrap') || !method_exists('Tc_Lib_Pdf_Wp_Bootstrap', 'build_pdf_url')) {
            echo '<p>' . esc_html__('PDF-URL-Helper noch nicht verfügbar.', 'tc-lib-pdf-wp') . '</p>';
            return;
        }
        self::long_term_url('Erlaubnis 2026-P-0148', 'erlaubnisschein', '2099-12-31', '2026-P-0148');
        self::long_term_url('Rechnung Erlaubnis 2026-P-0148', 'rechnung_erlaubnis', '2099-12-31', '2026-P-0148');
        self::long_term_url('TODO Mahnung Erlaubnis 2026-P-0148', 'mahnung_erlaubnis', '2099-12-31', '2026-P-0148');

        self::shortterm_url('TODO Mitgliedsantrag 233' , 'mitgliedsantrag', ['mnr' => '233']);
        self::shortterm_url('TODO Mitgliedsantraginfo 233', 'mitgliedsantraginfo', ['mnr' => '233']);
        self::long_term_url('Rechnung Mitgliedsantrag 233', 'rechnung_antrag', '2099-12-31', '233');
        self::long_term_url('TODO Mahnung Mitgliedsantrag 233', 'mahnung_antrag', '2099-12-31', '233');

        self::shortterm_url('Statistik 2024', 'fangstatistik', ['jahr' => '2024']);
        self::shortterm_url('Jahresvergleich', 'jahresvergleich');
        self::shortterm_url('Uservergleich 2024', 'fangstatistikuservergleich',  ['jahr' => '2024']);
    }
}

Tc_Lib_Pdf_Dashboard_Pdf_Links::init();

<?php
/**
 * Dashboard PDF Links widget.
 *
 * Provides example links for PDF generation in the WordPress dashboard and
 * the one-off action that regenerates the signed links stored in the
 * Quform entries of the membership application form.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Tc_Lib_Pdf_Dashboard_Pdf_Links {

    /** admin-post action for update_urls(). */
    private const ACTION_UPDATE_URLS = 'tc_lib_pdf_update_urls';

    /** Laufzeit der Langzeit-Links in den Quform-Einträgen. */
    private const ANTRAG_LINK_EXPIRES = '+2 years';

    public static function init(): void {
        if (is_admin()) {
            add_action('wp_dashboard_setup', array(__CLASS__, 'register_widget'));
            add_action('admin_post_' . self::ACTION_UPDATE_URLS, array(__CLASS__, 'handle_update_urls'));
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

        self::render_update_urls_notice();

        // Listen und Statistiken: nur Nonce (an die Admin-Sitzung gebunden),
        // keine Langzeit-Links.
        self::pdf_link('✓ Arbeitsdienst Liste 12.09.2026', 'liste_arbeitsdienst', ['dienst' => '12.09.2026']);
        self::pdf_link('✓ Jugendveranstaltung Liste 25.09.2026', 'liste_jugendveranstaltung', ['veranstaltung' => '25.09.2026']);

        self::pdf_link('✓ Erlaubnis 2026-P-0148', 'erlaubnisschein', ['nr' => '2026-P-0148'], '2099-12-31');
        self::pdf_link('✓ Erlaubnis Rechnung 2026-P-0148', 'rechnung_erlaubnis', ['nr' => '2026-P-0148'], '2099-12-31');
        self::pdf_link('✓ Erlaubnis Mahnung 2026-P-0148', 'mahnung_erlaubnis', ['nr' => '2026-P-0148'], '2099-12-31');

        self::pdf_link('✓ Vorbereitungslehrgang Rechnung', 'rechnung_vorbereitungslehrgang', ['nr' => '2026-L-0012'], '2099-12-31');
        self::pdf_link('✓ Vorbereitungslehrgang Anmeldung LFVBW' , 'anmeldung_lfvbw',  ['nr' => '2026-L-0012']);

        self::pdf_link('✓ Statistik 2024', 'fangstatistik', ['jahr' => '2024']);
        self::pdf_link('✓ Jahresvergleich', 'jahresvergleich');
        self::pdf_link('✓ vorjahresvergleich', 'vorjahresvergleich');
        self::pdf_link('✓ Uservergleich 2024', 'fangstatistikuservergleich',  ['jahr' => '2024']);
        self::pdf_link('✓ Merchandise Rechnung' , 'rechnung_merchandise', ['nr' => '2026-J-00001'], '2099-12-31');
        self::pdf_link('Merchandise Mahnung' , 'mahnung_merchandise', ['nr' => '2026-J-00001'], '2099-12-31');
        self::pdf_link('✓ Huette Rechnung' , 'rechnung_huette', ['nr' => '2026-H-0002'], '2099-12-31');
        self::pdf_link('Huette Mahnung' , 'mahnung_huette', ['nr' => '2026-H-0002'], '2099-12-31');

        self::pdf_link('Mitgliedsantrag 268' , 'mitgliedsantrag', ['mnr' => '268']);
        self::pdf_link('Mitgliedsantrag Infoblatt 268' , 'mitgliedsantraginfo', ['mnr' => '268']);
        self::pdf_link('Mitgliedsantrag Rechnung', 'rechnung_antrag', ['mnr' => '233'], '2099-12-31');
        self::pdf_link('Mitgliedsantrag Mahnung 233', 'mahnung_antrag', ['mnr' => '233'], '2099-12-31');

        self::render_update_urls_form();
    }

    // -----------------------------------------------------------------------
    // Links in den Quform-Einträgen des Mitgliedsantrags neu erzeugen
    // -----------------------------------------------------------------------

    /**
     * Button, der update_urls() über admin-post.php auslöst.
     */
    private static function render_update_urls_form(): void {
        echo '<hr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"'
            . ' onsubmit="return confirm(\'Alle Antrags- und Rechnungslinks in den Quform-Einträgen neu erzeugen? Bisher verschickte Links werden ungültig.\');">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_UPDATE_URLS) . '">';
        wp_nonce_field(self::ACTION_UPDATE_URLS);
        echo '<p>Links in den Mitgliedsantrag-Einträgen (Quform) neu signieren:</p>';
        echo '<p><button type="submit" class="button">Antragslinks neu erzeugen</button></p>';
        echo '</form>';
    }

    /**
     * Ergebnis der letzten Ausführung anzeigen (Redirect-Parameter).
     */
    private static function render_update_urls_notice(): void {
        if (!isset($_GET['tc_lib_pdf_urls'])) {
            return;
        }
        $result = sanitize_text_field(wp_unslash($_GET['tc_lib_pdf_urls']));
        if (preg_match('/^(\d+)-(\d+)$/', $result, $m)) {
            echo '<div class="notice notice-success inline"><p>'
                . esc_html(sprintf('Antragslinks neu erzeugt: %d Einträge aktualisiert, %d ohne Mitgliedsnummer übersprungen.', (int) $m[1], (int) $m[2]))
                . '</p></div>';
        } else {
            echo '<div class="notice notice-error inline"><p>'
                . esc_html('Antragslinks nicht erzeugt: ' . $result)
                . '</p></div>';
        }
    }

    /**
     * admin-post-Handler: Berechtigung und Nonce prüfen, update_urls()
     * ausführen, zurück zum Dashboard.
     */
    public static function handle_update_urls(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'tc-lib-pdf-wp'), '', array('response' => 403));
        }
        check_admin_referer(self::ACTION_UPDATE_URLS);

        try {
            [$updated, $skipped] = self::update_urls();
            $result = $updated . '-' . $skipped;
        } catch (Throwable $e) {
            $result = $e->getMessage();
        }

        wp_safe_redirect(add_query_arg('tc_lib_pdf_urls', rawurlencode($result), admin_url('index.php')));
        exit;
    }

    /**
     * Signierte Links für Mitgliedsantrag und Antragsrechnung in allen
     * Quform-Einträgen des Antragsformulars neu erzeugen.
     *
     * Notwendig nach jeder Änderung an der Signatur (build_pdf_url()), weil
     * die Links in den Einträgen gespeichert sind und von dort verschickt
     * werden. Die bisherigen Links werden dadurch ungültig.
     *
     * @return array{0: int, 1: int} [aktualisiert, übersprungen ohne Mitgliedsnummer]
     *
     * @throws RuntimeException Wenn Quform oder die Formular-Einstellungen fehlen.
     */
    private static function update_urls(): array {
        if (!class_exists('Quform')) {
            throw new RuntimeException('Quform ist nicht aktiv.');
        }

        $options_quform = get_option('bfv_mitgliedsantrag_quform');
        foreach (array('quform_id', 'id_mitgliedsnummer', 'id_url', 'id_url2') as $key) {
            if (!is_array($options_quform) || empty($options_quform[$key])) {
                throw new RuntimeException("Option bfv_mitgliedsantrag_quform['{$key}'] fehlt.");
            }
        }

        $repository = Quform::getService('repository');
        $formFactory = Quform::getService('formFactory');
        $config = $repository->getConfig($options_quform['quform_id']);
        if (!is_array($config)) {
            throw new RuntimeException('Quform-Formular ' . $options_quform['quform_id'] . ' nicht gefunden.');
        }
        $form = $formFactory->create($config);
        $entries = $repository->getEntries($form, array(
            'order_by' => 'created_at',
            'order' => 'ASC',
            'limit' => 10000,
        ));

        $id_mitgliedsnummer = 'element_' . $options_quform['id_mitgliedsnummer'];
        $updated = 0;
        $skipped = 0;

        if (!is_array($entries)) {
            return array($updated, $skipped);
        }

        foreach ($entries as $entry) {
            $mitgliedsnummer = trim((string) ($entry[$id_mitgliedsnummer] ?? ''));
            if ($mitgliedsnummer === '') {
                ++$skipped;
                continue;
            }

            $url_antrag = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url('mitgliedsantrag', ['mnr' => $mitgliedsnummer], self::ANTRAG_LINK_EXPIRES);
            $url_rechnung = Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url('rechnung_antrag', ['mnr' => $mitgliedsnummer], self::ANTRAG_LINK_EXPIRES);
            if ($url_antrag === '' || $url_rechnung === '') {
                ++$skipped;
                continue;
            }

            $update_ids = array();
            $update_ids[(int) $options_quform['id_url']] = $url_antrag;
            $update_ids[(int) $options_quform['id_url2']] = $url_rechnung;
            $repository->saveEntryData($entry['id'], $update_ids);
            ++$updated;
        }

        return array($updated, $skipped);
    }
}


Tc_Lib_Pdf_Dashboard_Pdf_Links::init();

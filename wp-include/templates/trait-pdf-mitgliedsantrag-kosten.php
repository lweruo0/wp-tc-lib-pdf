<?php
/**
 * Kostenberechnung Mitgliedsantrag.
 *
 * Gemeinsame Berechnung der Kosten bei Vereinseintritt für den Mitgliedsantrag
 * (Kostenübersicht im Antrag) und die Rechnung zum Mitgliedsantrag. Damit
 * zeigen Antrag und Rechnung immer dieselben Beträge.
 *
 * Die Beträge kommen aus den Optionen 'bfv_mitgliedsantrag_einstellungen'
 * (betrag_*), Mitgliedschaftsart und Optionen aus den Antragsdaten von
 * bfvmitgliedschaft()->mitgliedsantrag->get_formdata_by_mitgliedsnummer().
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Kosten bei Vereinseintritt.
 *
 * Voraussetzung: Die Optionen 'bfv_mitgliedsantrag_einstellungen' sind über
 * setOptions() gesetzt (getOption() aus PdfDataTrait).
 */
trait PdfMitgliedsantragKostenTrait {

	/**
	 * Kostenzeilen für einen Antrag berechnen.
	 *
	 * @param array<string, mixed> $antrag Antragsdaten (Keys mitgliedschaft,
	 *                                     option_boot, option_jugend, beitrag_foerder).
	 *
	 * @return array<int, array{0: string, 1: float}> Liste aus [Bezeichnung, Betrag].
	 */
	protected function mitgliedsantragKosten(array $antrag): array {
		$mitgliedschaft = (string) ($antrag['mitgliedschaft'] ?? '');
		$aktiv  = $mitgliedschaft === 'Aktiv';
		$jugend = $mitgliedschaft === 'Jugend';
		$passiv = $mitgliedschaft === 'Passiv';
		$foerder = $mitgliedschaft === 'Förder';
		$option2Angel = in_array($antrag['option_jugend'] ?? '', ['2.Angel'], true);
		$optionBoot   = in_array($antrag['option_boot'] ?? '', ['Boot', 'boot'], true);

		$kosten = [];
		if ($aktiv || $passiv || $jugend) {
			$kosten[] = ['Bearbeitungsgebühr', (float) $this->getOption('betrag_bearbeitungsgebuehr', 0)];
		}
		if ($aktiv) {
			$kosten[] = ['Erstbesatz', (float) $this->getOption('betrag_erstbesatz', 0)];
			$kosten[] = ['jährlicher Mitgliedsbeitrag', (float) $this->getOption('betrag_aktiv', 0)];
			if ($optionBoot) {
				$kosten[] = ['Option Boot', (float) $this->getOption('betrag_boot', 0)];
			}
		}
		if ($jugend) {
			$kosten[] = ['jährlicher Mitgliedsbeitrag', (float) $this->getOption('betrag_jugend', 0)];
			if ($option2Angel) {
				$kosten[] = ['Option 2.Angel', (float) $this->getOption('betrag_2angel', 0)];
			}
		}
		if ($passiv) {
			$kosten[] = ['jährlicher Mitgliedsbeitrag', (float) $this->getOption('betrag_passiv', 0)];
		}
		if ($foerder) {
			$kosten[] = ['jährlicher Mitgliedsbeitrag', (float) ($antrag['beitrag_foerder'] ?? 0)];
		}

		return $kosten;
	}
}

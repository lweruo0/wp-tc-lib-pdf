# wp-tc-lib-pdf — PDF Template System for WordPress

Kurz: dieses Plugin bietet ein leicht erweiterbares System zum Erzeugen von PDFs
mittels tc-lib-pdf (tecnickcom) in WordPress‑Umgebungen. Templates sind PHP‑Klassen
und können durch Traits modular erweitert werden.

Weitere Dokumentation:
- [ARCHITECTURE.md](ARCHITECTURE.md) — Aufbau, Klassenhierarchie, Traits, Security Flow
- [KONZEPT-PLATTFORM.md](KONZEPT-PLATTFORM.md) — geplante Trennung in WordPress- und Admidio-Adapter

## Anforderungen
- PHP >= 8.2 (wird vom Plugin beim Aktivieren geprüft)
- Composer (für Installation der Abhängigkeiten)

## Installation

1. Abhängigkeiten installieren (Entwicklungsmodus):

```powershell
composer install
```

2. Für Release / Produktion (deterministische Installation):

```powershell
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

3. Falls `composer.lock` veraltet ist (z. B. nach Änderung an `composer.json`):

```powershell
composer update
```

Hinweis: `install` verwendet `composer.lock` und ist reproduzierbar; `update`
schreibt eine neue `composer.lock` und lädt die neuesten kompatiblen Versionen.

## Fonts / Core fonts

Dieses Projekt nutzt tc-lib-pdf-font. Die Core‑Font‑Metriken (Helvetica, Times,
Courier, Symbol, ZapfDingbats) liegen in `font/core` als JSON‑Definitionen.

Wenn du die Core‑Metrics neu erzeugen oder anpassen willst, gibt es zwei Wege:

- Aus offiziellen AFM‑Quellen (empfohlen für PDF/A‑Konformität):
  - Projekt `tc-font-pdfa` (GitHub) stellt die AFM/Type1‑Assets bereit.
  - Zum Erzeugen der JSONs wurde das im Repo enthaltene Tool verwendet:

```powershell
# AFM in den Konverter‑Mirror legen (oder tc-font-pdfa herunterladen)
php vendor/tecnickcom/tc-lib-pdf-font/util/bulk_convert.php -o font/
```

- Alternativ können lokale TTF/OTF verwendet werden (weniger "official"), das
  Tool unterstützt auch TTF‑Quellen.

Wichtiger Hinweis: das Tool erzeugt mehrere Artefakte (z. B. `.z` Dateien). Das
Repository enthält bereits die erzeugten JSONs; temporäre Artefakte können
gelöscht werden, wenn du nur die JSON‑Metriken behalten willst.

## Entwicklung: Templates & Traits

- Templates sind Klassen in `wp-include/templates/` und erben von `PdfTemplate`
  bzw. `PdfRechnungBase` aus der Engine in `include/`.
- Wiederverwendbare UI‑Bausteine sind als Traits implementiert (z. B.
  `trait-pdf-header.php`, `trait-pdf-rechnungsdaten.php`).
- Templates werden in `wp-include/templates.php` registriert und bei Bedarf geladen.

Kurzes Beispiel (Registration):

```php
require_once __DIR__ . '/class-pdf-report.php';
PdfRegistry::register('report', 'PdfReport', __DIR__ . '/class-pdf-report.php');
```

## **Available PDFs**

Die registrierten PDF‑Templates sind in `wp-include/templates.php` konfiguriert.
Aktuelle IDs und Klassen (Auswahl):

- `rechnung_erlaubnis` — `PdfRechnungErlaubnis`
- `rechnung_merchandise` — `PdfRechnungMerchandise`
- `rechnung_antrag` — `PdfRechnungMitgliedsantrag`
- `rechnung_huette` — `PdfRechnungHuette`
- `rechnung_vorbereitungslehrgang` — `PdfRechnungVorbereitungsLehrgang2`
- `mahnung_erlaubnis` — `PdfMahnungErlaubnis`
- `mahnung_merchandise` — `PdfMahnungMerchandise`
- `mahnung_antrag` — `PdfMahnungMitgliedsantrag`
- `mahnung_huette` — `PdfMahnungHuette`
- `liste_arbeitsdienst` — `PdfListeArbeitsdienst`
- `liste_jugendveranstaltung` — `PdfListeJugendveranstaltung`
- `erlaubnisschein` — `PdfErlaubnisschein2`
- `fangstatistik` / `vorjahresvergleich` / `jahresvergleich` — Statistik‑Templates
- `anmeldung_lfvbw` — `PdfTemplateLFVBW`
- `mitgliedsantrag` / `mitgliedsantraginfo` — Antrags‑Templates

Prüfe die vollständige Liste in [wp-include/templates.php](wp-include/templates.php).

## **Integration — Links aus anderen Plugins erzeugen**

Es gibt zwei gebräuchliche Integrationswege, um von einem anderen Plugin aus auf
die PDF‑Funktionen zuzugreifen:

- **1) URL‑Helper (einfach / empfohlen)**

  Nutze den globalen Helfer `tc_lib_pdf_wp_create_pdf_url()` (Wrapper für
  `Tc_Lib_Pdf_Wp_Bootstrap::build_pdf_url`). Beispiel:

  ```php
  // Erzeugt einen langfristig gültigen Link (Signatur + expires)
  $url = tc_lib_pdf_wp_create_pdf_url('rechnung_merchandise', ['nr' => $nr], '+1 year');

  // Oder mit Nonce (kurzfristig):
  $url = tc_lib_pdf_wp_create_pdf_url('mahnung_merchandise', ['nr' => $nr]);
  ```

  Der Helper fügt automatisch entweder `nonce` (Default) oder `key` + `expires`
  (wenn $expires gesetzt) hinzu und baut die vollständige URL mit `get_pdf`.

- **2) Manuelles Erzeugen / Cachen der PDF (Speichern + Pfad zurückgeben)**

  Wenn ein Plugin die PDF zuerst generieren und auf dem Filesystem ablegen
  möchte, kann es die Template‑Instanz direkt nutzen:

  ```php
  $instance = PdfRegistry::create('rechnung_merchandise');
  $instance->setUrldata(['nr' => $nr]);        // Query‑Parameter für das Template
  $instance->setFormdata($formdata);           // Ggf. alle benötigten Felder setzen
  $instance->setFolderName('bfv_merchandise'); // optional: Speicherordner

  if ($instance->save()) {
      $path = $instance->getFileNameAbs();
      // z. B. in Form / DB speichern oder als Attachment verwenden
  }
  ```

  Diese Methode ruft intern `loadData()` / `render()` auf und schreibt die PDF
  an den durch `setFolderName()` / `setFileName()` bestimmten Ort.

  Hinweis zum Laden der Bibliothek: Der Composer‑Autoloader und `K_PATH_FONTS`
  werden aus Performance‑Gründen erst bei Bedarf geladen, nicht auf jedem
  WordPress‑Request. `PdfRegistry::create()` und `tc_lib_pdf_wp_create_pdf()`
  erledigen das automatisch. Wer `\Com\Tecnick\Pdf\Tcpdf` direkt anspricht,
  ruft vorher `Tc_Lib_Pdf_Wp_Bootstrap::boot()` auf. Der URL‑Helper
  `tc_lib_pdf_wp_create_pdf_url()` benötigt die Bibliothek nicht.

## **Integration: Daten‑Contract (was Ihr Plugin bereitstellen sollte)**

Templates erwarten Form‑/Adressdaten in einem assoziativen Array. Es gibt zwei
Möglichkeiten, die Templates mit Daten zu versorgen:

- Direkter Ansatz: Übergib ein bereits vorbereitetes Array via
  `setFormdata($array)` und optional `setAddressdata($array)` bevor du
  `save()` oder `stream()` aufrufst.

- Konventioneller Ansatz: Templates versuchen beim Laden (siehe
  `loadData()` in `wp-include/templates/class-pdf-rechnung-merchandise.php`) automatisch, eine
  Funktion/Instanz aus dem Host‑Plugin aufzurufen, z. B. `bfvjubilaeumsruten()`
  oder `bfvmerchandise()` im Beispielprojekt. Diese Instanz muss eine Methode
  `get_formdata_by_rechnungsnummer(string $nr): array` bereitstellen, die alle
  benötigten Felder zurückgibt.

Empfohlene Signatur (Beispiel):

```php
// In Ihrem Plugin
function myplugin() { return MyPluginSingleton::instance(); }

class MyPluginSingleton {
    public function get_formdata_by_rechnungsnummer(string $nr): array {
        // Liefert ein assoziatives Array mit den erwarteten Keys
    }
}
```

Wichtige, häufig verwendete Keys (nicht vollständig, aber ausreichend für
die Rechnungs-/Mahnung‑Templates):

- `rechnungsnummer` — string
- `rechnung_vorname`, `rechnung_name`, `rechnung_strasse`, `rechnung_plz`, `rechnung_ort`, `rechnung_email`
- `brutto`, `netto`, `steuer`, `steuersatz` — numerische Werte
- `zahlungsfrist_original` — Datum (string)
- `created_at` — Erstellungsdatum (string)
- `rechnungsposten` — array of line items, each item: `bezeichnung`, `anzahl`, `einzelpreis`, `gesamtpreis`, `steuersatz`
- Optional: `documenttype`, `texts_before`, `text_below`, `sender`, `returnme`

Wenn diese Struktur vorhanden ist, füllt das Template die Felder automatisch;
ansonsten kannst du die Werte vorher mit `setFormdata()` setzen.

### Beispiel: Integration in ein anderes Plugin (Kurzform)

```php
// 1) Erzeugt einen URL‑Link (empfohlen)
$url = tc_lib_pdf_wp_create_pdf_url('rechnung_merchandise', ['nr' => $nr], '+1 year');

// 2) Oder: PDF erzeugen und Pfad speichern
$inst = PdfRegistry::create('rechnung_merchandise');
$inst->setUrldata(['nr' => $nr]);
$inst->setFormdata($mydata);
if ($inst->save()) { $file = $inst->getFileNameAbs(); }
```

Weitere Beispiele für Linkerstellung und Caching befinden sich im Quellcode,
siehe insbesondere `wp-include/class-dashboard-pdf-links.php` und die Dispatcher/Registry‑Implementationen.


## Code‑Qualität
- PHPDoc und statische Analyse (z. B. PHPStan) sollten mit den Signaturen
  übereinstimmen; beim Refactoring wurden einige PHPDoc‑Tags bereits angepasst.

## Troubleshooting
- Wenn `composer install` fehlschlägt: prüfe zuerst Fehlermeldungen, speziell
  ob `composer.lock` und `composer.json` synchron sind. Falls nötig: `composer update`.
- Font‑Probleme: `K_PATH_FONTS` prüfen oder die Font‑JSONs in `font/core`.

## Was wurde zuletzt gemacht (Repo‑Hinweis)
- Core‑Font‑JSONs (Helvetica/Times/Courier + Varianten) wurden aus offiziellen
  AFM/Type1 Quellen generiert und in `font/core` geschrieben.

## Mithelfen / Kontakt
- Issues und Pull Requests bitte über das GitHub‑Repository eröffnen.

## Weiterführend

Klassenhierarchie, Traits, Security Flow, Dateistruktur, Extension Points und
Debugging sind in [ARCHITECTURE.md](ARCHITECTURE.md) beschrieben. Die geplante
Nutzung der Templates in Admidio ist in [KONZEPT-PLATTFORM.md](KONZEPT-PLATTFORM.md)
dokumentiert.

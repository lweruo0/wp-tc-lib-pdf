# TC Lib PDF for WordPress - PDF Template System

## Übersicht

Dieses System bietet eine flexible und erweiterbare Architektur zum Rendern verschiedener PDF-Templates basierend auf GET-Parametern. Mit Traits wird eine "Mehrfachvererbung" simuliert, um verschiedene Features zu kombinieren.

## Architektur

### 1. **PdfTemplate** (Basis-Klasse)
   - Abstrakte Basis für alle PDF-Templates
   - Erweitert `\Com\Tecnick\Pdf\Tcpdf`
   - Definiert die `render()` Methode, die von Subklassen implementiert werden muss
# wp-tc-lib-pdf — PDF Template System for WordPress

Kurz: dieses Plugin bietet ein leicht erweiterbares System zum Erzeugen von PDFs
mittels tc-lib-pdf (tecnickcom) in WordPress‑Umgebungen. Templates sind PHP‑Klassen
und können durch Traits modular erweitert werden.

## Anforderungen
- PHP >= 8.0 (oder kompatibel mit Ihrer tc-lib-pdf Version)
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

- Templates sind Klassen in `include/` und erben von `PdfTemplate`.
- Wiederverwendbare UI‑Bausteine sind als Traits implementiert (z. B.
  `trait-pdf-header.php`, `trait-pdf-rechnungsdaten.php`).
- Templates werden in `include/dispatch.php` bei Bedarf registriert.

Kurzes Beispiel (Registration):

```php
require_once __DIR__ . '/class-pdf-report.php';
PdfRegistry::register('report', 'PdfReport', __DIR__ . '/class-pdf-report.php');
```

## **Available PDFs**

Die im Dispatcher registrierten PDF‑Templates sind in `include/dispatch.php` konfiguriert.
Aktuelle IDs und Klassen (Auswahl):

- `rechnung_erlaubnis` — `PdfRechnungErlaubnis`
- `rechnung_merchandise` — `PdfRechnungMerchandise`
- `rechnung_antrag` — `PdfRechnungMitgliedsantrag`
- `rechnung_huette` — `PdfRechnungHuette`
- `rechnung_vorbereitungslehrgang` — `PdfRechnungVorbereitungsLehrgang2`
- `mahnung_erlaubnis` — `PdfMahnungErlaubnis`
- `mahnung_merchandise` — `PdfMahnungMerchandise`
- `mahnung_antrag` — `PdfRechnungMitgliedsantrag`
- `mahnung_huette` — `PdfMahnungHuette`
- `liste_arbeitsdienst` — `PdfListeArbeitsdienst`
- `liste_jugendveranstaltung` — `PdfListeJugendveranstaltung`
- `erlaubnisschein` — `PdfErlaubnisschein2`
- `fangstatistik` / `vorjahresvergleich` / `jahresvergleich` — Statistik‑Templates
- `anmeldung_lfvbw` — `PdfTemplateLFVBW`
- `mitgliedsantrag` / `mitgliedsantraginfo` — Antrags‑Templates

Prüfe die vollständige Liste in [include/dispatch.php](include/dispatch.php#L1).

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

## **Integration: Daten‑Contract (was Ihr Plugin bereitstellen sollte)**

Templates erwarten Form‑/Adressdaten in einem assoziativen Array. Es gibt zwei
Möglichkeiten, die Templates mit Daten zu versorgen:

- Direkter Ansatz: Übergib ein bereits vorbereitetes Array via
  `setFormdata($array)` und optional `setAddressdata($array)` bevor du
  `save()` oder `stream()` aufrufst.

- Konventioneller Ansatz: Templates versuchen beim Laden (siehe
  `loadData()` in `include/class-pdf-rechnung-merchandise.php`) automatisch, eine
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

Weitere Beispiele für die Produktion von Links und das Caching findet sich in
dem Beispiel‑Plugin [bfv-jubilaeumsruten](../bfv-jubilaeumsruten/bfv-jubilaeumsruten.php).


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

   ```

## Class Hierarchy

```
\Com\Tecnick\Pdf\Tcpdf (tc-lib-pdf)
    │
    └─→ PdfTemplate (Basis)
            │
            ├─→ PdfExample (+ PdfHeaderFooterTrait)
            │
            ├─→ PdfInvoice (+ PdfHeaderFooterTrait)
            │
            └─→ PdfSimple (keine Traits)
```

## Traits System (Mehrfachvererbung-Simulation)

```php
// Einfach
class PdfExample extends PdfTemplate {
    use PdfHeaderFooterTrait;
}

// Mehrere Traits kombinieren
class PdfCustom extends PdfTemplate {
    use PdfHeaderFooterTrait;
    use PdfWatermarkTrait;
    use PdfSignatureTrait;
}
```

## Security Flow

```
Request
   │
   ▼
Nonce Check (wp_verify_nonce)
   │
   ├─ Valid ─→ Continue
   │
   └─ Invalid ─→ wp_die() Error Page

   │
   ▼
Template Exists Check
   │
   ├─ Exists ─→ Instantiate
   │
   └─ Not Found ─→ Error Page

   │
   ▼
Type Check (instanceof PdfTemplate)
   │
   ├─ Valid ─→ Render
   │
   └─ Invalid ─→ Exception
```

## File Structure

```
wp-tc-lib-pdf/
├── wp-tc-lib-pdf.php                    # Plugin main file
├── include/
│   ├── dispatch.php                     # Router / Entry point
│   ├── class-pdf-template.php           # Base class
│   ├── class-pdf-registry.php           # Template registry
│   ├── trait-pdf-header-footer.php      # Header/Footer trait
│   ├── class-pdf-example.php            # Example template
│   ├── class-pdf-invoice.php            # Invoice template
│   ├── class-pdf-simple.php             # Simple template
│   └── pdf-template-examples.php        # Usage examples
├── PDF_TEMPLATES_README.md              # Documentation
└── ARCHITECTURE.md                      # This file
```

## Extension Points

### Neue Traits hinzufügen
```php
// include/trait-pdf-custom-feature.php
trait PdfCustomFeatureTrait {
    public function customMethod() { }
}

// Dann in Template verwenden
class MyTemplate extends PdfTemplate {
    use PdfHeaderFooterTrait;
    use PdfCustomFeatureTrait;
}
```

### Neue Templates hinzufügen
```php
// 1. Erstelle Klasse
// include/class-pdf-mycustom.php
class PdfMyCustom extends PdfTemplate { }

// 2. Registriere im Dispatcher
// include/dispatch.php
require_once __DIR__ . '/class-pdf-mycustom.php';
PdfRegistry::register('mycustom', 'PdfMyCustom', __DIR__ . '/class-pdf-mycustom.php');

// 3. Nutze URL
// ?demo_pdf=mycustom&nonce=XXX
```

## Performance-Tipps

1. **Lazy-Loading**: Templates werden nur geladen, wenn nötig
2. **Singleton Pattern**: PdfRegistry ist eine Singleton-Klasse
3. **Static Methods**: PdfRegistry nutzt statische Methoden (schneller)
4. **Error Handling**: Try-Catch verhindert Fatal Errors

## Debugging

```php
// In dispatch.php hinzufügen für Debug-Ausgabe
if ( WP_DEBUG ) {
    error_log( 'PDF Template: ' . $template_id );
    error_log( 'Template Config: ' . print_r( PdfRegistry::get( $template_id ), true ) );
}
```

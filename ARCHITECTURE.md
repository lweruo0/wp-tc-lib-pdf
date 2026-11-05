# PDF Template System - Architektur Übersicht

Stand: 2026-11-04. Beschreibt den aktuellen Code. Die Trennung in Engine
(`include/`), WordPress-Adapter (`wp-include/`) und den geplanten
Admidio-Adapter (`adm-include/`) ist in [KONZEPT-PLATTFORM.md](KONZEPT-PLATTFORM.md)
beschrieben; Phase 1 (Umzug) ist umgesetzt.

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress HTTP Request                       │
│   ?get_pdf=<id>&nonce=XXX              (kurzlebig, Nonce)        │
│   ?get_pdf=<id>&key=XXX&expires=Y-m-d  (langlebig, HMAC)         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│   wp-tc-lib-pdf.php (Plugin-Header)                              │
│   definiert TC_LIB_PDF_DIR, TC_LIB_PDF_BOOTSTRAPPED,              │
│   TC_LIB_PDF_WP_FILE und lädt wp-include/bootstrap.php           │
│                                                                  │
│   wp-include/bootstrap.php: Tc_Lib_Pdf_Wp_Bootstrap              │
│   ├─ plugins_loaded (10): init() → PHP-Check, Admin-Notices      │
│   ├─ plugins_loaded (11): templates.php + class-wp-dispatcher.php│
│   ├─ boot(): Composer-Autoloader + K_PATH_FONTS (lazy)           │
│   └─ build_pdf_url(): signierte URLs (nonce oder key+expires)    │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│   wp-include/templates.php: PdfRegistry::register(…) je Template │
│                                                                  │
│   wp-include/class-wp-dispatcher.php: Pdf_Dispatcher             │
│   1. init → should_render_pdf()                                  │
│      ├─ sanitize_text_field(wp_unslash($_GET['get_pdf']))        │
│      ├─ PdfRegistry::exists()                                    │
│      └─ verify_long_term_authorisation() ODER verify_nonce()     │
│   2. template_redirect → dispatch(): stream() oder output()      │
│   3. Fehler → wp_die()                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  PdfRegistry (statisch)                          │
│   • register(id, class, file)                                    │
│   • exists(id) / get(id) / getAll()                              │
│   • create(id): boot() → require_once file → new class()         │
│     Prüft: instanceof PdfOutputInterface                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  PdfTemplateBase (abstract)                      │
│   extends \Com\Tecnick\Pdf\Tcpdf, implements PdfOutputInterface  │
│   use PdfDataTrait                                               │
│   ├─ abstract loadData()   Daten in options/formdata/address     │
│   ├─ abstract render()     Seiteninhalt                          │
│   ├─ initialize()          Titel/Autor aus Daten                 │
│   ├─ stream() / output()   inline / attachment, Datei-Cache      │
│   └─ save()                PDF ins Upload-Verzeichnis            │
│                                                                  │
│                  PdfTemplate (abstract)                          │
│   extends PdfTemplateBase, Hilfsfunktionen (IBAN-Format u. a.)   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
                  Konkrete Templates (siehe unten)
```

## Workflow

1. **Link erzeugen** (aus anderem Plugin oder Dashboard-Widget)
   ```php
   $url = tc_lib_pdf_wp_create_pdf_url('rechnung_erlaubnis', ['nr' => '2026-P-0148'], '+1 year');
   // → https://…/?get_pdf=rechnung_erlaubnis&nr=2026-P-0148&key=…&expires=2027-11-02
   ```

2. **Dispatcher empfängt Request** (Hook `init`)
   - Sanitized `$_GET['get_pdf']`, prüft Registrierung
   - Prüft `key`+`expires` per `wp_hash(…, 'auth')` und `hash_equals()`,
     oder `nonce` per `wp_verify_nonce()`
   - Hängt bei Erfolg `dispatch()` an `template_redirect`

3. **Registry lädt Template**
   - `Tc_Lib_Pdf_Wp_Bootstrap::boot()` lädt Autoloader und Fonts (nur jetzt)
   - `require_once` der Template-Datei, Instanziierung

4. **Template rendert**
   - `loadData()`: Options per `get_option()`, Fachdaten über die
     `bfv*()`-Singletons anderer Plugins, URL-Parameter aus `$_GET`
   - Liegt die Datei bereits im Upload-Ordner und `new` ist nicht gesetzt,
     wird sie direkt ausgeliefert
   - Sonst `initialize()` → `render()` → `getOutPDFString()`

5. **PDF wird zum Browser gesendet**
   - `stream()`: `Content-Disposition: inline`
   - `output()`: `Content-Disposition: attachment` (Parameter `filedownload`)

## Class Hierarchy

```
\Com\Tecnick\Pdf\Tcpdf (tc-lib-pdf)
    │
    └─→ PdfTemplateBase (+ PdfDataTrait)
            │
            └─→ PdfTemplate
                    │
                    ├─→ PdfRechnungBase (abstract; 5 Traits, render(), prepareRechnung(), prepareMahnung())
                    │       ├─→ PdfRechnungErlaubnis ──→ PdfMahnungErlaubnis
                    │       ├─→ PdfRechnungMerchandise ──→ PdfMahnungMerchandise
                    │       ├─→ PdfRechnungHuette ──→ PdfMahnungHuette
                    │       ├─→ PdfRechnungMitgliedsantrag ──→ PdfMahnungMitgliedsantrag
                    │       └─→ PdfRechnungVorbereitungsLehrgang2 ──→ PdfMahnungVorbereitungslehrgang
                    │
                    ├─→ PdfErlaubnisschein2
                    ├─→ PdfListeArbeitsdienst
                    ├─→ PdfListeJugendveranstaltung
                    ├─→ PdfMitgliedsantragData (abstract)
                    │       ├─→ PdfMitgliedsantrag2
                    │       └─→ PdfMitgliedsantragInfoBlatt
                    └─→ PdfFangstatistik
                            ├─→ PdfFangstatistikJahr
                            ├─→ PdfFangstatistikMehrjahresvergleich
                            ├─→ PdfFangstatistikVorJahresvergleich
                            └─→ PdfFangstatistikUser

PdfOutputInterface (ohne tc-lib-pdf)
    │
    └─→ PdfTemplateLFVBW (+ PdfDataTrait)   Textersetzung in wp-include/templates/in.pdf
```

## Traits

| Trait | Datei | Zweck |
|---|---|---|
| `PdfDataTrait` | trait-pdf-data.php | Datencontainer options/formdata/addressdata/urldata, Storage-Pfad, Dateiname |
| `PdfHeaderTrait` | trait-pdf-header.php | Kopfzeile mit Logo/Titel, Fußzeile mit Zeitstempel |
| `PdfHeaderFooterTrait` | trait-pdf-header-footer.php | Kopf-/Fußzeile für Briefe (Rechnungen, Mahnungen) |
| `PdfAdressTrait` | trait-pdf-adress.php | Anschriftenfeld nach DIN 5008 |
| `PdfAbsenderTrait` | trait-pdf-absender.php | Absenderzeile über dem Anschriftenfeld |
| `PdfFalzmarkenTrait` | trait-pdf-falzmarken.php | Falz- und Lochmarken |
| `PdfRechnungsdatenTrait` | trait-pdf-rechnungsdaten.php | Rechnungsblock, Anschreiben, Positionen, Summen |
| `PdfTeilnehmerlisteTrait` | trait-pdf-teilnehmerliste.php | Tabellen für Teilnehmer-/Anmeldelisten |

Typische Kombinationen:

```php
// Rechnung / Mahnung: über die Rechnungsbasis, die alle fünf Brief-Traits bündelt
class PdfRechnungVorbereitungsLehrgang2 extends PdfRechnungBase {
    protected function loadData(): void {
        // Rohdaten holen …
        $formdata = $this->prepareRechnung($formdata, 'Rechnung Vorbereitungslehrgang');
        $formdata['texts_before']    = ['für den am ' . $formdata['date'] . ' angemeldeten … berechnen wir Ihnen:'];
        $formdata['rechnungsposten'] = [$this->posten('Theorieunterricht', '1x', 120.0, 120.0)];
        $this->setFormdata($formdata);
    }
}
class PdfMahnungVorbereitungslehrgang extends PdfRechnungVorbereitungsLehrgang2 {
    protected function loadData(): void {
        parent::loadData();
        $this->setFormdata($this->prepareMahnung($this->getAllFormdata(), 'Mahnung Vorbereitungslehrgang'));
    }
}

// Liste
class PdfListeArbeitsdienst extends PdfTemplate {
    use PdfHeaderTrait;
    use PdfTeilnehmerlisteTrait;
}
```

## Rechnungsbasis (PdfRechnungBase)

`include/class-pdf-rechnung-base.php`. Abstrakte Klasse für alle Rechnungen und
Mahnungen im Briefformat. Enthält, was in jedem Rechnungs-Template identisch
war:

| Methode | Zweck |
|---|---|
| `render()` / `renderRechnungSeite()` | Kopf (aus `header_name`/`url_verein` der Addressdata), Anschriftenfeld, Falzmarken, Absender, Rechnungsdaten-Block mit QR-Code, Anschreiben, Rechnungsblock |
| `prepareRechnung(array $raw, string $documenttype)` | Empfänger (aus `rechnung_*` oder neutralen Keys), Rücksendehinweis, Absender, Datum, Zahlungsfrist, Steuer-Label, `text_below` |
| `rechnungTextBelow(array)` | Standardtext unter dem Rechnungsblock (BGB-Hinweis); überschreibbar |
| `prepareMahnung(array, string $documenttype, ?bool $zweite, ?bool $ohneGebuehr)` | Zahlungserinnerungs-Überschrift, Mahngebühr aus Option `mahngebuehr` (doppelt bei zweiter Mahnung), erhöht `brutto`, setzt Texte. Bei `brutto` 0,00 unverändert. `null` liest die URL-Parameter `zweitemahnung`/`ohnemahngebuehr` |
| `mahnungTextsBefore(array)` / `mahnungTextBelow(array)` | Mahnungstexte; überschreibbar |
| `formatBetrag(float)` / `posten(...)` | Hilfsfunktionen für Beträge und Rechnungsposten |

Ein konkretes Template implementiert nur `loadData()`. Die Basis ruft keine
WordPress- oder Admidio-Funktion auf und ist damit auf beiden Hosts nutzbar.
Alle fünf WordPress-Rechnungs-Templates laufen auf der Basis.

## Registrierte Templates

Siehe [wp-include/templates.php](wp-include/templates.php). Template-ID →
Klasse → Datei (alle in `wp-include/templates/`):

| ID | Klasse | Datei |
|---|---|---|
| `rechnung_erlaubnis` | PdfRechnungErlaubnis | class-pdf-rechnung-erlaubnis.php |
| `mahnung_erlaubnis` | PdfMahnungErlaubnis | class-pdf-rechnung-erlaubnis.php |
| `rechnung_merchandise` | PdfRechnungMerchandise | class-pdf-rechnung-merchandise.php |
| `mahnung_merchandise` | PdfMahnungMerchandise | class-pdf-rechnung-merchandise.php |
| `rechnung_antrag` | PdfRechnungMitgliedsantrag | class-pdf-rechnung-mitgliedsantrag.php |
| `mahnung_antrag` | PdfMahnungMitgliedsantrag | class-pdf-rechnung-mitgliedsantrag.php |
| `rechnung_huette` | PdfRechnungHuette | class-pdf-rechnung-huette.php |
| `mahnung_huette` | PdfMahnungHuette | class-pdf-rechnung-huette.php |
| `rechnung_vorbereitungslehrgang` | PdfRechnungVorbereitungsLehrgang2 | class-pdf-rechnung-vorbereitungslehrgang.php |
| `erlaubnisschein` | PdfErlaubnisschein2 | class-pdf-erlaubnisschein.php |
| `liste_arbeitsdienst` | PdfListeArbeitsdienst | class-pdf-liste-arbeitsdienst.php |
| `liste_jugendveranstaltung` | PdfListeJugendveranstaltung | class-pdf-liste-jugendveranstaltung.php |
| `fangstatistik` | PdfFangstatistikJahr | class-pdf-fangstatistik.php |
| `vorjahresvergleich` | PdfFangstatistikVorJahresvergleich | class-pdf-fangstatistik.php |
| `jahresvergleich` | PdfFangstatistikMehrjahresvergleich | class-pdf-fangstatistik.php |
| `fangstatistikuservergleich` | PdfFangstatistikUser | class-pdf-fangstatistik.php |
| `mitgliedsantrag` | PdfMitgliedsantrag2 | class-pdf-mitgliedsantrag.php |
| `mitgliedsantraginfo` | PdfMitgliedsantragInfoBlatt | class-pdf-mitgliedsantrag.php |
| `anmeldung_lfvbw` | PdfTemplateLFVBW | class-pdf-anmeldunglfvwb.php |

## Datenfluss in loadData()

```
URL-Parameter ($_GET)           → urldata      getUrl('nr')
WP-Options (get_option)         → options      getOption('mahngebuehr')
WP-Option bfv_adressen          → addressdata  getAddress('name_verein')
bfv*()->get_formdata_by_…()     → formdata     getForm('brutto')
```

Die Fachdaten kommen aus den Singletons anderer bfv-Plugins
(`bfverlaubnisscheine()`, `bfvmitgliedsantrag()`, `bfvfangbuch()`,
`bfvarbeitsdienst()`, `bfvjugend()`, `bfvjubilaeumsruten()`, `bfvmerchandise()`,
`bfvfischerhuette()`, `bfvvorbereitungslehrgang()`). Jeder Aufruf ist mit
`function_exists()` abgesichert und fällt auf leere Arrays zurück.

## Security Flow

```
Request ?get_pdf=<id>
   │
   ▼
PdfRegistry::exists(id) ─── nein ─→ kein Hook, WordPress läuft normal weiter
   │ ja
   ▼
key + expires vorhanden?
   ├─ ja → expires nicht abgelaufen (wp_timezone)
   │        && hash_equals(wp_hash(signature_payload(id, params, expires), 'auth'), key)
   │           ├─ gültig ─→ dispatch()
   │           ├─ altes Format (nur id.expires.nr, kein weiterer signierter
   │           │  Parameter im Request, TC_LIB_PDF_LEGACY_KEYS ≠ false) ─→ dispatch()
   │           └─ ungültig ─→ weiter mit nonce
   ▼
nonce vorhanden?
   ├─ ja → wp_verify_nonce(nonce, signature_payload(id, params, ''))
   │           ├─ gültig ─→ dispatch()
   │           └─ ungültig ─→ kein Hook
   └─ nein ─→ kein Hook

dispatch()
   ├─ PdfRegistry::create() wirft Exception ─→ wp_die(…, 500)
   └─ stream() / output() ─→ exit
```

`signature_payload()` (in `Tc_Lib_Pdf_Wp_Bootstrap`) verbindet Template-ID,
Ablaufdatum und alle Parameter aus `Tc_Lib_Pdf_Wp_Bootstrap::SIGNED_PARAMS`
(`nr`, `mnr`, `jahr`, `dienst`, `veranstaltung`, `Mitgliedsnummer`, `verein`,
`zweitemahnung`, `ohnemahngebuehr`) in fester Reihenfolge, URL-kodiert und mit
`|` getrennt. Fehlende Parameter gehen leer ein. Ein Request, dessen Rohwert
sich durch `sanitize_text_field()` ändern würde, wird abgewiesen. Nicht signiert
sind nur Darstellungs-Schalter (`new`, `filedownload`, `layout`, `page`,
`SortByGew`).

Ohne gültige Autorisierung wird kein Fehler ausgegeben. Der Request läuft als
normaler WordPress-Request weiter.

## Storage

- Basis: `wp_upload_dir()['basedir']` + Ordnername aus `createStorageFolder('bfv_…')`
- Beim Anlegen werden `.htaccess` (Require all denied) und `index.php` geschrieben
- `stream()`/`output()` liefern eine vorhandene Datei direkt aus, außer `?new=1`
- `save()` erzeugt die Datei ohne HTTP-Ausgabe (für Aufrufe aus anderen Plugins)

## File Structure

```
wp-tc-lib-pdf/
├── wp-tc-lib-pdf.php                    Plugin-Header, definiert TC_LIB_PDF_*-Konstanten, lädt wp-include/bootstrap.php
├── uninstall.php                        leer
│
├── include/                             ENGINE – plattformneutral, prüft nur TC_LIB_PDF_BOOTSTRAPPED
│   ├── class-pdf-registry.php           PdfRegistry
│   ├── class-pdf-template.php           PdfTemplateBase, PdfTemplate
│   ├── class-pdf-rechnung-base.php      PdfRechnungBase (Rechnung/Mahnung)
│   ├── interface-pdf-output.php         PdfOutputInterface
│   ├── trait-pdf-*.php                  8 Traits (siehe oben)
│   └── images/                          Logos, Unterschrift (vereinsweit)
│
├── wp-include/                          HOST WordPress, prüft ABSPATH
│   ├── bootstrap.php                    Tc_Lib_Pdf_Wp_Bootstrap, Hooks, URL-Builder, Hilfsfunktionen
│   ├── class-wp-dispatcher.php          Pdf_Dispatcher (Nonce, Langzeit-Link, template_redirect)
│   ├── templates.php                    PdfRegistry::register(…) für alle WP-Templates
│   ├── class-dashboard-pdf-links.php    Dashboard-Widget mit Beispiel-Links (nur Admin)
│   └── templates/
│       ├── class-pdf-*.php              Templates (siehe oben); laden Engine-Dateien über TC_LIB_PDF_DIR
│       └── in.pdf                       LFVBW-Vorlage für PdfTemplateLFVBW
│
├── font/core/                           Core-Font-Metriken (JSON), K_PATH_FONTS
├── vendor/                              tc-lib-pdf und Abhängigkeiten (Composer, versioniert)
├── stubs/                               PHPStan-Stubs (Quform, WP_User-Felder)
├── phpstan.neon                         prüft include/ und wp-include/
├── readme.md                            Installation, Integration, Daten-Contract
├── ARCHITECTURE.md                      diese Datei
└── KONZEPT-PLATTFORM.md                 WP/Admidio-Trennung, Phasenplan
```

Konstanten, die der Host vor dem Laden der Engine definiert:

| Konstante | Bedeutung |
|---|---|
| `TC_LIB_PDF_DIR` | Plugin-Root; Templates laden Engine-Dateien über `TC_LIB_PDF_DIR . '/include/…'` |
| `TC_LIB_PDF_BOOTSTRAPPED` | Lade-Guard der Engine-Dateien (ersetzt `ABSPATH` dort) |
| `TC_LIB_PDF_WP_FILE` | Pfad der Plugin-Hauptdatei für `register_activation_hook()` (nur WordPress) |

## Extension Points

### Neues Template

```php
// 1. wp-include/templates/class-pdf-mycustom.php
require_once TC_LIB_PDF_DIR . '/include/class-pdf-template.php';
require_once TC_LIB_PDF_DIR . '/include/trait-pdf-header.php';

class PdfMyCustom extends PdfTemplate {
    use PdfHeaderTrait;

    public function __construct() {
        parent::__construct();
        $this->initializeUrlData();
    }

    protected function loadData(): void {
        $this->setAddressdata(get_option('bfv_adressen'));
        $this->setFormdata(['documenttype' => 'Mein Dokument']);
    }

    protected function render(): void {
        $this->addPage();
        // …
    }
}

// 2. wp-include/templates.php
PdfRegistry::register('mycustom', 'PdfMyCustom', $tc_lib_pdf_templates . '/class-pdf-mycustom.php');

// 3. Link
$url = tc_lib_pdf_wp_create_pdf_url('mycustom', ['nr' => '123']);
```

### Neuer Trait

```php
// include/trait-pdf-custom-feature.php
trait PdfCustomFeatureTrait {
    public function add_custom_feature(): void { /* … */ }
}
```

Der Trait wird in der Template-Datei per `require_once` geladen und mit `use`
eingebunden. Traits dürfen nur auf tc-lib-pdf und die Getter des `PdfDataTrait`
zugreifen, nicht auf WordPress-Funktionen (Vorbereitung auf die
Plattformtrennung).

## Performance

- Composer-Autoloader und Fonts werden erst in `PdfRegistry::create()` geladen,
  nicht bei jedem WordPress-Request
- Templates werden per `require_once` erst bei Bedarf geladen
- Fertige PDFs werden aus dem Upload-Ordner ausgeliefert, wenn vorhanden
- `tc_lib_pdf_wp_create_pdf_url()` benötigt tc-lib-pdf nicht

## Debugging

```php
// In dispatch() ergänzen
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('PDF Template: ' . $template_id);
    error_log('Template Config: ' . print_r(PdfRegistry::get($template_id), true));
}
```

Ein Template ohne Datei-Cache erzwingen: `&new=1` an die URL anhängen.

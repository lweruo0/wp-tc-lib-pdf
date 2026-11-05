# Konzept: Gemeinsame PDF-Engine für WordPress und Admidio

Stand: 2026-11-05

## 0. Zielsetzung

Die PDF-Engine dieses Plugins (Basisklassen, Layout-Traits, Registry,
Dispatcher-Logik, Fonts, Datencontainer) soll in WordPress **und** in Admidio
5.0.17 laufen. Beide Hosts erzeugen dabei **unterschiedliche Dokumente** mit
eigener Datenbeschaffung und eigenen Nummernkreisen. Es werden keine PDFs
plattformübergreifend geteilt.

Daraus folgt der Zuschnitt:

| | Engine (`include/`) | Host (`wp-include/`, `adm-include/`) |
|---|---|---|
| Enthält | Basisklassen, Traits, Registry, Dispatcher-Kern, Plattform-Schnittstelle, Datencontainer, Fonts, Vereins-Assets (Logos) | Bootstrap, Einstiegspunkt, Plattform-Implementierung, Nonce/CSRF, **eigene Templates**, eigene Template-Registrierung, Dashboard/Menü |
| Kennt | kein WordPress, kein Admidio | seine Host-API direkt |
| Wird geändert | selten, wirkt auf beide Hosts | je Host unabhängig |

Die WordPress-Templates bleiben inhaltlich wie sie sind. Sie rufen weiter
`get_option()` und die `bfv*()`-Singletons auf, ziehen aber nach
`wp-include/templates/` um. Die Admidio-Templates entstehen neu in
`adm-include/templates/` und greifen direkt auf `$gDb`, `$gProfileFields` usw.
zu. Eine plattformübergreifende Daten-Abstraktion (Provider-Interfaces) ist
damit **nicht** nötig.

## 1. Ist-Zustand: Was die Engine heute an WordPress bindet

Nur Engine-Dateien zählen. Die WordPress-Aufrufe in den Templates sind kein
Problem, weil die Templates host-spezifisch bleiben.

| # | Kopplung | Datei | Was |
|---|----------|-------|-----|
| 1 | Lade-Guard | alle Dateien in `include/` | ~~`defined('ABSPATH')`~~ → `TC_LIB_PDF_BOOTSTRAPPED` (erledigt in Phase 1) |
| 2 | Request | [trait-pdf-data.php:361](include/trait-pdf-data.php#L361) | `initializeUrlData()` liest aus `$_GET` |
| 3 | Storage | [trait-pdf-data.php:378-399](include/trait-pdf-data.php#L378-L399), `save()` in [class-pdf-template.php:183](include/class-pdf-template.php#L183) | `wp_upload_dir()`, `wp_mkdir_p()` |
| 4 | Zeitzone | [trait-pdf-header.php:102](include/trait-pdf-header.php#L102), [:136](include/trait-pdf-header.php#L136) | `get_option('timezone_string')` + `date_default_timezone_set()` |
| 5 | Vereins-Texte | [trait-pdf-header.php:139-141](include/trait-pdf-header.php#L139-L141), [trait-pdf-rechnungsdaten.php:228](include/trait-pdf-rechnungsdaten.php#L228) | `© Bezirksfischerei-Verein e.V. Ehingen/Donau`, `Ehingen, den` hart codiert |
| 6 | Dispatcher | [wp-include/class-wp-dispatcher.php](wp-include/class-wp-dispatcher.php) | Routing und Langzeit-Hash (neutral) vermischt mit Hooks, Nonce, `wp_die` (WP); seit Phase 1 im Host-Ordner, Herauslösen des Kerns in Phase 2 |

Alle anderen Traits (Absender, Adressfeld, Falzmarken, Rechnungsblock,
Teilnehmerliste, Header/Footer für Briefe) lesen ausschließlich über
`getAddress()`, `getOption()`, `getForm()` und sind bereits neutral.

## 2. Zielarchitektur

### 2.1 Ordnerstruktur

```
wp-tc-lib-pdf/
├── wp-tc-lib-pdf.php              WP-Plugin-Header, lädt nur wp-include/bootstrap.php
│
├── include/                       ENGINE – plattformneutral
│   ├── platform/
│   │   ├── interface-pdf-platform.php   Storage, Request, Zeitzone, Secret, fail()
│   │   └── class-pdf-runtime.php        statischer Halter: PdfRuntime::platform()
│   ├── class-pdf-dispatcher-core.php    Routing + Langzeit-Hash, ohne Hooks (neu, aus dispatch.php)
│   ├── class-pdf-registry.php           (bleibt; setzt urldata aus getQueryParams())
│   ├── class-pdf-template.php           PdfTemplateBase, PdfTemplate (bleibt)
│   ├── interface-pdf-output.php         (bleibt)
│   ├── trait-pdf-absender.php
│   ├── trait-pdf-adress.php
│   ├── trait-pdf-data.php               → Storage/$_GET über PdfRuntime::platform()
│   ├── trait-pdf-falzmarken.php
│   ├── trait-pdf-header.php             → Zeitzone über Plattform, Footer-Text aus Options
│   ├── trait-pdf-header-footer.php
│   ├── trait-pdf-rechnungsdaten.php     → Ortsname aus Addressdata
│   ├── trait-pdf-teilnehmerliste.php
│   └── images/                          Logos, Unterschrift (vereinsweit, beide Hosts)
│
├── wp-include/                    HOST WordPress
│   ├── bootstrap.php              heutige Tc_Lib_Pdf_Wp_Bootstrap + Hooks + URL-Builder
│   ├── class-wp-platform.php      wp_upload_dir, wp_mkdir_p, wp_timezone, wp_salt, wp_die
│   ├── class-wp-dispatcher.php    Hooks init/template_redirect, Nonce; delegiert an DispatcherCore
│   ├── class-dashboard-pdf-links.php
│   ├── templates.php              PdfRegistry::register(…) für alle WP-Templates
│   └── templates/                 heutige class-pdf-*.php (unverändert, nur verschoben)
│       ├── class-pdf-rechnung-erlaubnis.php
│       ├── class-pdf-erlaubnisschein.php
│       ├── class-pdf-fangstatistik.php
│       ├── …
│       └── in.pdf                 LFVBW-Vorlage (nur WP)
│
├── adm-include/                   HOST Admidio
│   ├── bootstrap.php              lädt system/common.php (dirname(__DIR__, 3)), definiert K_PATH_FONTS
│   ├── pdf.php                    Einstieg: /adm_plugins/wp-tc-lib-pdf/adm-include/pdf.php?get_pdf=…
│   ├── config_sample.php          Vorlage für config.php (Secret, Nummernkreis, Mapping); config.php gitignored
│   ├── class-adm-platform.php     adm_my_files, FileSystemUtils, $gTimezone, Secret aus config
│   ├── class-adm-dispatcher.php   $gValidLogin/Rollenprüfung, CSRF-Token; delegiert an DispatcherCore
│   ├── templates.php              PdfRegistry::register(…) für alle Admidio-Templates
│   └── templates/                 neue Templates, z. B. class-pdf-adm-rechnung-….php
│
├── font/core/                     gemeinsam
└── vendor/                        gemeinsam, versioniert
```

### 2.2 Plattform-Schnittstelle

Klein, weil die Engine nur noch Infrastruktur braucht. Einstellungen lesen die
Templates direkt über ihre Host-API; die Engine braucht keinen Settings-Zugriff.

```php
interface PdfPlatformInterface {
    // Storage
    public function getStorageBaseDir(): string;        // WP: wp_upload_dir()['basedir']; Adm: ADMIDIO_PATH.FOLDER_DATA.'/plugins/wp-tc-lib-pdf'
    public function createDirectory(string $path): bool; // WP: wp_mkdir_p; Adm: FileSystemUtils::createDirectoryIfNotExists
    public function protectDirectory(string $path): void; // WP: .htaccess + index.php; Adm: nichts (adm_my_files ist gesperrt)

    // Request – bereits vom Host-Dispatcher sanitized
    public function getQueryParams(): array;

    // Zeit
    public function getTimezone(): DateTimeZone;        // WP: wp_timezone(); Adm: new DateTimeZone($gTimezone)

    // Signierte Langzeit-Links
    public function getSecret(): string;                // WP: wp_salt('auth'); Adm: Secret aus config.php

    // Fehlerausgabe
    public function fail(string $message, int $status = 500): never; // WP: wp_die; Adm: $gMessage->show() oder 500+exit

    public function getPluginDir(): string;             // Repo-Root, ersetzt plugin_dir_path()
}
```

### 2.3 Dispatcher-Kern

Aus `dispatch.php` wird der neutrale Teil herausgelöst:

```php
final class PdfDispatcherCore {
    public function resolveTemplateId(array $query): ?string;          // get_pdf prüfen, PdfRegistry::exists()
    public function verifyLongTermKey(array $query): bool;             // key + expires, HMAC mit getSecret()
    public function render(string $templateId, array $query): never;   // PdfRegistry::create → stream()/output() → exit
}
```

Der Host-Dispatcher liefert nur noch den Einstieg und die kurzlebige
Autorisierung:

| | WordPress | Admidio |
|---|---|---|
| Einstieg | Hook `init` → `template_redirect` | direkter Aufruf von `adm-include/pdf.php` |
| Interne Nutzer | `wp_verify_nonce($nonce, id.nr.jahr)` (an Benutzersession gebunden) | `$gValidLogin && $gCurrentUser->isAdministrator()`, kein Token nötig |
| Externe Empfänger | `PdfDispatcherCore::verifyLongTermKey()` | identisch |
| Sanitizing | `sanitize_text_field(wp_unslash(...))` | `admFuncVariableIsValid($_GET, …)` |

Ablauf im Admidio-Dispatcher:

```
key + expires vorhanden und verifyLongTermKey() gültig  → render()
sonst: eingeloggt und Administrator                      → render()
sonst: eingeloggt, kein Administrator                    → 403 über $gMessage->show()
sonst: nicht eingeloggt                                  → Weiterleitung zur Anmeldung (login_valid.php)
```

Ein kurzlebiger Token wie in WordPress entfällt in Admidio. Die Anzeige eines
PDFs ändert keinen Zustand, daher ist kein CSRF-Schutz erforderlich; die
Rollenprüfung genügt. Admidio-Seiten verlinken PDFs für Administratoren
schlicht mit `?get_pdf=<id>&nr=…`, für Externe mit `key` + `expires`.

**Rückwärtskompatibilität:** `wp_hash($data, 'auth')` ist intern
`hash_hmac('md5', $data, wp_salt('auth'))`. Rechnet `verifyLongTermKey()` genau
das mit `getSecret()`, bleiben alle bestehenden WordPress-Links gültig. Da
Admidio ein anderes Secret und andere Template-IDs hat, kann ein Link des einen
Hosts im anderen nie gültig sein.

### 2.4 Templates je Host

Ein Template ist weiterhin eine Klasse, die `PdfTemplate` erweitert, Traits
einbindet und `loadData()` sowie `render()` implementiert. Der einzige
Unterschied zwischen den Hosts ist der Inhalt von `loadData()`:

```php
// wp-include/templates/class-pdf-rechnung-erlaubnis.php – wie heute
protected function loadData(): void {
    $nr = $this->getUrl('nr', '');
    $this->setOptions(get_option('bfv_erlaubnisschein'));
    $this->setAddressdata(get_option('bfv_adressen'));
    $formdata = bfverlaubnisscheine()->get_formdata_by_rechnungsnummer($nr);
    …
}

// adm-include/templates/class-pdf-adm-rechnung-beitrag.php – neu
protected function loadData(): void {
    global $gDb, $gCurrentOrgId;
    $nr = $this->getUrl('nr', '');
    $this->setAddressdata(AdmConfig::adresse());          // aus adm-include/config.php
    $row = $gDb->queryPrepared('SELECT … FROM adm_plg_bfv_rechnungen WHERE rec_nr = ? AND rec_org_id = ?', [$nr, $gCurrentOrgId])->fetch();
    …
}
```

Die Layout-Traits (`PdfRechnungsdatenTrait`, `PdfAdressTrait`,
`PdfFalzmarkenTrait` …) sorgen dafür, dass Briefe aus beiden Hosts gleich
aussehen, obwohl sie aus unterschiedlichen Daten entstehen. Ein Admidio-Template
ohne WordPress-Gegenstück ist ausdrücklich der Normalfall.

Der Daten-Contract der Traits (Keys in `addressdata` wie `name_verein`,
`iban_verein`, Keys in `formdata` wie `rechnungsposten`, `brutto`,
`zahlungsfrist`) ist in der [readme.md](readme.md) dokumentiert und gilt für
beide Hosts.

**Gemeinsame Rechnungsbasis (umgesetzt 2026-11-03).** Die fünf WordPress-
Rechnungstemplates waren zu etwa 60 % identisch: dieselben fünf Traits,
derselbe Konstruktor, dasselbe `render()`, dieselbe Aufbereitung von
Empfängeradresse, Absender, Datum, Zahlungsfrist und Steuer-Bezeichnung, und
eine fünfmal kopierte Mahnungs-Logik. Dieser Anteil liegt jetzt in
[include/class-pdf-rechnung-base.php](include/class-pdf-rechnung-base.php):

| Methode | Zweck |
|---|---|
| `renderRechnungSeite()` | Seitenaufbau; Kopfzeile aus `header_name` / `url_verein` (Addressdata) statt hart codiert |
| `prepareRechnung(array $raw, string $documenttype)` | Standardfelder ableiten; liest Empfänger aus `rechnung_*` (WordPress) oder neutralen Keys `first_name`, `street`, … (Admidio) |
| `rechnungTextBelow()` | BGB-Hinweis, überschreibbar |
| `prepareMahnung(array, string, ?bool $zweite, ?bool $ohneGebuehr)` | Mahngebühr aus Option `mahngebuehr`, Texte, Überschrift; `null` = URL-Parameter auswerten |
| `mahnungTextsBefore()` / `mahnungTextBelow()` | überschreibbar |
| `formatBetrag()` / `posten()` | Hilfsfunktionen |

Das Vorbereitungslehrgang-Template ist als erstes umgestellt (von 257 auf
rund 120 Zeilen, davon die Hälfte Rechnungsposten) und per CLI-Smoke-Test ohne
WordPress gerendert. Der Mitgliedsantrag folgte als zweites; dabei wurde sein
ad-hoc Sammeldokument über mehrere Mitgliedsnummern entfernt, es ist jetzt eine
normale Einzelrechnung mit eigener Mahnungsklasse. Die Basis enthält keine
WordPress-Funktion. Erlaubnis, Merchandise und Hütte folgten am 2026-11-04;
damit laufen alle fünf WordPress-Rechnungstemplates auf der Basis und die
Mahnungslogik existiert nur noch einmal.

Ein Admidio-Template implementiert damit nur `loadData()`: Buchungspositionen
holen, Empfänger aus Profilfeldern auf die neutralen Keys mappen,
`prepareRechnung()`, `rechnungsposten` und `texts_before` setzen, Dateiname
setzen.
### 2.5 Vereins-Texte aus den Traits herauslösen

Drei hart codierte Stellen werden datengetrieben, damit die Engine nicht an den
Verein gebunden ist und jeder Host seine Werte setzt:

| Stelle | heute | künftig |
|---|---|---|
| trait-pdf-rechnungsdaten.php:228 | `'Ehingen, den '` | `$this->getAddress('ort_verein_kurz', '') . ', den '` |
| trait-pdf-header.php:139-141 | `'© Bezirksfischerei-Verein e.V. Ehingen/Donau …'` | `$this->getOption('footer_copyright', '')` |
| trait-pdf-header.php:102-104, 136-137 | `get_option('timezone_string')` + `date_default_timezone_set()` | `DateTimeImmutable` mit `PdfRuntime::platform()->getTimezone()` |

WordPress setzt diese Werte aus `bfv_adressen`, Admidio aus seiner `config.php`.

### 2.6 Admidio-Seite (5.0.17)

Geprüft gegen den Quellcode zum Tag `v5.0.17`. Admidio 5 hat `adm_program/`
aufgelöst; Systemdateien liegen unter `system/`, Klassen namespaced unter
`src/` (`Admidio\…`).

**Plugin-Einstieg** (Muster aus `adm_plugins/who-is-online/index.php`):

```php
$rootPath = dirname(__DIR__, 2);                 // index.php direkt im Plugin-Ordner
require_once($rootPath . '/system/common.php');
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}
```

Unser Einstieg liegt eine Ebene tiefer (`adm-include/pdf.php`), also
`dirname(__DIR__, 3)`. Davor ein Guard: Existiert `system/common.php` nicht,
läuft die Datei in WordPress und beendet sich sofort.

**Globals aus `common.php`:**

| Global | Klasse | Verwendung |
|---|---|---|
| `$gDb` | `Admidio\Infrastructure\Database` | SQL in den Admidio-Templates |
| `$gSettingsManager` | `Admidio\Preferences\ValueObject\SettingsManager` | optional für Einstellungen |
| `$gCurrentUser` | `Admidio\Users\Entity\User` | Rollenprüfung, Mitgliedsdaten |
| `$gCurrentSession` | `Admidio\Session\Entity\Session` | `getCsrfToken()` |
| `$gProfileFields` | `Admidio\ProfileFields\ValueObjects\ProfileFields` | Profilfelder (Mitgliedsnummer u. ä.) |
| `$gMessage` | `Admidio\UI\Component\Message` | `fail()` |
| `$gValidLogin`, `$gCurrentOrgId` | bool, int | Login, Mandant |

Konstanten aus `system/bootstrap/constants.php`: `ADMIDIO_PATH`, `ADMIDIO_URL`,
`FOLDER_DATA = '/adm_my_files'`, `FOLDER_PLUGINS = '/adm_plugins'`,
`FOLDER_SYSTEM = '/system'`. `$gTimezone` (PHP-Zeitzonenname) kommt aus
`adm_my_files/config.php`.

**Fonts und Bibliotheken.** Admidio 5.0.17 bündelt `tecnickcom/tcpdf` 6.11.4,
aber keine `tc-lib-*`-Pakete. Keine Namespace-Kollision: TCPDF ist die globale
Klasse `TCPDF`, tc-lib-pdf liegt unter `Com\Tecnick\Pdf`. `K_PATH_FONTS`
definiert TCPDF erst, wenn seine Klassendatei per Autoloader geladen wird, was
in einem PDF-Request unseres Plugins nicht passiert. Absicherung:
`adm-include/bootstrap.php` definiert `K_PATH_FONTS` auf `font/core` direkt
nach `common.php`.

**Autorisierung (entschieden):** Zugriff nur für Administratoren sowie über
signierte Langzeit-Links für externe Empfänger. Der Dispatcher prüft zuerst den
Langzeit-Link. Schlägt das fehl, bindet er
`ADMIDIO_PATH . FOLDER_SYSTEM . '/login_valid.php'` ein (leitet nicht
eingeloggte Benutzer zur Anmeldung um) und prüft anschließend
`$gCurrentUser->isAdministrator()`. Eine feinere Rollenprüfung ist nicht
vorgesehen. Externe Empfänger brauchen kein Admidio-Konto.

Für die Link-Erzeugung auf Admidio-Seiten bekommt `adm-include/` ein
Gegenstück zu `tc_lib_pdf_wp_create_pdf_url()`: ohne `$expires` entsteht ein
Link ohne Token (funktioniert nur für eingeloggte Administratoren), mit
`$expires` ein signierter Langzeit-Link.

### 2.7 Erste Admidio-Dokumente: Beitrags- und Arbeitsdienstrechnung

> Ausgearbeitet in [KONZEPT-ADMIDIO-RECHNUNGEN.md](KONZEPT-ADMIDIO-RECHNUNGEN.md)
> (Rechnungskopf, Versandwege, Mahnung, Umsetzungsreihenfolge). Bei
> Abweichungen gilt jenes Dokument. Wichtigste Abweichungen von diesem
> Abschnitt (Stand 2026-11-05):
>
> - Aufnahmerechnung ist eine Beitragsrechnung über einmalige Beitragsrollen;
>   die WordPress-Rechnung zum Mitgliedsantrag zieht dorthin um.
> - Rechnungskopf als eigene Tabelle im Fork (entschieden); `mfb_invoice_date`
>   entfällt, an den Positionen bleibt nur `mfb_invoice_no`.
> - Zahlungsziel aus den Einstellungen des Fork statt fest 14 Tage.
> - Nur eine Mahnstufe; die Mahngebühr wird manuell als Position angelegt.
> - Keine Gutschriften; Storno ganzer Rechnungen als Vermerk im PDF.
> - Änderungen am Fork werden nur als TODO gesammelt
>   (KONZEPT-ADMIDIO-RECHNUNGEN.md, Abschnitt 10).

Entschieden: In Admidio entstehen zuerst **Beitragsrechnungen** und
**Arbeitsdienstrechnungen**, jeweils mit Mahnung. Vorlage ist das
Vorbereitungslehrgang-Template, bevorzugt über die Rechnungsbasis aus 2.4.

**Die Rechnungsverwaltung liegt beim Plugin Mitgliedsbeitrag** (Fork
[lweruo0/Mitgliedsbeitrag](https://github.com/lweruo0/Mitgliedsbeitrag),
Basis rmbinder 6.0.3, erweitert um Buchungshistorie 6.1, gespeicherte
SEPA-Läufe 6.2 und Arbeitsdienst 6.4). Das PDF-Plugin legt **keine eigenen
Tabellen** in Admidio an. Die Templates lesen ausschließlich aus dem
Datenmodell des Mitgliedsbeitrag-Plugins.

**Datenmodell des Mitgliedsbeitrag-Forks, soweit für die PDFs relevant:**

| Quelle | Inhalt | Zugriff |
|---|---|---|
| `adm_plugin_membershipfee_bookings` | eine Zeile je Buchungsposition: `mfb_usr_id`, `mfb_year`, `mfb_source` (`rolle`, `individual`, `manuell`, `ad`, `ad_manuell`), `mfb_rol_name`, `mfb_text` (140 Z.), `mfb_amount`, `mfb_status` (`offen`, `exportiert`, `eingezogen`, `storniert`), `mfb_method` (`SEPA`/`MAN`), `mfb_due_date`, `mfb_booking_date`, `mfb_reference` (SEPA-EndToEnd-ID), `mfb_run_id` | `BookingStore::getByUser($usrId, $status, $year)`, dann `CollectionScope::membership($orgId)->filterPositions()` bzw. `::work($orgId)` |
| `adm_plugin_membershipfee_workhours` | Arbeitseinsätze: `mfw_usr_id`, `mfw_year`, `mfw_date`, `mfw_hours`, `mfw_activity` | `WorkHoursStore::getByYear($year, $usrId)`, `::sumByUser($year)` |
| Profilfelder (Kategorie Mitgliedsbeitrag) | `FEE{org}`, `CONTRIBUTORY_TEXT{org}`, `DUEDATE{org}`, `PAID{org}`, `MEMBERNUMBER{org}`, `DEBTOR*`, `IBAN`, `MANDATEID{org}` | `$gProfileFields`, `User::getValue()`; Namen über `CollectionScope::field(FIELD_FEE)` usw. |
| Profilfelder (Kategorie Arbeitsdienst) | `ADTARGET{org}` (Sollstunden), `ADFEE`, `ADTEXT`, `ADDUEDATE`, `ADPAID` | `WorkProfileFields::targetField($orgId)`, `CollectionScope::work()` |
| Konfiguration | `Kontodaten` (Bank, Inhaber, IBAN, BIC, Gläubiger-ID), `Beitrag.beitrag_prefix`, `Arbeitsdienst.stundensatz`, `altersgrenze`, `soll_rollen`/`soll_stunden` | `(new ConfigTable())->read()`, dann `->config['…']` |
| Zugriffsrecht | Menüpunkt-Rollen des Plugins | `isUserAuthorized()` aus `system/common_function.php` |

Klassenladung: Der Fork registriert in `system/common_function.php` einen
eigenen Autoloader für den Namespace `Plugins\…`. Das Admidio-Template bindet
deshalb vor dem ersten Klassenzugriff
`ADMIDIO_PATH . FOLDER_PLUGINS . '/Mitgliedsbeitrag/system/common_function.php'`
ein und prüft dessen Existenz; fehlt das Plugin, bricht `loadData()` mit
klarer Fehlermeldung ab. Der Ordnername des Mitgliedsbeitrag-Plugins steht in
`adm-include/config.php`.

**Rechnung = Positionen eines Mitglieds, Jahres und Buchungskreises.**

| | Beitragsrechnung | Arbeitsdienstrechnung |
|---|---|---|
| Template-ID | `adm_rechnung_beitrag`, `adm_mahnung_beitrag` | `adm_rechnung_arbeitsdienst`, `adm_mahnung_arbeitsdienst` |
| Buchungskreis | `CollectionScope::MEMBERSHIP` (`mf`) | `CollectionScope::WORK` (`ad`) |
| Posten | je Position mit Quelle `rolle`, `individual`, `manuell`: `mfb_rol_name` als Bezeichnung, `mfb_text`, `mfb_amount`; Gutschriften negativ | die eine Position „Ersatzleistung Arbeitsdienst JAHR: Soll x h, Ist y h, z h x 20,00 EUR“ (Quelle `ad`), ggf. `ad_manuell` |
| Zusatzinfo auf dem PDF | Beitragsjahr, Mitgliedsnummer, bei SEPA Mandatsreferenz und Hinweis auf Einzug | Sollstunden (`ADTARGET`), Ist-Stunden (`sumByUser`), Fehlstunden; darunter die **Liste der Arbeitseinsätze** des Jahres aus `WorkHoursStore::getByYear($year, $usrId)` mit Datum, Tätigkeit und Stunden (entschieden) |
| Fälligkeit | `mfb_due_date` falls gesetzt, sonst `DUEDATE{org}`, sonst Rechnungsdatum + 14 Tage | analog mit `ADDUEDATE` |
| Zahlart-Text | `mfb_method` = `SEPA`: „wird per Lastschrift eingezogen“; `MAN` oder offen ohne Mandat: Überweisungsaufforderung + EPC-QR-Code (bestehender Trait) | analog |
| Empfänger | `DEBTOR*`-Felder falls gesetzt, sonst `FIRST_NAME`, `LAST_NAME`, `STREET`, `POSTCODE`, `CITY`, `EMAIL` (gleiche Regel wie `bill_export.php` des Forks) | dito |
| Mahnung | Positionen des Kreises mit Status `offen` oder `exportiert` und Fälligkeit vor heute; Mahngebühr als manuelle Position nach Positionsvorlage des Forks | dito |
| Nummernkreis | `JAHR-B-NNNN`, z. B. `2026-B-0012` | `JAHR-AD-NNNN`, z. B. `2026-AD-0012` |
| Dateiname | `rechnung_<nr>.pdf` / `mahnung_<nr>.pdf` im Ordner `beitrag/` | dito im Ordner `arbeitsdienst/` |

Vereinsadresse, Steuernummer und Fußzeilentext kommen aus
`adm-include/config.php`; Bankverbindung und Gläubiger-ID aus der
`Kontodaten`-Konfiguration des Mitgliedsbeitrag-Plugins, damit sie nur an
einer Stelle gepflegt werden.

**Rechnungsnummer.** Der Fork kennt bisher keine Rechnungsnummer. `mfb_reference`
ist die SEPA-EndToEnd-ID und steht nicht zur Verfügung. Da die
Rechnungsverwaltung beim Mitgliedsbeitrag-Plugin liegt, gehört auch die
Nummernvergabe dorthin, nicht in die PDF-Engine. Vorschlag für den Fork:

- Zwei Spalten in `bookings`: `mfb_invoice_no` (varchar 20, NULL) und
  `mfb_invoice_date` (date, NULL). Index auf (`mfb_org_id`, `mfb_invoice_no`).
- Schritt „Rechnungen erstellen“ (in `docs/arbeitsdienst.md` bereits als 7b
  vorgesehen) weist allen offenen Positionen eines Mitglieds, Jahres und
  Kreises, die noch keine Nummer haben, eine gemeinsame Nummer zu. Die
  Laufnummer ist `MAX(mfb_invoice_no)` des Jahres und Kreises + 1, in einer
  Transaktion. Kein Zähler nötig, keine Zusatztabelle.
- Eine Nummer wird nie wiederverwendet. Eine spätere Position desselben Jahres
  (Nachberechnung, Rücklastschriftgebühr) bekommt eine neue Nummer.
- Storno einer Position mit Nummer: die Nummer bleibt, das PDF zeigt den
  Storno aus `mfb_storno_date` und `mfb_storno_reason`.

Das PDF-Template löst `nr` dann über
`BookingStore::search(['invoice_no' => $nr])` auf (eine kleine Erweiterung
von `search()` im Fork) und erhält Mitglied, Jahr, Kreis und Positionen in
einem Schritt. Bis die Nummernvergabe im Fork existiert, kann das Template
übergangsweise mit `usr_id` + `year` + `collection` als URL-Parametern
arbeiten und eine Hilfsnummer `JAHR-B-U<usr_id>` anzeigen. Diese Hilfsnummer
ist eindeutig, aber nicht fortlaufend und sollte nicht an Mitglieder gehen.

**Einhängepunkte im Fork** (kleine Änderungen dort, nicht im PDF-Plugin):

- Mitglieder-Historie (`bookings_function.php`, Funktion `mf_render_bookings`):
  je Jahr und Kreis ein Link „Rechnung (PDF)“ und bei überfälligen Positionen
  „Mahnung (PDF)“. Der Link entsteht über eine Hilfsfunktion des PDF-Plugins,
  die der Fork nur aufruft, wenn
  `ADMIDIO_PATH . FOLDER_PLUGINS . '/wp-tc-lib-pdf/adm-include/bootstrap.php'`
  existiert.
- Rechnungs-Export (`bill.php`): zusätzlich zu CSV/XLSX und Mail eine Aktion
  „PDFs erzeugen“, die je markiertem Mitglied `PdfRegistry::create(...)->save()`
  aufruft und die Dateien im Storage-Ordner ablegt, ggf. als ZIP.
- Mail-Versand (`message_write.php`): Option, die PDF-Rechnung als Anhang
  mitzuschicken (Pfad aus `getFileNameAbs()`), und für Externe ohne
  Admidio-Konto den signierten Langzeit-Link in den Mailtext einzusetzen.

Die Berechnung der Beiträge, die Arbeitsdienst-Abrechnung
(`WorkBillingPlanner`), SEPA-Einzug und Zahlungsverfolgung bleiben vollständig
im Mitgliedsbeitrag-Plugin. Das PDF-Plugin ist reiner Leser.

### 2.8 Deployment: ein Repo in beide Hosts

Entscheidung: Das Repository wird als Ganzes in beide Hosts kopiert. Kein
Composer-Paket, kein Submodule.

```
WordPress:  wp-content/plugins/wp-tc-lib-pdf/     ← Repo-Inhalt
Admidio:    adm_plugins/wp-tc-lib-pdf/            ← identischer Repo-Inhalt
```

- `vendor/` ist versioniert (451 Dateien). Auf den Hosts ist kein `composer install` nötig.
- `wp-tc-lib-pdf.php` wird nur von WordPress geladen. In Admidio liegt die Datei ungenutzt.
- `adm-include/pdf.php` ist in WordPress per URL erreichbar; der Guard beendet
  die Ausführung. Zusätzlich kann `.htaccess` im WP-Plugin-Ordner `adm-include/` sperren.
- `wp-include/` und `wp-include/templates/` werden in Admidio nie geladen,
  `adm-include/` nie in WordPress. Nur `include/`, `font/`, `vendor/` laufen auf beiden.
- Admidio-Updates liefern `adm_plugins/` neu aus; eigene Plugins müssen erhalten bleiben.
- Pro Host bleibt `adm-include/config.php` außerhalb der Versionierung
  (gitignored), `config_sample.php` liegt im Repo.
- PHP 8.5 ist auf beiden Seiten Mindestvoraussetzung (composer.json `"php": "^8.5"`, Prüfung in `vendor/composer/platform_check.php`). WordPress und Admidio laufen auf 8.5 (Stand 2026-11-05).

## 3. Nummernkreise

Die Engine behandelt `nr` als undurchsichtige Zeichenkette. Sie steht im
Dateinamen, im signierten Link und im Rechnungsblock, wird aber nirgends
interpretiert. Vergabe und Format sind Sache der Host-Datenschicht.

Heute in WordPress (durch die bfv-Plugins vergeben):

| Kreis | Format | Verwendung |
|---|---|---|
| P | `2026-P-0148` | Erlaubnisscheine |
| J | `2026-J-00001` | Merchandise / Jubiläumsruten |
| H | `2026-H-0002` | Hütte |
| L | `2026-L-0012` | Vorbereitungslehrgang |
| – | Mitgliedsnummer | Mitgliedsantrag |

Für Admidio sind **eigene Kreise** festgelegt (entschieden 2026-11-02), in
derselben Grundform `JAHR-KENNUNG-LAUFNUMMER` mit vierstelliger Laufnummer:

| Kreis | Format | Verwendung |
|---|---|---|
| B | `2026-B-0012` | Beitragsrechnungen |
| AD | `2026-AD-0012` | Arbeitsdienstrechnungen |

Beide Kennungen kommen in WordPress nicht vor. Die Kennung darf also ein oder
zwei Buchstaben lang sein; Validierung im Template:

```php
if (!preg_match('/^\d{4}-(B|AD)-\d{4}$/', $nr)) { … fail('Ungültige Rechnungsnummer') }
```

Das erlaubte Muster steht in `adm-include/config.php`, damit weitere Kreise
später ohne Code-Änderung am Template ergänzt werden können.

Vergeben und gespeichert werden die Admidio-Nummern im Mitgliedsbeitrag-Plugin
(Spalte `mfb_invoice_no`, siehe Abschnitt 2.7), nicht im PDF-Plugin. Die
PDF-Engine liest die Nummer nur.

Warum das wichtig ist, obwohl die Systeme getrennt laufen:

- Beide Hosts gehören demselben Verein. In der Buchhaltung landen Belege aus
  beiden Systemen nebeneinander; Nummern müssen dort eindeutig sein.
- Die Storage-Ordner liegen zwar auf getrennten Dateisystemen, aber ein
  gemeinsames Archiv oder ein Export beider Systeme würde bei gleichen Nummern
  Dateien überschreiben.
- Die Kennung macht beim Lesen einer Nummer sofort klar, aus welchem System der
  Beleg stammt.

Zähler und Lückenlosigkeit liegen in der Admidio-Datenschicht (eigene Tabelle
mit `AUTO_INCREMENT` oder Sequenz pro Jahr und Kreis), nicht in der Engine.

## 4. Migrationspfad

Jede Phase lässt WordPress lauffähig und ist einzeln committbar.

1. **Umzug ohne Funktionsänderung** (erledigt 2026-11-04)
   `wp-include/` anlegen. Bootstrap-Klasse, Dispatcher und Dashboard-Widget
   verschieben. Alle `class-pdf-*.php` nach `wp-include/templates/`, `in.pdf`
   dazu. Registrierung nach `wp-include/templates.php`. Guard-Konstante
   `TC_LIB_PDF_BOOTSTRAPPED` und `TC_LIB_PDF_DIR` einführen, `ABSPATH`-Guards
   in `include/` ersetzen. `require_once`-Pfade anpassen.

2. **Plattform-Schnittstelle und Dispatcher-Kern**
   `PdfPlatformInterface`, `PdfRuntime`, `WpPlatform`. In `trait-pdf-data.php`
   Storage und `$_GET` umstellen, in `class-pdf-template.php` `save()`.
   `PdfDispatcherCore` aus `dispatch.php` herauslösen; `WpDispatcher` behält
   Hooks und Nonce. Langzeit-Hash mit `getSecret()` = `wp_salt('auth')`, damit
   bestehende Links gültig bleiben.

3. **Vereins-Texte**
   Die drei Stellen aus Abschnitt 2.5 datengetrieben machen. WP-Templates setzen
   `ort_verein_kurz` und `footer_copyright` aus `bfv_adressen` bzw. als
   Konstante. Zeitzone über die Plattform.

4. **Absicherung**
   CI-Grep oder PHPStan-Regel: keine `wp_*`-, `get_option`-, `esc_*`-Aufrufe in
   `include/`. `FakePlatform` mit Array-Daten, um jedes Template per CLI ohne
   WordPress zu rendern. Erste Regressionsmöglichkeit für die PDFs.

5. **Admidio**
   `adm-include/` mit Bootstrap, `AdmPlatform`, `AdmDispatcher`,
   `config_sample.php`. Keine Tabellen. Beitragsrechnung als erstes Template,
   abgeleitet von `PdfRechnungBase` oder als bereinigte Kopie des
   Vorbereitungslehrgang-Templates; liest über `BookingStore`,
   `CollectionScope` und `$gProfileFields` aus dem Mitgliedsbeitrag-Fork;
   Mahnung als Unterklasse. Dann Arbeitsdienstrechnung mit `WorkHoursStore`
   nach demselben Muster. Link-Erzeugung für Admidio-Seiten (Gegenstück zu
   `tc_lib_pdf_wp_create_pdf_url()`).

   Parallel im Mitgliedsbeitrag-Fork: Spalten `mfb_invoice_no` und
   `mfb_invoice_date`, Schritt „Rechnungen erstellen“ mit Nummernvergabe,
   `search(['invoice_no' => …])`, PDF-Links in der Historie (Abschnitt 2.7).

   Reihenfolge-Hinweis: Die Rechnungsbasis (2.4) sollte vor dem ersten
   Admidio-Template entstehen, sonst wird die Mahnungs-Logik ein sechstes Mal
   kopiert.

6. **Optional**
   Namespace `Bfv\Pdf\` für die Engine gegen Klassenkollisionen.

## 5. Entscheidungen und offene Fragen

Entschieden (2026-11-02):

- Admidio-Version **5.0.17** (Abschnitt 2.6).
- Deployment **ein Repo, in beide Hosts kopiert** (Abschnitt 2.7).
- **Gleiche Engine, unterschiedliche Dokumente.** Keine plattformübergreifenden
  Daten-Provider; Templates sind host-spezifisch (Abschnitt 0, 2.4).
- **Getrennte Nummernkreise** je Host (Abschnitt 3).
- **Autorisierung in Admidio:** nur Administratoren, plus signierte
  Langzeit-Links für externe Empfänger. Kein kurzlebiger Token (Abschnitt 2.3, 2.6).
  WordPress bleibt beim bestehenden Nonce-Verfahren.
- **Erste Admidio-Dokumente:** Beitragsrechnung und Arbeitsdienstrechnung,
  jeweils mit Mahnung, auf Basis des Vorbereitungslehrgang-Templates
  (Abschnitt 2.7).
- **Nummernkreise Admidio:** `2026-B-0012` für Beiträge, `2026-AD-0012` für
  Arbeitsdienst (Abschnitt 3).
- **Rechnungsverwaltung in Admidio** liegt beim Plugin Mitgliedsbeitrag
  (eigener Fork). Das PDF-Plugin legt keine Tabellen an und liest nur
  (Abschnitt 2.7). Beitragshöhe, Arbeitsstunden und Zahlstatus kommen von dort.

- **Rechnungsbasis `PdfRechnungBase`** ist angelegt (2026-11-03,
  `include/class-pdf-rechnung-base.php`). Alle fünf WordPress-
  Rechnungstemplates sind darauf umgestellt (Stand 2026-11-04).
- **Nummernvergabe** erfolgt im Mitgliedsbeitrag-Fork: Schritt „Rechnungen
  erstellen“, `MAX + 1` je Beitragsjahr und Kreis. Das PDF-Plugin liest nur.
  Datenmodell (Rechnungskopf + `mfb_invoice_no`) seit 2026-11-05 in
  KONZEPT-ADMIDIO-RECHNUNGEN.md, Abschnitt 3.
- **Arbeitsdienstrechnung** enthält die Liste der Arbeitseinsätze des Jahres
  (`WorkHoursStore::getByYear($year, $usrId)`: Datum, Tätigkeit, Stunden) als
  Nachweis unter dem Rechnungsblock, dazu Soll, Ist und Fehlstunden.

- **Phase 1 umgesetzt** (2026-11-04): `wp-include/` mit Bootstrap, Dispatcher,
  Widget, Registrierung und allen Templates; Engine in `include/` prüft
  `TC_LIB_PDF_BOOTSTRAPPED`; `in.pdf` liegt beim LFVBW-Template. Verifiziert
  per CLI: alle 19 registrierten Templates laden, Rechnungen rendern
  unverändert. In `include/` verbleiben `wp_upload_dir`, `wp_mkdir_p` und
  `get_option('timezone_string')` für Phase 2. Nachtrag: Erlaubnisschein und
  Fangstatistik luden Bilder über `__DIR__ . '/images/'`, was nach dem Umzug
  ins Leere zeigte; korrigiert auf `TC_LIB_PDF_DIR . '/include/images/'`.
  Regel für Templates in `wp-include/templates/`: Engine-Dateien und Bilder
  immer über `TC_LIB_PDF_DIR`, `__DIR__` nur für Dateien im Template-Ordner
  selbst (`in.pdf`).

- **Ein Dokument enthält genau eine Rechnung.** Sammeldokumente mit mehreren
  Rechnungen sind nicht vorgesehen (entschieden 2026-11-03).

Entschieden (2026-11-05):

- **PHP 8.5** auf WordPress und Admidio (Abschnitt 2.8).
- **Rechnung/Mahnung zum Mitgliedsantrag** (`rechnung_antrag`,
  `mahnung_antrag`) werden in WordPress nicht mehr verbessert; sie ziehen als
  Beitragsrechnung nach Admidio um. Bekannte Mängel bleiben bis dahin
  („war am  fällig“, keine Mahngebühr, Frist ab heute).
- **Rechnungserstellung in Admidio**: Entscheidungen und Annahmen in
  KONZEPT-ADMIDIO-RECHNUNGEN.md.
- **Mitgliedsbeitrag-Fork** wird nicht aus diesem Repo heraus geändert;
  nötige Änderungen stehen als TODO in KONZEPT-ADMIDIO-RECHNUNGEN.md,
  Abschnitt 10.
- **Druckstapel** nur aus bereits erzeugten PDFs; das Zusammenfügen ist eine
  abstrakte Engine-Funktion (`PdfJoiner`), keine Rechnung. Die Regel „ein
  Dokument enthält genau eine Rechnung“ bleibt.
- **Fotos in Anhängen** werden vor dem Einbetten gedreht und verkleinert,
  wenn gd verfügbar ist (`include/class-pdf-image-normalizer.php`).

To-do, in dieser Reihenfolge:

1. ~~**Funktionsprüfung im WordPress-Backend**~~ – **erledigt 2026-11-05.**
   Alle Widget-Links auf bfv-ehingen.de frisch erzeugt (`&new=1`), alle
   liefern ein PDF. Befunde:
   - Vorbereitungslehrgang: Zahlungsfrist wird ab heute statt ab
     Rechnungsdatum berechnet (offen, siehe unten).
   - Rechnungsdatum in UTC statt Ortszeit (Phase 3).
   - Mitgliedsantrag-Rechnung/-Mahnung: Mängel bleiben, Umzug nach Admidio.
   - Mitgliedsantrag: Fotos in voller Auflösung und falsch gedreht
     (behoben 2026-11-05, 10,6 MB → 0,94 MB bei mnr 268).
2. ~~**Sicherheitsbehebung signierte Links**~~ – **erledigt und auf dem
   Server 2026-11-05.** Signiert werden alle Parameter aus
   `PdfDispatcherCore::SIGNED_PARAMS` (`nr`, `mnr`, `jahr`, `dienst`,
   `veranstaltung`, `Mitgliedsnummer`, `verein`, `zweitemahnung`,
   `ohnemahngebuehr`), in Langzeit-Link und Nonce. Rohwerte müssen
   `sanitize_text_field()`-stabil sein. Alte Langzeit-Links, die nur über `nr`
   auswählen, bleiben gültig, solange kein weiterer signierter Parameter im
   Request steht (`verifyLegacyLongTermKey()`, abschaltbar mit
   `define('TC_LIB_PDF_LEGACY_KEYS', false)`). Dashboard-Widget: Listen nur
   Nonce; Button „Antragslinks neu erzeugen“ ruft `update_urls()` und schreibt
   die Quform-Felder Antrag, Rechnung und Infoblatt (`id_url`, `id_url2`,
   `id_url3`) neu. Geprüft auf dem Server: manipulierte `mnr`/`dienst`/
   `ohnemahngebuehr` abgewiesen, alter Link mit `mnr` abgewiesen, alte
   `nr`-Links gültig, neue Quform-Links liefern PDFs.
3. **Phase 2** (Plattform-Schnittstelle, Dispatcher-Kern). `signature_payload()`
   und `SIGNED_PARAMS` wandern dabei in den Dispatcher-Kern.
   - **2a Plattform-Schnittstelle: umgesetzt 2026-11-05, noch nicht auf dem
     Server.** `include/platform/` (`PdfPlatformInterface`, `PdfRuntime`),
     `wp-include/class-wp-platform.php`. Storage, URL-Parameter, `save()` und
     das Laden von tc-lib-pdf laufen über die Plattform. Verhaltensänderung:
     Templates erhalten URL-Parameter ohne WordPress-Escape-Schrägstriche
     (`wp_unslash`). Lokal geprüft: alle 19 Templates laden, Rechnung wird
     gespeichert, Ordnerschutz entsteht.
   - **2b Dispatcher-Kern: umgesetzt 2026-11-05, noch nicht auf dem Server.**
     `include/class-pdf-dispatcher-core.php` (`PdfDispatcherCore`): Routing
     (`resolveTemplateId()`), `SIGNED_PARAMS`, `normalizeValue()`,
     `signaturePayload()`, `longTermKey()`, `verifyLongTermKey()` inkl.
     Legacy-Format, `render()`. `Pdf_Dispatcher` behält nur Hooks und Nonce;
     `build_pdf_url()` nutzt den Kern. Lokal geprüft: mit dem bisherigen Code
     erzeugte Links (Langzeit und Nonce) sind mit dem Kern gültig, neue Links
     sind bytegleich, Manipulationen und abgelaufene Links werden abgewiesen.
4. **Phase 4 (Absicherung), vorgezogen: umgesetzt 2026-11-05.**
   `tools/check-engine-purity.php` (auch als GitHub-Actions-Job
   `engine-purity`), `tests/class-pdf-fake-platform.php`,
   `tests/render-rechnungen.php` mit Snapshots für alle 9 Rechnungs- und
   Mahnungs-Templates. Einzige bekannte Ausnahme der Reinheitsprüfung:
   Zeitzone in `trait-pdf-header.php` (Phase 3).
5. **Phase 3** (Zeitzone über die Plattform, Vereinstexte).
   - **Rechnungsdaten in Vereinszeitzone: umgesetzt 2026-11-05, noch nicht
     auf dem Server.** Befund: Quform speichert `created_at` in UTC; belegt
     an nächtlichen Einträgen von Mitgliedsantrag (eid 12706), Schnupperpass/
     Erlaubnis (eid 15468) und Jubiläumsruten/Merchandise (eid 7120) – die
     Rechnungen zeigten den Vortag. `PdfRechnungBase::now()` und
     `::localDateTime()` rechnen über `PdfRuntime::platform()->getTimezone()`;
     Rechnungsdatum, Standardfrist, Mahnungsdatum und Mahnfrist nutzen sie.
     Test: `tests/test-datum.php`, Fixtures mit nächtlichen UTC-Zeitstempeln.
   - Offen: Zeitzone in `trait-pdf-header.php` (Listen, Statistiken),
     „Ehingen, den“ und Copyright aus Daten, Zahlungsfrist Vorbereitungslehrgang
     ab Rechnungsdatum.
6. **Phase 5 / Admidio** nach KONZEPT-ADMIDIO-RECHNUNGEN.md, Abschnitt 8.
   Unabhängig davon jederzeit möglich: `PdfJoiner` (dort Schritt 9a).

Klein, jederzeit:

- Vorbereitungslehrgang: Zahlungsfrist ab Rechnungsdatum statt ab heute
  (`prepareRechnung()` ohne `zahlungsfrist_original`).

Sicherheitshinweis (gefunden 2026-11-03, **behoben 2026-11-05**, siehe To-do 2): Der signierte Langzeit-Link deckt
nur `nr` ab (`build_pdf_url()` in [wp-include/bootstrap.php](wp-include/bootstrap.php),
`verify_long_term_authorisation()` in [wp-include/class-wp-dispatcher.php](wp-include/class-wp-dispatcher.php)), nicht `mnr`. Langzeit-Links
für `mitgliedsantrag`, `mitgliedsantraginfo`, `rechnung_antrag` und
`mahnung_antrag` haben damit für jede Mitgliedsnummer denselben Hash; wer einen
Link besitzt, kann durch Ändern von `mnr` die Unterlagen anderer Mitglieder
abrufen. Behebung: `build_pdf_url()` und Verifier hashen alle
Auswahlparameter (`nr`, `mnr`, `jahr`) in fester Reihenfolge mit Trenner,
anschließend `update_urls()` im Dashboard-Widget einmal ausführen, damit die
in Quform gespeicherten Links neu erzeugt werden. Bestehende Links werden
dadurch ungültig, was hier gewollt ist. Gehört in den Dispatcher-Kern
(Abschnitt 2.3), umgesetzt als To-do 2 oben.

Betroffene Templates (Auswahl über einen nicht signierten Parameter):

| Template-ID | Parameter | Abrufbar | Schwere |
|---|---|---|---|
| `rechnung_antrag`, `mahnung_antrag` | `mnr` | Rechnung jedes Mitglieds mit Adresse | hoch |
| `mitgliedsantrag`, `mitgliedsantraginfo` | `mnr` | Mitgliedsantrag jedes Mitglieds | hoch |
| `liste_arbeitsdienst` | `dienst` | Teilnehmerliste jedes Arbeitsdienstes | mittel |
| `liste_jugendveranstaltung` | `veranstaltung` | Teilnehmerliste jeder Jugendveranstaltung | mittel |
| `fangstatistikuservergleich` | `Mitgliedsnummer` | Fangstatistik jedes Mitglieds | mittel |
| `fangstatistik`, `vorjahresvergleich`, `jahresvergleich` | `jahr`, `verein` | Vereinsstatistik, keine Personendaten | gering |

Nicht betroffen: alle Templates mit Auswahl über `nr` (Erlaubnisschein,
Rechnungen und Mahnungen Erlaubnis, Merchandise, Hütte, Lehrgang, LFVBW).

Kritischster Fall: `update_urls()` schreibt Langzeit-Links für
`mitgliedsantrag` und `rechnung_antrag` mit zwei Jahren Laufzeit in die
Quform-Einträge; diese Links gehen an die Antragsteller. Jeder Empfänger kann
durch Ändern von `mnr` die Anträge und Rechnungen aller anderen Mitglieder
öffnen.

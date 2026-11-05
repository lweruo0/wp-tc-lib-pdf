# Konzept: Rechnungserstellung in Admidio

Stand: 2026-11-05 · alle Fragen aus Abschnitt 9 beantwortet

Baut auf [KONZEPT-PLATTFORM.md](KONZEPT-PLATTFORM.md) auf (Engine, Admidio-Host,
Abschnitt 2.7 und 3) und beschreibt den vollständigen Weg von der
Buchungsposition bis zur Rechnung beim Mitglied. Geprüft gegen den
Mitgliedsbeitrag-Fork [lweruo0/Mitgliedsbeitrag](https://github.com/lweruo0/Mitgliedsbeitrag),
Stand `a693cb1` (2026-11-02, Version 6.4).

## Entscheidungen und Annahmen auf einen Blick

**Entschieden (2026-11-05):**

| Thema | Entscheidung | Abschnitt |
|---|---|---|
| Arbeitsweise | Änderungen am Mitgliedsbeitrag-Fork nur als TODO sammeln, nicht hier umsetzen | 8, 10 |
| Aufnahmerechnung | keine eigene Art; Beitragsrechnung über einmalige Beitragsrollen | 2.1 |
| Einmalige Rollen | Lösung (Planer oder Rollenende) entscheidet der Fork | 2.1, 10 |
| Jahr in der Rechnungsnummer | Beitragsjahr (`mfb_year`) | 3.4 |
| Rechnung für SEPA-Zahler | ja, mit Lastschrift-Hinweis statt QR-Code | 7 |
| Versandwege | Mail-Anhang, signierter Link, Druckstapel, Profil | 5 |
| Druckstapel | nur aus bereits erzeugten PDFs; PDF-Join abstrakt in der Engine | 1, 5.3 |
| Profil-Download | kurzlebiger signierter Link, Dispatcher unverändert | 5.5 |
| Mahnung | nur **eine** Mahnstufe | 6 |
| Mahngebühr | wird manuell als Position hinzugefügt | 6 |
| Gutschriften | gibt es nicht | 4 |
| Storno ganzer Rechnung | Vermerk im PDF und Status `storniert`, kein eigenes Dokument | 5.4, 7 |
| Zahlungsziel | aus den Einstellungen des Mitgliedsbeitrag-Plugins | 4, 6 |
| Datenmodell | Rechnungskopf als eigene Tabelle `…_invoices` im Fork; Positionen tragen nur `mfb_invoice_no`; `mfb_invoice_date` aus KONZEPT-PLATTFORM.md 2.7 entfällt | 3.2 |

**Annahmen** (gelten, bis sie widerrufen werden):

| Annahme | Begründung | Abschnitt |
|---|---|---|
| „Manuell“ heißt: Der Kassierer legt die Gebühr vor dem Erstellen der Mahnung über „Position hinzufügen“ mit der Positionsvorlage „Mahngebühr“ an; beim Erstellen der Mahnung wird sie der gemahnten Rechnung zugeordnet | Gebühr steht in der Buchungshistorie und wird mit eingezogen; Mahnung ohne Gebühr bleibt möglich | 6 |
| Das Zahlungsziel ist eine neue Einstellung in Tagen im Fork und gilt auch für die Mahnfrist | eine Stelle für beide Fristen | 4, 6, 10 |
| Fälligkeit der Rechnung: `mfb_due_date` → Profilfeld-Fälligkeit → Rechnungsdatum + Zahlungsziel | übernimmt die vorhandenen Fälligkeiten des Forks | 4 |
| Eine ausgestellte Rechnung wird nicht verändert; bei Storno entsteht eine zusätzliche Fassung `…_storniert.pdf` | Beleg bleibt nachvollziehbar, Vermerk trotzdem sichtbar | 5.4 |
| Summe ≤ 0 einer Rechnung ist ein Datenfehler und wird gemeldet | folgt aus „keine Gutschriften“ | 4 |
| Nach der einen Mahnung geht es außerhalb des Plugins weiter | folgt aus „eine Mahnstufe“ | 6 |
| Mahnungen nur für Positionen im Status `offen`, nicht für laufende SEPA-Einzüge (`exportiert`) | sonst Mahnung für bereits eingezogene Beträge | 6 |
| Druckstapel wird nicht abgelegt; Download setzt „gedruckt“ | Druckstapel ist keine Rechnung | 5.3 |

## 0. Zielsetzung

- Admidio erzeugt Rechnungen und Mahnungen als PDF für **Mitgliedsbeiträge**
  (einschließlich Aufnahme) und für den **Arbeitsdienst**.
- Die Rechnungen kommen auf vier Wegen zum Mitglied: **PDF als Mail-Anhang**,
  **signierter Link in der Mail**, **Sammel-Download zum Drucken** und
  **Download im eigenen Profil**.
- Die heutige WordPress-Rechnung zum Mitgliedsantrag (`rechnung_antrag`,
  `mahnung_antrag`) entfällt danach.

## 1. Aufgabenteilung

| | Mitgliedsbeitrag-Fork | wp-tc-lib-pdf (Engine `include/`, Host `adm-include/`) |
|---|---|---|
| Beträge, Positionen, Status | ja (Neuberechnung, AD-Abrechnung, SEPA, Zahlungen) | nur lesen |
| Rechnungsnummer, Rechnungsdatum, Fälligkeit | **vergeben und speichern** | nur lesen |
| Mahnung (Datum, Frist), Mahngebühr-Position | **speichern** (Gebühr manuell) | nur lesen |
| Versandprotokoll (Mail, Druck) | **speichern** | – |
| PDF-Layout, Erzeugung, Ablage der Datei | – | ja |
| Signierte Links | ruft Hilfsfunktion auf | erzeugt und prüft |
| PDF zusammenfügen (Join) für den Druckstapel | wählt die **bereits abgelegten** PDFs aus, übergibt die Dateipfade, liefert das Ergebnis aus | **abstrakt in der Engine** (`include/`): fügt beliebige vorhandene PDF-Dateien zu einem Dokument zusammen; kennt weder Rechnungen noch Admidio, auch in WordPress nutzbar (5.3) |
| Oberfläche (Auswahl, Buttons, Mail) | ja (`bill.php`, `message_write.php`, Profil) | – |

Grundsatz aus KONZEPT-PLATTFORM.md bleibt: Das PDF-Plugin legt **keine
Tabellen** an. Alles, was eine Rechnung ausmacht, liegt im Fork.

## 2. Was ist eine Rechnung?

**Rechnung = alle Positionen eines Mitglieds, eines Beitragsjahres und eines
Buchungskreises, die gemeinsam abgerechnet werden.** Sie bekommen eine
gemeinsame Rechnungsnummer.

| | Beitragsrechnung | Arbeitsdienstrechnung |
|---|---|---|
| Buchungskreis | `CollectionScope::MEMBERSHIP` (`mf`) | `CollectionScope::WORK` (`ad`) |
| Quellen (`mfb_source`) | `rolle`, `individual`, `manuell` | `ad`, `ad_manuell` |
| Nummernkreis | `JAHR-B-NNNN` | `JAHR-AD-NNNN` |
| Template | `adm_rechnung_beitrag`, `adm_mahnung_beitrag` | `adm_rechnung_arbeitsdienst`, `adm_mahnung_arbeitsdienst` |

### 2.1 Aufnahmerechnung = Beitragsrechnung

Eine eigene Aufnahmerechnung gibt es nicht. Neue Mitglieder bekommen ihre
Beitragsrollen, darunter Rollen für die einmaligen Kosten, und die normale
Neuberechnung erzeugt daraus Positionen:

| WordPress heute (Antrag 233) | Admidio |
|---|---|
| Bearbeitungsgebühr 20,00 € | Rolle „Aufnahmegebühr“, `rol_cost` 20, Zeitraum einmalig |
| Erstbesatz 250,00 € | Rolle „Erstbesatz“, `rol_cost` 250, Zeitraum einmalig |
| jährlicher Mitgliedsbeitrag 310,00 € | Rolle „Aktiv“, `rol_cost` 310, jährlich |

**Lücke im Fork:** `ContributionPlanner::planMember()` wertet
`rol_cost_period` nicht aus. Eine einmalige Rolle wird in **jedem** Jahr
berechnet, in dem das Mitglied der Rolle angehört. Zwei Lösungen:

| | Vorgehen | Bewertung |
|---|---|---|
| A | Organisatorisch: Mitgliedschaft in einmaligen Rollen endet am 31.12. des Aufnahmejahres | ohne Code, aber fehleranfällig (vergessenes Enddatum = doppelte Gebühr) |
| B | Planer: Rolle mit `rol_cost_period = -1` nur berechnen, wenn es für diese Rolle in **keinem** Jahr eine exportierte oder eingezogene Position gibt | **empfohlen**, kleine Änderung im Planer, Test im vorhandenen PHPUnit-Satz |

Das Anschreiben erkennt eine Aufnahme daran, dass eine Position zu einer
einmaligen Rolle gehört, und formuliert dann
„für Ihre Aufnahme in den Verein berechnen wir Ihnen:“ statt
„für das Beitragsjahr 2026 berechnen wir Ihnen:“.

Unterjährige Aufnahme: Die Neuberechnung läuft heute über alle Mitglieder,
lässt sich aber auf ausgewählte Rollen beschränken (`recalculation_roleselection`).
Für eine Aufnahme also Neuberechnung mit den Rollen „Aufnahmegebühr“,
„Erstbesatz“ und der Beitragsrolle, danach „Rechnung erstellen“ nur für das
neue Mitglied (Abschnitt 4). Andere Mitglieder mit offenen Positionen in diesen
Rollen werden dabei mit aktualisiert, bekommen aber keine Rechnung, solange sie
nicht markiert sind.

## 3. Datenmodell im Fork

### 3.1 Spalten an den Positionen (wie in KONZEPT-PLATTFORM.md 2.7 entschieden)

| Spalte | Typ | Inhalt |
|---|---|---|
| `mfb_invoice_no` | varchar(20) NULL | Rechnungsnummer, z. B. `2026-B-0012` |

Index auf (`mfb_org_id`, `mfb_invoice_no`). `BookingStore::search()` bekommt
den Filter `invoice_no`.

### 3.2 Neue Tabelle für den Rechnungskopf (entschieden 2026-11-05)

KONZEPT-PLATTFORM.md sah nur Spalten an den Positionen vor
(`mfb_invoice_no`, `mfb_invoice_date`). Für vier Versandwege und die Mahnung
braucht jede Rechnung aber eigenen Zustand, der nicht zu einer einzelnen
Position gehört. Deshalb eine schmale Kopftabelle im Fork, angelegt wie die
übrigen über `BookingStore::ensureTables()`.

`<prefix>_plugin_membershipfee_invoices`

| Spalte | Inhalt |
|---|---|
| `mfi_id` | laufende Nummer |
| `mfi_org_id`, `mfi_usr_id`, `mfi_year`, `mfi_kind` | Organisation, Mitglied, Beitragsjahr, Buchungskreis (`mf`/`ad`) |
| `mfi_invoice_no` | Rechnungsnummer, **unique** je Organisation |
| `mfi_invoice_date` | Rechnungsdatum |
| `mfi_due_date` | Zahlungsziel |
| `mfi_amount` | Summe der Positionen bei Erstellung |
| `mfi_method` | `SEPA` (wird eingezogen) oder `MAN` (Überweisung) bei Erstellung |
| `mfi_status` | `offen`, `bezahlt`, `storniert` (abgeleitet, siehe 3.3) |
| `mfi_dunning_date`, `mfi_dunning_due` | Datum und neue Frist der Mahnung (NULL = nicht gemahnt; es gibt nur eine Mahnung) |
| `mfi_sent_mail`, `mfi_sent_link`, `mfi_sent_print` | Zeitpunkt des letzten Versands je Weg (NULL = nie) |
| `mfi_pdf_file` | Dateiname der abgelegten Rechnung (Abschnitt 5.4) |
| `mfi_usr_id_create`, `mfi_timestamp_create` | Protokoll |

Die Positionen verweisen über `mfb_invoice_no` auf den Kopf. Die
Rechnungsnummer bleibt damit der einzige Schlüssel, den die PDF-Engine kennt.

### 3.3 Status der Rechnung

Abgeleitet aus den Positionen, beim Lesen berechnet und beim Speichern
mitgeschrieben:

| Positionen der Rechnung | Rechnung |
|---|---|
| alle `eingezogen` | `bezahlt` |
| alle `storniert` | `storniert` |
| sonst | `offen` |

### 3.4 Nummernvergabe

- Laufnummer = höchste Nummer des Jahres und Kreises + 1, vierstellig.
  Ermittelt in einer Transaktion; der Unique-Index auf `mfi_invoice_no`
  verhindert Doppelvergabe bei gleichzeitigen Läufen (bei Kollision: neu
  lesen, erneut versuchen).
- Eine Nummer wird **nie** wiederverwendet, auch nicht nach Storno.
- Eine spätere Position desselben Jahres (Nachberechnung nach Rollenwechsel,
  Rücklastschriftgebühr) bekommt eine **neue** Rechnung.
- **Ausnahme Mahngebühr:** Sie wird manuell hinzugefügt, gehört zur gemahnten
  Rechnung und bekommt deren Nummer (Abschnitt 6).
- Die Jahreszahl in der Nummer ist das **Beitragsjahr** (`mfb_year`), nicht
  das Jahr des Rechnungsdatums. Eine Nachberechnung für 2026 im Januar 2027
  heißt also `2026-B-0123`. (Entschieden 2026-11-05)

## 4. Ablauf im Fork

Der Reiter „Rechnung“ (`system/bill.php`) bleibt der Einstieg. Er zeigt heute
alle Mitglieder mit offenen Positionen des gewählten Buchungskreises, mit
Filter nach E-Mail vorhanden/fehlt, Markierung und Export als CSV/XLSX.

Neu:

```
bill.php  (Buchungskreis mf oder ad, Mitglieder markieren)
   │
   ├─ [Rechnungen erstellen]   offene Positionen ohne Nummer → je Mitglied eine Rechnung
   │                           Kopf anlegen, Nummer an Positionen, PDF erzeugen und ablegen
   │
   ├─ [Per E-Mail senden]      → message_write.php mit Option „Rechnung anhängen“
   │                             und Platzhaltern #invoice_no#, #invoice_link# …
   │
   ├─ [Druckstapel]            nur bereits erzeugte PDFs: ZIP oder ein Druck-PDF (Abschnitt 5.3)
   │
   └─ [Mahnungen]              überfällige Rechnungen, eine Mahnung je Rechnung (Abschnitt 6)
```

Neue Spalten in der Liste: Rechnungsnummer, Rechnungsdatum, Fälligkeit,
gemahnt am, letzter Versand. Neuer Filter „ohne Rechnung“ / „Rechnung offen“ /
„überfällig“.

**Rechnungen erstellen** (`BookingService::createInvoices(array $usrIds, CollectionScope $scope)`):

1. Je markiertem Mitglied die Positionen des Kreises mit Status `offen` und
   ohne `mfb_invoice_no` holen (`BookingStore::getByUser()` +
   `$scope->filterPositions()`), gruppiert nach `mfb_year`.
2. Summe ≤ 0,00 €: keine Rechnung. Gutschriften gibt es nicht
   (entschieden 2026-11-05); eine Summe ≤ 0 ist ein Datenfehler und wird in
   der Ergebnisliste gemeldet.
3. Kopf anlegen: Nummer (3.4), Rechnungsdatum = heute (Zeitzone `$gTimezone`),
   Fälligkeit nach der Regel aus 2.7: `mfb_due_date` → Profilfeld
   `DUEDATE{org}`/`ADDUEDATE` → Rechnungsdatum + **Zahlungsziel aus den
   Einstellungen** des Mitgliedsbeitrag-Plugins (Tage, entschieden 2026-11-05). Zahlart: `SEPA`,
   wenn Mandat und IBAN vorhanden, sonst `MAN`.
4. `mfb_invoice_no` an alle Positionen schreiben.
5. PDF erzeugen und ablegen: `tc_lib_pdf_adm_save('adm_rechnung_beitrag', ['nr' => …])`
   (Abschnitt 5.4), Dateiname im Kopf speichern.

Der Schritt ist idempotent: Ein zweiter Aufruf findet keine Positionen ohne
Nummer mehr und tut nichts.

**Was passiert danach mit den Positionen?** Nichts. SEPA-Export, Zahlungen und
Storno arbeiten weiter auf den Positionen; die Rechnungsnummer läuft nur mit.

Einhängepunkt für das PDF-Plugin: Der Fork prüft
`is_file(ADMIDIO_PATH . FOLDER_PLUGINS . '/wp-tc-lib-pdf/adm-include/bootstrap.php')`
und blendet die PDF-Aktionen sonst aus. Nummernvergabe und Export laufen auch
ohne PDF-Plugin.

## 5. Versandwege

### 5.1 PDF als Mail-Anhang

`system/message_write.php` / `message_send.php` versenden heute je Empfänger
eine eigene Mail (`Admidio\Infrastructure\Email`) mit Platzhaltern
(`replace_emailparameter()`) und optionalen hochgeladenen Anhängen.

Erweiterung:

- Checkbox „Rechnung als PDF anhängen“ (nur sichtbar, wenn das PDF-Plugin da
  ist). Je Empfänger wird die **zuletzt erstellte offene Rechnung** des
  Kreises angehängt: `$email->addAttachment($pfad, 'Rechnung_2026-B-0012.pdf')`.
  Pfad aus `mfi_pdf_file`; fehlt die Datei, wird sie neu erzeugt.
- Mitglieder ohne Rechnung erhalten keine Mail und erscheinen in der
  Ergebnisliste unter „nicht gesendet: keine Rechnung“.
- Neue Platzhalter in `replace_emailparameter()`:

| Platzhalter | Inhalt |
|---|---|
| `#invoice_no#` | Rechnungsnummer |
| `#invoice_date#` | Rechnungsdatum |
| `#invoice_due#` | Zahlungsziel |
| `#invoice_amount#` | Rechnungsbetrag |
| `#invoice_link#` | signierter Link (5.2) |

- Nach erfolgreichem Versand: `mfi_sent_mail` (bzw. `mfi_sent_link`, wenn der
  Text `#invoice_link#` enthält) setzen.
- Größe: Eine Rechnung hat rund 50 KB, `Email::getMaxAttachmentSize()` ist
  kein Thema.
- E-Mail-Vorlagen „Rechnung Beitrag“ und „Rechnung Arbeitsdienst“ in
  Einstellungen → E-Mail-Benachrichtigungen, wie die vorhandene Vorlage
  „Vorabinformation Arbeitsdienst“.

### 5.2 Signierter Link in der Mail

- `#invoice_link#` = `tc_lib_pdf_adm_create_pdf_url('adm_rechnung_beitrag', ['nr' => $nr], '+1 year')`.
- Funktioniert ohne Admidio-Konto (Prüfung `PdfDispatcherCore::verifyLongTermKey()`
  mit Secret aus `adm-include/config.php`, siehe KONZEPT-PLATTFORM.md 2.3).
- Signiert wird mit derselben Logik wie in WordPress (alle Auswahlparameter,
  `signature_payload()` aus dem Dispatcher-Kern). Da die Rechnung allein über
  `nr` ausgewählt wird, kann ein Empfänger mit seinem Link keine fremde
  Rechnung öffnen.
- Für Mahnungen eigener Link auf `adm_mahnung_beitrag`, gleiche Nummer.

### 5.3 Druckstapel (ZIP oder ein Druck-PDF)

Für Mitglieder ohne E-Mail (Filter in `bill.php`) oder für den Postversand.

**Nur bereits erzeugte PDFs** (entschieden 2026-11-05): Der Druckstapel nimmt
ausschließlich Rechnungen und Mahnungen, deren Datei schon abgelegt ist
(5.4). Er erzeugt nichts nach. Markierte Mitglieder ohne abgelegte Datei
erscheinen in der Ergebnisliste als „nicht im Druckstapel: kein PDF
vorhanden“; dafür zuerst „Rechnungen erstellen“ bzw. „Mahnung erstellen“
ausführen.

| Variante | Umsetzung |
|---|---|
| ZIP | `ZipArchive` mit den abgelegten Einzel-PDFs, Dateiname `rechnungen_2026-B_<datum>.zip` (Fork) |
| Druck-PDF | Fork übergibt die Dateipfade an den abstrakten PDF-Join der Engine und liefert das Ergebnis aus |

**PDF-Join in der Engine (abstrakt, umgesetzt 2026-11-05).**
`include/class-pdf-joiner.php`, plattformneutral, ohne Bezug zu Rechnungen,
Admidio oder WordPress:

```php
// Fügt vorhandene PDF-Dateien in der übergebenen Reihenfolge zusammen.
$result = PdfJoiner::join(
    $files,                       // Pfade der abgelegten PDF-Dateien
    ['duplex' => true, 'title' => 'Druckstapel', 'skip_invalid' => true],
);
$result->pdf;                     // PDF als Binärstring (nicht gespeichert)
$result->pages;                   // Seiten inkl. Leerseiten
$result->files;                   // übernommene Dateien
$result->skipped;                 // ["rechnung_2026-B-0007.pdf: PDF nicht lesbar", …]
```

- Umsetzung mit den Importfunktionen von tc-lib-pdf
  (`setImportSourceData()` mit `strict_limits`, `getSourcePageCount()`,
  `addPageFromImport()`).
- `duplex`: Hat ein Dokument eine ungerade Seitenzahl, folgt eine Leerseite
  im Format der letzten Seite, damit jedes Dokument auf einer Vorderseite
  beginnt.
- Ungültige Dateien: `PdfJoinException` mit Dateiname (`getFileName()`);
  mit `skip_invalid` stattdessen Eintrag in `$result->skipped`.
  Überschrittene Grenzen: `PdfJoinLimitException`, auch mit `skip_invalid`.
- Kein Speichern, keine Autorisierung, kein HTTP: Das macht der Aufrufer.
- Nutzbar für jeden Sammeldruck, auch in WordPress (z. B. Erlaubnisscheine).

**Sicherheit des PDF-Join (entschieden 2026-11-05).** Der Joiner prüft
selbst, was man nicht vergessen darf; damit kann er auch bei einem
unsauberen Aufrufer nie mehr ausliefern als PDFs aus der eigenen Ablage.

| Im Joiner (Engine) | Regel |
|---|---|
| Wurzelordner | Jede Datei muss nach `realpath()` unterhalb des Ablageordners der Plattform (`getStorageBaseDir()`) liegen. `../`, Symlinks nach außen und fremde Ordner werden abgelehnt. Option `root` kann einen anderen Ordner setzen (der Aufrufer z. B. nur `…/beitrag`); er muss existieren und darf keine Laufwerks-/Dateisystemwurzel sein. |
| Dateityp | reguläre, lesbare Datei, Endung `.pdf`, Inhalt beginnt mit `%PDF-`; leere Dateien und Pfade mit Nullbyte werden abgelehnt. |
| Grenzen | höchstens 500 Dateien, 20 MB je Datei, 200 MB insgesamt, 2000 Seiten (Konstanten; per Option nur kleiner). Alle Pfade werden vor dem ersten Lesen geprüft. |
| Meldungen | nennen nur den Dateinamen, nie den Pfad. |
| Ergebnis | nur als String; keine Zwischendatei. Metadaten: Titel aus der Option, Autor und Betreff leer, keine Mitgliedsnamen. |

| Beim Aufrufer (Fork, ebenso ein WordPress-Aufrufer) | Regel |
|---|---|
| Rechte | Admidio: nur Rollen mit Rechten auf die Beitragsverwaltung (wie `bill.php`); WordPress: `current_user_can('manage_options')`. |
| CSRF | Druckstapel nur per POST mit Admidio-CSRF-Token bzw. WordPress-Nonce. |
| Auswahl | Die Anfrage enthält nur Benutzer-IDs bzw. Rechnungsnummern. Die Pfade kommen aus der Datenbank (`mfi_pdf_file`), nie aus der Anfrage; Dateiname zusätzlich gegen `^(rechnung\|mahnung)_\d{4}-[A-Z]-\d{4}\.pdf$` prüfen. `root` auf den Unterordner (`beitrag`/`arbeitsdienst`) setzen. |
| Auslieferung | nur als direkter Download in der laufenden Sitzung, **nie als signierter Link** (Daten vieler Mitglieder). Header: `Content-Type: application/pdf`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store`. |
| Protokoll | `mfi_sent_print` setzen; festhalten, wer den Stapel wann geladen hat. `$result->skipped` in der Ergebnisliste anzeigen. |

Die Entscheidung „ein Dokument enthält genau eine Rechnung“ bleibt gültig:
Der Druckstapel ist keine Rechnung, sondern eine Druckdatei aus fertigen
Rechnungen. Er wird nicht abgelegt.

Nach dem Download: `mfi_sent_print` setzen (Annahme: was gedruckt wird, wird
verschickt).

### 5.4 Ablage der PDFs

- Ordner: `adm_my_files/plugins/wp-tc-lib-pdf/beitrag/` bzw.
  `…/arbeitsdienst/` (`AdmPlatform::getStorageBaseDir()`), nicht öffentlich
  erreichbar.
- Dateiname: `rechnung_2026-B-0012.pdf`, `mahnung_2026-B-0012.pdf`.
- **Eine erstellte Rechnung ändert sich nicht mehr.** Jeder Abruf (Mail,
  Link, Profil, Druck) liefert die abgelegte Datei. Ändern sich später
  Adresse oder Bankverbindung, bleibt das ausgestellte Dokument, wie es war.
  Neu erzeugen nur ausdrücklich (Administrator, Parameter `new=1`), z. B. nach
  einem Layoutfehler.
- **Storno** (entschieden 2026-11-05: Vermerk im PDF, kein eigenes
  Dokument): Wird eine Rechnung ganz storniert (Status `storniert`), bleibt
  die ausgestellte Datei unverändert erhalten. Zusätzlich entsteht
  `rechnung_2026-B-0012_storniert.pdf` mit dem Vermerk; alle Abrufe liefern ab
  dann diese Fassung.
- Neu für die Engine: `save()` legt die Datei an, `stream()` liefert sie aus.
  Heute schreibt `stream()` den Cache nicht (`file_put_contents`
  auskommentiert); für Admidio-Rechnungen wird `save()` beim Erstellen
  aufgerufen, damit ist die Datei immer da.

### 5.5 Download im Mitgliederprofil

Der Fork baut bereits Registerkarten ins Profil ein (`ProfileAddin`): „Beiträge“
(nur Zugriffsrolle) und „Arbeitsdienst“ (auch das Mitglied selbst).

Erweiterung: In beiden Karten eine Liste „Rechnungen“ für das **eigene**
Profil: Nummer, Datum, Betrag, Status, Link „PDF“ (und „Mahnung“, falls
vorhanden). Für die Beitragsrechnungen braucht es dafür einen Teil der Karte
„Beiträge“, der auch für das Mitglied selbst sichtbar ist, oder eine eigene
schmale Karte „Rechnungen“.

Autorisierung: KONZEPT-PLATTFORM.md 2.6 erlaubt in Admidio bisher nur
Administratoren und signierte Links. Zwei Möglichkeiten:

| | Vorgehen | Bewertung |
|---|---|---|
| A | Das Profil erzeugt für jede eigene Rechnung einen kurzlebigen signierten Link (`expires` = morgen) | **entschieden 2026-11-05**: keine Änderung am Dispatcher, gleiche Prüfung wie Mail-Links |
| B | Dispatcher erlaubt eingeloggten Benutzern ihre eigenen Dokumente; Template meldet nach `loadData()` die `usr_id` des Empfängers | sauberer, aber neue Schnittstelle in der Engine und eine zweite Prüfung, die stimmen muss |

## 6. Mahnung

**Überfällig** ist eine Rechnung, wenn

- `mfi_status = offen`,
- sie noch nicht gemahnt wurde (`mfi_dunning_date` leer),
- das Zahlungsziel vor heute liegt und
- mindestens eine Position noch `offen` ist. Positionen im Status
  `exportiert` stecken in einem laufenden SEPA-Einzug und werden **nicht**
  gemahnt.

Ablauf „Mahnungen“ in `bill.php`:

**Es gibt nur eine Mahnstufe** (entschieden 2026-11-05). Nach Ablauf der
Mahnfrist geht es außerhalb des Plugins weiter.

**Die Mahngebühr wird manuell hinzugefügt** (entschieden 2026-11-05): Der
Kassierer legt sie vor dem Erstellen der Mahnung über „Position hinzufügen“
(Positionsvorlage „Mahngebühr“) als manuelle Position an, nur bei den
Mitgliedern, bei denen er eine Gebühr erheben will. Mahnungen ohne Gebühr
sind damit ohne Weiteres möglich.

1. Liste der überfälligen Rechnungen des Kreises.
2. Markieren, „Mahnung erstellen“:
   - `mfi_dunning_date` = heute, `mfi_dunning_due` = heute + Zahlungsziel aus
     den Einstellungen.
   - Offene manuelle Positionen desselben Mitglieds, Jahres und Kreises
     **ohne** Rechnungsnummer, deren Text der Positionsvorlage „Mahngebühr“
     entspricht, bekommen die Rechnungsnummer der gemahnten Rechnung. Der
     Dialog zeigt vorher an, welche Gebühr zugeordnet wird. So steht die
     Gebühr in der Buchungshistorie, wird per SEPA oder Überweisung mit
     eingezogen und erscheint auf der Mahnung.
   - PDF `mahnung_<nr>.pdf` erzeugen und ablegen.
3. Versand wie die Rechnung (5.1 bis 5.3) mit eigener Mailvorlage.

Template: `PdfAdmMahnungBeitrag extends PdfAdmRechnungBeitrag` ruft
`prepareMahnung($formdata, 'Mahnung …', zweiteMahnung: false, ohneMahngebuehr: true)`
auf. Die Gebühr kommt **nicht** aus der Option `mahngebuehr` der Engine,
sondern steht bereits als Position in der Rechnung. Die Texte von
`PdfRechnungBase` („war am … fällig“, neue Frist) passen unverändert, weil
`zahlungsfrist_original` aus `mfi_due_date` gesetzt wird.

## 7. PDF-Templates in `adm-include/templates/`

Gemeinsame Basis `PdfAdmRechnung extends PdfRechnungBase` mit der
Datenbeschaffung; die vier Templates unterscheiden sich nur in Kreis, Texten
und Zusatzblock.

`loadData()`:

1. `nr` aus der URL, gegen das Muster aus `config.php` prüfen
   (`/^\d{4}-(B|AD)-\d{4}$/`).
2. Mitgliedsbeitrag-Plugin laden
   (`…/Mitgliedsbeitrag/system/common_function.php`), sonst Fehler.
3. Kopf über `nr` lesen, Positionen über `BookingStore::search(['invoice_no' => $nr])`.
4. Empfänger aus `DEBTOR*`, sonst `FIRST_NAME`, `LAST_NAME`, `STREET`,
   `POSTCODE`, `CITY`, `EMAIL` (Regel wie `bill_export.php`), auf die neutralen
   Keys der Rechnungsbasis.
5. Vereinsdaten aus `adm-include/config.php`, Bankverbindung und Gläubiger-ID
   aus `ConfigTable` → `Kontodaten` (`inhaber`, `iban`, `bic`, `bank`, `ci`).
6. `prepareRechnung()`, dann `created_at` = `mfi_invoice_date`,
   `zahlungsfrist_original` = `mfi_due_date`, Posten aus den Positionen
   (`mfb_rol_name` bzw. `mfb_text`, Betrag; stornierte Positionen mit Vermerk
   „storniert am …“ und 0,00 € in der Summe; sind alle Positionen storniert,
   trägt das PDF zusätzlich den Vermerk „Rechnung storniert am … (Grund)“
   über dem Rechnungsblock).
7. Zahlart (Rechnung für alle Zahler, entschieden 2026-11-05): `SEPA` → „Der Betrag wird am … von Ihrem Konto IBAN DE…xxx
   (Mandat …) eingezogen.“ und **kein** QR-Code; `MAN` → Überweisungstext und
   EPC-QR-Code (vorhandener Trait).
8. Dateiname und Ordner setzen.

Zusatzblöcke:

| Template | Zusatz |
|---|---|
| Beitrag | Beitragsjahr, Mitgliedsnummer (`MEMBERNUMBER{org}`), bei Aufnahme abweichendes Anschreiben (2.1) |
| Arbeitsdienst | Soll (`ADTARGET`), Ist (`WorkHoursStore::sumByUser()`), Fehlstunden, Stundensatz; darunter die Liste der Einsätze des Jahres (`WorkHoursStore::getByYear($year, $usrId)`: Datum, Tätigkeit, Stunden) |

## 8. Umsetzungsreihenfolge

| # | Wo | Schritt | setzt voraus |
|---|---|---|---|
| 1 | wp-tc-lib-pdf | Phase 2: Plattform-Schnittstelle, `PdfDispatcherCore` mit `signature_payload()` | – |
| 2 | wp-tc-lib-pdf | Phase 3: Zeitzone über die Plattform, Vereinstexte aus Daten | 1 |
| 3 | wp-tc-lib-pdf | `adm-include/`: Bootstrap, `AdmPlatform`, `AdmDispatcher`, `config_sample.php`, URL- und Save-Helfer | 1 |
| 4 | Fork (TODO) | Planer: einmalige Rollen nur einmal berechnen (2.1 B) + Test | – |
| 5 | Fork (TODO) | Tabelle `invoices`, Spalte `mfb_invoice_no`, `search(['invoice_no'])`, `createInvoices()` + Tests | – |
| 6 | wp-tc-lib-pdf | `PdfAdmRechnung`, Templates Beitrag und Arbeitsdienst | 3, 5 |
| 7 | Fork (TODO) | `bill.php`: Rechnungen erstellen, Spalten, Filter | 5, 6 |
| 8 | Fork (TODO) | Mail: Anhang, Platzhalter, Vorlagen | 7 |
| 9a | wp-tc-lib-pdf | `PdfJoiner` in der Engine (abstrakter PDF-Join, 5.3) – **erledigt 2026-11-05** | – |
| 9b | Fork (TODO) | Druckstapel in `bill.php` aus vorhandenen PDFs (ZIP, Druck-PDF über `PdfJoiner`) | 7, 9a |
| 10 | Fork (TODO) + wp-tc-lib-pdf | Mahnungen: Liste, Gebühr-Position zuordnen, Templates | 7 |
| 11 | Fork (TODO) | Profil: eigene Rechnungen | 7 |
| 12 | WordPress | `rechnung_antrag`/`mahnung_antrag` abschalten, Quform-Feld `id_url2` nicht mehr befüllen | 11 und erste Aufnahme über Admidio |

Schritte 4 und 5 hängen nicht am PDF-Plugin und können parallel zu 1 bis 3
laufen.

**Arbeitsweise (entschieden 2026-11-05):** Änderungen am Mitgliedsbeitrag-Fork
werden **nicht** in diesem Repo bzw. in dieser Arbeitssitzung umgesetzt. Sie
werden nur als TODOs gesammelt (Abschnitt 10) und im Fork separat bearbeitet.
In diesem Repo entstehen nur die Schritte 1, 2, 3, 6 und 9a (PDF-Join).

## 9. Fragen und Antworten

1. ~~**Kopftabelle**~~ – **entschieden 2026-11-05: ja**, eigene Tabelle
   `…_invoices` im Fork (3.2); ersetzt `mfb_invoice_date` aus
   KONZEPT-PLATTFORM.md 2.7.
2. ~~**Jahr in der Rechnungsnummer**~~ – **entschieden 2026-11-05:
   Beitragsjahr** (`mfb_year`), siehe 3.4.
3. ~~**Rechnung auch für SEPA-Zahler?**~~ – **entschieden 2026-11-05: ja**,
   mit Lastschrift-Hinweis statt QR-Code (siehe 7, Schritt 7).
4. ~~**Einmalige Rollen**~~ – **an den Mitgliedsbeitrag-Fork übergeben
   (2026-11-05)**, Entscheidung dort, siehe TODO in Abschnitt 10.
5. ~~**Profil-Download**~~ – **entschieden 2026-11-05: kurzlebiger signierter
   Link** (5.5 A). Keine Änderung an der Autorisierung des Dispatchers.
6. ~~**Mahnstufen**~~ – **entschieden 2026-11-05: eine Mahnstufe**, die
   Mahngebühr wird manuell als Position hinzugefügt (Abschnitt 6).
7. ~~**Gutschriften**~~ – **entschieden 2026-11-05: gibt es nicht.** Kein
   eigenes Dokument; Summe ≤ 0 wird als Fehler gemeldet (Abschnitt 4).
8. ~~**Storno einer ganzen Rechnung**~~ – **entschieden 2026-11-05: Vermerk
   im PDF und Status `storniert`**, kein eigenes Stornodokument (5.4).
9. ~~**Zahlungsziel**~~ – **entschieden 2026-11-05: aus den Einstellungen**
   des Mitgliedsbeitrag-Plugins (neue Einstellung, Fork-TODO in Abschnitt 10).
   Gilt für Rechnung und Mahnfrist. Das PDF-Plugin liest nur `mfi_due_date`
   bzw. `mfi_dunning_due`; `PdfRechnungBase::ZAHLUNGSZIEL` gilt nur für
   WordPress.

## 10. TODOs für den Mitgliedsbeitrag-Fork

Gesammelt, nicht umgesetzt. Nummern in Klammern verweisen auf Abschnitt 8.

- [ ] **Einmalige Rollen** (4): im Fork entscheiden, ob einmalige Kosten
      (Aufnahmegebühr, Erstbesatz) über eine **Planer-Änderung** (2.1 B) oder
      **organisatorisch** über das Rollenende am 31.12. (2.1 A) einmalig
      bleiben. Bei B: `ContributionPlanner::planMember()` berechnet Rollen mit
      `rol_cost_period = -1` nur, wenn es für die Rolle in keinem Jahr eine
      exportierte oder eingezogene Position gibt; Rollendaten müssen dafür
      `rol_cost_period` mitliefern; PHPUnit-Test ergänzen. Für das PDF-Plugin
      ist nur wichtig, dass die Positionen stimmen; das Anschreiben erkennt die
      Aufnahme an der einmaligen Rolle (2.1).
- [ ] **Tabelle `…_plugin_membershipfee_invoices`** (5) nach 3.2 in
      `BookingStore::ensureTables()`, Unique-Index auf (`mfi_org_id`,
      `mfi_invoice_no`), Entfernen in `removeDataOfCurrentOrganization()` und
      `uninstall.php`. (entschieden 9.1)
- [ ] **Spalte `mfb_invoice_no`** an den Positionen (5), Index auf
      (`mfb_org_id`, `mfb_invoice_no`), in `BOOKING_COLUMNS` aufnehmen.
- [ ] **`BookingStore::search()`** um Filter `invoice_no` erweitern (5).
- [ ] **`BookingService::createInvoices(array $usrIds, CollectionScope $scope)`**
      (5): Ablauf aus Abschnitt 4, Nummernvergabe aus 3.4 mit Transaktion und
      Retry, idempotent. Tests.
- [ ] **Status der Rechnung** aus den Positionen ableiten (3.3), beim Markieren
      als eingezogen, Storno und „Datum löschen“ mitschreiben.
- [ ] **`bill.php`** (7): Spalten Rechnungsnummer, Datum, Fälligkeit,
      gemahnt am, letzter Versand; Filter „ohne Rechnung“ / „offen“ /
      „überfällig“; Button „Rechnungen erstellen“; PDF-Aktionen nur, wenn
      `wp-tc-lib-pdf/adm-include/bootstrap.php` existiert.
- [ ] **Mail** (8): in `message_write.php`/`message_send.php` Checkbox
      „Rechnung als PDF anhängen“ (`$email->addAttachment()`), Ergebnisliste
      „nicht gesendet: keine Rechnung“, `mfi_sent_mail`/`mfi_sent_link` setzen.
- [ ] **Platzhalter** in `replace_emailparameter()` (8): `#invoice_no#`,
      `#invoice_date#`, `#invoice_due#`, `#invoice_amount#`, `#invoice_link#`.
- [ ] **E-Mail-Vorlagen** „Rechnung Beitrag“, „Rechnung Arbeitsdienst“,
      „Mahnung Beitrag“, „Mahnung Arbeitsdienst“ (8, 10).
- [ ] **Druckstapel** (9b): Button in `bill.php`; nimmt **nur bereits
      erzeugte** PDFs (`mfi_pdf_file` bzw. Mahnungsdatei vorhanden), erzeugt
      nichts nach; fehlende Dateien in der Ergebnisliste melden; ZIP über
      `ZipArchive` oder Druck-PDF über `PdfJoiner::join()` der Engine;
      `mfi_sent_print` setzen. Sicherheit nach 5.3 (Aufruferseite): Recht
      Beitragsverwaltung, POST + CSRF-Token, Pfade nur aus `mfi_pdf_file`
      plus Dateinamen-Muster, `root` auf den Unterordner, kein signierter
      Link, Download-Header, Protokoll; `$result->skipped` anzeigen. Gleiche
      Regeln für das ZIP.
- [ ] **Mahnungen** (10): Liste überfälliger, noch nicht gemahnter
      Rechnungen nach Abschnitt 6; nur **eine** Mahnung je Rechnung;
      `mfi_dunning_date`/`mfi_dunning_due` setzen; manuell angelegte
      Mahngebühr-Positionen (Positionsvorlage „Mahngebühr“, ohne
      Rechnungsnummer) der gemahnten Rechnung zuordnen und im Dialog anzeigen;
      PDF erzeugen. Positionsvorlage „Mahngebühr“ anlegen.
- [ ] **Profil** (11): Liste „Rechnungen“ für das eigene Profil in
      `ProfileAddin`, Links als kurzlebige signierte Links (`expires` = morgen, entschieden 9.5).
- [ ] **Einstellung Zahlungsziel** (5): neue Einstellung „Zahlungsziel
      (Tage)“, z. B. Abschnitt `Rechnung` in `ConfigTable`, verwendet für
      `mfi_due_date` (wenn weder `mfb_due_date` noch Profilfeld-Fälligkeit
      gesetzt) und für die Mahnfrist `mfi_dunning_due`.
- [ ] **Storno ganzer Rechnungen** (5): beim Stornieren der letzten offenen
      Position einer Rechnung `mfi_status = storniert` setzen und die
      stornierte PDF-Fassung erzeugen lassen (5.4).
- [ ] **Doku** im Fork: `docs/` um Rechnungen ergänzen, README-Versionshinweis.

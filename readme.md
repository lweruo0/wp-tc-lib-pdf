# TC Lib PDF for WordPress - PDF Template System

## Übersicht

Dieses System bietet eine flexible und erweiterbare Architektur zum Rendern verschiedener PDF-Templates basierend auf GET-Parametern. Mit Traits wird eine "Mehrfachvererbung" simuliert, um verschiedene Features zu kombinieren.

## Architektur

### 1. **PdfTemplate** (Basis-Klasse)
   - Abstrakte Basis für alle PDF-Templates
   - Erweitert `\Com\Tecnick\Pdf\Tcpdf`
   - Definiert die `render()` Methode, die von Subklassen implementiert werden muss
   - Stellt Methoden für Dokumentmetadaten bereit

### 2. **Traits** (Feature-Bundles)
   - **PdfHeaderFooterTrait**: Bietet Header/Footer-Funktionalität
   - Können kombiniert werden, um verschiedene Feature-Sets zu erstellen
   - Erlauben Simulation von Mehrfachvererbung

### 3. **Template-Klassen**
   - **PdfExample**: Einfaches Beispiel-Template mit Header/Footer
   - **PdfInvoice**: Rechnungs-Template mit Header/Footer und Struktur
   - **PdfSimple**: Minimales Template ohne Zusatz-Features
   - Können leicht erweitert oder angepasst werden

### 4. **PdfRegistry** (Template-Registry)
   - Verwaltet Registrierung und Instanziierung von Templates
   - Ermöglicht dynamisches Laden von Template-Klassen
   - Validiert Abhängigkeiten und Sicherheit

### 5. **Pdf_Dispatcher** (Router)
   - Verarbeitet `$_GET['demo_pdf']` Parameter
   - Verifiziert Nonce-Token für Sicherheit
   - Lädt und rendert die richtige PDF-Template
   - Behandelt Fehler und gibt aussagekräftige Meldungen zurück

## Verwendung

### URL-Format zum PDF-Rendering

```
https://example.com/?demo_pdf=example&nonce=NONCE_VALUE
```

Verfügbare Templates:
- `demo_pdf=example` - Einfaches Beispiel-Template
- `demo_pdf=invoice` - Rechnungs-Template
- `demo_pdf=simple` - Minimales Template

### Nonce generieren

Im Backend oder Template-Code:

```php
$nonce = wp_create_nonce('demo_pdf_render');
$url = add_query_arg([
    'demo_pdf' => 'example',
    'nonce' => $nonce
], home_url('/'));
```

### Link in Template einfügen

```php
<?php
$nonce = wp_create_nonce('demo_pdf_render');
$pdf_url = add_query_arg([
    'demo_pdf' => 'example',
    'nonce' => $nonce
], home_url('/'));
?>
<a href="<?php echo esc_url($pdf_url); ?>" class="button">PDF downloaden</a>
```

## Ein neues Template erstellen

### 1. Einfaches Template (ohne Header/Footer)

Datei: `include/class-pdf-report.php`

```php
<?php

require_once __DIR__ . '/class-pdf-template.php';

class PdfReport extends PdfTemplate {
    protected string $report_title = 'Report';

    public function __construct() {
        parent::__construct();
        $this->title = $this->report_title;
    }

    public function setReportTitle(string $title): void {
        $this->report_title = $title;
    }

    protected function render(): void {
        $this->addPage();

        // Ihr PDF-Inhalt hier
        $this->setFontSize(16);
        $this->color->setPdfColor('#1a3a6b');
        $out = $this->getTextCell(
            txt: $this->report_title,
            posx: 10,
            posy: 20,
            width: 190,
            height: 15,
            offset: 0,
            linespace: 0,
            valign: \Com\Tecnick\Pdf\TextVAlign::Top,
            halign: \Com\Tecnick\Pdf\TextHAlign::Center,
        );
        echo $out; // phpcs:ignore
    }
}
```

### 2. Template mit Header/Footer (nutzt Trait)

Datei: `include/class-pdf-report-with-footer.php`

```php
<?php

require_once __DIR__ . '/class-pdf-template.php';
require_once __DIR__ . '/trait-pdf-header-footer.php';

class PdfReportWithFooter extends PdfTemplate {
    use PdfHeaderFooterTrait;

    public function __construct() {
        parent::__construct();
        $this->title = 'Report mit Footer';
        $this->enableDefaultPageContent(true);
    }

    protected function render(): void {
		$this->setHeaderText('Bezirksfischerei-Verein e.V. Ehingen/Donau', 'https://bfv-ehingen.de', 'https://bfv-ehingen.de');
        $this->addPage();

        // Ihr Inhalt hier
        $this->setFontSize(14);
        $this->color->setPdfColor('#555555');
        $out = $this->getTextCell(
            txt: 'Bericht mit automatischer Header/Footer',
            posx: 10,
            posy: 50,
            width: 190,
            height: 20,
            offset: 0,
            linespace: 0,
            valign: \Com\Tecnick\Pdf\TextVAlign::Top,
            halign: \Com\Tecnick\Pdf\TextHAlign::Center,
        );
        echo $out; // phpcs:ignore
    }
}
```

### 3. Template im Dispatcher registrieren

In `include/dispatch.php`:

```php
// Neue Template-Klasse laden und registrieren
require_once __DIR__ . '/class-pdf-report.php';
PdfRegistry::register('report', 'PdfReport', __DIR__ . '/class-pdf-report.php');

require_once __DIR__ . '/class-pdf-report-with-footer.php';
PdfRegistry::register('report-footer', 'PdfReportWithFooter', __DIR__ . '/class-pdf-report-with-footer.php');
```

Jetzt sind neue Templates verfügbar:
- `demo_pdf=report`
- `demo_pdf=report-footer`

## Eigene Traits erstellen

Erstelle einen neuen Trait für wiederverwendbare Funktionalität:

Datei: `include/trait-pdf-watermark.php`

```php
<?php

trait PdfWatermarkTrait {
    protected string $watermark_text = '';

    public function setWatermark(string $text): void {
        $this->watermark_text = $text;
    }

    public function addWatermark(): void {
        if (empty($this->watermark_text)) {
            return;
        }

        // Watermark-Logik hier
        $this->setFontSize(48);
        $this->color->setPdfColor('#eeeeee');
        // ... Watermark rendern
    }
}
```

Verwende den Trait in mehreren Templates:

```php
class PdfInvoiceWithWatermark extends PdfTemplate {
    use PdfHeaderFooterTrait;
    use PdfWatermarkTrait;

    protected function render(): void {
        $this->addWatermark();
        // ... Rest des Templates
    }
}
```

## Sicherheit

- **Nonce-Verifikation**: Alle PDF-Rendering-Anfragen werden durch `wp_verify_nonce()` verifiziert
- **Sanitization**: GET-Parameter werden durch `sanitize_text_field()` bereinigt
- **Escaping**: Alle Ausgaben sind korrekt escaped
- **Fehlerbehandlung**: Fehler werden via `wp_die()` berichtet (nicht öffentlich)

## Error-Handling

Der Dispatcher behandelt folgende Fehler:
- Ungültiger/fehlender Nonce → Fehlerseite
- Unbekanntes Template → Fehlerseite
- Fehlende Template-Datei → Exception
- Fehlende Template-Klasse → Exception
- Template erbt nicht von PdfTemplate → Exception

Alle Fehler werden mit aussagekräftigen Meldungen angezeigt.

## Best Practices

1. **Template-Klassen**: Erweitere immer `PdfTemplate`, nicht direkt `\Com\Tecnick\Pdf\Tcpdf`
2. **Traits verwenden**: Nutze Traits für wiederverwendbare Features
3. **Dokumentation**: Dokumentiere die Struktur deines Templates mit PHPDoc
4. **Error-Handling**: Überprüfe auf null/false Rückgabewerte
5. **Security**: Nutze immer Nonces für PDF-Anfragen
6. **Performance**: Cache komplexe Berechnungen, wenn möglich

## Beispiel: Erweiterte Template-Klasse

```php
<?php

require_once __DIR__ . '/class-pdf-template.php';
require_once __DIR__ . '/trait-pdf-header-footer.php';

class PdfCustomReport extends PdfTemplate {
    use PdfHeaderFooterTrait;

    protected string $report_date = '';
    protected array $data = [];

    public function __construct() {
        parent::__construct();
        $this->title = 'Custom Report';
        $this->report_date = date('Y-m-d');
        $this->enableDefaultPageContent(true);
    }

    public function setData(array $data): void {
        $this->data = $data;
    }

    protected function render(): void {
		$this->setHeaderText('Bezirksfischerei-Verein e.V. Ehingen/Donau', 'https://bfv-ehingen.de', 'https://bfv-ehingen.de');
        $this->addPage();

        // Render data
        foreach ($this->data as $row) {
            $this->renderRow($row);
        }
    }

    private function renderRow(array $row): void {
        // Deine Rendering-Logik
    }
}
```

## Troubleshooting

**Problem**: PDF wird nicht angezeigt
- Überprüfe die Nonce-Validierung
- Prüfe, ob das Template registriert ist
- Überprüfe PHP-Fehler-Logs

**Problem**: Header/Footer nicht sichtbar
- Stelle sicher, dass `enableDefaultPageContent(true)` aufgerufen wird
- Überprüfe, dass die Trait verwendet wird
- Prüfe Margins und Positioning

**Problem**: Fehlerhafte Fonts
- Stelle sicher, dass `K_PATH_FONTS` korrekt definiert ist
- Überprüfe die Composer-Installation: `composer install`

## Support

Für Fragen oder Issues, konsultiere die tc-lib-pdf Dokumentation:
https://github.com/tecnickcom/tc-lib-pdf






# PDF Template System - Architektur Übersicht

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress HTTP Request                       │
│                  ?demo_pdf=example&nonce=XXX                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  wp-tc-lib-pdf.php (Plugin)                      │
│              ┌─ plugins_loaded Hook                              │
│              └─ Loads dispatch.php                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  dispatch.php (Router)                           │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  1. Verifiziert Nonce                                    │   │
│  │  2. Sanitiert $_GET['demo_pdf']                          │   │
│  │  3. Ruft PdfRegistry auf                                 │   │
│  │  4. Error-Handling                                       │   │
│  └──────────────────────────────────────────────────────────┘   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  PdfRegistry (Singleton)                         │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  • register(id, class, file)                             │   │
│  │  • exists(id)                                            │   │
│  │  • create(id) → PdfTemplate instance                     │   │
│  │  • getAll()                                              │   │
│  └──────────────────────────────────────────────────────────┘   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  PdfTemplate (Abstract Base)                     │
│           ┌─ extends \Com\Tecnick\Pdf\Tcpdf                     │
│           ├─ abstract render()                                  │
│           ├─ setDocTitle()                                      │
│           ├─ setDocAuthor()                                     │
│           └─ setDocSubject()                                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
            ┌────────────┼────────────┐
            │            │            │
            ▼            ▼            ▼
     ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
     │ PdfExample   │ │ PdfInvoice   │ │ PdfSimple    │
     ├──────────────┤ ├──────────────┤ ├──────────────┤
     │ + Trait:     │ │ + Trait:     │ │ (Keine Traits│
     │   Header     │ │   Header     │ │              │
     │   Footer     │ │   Footer     │ │              │
     │              │ │              │ │              │
     │ render()     │ │ render()     │ │ render()     │
     └──────────────┘ └──────────────┘ └──────────────┘
```

## Workflow

1. **User klickt auf PDF-Link**
   ```
   URL: ?demo_pdf=example&nonce=ABC123
   ```

2. **Dispatcher empfängt Request**
   - Verifiziert Nonce via `wp_verify_nonce()`
   - Sanitiert `$_GET['demo_pdf']`

3. **Registry lädt Template**
   - Ruft `PdfRegistry::create('example')` auf
   - Lädt `class-pdf-example.php`
   - Instantiiert `new PdfExample()`

4. **Template rendert**
   - Ruft `render()` auf
   - Fügt Inhalt zum PDF hinzu
   - Ruft `output()` auf

5. **PDF wird zum Browser gesendet**
   ```
   Header: Content-Type: application/pdf
   Content: PDF binary data
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
├── pdf-templates-config.json            # Configuration
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

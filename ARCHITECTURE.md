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

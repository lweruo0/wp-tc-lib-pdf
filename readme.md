# TC Lib PDF for WordPress

Dieses Plugin ersetzt den alten TCPDF-Loader durch einen modernen Bootstrap-Ansatz für tc-lib-pdf.

## Ziel

- tc-lib-pdf über Composer laden
- keine manuelle Einbindung alter TCPDF-Dateien mehr
- einfache Verwendung aus WordPress heraus

## Composer-Konfiguration

Die Konfiguration befindet sich in [composer.json](composer.json):

```json
{
  "name": "oliver-ruoss/tc-lib-pdf-wp",
  "description": "WordPress bootstrap for tc-lib-pdf",
  "type": "wordpress-plugin",
  "require": {
    "php": "^8.2",
    "tecnickcom/tc-lib-pdf": "^8"
  }
}
```

## Installation

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-gd

composer update

```

## Verwendung

Im Plugin kann ein PDF wie folgt erzeugt werden:

```php
$pdf = tc_lib_pdf_wp_create_pdf();
```

Falls ältere Aufrufer noch `new TCPDF()` erwarten, wird ein Alias auf die neue Klasse angelegt, sofern die Bibliothek geladen wurde.

## Hinweise

- tc-lib-pdf benötigt PHP 8.2 oder neuer.
- Die Fonts werden über die Composer-Installation der Bibliothek bereitgestellt.
- Das Plugin ist als einfacher Bootstrap-Loader für WordPress gedacht und ersetzt nicht die eigentliche PDF-API.


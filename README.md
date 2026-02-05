# DocumentGeneratorX

A Laravel package to generate **PDF documents** from DOCX or HTML templates with variable replacement support including text, numbers, images, and more.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ayoratoumvone/documentgeneratorx.svg?style=flat-square)](https://packagist.org/packages/ayoratoumvone/documentgeneratorx)
[![Total Downloads](https://img.shields.io/packagist/dt/ayoratoumvone/documentgeneratorx.svg?style=flat-square)](https://packagist.org/packages/ayoratoumvone/documentgeneratorx)

## Features

- **PDF Output Only**: All documents are generated as PDF
- **DOCX Templates**: Use Microsoft Word documents as templates
- **HTML Templates**: Use HTML files as templates
- **Image Support**: Insert images from local files or URLs with custom dimensions
- **Type-Safe Variables**: Advanced variable syntax with type checking
- **Remote Templates**: Load templates from URLs
- **Storage Integration**: Save to Laravel storage or download directly

## Supported Formats

| Input (Template) | Output |
|-----------------|--------|
| DOCX (Word)     | PDF    |
| HTML            | PDF    |

## Installation

```bash
composer require ayoratoumvone/documentgeneratorx
```

## Quick Start

```php
use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

// Generate PDF from DOCX template
$pdfPath = DocumentGenerator::template('template.docx')
    ->variables([
        'name' => 'John Doe',
        'photo' => 'https://example.com/photo.jpg',
    ])
    ->generate();
```

## Variable Syntax

Use double curly braces with type annotations:

```
{{variable_name:type,option1:value1,option2:value2}}
```

### Supported Types

| Type | Syntax | Example |
|------|--------|---------|
| Text | `{{name:text}}` | `'John Doe'` |
| Number | `{{age:number}}` | `25` |
| Image | `{{photo:image,width:200,height:100}}` | `'path/to/image.jpg'` |
| Date | `{{date:date}}` | `'2024-01-15'` |
| Boolean | `{{active:boolean}}` | `true` |

### Image Options

```
{{logo:image}}                           // Default size
{{photo:image,width:200}}                // Fixed width
{{banner:image,width:200,height:100}}    // Fixed dimensions
{{avatar:image,ratio:1:1}}               // Aspect ratio
```

## Template Examples

### DOCX Template

Create a Word document with:
```
Je suis un test {{name:text}}
{{photo:image,width:200,height:100}}
```

### HTML Template

```html
<!DOCTYPE html>
<html>
<head><title>{{title:text}}</title></head>
<body>
    <h1>Hello {{name:text}}</h1>
    {{logo:image,width:300}}
</body>
</html>
```

## Usage

### Basic Usage

```php
use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

$pdfPath = DocumentGenerator::template(storage_path('templates/invoice.docx'))
    ->variables([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'photo' => 'https://example.com/avatar.jpg',
    ])
    ->generate();
```

### With Image from URL

```php
$pdfPath = DocumentGenerator::template('template.docx')
    ->variables([
        'name' => 'Ayoratoumvone',
        'photo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Google_Images_2015_logo.svg/1280px-Google_Images_2015_logo.svg.png',
    ])
    ->generate('output.pdf');
```

### With Local Image

```php
$pdfPath = DocumentGenerator::template('template.docx')
    ->variables([
        'name' => 'Ayoratoumvone',
        'photo' => storage_path('images/logo.png'),
    ])
    ->generate('output.pdf');
```

### Download PDF

```php
return DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John Doe'])
    ->download('document.pdf');
```

### Save to Storage

```php
$path = DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John Doe'])
    ->generateToStorage('documents/output.pdf', 'public');
```

### Using HTML Template

```php
$pdfPath = DocumentGenerator::template('template.html')
    ->variables([
        'title' => 'My Document',
        'name' => 'John Doe',
        'logo' => storage_path('images/logo.png'),
    ])
    ->generate();
```

## Standalone Usage (Without Laravel)

```php
require 'vendor/autoload.php';

use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Generators\HtmlToPdfGenerator;

// From DOCX
$generator = new DocxToPdfGenerator();
$generator->generate(
    'template.docx',
    ['name' => 'John Doe', 'photo' => 'image.jpg'],
    'output.pdf'
);

// From HTML
$generator = new HtmlToPdfGenerator();
$generator->generate(
    'template.html',
    ['name' => 'John Doe', 'logo' => 'logo.png'],
    'output.pdf'
);
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="documentgenerator-config"
```

Config options in `config/documentgenerator.php`:

```php
return [
    'template_path' => storage_path('app/document-templates'),
    'output_path' => storage_path('app/generated-documents'),
    'disk' => 'local',
    'auto_delete' => true,
];
```

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- GD extension for image processing

## Testing

```bash
# Run test
php generate_test.php

# Run PHPUnit tests
composer test
```

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.

## Author

- [Ayoratoumvone](https://github.com/Ayoratou99)

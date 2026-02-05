# DocumentGeneratorX API Reference

## Overview

This package generates **PDF documents** from DOCX or HTML templates.

| Input Format | Output Format |
|-------------|---------------|
| DOCX (Word) | PDF           |
| HTML        | PDF           |

---

## DocumentGenerator Class

Main class for generating documents.

### Methods

#### `template(string $source): self`

Set the template file path or URL.

**Parameters:**
- `$source` (string) - File path or URL to template (.docx or .html)

**Returns:** `self` for method chaining

**Example:**
```php
// Local DOCX file
DocumentGenerator::template('/path/to/template.docx')

// Local HTML file
DocumentGenerator::template('/path/to/template.html')

// URL
DocumentGenerator::template('https://example.com/template.docx')
```

---

#### `templateFromStorage(string $path, string $disk = 'local'): self`

Load template from Laravel storage disk.

**Parameters:**
- `$path` (string) - Path within storage disk
- `$disk` (string) - Storage disk name (default: 'local')

**Returns:** `self` for method chaining

**Example:**
```php
DocumentGenerator::templateFromStorage('templates/invoice.docx', 'public')
```

---

#### `variables(array $variables): self`

Set all variables at once.

**Parameters:**
- `$variables` (array) - Associative array of variable names and values

**Returns:** `self` for method chaining

**Example:**
```php
DocumentGenerator::variables([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'photo' => '/path/to/image.jpg',
])
```

---

#### `addVariable(string $key, mixed $value): self`

Add a single variable.

**Parameters:**
- `$key` (string) - Variable name
- `$value` (mixed) - Variable value

**Returns:** `self` for method chaining

**Example:**
```php
DocumentGenerator::addVariable('name', 'John Doe')
    ->addVariable('email', 'john@example.com')
```

---

#### `generate(?string $outputPath = null): string`

Generate the PDF document.

**Parameters:**
- `$outputPath` (string|null) - Output file path (auto-generated if null)

**Returns:** `string` - Path to generated PDF file

**Throws:** `DocumentGeneratorException` on failure

**Example:**
```php
$path = DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->generate('/output/document.pdf');
```

---

#### `generateToStorage(string $path, string $disk = 'local'): string`

Generate and save to storage disk.

**Parameters:**
- `$path` (string) - Path within storage disk
- `$disk` (string) - Storage disk name (default: 'local')

**Returns:** `string` - Full path to stored file

**Example:**
```php
$path = DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->generateToStorage('documents/output.pdf', 'public');
```

---

#### `download(?string $filename = null): BinaryFileResponse`

Generate and return as download response.

**Parameters:**
- `$filename` (string|null) - Download filename (auto-generated if null)

**Returns:** `BinaryFileResponse` - Laravel download response

**Example:**
```php
return DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->download('document.pdf');
```

---

#### `getVariables(): array`

Get current variables.

**Returns:** `array` - Current variables

---

#### `getTemplateFormat(): ?string`

Get detected template format.

**Returns:** `string|null` - Template format ('docx' or 'html')

---

#### `temporary(bool $temp = true): self`

Set output as temporary (auto-deleted on script end).

**Parameters:**
- `$temp` (bool) - Whether output should be temporary (default: true)

**Returns:** `self` for method chaining

**Example:**
```php
// Force temporary output (overrides config)
DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->temporary()
    ->generate();
```

---

#### `permanent(): self`

Set output as permanent (not auto-deleted).

**Returns:** `self` for method chaining

**Example:**
```php
// Keep the generated file permanently
DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->permanent()
    ->generate('/path/to/keep/document.pdf');
```

---

#### `cleanup(): self`

Manually delete the last generated file.

**Returns:** `self` for method chaining

**Example:**
```php
$generator = DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John']);

$path = $generator->generate();

// Do something with the file...

// Then manually delete it
$generator->cleanup();
```

---

#### `getLastGeneratedPath(): ?string`

Get the path of the last generated file.

**Returns:** `string|null` - Path to last generated file

---

#### `isTemporary(): bool`

Check if output is set to temporary mode.

**Returns:** `bool` - True if temporary output is enabled

---

#### `reset(): self`

Reset generator state and cleanup files.

**Returns:** `self` for method chaining

**Example:**
```php
$generator = DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->generate();

$generator->reset()
    ->template('another.docx')
    ->variables(['name' => 'Jane'])
    ->generate();
```

---

## Generators

### DocxToPdfGenerator

Converts DOCX templates to PDF.

```php
use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;

$generator = new DocxToPdfGenerator();
$generator->generate('template.docx', $variables, 'output.pdf');
```

### HtmlToPdfGenerator

Converts HTML templates to PDF.

```php
use Ayoratoumvone\Documentgeneratorx\Generators\HtmlToPdfGenerator;

$generator = new HtmlToPdfGenerator();
$generator->generate('template.html', $variables, 'output.pdf');
```

---

## Variable Types

### Text

**Syntax:** `{{variable:text}}`

**Valid Values:**
- String
- Number (converted to string)

**Example:**
```php
['name' => 'John Doe']
['description' => 'Lorem ipsum dolor sit amet']
```

---

### Number

**Syntax:** `{{variable:number}}` or `{{variable:integer}}`

**Valid Values:**
- Integer
- Float
- Numeric string

**Example:**
```php
['age' => 30]
['price' => 99.99]
['quantity' => '5']
```

---

### Image

**Syntax:** 
- `{{variable:image}}`
- `{{variable:image,width:200}}`
- `{{variable:image,height:150}}`
- `{{variable:image,width:400,height:300}}`
- `{{variable:image,ratio:16:9}}`
- `{{variable:image,width:800,ratio:16:9}}`

**Valid Values:**
- Local file path
- Public URL

**Options:**
- `width` - Image width in pixels
- `height` - Image height in pixels
- `ratio` - Aspect ratio (e.g., `16:9`, `4:3`, `1:1`)

**Example:**
```php
['logo' => '/path/to/logo.png']
['avatar' => 'https://example.com/avatar.jpg']
['banner' => storage_path('images/banner.jpg')]
```

**Template:**
```
{{logo:image,width:200}}
{{avatar:image,width:150,ratio:1:1}}
{{banner:image,ratio:16:9}}
```

---

### Date

**Syntax:** `{{variable:date}}`

**Valid Values:**
- DateTime instance
- Date string

**Example:**
```php
['created_at' => now()]
['birth_date' => Carbon::parse('1990-01-01')]
['invoice_date' => '2024-02-05']
```

---

### Boolean

**Syntax:** `{{variable:boolean}}` or `{{variable:bool}}`

**Valid Values:**
- `true` / `false`

**Output:** "Yes" or "No"

**Example:**
```php
['is_active' => true]  // Outputs: "Yes"
['has_discount' => false]  // Outputs: "No"
```

---

## Configuration

### Config File: `config/documentgenerator.php`

```php
return [
    // Default template storage path
    'template_path' => storage_path('app/document-templates'),
    
    // Default output path (used when temp_output is false)
    'output_path' => storage_path('app/generated-documents'),
    
    // Save to temp directory and auto-delete (prevents storage filling up)
    'temp_output' => true,
    
    // Delete file after download() is called
    'delete_after_download' => true,
    
    // Cleanup temp files when script ends
    'cleanup_on_shutdown' => true,
    
    // Storage disk for generateToStorage()
    'disk' => 'local',
];
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `template_path` | string | `storage_path('app/document-templates')` | Where templates are stored |
| `output_path` | string | `storage_path('app/generated-documents')` | Where permanent files are saved |
| `temp_output` | bool | `true` | Use temp directory for generated files |
| `delete_after_download` | bool | `true` | Auto-delete after download() |
| `cleanup_on_shutdown` | bool | `true` | Cleanup when script ends |
| `disk` | string | `'local'` | Laravel storage disk |

### Temporary Output Behavior

When `temp_output` is `true` (default):
- Files are saved to system temp directory (`sys_get_temp_dir()`)
- Files are automatically deleted when the PHP script ends
- Prevents storage from filling up with generated documents

When `temp_output` is `false`:
- Files are saved to `output_path`
- Files are kept permanently until manually deleted

**Override in code:**
```php
// Force temporary for this call
DocumentGenerator::template('template.docx')
    ->temporary()
    ->generate();

// Force permanent for this call
DocumentGenerator::template('template.docx')
    ->permanent()
    ->generate('/keep/this/file.pdf');
```

---

## Exceptions

### `DocumentGeneratorException`

Base exception for all document generation errors.

**Common Cases:**
- Template file not found
- Invalid template format
- Invalid variable type
- Image processing error
- PDF generation error

**Example:**
```php
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;

try {
    DocumentGenerator::template('template.docx')
        ->variables(['name' => 'John'])
        ->generate();
} catch (DocumentGeneratorException $e) {
    Log::error('Document generation failed: ' . $e->getMessage());
}
```

---

## Facade

### DocumentGenerator Facade

**Namespace:** `Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator`

**Usage:**
```php
use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

DocumentGenerator::template('template.docx')
    ->variables(['name' => 'John'])
    ->generate();
```

---

## Best Practices

1. **Always validate inputs** before passing to generator
2. **Use type hints** in templates for better validation
3. **Optimize images** before processing (smaller images = faster generation)
4. **Cache templates** for repeated use
5. **Use queues** for bulk generation
6. **Handle exceptions** appropriately
7. **Clean up** generated files when no longer needed

---

## Version Compatibility

| Package Version | Laravel Version | PHP Version |
|----------------|-----------------|-------------|
| 1.x | 10.x, 11.x | 8.1+ |

---

## Support

- Documentation: [GitHub](https://github.com/Ayoratou99/documentgeneratorx)
- Issues: [GitHub Issues](https://github.com/Ayoratou99/documentgeneratorx/issues)
- Email: 44085615+Ayoratou99@users.noreply.github.com

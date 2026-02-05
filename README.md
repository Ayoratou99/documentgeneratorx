# DocumentGeneratorX

A Laravel package to generate **PDF documents** from DOCX or HTML templates with variable replacement, styling support, batch generation, and queue integration for parallel processing.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ayoratoumvone/documentgeneratorx.svg?style=flat-square)](https://packagist.org/packages/ayoratoumvone/documentgeneratorx)
[![Total Downloads](https://img.shields.io/packagist/dt/ayoratoumvone/documentgeneratorx.svg?style=flat-square)](https://packagist.org/packages/ayoratoumvone/documentgeneratorx)

## Features

- **PDF Output**: All documents are generated as high-quality PDF
- **DOCX Templates**: Use Microsoft Word documents as templates
- **HTML Templates**: Use HTML files as templates
- **Variable Styling**: Apply colors, bold, italic, and more to variables
- **Image Support**: Insert images with custom dimensions
- **Batch Generation**: Generate multiple documents at once
- **Queue Support**: Process documents in background with Laravel queues
- **Event System**: Hook into generation lifecycle with Laravel events
- **LibreOffice Integration**: Best quality PDF output (default)

## Installation

```bash
composer require ayoratoumvone/documentgeneratorx
```

Publish the config file:

```bash
php artisan vendor:publish --tag="documentgenerator-config"
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

---

## Table of Contents

- [Variable Syntax](#variable-syntax)
- [Styling Variables](#styling-variables)
- [Single Document Generation](#single-document-generation)
- [Batch Generation](#batch-generation)
- [Queue & Events (Parallel Processing)](#queue--events-parallel-processing)
- [PDF Conversion Methods](#pdf-conversion-methods)
- [Configuration](#configuration)
- [Standalone Usage](#standalone-usage)

---

## Variable Syntax

Use double curly braces with type annotations in your template:

```
{{variable_name:type,option1:value1,option2:value2}}
```

### Supported Types

| Type | Syntax | Example Value |
|------|--------|---------------|
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

---

## Styling Variables

Apply styling directly in template placeholders:

```
{{title:text,color:red,bold:true}}
{{name:text,font-size:18,underline:true}}
{{warning:text,color:#FF0000,background-color:#FFFF00}}
```

### Supported Style Properties

| Property | Example | Description |
|----------|---------|-------------|
| `color` | `color:red` or `color:#FF0000` | Text color |
| `bold` | `bold:true` | Bold text |
| `italic` | `italic:true` | Italic text |
| `underline` | `underline:true` | Underlined text |
| `font-size` | `font-size:14` | Font size in points |
| `font-family` | `font-family:Arial` | Font family |
| `background-color` | `background-color:#FFFF00` | Highlight color |

### Named Colors

`red`, `green`, `blue`, `black`, `white`, `yellow`, `orange`, `purple`, `pink`, `gray`, `brown`, `navy`, `teal`, `maroon`

---

## Single Document Generation

Methods for generating **one document at a time**.

### Available Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `generate($path)` | Generate PDF to file path | File path |
| `download($filename)` | Generate and return download response | HTTP Response |
| `generateToStorage($path, $disk)` | Generate and save to Laravel storage | Storage path |

### Generate to File

```php
use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

$pdfPath = DocumentGenerator::template('invoice.docx')
    ->variables([
        'customer_name' => 'John Doe',
        'total' => '$1,234.00',
    ])
    ->generate('invoices/invoice-001.pdf');
```

### Download Response (Single Document Only)

```php
// Returns a download response - works for SINGLE document only
return DocumentGenerator::template('contract.docx')
    ->variables(['name' => 'John Doe'])
    ->download('contract.pdf');
```

### Save to Storage

```php
$path = DocumentGenerator::template('report.docx')
    ->variables(['title' => 'Monthly Report'])
    ->generateToStorage('reports/march-2024.pdf', 'public');
```

---

## Batch Generation

Generate **multiple documents** at once. Returns an array of results.

> **Note:** `download()` is NOT available for batch generation. You cannot download multiple files in a single HTTP response. For batch downloads, generate files then create a ZIP archive.

### Available Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `generateBatch($documents)` | Generate multiple PDFs (same template) | Array of results |
| `batchGenerate($documents)` | Generate multiple PDFs (different templates) | Array of results |

### Same Template, Different Data

```php
use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

$generator = DocumentGenerator::template('certificate.docx');

$results = $generator->generateBatch([
    ['variables' => ['name' => 'Alice Johnson'], 'output' => 'certs/alice.pdf'],
    ['variables' => ['name' => 'Bob Smith'], 'output' => 'certs/bob.pdf'],
    ['variables' => ['name' => 'Carol White'], 'output' => 'certs/carol.pdf'],
]);

// Check results
foreach ($results as $result) {
    if ($result['success']) {
        echo "Generated: {$result['path']}\n";
    } else {
        echo "Failed: {$result['error']}\n";
    }
}
```

### Different Templates

```php
use Ayoratoumvone\Documentgeneratorx\DocumentGenerator;

$results = DocumentGenerator::batchGenerate([
    [
        'template' => 'invoice.docx',
        'variables' => ['customer' => 'John', 'total' => '$500'],
        'output' => 'docs/invoice.pdf',
    ],
    [
        'template' => 'contract.docx', 
        'variables' => ['client' => 'Jane', 'date' => '2024-01-15'],
        'output' => 'docs/contract.pdf',
    ],
]);
```

### Progress Callback

```php
$results = $generator->generateBatch($documents, false, function($completed, $total, $path) {
    $percent = round(($completed / $total) * 100);
    echo "Progress: {$percent}% ({$completed}/{$total})\n";
});
```

### Download Multiple Documents as ZIP

To let users download multiple documents, create a ZIP file:

```php
use ZipArchive;

// Generate batch
$results = $generator->generateBatch($documents);

// Create ZIP with successful files
$zipPath = storage_path('app/temp/documents.zip');
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

foreach ($results as $result) {
    if ($result['success']) {
        $zip->addFile($result['path'], basename($result['path']));
    }
}
$zip->close();

// Return ZIP download
return response()->download($zipPath, 'documents.zip')->deleteFileAfterSend(true);
```

---

## Queue & Events (Parallel Processing)

For generating many documents simultaneously, use Laravel's queue system with the built-in job and events. Each document gets a **system-generated `documentId`** that you can use to track its progress.

### Why Use Queues?

- **Parallel Processing**: Generate multiple documents at the same time
- **Background Processing**: Don't block web requests
- **Reliability**: Automatic retries on failure
- **Scalability**: Distribute work across multiple queue workers
- **Tracking**: Each document gets a unique ID for status tracking

### Generate Single Document in Queue

```php
use Ayoratoumvone\Documentgeneratorx\Jobs\GenerateDocument;

// Create the job to get the document ID
$job = new GenerateDocument(
    templatePath: storage_path('templates/invoice.docx'),
    variables: ['customer' => 'John Doe', 'total' => '$1,234'],
    outputPath: storage_path('app/invoices/invoice-001.pdf')
);

// Get the document ID for tracking (store this in your database)
$documentId = $job->documentId;  // e.g., "doc_550e8400-e29b-41d4-a716-446655440000"

// Save to your database
DocumentRequest::create([
    'document_id' => $documentId,
    'user_id' => auth()->id(),
    'status' => 'pending',
]);

// Dispatch the job
dispatch($job);

// Return the document ID to user so they can check status later
return response()->json(['document_id' => $documentId, 'status' => 'processing']);
```

### Generate Multiple Documents in Parallel

Use the `GenerateDocumentBatch` helper:

```php
use Ayoratoumvone\Documentgeneratorx\Jobs\GenerateDocumentBatch;

// Create batch
$batchHelper = GenerateDocumentBatch::create([
    ['template' => 'invoice.docx', 'variables' => ['name' => 'Alice'], 'output' => 'alice.pdf'],
    ['template' => 'invoice.docx', 'variables' => ['name' => 'Bob'], 'output' => 'bob.pdf'],
    ['template' => 'invoice.docx', 'variables' => ['name' => 'Carol'], 'output' => 'carol.pdf'],
]);

// Get all document IDs BEFORE dispatching (store these in your database)
$documentIds = $batchHelper->getDocumentIds();
// ['doc_abc123...', 'doc_def456...', 'doc_ghi789...']

// Save to your database for tracking
foreach ($documentIds as $documentId) {
    DocumentRequest::create([
        'document_id' => $documentId,
        'user_id' => auth()->id(),
        'status' => 'pending',
    ]);
}

// Now dispatch
$batch = $batchHelper
    ->name('Monthly Invoices')
    ->onQueue('documents')
    ->dispatch();

// Return document IDs to user
return response()->json([
    'batch_id' => $batchHelper->getBatchId(),
    'document_ids' => $documentIds,
    'status' => 'processing',
]);
```

### Tracking Document Status with Events

When documents complete (or fail), events fire with the `documentId`. Use listeners to update your database:

```php
// In your EventServiceProvider
protected $listen = [
    \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated::class => [
        \App\Listeners\UpdateDocumentStatus::class,
    ],
    \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerationFailed::class => [
        \App\Listeners\HandleDocumentFailure::class,
    ],
];
```

```php
// app/Listeners/UpdateDocumentStatus.php
class UpdateDocumentStatus
{
    public function handle(DocumentGenerated $event): void
    {
        // Find your record using the document ID
        $request = DocumentRequest::where('document_id', $event->documentId)->first();
        
        if ($request) {
            $request->update([
                'status' => 'completed',
                'file_path' => $event->outputPath,
                'completed_at' => now(),
            ]);
            
            // Notify the user
            $request->user->notify(new DocumentReadyNotification($event->outputPath));
        }
    }
}
```

### API Endpoint Example

```php
// Controller: Start document generation
public function generateInvoice(Request $request)
{
    $job = new GenerateDocument(
        'invoice.docx',
        ['customer' => $request->customer_name, 'total' => $request->total],
        "invoices/{$request->invoice_id}.pdf"
    );
    
    // Store for tracking
    DocumentRequest::create([
        'document_id' => $job->documentId,
        'user_id' => auth()->id(),
        'type' => 'invoice',
        'status' => 'processing',
    ]);
    
    dispatch($job);
    
    return response()->json([
        'document_id' => $job->documentId,
        'message' => 'Document is being generated',
    ]);
}

// Controller: Check status
public function checkStatus(string $documentId)
{
    $request = DocumentRequest::where('document_id', $documentId)
        ->where('user_id', auth()->id())
        ->firstOrFail();
    
    return response()->json([
        'document_id' => $documentId,
        'status' => $request->status,
        'file_path' => $request->file_path,
        'completed_at' => $request->completed_at,
    ]);
}
```

### Events

The package fires events at each stage of document generation. Use these to add custom logic.

| Event | When Fired | Use Case |
|-------|------------|----------|
| `DocumentGenerating` | Before generation starts | Validate, log start |
| `DocumentGenerated` | After successful generation | Notify user, move file |
| `DocumentGenerationFailed` | When generation fails | Log error, notify admin |
| `BatchGenerationCompleted` | When batch finishes | Zip files, send summary |

### Setting Up Event Listeners

**1. Publish example listeners:**

```bash
php artisan vendor:publish --tag="documentgenerator-listeners"
```

**2. Register listeners in `EventServiceProvider`:**

```php
// app/Providers/EventServiceProvider.php

use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated;
use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerationFailed;
use Ayoratoumvone\Documentgeneratorx\Events\BatchGenerationCompleted;
use App\Listeners\DocumentGenerator\LogDocumentGenerated;
use App\Listeners\DocumentGenerator\NotifyDocumentReady;
use App\Listeners\DocumentGenerator\HandleGenerationFailed;
use App\Listeners\DocumentGenerator\HandleBatchCompleted;

protected $listen = [
    DocumentGenerated::class => [
        LogDocumentGenerated::class,
        NotifyDocumentReady::class,
    ],
    DocumentGenerationFailed::class => [
        HandleGenerationFailed::class,
    ],
    BatchGenerationCompleted::class => [
        HandleBatchCompleted::class,
    ],
];
```

### Example: Notify User When Document is Ready

```php
// app/Listeners/DocumentGenerator/NotifyDocumentReady.php

namespace App\Listeners\DocumentGenerator;

use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated;
use App\Models\DocumentRequest;
use App\Notifications\DocumentReadyNotification;

class NotifyDocumentReady
{
    public function handle(DocumentGenerated $event): void
    {
        // Find the document request using the system-generated documentId
        $request = DocumentRequest::where('document_id', $event->documentId)->first();
        
        if ($request) {
            // Update status
            $request->update([
                'status' => 'completed',
                'file_path' => $event->outputPath,
            ]);
            
            // Notify the user who requested this document
            $request->user->notify(new DocumentReadyNotification(
                $event->outputPath,
                $event->getFileSizeFormatted()
            ));
        }
    }
}
```

### Example: Create ZIP of Batch Documents

```php
// app/Listeners/DocumentGenerator/HandleBatchCompleted.php

use Ayoratoumvone\Documentgeneratorx\Events\BatchGenerationCompleted;
use ZipArchive;

class HandleBatchCompleted
{
    public function handle(BatchGenerationCompleted $event): void
    {
        if (!$event->isFullySuccessful()) {
            Log::warning("Batch {$event->batchId} had {$event->failedCount} failures");
            return;
        }

        // Create ZIP with all documents
        $zipPath = storage_path("app/batches/{$event->batchId}.zip");
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($event->getSuccessfulPaths() as $path) {
            $zip->addFile($path, basename($path));
        }

        $zip->close();
        
        Log::info("Batch ZIP created: {$zipPath}");
    }
}
```

### Running Queue Workers

Start queue workers to process document generation jobs:

```bash
# Single worker
php artisan queue:work

# Multiple workers for parallel processing
php artisan queue:work --queue=documents &
php artisan queue:work --queue=documents &
php artisan queue:work --queue=documents &

# Using Supervisor (production)
# See Laravel docs: https://laravel.com/docs/queues#supervisor-configuration
```

---

## PDF Conversion Methods

### LibreOffice (Default - Best Quality)

Uses LibreOffice for pixel-perfect PDF conversion. **Recommended for production.**

**Install LibreOffice:**
- Windows: [Download](https://www.libreoffice.org/download/download/)
- Linux: `sudo apt install libreoffice`
- macOS: `brew install libreoffice`

```env
DOCUMENT_PDF_CONVERSION=libreoffice
LIBREOFFICE_PATH="C:\Program Files\LibreOffice\program\soffice.exe"
```

### Dompdf (HTML-based Fallback)

No external dependencies, but may not preserve all formatting.

```env
DOCUMENT_PDF_CONVERSION=dompdf
```

### Error Handling

If LibreOffice is not installed, you'll get a helpful error:

```
LibreOffice is not installed or not found on this system.

To fix this, you have two options:

1. Install LibreOffice (recommended for best PDF quality):
   Download from: https://www.libreoffice.org/download/download/
   Then set the path in your .env file:
   LIBREOFFICE_PATH="C:\Program Files\LibreOffice\program\soffice.exe"

2. Use HTML-based conversion (no LibreOffice required):
   Set in your .env file:
   DOCUMENT_PDF_CONVERSION=dompdf
```

---

## Configuration

```php
// config/documentgenerator.php

return [
    // PDF conversion method: 'libreoffice' (default) or 'dompdf'
    'pdf_conversion' => env('DOCUMENT_PDF_CONVERSION', 'libreoffice'),
    
    // LibreOffice executable path (auto-detected if not set)
    'libreoffice_path' => env('LIBREOFFICE_PATH', null),
    
    // Default template storage path
    'template_path' => storage_path('app/document-templates'),
    
    // Default output path for permanent files
    'output_path' => storage_path('app/generated-documents'),
    
    // Save to temp directory (auto-deleted)
    'temp_output' => true,
    
    // Delete file after download() response
    'delete_after_download' => true,
    
    // Cleanup temp files on script end
    'cleanup_on_shutdown' => true,
    
    // Default storage disk
    'disk' => 'local',
];
```

---

## Standalone Usage (Without Laravel)

```php
require 'vendor/autoload.php';

use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;

$generator = new DocxToPdfGenerator();

// Set LibreOffice path
$generator->setLibreOfficePath('C:\Program Files\LibreOffice\program\soffice.exe');

// Generate PDF
$generator->generate(
    'template.docx',
    ['name' => 'John Doe', 'date' => '2024-01-15'],
    'output.pdf'
);
```

---

## Requirements

- PHP 8.1+
- Laravel 9.x, 10.x, 11.x, or 12.x
- GD extension for image processing
- LibreOffice (recommended) or Dompdf

---

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.

## Author

- [Ayoratoumvone](https://github.com/Ayoratou99)

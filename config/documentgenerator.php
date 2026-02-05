<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Conversion Method
    |--------------------------------------------------------------------------
    |
    | Method to convert DOCX to PDF:
    | - 'libreoffice': (default) Uses LibreOffice for best quality PDF output.
    |                  Preserves all formatting, styles, and layout from the
    |                  original DOCX document. Requires LibreOffice installation.
    | - 'dompdf': Uses HTML-based conversion. No external dependencies required,
    |             but may not preserve all formatting from the original document.
    |             Use this if LibreOffice is not available on your system.
    |
    | If LibreOffice is not installed and method is 'libreoffice', an exception
    | will be thrown with instructions to either install LibreOffice or switch
    | to 'dompdf' mode.
    |
    | LibreOffice download: https://www.libreoffice.org/download/download/
    |
    */
    'pdf_conversion' => env('DOCUMENT_PDF_CONVERSION', 'libreoffice'),

    /*
    |--------------------------------------------------------------------------
    | LibreOffice Path
    |--------------------------------------------------------------------------
    |
    | Path to the LibreOffice executable. Leave null for auto-detection.
    | 
    | Common paths:
    | - Windows: C:\Program Files\LibreOffice\program\soffice.exe
    | - Linux: /usr/bin/libreoffice or /usr/bin/soffice
    | - macOS: /Applications/LibreOffice.app/Contents/MacOS/soffice
    |
    */
    'libreoffice_path' => env('LIBREOFFICE_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | Template Path
    |--------------------------------------------------------------------------
    |
    | The default path where document templates are stored.
    |
    */
    'template_path' => storage_path('app/document-templates'),

    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | The default path where generated documents are saved.
    | If 'temp_output' is true, this will be ignored and system temp dir is used.
    |
    */
    'output_path' => storage_path('app/generated-documents'),

    /*
    |--------------------------------------------------------------------------
    | Temporary Output
    |--------------------------------------------------------------------------
    |
    | When true, generated PDF files are saved to the system temp directory
    | and automatically deleted after download or when the script ends.
    | This prevents storage from filling up with generated files.
    |
    | Set to false if you need to keep generated files permanently.
    |
    */
    'temp_output' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto Delete After Download
    |--------------------------------------------------------------------------
    |
    | When true, generated files are automatically deleted after being
    | downloaded. Only applies when using the download() method.
    |
    */
    'delete_after_download' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto Cleanup on Script End
    |--------------------------------------------------------------------------
    |
    | When true, temporary output files are registered for deletion when
    | the PHP script ends. This ensures cleanup even if download fails.
    |
    */
    'cleanup_on_shutdown' => true,

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default Laravel storage disk to use for generateToStorage().
    |
    */
    'disk' => 'local',
];

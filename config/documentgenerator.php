<?php

return [
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
    |
    */
    'output_path' => storage_path('app/generated-documents'),

    /*
    |--------------------------------------------------------------------------
    | Default Format
    |--------------------------------------------------------------------------
    |
    | The default output format for generated documents.
    | Supported: "docx", "pdf"
    |
    */
    'default_format' => 'docx',

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default Laravel storage disk to use.
    |
    */
    'disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Auto Delete
    |--------------------------------------------------------------------------
    |
    | Whether to automatically delete temporary files after generation.
    |
    */
    'auto_delete' => true,
];

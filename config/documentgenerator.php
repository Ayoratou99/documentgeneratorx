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

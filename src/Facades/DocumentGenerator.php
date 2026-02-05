<?php

namespace Ayoratoumvone\Documentgeneratorx\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * DocumentGeneratorX Facade
 * 
 * Generate PDF documents from DOCX or HTML templates.
 * 
 * @method static \Ayoratoumvone\Documentgeneratorx\DocumentGenerator template(string $source) Set template (DOCX or HTML)
 * @method static \Ayoratoumvone\Documentgeneratorx\DocumentGenerator templateFromStorage(string $path, string $disk = 'local')
 * @method static \Ayoratoumvone\Documentgeneratorx\DocumentGenerator variables(array $variables) Set variables to replace
 * @method static \Ayoratoumvone\Documentgeneratorx\DocumentGenerator addVariable(string $key, mixed $value)
 * @method static string generate(string $outputPath = null) Generate PDF and return path
 * @method static string generateToStorage(string $path, string $disk = 'local')
 * @method static \Symfony\Component\HttpFoundation\BinaryFileResponse download(string $filename = null) Download PDF
 * @method static \Ayoratoumvone\Documentgeneratorx\DocumentGenerator reset()
 * @method static array getVariables()
 * @method static string|null getTemplateFormat() Get template format (docx or html)
 *
 * @see \Ayoratoumvone\Documentgeneratorx\DocumentGenerator
 */
class DocumentGenerator extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'document-generator';
    }
}

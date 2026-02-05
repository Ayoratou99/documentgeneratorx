<?php

namespace Ayoratoumvone\Documentgeneratorx\Contracts;

/**
 * Interface for document generators
 * 
 * All document generators (DOCX, PDF, etc.) must implement this interface
 * to ensure consistent behavior across different output formats.
 */
interface GeneratorInterface
{
    /**
     * Generate a document from a template
     *
     * @param string $templatePath Path to the template file
     * @param array $variables Array of variables to replace in the template
     * @param string $outputPath Path where the generated document will be saved
     * @return void
     * @throws \Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException
     */
    public function generate(string $templatePath, array $variables, string $outputPath): void;
}

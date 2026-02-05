<?php

/**
 * Example: Generate PDF and DOCX documents with all variable types
 * 
 * This example demonstrates how to use DocumentGeneratorX in a Laravel application.
 * Copy this code into a Laravel controller or artisan command to test.
 */

use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

// Image URL to use for testing
$imageUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Google_Images_2015_logo.svg/1280px-Google_Images_2015_logo.svg.png';

/**
 * Example 1: Generate PDF with all variable types
 */
function generatePdfExample(string $imageUrl): string
{
    $templatePath = base_path('tests/templates/test_pdf_template.html');
    
    $pdfPath = DocumentGenerator::template($templatePath)
        ->variables([
            // Image with ratio (16:9)
            'company_logo' => $imageUrl,
            
            // Text variables
            'full_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'description' => 'This is a comprehensive test of DocumentGeneratorX package. It supports multiple variable types including text, numbers, images, dates, and booleans.',
            
            // Number variables
            'age' => 28,
            'quantity' => 150,
            'price' => 99,
            
            // Date variable
            'created_date' => date('Y-m-d'),
            
            // Boolean variable
            'is_active' => true,
        ])
        ->format('pdf')
        ->generate();
    
    return $pdfPath;
}

/**
 * Example 2: Generate DOCX with images and variables
 * 
 * Note: For DOCX, you need to create a Word template file with placeholders like:
 * {{company_logo:image,width:400,ratio:16:9}}
 * {{full_name:text}}
 * etc.
 */
function generateDocxExample(string $imageUrl): string
{
    $templatePath = storage_path('templates/sample.docx');
    
    $docxPath = DocumentGenerator::template($templatePath)
        ->variables([
            // Image with ratio
            'company_logo' => $imageUrl,
            
            // Text variables
            'full_name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'description' => 'Generated using DocumentGeneratorX',
            
            // Number variables
            'age' => 32,
            'quantity' => 75,
            'price' => 149,
            
            // Date variable
            'created_date' => date('Y-m-d H:i:s'),
            
            // Boolean variable
            'is_active' => false,
        ])
        ->format('docx')
        ->generate();
    
    return $docxPath;
}

/**
 * Example 3: Download document directly
 */
function downloadPdfExample(string $imageUrl)
{
    $templatePath = base_path('tests/templates/test_pdf_template.html');
    
    return DocumentGenerator::template($templatePath)
        ->variables([
            'company_logo' => $imageUrl,
            'full_name' => 'Download Test User',
            'email' => 'download@test.com',
            'description' => 'This PDF will be downloaded directly.',
            'age' => 25,
            'quantity' => 100,
            'price' => 50,
            'created_date' => date('Y-m-d'),
            'is_active' => true,
        ])
        ->format('pdf')
        ->download('test-document.pdf');
}

/**
 * Example 4: Save to Laravel storage
 */
function saveToStorageExample(string $imageUrl): string
{
    $templatePath = base_path('tests/templates/test_pdf_template.html');
    
    $storagePath = DocumentGenerator::template($templatePath)
        ->variables([
            'company_logo' => $imageUrl,
            'full_name' => 'Storage Test User',
            'email' => 'storage@test.com',
            'description' => 'This document is saved to Laravel storage.',
            'age' => 30,
            'quantity' => 200,
            'price' => 75,
            'created_date' => date('Y-m-d'),
            'is_active' => true,
        ])
        ->format('pdf')
        ->generateToStorage('documents/test-' . time() . '.pdf', 'local');
    
    return $storagePath;
}

// ============================================
// Usage in Laravel Controller
// ============================================

/*
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

class DocumentController extends Controller
{
    public function generatePdf()
    {
        $imageUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Google_Images_2015_logo.svg/1280px-Google_Images_2015_logo.svg.png';
        
        $pdfPath = DocumentGenerator::template(base_path('tests/templates/test_pdf_template.html'))
            ->variables([
                'company_logo' => $imageUrl,
                'full_name' => 'John Doe',
                'email' => 'john@example.com',
                'description' => 'Test document',
                'age' => 28,
                'quantity' => 150,
                'price' => 99,
                'created_date' => now()->format('Y-m-d'),
                'is_active' => true,
            ])
            ->format('pdf')
            ->generate();
        
        return response()->json([
            'success' => true,
            'path' => $pdfPath,
        ]);
    }
    
    public function downloadPdf()
    {
        $imageUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Google_Images_2015_logo.svg/1280px-Google_Images_2015_logo.svg.png';
        
        return DocumentGenerator::template(base_path('tests/templates/test_pdf_template.html'))
            ->variables([
                'company_logo' => $imageUrl,
                'full_name' => 'John Doe',
                'email' => 'john@example.com',
                'description' => 'Downloaded document',
                'age' => 28,
                'quantity' => 150,
                'price' => 99,
                'created_date' => now()->format('Y-m-d'),
                'is_active' => true,
            ])
            ->format('pdf')
            ->download('invoice.pdf');
    }
}
*/

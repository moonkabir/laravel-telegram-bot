<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIService
{
    private ?string $lastPdfExtractionMethod = null;

    /**
     * Last PDF extraction method used (pdf_parser|openai_assistant).
     */
    public function getLastPdfExtractionMethod(): ?string
    {
        return $this->lastPdfExtractionMethod;
    }

    /**
     * Extract text from document using OpenAI
     */
    public function extractText($filePath, $fileType)
    {
        $fullPath = $this->getFilePath($filePath);
        if (!$fullPath || !file_exists($fullPath)) {
            throw new \Exception('File not found: ' . $filePath);
        }

        Log::info('Processing file: ' . $fullPath);
        Log::info('File type: ' . $fileType);
        Log::info('File size: ' . filesize($fullPath) . ' bytes');

        // PDF: throw on failure (no soft error strings)
        if ($fileType === 'pdf') {
            return $this->extractTextFromPDF($fullPath);
        }

        // Images: Vision OCR — throw on failure
        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'], true)) {
            return $this->extractTextFromImage($fullPath);
        }

        try {
            if (in_array($fileType, ['txt', 'csv', 'log', 'md', 'json', 'xml', 'yaml', 'yml'])) {
                Log::info('Text file detected, reading directly');
                $content = file_get_contents($fullPath);
                return $content ?: "File is empty";
            }

            if ($fileType === 'docx') {
                return $this->extractTextFromDOCX($fullPath);
            }

            return $this->extractTextFromGenericFile($fullPath, $fileType);
        } catch (\Exception $e) {
            Log::error('Document processing error: ' . $e->getMessage());
            return "Error processing document: " . $e->getMessage();
        }
    }

    /**
     * Whether OpenAI returned a usable extraction (not an apology / binary junk).
     *
     * @param  int  $minLength  Minimum accepted text length (images may be short)
     */
    public function isFailedExtractionResponse(?string $text, int $minLength = 50): bool
    {
        if ($text === null || trim($text) === '' || strlen(trim($text)) < $minLength) {
            return true;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
            return true;
        }

        $lower = strtolower($text);
        $failureMarkers = [
            'extraction_failed',
            'unable to process',
            'unable to process the file',
            'internal errors',
            'different method',
            'cannot extract',
            'could not extract',
            'failed to extract',
            'issues with extracting',
            'download and check',
            "i'll try a different",
            'it seems there is an issue',
            'it seems there are issues',
            'it appears i am unable',
            'failed to process pdf',
            'error processing document',
            'provide me with a description',
            'extracting the text using your local tools',
            'unfortunately, i\'m currently unable',
            'no text extracted',
            'image is too large',
            'image processing failed',
        ];

        foreach ($failureMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the full file path from storage
     */
    private function getFilePath($filePath)
    {
        $possiblePaths = [
            Storage::disk('public')->path($filePath),
            storage_path('app/public/' . $filePath),
            storage_path('app/' . $filePath),
            public_path('storage/' . $filePath),
            $filePath,
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                Log::info('Found file at: ' . $path);
                return $path;
            }
        }

        Log::error('File not found: ' . $filePath);
        return null;
    }

    /**
     * Extract text from image using OpenAI Vision
     */
    private function extractTextFromImage($imagePath)
    {
        try {
            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = mime_content_type($imagePath);

            if (strlen($base64Image) > 5000000) {
                Log::warning('Image too large for OpenAI processing');
                throw new \Exception('Image is too large. Please compress to under 5MB.');
            }

            Log::info('Sending image to OpenAI for OCR');

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract ALL text from this image. If it contains a document, table, form, or handwritten text, extract it accurately and completely. Return ONLY the extracted text, no additional commentary. If there is truly no text, return exactly: NO_TEXT_FOUND'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64Image}",
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 4096,
                'temperature' => 0.1,
            ]);

            if (!$response || !isset($response->choices[0]->message->content)) {
                throw new \Exception('No text extracted from image');
            }

            $extractedText = trim($response->choices[0]->message->content);
            Log::info('Image OCR complete. Text length: ' . strlen($extractedText));

            if ($extractedText === '' || strcasecmp($extractedText, 'NO_TEXT_FOUND') === 0) {
                throw new \Exception('No readable text found in image');
            }

            return $extractedText;

        } catch (\Exception $e) {
            Log::error('Image OCR Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract text from PDF.
     * 1) Local PDF parser (reliable for text-based PDFs)
     * 2) OpenAI Assistants fallback
     */
    private function extractTextFromPDF($pdfPath)
    {
        $this->lastPdfExtractionMethod = null;

        Log::info('Attempting PDF text extraction with smalot/pdfparser');
        $parsedText = $this->extractTextWithPdfParser($pdfPath);

        if ($this->isUsableExtractedText($parsedText)) {
            $this->lastPdfExtractionMethod = 'pdf_parser';
            Log::info('PDF parser extraction successful. Length: ' . strlen($parsedText));
            return $parsedText;
        }

        Log::info('PDF parser returned insufficient text, trying OpenAI Assistants');

        try {
            $assistantText = $this->extractTextFromPDFUsingFileAPI($pdfPath);
            $this->lastPdfExtractionMethod = 'openai_assistant';
            return $assistantText;
        } catch (\Exception $e) {
            Log::error('OpenAI Assistants PDF fallback failed: ' . $e->getMessage());
            throw new \Exception(
                'PDF extraction failed: no readable text layer found, and OpenAI Assistants could not extract content from this file.',
                0,
                $e
            );
        }
    }

    /**
     * Extract embedded text from a PDF using smalot/pdfparser.
     */
    private function extractTextWithPdfParser(string $pdfPath): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = trim($pdf->getText() ?? '');

            // Normalize whitespace a bit for storage/chunking
            $text = preg_replace("/[ \t]+/", ' ', $text);
            $text = preg_replace("/\n{3,}/", "\n\n", $text);

            return trim($text);
        } catch (\Throwable $e) {
            Log::warning('PDF parser error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Usable document text (not empty, not binary, not an AI apology).
     */
    private function isUsableExtractedText(?string $text): bool
    {
        if ($this->isFailedExtractionResponse($text)) {
            return false;
        }

        $readableChars = preg_match_all('/[\p{L}\p{N}\s\.\,\;\:\!\?\(\)\-\/\n\r]/u', $text);
        $ratio = $readableChars / max(strlen($text), 1);

        return $ratio >= 0.6;
    }

    /**
     * Upload PDF to OpenAI Assistants and extract text.
     */
    private function extractTextFromPDFUsingFileAPI($pdfPath)
    {
        $tempFile = null;
        $fileId = null;
        $assistantId = null;
        $createdAssistant = false;

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
            if (file_put_contents($tempFile, file_get_contents($pdfPath)) === false) {
                throw new \Exception('Failed to create temporary PDF copy');
            }

            Log::info('Uploading PDF to OpenAI...');
            $file = OpenAI::files()->upload([
                'file' => fopen($tempFile, 'r'),
                'purpose' => 'assistants',
            ]);

            if (!$file || !isset($file->id)) {
                throw new \Exception('Failed to upload PDF to OpenAI');
            }

            $fileId = $file->id;
            Log::info('PDF uploaded to OpenAI with ID: ' . $fileId);

            $assistantId = config('openai.pdf_assistant_id');
            if (!$assistantId) {
                Log::info('Creating temporary OpenAI assistant for PDF extraction...');
                $assistant = OpenAI::assistants()->create([
                    'model' => 'gpt-4o-mini',
                    'name' => 'PDF Text Extractor',
                    'instructions' => 'You extract text from PDF files. Use code_interpreter to read the attached PDF. Return ONLY the extracted document text. If extraction fails, reply exactly: EXTRACTION_FAILED',
                    'tools' => [
                        ['type' => 'code_interpreter'],
                    ],
                ]);
                $assistantId = $assistant->id;
                $createdAssistant = true;
                Log::info('Assistant created with ID: ' . $assistantId);
            } else {
                Log::info('Using configured PDF assistant: ' . $assistantId);
            }

            Log::info('Creating OpenAI thread...');
            $thread = OpenAI::threads()->create([
                'messages' => [[
                    'role' => 'user',
                    'content' => 'Extract all text from the attached PDF. Return only the document text.',
                    'attachments' => [[
                        'file_id' => $fileId,
                        'tools' => [['type' => 'code_interpreter']],
                    ]],
                ]],
            ]);

            Log::info('Thread created with ID: ' . $thread->id);

            Log::info('Running assistant...');
            $run = OpenAI::threads()->runs()->create($thread->id, [
                'assistant_id' => $assistantId,
            ]);

            $this->waitForRun($thread->id, $run->id);

            Log::info('Retrieving messages...');
            $messages = OpenAI::threads()->messages()->list($thread->id);

            $extractedText = '';
            foreach ($messages->data as $message) {
                if ($message->role !== 'assistant') {
                    continue;
                }
                foreach ($message->content as $content) {
                    if (isset($content->text->value)) {
                        $extractedText .= $content->text->value . "\n\n";
                    }
                }
            }

            $extractedText = trim($extractedText);
            Log::info('PDF extraction complete. Text length: ' . strlen($extractedText));

            if ($this->isFailedExtractionResponse($extractedText)) {
                throw new \Exception('OpenAI could not extract text from this PDF');
            }

            return $extractedText;
        } catch (\Exception $e) {
            Log::error('PDF Assistants API error: ' . $e->getMessage());
            throw new \Exception('PDF extraction failed: ' . $e->getMessage(), 0, $e);
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }

            if ($fileId) {
                try {
                    OpenAI::files()->delete($fileId);
                } catch (\Throwable $e) {
                    Log::warning('OpenAI file cleanup failed: ' . $e->getMessage());
                }
            }

            if ($createdAssistant && $assistantId) {
                try {
                    OpenAI::assistants()->delete($assistantId);
                } catch (\Throwable $e) {
                    Log::warning('OpenAI assistant cleanup failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Wait for an Assistants run to complete.
     */
    private function waitForRun($threadId, $runId, $maxAttempts = 60)
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $run = OpenAI::threads()->runs()->retrieve($threadId, $runId);

            Log::info('Run status: ' . $run->status . ' (attempt ' . ($attempts + 1) . '/' . $maxAttempts . ')');

            if ($run->status === 'ready') {
                return $run;
            }

            if (in_array($run->status, ['failed', 'cancelled', 'expired'], true)) {
                $detail = $run->lastError->message ?? $run->status;
                throw new \Exception('Run failed with status: ' . $detail);
            }

            sleep(3);
            $attempts++;
        }

        throw new \Exception('Run timed out after ' . $maxAttempts . ' attempts');
    }

    /**
     * Extract text from DOCX
     */
    private function extractTextFromDOCX($docxPath)
    {
        try {
            // Try direct extraction first
            $text = $this->extractTextFromDOCXDirect($docxPath);
            if (!empty($text) && strlen($text) > 50) {
                Log::info('DOCX direct extraction successful. Length: ' . strlen($text));
                return $text;
            }

            // If direct fails, try reading as text
            $content = file_get_contents($docxPath);
            if (mb_check_encoding($content, 'UTF-8') && strlen($content) > 0) {
                return $content;
            }

            return "Could not extract text from DOCX. Please convert to text format.";

        } catch (\Exception $e) {
            Log::error('DOCX OCR Error: ' . $e->getMessage());
            return "Failed to extract text from DOCX: " . $e->getMessage();
        }
    }

    /**
     * Extract text from DOCX directly
     */
    private function extractTextFromDOCXDirect($docxPath)
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($docxPath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();

                if ($xml) {
                    $text = strip_tags($xml);
                    $text = html_entity_decode($text);
                    $text = preg_replace('/\s+/', ' ', $text);
                    return trim($text);
                }
            }
            return '';
        } catch (\Exception $e) {
            Log::error('DOCX direct extraction error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from generic file types
     */
    private function extractTextFromGenericFile($filePath, $fileType)
    {
        try {
            $content = file_get_contents($filePath);

            if (mb_check_encoding($content, 'UTF-8') && strlen($content) > 0) {
                return $content;
            }

            return "Unable to extract text from {$fileType} file.";

        } catch (\Exception $e) {
            Log::error('Generic file processing error: ' . $e->getMessage());
            return "Failed to process file: " . $e->getMessage();
        }
    }

    /**
     * Create embeddings for text using OpenAI
     */
    public function createEmbedding($text)
    {
        try {
            if (empty($text) || strlen($text) < 10) {
                Log::warning('Text too short for embedding');
                return null;
            }

            if (strlen($text) > 8000) {
                $text = substr($text, 0, 8000);
            }

            Log::info('Creating embedding for text length: ' . strlen($text));

            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-ada-002',
                'input' => $text,
            ]);

            if (!$response || !isset($response->embeddings[0]->embedding)) {
                Log::error('Invalid embedding response');
                return null;
            }

            return $response->embeddings[0]->embedding;

        } catch (\Exception $e) {
            Log::error('Embedding creation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Chunk text into smaller pieces for vector storage
     */
    public function chunkText($text, $chunkSize = 1000, $overlap = 200)
    {
        if (empty($text)) {
            return [];
        }

        $text = trim($text);
        $textLength = strlen($text);

        if ($textLength <= $chunkSize) {
            return [$text];
        }

        // Overlap must be smaller than chunk size or the cursor never advances
        $overlap = max(0, min($overlap, $chunkSize - 1));

        $chunks = [];
        $start = 0;
        $maxChunks = (int) ceil($textLength / max($chunkSize - $overlap, 1)) + 5;

        while ($start < $textLength) {
            if (count($chunks) >= $maxChunks) {
                Log::warning('chunkText aborted: max chunk safety limit reached');
                break;
            }

            $end = min($start + $chunkSize, $textLength);

            if ($end < $textLength) {
                $search = substr($text, $start, $chunkSize);
                $sentenceBreak = strrpos($search, '. ');
                if ($sentenceBreak !== false && $sentenceBreak > (int) ($chunkSize * 0.7)) {
                    $end = $start + $sentenceBreak + 2;
                } else {
                    $spaceBreak = strrpos($search, ' ');
                    if ($spaceBreak !== false && $spaceBreak > (int) ($chunkSize * 0.7)) {
                        $end = $start + $spaceBreak;
                    }
                }
            }

            // Guard: end must always move forward
            if ($end <= $start) {
                $end = min($start + $chunkSize, $textLength);
            }

            $chunk = trim(substr($text, $start, $end - $start));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            // Last segment — stop (do not rewind with overlap)
            if ($end >= $textLength) {
                break;
            }

            $nextStart = $end - $overlap;
            if ($nextStart <= $start) {
                $nextStart = $end; // force progress
            }
            $start = $nextStart;
        }

        Log::info('Created ' . count($chunks) . ' chunks');
        return $chunks;
    }

    /**
     * Summarize text using OpenAI
     */
    public function summarizeText($text, $maxLength = 500)
    {
        try {
            if (empty($text)) {
                return "No text to summarize.";
            }

            if (strlen($text) > 3000) {
                $text = substr($text, 0, 3000);
            }

            Log::info('Generating summary...');

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a document summarizer. Summarize the following text concisely in about {$maxLength} characters."
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ]);

            if (!$response || !isset($response->choices[0]->message->content)) {
                return "Unable to generate summary.";
            }

            return $response->choices[0]->message->content;

        } catch (\Exception $e) {
            Log::error('Text summarization error: ' . $e->getMessage());
            return "Summary generation failed: " . $e->getMessage();
        }
    }

    /**
     * Extract key information from document
     */
    public function extractKeyInfo($text)
    {
        try {
            if (empty($text)) {
                return ['error' => 'No text provided'];
            }

            if (strlen($text) > 3000) {
                $text = substr($text, 0, 3000);
            }

            Log::info('Extracting key information...');

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Extract key information from this document and return as a valid JSON object with these fields: title, author, date, keywords (array), summary, entities (people, organizations, locations)."
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.3,
            ]);

            if (!$response || !isset($response->choices[0]->message->content)) {
                return ['error' => 'API returned invalid response'];
            }

            $content = $response->choices[0]->message->content;

            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonData = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $jsonData;
                }
            }

            return ['raw_extracted' => $content];

        } catch (\Exception $e) {
            Log::error('Key info extraction error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}

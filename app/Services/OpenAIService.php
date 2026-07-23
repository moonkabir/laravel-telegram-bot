<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIService
{
    /**
     * Extract text from document using OpenAI
     */
    public function extractText($filePath, $fileType)
    {
        try {
            $fullPath = $this->getFilePath($filePath);

            if (!$fullPath || !file_exists($fullPath)) {
                throw new \Exception('File not found: ' . $filePath);
            }

            $fileSize = filesize($fullPath);
            Log::info('Processing file: ' . $fullPath);
            Log::info('File type: ' . $fileType);
            Log::info('File size: ' . $fileSize . ' bytes');

            // Handle different file types
            if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'])) {
                return $this->extractTextFromImage($fullPath);
            }

            if ($fileType === 'pdf') {
                return $this->extractTextFromPDF($fullPath);
            }

            if (in_array($fileType, ['txt', 'csv', 'log', 'md', 'json', 'xml', 'yaml', 'yml'])) {
                return $this->extractTextFromTextFile($fullPath);
            }

            if ($fileType === 'docx') {
                return $this->extractTextFromDOCX($fullPath);
            }

            return $this->extractTextFromGenericFile($fullPath, $fileType);

        } catch (\Exception $e) {
            Log::error('OpenAI OCR Error: ' . $e->getMessage());
            return "Error processing document: " . $e->getMessage();
        }
    }

    /**
     * Extract text from text files with chunking and OpenAI processing
     */
    private function extractTextFromTextFile($filePath)
    {
        try {
            $content = file_get_contents($filePath);

            if (empty($content)) {
                return "File is empty";
            }

            $fileSize = strlen($content);
            Log::info('Text file size: ' . $fileSize . ' characters');

            // ✅ If file is small, process directly
            if ($fileSize < 5000) {
                Log::info('Small text file, processing directly with OpenAI');
                return $this->processWithOpenAI($content, 'Clean and format this text');
            }

            // ✅ For large files, chunk and process
            Log::info('Large text file, chunking for OpenAI processing');

            // Create chunks with overlap for context
            $chunks = $this->chunkText($content, 2000, 200);

            Log::info('Created ' . count($chunks) . ' chunks');

            $processedChunks = [];
            $totalChunks = count($chunks);

            foreach ($chunks as $index => $chunk) {
                Log::info('Processing chunk ' . ($index + 1) . ' of ' . $totalChunks);

                try {
                    $processedChunk = $this->processWithOpenAI(
                        $chunk,
                        "Process this text chunk (chunk " . ($index + 1) . " of " . $totalChunks . "). Clean, format, and organize the content. Return ONLY the cleaned text."
                    );
                    $processedChunks[] = $processedChunk;

                } catch (\Exception $e) {
                    Log::error('Error processing chunk ' . ($index + 1) . ': ' . $e->getMessage());
                    // If OpenAI fails, use the raw chunk
                    $processedChunks[] = $chunk;
                }
            }

            // Combine all processed chunks
            $fullText = implode("\n\n", $processedChunks);
            Log::info('Text processing complete. Final length: ' . strlen($fullText));

            return $fullText;

        } catch (\Exception $e) {
            Log::error('Text file processing error: ' . $e->getMessage());
            return "Failed to process text file: " . $e->getMessage();
        }
    }

    /**
     * Process text with OpenAI with error handling
     */
    private function processWithOpenAI($text, $instruction)
    {
        try {
            // Ensure text is not too long
            if (strlen($text) > 8000) {
                $text = substr($text, 0, 8000);
            }

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a text processing assistant. $instruction Return ONLY the processed text, no additional commentary."
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ],
                'max_tokens' => 4096,
                'temperature' => 0.1,
            ]);

            if (!$response || !isset($response->choices[0]->message->content)) {
                Log::error('Invalid OpenAI response');
                return $text;
            }

            return $response->choices[0]->message->content;

        } catch (\Exception $e) {
            Log::error('OpenAI processing error: ' . $e->getMessage());
            return $text; // Return original text on error
        }
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

            // ✅ Check if file is too large for base64 encoding
            if (strlen($base64Image) > 20000000) { // 20MB limit
                Log::warning('Image too large for base64 encoding');
                return "Image is too large. Please compress the image.";
            }

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract ALL text from this image. If it contains a document, table, form, or handwritten text, extract it accurately and completely. Return ONLY the extracted text, no additional commentary.'
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
                Log::error('Invalid API response structure');
                return "No text extracted from image";
            }

            return $response->choices[0]->message->content;

        } catch (\Exception $e) {
            Log::error('Image OCR Error: ' . $e->getMessage());
            return "Failed to extract text from image: " . $e->getMessage();
        }
    }

    /**
     * Extract text from PDF using OpenAI
     */
    private function extractTextFromPDF($pdfPath)
    {
        try {
            // Try direct text extraction first
            $text = $this->extractTextFromPDFDirect($pdfPath);
            if (!empty($text) && strlen($text) > 100) {
                Log::info('PDF direct extraction successful. Length: ' . strlen($text));
                return $text;
            }

            // ✅ Try to process PDF using OpenAI's File API
            Log::info('Attempting PDF processing with OpenAI File API');
            return $this->extractTextFromPDFWithOpenAI($pdfPath);

        } catch (\Exception $e) {
            Log::error('PDF OCR Error: ' . $e->getMessage());
            return "Failed to extract text from PDF: " . $e->getMessage();
        }
    }

    /**
     * Extract text from PDF using OpenAI File API
     */
    private function extractTextFromPDFWithOpenAI($pdfPath)
    {
        try {
            $pdfContent = file_get_contents($pdfPath);
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
            file_put_contents($tempFile, $pdfContent);

            // Upload file to OpenAI
            $file = OpenAI::files()->upload([
                'file' => fopen($tempFile, 'r'),
                'purpose' => 'assistants',
            ]);

            if (!$file || !isset($file->id)) {
                throw new \Exception('Failed to upload PDF to OpenAI');
            }

            // Create assistant
            $assistant = OpenAI::assistants()->create([
                'model' => 'gpt-4o-mini',
                'name' => 'PDF Text Extractor',
                'instructions' => 'Extract all text from the provided PDF document. Return the complete text content with proper formatting.',
                'tools' => [['type' => 'retrieval']],
                'file_ids' => [$file->id],
            ]);

            // Create thread
            $thread = OpenAI::threads()->create([]);

            // Add message
            OpenAI::threads()->messages()->create($thread->id, [
                'role' => 'user',
                'content' => 'Please extract all text from the uploaded PDF document. Return the complete text with all formatting preserved.',
            ]);

            // Run assistant
            $run = OpenAI::threads()->runs()->create($thread->id, [
                'assistant_id' => $assistant->id,
            ]);

            // Wait for completion
            $run = $this->waitForRun($thread->id, $run->id);

            // Get messages
            $messages = OpenAI::threads()->messages()->list($thread->id);

            $extractedText = '';
            foreach ($messages->data as $message) {
                if ($message->role === 'assistant') {
                    foreach ($message->content as $content) {
                        if (isset($content->text->value)) {
                            $extractedText .= $content->text->value . "\n\n";
                        }
                    }
                }
            }

            // Clean up
            try {
                OpenAI::files()->delete($file->id);
                OpenAI::assistants()->delete($assistant->id);
            } catch (\Exception $e) {
                Log::warning('Cleanup error: ' . $e->getMessage());
            }

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            Log::info('PDF extraction complete. Text length: ' . strlen($extractedText));

            return $extractedText ?: "No text extracted from PDF";

        } catch (\Exception $e) {
            Log::error('PDF File API error: ' . $e->getMessage());

            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }

            throw new \Exception('Failed to process PDF with OpenAI: ' . $e->getMessage());
        }
    }

    /**
     * Extract text from PDF directly
     */
    private function extractTextFromPDFDirect($pdfPath)
    {
        try {
            $content = file_get_contents($pdfPath);
            preg_match_all('/\(([^)]*)\)/', $content, $matches);

            if (!empty($matches[1])) {
                $text = implode(' ', $matches[1]);
                $text = str_replace(['\\\\(', '\\\\)', '\\\\n', '\\\\r', '\\\\t'], ['(', ')', "\n", "\r", "\t"], $text);
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
            }
            return '';
        } catch (\Exception $e) {
            Log::error('Direct PDF extraction failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from DOCX using OpenAI
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

            // ✅ Try to process with OpenAI
            Log::info('Processing DOCX with OpenAI');
            $docxContent = file_get_contents($docxPath);
            $base64Docx = base64_encode($docxContent);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract ALL text from this Word document. Include all headings, paragraphs, tables, bullet points, and any formatted text. Return ONLY the extracted text.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,{$base64Docx}",
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 4096,
            ]);

            if (!$response || !isset($response->choices[0]->message->content)) {
                return "No text extracted from DOCX";
            }

            return $response->choices[0]->message->content;

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
                // Process with OpenAI if text is clean
                if (strlen($content) < 5000) {
                    return $this->processWithOpenAI($content, "Clean and format this text");
                }
                return $content;
            }

            return "Unable to extract text from {$fileType} file.";

        } catch (\Exception $e) {
            Log::error('Generic file processing error: ' . $e->getMessage());
            return "Failed to process file: " . $e->getMessage();
        }
    }

    /**
     * Wait for a run to complete
     */
    private function waitForRun($threadId, $runId, $maxAttempts = 30)
    {
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $run = OpenAI::threads()->runs()->retrieve($threadId, $runId);

            if ($run->status === 'completed') {
                return $run;
            }

            if (in_array($run->status, ['failed', 'cancelled', 'expired'])) {
                throw new \Exception('Run failed with status: ' . $run->status);
            }

            sleep(2);
            $attempts++;
        }

        throw new \Exception('Run timed out after ' . $maxAttempts . ' attempts');
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
                return $path;
            }
        }

        return null;
    }

    /**
     * Create embeddings for text using OpenAI
     */
    public function createEmbedding($text)
    {
        try {
            if (empty($text)) {
                return null;
            }

            // Truncate to safe length
            if (strlen($text) > 8000) {
                $text = substr($text, 0, 8000);
            }

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

        // If text is smaller than chunk size, return as single chunk
        if ($textLength <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;

        while ($start < $textLength) {
            // Calculate end position
            $end = $start + $chunkSize;

            // If end is beyond text length, use text length
            if ($end >= $textLength) {
                $chunks[] = trim(substr($text, $start));
                break;
            }

            // Try to find a good break point
            $search = substr($text, $start, $chunkSize);

            // Try to break at sentence boundary (period followed by space)
            $sentenceBreak = strrpos($search, '. ');
            if ($sentenceBreak !== false && $sentenceBreak > $chunkSize * 0.6) {
                $end = $start + $sentenceBreak + 2;
            } else {
                // Try to break at space
                $spaceBreak = strrpos($search, ' ');
                if ($spaceBreak !== false && $spaceBreak > $chunkSize * 0.6) {
                    $end = $start + $spaceBreak;
                }
            }

            // Ensure we don't go backwards or create empty chunks
            if ($end <= $start) {
                $end = $start + $chunkSize;
            }

            // Extract the chunk
            $chunk = trim(substr($text, $start, $end - $start));
            if (!empty($chunk)) {
                $chunks[] = $chunk;
            }

            // Move start position for next chunk (with overlap)
            $start = $end - $overlap;

            // Prevent infinite loop
            if ($start >= $textLength) {
                break;
            }

            // Ensure progress is made
            if ($start <= $end && $start > 0) {
                // If we're stuck, force progress
                $start = $end + 1;
            }
        }

        // Remove any empty chunks
        $chunks = array_filter($chunks, function($chunk) {
            return !empty(trim($chunk));
        });

        // If no chunks were created, return the original text as one chunk
        if (empty($chunks)) {
            return [$text];
        }

        return array_values($chunks);
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

            if (strlen($text) > 8000) {
                $text = substr($text, 0, 8000);
            }

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
            return "Failed to generate summary: " . $e->getMessage();
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

            if (strlen($text) > 8000) {
                $text = substr($text, 0, 8000);
            }

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

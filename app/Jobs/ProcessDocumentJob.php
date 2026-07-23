<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 2;
    public $backoff = [60, 120];

    protected $documentId;
    protected $filePath;
    protected $fileType;

    public function __construct($documentId, $filePath, $fileType)
    {
        $this->documentId = $documentId;
        $this->filePath = $filePath;
        $this->fileType = $fileType;

        set_time_limit(600);
    }

    public function handle(OpenAIService $openAIService)
    {
        // ✅ FIX: Use DB transaction to avoid dirty-tracking issues
        DB::beginTransaction();

        try {
            Log::info('Processing document job started', [
                'document_id' => $this->documentId,
                'file_path' => $this->filePath,
                'file_type' => $this->fileType
            ]);

            // ✅ FIX: Use fresh() to avoid dirty-tracking
            $document = Document::find($this->documentId);

            if (!$document) {
                Log::error('Document not found', ['document_id' => $this->documentId]);
                DB::rollBack();
                return;
            }

            // Update status using fresh instance
            $document->status = 'processing';
            $document->save();
            $document->refresh();

            // Get full file path
            $fullPath = Storage::disk('public')->path($this->filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception('File not found: ' . $fullPath);
            }

            $fileSize = filesize($fullPath);
            Log::info('File size: ' . $fileSize . ' bytes');

            // ✅ Process based on file type
            $extractedText = null;
            $extractionMethod = 'unknown';

            // TEXT FILES - Read directly
            if (in_array($this->fileType, ['txt', 'csv', 'log', 'md', 'json', 'xml', 'yaml', 'yml'])) {
                Log::info('Text file detected, reading directly');
                $extractedText = file_get_contents($fullPath);
                $extractionMethod = 'direct_read';

                if (empty($extractedText)) {
                    throw new \Exception('File is empty');
                }
            }

            // IMAGES - Use OpenAI (only if small enough)
            elseif (in_array($this->fileType, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                Log::info('Image file detected, processing with OpenAI');

                if ($fileSize > 5000000) { // 5MB
                    throw new \Exception('Image too large for processing (max 5MB)');
                }

                $extractedText = $openAIService->extractText($this->filePath, $this->fileType);
                $extractionMethod = 'openai_vision';

                if (empty($extractedText) || str_contains($extractedText, 'Error')) {
                    throw new \Exception('Image extraction failed: ' . $extractedText);
                }
            }

            // PDF - Try direct first, then OpenAI
            elseif ($this->fileType === 'pdf') {
                Log::info('PDF file detected');

                // Try direct extraction
                $extractedText = $this->extractTextFromPDFDirect($fullPath);

                if (!empty($extractedText) && strlen($extractedText) > 100) {
                    $extractionMethod = 'pdf_direct';
                    Log::info('PDF direct extraction successful');
                } elseif ($fileSize < 2000000) { // 2MB
                    Log::info('Processing PDF with OpenAI');
                    $extractedText = $openAIService->extractText($this->filePath, $this->fileType);
                    $extractionMethod = 'openai_pdf';

                    if (empty($extractedText) || str_contains($extractedText, 'Error')) {
                        throw new \Exception('PDF extraction failed: ' . $extractedText);
                    }
                } else {
                    throw new \Exception('PDF too large for OpenAI and direct extraction failed');
                }
            }

            // DOCX - Direct extraction
            elseif ($this->fileType === 'docx') {
                Log::info('DOCX file detected');
                $extractedText = $this->extractTextFromDOCXDirect($fullPath);
                $extractionMethod = 'docx_direct';

                if (empty($extractedText) || strlen($extractedText) < 50) {
                    throw new \Exception('DOCX extraction failed');
                }
            }

            // Default - mark as completed with note
            else {
                Log::info('Unsupported file type: ' . $this->fileType);
                $extractedText = "File type: {$this->fileType}. Size: {$fileSize} bytes. This file type is not supported for text extraction.";
                $extractionMethod = 'unsupported';
            }

            // ✅ Save the extracted text
            $metadata = $document->metadata ?? [];
            $metadata['text_length'] = strlen($extractedText);
            $metadata['processed_at'] = now()->toDateTimeString();
            $metadata['extraction_method'] = $extractionMethod;
            $metadata['file_type'] = $this->fileType;
            $metadata['file_size'] = $fileSize;

            $document->extracted_text = $extractedText;
            $document->metadata = $metadata;
            $document->status = 'completed';
            $document->save();
            $document->refresh();

            Log::info('Document saved successfully', [
                'document_id' => $document->id,
                'extraction_method' => $extractionMethod,
                'text_length' => strlen($extractedText)
            ]);

            // ✅ Create chunks (in a separate try-catch)
            try {
                $this->createChunks($document, $extractedText, $openAIService);
            } catch (\Exception $e) {
                Log::error('Chunk creation failed but document was saved: ' . $e->getMessage());
                // Don't fail the job, just log the error
            }

            DB::commit();
            Log::info('Document processing completed successfully', ['document_id' => $this->documentId]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Document processing failed', [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // ✅ Update document status to failed (outside transaction if needed)
            try {
                $document = Document::find($this->documentId);
                if ($document) {
                    $document->status = 'failed';
                    $document->error_message = substr($e->getMessage(), 0, 500);
                    $document->save();
                }
            } catch (\Exception $updateError) {
                Log::error('Failed to update document status: ' . $updateError->getMessage());
            }

            // ✅ Don't retry for permanent errors
            $permanentErrors = ['File not found', 'File is empty', 'Image too large', 'PDF too large'];
            foreach ($permanentErrors as $permanentError) {
                if (str_contains($e->getMessage(), $permanentError)) {
                    $this->delete();
                    return;
                }
            }

            throw $e;
        }
    }

    private function createChunks($document, $text, OpenAIService $openAIService)
    {
        try {
            if (empty($text)) {
                Log::info('Text too short for chunking, skipping');
                return;
            }

            $chunks = $openAIService->chunkText($text, 1500, 200);

            if (empty($chunks)) {
                Log::warning('No chunks created');
                return;
            }

            Log::info('Creating ' . count($chunks) . ' chunks');

            $savedCount = 0;
            foreach ($chunks as $index => $chunkContent) {
                if (empty(trim($chunkContent))) {
                    continue;
                }

                try {
                    // Try to create embedding
                    $embedding = null;
                    try {
                        if (strlen($chunkContent) > 100) {
                            $embedding = $openAIService->createEmbedding($chunkContent);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Embedding creation failed for chunk ' . $index);
                    }

                    DocumentChunk::create([
                        'document_id' => $document->id,
                        'content' => $chunkContent,
                        'chunk_index' => $index,
                        'embedding' => $embedding,
                    ]);

                    $savedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to save chunk ' . $index . ': ' . $e->getMessage());
                }
            }

            Log::info('Saved ' . $savedCount . ' chunks');

        } catch (\Exception $e) {
            Log::error('Chunk creation error: ' . $e->getMessage());
            throw $e;
        }
    }

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
            Log::error('PDF direct extraction failed: ' . $e->getMessage());
            return '';
        }
    }

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
            Log::error('DOCX direct extraction failed: ' . $e->getMessage());
            return '';
        }
    }
}

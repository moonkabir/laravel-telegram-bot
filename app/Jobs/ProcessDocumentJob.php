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

    public $timeout = 900; // 15 minutes
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

        set_time_limit(900);
        ini_set('max_execution_time', 900);
    }

    public function handle(OpenAIService $openAIService)
    {
        DB::beginTransaction();

        try {
            Log::info('Processing document job started', [
                'document_id' => $this->documentId,
                'file_path' => $this->filePath,
                'file_type' => $this->fileType
            ]);

            $document = Document::find($this->documentId);

            if (!$document) {
                Log::error('Document not found', ['document_id' => $this->documentId]);
                DB::rollBack();
                return;
            }

            $document->status = 'processing';
            $document->save();
            $document->refresh();

            $fullPath = Storage::disk('public')->path($this->filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception('File not found: ' . $fullPath);
            }

            $fileSize = filesize($fullPath);
            Log::info('File size: ' . $fileSize . ' bytes');

            $extractedText = null;
            $extractionMethod = 'unknown';

            if (in_array($this->fileType, ['txt', 'csv', 'log', 'md', 'json', 'xml', 'yaml', 'yml'])) {
                Log::info('Text file detected, reading directly');
                $extractedText = file_get_contents($fullPath);
                $extractionMethod = 'direct_read';

                if (empty($extractedText)) {
                    throw new \Exception('File is empty');
                }
            } elseif (in_array($this->fileType, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                Log::info('Image file detected, processing with OpenAI Vision');

                if ($fileSize > 5000000) {
                    throw new \Exception('Image too large for processing (max 5MB)');
                }

                $extractedText = $openAIService->extractText($this->filePath, $this->fileType);
                $extractionMethod = 'openai_vision';

                // Images may contain very little text; only reject empty/apology/binary
                if ($openAIService->isFailedExtractionResponse($extractedText, 1)) {
                    Log::error('Image OCR rejected', [
                        'preview' => substr((string) $extractedText, 0, 200),
                        'length' => strlen((string) $extractedText),
                    ]);
                    throw new \Exception('Image extraction failed: no readable text found in image');
                }
            } elseif ($this->fileType === 'pdf') {
                Log::info('PDF file detected, extracting text');

                if ($fileSize > 15000000) {
                    throw new \Exception('PDF too large for processing (max 15MB)');
                }

                $extractedText = $openAIService->extractText($this->filePath, $this->fileType);
                $extractionMethod = $openAIService->getLastPdfExtractionMethod() ?? 'pdf';

                if ($openAIService->isFailedExtractionResponse($extractedText)) {
                    throw new \Exception('PDF extraction failed: OpenAI returned no usable text');
                }

                Log::info('PDF extraction successful', [
                    'method' => $extractionMethod,
                    'length' => strlen($extractedText),
                ]);
            } elseif ($this->fileType === 'docx') {
                Log::info('DOCX file detected');
                $extractedText = $this->extractTextFromDOCXDirect($fullPath);
                $extractionMethod = 'docx_direct';

                if (empty($extractedText) || strlen(trim($extractedText)) < 1) {
                    throw new \Exception('DOCX extraction failed');
                }
            } else {
                throw new \Exception("Unsupported file type: {$this->fileType}");
            }

            $metadata = $document->metadata ?? [];
            $metadata['text_length'] = strlen($extractedText);
            $metadata['processed_at'] = now()->toDateTimeString();
            $metadata['extraction_method'] = $extractionMethod;
            $metadata['file_type'] = $this->fileType;
            $metadata['file_size'] = $fileSize;

            $document->extracted_text = $extractedText;
            $document->metadata = $metadata;
            $document->status = 'completed';
            $document->error_message = null;
            $document->save();
            $document->refresh();

            Log::info('Document saved successfully', [
                'document_id' => $document->id,
                'extraction_method' => $extractionMethod,
                'text_length' => strlen($extractedText)
            ]);

            try {
                $this->createChunks($document, $extractedText, $openAIService);
            } catch (\Exception $e) {
                Log::error('Chunk creation failed but document was saved: ' . $e->getMessage());
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

            $permanentErrors = [
                'File not found',
                'File is empty',
                'Image too large',
                'PDF too large',
                'PDF extraction failed',
                'Unsupported file type',
                'OpenAI could not extract',
            ];

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
        if (empty($text) || strlen(trim($text)) < 1) {
            Log::info('Text empty for chunking, skipping');
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
            Log::error('DOCX extraction failed: ' . $e->getMessage());
            return '';
        }
    }
}

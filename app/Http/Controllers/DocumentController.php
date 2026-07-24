<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Jobs\ProcessDocumentJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class DocumentController extends Controller
{
    public function index()
    {
        try {
            $documents = Document::withCount('chunks')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

        } catch (\Exception $e) {
            Log::error('Failed to fetch documents: ' . $e->getMessage());
            $documents = collect([]);
        }

        return view('documents.index', compact('documents'));
    }

    public function upload(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'file' => 'required|file|max:102400|mimes:pdf,jpg,jpeg,png,gif,bmp,docx,txt,csv,log,md,json,xml',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            // Upload file
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->store('documents', 'public');

            if (!$path) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to upload file'
                ], 500);
            }

            // Get file info
            $fileType = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();

            // Create document record with pending status
            $document = Document::create([
                'name' => $request->name,
                'file_path' => $path,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'status' => 'pending',
                'metadata' => [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_at' => now()->toDateTimeString(),
                ],
            ]);

            // ✅ Dispatch job for processing
            ProcessDocumentJob::dispatch($document->id, $path, $fileType);

            Log::info('Document queued for processing', ['document_id' => $document->id]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully! Processing in background.',
                'document_id' => $document->id,
                'name' => $request->name,
                'status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('Document upload error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStatus($id)
    {
        try {
            $document = Document::findOrFail($id);

            return response()->json([
                'success' => true,
                'status' => $document->status,
                'message' => $document->error_message ?? null,
                'metadata' => $document->metadata,
                'text_length' => $document->extracted_text ? strlen($document->extracted_text) : 0,
                'chunks_count' => $document->chunks()->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found'
            ], 404);
        }
    }

    public function search(Request $request)
    {
        try {
            $query = $request->get('q', '');

            if (empty($query)) {
                $documents = Document::withCount('chunks')
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

                return response()->json([
                    'success' => true,
                    'documents' => $documents,
                ]);
            }

            $documents = Document::withCount('chunks')
                ->where('name', 'LIKE', "%{$query}%")
                ->orWhere('extracted_text', 'LIKE', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'documents' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $document = Document::findOrFail($id);

            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Document deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $document = Document::with('chunks')->findOrFail($id);

            return response()->json([
                'success' => true,
                'document' => $document,
                'chunks' => $document->chunks,
                'metadata' => $document->metadata,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found'
            ], 404);
        }
    }
}

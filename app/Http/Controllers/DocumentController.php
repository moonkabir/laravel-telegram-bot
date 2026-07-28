<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index()
    {
        try {
            $documents = Document::withCount('chunks')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

        } catch (\Exception $e) {
            Log::error('Failed to fetch documents: '.$e->getMessage());
            $documents = collect([]);
        }

        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        $documents = Document::all();

        return view('documents.create', compact('documents'));
    }

    public function upload(Request $request)
    {
        $path = null;

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'input_type' => 'required|in:pdf,text',
                'file' => 'nullable|required_if:input_type,pdf|file|max:10240|mimes:pdf,txt',
                'content' => 'nullable|required_if:input_type,text|string|min:10|max:50000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first(),
                ], 422);
            }

            if ($request->input_type === 'pdf') {
                $file = $request->file('file');
                $path = $file->store('documents', 'public');
                $fileType = $file->getClientOriginalExtension();
                $fileSize = $file->getSize();
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
            } else {
                $content = trim((string) $request->content);
                $path = 'documents/'.Str::uuid().'.txt';

                if (! Storage::disk('public')->put($path, $content)) {
                    $path = null;
                }

                $fileType = 'txt';
                $fileSize = strlen($content);
                $originalName = null;
                $mimeType = 'text/plain';
            }

            if ($path === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to save policy content',
                ], 500);
            }

            $document = Document::create([
                'name' => $request->name,
                'file_path' => $path,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'status' => 'pending',
                'metadata' => [
                    'input_type' => $request->input_type,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'uploaded_at' => now()->toDateTimeString(),
                ],
            ]);

            ProcessDocumentJob::dispatch($document->id, $path, $fileType);

            Log::info('Document queued for processing', ['document_id' => $document->id]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully! Processing in background.',
                'document_id' => $document->id,
                'name' => $request->name,
                'input_type' => $request->input_type,
                'status' => 'pending',
            ]);

        } catch (\Exception $e) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Document upload error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Failed to upload document: '.$e->getMessage(),
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
                'chunks_count' => $document->chunks()->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found',
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
            Log::error('Search error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Search failed: '.$e->getMessage(),
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
                'message' => 'Document deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Document deletion error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete document: '.$e->getMessage(),
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
                'error' => 'Document not found',
            ], 404);
        }
    }
}

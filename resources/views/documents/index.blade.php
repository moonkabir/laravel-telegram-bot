@extends('layouts.admin')
@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Search -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" id="searchInput" placeholder="Search documents..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex gap-2">
                    <select id="searchType" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="text">Text Search</option>
                        <option value="semantic">Semantic Search</option>
                    </select>
                    <button id="searchBtn" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
        <!-- Document List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-lg font-semibold mb-2">Uploaded Policies</h2>
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm">{{ $documents->count() }} policies</span>
                </div>
                <a href="{{ route('documents.create') }}" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-plus"></i> Upload
                </a>
            </div>            
            <div id="documentList" class="space-y-3">
                @forelse($documents as $doc)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
                        <div class="flex items-center space-x-3 flex-1">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-200">
                                <i class="fas {{ $doc->file_icon }}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800">{{ $doc->name }}</p>
                                <div class="flex items-center space-x-2 text-xs text-gray-500">
                                    <span>{{ ($doc->metadata['input_type'] ?? $doc->file_type) === 'text' ? 'Text' : 'PDF' }}</span>
                                    <span>•</span>
                                    <span>{{ $doc->formatted_size }}</span>
                                    <span>•</span>
                                    <span>{{ $doc->created_at->format('d-M-Y') }}</span>
                                    <span>•</span>
                                    <span id="doc-{{ $doc->id }}-status" class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $doc->status === 'ready' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $doc->status === 'processing' ? 'bg-blue-100 text-blue-700 animate-pulse' : '' }}
                                        {{ $doc->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $doc->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                    ">
                                        {{ $doc->status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($doc->status === 'ready')
                                <button onclick="viewDocument({{ $doc->id }})"
                                        class="text-blue-500 hover:text-blue-700 transition px-2 py-1 rounded hover:bg-blue-50">
                                    <i class="fas fa-eye"></i>
                                </button>
                            @endif
                            <button onclick="deleteDocument({{ $doc->id }})"
                                    class="text-gray-400 hover:text-red-500 transition px-2 py-1 rounded hover:bg-red-50">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-file-circle-plus text-3xl block mb-2"></i>
                        <p>No policies uploaded yet</p>
                        <p class="text-xs mt-1">Upload your first policy above</p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $documents->links() }}
            </div>
        </div>
    </div>

    <!-- View Document Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-semibold">Document Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modalContent" class="p-4 overflow-y-auto max-h-[calc(90vh-100px)]">
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold mb-2">Extracted Text Preview</h4>
                        <p id="modalText" class="text-sm text-gray-600 whitespace-pre-wrap"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold mb-2">Summary</h4>
                            <p id="modalSummary" class="text-sm text-gray-600"></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold mb-2">Key Information</h4>
                            <div id="modalKeyInfo" class="text-sm text-gray-600"></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold mb-2">Chunks</h4>
                        <div id="modalChunks" class="space-y-2 max-h-60 overflow-y-auto"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>

        // Delete document
        async function deleteDocument(id) {
            if (!confirm('Delete this document?')) return;

            try {
                const response = await fetch(`/documents/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                if (data.success) {
                    location.reload();
                }
            } catch (error) {
                alert('Error deleting document');
            }
        }

        // View document
        async function viewDocument(id) {
            try {
                const response = await fetch(`/documents/${id}`);
                const data = await response.json();

                if (data.success) {
                    const doc = data.document;
                    document.getElementById('modalTitle').textContent = doc.name;
                    document.getElementById('modalText').textContent = doc.extracted_text || 'No text extracted';

                    if (doc.metadata && doc.metadata.summary) {
                        document.getElementById('modalSummary').textContent = doc.metadata.summary;
                    }

                    if (doc.metadata && doc.metadata.key_info) {
                        const keyInfo = doc.metadata.key_info;
                        let html = '';
                        if (keyInfo.title) html += `<p><strong>Title:</strong> ${keyInfo.title}</p>`;
                        if (keyInfo.author) html += `<p><strong>Author:</strong> ${keyInfo.author}</p>`;
                        if (keyInfo.date) html += `<p><strong>Date:</strong> ${keyInfo.date}</p>`;
                        if (keyInfo.keywords) html += `<p><strong>Keywords:</strong> ${keyInfo.keywords}</p>`;
                        document.getElementById('modalKeyInfo').innerHTML = html || 'No key information extracted';
                    }

                    // Show chunks
                    const chunksContainer = document.getElementById('modalChunks');
                    chunksContainer.innerHTML = '';
                    if (data.chunks && data.chunks.length > 0) {
                        data.chunks.forEach((chunk, index) => {
                            const div = document.createElement('div');
                            div.className = 'bg-white p-2 rounded border border-gray-200';
                            div.innerHTML = `
                                <span class="text-xs text-gray-500">Chunk ${index + 1}</span>
                                <p class="text-sm">${chunk.content.substring(0, 200)}...</p>
                            `;
                            chunksContainer.appendChild(div);
                        });
                    } else {
                        chunksContainer.innerHTML = '<p class="text-gray-500">No chunks available</p>';
                    }

                    document.getElementById('viewModal').classList.remove('hidden');
                    document.getElementById('viewModal').classList.add('flex');
                }
            } catch (error) {
                alert('Error loading document details');
            }
        }

        function closeModal() {
            document.getElementById('viewModal').classList.add('hidden');
            document.getElementById('viewModal').classList.remove('flex');
        }

        // Close modal on background click
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Search
        document.getElementById('searchBtn').addEventListener('click', async function() {
            const query = document.getElementById('searchInput').value;
            const type = document.getElementById('searchType').value;

            if (!query.trim()) {
                location.reload();
                return;
            }

            try {
                const response = await fetch(`/documents/search?q=${encodeURIComponent(query)}&type=${type}`);
                const data = await response.json();

                if (data.success) {
                    const list = document.getElementById('documentList');
                    list.innerHTML = '';

                    if (data.documents.data.length === 0) {
                        list.innerHTML = `
                            <div class="text-center text-gray-400 py-8">
                                <i class="fas fa-search text-3xl block mb-2"></i>
                                <p>No documents found for "${query}"</p>
                            </div>
                        `;
                        return;
                    }

                    data.documents.data.forEach(doc => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition';
                        div.innerHTML = `
                            <div class="flex items-center space-x-3 flex-1">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-200">
                                    <i class="fas ${getFileIcon(doc.file_type)}"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-800">${doc.name}</p>
                                    <div class="flex items-center space-x-2 text-xs text-gray-500">
                                        <span>${doc.file_type}</span>
                                        <span>•</span>
                                        <span>${formatBytes(doc.file_size)}</span>
                                        <span>•</span>
                                        <span>${new Date(doc.created_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="viewDocument(${doc.id})" class="text-blue-500 hover:text-blue-700 transition px-2 py-1 rounded hover:bg-blue-50">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="deleteDocument(${doc.id})" class="text-gray-400 hover:text-red-500 transition px-2 py-1 rounded hover:bg-red-50">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                        list.appendChild(div);
                    });
                }
            } catch (error) {
                alert('Search failed');
            }
        });

        // Helper functions
        function getFileIcon(type) {
            const icons = {
                'pdf': 'fa-file-pdf text-red-500',
                'doc': 'fa-file-word text-blue-500',
                'docx': 'fa-file-word text-blue-500',
                'jpg': 'fa-file-image text-green-500',
                'jpeg': 'fa-file-image text-green-500',
                'png': 'fa-file-image text-green-500',
                'gif': 'fa-file-image text-green-500',
                'bmp': 'fa-file-image text-green-500',
                'txt': 'fa-file-lines text-gray-500',
            };
            return icons[type.toLowerCase()] || 'fa-file text-gray-400';
        }

        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return bytes.toFixed(2) + ' ' + units[i];
        }

        // Enter key for search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });


        // Status polling for documents
        function checkDocumentStatus(documentId) {
            const interval = setInterval(async () => {
                try {
                    const response = await fetch(`/documents/status/${documentId}`);
                    const data = await response.json();

                    if (data.success) {
                        const statusBadge = document.getElementById(`doc-${documentId}-status`);
                        if (statusBadge) {
                            statusBadge.textContent = data.status;
                            statusBadge.className = `px-2 py-0.5 rounded-full text-xs font-medium
                                ${data.status === 'ready' ? 'bg-green-100 text-green-700' : ''}
                                ${data.status === 'processing' ? 'bg-blue-100 text-blue-700 animate-pulse' : ''}
                                ${data.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ''}
                                ${data.status === 'failed' ? 'bg-red-100 text-red-700' : ''}
                            `;
                        }

                        if (data.status === 'ready' || data.status === 'failed') {
                            clearInterval(interval);
                            if (data.status === 'ready') {
                                setTimeout(() => location.reload(), 2000);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error checking status:', error);
                }
            }, 3000);
        }

        @foreach($documents as $doc)
            @if(in_array($doc->status, ['pending', 'processing']))
                checkDocumentStatus({{ $doc->id }});
            @endif
        @endforeach
    </script>
@endsection
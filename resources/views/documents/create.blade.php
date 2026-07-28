@extends('layouts.admin')
@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Upload Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">
                <i class="fas fa-upload text-blue-500"></i> Upload Policy
            </h2>
            <form id="uploadForm" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Policy Name</label>
                    <input type="text" name="name" id="docName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter policy name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                    <input type="file" name="file" id="docFile" accept=".pdf,.txt" required class="w-full px-4 py-2 border border-gray-300 rounded-lg file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Supported: PDF, TXT (Max 10MB)</p>
                </div>
                <button type="submit" id="uploadBtn" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50">
                    <i class="fas fa-upload"></i> Upload & Process with AI
                </button>
            </form>
            <div id="uploadStatus" class="mt-4 hidden"></div>
            <div id="uploadProgress" class="mt-4 hidden">
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div id="progressBar" class="bg-blue-500 h-2.5 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
                <p id="progressText" class="text-xs text-gray-500 mt-1">Processing...</p>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
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
                                ${data.status === 'completed' ? 'bg-green-100 text-green-700' : ''}
                                ${data.status === 'processing' ? 'bg-blue-100 text-blue-700 animate-pulse' : ''}
                                ${data.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ''}
                                ${data.status === 'failed' ? 'bg-red-100 text-red-700' : ''}
                            `;
                        }

                        // If completed or failed, stop polling
                        if (data.status === 'completed' || data.status === 'failed') {
                            clearInterval(interval);
                            if (data.status === 'completed') {
                                setTimeout(() => location.reload(), 2000);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error checking status:', error);
                }
            }, 3000);
        }

        // After successful upload, start polling
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const status = document.getElementById('uploadStatus');
            const btn = document.getElementById('uploadBtn');

            status.className = 'mt-4 p-3 rounded-lg bg-blue-50 text-blue-700';
            status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading and queuing for processing...';
            status.classList.remove('hidden');
            btn.disabled = true;

            try {
                const response = await fetch('{{ route("documents.upload") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload(); // Reload to show the new document in the list
                } else {
                    throw new Error(data.error || 'Upload failed');
                }
            } catch (error) {
                status.className = 'mt-4 p-3 rounded-lg bg-red-50 text-red-700';
                status.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + error.message;
            } finally {
                btn.disabled = false;
            }
        });
    </script>
@endsection
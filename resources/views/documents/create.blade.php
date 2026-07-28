@extends('layouts.admin')
@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">
                <i class="fas fa-file-circle-plus text-blue-500"></i> Add Policy
            </h2>
            <form id="uploadForm" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Policy Name</label>
                    <input type="text" name="name" id="docName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter policy name">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Policy Content</label>
                    <input type="hidden" name="input_type" id="inputType" value="pdf">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" id="pdfTab" class="input-tab px-4 py-3 border-2 border-blue-500 bg-blue-50 text-blue-700 rounded-lg font-medium">
                            <i class="fas fa-file-pdf mr-2"></i> Upload PDF
                        </button>
                        <button type="button" id="textTab" class="input-tab px-4 py-3 border-2 border-gray-200 text-gray-600 rounded-lg font-medium hover:border-blue-300">
                            <i class="fas fa-keyboard mr-2"></i> Enter Text
                        </button>
                    </div>
                </div>

                <div id="pdfPanel">
                    <label class="block text-sm font-medium text-gray-700 mb-2">PDF Document</label>
                    <input type="file" name="file" id="docFile" accept="application/pdf,.pdf" required class="w-full px-4 py-2 border border-gray-300 rounded-lg file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">PDF only, maximum 10MB.</p>
                </div>

                <div id="textPanel" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Policy Text</label>
                    <textarea name="content" id="policyContent" rows="15" minlength="10" maxlength="50000" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Type or paste the complete policy content here..."></textarea>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>Enter at least 10 characters.</span>
                        <span id="characterCount">0 / 50,000</span>
                    </div>
                </div>

                <button type="submit" id="uploadBtn" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50">
                    <i class="fas fa-save"></i> Save & Process
                </button>
                <a href="{{ route('documents.index') }}" class="ml-3 text-gray-600 hover:text-gray-800">Cancel</a>
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
        const inputType = document.getElementById('inputType');
        const pdfTab = document.getElementById('pdfTab');
        const textTab = document.getElementById('textTab');
        const pdfPanel = document.getElementById('pdfPanel');
        const textPanel = document.getElementById('textPanel');
        const docFile = document.getElementById('docFile');
        const policyContent = document.getElementById('policyContent');

        function selectInputType(type) {
            const isPdf = type === 'pdf';

            inputType.value = type;
            pdfPanel.classList.toggle('hidden', !isPdf);
            textPanel.classList.toggle('hidden', isPdf);
            docFile.required = isPdf;
            policyContent.required = !isPdf;

            pdfTab.className = `input-tab px-4 py-3 border-2 rounded-lg font-medium ${
                isPdf ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:border-blue-300'
            }`;
            textTab.className = `input-tab px-4 py-3 border-2 rounded-lg font-medium ${
                !isPdf ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:border-blue-300'
            }`;
        }

        pdfTab.addEventListener('click', () => selectInputType('pdf'));
        textTab.addEventListener('click', () => selectInputType('text'));
        policyContent.addEventListener('input', () => {
            document.getElementById('characterCount').textContent =
                `${policyContent.value.length.toLocaleString()} / 50,000`;
        });

        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const status = document.getElementById('uploadStatus');
            const btn = document.getElementById('uploadBtn');

            status.className = 'mt-4 p-3 rounded-lg bg-blue-50 text-blue-700';
            status.textContent = 'Saving and queuing the policy for processing...';
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
                    window.location.href = '{{ route("documents.index") }}';
                } else {
                    throw new Error(data.error || 'Upload failed');
                }
            } catch (error) {
                status.className = 'mt-4 p-3 rounded-lg bg-red-50 text-red-700';
                status.textContent = error.message;
            } finally {
                btn.disabled = false;
            }
        });
    </script>
@endsection
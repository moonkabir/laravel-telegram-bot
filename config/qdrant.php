<?php

return [
    'host' => env('QDRANT_HOST'),
    'api_key' => env('QDRANT_API_KEY'),
    'collection' => env('QDRANT_COLLECTION', 'document_chunks'),
    'vector_size' => (int) env('QDRANT_VECTOR_SIZE', 1536),
    'vector_name' => env('QDRANT_VECTOR_NAME', 'content'),
];

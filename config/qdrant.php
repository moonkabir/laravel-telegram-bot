<?php

return [
    'host' => env('QDRANT_HOST'),
    'port' => (int) env('QDRANT_PORT', 6333),
    'api_key' => env('QDRANT_API_KEY'),
    'collection' => env('QDRANT_COLLECTION', 'document_chunks_moon2'),
    'vector_size' => (int) env('QDRANT_VECTOR_SIZE', 1536),
    'vector_name' => env('QDRANT_VECTOR_NAME', 'content'),
];

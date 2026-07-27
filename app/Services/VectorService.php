<?php
namespace App\Services;

use App\Models\DocumentChunk;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
class VectorService
{
    public function generateEmbedding(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-ada-002',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        foreach ($vec1 as $key => $value) {
            $dotProduct += $value * ($vec2[$key] ?? 0);
            $norm1 += $value * $value;
            $norm2 += ($vec2[$key] ?? 0) * ($vec2[$key] ?? 0);
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    public function searchSimilarChunks(string $query, int $limit = 5): array
    {
        Log::info('Searching for similar chunks: ' . $query);
        $queryEmbedding = $this->generateEmbedding($query);
        // Log::info('Query embedding: ' . json_encode($queryEmbedding));
        $hits = app(QdrantService::class)->search($queryEmbedding, $limit);        
        $results = [];
        foreach ($hits as $hit) {
            Log::info('Hit: ' . json_encode($hit['id']));
            $chunk = DocumentChunk::with('document')->find($hit['id']);
            Log::info('Chunk: ' . json_encode($chunk));
            if (!$chunk) {
                continue;
            }
            $results[] = [
                'chunk' => $chunk,
                'similarity' => $hit['score'],
                'content' => $chunk->content,
            ];
        }

        return $results;
    }
}

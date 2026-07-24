<?php
namespace App\Services;

use App\Models\DocumentChunk;
use OpenAI\Laravel\Facades\OpenAI;

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

    // public function searchSimilarChunks(string $query, int $limit = 5): array
    // {
    //     // Generate embedding for query
    //     $queryEmbedding = $this->generateEmbedding($query);

    //     // Get all chunks from database
    //     $chunks = DocumentChunk::with('document')->get();

    //     // Calculate cosine similarity
    //     $results = [];
    //     foreach ($chunks as $chunk) {
    //         $similarity = $this->cosineSimilarity($queryEmbedding, $chunk->embedding);
    //         $results[] = [
    //             'chunk' => $chunk,
    //             'similarity' => $similarity,
    //             'content' => $chunk->content
    //         ];
    //     }

    //     // Sort by similarity and return top results
    //     usort($results, function($a, $b) {
    //         return $b['similarity'] <=> $a['similarity'];
    //     });

    //     return array_slice($results, 0, $limit);
    // }

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
        $queryEmbedding = $this->generateEmbedding($query);
        $hits = app(QdrantService::class)->search($queryEmbedding, $limit);

        $results = [];
        foreach ($hits as $hit) {
            $chunk = DocumentChunk::with('document')->find($hit['id']);
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

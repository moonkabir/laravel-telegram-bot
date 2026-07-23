<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'content',
        'chunk_index',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Search chunks using vector similarity with better error handling
     */
    public static function searchByEmbedding(array $queryEmbedding, $limit = 5)
    {
        try {
            $chunks = self::with('document')->get();
            $results = [];

            foreach ($chunks as $chunk) {
                if ($chunk->embedding && is_array($chunk->embedding)) {
                    try {
                        $similarity = self::cosineSimilarity($queryEmbedding, $chunk->embedding);
                        $results[] = [
                            'chunk' => $chunk,
                            'similarity' => $similarity,
                        ];
                    } catch (\Exception $e) {
                        Log::warning('Similarity calculation failed for chunk ' . $chunk->id);
                        continue;
                    }
                }
            }

            // Sort by similarity
            usort($results, function ($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            return array_slice($results, 0, $limit);

        } catch (\Exception $e) {
            Log::error('Semantic search error: ' . $e->getMessage());
            return [];
        }
    }

    private static function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        $size = min(count($vec1), count($vec2));

        for ($i = 0; $i < $size; $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += pow($vec1[$i], 2);
            $norm2 += pow($vec2[$i], 2);
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }
}

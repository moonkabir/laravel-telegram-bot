<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
class RagService
{
    public function __construct(
        private VectorService $vectorService,
    ) {}

    public function answerFromDocuments(string $question): string
    {
        $results = $this->vectorService->searchSimilarChunks($question, 5);
        Log::info('Results: ' . json_encode($results));
        // Drop weak matches (optional but important for synonyms/noise)
        $results = array_filter($results, fn ($r) => ($r['similarity'] ?? 0) >= 0.75);

        if (empty($results)) {
            return "I couldn't find relevant information in the uploaded documents.";
        }

        $context = collect($results)
            ->map(fn ($r, $i) =>
                '['.($i + 1).'] ('.($r['chunk']->document->name ?? 'doc').")\n".$r['content']
            )
            ->implode("\n\n");

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' =>
                        "Answer ONLY using the provided document context. ".
                        "If the answer is not in the context, say you don't know. ".
                        "Be concise. Treat synonym/paraphrase questions as the same intent.",
                ],
                [
                    'role' => 'user',
                    'content' => "Context:\n{$context}\n\nQuestion: {$question}",
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 800,
        ]);

        return $response->choices[0]->message->content
            ?? "Sorry, I couldn't generate an answer.";
    }
}
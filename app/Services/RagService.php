<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class RagService
{
    private const NO_DOCUMENT_ANSWER = "I could not find this information in the available HR policies. Please contact HR for clarification.";

    public function __construct(
        private VectorService $vectorService,
    ) {}

    /**
     * Greetings / basic chat → OpenAI.
     * Everything else → uploaded documents (RAG).
     */
    public function answer(string $question): string
    {
        if ($this->isGreetingOrBasicChat($question)) {
            return $this->answerWithOpenAI($question);
        }

        return $this->answerFromDocuments($question);
    }

    public function answerFromDocuments(string $question): string
    {
        $results = $this->vectorService->searchSimilarChunks($question, 5);
        Log::info('RAG search results', [
            'question' => $question,
            'count' => count($results),
        ]);

        $results = array_values(array_filter(
            $results,
            fn ($r) => ($r['similarity'] ?? 0) >= 0.75
        ));

        if (empty($results)) {
            return self::NO_DOCUMENT_ANSWER;
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
                        'Answer ONLY using the provided document context. '.
                        'If the answer is not clearly supported by the context, reply with exactly: '.
                        self::NO_DOCUMENT_ANSWER.' '.
                        'Do not invent facts. Be concise. Treat synonym/paraphrase questions as the same intent.',
                ],
                [
                    'role' => 'user',
                    'content' => "Context:\n{$context}\n\nQuestion: {$question}",
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 800,
        ]);

        $answer = trim((string) ($response->choices[0]->message->content ?? ''));

        if ($answer === '' || $this->looksLikeUnknownAnswer($answer)) {
            return self::NO_DOCUMENT_ANSWER;
        }

        return $answer;
    }

    protected function isGreetingOrBasicChat(string $question): bool
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' =>
                        'Classify the user message. Reply with exactly one word: chat or document. '.
                        'chat = greetings, thanks, goodbye, how are you, who are you, small talk, jokes, or other basic conversation that does not need uploaded documents. '.
                        'document = any question that asks for facts, details, prices, terms, people, companies, dates, or content that would come from uploaded documents.',
                ],
                [
                    'role' => 'user',
                    'content' => $question,
                ],
            ],
            'temperature' => 0,
            'max_tokens' => 5,
        ]);

        $label = strtolower(trim((string) ($response->choices[0]->message->content ?? 'document')));

        Log::info('Message intent classified', [
            'question' => $question,
            'label' => $label,
        ]);

        return str_starts_with($label, 'chat');
    }

    protected function answerWithOpenAI(string $question): string
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' =>
                        'You are a friendly Telegram assistant. '.
                        'Reply briefly and naturally to greetings and basic conversation. '.
                        'If the user asks for document-specific information, tell them to ask about their uploaded documents.',
                ],
                [
                    'role' => 'user',
                    'content' => $question,
                ],
            ],
            'temperature' => 0.7,
            'max_tokens' => 200,
        ]);

        return trim((string) ($response->choices[0]->message->content ?? ''))
            ?: "Hello! How can I help you today?";
    }

    protected function looksLikeUnknownAnswer(string $answer): bool
    {
        $lower = strtolower($answer);

        return str_contains($lower, "couldn't find relevant information")
            || str_contains($lower, 'could not find relevant information')
            || str_contains($lower, "i don't know")
            || str_contains($lower, 'i do not know')
            || str_contains($lower, 'not in the context')
            || str_contains($lower, 'not found in the')
            || str_contains($lower, 'no relevant information');
    }
}

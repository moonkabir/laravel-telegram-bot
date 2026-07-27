<?php

namespace App\Services;

use Qdrant\Qdrant;
use Qdrant\Config;
use Qdrant\Http\Builder;
use Qdrant\Models\Request\CreateCollection;
use Qdrant\Models\Request\VectorParams;
use Qdrant\Models\PointsStruct;
use Qdrant\Models\PointStruct;
use Qdrant\Models\VectorStruct;
use Qdrant\Models\Request\SearchRequest;
use Illuminate\Support\Facades\Log;

class QdrantService
{
    protected Qdrant $client;
    protected string $collection;
    protected string $vectorName;
    protected int $vectorSize;

    public function __construct()
    {
        $config = new Config(
            config('qdrant.host'),
            (int) config('qdrant.port', 6333)
        );
        $config->setApiKey(config('qdrant.api_key'));

        $this->client = new Qdrant((new Builder())->build($config));
        $this->collection = config('qdrant.collection');
        $this->vectorName = config('qdrant.vector_name', 'content');
        $this->vectorSize = (int) config('qdrant.vector_size', 1536);
    }

    public function ensureCollection(): void
    {
        if ($this->collectionExists()) {
            return;
        }

        Log::info('Qdrant collection missing, creating', [
            'collection' => $this->collection,
            'vector_name' => $this->vectorName,
            'vector_size' => $this->vectorSize,
        ]);

        $create = new CreateCollection();
        $create->addVector(
            new VectorParams($this->vectorSize, VectorParams::DISTANCE_COSINE),
            $this->vectorName
        );

        try {
            $this->client->collections($this->collection)->create($create);
            Log::info('Qdrant collection created', ['collection' => $this->collection]);
        } catch (\Throwable $e) {
            // Another worker may have created it between exists() and create().
            if ($this->collectionExists()) {
                Log::info('Qdrant collection already exists after create race', [
                    'collection' => $this->collection,
                ]);

                return;
            }

            Log::error('Qdrant collection create failed', [
                'collection' => $this->collection,
                'payload' => $create->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $this->exceptionResponse($e),
            ]);

            throw $e;
        }
    }

    public function upsertChunk(int $chunkId, array $embedding, array $payload = []): void
    {
        $this->ensureCollection();

        $vector = array_values($embedding);
        if (count($vector) !== $this->vectorSize) {
            throw new \InvalidArgumentException(sprintf(
                'Embedding size %d does not match Qdrant vector size %d',
                count($vector),
                $this->vectorSize
            ));
        }

        Log::info('Upserting chunk', ['chunk_id' => $chunkId]);

        $points = new PointsStruct();
        $points->addPoint(
            new PointStruct(
                $chunkId,
                new VectorStruct($vector, $this->vectorName),
                $payload
            )
        );

        $this->client->collections($this->collection)->points()->upsert($points, ['wait' => 'true']);
        Log::info('Qdrant upsert successful', ['chunk_id' => $chunkId]);
    }

    public function search(array $queryEmbedding, int $limit = 5): array
    {
        $request = (new SearchRequest(
            new VectorStruct(array_values($queryEmbedding), $this->vectorName)
        ))
            ->setLimit($limit)
            ->setWithPayload(true);

        $response = $this->client->collections($this->collection)->points()->search($request);

        return $response['result'] ?? [];
    }

    protected function collectionExists(): bool
    {
        try {
            $response = $this->client->collections($this->collection)->exists();

            return (bool) ($response['result']['exists'] ?? false);
        } catch (\Throwable $e) {
            // Older Qdrant versions may not support /exists; fall back to info().
            try {
                $this->client->collections($this->collection)->info();

                return true;
            } catch (\Throwable $infoError) {
                if ((int) $infoError->getCode() === 404) {
                    return false;
                }

                Log::error('Qdrant collection check failed', [
                    'collection' => $this->collection,
                    'exists_error' => $e->getMessage(),
                    'info_error' => $infoError->getMessage(),
                    'info_code' => $infoError->getCode(),
                    'response' => $this->exceptionResponse($infoError),
                ]);

                throw $infoError;
            }
        }
    }

    protected function exceptionResponse(\Throwable $e): mixed
    {
        if (! method_exists($e, 'getResponse')) {
            return null;
        }

        try {
            return $e->getResponse()->__toArray();
        } catch (\Throwable) {
            return null;
        }
    }
}

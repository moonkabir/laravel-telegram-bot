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

    public function __construct()
    {
        $config = new Config(config('qdrant.host'));
        $config->setApiKey(config('qdrant.api_key'));

        $this->client = new Qdrant((new Builder())->build($config));
        $this->collection = config('qdrant.collection');
        $this->vectorName = config('qdrant.vector_name', 'content');
    }

    public function ensureCollection(): void
    {
        try {
            $this->client->collections($this->collection)->info();
        } catch (\Throwable $e) {
            $create = new CreateCollection();
            $create->addVector(
                new VectorParams(config('qdrant.vector_size'), VectorParams::DISTANCE_COSINE),
                $this->vectorName
            );
            $this->client->collections($this->collection)->create($create);
            Log::info('Qdrant collection created: ' . $this->collection);
        }
    }

    public function upsertChunk(int $chunkId, array $embedding, array $payload = []): void
    {
        $this->ensureCollection();
        Log::info('Upserting chunk: ' . $chunkId);
        $points = new PointsStruct();
        $points->addPoint(
            new PointStruct(
                $chunkId,
                new VectorStruct(array_values($embedding), $this->vectorName),
                $payload
            )
        );
        Log::info('Points: ' . json_encode($points));
        $this->client->collections($this->collection)->points()->upsert($points, ['wait' => 'true']);
        Log::info('Qdrant upsert successful for chunk ' . $chunkId);
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
}

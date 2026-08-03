<?php

namespace Tests\Unit;

use App\Services\Search\EmbeddingClient;
use Tests\TestCase;

class EmbeddingClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.embeddings.provider' => 'stub',
            'services.embeddings.dims' => 64,
        ]);
    }

    public function test_stub_returns_fixed_dimensional_deterministic_vectors(): void
    {
        $client = new EmbeddingClient;

        $a = $client->embed('baby feeding bottle');
        $b = $client->embed('baby feeding bottle');

        $this->assertCount(64, $a);
        $this->assertSame($a, $b, 'Stub embeddings must be deterministic.');
    }

    public function test_shared_tokens_are_closer_than_unrelated_text(): void
    {
        $client = new EmbeddingClient;
        $cos = fn (string $x, string $y) => array_sum(
            array_map(fn ($p, $q) => $p * $q, $client->embed($x), $client->embed($y))
        );

        $this->assertGreaterThan(
            $cos('baby feeding bottle', 'car engine oil'),
            $cos('baby feeding bottle', 'baby milk bottle'),
        );
    }
}

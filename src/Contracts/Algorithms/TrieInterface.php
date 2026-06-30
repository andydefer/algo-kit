<?php

namespace AndyDefer\AlgoKIT\Contracts\Algorithms;

use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Collections\TrieResultCollection;

interface TrieInterface
{
    public function insert(string $word, ?string $context = null): void;

    public function search(string $prefix, ?string $context = null, int $limit = 10): TrieResultCollection;

    public function insertBatch(TrieCollection $collection): void;

    public function searchBatch(TrieCollection $collection, int $limit = 10): array;

    public function clear(): void;
}

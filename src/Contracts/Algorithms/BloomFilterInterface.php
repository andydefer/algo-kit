<?php

namespace AndyDefer\AlgoKIT\Contracts\Algorithms;

use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Collections\BloomFilterResultCollection;

interface BloomFilterInterface
{
    public function insert(string $value, ?string $context = null): void;

    public function exists(string $value, ?string $context = null): bool;

    public function insertBatch(BloomFilterCollection $collection): void;

    public function existsBatch(BloomFilterCollection $collection): BloomFilterResultCollection;

    public function clear(?string $context = null): void;
}

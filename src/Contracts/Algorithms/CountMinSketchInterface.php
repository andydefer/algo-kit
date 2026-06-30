<?php

namespace AndyDefer\AlgoKIT\Contracts\Algorithms;

use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Collections\CountMinSketchResultCollection;

interface CountMinSketchInterface
{
    public function add(string $value, ?string $context = null): void;

    public function count(string $value, ?string $context = null): int;

    public function addBatch(CountMinSketchCollection $collection): void;

    public function countBatch(CountMinSketchCollection $collection): CountMinSketchResultCollection;

    public function clear(?string $context = null): void;
}

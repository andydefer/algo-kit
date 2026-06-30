<?php

namespace AndyDefer\AlgoKIT\Contracts\Algorithms;

use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Collections\HyperLogLogResultCollection;

interface HyperLogLogInterface
{
    public function add(string $value, ?string $context = null): void;

    public function count(?string $context = null): int;

    public function addBatch(HyperLogLogCollection $collection): void;

    public function countBatch(HyperLogLogCollection $collection): HyperLogLogResultCollection;

    public function clear(?string $context = null): void;
}

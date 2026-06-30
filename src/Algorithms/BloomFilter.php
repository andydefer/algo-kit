<?php

namespace AndyDefer\AlgoKIT\Algorithms;

use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Collections\BloomFilterResultCollection;
use AndyDefer\AlgoKIT\Contracts\Algorithms\BloomFilterInterface;
use AndyDefer\AlgoKIT\Records\BloomFilterResultRecord;
use AndyDefer\AlgoKIT\Storage\StorageInterface;

class BloomFilter implements BloomFilterInterface
{
    private int $size;

    private int $hashCount;

    public function __construct(
        private StorageInterface $storage,
        int $size = 10000,
        int $hashCount = 3,
        private string $key = 'bloom'
    ) {
        $this->size = $size;
        $this->hashCount = $hashCount;

        if (! $this->storage->exists($this->key)) {
            $this->storage->set($this->key, []);
        }
    }

    private function getBits(?string $context = null): array
    {
        $contextKey = $context !== null ? $this->key.'_'.$context : $this->key;

        if (! $this->storage->exists($contextKey)) {
            $bits = array_fill(0, $this->size, 0);
            $this->storage->set($contextKey, $bits);

            return $bits;
        }

        $bits = $this->storage->get($contextKey);

        if ($bits === null || ! is_array($bits) || count($bits) !== $this->size) {
            $bits = array_fill(0, $this->size, 0);
            $this->storage->set($contextKey, $bits);
        }

        return $bits;
    }

    private function saveBits(array $bits, ?string $context = null): void
    {
        $contextKey = $context !== null ? $this->key.'_'.$context : $this->key;
        $this->storage->set($contextKey, $bits);
    }

    public function insert(string $value, ?string $context = null): void
    {
        $bits = $this->getBits($context);

        for ($i = 0; $i < $this->hashCount; $i++) {
            $index = $this->hash($value, $i);
            $bits[$index] = 1;
        }

        $this->saveBits($bits, $context);
    }

    public function exists(string $value, ?string $context = null): bool
    {
        $bits = $this->getBits($context);

        for ($i = 0; $i < $this->hashCount; $i++) {
            $index = $this->hash($value, $i);
            if ($bits[$index] === 0) {
                return false;
            }
        }

        return true;
    }

    public function insertBatch(BloomFilterCollection $collection): void
    {
        $bitsByContext = [];

        foreach ($collection as $record) {
            $contextKey = $record->context ?? 'global';

            if (! isset($bitsByContext[$contextKey])) {
                $bitsByContext[$contextKey] = $this->getBits($record->context);
            }
            // 🔥 Sans référence, on copie
            $bits = $bitsByContext[$contextKey];

            for ($i = 0; $i < $this->hashCount; $i++) {
                $index = $this->hash($record->value, $i);
                $bits[$index] = 1;
            }

            // 🔥 Sauvegarder immédiatement
            $bitsByContext[$contextKey] = $bits;
            $this->saveBits($bits, $record->context);
        }

        // Sauvegarder les contextes restants
        foreach ($bitsByContext as $contextKey => $bits) {
            $context = $contextKey !== 'global' ? $contextKey : null;
            $this->saveBits($bits, $context);
        }
    }

    public function existsBatch(BloomFilterCollection $collection): BloomFilterResultCollection
    {
        $results = new BloomFilterResultCollection;
        $cache = [];

        foreach ($collection as $record) {
            $contextKey = $record->context ?? 'global';

            if (! isset($cache[$contextKey])) {
                $cache[$contextKey] = $this->getBits($record->context);
            }
            $bits = $cache[$contextKey];

            $exists = true;
            for ($i = 0; $i < $this->hashCount; $i++) {
                $index = $this->hash($record->value, $i);
                if ($bits[$index] === 0) {
                    $exists = false;
                    break;
                }
            }

            $results->add(new BloomFilterResultRecord($record->value, $exists, $record->context));
        }

        return $results;
    }

    private function hash(string $value, int $seed): int
    {
        return abs(crc32($seed.$value)) % $this->size;
    }

    public function clear(?string $context = null): void
    {
        if ($context !== null) {
            $this->storage->delete($this->key.'_'.$context);
        } else {
            $this->storage->delete($this->key);
        }
    }
}

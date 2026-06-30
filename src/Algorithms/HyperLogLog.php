<?php

namespace AndyDefer\AlgoKIT\Algorithms;

use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Collections\HyperLogLogResultCollection;
use AndyDefer\AlgoKIT\Contracts\Algorithms\HyperLogLogInterface;
use AndyDefer\AlgoKIT\Records\HyperLogLogResultRecord;
use AndyDefer\AlgoKIT\Storage\StorageInterface;

class HyperLogLog implements HyperLogLogInterface
{
    private int $m;

    private int $p;

    public function __construct(
        private StorageInterface $storage,
        int $precision = 16,
        private string $key = 'hll'
    ) {
        $this->p = $precision;
        $this->m = 1 << $precision;

        if (! $this->storage->exists($this->key)) {
            $this->storage->set($this->key, []);
        }
    }

    private function getRegisters(?string $context = null): array
    {
        $contextKey = $context !== null ? $this->key.'_'.$context : $this->key;

        if (! $this->storage->exists($contextKey)) {
            $registers = array_fill(0, $this->m, 0);
            $this->storage->set($contextKey, $registers);

            if ($context !== null) {
                $this->addContextToList($context);
            }

            return $registers;
        }

        $registers = $this->storage->get($contextKey);

        if ($registers === null || ! is_array($registers) || count($registers) !== $this->m) {
            $registers = array_fill(0, $this->m, 0);
            $this->storage->set($contextKey, $registers);
        }

        return $registers;
    }

    private function saveRegisters(array $registers, ?string $context = null): void
    {
        $contextKey = $context !== null ? $this->key.'_'.$context : $this->key;
        $this->storage->set($contextKey, $registers);
    }

    private function getContextList(): array
    {
        return $this->storage->get($this->key.'_contexts', []);
    }

    private function addContextToList(string $context): void
    {
        $contexts = $this->storage->get($this->key.'_contexts', []);
        if (! in_array($context, $contexts)) {
            $contexts[] = $context;
            $this->storage->set($this->key.'_contexts', $contexts);
        }
    }

    private function calculateCount(array $registers): int
    {
        if (empty($registers) || ! is_array($registers) || count($registers) === 0) {
            return 0;
        }

        $sum = 0;
        foreach ($registers as $register) {
            $sum += pow(2, -$register);
        }

        if ($sum == 0) {
            return 0;
        }

        $alpha = 0.7213 / (1 + 1.079 / $this->m);
        $estimate = $alpha * $this->m * $this->m / $sum;

        if ($estimate <= 2.5 * $this->m) {
            $zeros = 0;
            foreach ($registers as $register) {
                if ($register === 0) {
                    $zeros++;
                }
            }
            if ($zeros > 0) {
                $estimate = $this->m * log($this->m / $zeros);
            }
        }

        return (int) $estimate;
    }

    public function add(string $value, ?string $context = null): void
    {
        $registers = $this->getRegisters($context);

        $hash = $this->hash($value);
        $index = $hash & ($this->m - 1);
        $w = $hash >> $this->p;

        $rank = $this->leadingZeros($w) + 1;

        if ($rank > $registers[$index]) {
            $registers[$index] = $rank;
            $this->saveRegisters($registers, $context);
        }
    }

    public function count(?string $context = null): int
    {
        // Contexte spécifique
        if ($context !== null) {
            $registers = $this->getRegisters($context);

            return $this->calculateCount($registers);
        }

        // Contexte global = somme de tout
        $total = 0;

        // 1. Données globales
        $globalRegisters = $this->getRegisters(null);
        $total += $this->calculateCount($globalRegisters);

        // 2. Tous les contextes
        foreach ($this->getContextList() as $contextName) {
            $total += $this->count($contextName);
        }

        return $total;
    }

    public function addBatch(HyperLogLogCollection $collection): void
    {
        $registersByContext = [];
        $dirty = [];

        foreach ($collection as $record) {
            $contextKey = $record->context ?? 'global';

            if (! isset($registersByContext[$contextKey])) {
                $registersByContext[$contextKey] = $this->getRegisters($record->context);
                $dirty[$contextKey] = false;
            }
            $registers = &$registersByContext[$contextKey];

            $hash = $this->hash($record->value);
            $index = $hash & ($this->m - 1);
            $w = $hash >> $this->p;

            $rank = $this->leadingZeros($w) + 1;

            if ($rank > $registers[$index]) {
                $registers[$index] = $rank;
                $dirty[$contextKey] = true;
            }
        }

        foreach ($registersByContext as $contextKey => $registers) {
            if ($dirty[$contextKey]) {
                $context = $contextKey !== 'global' ? $contextKey : null;
                $this->saveRegisters($registers, $context);
            }
        }
    }

    public function countBatch(HyperLogLogCollection $collection): HyperLogLogResultCollection
    {
        $results = new HyperLogLogResultCollection;

        foreach ($collection as $record) {
            $count = $this->count($record->context);
            $results->add(new HyperLogLogResultRecord($count, $record->context));
        }

        return $results;
    }

    private function hash(string $value): int
    {
        return abs(crc32($value));
    }

    private function leadingZeros(int $value): int
    {
        if ($value === 0) {
            return 32;
        }

        $zeros = 0;
        while (($value & 1) === 0) {
            $zeros++;
            $value >>= 1;
        }

        return $zeros;
    }

    public function clear(?string $context = null): void
    {
        if ($context !== null) {
            $contextKey = $this->key.'_'.$context;
            $this->storage->delete($contextKey);

            // Supprimer de la liste
            $contexts = $this->storage->get($this->key.'_contexts', []);
            $contexts = array_filter($contexts, fn ($c) => $c !== $context);
            $this->storage->set($this->key.'_contexts', array_values($contexts));
        } else {
            $this->storage->delete($this->key);

            // Supprimer tous les contextes
            $contexts = $this->storage->get($this->key.'_contexts', []);
            foreach ($contexts as $contextName) {
                $this->storage->delete($this->key.'_'.$contextName);
            }
            $this->storage->delete($this->key.'_contexts');
        }
    }
}

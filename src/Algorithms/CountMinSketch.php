<?php

namespace AndyDefer\AlgoKIT\Algorithms;

use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Collections\CountMinSketchResultCollection;
use AndyDefer\AlgoKIT\Contracts\Algorithms\CountMinSketchInterface;
use AndyDefer\AlgoKIT\Records\CountMinSketchResultRecord;
use AndyDefer\AlgoKIT\Storage\StorageInterface;

class CountMinSketch implements CountMinSketchInterface
{
    private int $width;

    private int $depth;

    public function __construct(
        private StorageInterface $storage,
        int $width = 10000,
        int $depth = 5,
        private string $key = 'cms'
    ) {
        $this->width = $width;
        $this->depth = $depth;

        if (! $this->storage->exists($this->key)) {
            $this->storage->set($this->key, []);
        }
    }

    private function getTable(?string $context = null): array
    {
        $contextKey = $context !== null ? $this->key.'_'.$context : $this->key;

        if (! $this->storage->exists($contextKey)) {
            $table = array_fill(0, $this->depth, array_fill(0, $this->width, 0));
            $this->storage->set($contextKey, $table);

            return $table;
        }

        $table = $this->storage->get($contextKey);

        if ($table === null) {
            $table = array_fill(0, $this->depth, array_fill(0, $this->width, 0));
            $this->storage->set($contextKey, $table);
        }

        // S'assurer que toutes les colonnes existent
        $width = $this->width;
        $depth = $this->depth;
        foreach ($table as $i => $row) {
            if (count($row) < $width) {
                $table[$i] = array_pad($row, $width, 0);
            }
        }

        while (count($table) < $depth) {
            $table[] = array_fill(0, $width, 0);
        }

        return $table;
    }

    private function saveTable(array $table, ?string $context = null): void
    {
        $contextKey = $context !== null ? $this->key.'_'.$context : $this->key;
        $this->storage->set($contextKey, $table);
    }

    public function add(string $value, ?string $context = null): void
    {
        $table = $this->getTable($context);

        for ($i = 0; $i < $this->depth; $i++) {
            $index = $this->hash($value, $i);
            if (! isset($table[$i][$index])) {
                $table[$i][$index] = 0;
            }
            $table[$i][$index]++;
        }

        $this->saveTable($table, $context);
    }

    public function count(string $value, ?string $context = null): int
    {
        $table = $this->getTable($context);
        $min = PHP_INT_MAX;

        for ($i = 0; $i < $this->depth; $i++) {
            $index = $this->hash($value, $i);
            $val = isset($table[$i][$index]) ? (int) $table[$i][$index] : 0;
            $min = min($min, $val);
        }

        return $min;
    }

    public function addBatch(CountMinSketchCollection $collection): void
    {
        $tables = [];

        foreach ($collection as $record) {
            $contextKey = $record->context ?? 'global';

            if (! isset($tables[$contextKey])) {
                $tables[$contextKey] = $this->getTable($record->context);
            }

            // Copier la table
            $table = $tables[$contextKey];

            for ($i = 0; $i < $this->depth; $i++) {
                $index = $this->hash($record->value, $i);
                if (! isset($table[$i][$index])) {
                    $table[$i][$index] = 0;
                }
                $table[$i][$index]++;
            }

            // Mettre à jour la table dans le tableau
            $tables[$contextKey] = $table;
        }

        foreach ($tables as $contextKey => $table) {
            $context = $contextKey !== 'global' ? $contextKey : null;
            $this->saveTable($table, $context);
        }
    }

    public function countBatch(CountMinSketchCollection $collection): CountMinSketchResultCollection
    {
        $results = new CountMinSketchResultCollection;
        $cache = [];

        foreach ($collection as $record) {
            $context = $record->context;
            $contextKey = $context ?? 'global';

            if (! isset($cache[$contextKey])) {
                $cache[$contextKey] = $this->getTable($context);
            }

            $table = $cache[$contextKey];

            $min = PHP_INT_MAX;
            for ($i = 0; $i < $this->depth; $i++) {
                $index = $this->hash($record->value, $i);
                $val = isset($table[$i][$index]) ? (int) $table[$i][$index] : 0;
                $min = min($min, $val);
            }

            $results->add(new CountMinSketchResultRecord(
                $record->value,
                $min,
                $record->context
            ));
        }

        return $results;
    }

    private function hash(string $value, int $seed): int
    {
        return abs(crc32($seed.$value)) % $this->width;
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

<?php

namespace AndyDefer\AlgoKIT\Algorithms;

use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Collections\TrieResultCollection;
use AndyDefer\AlgoKIT\Contracts\Algorithms\TrieInterface;
use AndyDefer\AlgoKIT\Records\TrieResultRecord;
use AndyDefer\AlgoKIT\Storage\StorageInterface;

class Trie implements TrieInterface
{
    public function __construct(
        private StorageInterface $storage,
        private string $key = 'trie'
    ) {
        if (! $this->storage->exists($this->key)) {
            $this->storage->set($this->key, ['root' => ['children' => [], 'words' => []]]);
        }
    }

    private function &getRoot(): array
    {
        $data = $this->storage->get($this->key, ['root' => ['children' => [], 'words' => []]]);

        return $data;
    }

    private function saveRoot(array $root): void
    {
        $this->storage->set($this->key, $root);
    }

    private function &getContextNode(array &$root, ?string $context): array
    {
        if ($context !== null) {
            if (! isset($root[$context])) {
                $root[$context] = ['children' => [], 'words' => []];
            }

            return $root[$context];
        }

        return $root['root'];
    }

    public function insert(string $word, ?string $context = null): void
    {
        $root = $this->getRoot();
        $node = &$this->getContextNode($root, $context);
        $chars = str_split($word);

        foreach ($chars as $char) {
            if (! isset($node['children'][$char])) {
                $node['children'][$char] = ['children' => [], 'words' => []];
            }
            $node = &$node['children'][$char];
        }

        if (! in_array($word, $node['words'])) {
            $node['words'][] = $word;
        }

        $this->saveRoot($root);
    }

    public function search(string $prefix, ?string $context = null, int $limit = 10): TrieResultCollection
    {
        $root = $this->getRoot();
        $node = $this->findNode($root, $prefix, $context);
        $results = new TrieResultCollection;

        if ($node === null) {
            return $results;
        }

        $words = $this->collectWords($node, $prefix, $limit);

        foreach ($words as $word) {
            $results->add(new TrieResultRecord($word, $context));
        }

        return $results;
    }

    public function insertBatch(TrieCollection $collection): void
    {
        $root = $this->getRoot();

        foreach ($collection as $record) {
            $node = &$this->getContextNode($root, $record->context);
            $chars = str_split($record->value);

            foreach ($chars as $char) {
                if (! isset($node['children'][$char])) {
                    $node['children'][$char] = ['children' => [], 'words' => []];
                }
                $node = &$node['children'][$char];
            }

            if (! in_array($record->value, $node['words'])) {
                $node['words'][] = $record->value;
            }
        }

        $this->saveRoot($root);
    }

    public function searchBatch(TrieCollection $collection, int $limit = 10): array
    {
        $results = [];
        $root = $this->getRoot();

        foreach ($collection as $record) {
            $node = $this->findNode($root, $record->value, $record->context);
            $resultCollection = new TrieResultCollection;

            if ($node !== null) {
                $words = $this->collectWords($node, $record->value, $limit);
                foreach ($words as $word) {
                    $resultCollection->add(new TrieResultRecord($word, $record->context));
                }
            }

            $key = $record->context !== null ? $record->context.':'.$record->value : $record->value;
            $results[$key] = $resultCollection;
        }

        return $results;
    }

    private function findNode(array $root, string $prefix, ?string $context = null): ?array
    {
        $node = &$this->getContextNode($root, $context);
        $chars = str_split($prefix);

        foreach ($chars as $char) {
            if (! isset($node['children'][$char])) {
                return null;
            }
            $node = &$node['children'][$char];
        }

        return $node;
    }

    private function collectWords(array $node, string $prefix, int $limit): array
    {
        $results = [];

        foreach ($node['words'] as $word) {
            $results[] = $word;
            if (count($results) >= $limit) {
                return $results;
            }
        }

        foreach ($node['children'] as $char => $childNode) {
            if (count($results) >= $limit) {
                break;
            }
            $results = array_merge($results, $this->collectWords($childNode, $prefix.$char, $limit));
        }

        return $results;
    }

    public function clear(): void
    {
        $this->storage->delete($this->key);
    }
}

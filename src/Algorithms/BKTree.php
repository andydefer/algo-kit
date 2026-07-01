<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Algorithms;

use AndyDefer\AlgoKIT\Collections\BKTreeNodeCollection;
use AndyDefer\AlgoKIT\Collections\BKTreeResultCollection;
use AndyDefer\AlgoKIT\Contracts\Algorithms\TreeInterface;
use AndyDefer\AlgoKIT\Records\BKTreeNodeRecord;
use AndyDefer\AlgoKIT\Records\BKTreeResultRecord;
use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;

/**
 * BK-Tree for fuzzy string matching using Levenshtein distance.
 *
 * Enables efficient spell correction and approximate string search.
 * The tree structure allows searching for similar words with a given
 * tolerance (maximum Levenshtein distance).
 *
 * @example
 * $bkTree = new BKTree($storage);
 * $bkTree->insert('laravel');
 * $bkTree->insert('laragon');
 * $results = $bkTree->search('larvel'); // Returns closest matches
 *
 * @see https://en.wikipedia.org/wiki/BK-tree
 */
final class BKTree implements TreeInterface
{
    private const DEFAULT_TOLERANCE = 2;

    private const DEFAULT_LIMIT = 10;

    /**
     * @param  StorageInterface  $storage  The storage backend for persistence
     * @param  string  $key  Unique key for this tree instance in storage
     */
    public function __construct(
        private StorageInterface $storage,
        private string $key = 'bktree'
    ) {
        if (! $this->storage->exists($this->key)) {
            $this->storage->set($this->key, null);
        }
    }

    /**
     * Inserts a word into the BK-Tree.
     *
     * @param  string  $word  The word to insert
     */
    public function insert(string $word): void
    {
        $root = $this->getRoot();

        if ($root === null) {
            $this->saveRoot($this->createNode($word));

            return;
        }

        $this->insertNode($root, $word);
        $this->saveRoot($root);
    }

    /**
     * Searches for words similar to the given word.
     *
     * @param  string  $word  The word to search for
     * @param  int  $tolerance  Maximum Levenshtein distance allowed (default: 2)
     * @param  int  $limit  Maximum number of results to return (default: 10)
     * @return BKTreeResultCollection Collection of matching words with distances
     */
    public function search(string $word, int $tolerance = self::DEFAULT_TOLERANCE, int $limit = self::DEFAULT_LIMIT): BKTreeResultCollection
    {
        $root = $this->getRoot();
        $results = new BKTreeResultCollection;

        if ($root === null) {
            return $results;
        }

        $this->searchNode($root, $word, $tolerance, $results);

        return $this->sortAndLimitResults($results, $limit);
    }

    /**
     * Clears all data from the BK-Tree.
     */
    public function clear(): void
    {
        $this->storage->delete($this->key);
    }

    /**
     * Retrieves the root node from storage.
     *
     * @return BKTreeNodeRecord|null The root node or null if tree is empty
     */
    private function getRoot(): ?BKTreeNodeRecord
    {
        $source = $this->storage->get($this->key);

        // Si c'est déjà un objet BKTreeNodeRecord (MemoryStorage)
        if ($source instanceof BKTreeNodeRecord) {
            return $source;
        }

        // Si c'est un tableau, le convertir en objet
        if (is_array($source)) {
            return BKTreeNodeRecord::from($source);
        }

        return null;
    }

    /**
     * Saves the root node to storage.
     *
     * @param  BKTreeNodeRecord|null  $root  The root node to save
     */
    private function saveRoot(?BKTreeNodeRecord $root): void
    {
        // Convertir l'objet en tableau pour le stockage (SQLite, Jsonl, etc.)
        $data = $root ? $root->toArray() : null;
        $this->storage->set($this->key, $data);
    }

    /**
     * Creates a new tree node for a word.
     *
     * @param  string  $word  The word for the node
     * @return BKTreeNodeRecord The created node
     */
    private function createNode(string $word): BKTreeNodeRecord
    {
        return new BKTreeNodeRecord($word, new BKTreeNodeCollection);
    }

    /**
     * Recursively inserts a word into the tree.
     *
     * @param  BKTreeNodeRecord  $node  Current node
     * @param  string  $word  Word to insert
     */
    private function insertNode(BKTreeNodeRecord $node, string $word): void
    {
        $distance = $this->calculateDistance($node->word, $word);

        if ($distance === 0) {
            return;
        }

        $existingChild = $this->findChildByWord($node, $word);
        if ($existingChild !== null) {
            return;
        }

        $childAtDistance = $this->findChildAtDistance($node, $distance);
        if ($childAtDistance !== null) {
            $this->insertNode($childAtDistance, $word);
        } else {
            $node->children->add($this->createNode($word));
        }
    }

    /**
     * Finds a child node by its word.
     *
     * @param  BKTreeNodeRecord  $node  Parent node
     * @param  string  $word  Word to search for
     * @return BKTreeNodeRecord|null Found child or null
     */
    private function findChildByWord(BKTreeNodeRecord $node, string $word): ?BKTreeNodeRecord
    {
        return $node->children->find(fn (BKTreeNodeRecord $child) => $child->word === $word);
    }

    /**
     * Finds a child node at a specific Levenshtein distance.
     *
     * @param  BKTreeNodeRecord  $node  Parent node
     * @param  int  $distance  The distance to match
     * @return BKTreeNodeRecord|null Found child or null
     */
    private function findChildAtDistance(BKTreeNodeRecord $node, int $distance): ?BKTreeNodeRecord
    {
        foreach ($node->children as $child) {
            if ($this->calculateDistance($node->word, $child->word) === $distance) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Recursively searches the tree for similar words.
     *
     * @param  BKTreeNodeRecord  $node  Current node
     * @param  string  $word  Word to search for
     * @param  int  $tolerance  Maximum Levenshtein distance
     * @param  BKTreeResultCollection  $results  Collection to add matches to
     */
    private function searchNode(
        BKTreeNodeRecord $node,
        string $word,
        int $tolerance,
        BKTreeResultCollection $results
    ): void {
        $distance = $this->calculateDistance($node->word, $word);

        if ($distance <= $tolerance) {
            $results->add(new BKTreeResultRecord($node->word, $distance));
        }

        $minDistance = $distance - $tolerance;
        $maxDistance = $distance + $tolerance;

        foreach ($node->children as $child) {
            $childDistance = $this->calculateDistance($node->word, $child->word);
            if ($childDistance >= $minDistance && $childDistance <= $maxDistance) {
                $this->searchNode($child, $word, $tolerance, $results);
            }
        }
    }

    /**
     * Sorts results by distance and applies limit.
     *
     * @param  BKTreeResultCollection  $results  Unsorted results
     * @param  int  $limit  Maximum number of results
     * @return BKTreeResultCollection Sorted and limited results
     */
    private function sortAndLimitResults(BKTreeResultCollection $results, int $limit): BKTreeResultCollection
    {
        $items = $results->toArray();

        usort($items, fn (BKTreeResultRecord $a, BKTreeResultRecord $b) => $a->distance <=> $b->distance
        );

        $items = array_slice($items, 0, $limit);

        return BKTreeResultCollection::collect($items);
    }

    /**
     * Calculates the Levenshtein distance between two strings.
     *
     * @param  string  $a  First string
     * @param  string  $b  Second string
     * @return int The Levenshtein distance
     */
    private function calculateDistance(string $a, string $b): int
    {
        return \levenshtein($a, $b);
    }
}

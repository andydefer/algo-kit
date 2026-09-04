<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Algorithms;

use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Collections\TopKResultCollection;
use AndyDefer\AlgoKIT\Contracts\Algorithms\TopKInterface;
use AndyDefer\AlgoKIT\Records\TopKResultRecord;
use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;

/**
 * Maintains the K most frequent elements in a data stream.
 *
 * Uses a space-saving algorithm that keeps track of the most frequent
 * elements with O(1) memory relative to K. When a new element appears
 * and K is exceeded, the least frequent element is replaced.
 *
 * @example
 * $topK = new TopK($storage, 5);
 * $topK->add('php');
 * $topK->add('php');
 * $topK->add('laravel');
 * $top = $topK->getTop(); // Returns top 5 most frequent elements
 *
 * @see https://en.wikipedia.org/wiki/Streaming_algorithm#Top-K
 */
final class TopK implements TopKInterface
{
    /** @var int Default number of elements to track */
    private const DEFAULT_K = 10;

    /** @var int Default increment amount */
    private const DEFAULT_INCREMENT = 1;

    /** @var int Number of elements to track */
    private int $k;

    /**
     * @param  StorageInterface  $storage  The storage backend for persistence
     * @param  int  $k  Number of most frequent elements to track
     * @param  string  $key  Unique key for this Top-K instance in storage
     */
    public function __construct(
        private StorageInterface $storage,
        int $k = self::DEFAULT_K,
        private string $key = 'topk'
    ) {
        $this->k = $k;

        if (! $this->storage->exists($this->key)) {
            $this->initializeStorage();
        }
    }

    /**
     * Adds a value with optional increment.
     *
     * @param  string  $value  The value to add
     * @param  int  $increment  Amount to increment by (default: 1)
     */
    public function add(string $value, int $increment = self::DEFAULT_INCREMENT): void
    {
        $data = $this->getData();
        $items = &$data['items'];
        $counts = &$data['counts'];

        $this->incrementCount($counts, $value, $increment);

        if (! $this->isValueTracked($items, $value)) {
            $this->addOrReplaceItem($items, $counts, $value);
        }

        $this->sortItemsByCount($items, $counts);
        $this->saveData($data);
    }

    /**
     * Returns the K most frequent elements.
     *
     * @return TopKResultCollection Collection of top elements with their counts
     */
    public function getTop(): TopKResultCollection
    {
        $data = $this->getData();
        $results = new TopKResultCollection;

        foreach ($data['items'] as $item) {
            $results->add(new TopKResultRecord($item, $data['counts'][$item]));
        }

        return $results;
    }

    /**
     * Adds multiple values in batch for better performance.
     *
     * @param  TopKCollection  $collection  Collection of values to add
     */
    public function addBatch(TopKCollection $collection): void
    {
        foreach ($collection as $record) {
            $this->add($record->value, $record->increment);
        }
    }

    /**
     * Clears all data from the Top-K tracker.
     */
    public function clear(): void
    {
        $this->storage->delete($this->key);
    }

    /**
     * Returns the storage backend used by this Top-K instance.
     *
     * @return StorageInterface The storage backend
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Returns the storage key used by this Top-K instance.
     *
     * @return string The storage key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Initializes the storage with empty data structure.
     */
    private function initializeStorage(): void
    {
        $this->storage->set($this->key, ['items' => [], 'counts' => []]);
    }

    /**
     * Retrieves the data from storage.
     *
     * @return array{items: array<int, string>, counts: array<string, int>}
     */
    private function &getData(): array
    {
        $data = $this->storage->get($this->key, ['items' => [], 'counts' => []]);

        return $data;
    }

    /**
     * Saves the data to storage.
     *
     * @param  array{items: array<int, string>, counts: array<string, int>}  $data
     */
    private function saveData(array $data): void
    {
        $this->storage->set($this->key, $data);
    }

    /**
     * Increments the count for a value.
     *
     * @param  array<string, int>  $counts  Counts array
     * @param  string  $value  The value to increment
     * @param  int  $increment  Increment amount
     */
    private function incrementCount(array &$counts, string $value, int $increment): void
    {
        if (! isset($counts[$value])) {
            $counts[$value] = 0;
        }

        $counts[$value] += $increment;
    }

    /**
     * Checks if a value is already tracked.
     *
     * @param  array<int, string>  $items  Tracked items
     * @param  string  $value  Value to check
     * @return bool True if tracked, false otherwise
     */
    private function isValueTracked(array $items, string $value): bool
    {
        return in_array($value, $items, true);
    }

    /**
     * Adds a new value or replaces the least frequent one.
     *
     * @param  array<int, string>  $items  Tracked items
     * @param  array<string, int>  $counts  Counts array
     * @param  string  $value  Value to add
     */
    private function addOrReplaceItem(array &$items, array &$counts, string $value): void
    {
        if ($this->hasRoomForMoreItems($items)) {
            $items[] = $value;

            return;
        }

        $this->replaceLeastFrequentItem($items, $counts, $value);
    }

    /**
     * Checks if there is room for more items in the list.
     *
     * @param  array<int, string>  $items  Tracked items
     * @return bool True if room available, false otherwise
     */
    private function hasRoomForMoreItems(array $items): bool
    {
        return count($items) < $this->k;
    }

    /**
     * Replaces the least frequent item with a new value.
     *
     * @param  array<int, string>  $items  Tracked items
     * @param  array<string, int>  $counts  Counts array
     * @param  string  $value  New value to add
     */
    private function replaceLeastFrequentItem(array &$items, array &$counts, string $value): void
    {
        $leastFrequentItem = $this->findLeastFrequentItem($items, $counts);

        if ($counts[$value] > $counts[$leastFrequentItem]) {
            $index = array_search($leastFrequentItem, $items, true);
            if ($index !== false) {
                $items[$index] = $value;
            }
        }
    }

    /**
     * Finds the least frequent item in the list.
     *
     * @param  array<int, string>  $items  Tracked items
     * @param  array<string, int>  $counts  Counts array
     * @return string The least frequent item
     */
    private function findLeastFrequentItem(array $items, array $counts): string
    {
        $leastItem = $items[0];
        $leastCount = $counts[$leastItem];

        foreach ($items as $item) {
            if ($counts[$item] < $leastCount) {
                $leastCount = $counts[$item];
                $leastItem = $item;
            }
        }

        return $leastItem;
    }

    /**
     * Sorts items by count in descending order.
     *
     * @param  array<int, string>  $items  Tracked items
     * @param  array<string, int>  $counts  Counts array
     */
    private function sortItemsByCount(array &$items, array $counts): void
    {
        usort($items, function ($a, $b) use ($counts) {
            return $counts[$b] <=> $counts[$a];
        });
    }
}

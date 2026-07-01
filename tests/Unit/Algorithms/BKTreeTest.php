<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\AlgoKIT\Collections\BKTreeResultCollection;
use AndyDefer\AlgoKIT\Records\BKTreeResultRecord;
use AndyDefer\AlgoKIT\Tests\SqliteStorageTestCase;
use AndyDefer\StorageKit\Storage\MemoryStorage;

final class BKTreeTest extends SqliteStorageTestCase
{
    private BKTree $bkTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bkTree = new BKTree($this->getStorage(), 'test_bktree');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_insert_and_search(): void
    {
        // Arrange
        $words = ['laravel', 'python', 'php', 'javascript', 'ruby'];

        // Act
        foreach ($words as $word) {
            $this->bkTree->insert($word);
        }

        $results = $this->bkTree->search('larvel', 2);

        // Assert
        $this->assertInstanceOf(BKTreeResultCollection::class, $results);
        $this->assertNotEmpty($results);

        $firstResult = $results->first();
        $this->assertInstanceOf(BKTreeResultRecord::class, $firstResult);
        $this->assertSame('laravel', $firstResult->word);
        $this->assertLessThanOrEqual(2, $firstResult->distance);
    }

    public function test_search_with_tolerance(): void
    {
        // Arrange
        $words = ['php', 'python', 'perl', 'pascal'];

        foreach ($words as $word) {
            $this->bkTree->insert($word);
        }

        // Act
        $results = $this->bkTree->search('php', 0);

        // Assert
        $this->assertInstanceOf(BKTreeResultCollection::class, $results);
        $this->assertCount(1, $results);

        $firstResult = $results->first();
        $this->assertInstanceOf(BKTreeResultRecord::class, $firstResult);
        $this->assertSame('php', $firstResult->word);
        $this->assertSame(0, $firstResult->distance);
    }

    public function test_search_with_limit(): void
    {
        // Arrange
        $words = ['laravel', 'laragon', 'large', 'laptop'];

        foreach ($words as $word) {
            $this->bkTree->insert($word);
        }

        // Act
        $results = $this->bkTree->search('lara', 3, 2);

        // Assert
        $this->assertInstanceOf(BKTreeResultCollection::class, $results);
        $this->assertCount(2, $results);

        $items = $results->toArray();
        $this->assertLessThanOrEqual(3, $items[0]->distance);
        $this->assertLessThanOrEqual(3, $items[1]->distance);

        $expectedWords = ['laravel', 'laragon', 'large'];
        $this->assertContains($items[0]->word, $expectedWords);
        $this->assertContains($items[1]->word, $expectedWords);
    }

    public function test_search_non_existent(): void
    {
        // Act
        $results = $this->bkTree->search('nonexistent', 2);

        // Assert
        $this->assertInstanceOf(BKTreeResultCollection::class, $results);
        $this->assertEmpty($results);
        $this->assertSame(0, $results->count());
    }

    public function test_clear(): void
    {
        // Arrange
        $this->bkTree->insert('laravel');

        // Act
        $this->bkTree->clear();
        $results = $this->bkTree->search('larvel', 2);

        // Assert
        $this->assertInstanceOf(BKTreeResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_persistence(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $key = 'persistent_bktree';

        $bkTree1 = new BKTree($storage, $key);
        $bkTree1->insert('laravel');
        $bkTree1->insert('php');

        // Act
        $bkTree2 = new BKTree($storage, $key);
        $results = $bkTree2->search('larvel', 2);

        // Assert
        $this->assertInstanceOf(BKTreeResultCollection::class, $results);
        $this->assertNotEmpty($results);

        $firstResult = $results->first();
        $this->assertInstanceOf(BKTreeResultRecord::class, $firstResult);
        $this->assertSame('laravel', $firstResult->word);

        $storage->clear();
    }
}

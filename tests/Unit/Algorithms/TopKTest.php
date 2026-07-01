<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Collections\TopKResultCollection;
use AndyDefer\AlgoKIT\Records\TopKRecord;
use AndyDefer\AlgoKIT\Tests\SqliteStorageTestCase;
use AndyDefer\StorageKit\Storage\MemoryStorage;

final class TopKTest extends SqliteStorageTestCase
{
    private TopK $topK;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topK = new TopK($this->getStorage(), 3, 'test_topk');
    }

    public function test_add_and_get_top(): void
    {
        // Arrange
        $values = ['php', 'laravel', 'php', 'python', 'laravel', 'php'];

        // Act
        foreach ($values as $value) {
            $this->topK->add($value);
        }

        $top = $this->topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);
        $this->assertCount(3, $top);

        $items = $top->toArray();
        $this->assertSame('php', $items[0]->value);
        $this->assertSame(3, $items[0]->count);
        $this->assertSame('laravel', $items[1]->value);
        $this->assertSame(2, $items[1]->count);
        $this->assertSame('python', $items[2]->value);
        $this->assertSame(1, $items[2]->count);
    }

    public function test_add_with_increment(): void
    {
        // Arrange
        $values = ['php', 'laravel', 'python'];

        // Act
        $this->topK->add('php', 5);
        $this->topK->add('laravel', 3);
        $this->topK->add('python', 2);

        $top = $this->topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);

        $items = $top->toArray();
        $this->assertSame('php', $items[0]->value);
        $this->assertSame(5, $items[0]->count);
    }

    public function test_limited_k(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $topK = new TopK($storage, 2, 'small_topk');

        $values = ['a', 'b', 'c', 'a', 'b', 'a'];

        // Act
        foreach ($values as $value) {
            $topK->add($value);
        }

        $top = $topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);
        $this->assertCount(2, $top);

        $items = $top->toArray();
        $this->assertSame('a', $items[0]->value);
        $this->assertSame(3, $items[0]->count);
        $this->assertSame('b', $items[1]->value);
        $this->assertSame(2, $items[1]->count);
    }

    public function test_get_top_when_empty(): void
    {
        // Act
        $top = $this->topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);
        $this->assertEmpty($top);
        $this->assertSame(0, $top->count());
    }

    public function test_clear(): void
    {
        // Arrange
        $this->topK->add('php');
        $this->topK->add('laravel');

        // Act
        $this->topK->clear();
        $top = $this->topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);
        $this->assertEmpty($top);
    }

    public function test_persistence(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $key = 'persistent_topk';

        $topK1 = new TopK($storage, 3, $key);
        $topK1->add('php');
        $topK1->add('php');
        $topK1->add('laravel');

        // Act
        $topK2 = new TopK($storage, 3, $key);
        $top = $topK2->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);

        $items = $top->toArray();
        $this->assertSame('php', $items[0]->value);
        $this->assertSame(2, $items[0]->count);
        $this->assertSame('laravel', $items[1]->value);
        $this->assertSame(1, $items[1]->count);

        $storage->clear();
    }

    public function test_add_batch(): void
    {
        // Arrange
        $collection = new TopKCollection;
        $collection->add(new TopKRecord('php', 2));
        $collection->add(new TopKRecord('laravel', 1));
        $collection->add(new TopKRecord('python', 1));
        $collection->add(new TopKRecord('php', 1));

        // Act
        $this->topK->addBatch($collection);

        $top = $this->topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);
        $this->assertCount(3, $top);

        $items = $top->toArray();
        $this->assertSame('php', $items[0]->value);
        $this->assertSame(3, $items[0]->count);
        $this->assertSame('laravel', $items[1]->value);
        $this->assertSame(1, $items[1]->count);
        $this->assertSame('python', $items[2]->value);
        $this->assertSame(1, $items[2]->count);
    }

    public function test_add_batch_with_empty_collection(): void
    {
        // Arrange
        $collection = new TopKCollection;

        // Act
        $this->topK->addBatch($collection);

        $top = $this->topK->getTop();

        // Assert
        $this->assertInstanceOf(TopKResultCollection::class, $top);
        $this->assertEmpty($top);
        $this->assertSame(0, $top->count());
    }
}

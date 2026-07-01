<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Collections\CountMinSketchResultCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;
use AndyDefer\AlgoKIT\Tests\SqliteStorageTestCase;
use AndyDefer\StorageKit\Storage\MemoryStorage;

final class CountMinSketchTest extends SqliteStorageTestCase
{
    private CountMinSketch $cms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cms = new CountMinSketch($this->getStorage(), 1000, 3, 'test_cms');
    }

    protected function tearDown(): void
    {
        $this->cms->clear();
        parent::tearDown();
    }

    public function test_add_and_count(): void
    {
        // Arrange
        $value = 'laravel';

        // Act
        $this->cms->add('laravel');
        $this->cms->add('laravel');
        $this->cms->add('php');

        // Assert
        $this->assertSame(2, $this->cms->count('laravel'));
        $this->assertSame(1, $this->cms->count('php'));
        $this->assertSame(0, $this->cms->count('python'));
    }

    public function test_add_and_count_with_context(): void
    {
        // Act
        $this->cms->add('laravel', 'framework');
        $this->cms->add('laravel', 'framework');
        $this->cms->add('php', 'language');

        // Assert
        $this->assertSame(2, $this->cms->count('laravel', 'framework'));
        $this->assertSame(0, $this->cms->count('laravel', 'language'));
        $this->assertSame(1, $this->cms->count('php', 'language'));
        $this->assertSame(0, $this->cms->count('php', 'framework'));
    }

    public function test_multiple_adds(): void
    {
        // Arrange
        $value = 'laravel';

        // Act
        for ($i = 0; $i < 100; $i++) {
            $this->cms->add($value);
        }

        // Assert
        $this->assertSame(100, $this->cms->count($value));
    }

    public function test_different_values(): void
    {
        // Arrange
        $values = ['a', 'b', 'c', 'a', 'b', 'a'];

        // Act
        foreach ($values as $value) {
            $this->cms->add($value);
        }

        // Assert
        $this->assertSame(3, $this->cms->count('a'));
        $this->assertSame(2, $this->cms->count('b'));
        $this->assertSame(1, $this->cms->count('c'));
    }

    public function test_clear(): void
    {
        // Arrange
        $this->cms->add('laravel');
        $this->cms->add('laravel');

        // Act
        $this->cms->clear();

        // Assert
        $this->assertSame(0, $this->cms->count('laravel'));
    }

    public function test_persistence(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $key = 'persistent_cms';

        $cms1 = new CountMinSketch($storage, 1000, 3, $key);
        $cms1->add('laravel');
        $cms1->add('laravel');
        $cms1->add('php');

        // Act
        $cms2 = new CountMinSketch($storage, 1000, 3, $key);

        // Assert
        $this->assertSame(2, $cms2->count('laravel'));
        $this->assertSame(1, $cms2->count('php'));

        $storage->clear();
    }

    public function test_add_batch(): void
    {
        // Arrange
        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel'));
        $collection->add(new CountMinSketchRecord('laravel'));
        $collection->add(new CountMinSketchRecord('php'));
        $collection->add(new CountMinSketchRecord('python'));

        // Act
        $this->cms->addBatch($collection);

        // Assert
        $this->assertSame(2, $this->cms->count('laravel'));
        $this->assertSame(1, $this->cms->count('php'));
        $this->assertSame(1, $this->cms->count('python'));
        $this->assertSame(0, $this->cms->count('javascript'));
    }

    public function test_add_batch_with_context(): void
    {
        // Arrange
        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel', 'framework'));
        $collection->add(new CountMinSketchRecord('laravel', 'framework'));
        $collection->add(new CountMinSketchRecord('php', 'language'));

        // Act
        $this->cms->addBatch($collection);

        // Assert
        $this->assertSame(2, $this->cms->count('laravel', 'framework'));
        $this->assertSame(0, $this->cms->count('laravel', 'language'));
        $this->assertSame(1, $this->cms->count('php', 'language'));
    }

    public function test_count_batch(): void
    {
        // Arrange
        $this->cms->add('laravel');
        $this->cms->add('laravel');
        $this->cms->add('php');
        $this->cms->add('python');

        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel'));
        $collection->add(new CountMinSketchRecord('php'));
        $collection->add(new CountMinSketchRecord('javascript'));
        $collection->add(new CountMinSketchRecord('python'));

        // Act
        $results = $this->cms->countBatch($collection);

        // Assert
        $this->assertInstanceOf(CountMinSketchResultCollection::class, $results);
        $this->assertCount(4, $results);

        $items = $results->toArray();
        $this->assertSame(2, $items[0]->count);
        $this->assertSame('laravel', $items[0]->value);
        $this->assertSame(1, $items[1]->count);
        $this->assertSame('php', $items[1]->value);
        $this->assertSame(0, $items[2]->count);
        $this->assertSame('javascript', $items[2]->value);
        $this->assertSame(1, $items[3]->count);
        $this->assertSame('python', $items[3]->value);
    }

    public function test_count_batch_with_context(): void
    {
        // Arrange
        $this->cms->add('laravel', 'framework');
        $this->cms->add('laravel', 'framework');
        $this->cms->add('php', 'language');

        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel', 'framework'));
        $collection->add(new CountMinSketchRecord('laravel', 'language'));
        $collection->add(new CountMinSketchRecord('php', 'language'));

        // Act
        $results = $this->cms->countBatch($collection);

        // Assert
        $items = $results->toArray();
        $this->assertSame(2, $items[0]->count);
        $this->assertSame('framework', $items[0]->context);
        $this->assertSame(0, $items[1]->count);
        $this->assertSame('language', $items[1]->context);
        $this->assertSame(1, $items[2]->count);
        $this->assertSame('language', $items[2]->context);
    }

    public function test_count_batch_with_empty_collection(): void
    {
        // Arrange
        $collection = new CountMinSketchCollection;

        // Act
        $results = $this->cms->countBatch($collection);

        // Assert
        $this->assertInstanceOf(CountMinSketchResultCollection::class, $results);
        $this->assertCount(0, $results);
        $this->assertEmpty($results);
    }

    public function test_add_batch_with_empty_collection(): void
    {
        // Arrange
        $collection = new CountMinSketchCollection;

        // Act
        $this->cms->addBatch($collection);

        // Assert
        $this->assertSame(0, $this->cms->count('laravel'));
        $this->assertSame(0, $this->cms->count('php'));
    }
}

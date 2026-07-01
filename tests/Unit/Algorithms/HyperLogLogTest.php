<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Collections\HyperLogLogResultCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\AlgoKIT\Tests\SqliteStorageTestCase;
use AndyDefer\StorageKit\Storage\MemoryStorage;

final class HyperLogLogTest extends SqliteStorageTestCase
{
    private HyperLogLog $hll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hll = new HyperLogLog($this->getStorage(), 8, 'test_hll');
    }

    protected function tearDown(): void
    {
        $this->hll->clear();
        parent::tearDown();
    }

    public function test_add_and_count(): void
    {
        // Arrange
        $values = ['a', 'b', 'c', 'd', 'e'];

        // Act
        foreach ($values as $value) {
            $this->hll->add($value);
        }

        $count = $this->hll->count();

        // Assert
        $this->assertGreaterThanOrEqual(4, $count);
        $this->assertLessThanOrEqual(8, $count);
    }

    public function test_add_and_count_with_context(): void
    {
        // Arrange
        $this->hll->add('a', 'context1');
        $this->hll->add('b', 'context1');
        $this->hll->add('c', 'context1');
        $this->hll->add('d', 'context2');
        $this->hll->add('e', 'context2');

        // Act
        $count1 = $this->hll->count('context1');
        $count2 = $this->hll->count('context2');
        $countGlobal = $this->hll->count();

        // Assert
        $this->assertGreaterThanOrEqual(2, $count1);
        $this->assertLessThanOrEqual(5, $count1);

        $this->assertGreaterThanOrEqual(1, $count2);
        $this->assertLessThanOrEqual(4, $count2);

        $this->assertGreaterThanOrEqual(4, $countGlobal);
        $this->assertLessThanOrEqual(8, $countGlobal);
    }

    public function test_duplicates(): void
    {
        // Arrange
        $values = ['a', 'a', 'a', 'b', 'b', 'c'];

        // Act
        foreach ($values as $value) {
            $this->hll->add($value);
        }

        $count = $this->hll->count();

        // Assert
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(5, $count);
    }

    public function test_large_set(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $hll = new HyperLogLog($storage, 16, 'test_hll_precise');

        // Act
        for ($i = 0; $i < 1000; $i++) {
            $value = 'value_'.$i;
            $hll->add($value);
        }

        $count = $hll->count();

        // Assert
        $this->assertGreaterThan(900, $count);
        $this->assertLessThan(1100, $count);

        $hll->clear();
    }

    public function test_clear(): void
    {
        // Arrange
        $this->hll->add('a');
        $this->hll->add('b');

        // Act
        $countBefore = $this->hll->count();
        $this->hll->clear();
        $countAfter = $this->hll->count();

        // Assert
        $this->assertGreaterThan(0, $countBefore);
        $this->assertSame(0, $countAfter);
    }

    public function test_persistence(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $key = 'persistent_hll';

        $hll1 = new HyperLogLog($storage, 8, $key);
        $hll1->add('a');
        $hll1->add('b');
        $hll1->add('c');

        // Act
        $hll2 = new HyperLogLog($storage, 8, $key);
        $count = $hll2->count();

        // Assert
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(5, $count);

        $hll1->clear();
    }

    public function test_add_batch(): void
    {
        // Arrange
        $collection = new HyperLogLogCollection;
        $collection->add(new HyperLogLogRecord('a'));
        $collection->add(new HyperLogLogRecord('b'));
        $collection->add(new HyperLogLogRecord('c'));
        $collection->add(new HyperLogLogRecord('a'));

        // Act
        $this->hll->addBatch($collection);

        $count = $this->hll->count();

        // Assert
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(5, $count);
    }

    public function test_add_batch_with_context(): void
    {
        // Arrange
        $collection = new HyperLogLogCollection;
        $collection->add(new HyperLogLogRecord('a', 'context1'));
        $collection->add(new HyperLogLogRecord('b', 'context1'));
        $collection->add(new HyperLogLogRecord('c', 'context2'));

        // Act
        $this->hll->addBatch($collection);

        $count1 = $this->hll->count('context1');
        $count2 = $this->hll->count('context2');

        // Assert
        $this->assertGreaterThanOrEqual(1, $count1);
        $this->assertLessThanOrEqual(4, $count1);

        $this->assertGreaterThanOrEqual(0, $count2);
        $this->assertLessThanOrEqual(3, $count2);
    }

    public function test_add_batch_with_empty_collection(): void
    {
        // Arrange
        $collection = new HyperLogLogCollection;

        // Act
        $this->hll->addBatch($collection);
        $count = $this->hll->count();

        // Assert
        $this->assertSame(0, $count);
    }

    public function test_count_batch(): void
    {
        // Arrange
        $this->hll->add('a', 'context1');
        $this->hll->add('b', 'context2');
        $this->hll->add('c', 'context3');

        $collection = new HyperLogLogCollection;
        $collection->add(new HyperLogLogRecord('x', 'context1'));
        $collection->add(new HyperLogLogRecord('y', 'context2'));
        $collection->add(new HyperLogLogRecord('z', 'context3'));

        // Act
        $results = $this->hll->countBatch($collection);

        // Assert
        $this->assertInstanceOf(HyperLogLogResultCollection::class, $results);
        $this->assertCount(3, $results);

        $items = $results->toArray();
        $this->assertGreaterThanOrEqual(1, $items[0]->count);
        $this->assertSame('context1', $items[0]->context);

        $this->assertGreaterThanOrEqual(1, $items[1]->count);
        $this->assertSame('context2', $items[1]->context);

        $this->assertGreaterThanOrEqual(1, $items[2]->count);
        $this->assertSame('context3', $items[2]->context);
    }

    public function test_count_batch_with_empty_collection(): void
    {
        // Arrange
        $collection = new HyperLogLogCollection;

        // Act
        $results = $this->hll->countBatch($collection);

        // Assert
        $this->assertInstanceOf(HyperLogLogResultCollection::class, $results);
        $this->assertCount(0, $results);
        $this->assertEmpty($results);
    }
}

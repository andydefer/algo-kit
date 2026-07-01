<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Collections\BloomFilterResultCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;
use AndyDefer\AlgoKIT\Tests\SqliteStorageTestCase;
use AndyDefer\StorageKit\Storage\MemoryStorage;

final class BloomFilterTest extends SqliteStorageTestCase
{
    private BloomFilter $bloom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bloom = new BloomFilter($this->getStorage(), 1000, 3, 'test_bloom');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_insert_and_exists(): void
    {
        // Arrange
        $value = 'laravel';

        // Act
        $this->bloom->insert($value);

        // Assert
        $this->assertTrue($this->bloom->exists('laravel'));
        $this->assertFalse($this->bloom->exists('python'));
    }

    public function test_insert_and_exists_with_context(): void
    {
        // Arrange
        $this->bloom->insert('laravel', 'framework');
        $this->bloom->insert('python', 'language');

        // Assert
        $this->assertTrue($this->bloom->exists('laravel', 'framework'));
        $this->assertFalse($this->bloom->exists('laravel', 'language'));
        $this->assertTrue($this->bloom->exists('python', 'language'));
        $this->assertFalse($this->bloom->exists('python', 'framework'));
    }

    public function test_multiple_inserts(): void
    {
        // Arrange
        $words = ['php', 'python', 'laravel', 'javascript'];

        // Act
        foreach ($words as $word) {
            $this->bloom->insert($word);
        }

        // Assert
        foreach ($words as $word) {
            $this->assertTrue($this->bloom->exists($word));
        }

        $this->assertFalse($this->bloom->exists('ruby'));
    }

    public function test_false_positives(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $smallBloom = new BloomFilter($storage, 10, 3, 'small_bloom');

        // Act
        $smallBloom->insert('a');
        $smallBloom->insert('b');
        $smallBloom->insert('c');

        // Assert
        $this->assertTrue($smallBloom->exists('a'));
        $this->assertTrue($smallBloom->exists('b'));
        $this->assertTrue($smallBloom->exists('c'));
    }

    public function test_clear(): void
    {
        // Arrange
        $this->bloom->insert('laravel');

        // Act
        $this->bloom->clear();

        // Assert
        $this->assertFalse($this->bloom->exists('laravel'));
    }

    public function test_persistence(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $key = 'persistent_bloom';

        $bloom1 = new BloomFilter($storage, 1000, 3, $key);
        $bloom1->insert('laravel');
        $bloom1->insert('php');

        // Act
        $bloom2 = new BloomFilter($storage, 1000, 3, $key);

        // Assert
        $this->assertTrue($bloom2->exists('laravel'));
        $this->assertTrue($bloom2->exists('php'));
        $this->assertFalse($bloom2->exists('python'));

        $storage->clear();
    }

    public function test_insert_batch(): void
    {
        // Arrange
        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel'));
        $collection->add(new BloomFilterRecord('php'));
        $collection->add(new BloomFilterRecord('python'));

        // Act
        $this->bloom->insertBatch($collection);

        // Assert
        $this->assertTrue($this->bloom->exists('laravel'));
        $this->assertTrue($this->bloom->exists('php'));
        $this->assertTrue($this->bloom->exists('python'));
        $this->assertFalse($this->bloom->exists('javascript'));
    }

    public function test_insert_batch_with_context(): void
    {
        // Arrange
        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel', 'framework'));
        $collection->add(new BloomFilterRecord('php', 'language'));
        $collection->add(new BloomFilterRecord('laravel', 'language'));

        // Act
        $this->bloom->insertBatch($collection);

        // Assert
        $this->assertTrue($this->bloom->exists('laravel', 'framework'));
        $this->assertTrue($this->bloom->exists('php', 'language'));
        $this->assertTrue($this->bloom->exists('laravel', 'language'));
        $this->assertFalse($this->bloom->exists('laravel', 'database'));
    }

    public function test_exists_batch(): void
    {
        // Arrange
        $this->bloom->insert('laravel');
        $this->bloom->insert('php');
        $this->bloom->insert('python');

        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel'));
        $collection->add(new BloomFilterRecord('php'));
        $collection->add(new BloomFilterRecord('javascript'));
        $collection->add(new BloomFilterRecord('python'));

        // Act
        $results = $this->bloom->existsBatch($collection);

        // Assert
        $this->assertInstanceOf(BloomFilterResultCollection::class, $results);
        $this->assertCount(4, $results);

        $items = $results->toArray();
        $this->assertTrue($items[0]->exists);
        $this->assertSame('laravel', $items[0]->value);

        $this->assertTrue($items[1]->exists);
        $this->assertSame('php', $items[1]->value);

        $this->assertFalse($items[2]->exists);
        $this->assertSame('javascript', $items[2]->value);

        $this->assertTrue($items[3]->exists);
        $this->assertSame('python', $items[3]->value);
    }

    public function test_exists_batch_with_context(): void
    {
        // Arrange
        $this->bloom->insert('laravel', 'framework');
        $this->bloom->insert('php', 'language');

        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel', 'framework'));
        $collection->add(new BloomFilterRecord('laravel', 'language'));
        $collection->add(new BloomFilterRecord('php', 'language'));

        // Act
        $results = $this->bloom->existsBatch($collection);

        // Assert
        $items = $results->toArray();
        $this->assertTrue($items[0]->exists);
        $this->assertSame('framework', $items[0]->context);
        $this->assertFalse($items[1]->exists);
        $this->assertSame('language', $items[1]->context);
        $this->assertTrue($items[2]->exists);
        $this->assertSame('language', $items[2]->context);
    }

    public function test_exists_batch_with_empty_collection(): void
    {
        // Arrange
        $collection = new BloomFilterCollection;

        // Act
        $results = $this->bloom->existsBatch($collection);

        // Assert
        $this->assertInstanceOf(BloomFilterResultCollection::class, $results);
        $this->assertCount(0, $results);
        $this->assertEmpty($results);
    }
}

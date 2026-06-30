<?php

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Collections\BloomFilterResultCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

class BloomFilterTest extends TestCase
{
    private BloomFilter $bloom;

    protected function setUp(): void
    {
        $storage = new MemoryStorage;
        $this->bloom = new BloomFilter($storage, 1000, 3, 'test_bloom');
    }

    public function test_insert_and_exists(): void
    {
        $this->bloom->insert('laravel');
        $this->assertTrue($this->bloom->exists('laravel'));
        $this->assertFalse($this->bloom->exists('python'));
    }

    public function test_insert_and_exists_with_context(): void
    {
        $this->bloom->insert('laravel', 'framework');
        $this->bloom->insert('python', 'language');

        $this->assertTrue($this->bloom->exists('laravel', 'framework'));
        $this->assertFalse($this->bloom->exists('laravel', 'language'));
        $this->assertTrue($this->bloom->exists('python', 'language'));
        $this->assertFalse($this->bloom->exists('python', 'framework'));
    }

    public function test_multiple_inserts(): void
    {
        $words = ['php', 'python', 'laravel', 'javascript'];

        foreach ($words as $word) {
            $this->bloom->insert($word);
        }

        foreach ($words as $word) {
            $this->assertTrue($this->bloom->exists($word));
        }

        $this->assertFalse($this->bloom->exists('ruby'));
    }

    public function test_false_positives(): void
    {
        $storage = new MemoryStorage;
        $smallBloom = new BloomFilter($storage, 10, 3, 'small_bloom');

        $smallBloom->insert('a');
        $smallBloom->insert('b');
        $smallBloom->insert('c');

        $this->assertTrue($smallBloom->exists('a'));
        $this->assertTrue($smallBloom->exists('b'));
        $this->assertTrue($smallBloom->exists('c'));
    }

    public function test_clear(): void
    {
        $this->bloom->insert('laravel');
        $this->assertTrue($this->bloom->exists('laravel'));

        $this->bloom->clear();
        $this->assertFalse($this->bloom->exists('laravel'));
    }

    public function test_persistence(): void
    {
        $storage = new MemoryStorage;

        $bloom1 = new BloomFilter($storage, 1000, 3, 'persistent_bloom');
        $bloom1->insert('laravel');
        $bloom1->insert('php');

        $bloom2 = new BloomFilter($storage, 1000, 3, 'persistent_bloom');
        $this->assertTrue($bloom2->exists('laravel'));
        $this->assertTrue($bloom2->exists('php'));
        $this->assertFalse($bloom2->exists('python'));
    }

    public function test_insert_batch(): void
    {
        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel'));
        $collection->add(new BloomFilterRecord('php'));
        $collection->add(new BloomFilterRecord('python'));

        $this->bloom->insertBatch($collection);

        $this->assertTrue($this->bloom->exists('laravel'));
        $this->assertTrue($this->bloom->exists('php'));
        $this->assertTrue($this->bloom->exists('python'));
        $this->assertFalse($this->bloom->exists('javascript'));
    }

    public function test_insert_batch_with_context(): void
    {
        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel', 'framework'));
        $collection->add(new BloomFilterRecord('php', 'language'));
        $collection->add(new BloomFilterRecord('laravel', 'language'));

        $this->bloom->insertBatch($collection);

        $this->assertTrue($this->bloom->exists('laravel', 'framework'));
        $this->assertTrue($this->bloom->exists('php', 'language'));
        $this->assertTrue($this->bloom->exists('laravel', 'language'));
        $this->assertFalse($this->bloom->exists('laravel', 'database'));
    }

    public function test_exists_batch(): void
    {
        $this->bloom->insert('laravel');
        $this->bloom->insert('php');
        $this->bloom->insert('python');

        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel'));
        $collection->add(new BloomFilterRecord('php'));
        $collection->add(new BloomFilterRecord('javascript'));
        $collection->add(new BloomFilterRecord('python'));

        $results = $this->bloom->existsBatch($collection);

        $this->assertInstanceOf(BloomFilterResultCollection::class, $results);
        $this->assertCount(4, $results);

        $items = $results->toArray();
        $this->assertTrue($items[0]->exists);
        $this->assertEquals('laravel', $items[0]->value);

        $this->assertTrue($items[1]->exists);
        $this->assertEquals('php', $items[1]->value);

        $this->assertFalse($items[2]->exists);
        $this->assertEquals('javascript', $items[2]->value);

        $this->assertTrue($items[3]->exists);
        $this->assertEquals('python', $items[3]->value);
    }

    public function test_exists_batch_with_context(): void
    {
        $this->bloom->insert('laravel', 'framework');
        $this->bloom->insert('php', 'language');

        $collection = new BloomFilterCollection;
        $collection->add(new BloomFilterRecord('laravel', 'framework'));
        $collection->add(new BloomFilterRecord('laravel', 'language'));
        $collection->add(new BloomFilterRecord('php', 'language'));

        $results = $this->bloom->existsBatch($collection);

        $items = $results->toArray();
        $this->assertTrue($items[0]->exists);
        $this->assertEquals('framework', $items[0]->context);
        $this->assertFalse($items[1]->exists);
        $this->assertEquals('language', $items[1]->context);
        $this->assertTrue($items[2]->exists);
        $this->assertEquals('language', $items[2]->context);
    }

    public function test_exists_batch_with_empty_collection(): void
    {
        $collection = new BloomFilterCollection;
        $results = $this->bloom->existsBatch($collection);

        $this->assertInstanceOf(BloomFilterResultCollection::class, $results);
        $this->assertCount(0, $results);
        $this->assertEmpty($results);
    }
}

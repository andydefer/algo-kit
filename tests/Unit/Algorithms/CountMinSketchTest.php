<?php

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Collections\CountMinSketchResultCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

class CountMinSketchTest extends TestCase
{
    private CountMinSketch $cms;

    protected function setUp(): void
    {
        $storage = new MemoryStorage;
        $this->cms = new CountMinSketch($storage, 1000, 3, 'test_cms');
    }

    public function test_add_and_count(): void
    {
        $this->cms->add('laravel');
        $this->cms->add('laravel');
        $this->cms->add('php');

        $this->assertEquals(2, $this->cms->count('laravel'));
        $this->assertEquals(1, $this->cms->count('php'));
        $this->assertEquals(0, $this->cms->count('python'));
    }

    public function test_add_and_count_with_context(): void
    {
        $this->cms->add('laravel', 'framework');
        $this->cms->add('laravel', 'framework');
        $this->cms->add('php', 'language');

        $this->assertEquals(2, $this->cms->count('laravel', 'framework'));
        $this->assertEquals(0, $this->cms->count('laravel', 'language'));
        $this->assertEquals(1, $this->cms->count('php', 'language'));
        $this->assertEquals(0, $this->cms->count('php', 'framework'));
    }

    public function test_multiple_adds(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cms->add('laravel');
        }

        $this->assertEquals(100, $this->cms->count('laravel'));
    }

    public function test_different_values(): void
    {
        $values = ['a', 'b', 'c', 'a', 'b', 'a'];

        foreach ($values as $value) {
            $this->cms->add($value);
        }

        $this->assertEquals(3, $this->cms->count('a'));
        $this->assertEquals(2, $this->cms->count('b'));
        $this->assertEquals(1, $this->cms->count('c'));
    }

    public function test_clear(): void
    {
        $this->cms->add('laravel');
        $this->cms->add('laravel');

        $this->assertEquals(2, $this->cms->count('laravel'));

        $this->cms->clear();
        $this->assertEquals(0, $this->cms->count('laravel'));
    }

    public function test_persistence(): void
    {
        $storage = new MemoryStorage;

        $cms1 = new CountMinSketch($storage, 1000, 3, 'persistent_cms');
        $cms1->add('laravel');
        $cms1->add('laravel');
        $cms1->add('php');

        $cms2 = new CountMinSketch($storage, 1000, 3, 'persistent_cms');
        $this->assertEquals(2, $cms2->count('laravel'));
        $this->assertEquals(1, $cms2->count('php'));
    }

    public function test_add_batch(): void
    {
        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel'));
        $collection->add(new CountMinSketchRecord('laravel'));
        $collection->add(new CountMinSketchRecord('php'));
        $collection->add(new CountMinSketchRecord('python'));

        $this->cms->addBatch($collection);

        $this->assertEquals(2, $this->cms->count('laravel'));
        $this->assertEquals(1, $this->cms->count('php'));
        $this->assertEquals(1, $this->cms->count('python'));
        $this->assertEquals(0, $this->cms->count('javascript'));
    }

    public function test_add_batch_with_context(): void
    {
        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel', 'framework'));
        $collection->add(new CountMinSketchRecord('laravel', 'framework'));
        $collection->add(new CountMinSketchRecord('php', 'language'));

        $this->cms->addBatch($collection);

        // 🔥 DEBUG

        $this->assertEquals(2, $this->cms->count('laravel', 'framework'));
        $this->assertEquals(0, $this->cms->count('laravel', 'language'));
        $this->assertEquals(1, $this->cms->count('php', 'language'));
    }

    public function test_count_batch(): void
    {
        $this->cms->add('laravel');
        $this->cms->add('laravel');
        $this->cms->add('php');
        $this->cms->add('python');

        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel'));
        $collection->add(new CountMinSketchRecord('php'));
        $collection->add(new CountMinSketchRecord('javascript'));
        $collection->add(new CountMinSketchRecord('python'));

        $results = $this->cms->countBatch($collection);

        $this->assertInstanceOf(CountMinSketchResultCollection::class, $results);
        $this->assertCount(4, $results);

        $items = $results->toArray();
        $this->assertEquals(2, $items[0]->count);
        $this->assertEquals('laravel', $items[0]->value);
        $this->assertEquals(1, $items[1]->count);
        $this->assertEquals('php', $items[1]->value);
        $this->assertEquals(0, $items[2]->count);
        $this->assertEquals('javascript', $items[2]->value);
        $this->assertEquals(1, $items[3]->count);
        $this->assertEquals('python', $items[3]->value);
    }

    public function test_count_batch_with_context(): void
    {
        $this->cms->add('laravel', 'framework');
        $this->cms->add('laravel', 'framework');
        $this->cms->add('php', 'language');

        $collection = new CountMinSketchCollection;
        $collection->add(new CountMinSketchRecord('laravel', 'framework'));
        $collection->add(new CountMinSketchRecord('laravel', 'language'));
        $collection->add(new CountMinSketchRecord('php', 'language'));

        $results = $this->cms->countBatch($collection);

        $items = $results->toArray();
        $this->assertEquals(2, $items[0]->count);
        $this->assertEquals('framework', $items[0]->context);
        $this->assertEquals(0, $items[1]->count);
        $this->assertEquals('language', $items[1]->context);
        $this->assertEquals(1, $items[2]->count);
        $this->assertEquals('language', $items[2]->context);
    }

    public function test_count_batch_with_empty_collection(): void
    {
        $collection = new CountMinSketchCollection;
        $results = $this->cms->countBatch($collection);

        $this->assertInstanceOf(CountMinSketchResultCollection::class, $results);
        $this->assertCount(0, $results);
        $this->assertEmpty($results);
    }

    public function test_add_batch_with_empty_collection(): void
    {
        $collection = new CountMinSketchCollection;
        $this->cms->addBatch($collection);

        $this->assertEquals(0, $this->cms->count('laravel'));
        $this->assertEquals(0, $this->cms->count('php'));
    }
}

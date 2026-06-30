<?php

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Collections\HyperLogLogResultCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

class HyperLogLogTest extends TestCase
{
    private HyperLogLog $hll;

    private MemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new MemoryStorage;
        // Utiliser une précision plus petite pour les tests (8 = 256 registres)
        $this->hll = new HyperLogLog($this->storage, 8, 'test_hll');
    }

    protected function tearDown(): void
    {
        $this->hll->clear();
        // Nettoyer les clés du storage
        $keys = ['test_hll', 'test_hll_context1', 'test_hll_context2', 'test_hll_global'];
        foreach ($keys as $key) {
            $this->storage->delete($key);
        }
    }

    public function test_add_and_count(): void
    {

        $values = ['a', 'b', 'c', 'd', 'e'];

        foreach ($values as $value) {
            $this->hll->add($value);
        }

        $count = $this->hll->count();

        // HyperLogLog est approximatif, on vérifie que le résultat est proche de 5
        $this->assertGreaterThanOrEqual(4, $count);
        $this->assertLessThanOrEqual(8, $count);
    }

    public function test_add_and_count_with_context(): void
    {

        // Ajout avec contextes
        $this->hll->add('a', 'context1');
        $this->hll->add('b', 'context1');
        $this->hll->add('c', 'context1');
        $this->hll->add('d', 'context2');
        $this->hll->add('e', 'context2');

        // Vérifier les contextes
        $count1 = $this->hll->count('context1');
        $count2 = $this->hll->count('context2');
        $countGlobal = $this->hll->count();

        // context1 a 3 éléments uniques
        $this->assertGreaterThanOrEqual(2, $count1);
        $this->assertLessThanOrEqual(5, $count1);

        // context2 a 2 éléments uniques
        $this->assertGreaterThanOrEqual(1, $count2);
        $this->assertLessThanOrEqual(4, $count2);

        // Global a 5 éléments uniques
        $this->assertGreaterThanOrEqual(4, $countGlobal);
        $this->assertLessThanOrEqual(8, $countGlobal);
    }

    public function test_duplicates(): void
    {

        $values = ['a', 'a', 'a', 'b', 'b', 'c'];

        foreach ($values as $value) {
            $this->hll->add($value);
        }

        $count = $this->hll->count();

        // 3 éléments uniques
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(5, $count);
    }

    public function test_large_set(): void
    {

        $storage = new MemoryStorage;
        // Précision 16 pour une meilleure précision
        $hll = new HyperLogLog($storage, 16, 'test_hll_precise');

        for ($i = 0; $i < 1000; $i++) {
            $value = 'value_'.$i;
            $hll->add($value);
        }

        $count = $hll->count();

        // 1000 éléments uniques, erreur ~1.6% avec precision 16
        $this->assertGreaterThan(900, $count);
        $this->assertLessThan(1100, $count);

        $hll->clear();
    }

    public function test_clear(): void
    {

        $this->hll->add('a');
        $this->hll->add('b');

        $countBefore = $this->hll->count();

        $this->assertGreaterThan(0, $countBefore);

        $this->hll->clear();
        $countAfter = $this->hll->count();

        $this->assertEquals(0, $countAfter);
    }

    public function test_persistence(): void
    {

        $storage = new MemoryStorage;

        $hll1 = new HyperLogLog($storage, 8, 'persistent_hll');
        $hll1->add('a');
        $hll1->add('b');
        $hll1->add('c');

        $hll2 = new HyperLogLog($storage, 8, 'persistent_hll');
        $count = $hll2->count();

        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(5, $count);

        $hll1->clear();
    }

    public function test_add_batch(): void
    {

        $collection = new HyperLogLogCollection;
        $collection->add(new HyperLogLogRecord('a'));
        $collection->add(new HyperLogLogRecord('b'));
        $collection->add(new HyperLogLogRecord('c'));
        $collection->add(new HyperLogLogRecord('a')); // Duplicate

        $this->hll->addBatch($collection);

        $count = $this->hll->count();

        // 3 éléments uniques
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(5, $count);
    }

    public function test_add_batch_with_context(): void
    {

        $collection = new HyperLogLogCollection;
        $collection->add(new HyperLogLogRecord('a', 'context1'));
        $collection->add(new HyperLogLogRecord('b', 'context1'));
        $collection->add(new HyperLogLogRecord('c', 'context2'));

        $this->hll->addBatch($collection);

        $count1 = $this->hll->count('context1');
        $count2 = $this->hll->count('context2');

        $this->assertGreaterThanOrEqual(1, $count1);
        $this->assertLessThanOrEqual(4, $count1);

        $this->assertGreaterThanOrEqual(0, $count2);
        $this->assertLessThanOrEqual(3, $count2);
    }

    public function test_add_batch_with_empty_collection(): void
    {

        $collection = new HyperLogLogCollection;
        $this->hll->addBatch($collection);

        $count = $this->hll->count();

        $this->assertEquals(0, $count);
    }

    public function test_count_batch(): void
    {

        // Ajout avec les mêmes contextes que le countBatch
        $this->hll->add('a', 'context1');
        $this->hll->add('b', 'context2');
        $this->hll->add('c', 'context3');

        // 🔥 DEBUG : Vérifier le count de chaque contexte

        $collection = new HyperLogLogCollection;
        $collection->add(new HyperLogLogRecord('x', 'context1'));
        $collection->add(new HyperLogLogRecord('y', 'context2'));
        $collection->add(new HyperLogLogRecord('z', 'context3'));

        $results = $this->hll->countBatch($collection);

        $this->assertInstanceOf(HyperLogLogResultCollection::class, $results);
        $this->assertCount(3, $results);

        $items = $results->toArray();

        foreach ($items as $item) {
        }

        $this->assertGreaterThanOrEqual(1, $items[0]->count);
        $this->assertEquals('context1', $items[0]->context);

        $this->assertGreaterThanOrEqual(1, $items[1]->count);
        $this->assertEquals('context2', $items[1]->context);

        $this->assertGreaterThanOrEqual(1, $items[2]->count);
        $this->assertEquals('context3', $items[2]->context);
    }

    public function test_count_batch_with_empty_collection(): void
    {

        $collection = new HyperLogLogCollection;
        $results = $this->hll->countBatch($collection);

        $this->assertInstanceOf(HyperLogLogResultCollection::class, $results);
        $this->assertCount(0, $results);
        $this->assertEmpty($results);

    }
}

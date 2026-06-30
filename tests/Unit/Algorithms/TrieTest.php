<?php

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Collections\TrieResultCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

class TrieTest extends TestCase
{
    private Trie $trie;

    protected function setUp(): void
    {
        $storage = new MemoryStorage;
        $this->trie = new Trie($storage, 'test_trie');
    }

    public function test_insert_and_search(): void
    {
        $words = ['laravel', 'laragon', 'large', 'laptop', 'light'];

        foreach ($words as $word) {
            $this->trie->insert($word);
        }

        $results = $this->trie->search('lar');

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(3, $results);

        $items = $results->toArray();
        $this->assertEquals('laravel', $items[0]->word);
        $this->assertEquals('laragon', $items[1]->word);
        $this->assertEquals('large', $items[2]->word);
    }

    public function test_search_with_context(): void
    {
        $this->trie->insert('bonjour', 'french');
        $this->trie->insert('hello', 'english');
        $this->trie->insert('merci', 'french');
        $this->trie->insert('thank_you', 'english');

        $frenchResults = $this->trie->search('bon', 'french');
        $this->assertCount(1, $frenchResults);
        $this->assertEquals('bonjour', $frenchResults->first()->word);
        $this->assertEquals('french', $frenchResults->first()->context);

        $englishResults = $this->trie->search('hel', 'english');
        $this->assertCount(1, $englishResults);
        $this->assertEquals('hello', $englishResults->first()->word);
        $this->assertEquals('english', $englishResults->first()->context);
    }

    public function test_insert_batch_with_context(): void
    {
        $collection = new TrieCollection;
        $collection->add(new TrieRecord('bonjour', 'french'));
        $collection->add(new TrieRecord('hello', 'english'));
        $collection->add(new TrieRecord('merci', 'french'));
        $collection->add(new TrieRecord('thank_you', 'english'));

        $this->trie->insertBatch($collection);

        $results = $this->trie->search('bon', 'french');
        $this->assertCount(1, $results);
        $this->assertEquals('bonjour', $results->first()->word);

        $results = $this->trie->search('hel', 'english');
        $this->assertCount(1, $results);
        $this->assertEquals('hello', $results->first()->word);
    }

    public function test_search_batch_with_context(): void
    {
        $words = ['laravel', 'python', 'php', 'javascript'];
        foreach ($words as $word) {
            $this->trie->insert($word);
        }

        $collection = new TrieCollection;
        $collection->add(new TrieRecord('la'));
        $collection->add(new TrieRecord('py'));
        $collection->add(new TrieRecord('ja'));

        $results = $this->trie->searchBatch($collection, 2);

        $this->assertArrayHasKey('la', $results);
        $this->assertArrayHasKey('py', $results);
        $this->assertArrayHasKey('ja', $results);

        $this->assertInstanceOf(TrieResultCollection::class, $results['la']);
        $this->assertInstanceOf(TrieResultCollection::class, $results['py']);
        $this->assertInstanceOf(TrieResultCollection::class, $results['ja']);
    }

    public function test_search_with_limit(): void
    {
        $words = ['php', 'python', 'perl', 'pascal', 'puppet'];

        foreach ($words as $word) {
            $this->trie->insert($word);
        }

        $results = $this->trie->search('p', null, 2);

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(2, $results);
    }

    public function test_search_empty_prefix(): void
    {
        $results = $this->trie->search('');

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_search_non_existent_prefix(): void
    {
        $this->trie->insert('laravel');
        $results = $this->trie->search('xyz');

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_duplicate_insert(): void
    {
        $this->trie->insert('laravel');
        $this->trie->insert('laravel');

        $results = $this->trie->search('lar');

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals('laravel', $results->first()->word);
    }

    public function test_clear(): void
    {
        $this->trie->insert('laravel');
        $this->assertNotEmpty($this->trie->search('lar'));

        $this->trie->clear();
        $results = $this->trie->search('lar');

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_persistence(): void
    {
        $storage = new MemoryStorage;

        $trie1 = new Trie($storage, 'persistent_trie');
        $trie1->insert('laravel');
        $trie1->insert('php');

        $trie2 = new Trie($storage, 'persistent_trie');
        $results = $trie2->search('l');

        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals('laravel', $results->first()->word);
    }

    public function test_clear_with_context(): void
    {
        $this->trie->insert('bonjour', 'french');
        $this->trie->insert('hello', 'english');

        // Clear specific context
        $this->trie->clear(); // Clear all

        $frenchResults = $this->trie->search('bon', 'french');
        $englishResults = $this->trie->search('hel', 'english');

        $this->assertEmpty($frenchResults);
        $this->assertEmpty($englishResults);
    }
}

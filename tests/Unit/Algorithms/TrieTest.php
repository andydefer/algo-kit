<?php

declare(strict_types=1);

namespace AndyDefer\AlgoKIT\Tests\Unit\Algorithms;

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Collections\TrieResultCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\AlgoKIT\Tests\SqliteStorageTestCase;
use AndyDefer\StorageKit\Storage\MemoryStorage;

final class TrieTest extends SqliteStorageTestCase
{
    private Trie $trie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trie = new Trie($this->getStorage(), 'test_trie');
    }

    public function test_insert_and_search(): void
    {
        // Arrange
        $words = ['laravel', 'laragon', 'large', 'laptop', 'light'];

        // Act
        foreach ($words as $word) {
            $this->trie->insert($word);
        }

        $results = $this->trie->search('lar');

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(3, $results);

        $items = $results->toArray();
        $this->assertSame('laravel', $items[0]->word);
        $this->assertSame('laragon', $items[1]->word);
        $this->assertSame('large', $items[2]->word);
    }

    public function test_search_with_context(): void
    {
        // Arrange
        $this->trie->insert('bonjour', 'french');
        $this->trie->insert('hello', 'english');
        $this->trie->insert('merci', 'french');
        $this->trie->insert('thank_you', 'english');

        // Act
        $frenchResults = $this->trie->search('bon', 'french');
        $englishResults = $this->trie->search('hel', 'english');

        // Assert
        $this->assertCount(1, $frenchResults);
        $this->assertSame('bonjour', $frenchResults->first()->word);
        $this->assertSame('french', $frenchResults->first()->context);

        $this->assertCount(1, $englishResults);
        $this->assertSame('hello', $englishResults->first()->word);
        $this->assertSame('english', $englishResults->first()->context);
    }

    public function test_insert_batch_with_context(): void
    {
        // Arrange
        $collection = new TrieCollection;
        $collection->add(new TrieRecord('bonjour', 'french'));
        $collection->add(new TrieRecord('hello', 'english'));
        $collection->add(new TrieRecord('merci', 'french'));
        $collection->add(new TrieRecord('thank_you', 'english'));

        // Act
        $this->trie->insertBatch($collection);

        // Assert
        $results = $this->trie->search('bon', 'french');
        $this->assertCount(1, $results);
        $this->assertSame('bonjour', $results->first()->word);

        $results = $this->trie->search('hel', 'english');
        $this->assertCount(1, $results);
        $this->assertSame('hello', $results->first()->word);
    }

    public function test_search_batch_with_context(): void
    {
        // Arrange
        $words = ['laravel', 'python', 'php', 'javascript'];
        foreach ($words as $word) {
            $this->trie->insert($word);
        }

        $collection = new TrieCollection;
        $collection->add(new TrieRecord('la'));
        $collection->add(new TrieRecord('py'));
        $collection->add(new TrieRecord('ja'));

        // Act
        $results = $this->trie->searchBatch($collection, 2);

        // Assert
        $this->assertArrayHasKey('la', $results);
        $this->assertArrayHasKey('py', $results);
        $this->assertArrayHasKey('ja', $results);

        $this->assertInstanceOf(TrieResultCollection::class, $results['la']);
        $this->assertInstanceOf(TrieResultCollection::class, $results['py']);
        $this->assertInstanceOf(TrieResultCollection::class, $results['ja']);
    }

    public function test_search_with_limit(): void
    {
        // Arrange
        $words = ['php', 'python', 'perl', 'pascal', 'puppet'];

        foreach ($words as $word) {
            $this->trie->insert($word);
        }

        // Act
        $results = $this->trie->search('p', null, 2);

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(2, $results);
    }

    public function test_search_empty_prefix(): void
    {
        // Act
        $results = $this->trie->search('');

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_search_non_existent_prefix(): void
    {
        // Arrange
        $this->trie->insert('laravel');

        // Act
        $results = $this->trie->search('xyz');

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_duplicate_insert(): void
    {
        // Arrange
        $this->trie->insert('laravel');

        // Act
        $this->trie->insert('laravel');

        $results = $this->trie->search('lar');

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(1, $results);
        $this->assertSame('laravel', $results->first()->word);
    }

    public function test_clear(): void
    {
        // Arrange
        $this->trie->insert('laravel');

        // Act
        $this->trie->clear();
        $results = $this->trie->search('lar');

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_persistence(): void
    {
        // Arrange
        $storage = new MemoryStorage;
        $key = 'persistent_trie';

        $trie1 = new Trie($storage, $key);
        $trie1->insert('laravel');
        $trie1->insert('php');

        // Act
        $trie2 = new Trie($storage, $key);
        $results = $trie2->search('l');

        // Assert
        $this->assertInstanceOf(TrieResultCollection::class, $results);
        $this->assertCount(1, $results);
        $this->assertSame('laravel', $results->first()->word);

        $storage->clear();
    }

    public function test_clear_with_context(): void
    {
        // Arrange
        $this->trie->insert('bonjour', 'french');
        $this->trie->insert('hello', 'english');

        // Act
        $this->trie->clear();

        // Assert
        $frenchResults = $this->trie->search('bon', 'french');
        $englishResults = $this->trie->search('hel', 'english');

        $this->assertEmpty($frenchResults);
        $this->assertEmpty($englishResults);
    }
}

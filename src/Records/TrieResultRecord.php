<?php

namespace AndyDefer\AlgoKIT\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

class TrieResultRecord extends AbstractRecord
{
    public function __construct(
        public string $word,
        public ?string $context = null
    ) {}
}

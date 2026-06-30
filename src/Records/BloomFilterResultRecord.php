<?php

namespace AndyDefer\AlgoKIT\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

class BloomFilterResultRecord extends AbstractRecord
{
    public function __construct(
        public string $value,
        public bool $exists,
        public ?string $context = null
    ) {}
}

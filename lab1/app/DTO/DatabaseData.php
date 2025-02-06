<?php

namespace App\DTO;

class DatabaseData
{
    public function __construct(
        public readonly string $driver,
        public readonly string $dbpath
    ) {}
}
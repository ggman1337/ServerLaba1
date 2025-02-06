<?php

namespace App\DTO;

class ServerData
{
    public function __construct(
        public readonly string $phpinfo
    ) {}
}
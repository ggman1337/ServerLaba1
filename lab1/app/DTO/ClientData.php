<?php

namespace App\DTO;

class ClientData
{
    public function __construct(
        public readonly string $ip,
        public readonly string $userAgent
    ) {}
}
<?php

namespace App\Services\Payloads;

class PayloadBuildResult
{
    public function __construct(
        private readonly array $attributes,
        private readonly array $payload,
    ) {
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}

<?php

namespace App\Contracts;

interface ErrorEnumInterface
{
    public function message(): string;
    public function httpCode(): int;
}

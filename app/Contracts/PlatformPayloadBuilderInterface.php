<?php

namespace App\Contracts;

use App\Services\Payloads\PayloadBuildResult;

interface PlatformPayloadBuilderInterface
{
    public function build(array $input): PayloadBuildResult;
}

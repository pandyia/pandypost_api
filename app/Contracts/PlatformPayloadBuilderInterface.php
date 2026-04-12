<?php

namespace App\Contracts;

use App\Services\Payloads\PayloadBuildResult;
use Illuminate\Http\UploadedFile;

interface PlatformPayloadBuilderInterface
{
    public function build(array $input, ?UploadedFile $thumbnail = null): PayloadBuildResult;
}

<?php

namespace App\Exceptions;

use BackedEnum;
use Exception;
use Illuminate\Http\JsonResponse;

abstract class BaseException extends Exception
{
    protected string $errorCode;
    protected array $context = [];

    public function __construct(string $message, string|BackedEnum $errorCode, int $httpCode = 400)
    {
        parent::__construct($message, $httpCode);
        $this->errorCode = $errorCode instanceof BackedEnum ? $errorCode->value : $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpCode(): int
    {
        return $this->getCode();
    }

    public function withContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        $response = [
            'error' => $this->getErrorCode(),
            'message' => $this->getMessage(),
        ];

        if (!empty($this->context) && config('app.debug')) {
            $response['context'] = $this->context;
        }

        return response()->json($response, $this->getHttpCode());
    }
}

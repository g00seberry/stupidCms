<?php

declare(strict_types=1);

namespace App\Support\Errors;

final class ErrorType
{
    public function __construct(
        public readonly ErrorCode $code,
        public readonly string $uri,
        public readonly string $title,
        public readonly int $status,
        public readonly string $defaultDetail,
    ) {
        $this->assertUri($uri);
        $this->assertStatus($status);
        $this->assertNotEmpty($title, 'title');
        $this->assertNotEmpty($defaultDetail, 'defaultDetail');
    }

    private function assertUri(string $uri): void
    {
        if (trim($uri) === '') {
            throw new \InvalidArgumentException('Error type URI cannot be empty.');
        }
    }

    private function assertStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException(sprintf(
                'Status code must be a valid HTTP status (100-599). %d given.',
                $status,
            ));
        }
    }

    private function assertNotEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Field "%s" cannot be empty.', $field));
        }
    }
}


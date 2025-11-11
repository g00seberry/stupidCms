<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateErrorsDoc extends Command
{
    protected $signature = 'docs:errors';
    protected $description = 'Generate errors documentation (RFC7807)';

    public function handle(): int
    {
        $this->info('Generating errors documentation...');

        $errors = $this->scanErrors();

        // JSON
        $jsonPath = base_path('docs/_generated/errors.json');
        file_put_contents($jsonPath, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✓ Generated: docs/_generated/errors.json");

        // Markdown
        $mdPath = base_path('docs/_generated/errors.md');
        $markdown = $this->generateMarkdown($errors);
        file_put_contents($mdPath, $markdown);
        $this->info("✓ Generated: docs/_generated/errors.md");

        return self::SUCCESS;
    }

    private function scanErrors(): array
    {
        // Определяем стандартные коды ошибок
        $errors = [
            [
                'status' => 400,
                'code' => 'BAD_REQUEST',
                'title' => 'Bad Request',
                'description' => 'The request could not be understood or was missing required parameters.',
                'example' => $this->example(
                    slug: 'bad-request',
                    status: 400,
                    title: 'Bad Request',
                    code: 'BAD_REQUEST',
                    detail: 'Invalid JSON syntax',
                    meta: [
                        'request_id' => '11111111-1111-4111-8111-111111111111',
                        'field' => 'payload',
                    ],
                    traceId: '00-11111111111141118111111111111111-1111111111114111-01'
                ),
            ],
            [
                'status' => 401,
                'code' => 'UNAUTHORIZED',
                'title' => 'Unauthorized',
                'description' => 'Authentication failed or was not provided.',
                'example' => $this->example(
                    slug: 'unauthorized',
                    status: 401,
                    title: 'Unauthorized',
                    code: 'UNAUTHORIZED',
                    detail: 'Invalid or expired token',
                    meta: [
                        'request_id' => '22222222-2222-4222-8222-222222222222',
                        'reason' => 'invalid_token',
                    ],
                    traceId: '00-22222222222242228222222222222222-2222222222224222-01'
                ),
            ],
            [
                'status' => 403,
                'code' => 'FORBIDDEN',
                'title' => 'Forbidden',
                'description' => 'You don\'t have permission to access this resource.',
                'example' => $this->example(
                    slug: 'forbidden',
                    status: 403,
                    title: 'Forbidden',
                    code: 'FORBIDDEN',
                    detail: 'Insufficient permissions to update this entry',
                    meta: [
                        'request_id' => '33333333-3333-4333-8333-333333333333',
                        'permission' => 'entries.update',
                    ],
                    traceId: '00-33333333333343338333333333333333-3333333333334333-01'
                ),
            ],
            [
                'status' => 404,
                'code' => 'NOT_FOUND',
                'title' => 'Not Found',
                'description' => 'The requested resource was not found.',
                'example' => $this->example(
                    slug: 'not-found',
                    status: 404,
                    title: 'Not Found',
                    code: 'NOT_FOUND',
                    detail: 'Entry with slug "non-existent" not found',
                    meta: [
                        'request_id' => '44444444-4444-4444-8444-444444444444',
                        'entry_slug' => 'non-existent',
                    ],
                    traceId: '00-44444444444444448444444444444444-4444444444444444-01'
                ),
            ],
            [
                'status' => 422,
                'code' => 'VALIDATION_ERROR',
                'title' => 'Validation Error',
                'description' => 'The request data failed validation.',
                'example' => $this->example(
                    slug: 'validation-error',
                    status: 422,
                    title: 'Validation Error',
                    code: 'VALIDATION_ERROR',
                    detail: 'The given data was invalid.',
                    meta: [
                        'request_id' => '55555555-5555-4555-8555-555555555555',
                        'errors' => [
                            'title' => ['The title field is required.'],
                            'slug' => ['The slug has already been taken.'],
                        ],
                    ],
                    traceId: '00-55555555555545558555555555555555-5555555555554555-01'
                ),
            ],
            [
                'status' => 429,
                'code' => 'RATE_LIMIT_EXCEEDED',
                'title' => 'Too Many Requests',
                'description' => 'Rate limit exceeded. Please try again later.',
                'example' => $this->example(
                    slug: 'rate-limit-exceeded',
                    status: 429,
                    title: 'Too Many Requests',
                    code: 'RATE_LIMIT_EXCEEDED',
                    detail: 'Rate limit of 60 requests per minute exceeded',
                    meta: [
                        'request_id' => '66666666-6666-4666-8666-666666666666',
                        'retry_after' => 60,
                    ],
                    traceId: '00-66666666666646668666666666666666-6666666666664666-01'
                ),
            ],
            [
                'status' => 500,
                'code' => 'INTERNAL_SERVER_ERROR',
                'title' => 'Internal Server Error',
                'description' => 'An unexpected error occurred on the server.',
                'example' => $this->example(
                    slug: 'internal-server-error',
                    status: 500,
                    title: 'Internal Server Error',
                    code: 'INTERNAL_SERVER_ERROR',
                    detail: 'An unexpected error occurred. Please try again later.',
                    meta: [
                        'request_id' => '77777777-7777-4777-8777-777777777777',
                    ],
                    traceId: '00-77777777777747778777777777777777-7777777777774777-01'
                ),
            ],
        ];

        return $errors;
    }

    private function generateMarkdown(array $errors): string
    {
        $md = "# Error Codes (RFC7807)\n\n";
        $md .= "> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:errors` to update.\n\n";
        $md .= "_Last generated: " . now()->toDateTimeString() . "_\n\n";

        $md .= "stupidCms API follows [RFC7807 Problem Details](https://tools.ietf.org/html/rfc7807) for error responses.\n\n";

        $md .= "## Error Response Format\n\n";
        $md .= "```json\n";
        $md .= "{\n";
        $md .= "  \"type\": \"https://stupidcms.dev/problems/validation-error\",\n";
        $md .= "  \"title\": \"Validation Error\",\n";
        $md .= "  \"status\": 422,\n";
        $md .= "  \"code\": \"VALIDATION_ERROR\",\n";
        $md .= "  \"detail\": \"The given data was invalid.\",\n";
        $md .= "  \"meta\": {\n";
        $md .= "    \"request_id\": \"aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee\",\n";
        $md .= "    \"errors\": {\n";
        $md .= "      \"field_name\": [\"Error message\"]\n";
        $md .= "    }\n";
        $md .= "  },\n";
        $md .= "  \"trace_id\": \"00-aaaaaaaa4ccc8dddeeeeeeeeeeee-aaaaaaaa4ccc8ddd-01\"\n";
        $md .= "}\n";
        $md .= "```\n\n";

        $md .= "## Standard Error Codes\n\n";
        $md .= "| Status | Code | Title | Description |\n";
        $md .= "|--------|------|-------|-------------|\n";

        foreach ($errors as $error) {
            $md .= sprintf(
                "| %d | `%s` | %s | %s |\n",
                $error['status'],
                $error['code'],
                $error['title'],
                $error['description']
            );
        }

        $md .= "\n## Examples\n\n";

        foreach ($errors as $error) {
            $md .= "### {$error['status']} {$error['title']}\n\n";
            $md .= "**Code**: `{$error['code']}`\n\n";
            $md .= "**Description**: {$error['description']}\n\n";
            $md .= "**Example Response**:\n\n";
            $md .= "```json\n";
            $md .= json_encode($error['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            $md .= "```\n\n";
        }

        return $md;
    }

    private function example(
        string $slug,
        int $status,
        string $title,
        string $code,
        string $detail,
        array $meta = [],
        ?string $traceId = null,
    ): array {
        return [
            'type' => sprintf('https://stupidcms.dev/problems/%s', $slug),
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            'meta' => $meta,
            'trace_id' => $traceId ?? '00-00000000000000000000000000000000-0000000000000000-01',
        ];
    }
}


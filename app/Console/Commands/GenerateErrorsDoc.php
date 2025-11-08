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
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/bad-request',
                    'title' => 'Bad Request',
                    'status' => 400,
                    'detail' => 'Invalid JSON syntax',
                ],
            ],
            [
                'status' => 401,
                'code' => 'UNAUTHORIZED',
                'title' => 'Unauthorized',
                'description' => 'Authentication failed or was not provided.',
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/unauthorized',
                    'title' => 'Unauthorized',
                    'status' => 401,
                    'detail' => 'Invalid or expired token',
                ],
            ],
            [
                'status' => 403,
                'code' => 'FORBIDDEN',
                'title' => 'Forbidden',
                'description' => 'You don\'t have permission to access this resource.',
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/forbidden',
                    'title' => 'Forbidden',
                    'status' => 403,
                    'detail' => 'Insufficient permissions to update this entry',
                ],
            ],
            [
                'status' => 404,
                'code' => 'NOT_FOUND',
                'title' => 'Not Found',
                'description' => 'The requested resource was not found.',
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/not-found',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Entry with slug "non-existent" not found',
                ],
            ],
            [
                'status' => 422,
                'code' => 'VALIDATION_ERROR',
                'title' => 'Validation Error',
                'description' => 'The request data failed validation.',
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/validation',
                    'title' => 'Validation Error',
                    'status' => 422,
                    'detail' => 'The given data was invalid.',
                    'errors' => [
                        'title' => ['The title field is required.'],
                        'slug' => ['The slug has already been taken.'],
                    ],
                ],
            ],
            [
                'status' => 429,
                'code' => 'RATE_LIMIT_EXCEEDED',
                'title' => 'Too Many Requests',
                'description' => 'Rate limit exceeded. Please try again later.',
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/rate-limit',
                    'title' => 'Too Many Requests',
                    'status' => 429,
                    'detail' => 'Rate limit of 60 requests per minute exceeded',
                    'retry_after' => 60,
                ],
            ],
            [
                'status' => 500,
                'code' => 'INTERNAL_SERVER_ERROR',
                'title' => 'Internal Server Error',
                'description' => 'An unexpected error occurred on the server.',
                'example' => [
                    'type' => 'https://api.stupidcms.local/errors/internal',
                    'title' => 'Internal Server Error',
                    'status' => 500,
                    'detail' => 'An unexpected error occurred. Please try again later.',
                ],
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
        $md .= "  \"type\": \"https://api.stupidcms.local/errors/validation\",\n";
        $md .= "  \"title\": \"Validation Error\",\n";
        $md .= "  \"status\": 422,\n";
        $md .= "  \"detail\": \"The given data was invalid.\",\n";
        $md .= "  \"errors\": {\n";
        $md .= "    \"field_name\": [\"Error message\"]\n";
        $md .= "  }\n";
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
}


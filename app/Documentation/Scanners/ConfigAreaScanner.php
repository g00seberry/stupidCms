<?php

declare(strict_types=1);

namespace App\Documentation\Scanners;

use App\Documentation\Contracts\ScannerInterface;
use App\Documentation\DocEntity;
use App\Documentation\DocId;
use App\Documentation\ValueObjects\ConfigAreaMeta;
use Illuminate\Support\Facades\File;

final class ConfigAreaScanner implements ScannerInterface
{
    private const CONFIG_PATH = 'config';

    /**
     * @return array<DocEntity>
     */
    public function scan(): array
    {
        $configPath = base_path(self::CONFIG_PATH);
        if (! File::exists($configPath)) {
            return [];
        }

        $entities = [];
        $files = File::glob($configPath.'/*.php');

        foreach ($files as $file) {
            $entity = $this->scanConfigArea($file);
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function scanConfigArea(string $filePath): ?DocEntity
    {
        $content = File::get($filePath);
        if ($content === false) {
            return null;
        }

        $relativePath = str_replace(base_path().'/', '', $filePath);
        $fileName = basename($filePath, '.php');
        $name = ucfirst($fileName);

        // Извлекаем метаданные
        $meta = $this->extractMeta($content);

        // Извлекаем summary из комментариев в начале файла
        $summary = $this->extractSummary($content, $name);

        return new DocEntity(
            id: DocId::forConfigArea($fileName),
            type: 'config_area',
            name: $name,
            path: $relativePath,
            summary: $summary,
            details: null,
            meta: $meta->toArray(),
            related: [],
            tags: ['config', $fileName],
        );
    }

    private function extractMeta(string $content): ConfigAreaMeta
    {
        // Пытаемся извлечь ключи из массива конфига
        $keys = [];
        $sections = [];

        // Ищем return [ ... ];
        if (preg_match('/return\s+\[(.*?)\];/s', $content, $matches)) {
            $arrayContent = $matches[1];
            
            // Ищем ключи верхнего уровня (простой парсинг)
            if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/', $arrayContent, $keyMatches)) {
                $keys = array_unique($keyMatches[1]);
            }

            // Ищем секции (массивы как значения)
            if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*\[/', $arrayContent, $sectionMatches)) {
                $sections = array_unique($sectionMatches[1]);
            }
        }

        return new ConfigAreaMeta(
            keys: array_values($keys),
            sections: array_values($sections),
        );
    }

    private function extractSummary(string $content, string $name): string
    {
        // Ищем комментарии в начале файла
        $lines = explode("\n", $content);
        $summary = '';

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Пропускаем пустые строки и открывающий тег PHP
            if (empty($line) || $line === '<?php' || str_starts_with($line, 'declare')) {
                continue;
            }

            // Ищем комментарии
            if (str_starts_with($line, '//')) {
                $comment = substr($line, 2);
                $comment = trim($comment);
                if (! empty($comment)) {
                    $summary = $comment;
                    break;
                }
            } elseif (str_starts_with($line, '/*')) {
                // Блочный комментарий
                if (preg_match('/\/\*\*\s*(.+?)\s*\*\//s', $line, $matches)) {
                    $summary = trim($matches[1]);
                    break;
                }
            } else {
                // Дошли до кода, останавливаемся
                break;
            }
        }

        return $summary ?: "Configuration: {$name}";
    }
}


<?php

declare(strict_types=1);

namespace App\Documentation\Scanners;

use App\Documentation\Contracts\ScannerInterface;
use App\Documentation\DocEntity;
use App\Documentation\DocId;
use App\Documentation\ValueObjects\ModelMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

final class ModelScanner implements ScannerInterface
{
    private const MODELS_PATH = 'app/Models';

    /**
     * @return array<DocEntity>
     */
    public function scan(): array
    {
        $modelsPath = base_path(self::MODELS_PATH);
        if (! File::exists($modelsPath)) {
            return [];
        }

        $entities = [];
        $files = File::glob($modelsPath.'/*.php');

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className === null) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
                if (! $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                    continue;
                }

                $entity = $this->scanModel($reflection, $file);
                if ($entity !== null) {
                    $entities[] = $entity;
                }
            } catch (\Throwable $e) {
                // Пропускаем модели, которые не можем загрузить
                continue;
            }
        }

        return $entities;
    }

    private function scanModel(ReflectionClass $reflection, string $filePath): ?DocEntity
    {
        $fqcn = $reflection->getName();
        $name = $reflection->getShortName();
        $relativePath = str_replace(base_path().'/', '', $filePath);

        // Извлекаем метаданные
        $meta = $this->extractMeta($reflection);

        // Извлекаем summary и details из PHPDoc
        $docComment = $reflection->getDocComment();
        [$summary, $details] = $this->parseDocComment($docComment ?: '', $name);

        // Определяем теги
        $tags = $this->extractTags($reflection, $name);

        return new DocEntity(
            id: DocId::forModel($fqcn),
            type: 'model',
            name: $name,
            path: $relativePath,
            summary: $summary,
            details: $details,
            meta: $meta->toArray(),
            related: [],
            tags: $tags,
        );
    }

    private function extractMeta(ReflectionClass $reflection): ModelMeta
    {
        $instance = $this->createInstance($reflection);

        // Table
        $table = $instance->getTable();

        // Fillable и guarded
        $fillable = [];
        $guarded = [];
        try {
            if ($reflection->hasProperty('fillable')) {
                $fillableProp = $reflection->getProperty('fillable');
                $fillableProp->setAccessible(true);
                if ($fillableProp->isInitialized($instance)) {
                    $fillable = $fillableProp->getValue($instance) ?: [];
                }
            }
        } catch (\Throwable) {
            // Игнорируем
        }

        try {
            if ($reflection->hasProperty('guarded')) {
                $guardedProp = $reflection->getProperty('guarded');
                $guardedProp->setAccessible(true);
                if ($guardedProp->isInitialized($instance)) {
                    $guarded = $guardedProp->getValue($instance) ?: [];
                }
            }
        } catch (\Throwable) {
            // Игнорируем
        }

        // Casts
        $casts = [];
        try {
            if ($reflection->hasProperty('casts')) {
                $castsProp = $reflection->getProperty('casts');
                $castsProp->setAccessible(true);
                if ($castsProp->isInitialized($instance)) {
                    $casts = $castsProp->getValue($instance) ?: [];
                }
            }
        } catch (\Throwable) {
            // Игнорируем
        }

        // Relations
        $relations = $this->extractRelations($reflection);

        // Factory
        $factory = null;
        if ($reflection->hasMethod('newFactory')) {
            try {
                $factoryMethod = $reflection->getMethod('newFactory');
                $factoryReturnType = $factoryMethod->getReturnType();
                if ($factoryReturnType instanceof \ReflectionNamedType) {
                    $factory = $factoryReturnType->getName();
                }
            } catch (\Throwable) {
                // Игнорируем ошибки
            }
        }

        return new ModelMeta(
            table: $table,
            fillable: $fillable,
            guarded: $guarded,
            casts: $casts,
            relations: $relations,
            factory: $factory,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractRelations(ReflectionClass $reflection): array
    {
        $relations = [];
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->isStatic() || $method->getNumberOfParameters() > 0) {
                continue;
            }

            // Пропускаем scope-методы
            if (str_starts_with($method->getName(), 'scope')) {
                continue;
            }

            // Проверяем тело метода на наличие relation-методов
            $methodBody = $this->getMethodBody($method);
            if ($methodBody === null) {
                continue;
            }

            $relationType = $this->detectRelationType($methodBody);
            if ($relationType === null) {
                continue;
            }

            $relatedModel = $this->extractRelatedModel($methodBody, $reflection);
            $relations[$method->getName()] = [
                'type' => $relationType,
                'related' => $relatedModel,
            ];
        }

        return $relations;
    }

    private function detectRelationType(string $methodBody): ?string
    {
        if (preg_match('/->(belongsTo|hasOne|hasMany|belongsToMany|hasManyThrough|morphTo|morphOne|morphMany|morphToMany)\(/', $methodBody, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractRelatedModel(string $methodBody, ReflectionClass $reflection): ?string
    {
        // Ищем ClassName::class в методе
        if (preg_match('/([A-Z][a-zA-Z0-9_\\\\]+)::class/', $methodBody, $matches)) {
            $className = $matches[1];
            // Если относительный класс, делаем его полным
            if (! str_contains($className, '\\')) {
                // Проверяем use statements в файле класса
                $fileContent = file_get_contents($reflection->getFileName());
                if ($fileContent !== false) {
                    // Ищем use statement для этого класса
                    if (preg_match('/use\s+([^;]+\\\\'.$className.');/', $fileContent, $useMatch)) {
                        $className = $useMatch[1];
                    } else {
                        // Используем namespace модели
                        $namespace = $reflection->getNamespaceName();
                        $className = $namespace.'\\'.$className;
                    }
                } else {
                    $namespace = $reflection->getNamespaceName();
                    $className = $namespace.'\\'.$className;
                }
            }

            return $className;
        }

        return null;
    }

    private function getMethodBody(ReflectionMethod $method): ?string
    {
        $filename = $method->getFileName();
        if ($filename === false) {
            return null;
        }

        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($filename);
        if ($lines === false) {
            return null;
        }

        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        return $body;
    }

    /**
     * @return array{string, string|null}
     */
    private function parseDocComment(string $docComment, string $className): array
    {
        if (empty($docComment)) {
            return [$className.' model', null];
        }

        // Убираем /** и */
        $docComment = preg_replace('/^\/\*\*|\*\/$/', '', $docComment);
        $lines = explode("\n", $docComment);

        $summary = '';
        $details = [];

        $inDetails = false;
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\*\s*/', '', $line);

            if (empty($line)) {
                if (! empty($summary)) {
                    $inDetails = true;
                }
                continue;
            }

            // Пропускаем аннотации
            if (str_starts_with($line, '@')) {
                continue;
            }

            if (empty($summary)) {
                $summary = $line;
            } elseif ($inDetails) {
                $details[] = $line;
            }
        }

        return [
            $summary ?: $className.' model',
            ! empty($details) ? implode("\n", $details) : null,
        ];
    }

    /**
     * @return array<string>
     */
    private function extractTags(ReflectionClass $reflection, string $name): array
    {
        $tags = [];

        // Базовый тег из имени модели
        $tags[] = strtolower($name);

        // Теги из PHPDoc @tags
        $docComment = $reflection->getDocComment();
        if ($docComment && preg_match('/@tags\s+(.+)/', $docComment, $matches)) {
            $docTags = array_map('trim', explode(',', $matches[1]));
            $tags = array_merge($tags, $docTags);
        }

        return array_unique($tags);
    }

    private function createInstance(ReflectionClass $reflection): object
    {
        // Пытаемся создать экземпляр без конструктора
        if ($reflection->isAbstract()) {
            throw new \RuntimeException('Cannot create instance of abstract class');
        }

        if (! $reflection->isInstantiable()) {
            throw new \RuntimeException('Class is not instantiable');
        }

        // Для моделей Eloquent можно использовать newInstanceWithoutConstructor
        return $reflection->newInstanceWithoutConstructor();
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = File::get($filePath);
        if ($content === false) {
            return null;
        }

        // Ищем namespace
        if (! preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        $namespace = $namespaceMatch[1];

        // Ищем имя класса
        if (! preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        $className = $classMatch[1];

        return $namespace.'\\'.$className;
    }
}


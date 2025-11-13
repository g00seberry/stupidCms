<?php

declare(strict_types=1);

namespace App\Documentation\Scanners;

use App\Documentation\Contracts\ScannerInterface;
use App\Documentation\DocEntity;
use App\Documentation\DocId;
use App\Documentation\ValueObjects\DomainServiceMeta;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class DomainServiceScanner implements ScannerInterface
{
    private const DOMAIN_PATH = 'app/Domain';

    /**
     * @return array<DocEntity>
     */
    public function scan(): array
    {
        $domainPath = base_path(self::DOMAIN_PATH);
        if (! File::exists($domainPath)) {
            return [];
        }

        $entities = [];
        $files = File::allFiles($domainPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file->getPathname());
            if ($className === null) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
                
                // Пропускаем интерфейсы, трейты, абстрактные классы
                if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isAbstract()) {
                    continue;
                }

                // Пропускаем исключения
                if ($reflection->isSubclassOf(\Throwable::class)) {
                    continue;
                }

                $entity = $this->scanDomainService($reflection, $file->getPathname());
                if ($entity !== null) {
                    $entities[] = $entity;
                }
            } catch (\Throwable $e) {
                // Пропускаем классы, которые не можем загрузить
                continue;
            }
        }

        return $entities;
    }

    private function scanDomainService(ReflectionClass $reflection, string $filePath): ?DocEntity
    {
        $fqcn = $reflection->getName();
        $name = $reflection->getShortName();
        $relativePath = str_replace(base_path().'/', '', $filePath);

        // Извлекаем метаданные
        $meta = $this->extractMeta($reflection);

        // Извлекаем summary и details из PHPDoc
        $docComment = $reflection->getDocComment();
        [$summary, $details] = $this->parseDocComment($docComment ?: '', $name);

        // Определяем теги из namespace
        $tags = $this->extractTags($reflection);

        // Генерируем ID
        $namespacePath = str_replace('App\\Domain\\', '', $fqcn);
        $namespacePath = str_replace('\\', '/', $namespacePath);

        return new DocEntity(
            id: DocId::forDomainService($namespacePath),
            type: 'domain_service',
            name: $name,
            path: $relativePath,
            summary: $summary,
            details: $details,
            meta: $meta->toArray(),
            related: [],
            tags: $tags,
        );
    }

    private function extractMeta(ReflectionClass $reflection): DomainServiceMeta
    {
        // Публичные методы
        $methods = $this->extractMethods($reflection);

        // Зависимости из конструктора
        $dependencies = $this->extractDependencies($reflection);

        // Реализуемые интерфейсы
        $interface = $this->extractInterface($reflection);

        return new DomainServiceMeta(
            methods: $methods,
            dependencies: $dependencies,
            interface: $interface,
        );
    }

    /**
     * @return array<string>
     */
    private function extractMethods(ReflectionClass $reflection): array
    {
        $methods = [];
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            // Пропускаем магические методы
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Пропускаем методы из родительских классов (если не переопределены)
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $methods[] = $method->getName();
        }

        return $methods;
    }

    /**
     * @return array<string>
     */
    private function extractDependencies(ReflectionClass $reflection): array
    {
        $dependencies = [];

        if (! $reflection->hasMethod('__construct')) {
            return $dependencies;
        }

        $constructor = $reflection->getMethod('__construct');
        $parameters = $constructor->getParameters();

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $dependencies[] = $type->getName();
            }
        }

        return $dependencies;
    }

    private function extractInterface(ReflectionClass $reflection): ?string
    {
        $interfaces = $reflection->getInterfaceNames();
        
        // Возвращаем первый интерфейс (обычно их один)
        return ! empty($interfaces) ? $interfaces[0] : null;
    }

    /**
     * @return array{string, string|null}
     */
    private function parseDocComment(string $docComment, string $className): array
    {
        if (empty($docComment)) {
            return [$className, null];
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
            $summary ?: $className,
            ! empty($details) ? implode("\n", $details) : null,
        ];
    }

    /**
     * @return array<string>
     */
    private function extractTags(ReflectionClass $reflection): array
    {
        $tags = [];
        $namespace = $reflection->getNamespaceName();

        // Извлекаем теги из namespace (например, App\Domain\Entries -> entry)
        $namespaceParts = explode('\\', $namespace);
        foreach ($namespaceParts as $part) {
            if ($part === 'App' || $part === 'Domain') {
                continue;
            }
            $tag = strtolower($part);
            // Убираем множественное число
            if (str_ends_with($tag, 'ies')) {
                $tag = substr($tag, 0, -3).'y';
            } elseif (str_ends_with($tag, 's')) {
                $tag = substr($tag, 0, -1);
            }
            $tags[] = $tag;
        }

        // Теги из PHPDoc @tags
        $docComment = $reflection->getDocComment();
        if ($docComment && preg_match('/@tags\s+(.+)/', $docComment, $matches)) {
            $docTags = array_map('trim', explode(',', $matches[1]));
            $tags = array_merge($tags, $docTags);
        }

        return array_unique($tags);
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


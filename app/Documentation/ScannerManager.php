<?php

declare(strict_types=1);

namespace App\Documentation;

use App\Documentation\Contracts\ScannerInterface;
use App\Documentation\Scanners\BladeViewScanner;
use App\Documentation\Scanners\ConfigAreaScanner;
use App\Documentation\Scanners\DomainServiceScanner;
use App\Documentation\Scanners\HttpEndpointScanner;
use App\Documentation\Scanners\ModelScanner;

final class ScannerManager
{
    /**
     * @var array<string, ScannerInterface>
     */
    private array $scanners = [];

    public function __construct()
    {
        $this->registerDefaultScanners();
    }

    private function registerDefaultScanners(): void
    {
        $this->register('model', new ModelScanner());
        $this->register('domain_service', new DomainServiceScanner());
        $this->register('blade_view', new BladeViewScanner());
        $this->register('config_area', new ConfigAreaScanner());
        $this->register('http_endpoint', new HttpEndpointScanner());
    }

    public function register(string $type, ScannerInterface $scanner): void
    {
        $this->scanners[$type] = $scanner;
    }

    /**
     * Запускает все зарегистрированные сканеры.
     *
     * @return array<DocEntity>
     */
    public function scanAll(): array
    {
        $entities = [];

        foreach ($this->scanners as $type => $scanner) {
            try {
                $scanned = $scanner->scan();
                $entities = array_merge($entities, $scanned);
            } catch (\Throwable $e) {
                // Логируем ошибку, но продолжаем работу
                report($e);
            }
        }

        return $entities;
    }

    /**
     * Запускает сканер для конкретного типа.
     *
     * @return array<DocEntity>
     */
    public function scanType(string $type): array
    {
        if (! isset($this->scanners[$type])) {
            return [];
        }

        try {
            return $this->scanners[$type]->scan();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->scanners);
    }
}


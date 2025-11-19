<?php

declare(strict_types=1);

/**
 * Модульная конфигурация тестов для Blueprint системы
 * 
 * Группирует тесты для:
 * - Моделей Blueprint, Path, DocValue, DocRef
 * - API Blueprints, Paths, Components
 * - Трейта HasDocumentData
 * - Observers (BlueprintObserver, PathObserver)
 * - Интеграционных тестов
 */

uses()
    ->group('blueprints')
    ->in(__DIR__ . '/../Unit/Models/BlueprintTest.php')
    ->in(__DIR__ . '/../Unit/Models/PathTest.php')
    ->in(__DIR__ . '/../Unit/Models/DocValueTest.php')
    ->in(__DIR__ . '/../Unit/Models/DocRefTest.php')
    ->in(__DIR__ . '/../Unit/Traits/HasDocumentDataTest.php')
    ->in(__DIR__ . '/../Unit/Observers/BlueprintObserverTest.php')
    ->in(__DIR__ . '/../Unit/Observers/PathObserverTest.php')
    ->in(__DIR__ . '/../Feature/Api/Blueprints')
    ->in(__DIR__ . '/../Feature/Blueprints');

// Дополнительные группы для фильтрации
uses()
    ->group('blueprints:models')
    ->in(__DIR__ . '/../Unit/Models/BlueprintTest.php')
    ->in(__DIR__ . '/../Unit/Models/PathTest.php')
    ->in(__DIR__ . '/../Unit/Models/DocValueTest.php')
    ->in(__DIR__ . '/../Unit/Models/DocRefTest.php');

uses()
    ->group('blueprints:api')
    ->in(__DIR__ . '/../Feature/Api/Blueprints');

uses()
    ->group('blueprints:integration')
    ->in(__DIR__ . '/../Feature/Blueprints');

uses()
    ->group('blueprints:observers')
    ->in(__DIR__ . '/../Unit/Observers/BlueprintObserverTest.php')
    ->in(__DIR__ . '/../Unit/Observers/PathObserverTest.php');

uses()
    ->group('blueprints:trait')
    ->in(__DIR__ . '/../Unit/Traits/HasDocumentDataTest.php');


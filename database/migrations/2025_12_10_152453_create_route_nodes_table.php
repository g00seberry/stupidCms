<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы route_nodes.
 *
 * Хранит иерархическое дерево маршрутов для DB-driven роутинга.
 * Поддерживает группы маршрутов и конкретные маршруты с различными типами действий.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('route_nodes', function (Blueprint $table): void {
            $table->id();
            
            // Self-relation для иерархии
            $table->foreignId('parent_id')->nullable()
                ->constrained('route_nodes')->restrictOnDelete();
            
            // Сортировка и состояние
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true)->index();
            
            // Тип узла
            $table->enum('kind', ['group', 'route'])->index();
            
            // Настройки маршрута/группы
            $table->string('name')->nullable(); // Имя маршрута (Route::name())
            $table->string('domain')->nullable(); // Домен для маршрута
            $table->string('prefix')->nullable(); // Префикс URI для группы
            $table->string('namespace')->nullable(); // Namespace контроллеров для группы
            
            // HTTP методы и URI (только для kind='route')
            $table->json('methods')->nullable(); // ['GET', 'POST', ...]
            $table->string('uri')->nullable(); // URI паттерн маршрута
            
            // Тип действия и само действие
            $table->enum('action_type', ['controller', 'entry'])->index();
            $table->string('action')->nullable(); // Controller@method, view:..., redirect:...
            
            // Связь с Entry (для action_type='entry')
            $table->foreignId('entry_id')->nullable()
                ->constrained('entries')->nullOnDelete()->index();
            
            // Дополнительные настройки
            $table->json('middleware')->nullable(); // ['web', 'auth', ...]
            $table->json('where')->nullable(); // {"id": "[0-9]+", ...}
            $table->json('defaults')->nullable(); // {"key": "value", ...}
            $table->json('options')->nullable(); // {"require_published": false, ...}
            
            $table->timestamps();
            $table->softDeletes();
            
            // Составной индекс для эффективной загрузки дерева с сортировкой
            $table->index(['parent_id', 'sort_order'], 'route_nodes_parent_sort_idx');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_nodes');
    }
};

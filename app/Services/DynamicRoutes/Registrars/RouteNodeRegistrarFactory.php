<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Registrars;

use App\Enums\RouteNodeKind;
use App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory;
use App\Services\DynamicRoutes\Validators\DynamicRouteValidator;
use Illuminate\Support\Facades\Log;

/**
 * Фабрика для создания регистраторов узлов маршрутов.
 *
 * Выбирает правильный регистратор на основе типа узла (kind).
 * Поддерживает регистрацию регистраторов для разных типов узлов.
 *
 * @package App\Services\DynamicRoutes\Registrars
 */
class RouteNodeRegistrarFactory
{
    /**
     * Зарегистрированные регистраторы.
     *
     * Массив, где ключ - значение RouteNodeKind (строка), значение - экземпляр регистратора.
     *
     * @var array<string, RouteNodeRegistrarInterface>
     */
    private array $registrars = [];

    /**
     * Создать фабрику с предустановленными регистраторами.
     *
     * Регистрирует регистраторы по умолчанию:
     * - RouteNodeKind::GROUP → RouteGroupRegistrar
     * - RouteNodeKind::ROUTE → RouteRouteRegistrar
     *
     * @param \App\Services\DynamicRoutes\Validators\DynamicRouteValidator $guard Guard для проверки безопасности
     * @param \App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory|null $actionResolverFactory Фабрика для разрешения действий
     * @return self
     */
    public static function createDefault(
        DynamicRouteValidator $guard,
        ?ActionResolverFactory $actionResolverFactory = null
    ): self {
        $factory = new self();
        
        // Создаём регистраторы с рекурсивной ссылкой на фабрику
        $factory->register(
            RouteNodeKind::GROUP,
            new RouteGroupRegistrar($guard, $factory)
        );
        
        $factory->register(
            RouteNodeKind::ROUTE,
            new RouteRouteRegistrar($guard, $factory, $actionResolverFactory)
        );

        return $factory;
    }

    /**
     * Зарегистрировать регистратор для типа узла.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @param \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarInterface $registrar Регистратор
     * @return void
     */
    public function register(RouteNodeKind $kind, RouteNodeRegistrarInterface $registrar): void
    {
        $this->registrars[$kind->value] = $registrar;
    }

    /**
     * Создать регистратор для указанного типа узла.
     *
     * Возвращает зарегистрированный регистратор для указанного kind.
     * Если регистратор не найден, логирует предупреждение и возвращает null.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarInterface|null Регистратор или null, если не найден
     */
    public function create(RouteNodeKind $kind): ?RouteNodeRegistrarInterface
    {
        if (!isset($this->registrars[$kind->value])) {
            Log::warning('RouteNodeRegistrarFactory: регистратор не найден для типа узла', [
                'kind' => $kind->value,
                'available_kinds' => array_keys($this->registrars),
            ]);
            return null;
        }

        return $this->registrars[$kind->value];
    }
}


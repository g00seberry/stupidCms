<?php

declare(strict_types=1);

namespace App\Domain\Options;

use App\Events\OptionChanged;
use App\Models\Option;
use Illuminate\Contracts\Cache\Repository as CacheRepo;
use Illuminate\Support\Facades\DB;

/**
 * Репозиторий для работы с опциями системы.
 *
 * Предоставляет доступ к опциям с кэшированием и поддержкой пространств имён.
 * Поддерживает мягкое удаление и восстановление опций.
 *
 * @package App\Domain\Options
 */
final class OptionsRepository
{
    /**
     * @param \Illuminate\Contracts\Cache\Repository $cache Кэш для хранения опций
     */
    public function __construct(private CacheRepo $cache) {}

    /**
     * Сформировать ключ кэша для опции.
     *
     * @param string $ns Пространство имён
     * @param string $key Ключ опции
     * @return string Ключ кэша
     */
    private function cacheKey(string $ns, string $key): string
    {
        return "opt:{$ns}:{$key}";
    }

    /**
     * Получить значение опции.
     *
     * Использует кэш с тегами (если поддерживается) для группировки по namespace.
     * При отсутствии в кэше загружает из БД.
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @param mixed $default Значение по умолчанию, если опция не найдена
     * @return mixed Значение опции или значение по умолчанию
     */
    public function get(string $ns, string $key, mixed $default = null): mixed
    {
        $ck = $this->cacheKey($ns, $key);

        if ($this->supportsTags()) {
            return $this->cache->tags(['options', "options:{$ns}"])
                ->rememberForever($ck, fn () => $this->fetchFromDatabase($ns, $key, $default));
        }

        return $this->cache->rememberForever($ck, fn () => $this->fetchFromDatabase($ns, $key, $default));
    }

    /**
     * Сохранить JSON-значение опции (примитив/массив/объект).
     *
     * Создаёт новую опцию или обновляет существующую (включая восстановление мягко удалённой).
     * Очищает кэш и диспатчит событие OptionChanged после коммита транзакции.
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @param mixed $value Значение опции (любой JSON-совместимый тип)
     * @param string|null $description Описание опции
     * @return \App\Models\Option Сохранённая опция
     */
    public function set(string $ns, string $key, mixed $value, ?string $description = null): Option
    {
        return DB::transaction(function () use ($ns, $key, $value, $description) {
            $option = Option::withTrashed()
                ->where('namespace', $ns)
                ->where('key', $key)
                ->first();

            $oldValue = $option?->value_json;

            if (! $option) {
                $option = new Option([
                    'namespace' => $ns,
                    'key' => $key,
                ]);
            }

            $option->value_json = $value;
            $option->description = $description;
            $option->deleted_at = null;
            $option->save();

            $this->flushCache($ns, $key);

            DB::afterCommit(static function () use ($ns, $key, $value, $oldValue) {
                event(new OptionChanged($ns, $key, $value, $oldValue));
            });

            return $option;
        });
    }

    /**
     * Удалить опцию (мягкое удаление).
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @return bool true, если опция была удалена; false, если не найдена
     */
    public function delete(string $ns, string $key): bool
    {
        return DB::transaction(function () use ($ns, $key) {
            $option = Option::query()
                ->where('namespace', $ns)
                ->where('key', $key)
                ->first();

            if (! $option) {
                return false;
            }

            $option->delete();
            $this->flushCache($ns, $key);

            return true;
        });
    }

    /**
     * Восстановить мягко удалённую опцию.
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @return \App\Models\Option|null Восстановленная опция или null, если не найдена
     */
    public function restore(string $ns, string $key): ?Option
    {
        return DB::transaction(function () use ($ns, $key) {
            $option = Option::withTrashed()
                ->where('namespace', $ns)
                ->where('key', $key)
                ->first();

            if (! $option) {
                return null;
            }

            $option->restore();
            $this->flushCache($ns, $key);

            return $option;
        });
    }

    /**
     * Проверить, поддерживает ли кэш теги.
     *
     * @return bool true, если поддерживает теги
     */
    private function supportsTags(): bool
    {
        if (! method_exists($this->cache, 'getStore')) {
            return false;
        }

        $store = $this->cache->getStore();
        return method_exists($store, 'tags');
    }

    /**
     * Загрузить опцию из БД.
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение опции или значение по умолчанию
     */
    private function fetchFromDatabase(string $ns, string $key, mixed $default): mixed
    {
        $option = Option::query()
            ->where('namespace', $ns)
            ->where('key', $key)
            ->first();

        return $option?->value_json ?? $default;
    }

    /**
     * Очистить кэш для опции.
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @return void
     */
    private function flushCache(string $ns, string $key): void
    {
        $ck = $this->cacheKey($ns, $key);

        if ($this->supportsTags()) {
            $this->cache->tags(['options', "options:{$ns}"])->forget($ck);
            return;
        }

        $this->cache->forget($ck);
    }

    /**
     * Получить значение опции как целое число.
     *
     * @param string $ns Пространство имён опции
     * @param string $key Ключ опции
     * @param int|null $default Значение по умолчанию
     * @return int|null Значение опции как int или null
     */
    public function getInt(string $ns, string $key, ?int $default = null): ?int
    {
        $value = $this->get($ns, $key, $default);
        return $value === null ? null : (int) $value;
    }
}

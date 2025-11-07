<?php

namespace App\Domain\Options;

use App\Events\OptionChanged;
use App\Models\Option;
use Illuminate\Contracts\Cache\Repository as CacheRepo;

final class OptionsRepository
{
    public function __construct(private CacheRepo $cache) {}

    private function cacheKey(string $ns, string $key): string
    {
        return "opt:{$ns}:{$key}";
    }

    /** @return mixed|null */
    public function get(string $ns, string $key, mixed $default = null): mixed
    {
        $ck = $this->cacheKey($ns, $key);
        
        // Проверяем, поддерживает ли кэш теги
        if ($this->supportsTags()) {
            return $this->cache->tags(['options', "options:{$ns}"])
                ->rememberForever($ck, function() use ($ns, $key, $default) {
                    return $this->fetchFromDatabase($ns, $key, $default);
                });
        }
        
        // Fallback для драйверов без поддержки тегов (array, file)
        return $this->cache->rememberForever($ck, function() use ($ns, $key, $default) {
            return $this->fetchFromDatabase($ns, $key, $default);
        });
    }

    /** Сохраняет JSON-значение (примитив/массив/объект). */
    public function set(string $ns, string $key, mixed $value): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($ns, $key, $value) {
            // Получаем старое значение из БД (до изменения)
            $oldOption = Option::query()
                ->where('namespace', $ns)
                ->where('key', $key)
                ->first();
            $oldValue = $oldOption?->value_json;
            
            Option::query()->updateOrCreate(
                ['namespace' => $ns, 'key' => $key],
                ['value_json' => $value],
            );
            
            // Инвалидация кэша опций и ответов
            $ck = $this->cacheKey($ns, $key);
            if ($this->supportsTags()) {
                $this->cache->tags(['options', "options:{$ns}"])->forget($ck);
            } else {
                // Fallback: удаляем конкретный ключ
                $this->cache->forget($ck);
            }
            
            // Диспатч события после коммита транзакции
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($ns, $key, $value, $oldValue) {
                event(new \App\Events\OptionChanged($ns, $key, $value, $oldValue));
            });
        });
    }

    private function supportsTags(): bool
    {
        if (!method_exists($this->cache, 'getStore')) {
            return false;
        }
        
        $store = $this->cache->getStore();
        return method_exists($store, 'tags');
    }

    private function fetchFromDatabase(string $ns, string $key, mixed $default): mixed
    {
        $o = Option::query()
            ->where('namespace', $ns)
            ->where('key', $key)
            ->first();
        return $o?->value_json ?? $default;
    }

    public function getInt(string $ns, string $key, ?int $default = null): ?int
    {
        $v = $this->get($ns, $key, $default);
        return is_null($v) ? null : (int) $v;
    }
}


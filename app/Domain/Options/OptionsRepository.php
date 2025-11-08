<?php

namespace App\Domain\Options;

use App\Events\OptionChanged;
use App\Models\Option;
use Illuminate\Contracts\Cache\Repository as CacheRepo;
use Illuminate\Support\Facades\DB;

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

        if ($this->supportsTags()) {
            return $this->cache->tags(['options', "options:{$ns}"])
                ->rememberForever($ck, fn () => $this->fetchFromDatabase($ns, $key, $default));
        }

        return $this->cache->rememberForever($ck, fn () => $this->fetchFromDatabase($ns, $key, $default));
    }

    /** Сохраняет JSON-значение (примитив/массив/объект). */
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

    private function supportsTags(): bool
    {
        if (! method_exists($this->cache, 'getStore')) {
            return false;
        }

        $store = $this->cache->getStore();
        return method_exists($store, 'tags');
    }

    private function fetchFromDatabase(string $ns, string $key, mixed $default): mixed
    {
        $option = Option::query()
            ->where('namespace', $ns)
            ->where('key', $key)
            ->first();

        return $option?->value_json ?? $default;
    }

    private function flushCache(string $ns, string $key): void
    {
        $ck = $this->cacheKey($ns, $key);

        if ($this->supportsTags()) {
            $this->cache->tags(['options', "options:{$ns}"])->forget($ck);
            return;
        }

        $this->cache->forget($ck);
    }

    public function getInt(string $ns, string $key, ?int $default = null): ?int
    {
        $value = $this->get($ns, $key, $default);
        return $value === null ? null : (int) $value;
    }
}

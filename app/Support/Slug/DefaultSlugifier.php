<?php

namespace App\Support\Slug;

final class DefaultSlugifier implements Slugifier
{
    public function __construct(private array $config) {}

    public function slugify(string $src, ?SlugOptions $opts = null): string
    {
        $o = $opts ?? new SlugOptions(...$this->config['slug']['default']);
        $scheme = $this->config['slug']['schemes'][$o->scheme] ?? ['map' => [], 'exceptions' => []];

        $s = trim($src);
        if ($s === '') {
            return '';
        }

        // 1) Unicode NFKD + lower
        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_KD) ?: $s;
        }
        if ($o->toLower) {
            $s = mb_strtolower($s, 'UTF-8');
        }

        // 2) Exceptions (whole-word)
        foreach ($scheme['exceptions'] as $ru => $lat) {
            $pattern = '/\b' . preg_quote(mb_strtolower($ru, 'UTF-8'), '/') . '\b/u';
            $s = preg_replace($pattern, $lat, $s);
        }

        // 3) Map RU→lat (longest first)
        $map = array_merge($scheme['map'], $o->customMap);
        uksort($map, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        $s = strtr($s, $map);

        // 4) asciiOnly cleanup
        // Заменяем пунктуацию и не-ASCII на пробелы, затем нормализуем
        $s = preg_replace('~[^a-z0-9\-_ ]+~i', ' ', $s);
        $s = preg_replace('~[ _]+~', $o->delimiter, $s);
        $s = preg_replace('~' . preg_quote($o->delimiter, '~') . '{2,}~', $o->delimiter, $s);
        $s = trim($s, $o->delimiter);

        // 5) stop-words (optional, token-wise)
        // Стоп-слова должны быть уже в латинице (после транслитерации)
        if (!empty($o->stopWords)) {
            // Транслитерируем стоп-слова, если они еще на русском
            $transliteratedStopWords = array_map(function ($word) use ($map) {
                return strtr(mb_strtolower($word, 'UTF-8'), $map);
            }, $o->stopWords);
            
            $tokens = array_filter(explode($o->delimiter, $s), function ($t) use ($transliteratedStopWords) {
                return !empty($t) && !in_array($t, $transliteratedStopWords, true);
            });
            $s = implode($o->delimiter, $tokens);
        }

        // 6) max length (try cut at token boundaries)
        if ($o->maxLength > 0 && strlen($s) > $o->maxLength) {
            $s = substr($s, 0, $o->maxLength);
            $s = rtrim($s, $o->delimiter);
        }

        // 7) post-process
        if ($o->postProcess) {
            $s = ($o->postProcess)($s, $o);
        }

        return $o->toLower ? strtolower($s) : $s;
    }
}


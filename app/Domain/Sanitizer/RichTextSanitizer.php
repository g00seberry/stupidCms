<?php

declare(strict_types=1);

namespace App\Domain\Sanitizer;

use Mews\Purifier\Facades\Purifier;

/**
 * Сервис для санитизации HTML контента.
 *
 * Очищает HTML от потенциально опасных элементов и атрибутов через HTMLPurifier.
 * Автоматически добавляет rel="noopener noreferrer" к ссылкам с target="_blank"
 * для защиты от атак через window.opener.
 *
 * @package App\Domain\Sanitizer
 */
final class RichTextSanitizer
{
    /**
     * @param string $profile Профиль очистки HTMLPurifier (по умолчанию 'cms_default')
     */
    public function __construct(private string $profile = 'cms_default') {}

    /**
     * Санитизировать HTML контент.
     *
     * Очищает HTML через HTMLPurifier и добавляет rel="noopener noreferrer"
     * к ссылкам с target="_blank".
     *
     * @param string $html Исходный HTML
     * @return string Очищенный HTML
     */
    public function sanitize(string $html): string
    {
        // Сохраняем href ссылок с target="_blank" до санитизации для сопоставления
        $targetBlankHrefs = [];
        if (preg_match_all('#<a\b[^>]*\btarget\s*=\s*([\'"]?)_blank\1[^>]*>#i', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if (preg_match('#href\s*=\s*([\'"])(.*?)\1#i', $tag, $hrefMatch)) {
                    $targetBlankHrefs[] = $hrefMatch[2];
                }
            }
        }
        
        $clean = Purifier::clean($html, $this->profile);
        
        // Обрабатываем каждую ссылку индивидуально
        $clean = preg_replace_callback(
            '#<a\b[^>]*>#i',
            function (array $m) use ($targetBlankHrefs) {
                $tag = $m[0];
                
                // Проверяем, была ли эта ссылка с target="_blank" в исходном HTML
                $wasTargetBlank = false;
                if (preg_match('#href\s*=\s*([\'"])(.*?)\1#i', $tag, $hrefMatch)) {
                    $href = $hrefMatch[2];
                    $wasTargetBlank = in_array($href, $targetBlankHrefs, true);
                }
                
                // Также проверяем, есть ли target="_blank" в очищенной ссылке
                // (HTMLPurifier может его сохранить, если он в белом списке)
                $hasTargetBlank = preg_match('#\btarget\s*=\s*([\'"]?)_blank\1#i', $tag);
                
                // Обрабатываем только если была target="_blank" в исходном HTML
                // или есть в очищенной ссылке
                if (!$wasTargetBlank && !$hasTargetBlank) {
                    return $tag;
                }
                
                // Извлекаем существующий rel (если есть)
                if (preg_match('#\brel\s*=\s*([\'"])(.*?)\1#i', $tag, $rm)) {
                    $quote = $rm[1];
                    $tokens = preg_split('/\s+/', trim($rm[2])) ?: [];
                    $need = array_diff(['noopener', 'noreferrer'], array_map('strtolower', $tokens));
                    
                    if ($need) {
                        $new = implode(' ', array_unique(array_merge($tokens, $need)));
                        // Заменяем значение rel в исходном теге
                        $tag = preg_replace('#\brel\s*=\s*[\'"].*?[\'"]#i', 'rel=' . $quote . $new . $quote, $tag, 1);
                    }
                    
                    return $tag;
                }
                
                // rel нет — добавляем
                return rtrim(substr($tag, 0, -1)) . ' rel="noopener noreferrer">';
            },
            $clean
        );
        
        return $clean;
    }
}


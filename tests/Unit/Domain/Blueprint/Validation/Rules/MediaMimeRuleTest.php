<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\MediaMimeRule;
use Tests\TestCase;

final class MediaMimeRuleTest extends TestCase
{
    public function test_get_type_returns_media_mime(): void
    {
        $rule = new MediaMimeRule(['image/jpeg', 'image/png'], 'avatar');
        
        $this->assertEquals('media_mime', $rule->getType());
    }

    public function test_get_params_returns_correct_structure(): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        $pathFullPath = 'profile.avatar';
        
        $rule = new MediaMimeRule($allowedMimes, $pathFullPath);
        $params = $rule->getParams();
        
        $this->assertArrayHasKey('allowed_mime_types', $params);
        $this->assertArrayHasKey('path_full_path', $params);
        $this->assertEquals($allowedMimes, $params['allowed_mime_types']);
        $this->assertEquals($pathFullPath, $params['path_full_path']);
    }

    public function test_get_allowed_mime_types_returns_constructor_value(): void
    {
        $allowedMimes = ['image/jpeg', 'image/png'];
        $rule = new MediaMimeRule($allowedMimes, 'avatar');
        
        $this->assertEquals($allowedMimes, $rule->getAllowedMimeTypes());
    }

    public function test_get_path_full_path_returns_constructor_value(): void
    {
        $pathFullPath = 'profile.avatar';
        $rule = new MediaMimeRule(['image/jpeg'], $pathFullPath);
        
        $this->assertEquals($pathFullPath, $rule->getPathFullPath());
    }
}


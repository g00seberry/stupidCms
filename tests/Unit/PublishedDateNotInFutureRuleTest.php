<?php

namespace Tests\Unit;

use App\Rules\PublishedDateNotInFuture;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublishedDateNotInFutureRuleTest extends TestCase
{
    public function test_passes_for_draft_status(): void
    {
        $rule = new PublishedDateNotInFuture();
        $rule->setData(['status' => 'draft']);

        $this->assertTrue($rule->passes('published_at', Carbon::now('UTC')->addDay()->toDateTimeString()));
    }

    public function test_passes_for_published_with_null_date(): void
    {
        $rule = new PublishedDateNotInFuture();
        $rule->setData(['status' => 'published']);

        $this->assertTrue($rule->passes('published_at', null));
    }

    public function test_passes_for_published_with_empty_date(): void
    {
        $rule = new PublishedDateNotInFuture();
        $rule->setData(['status' => 'published']);

        $this->assertTrue($rule->passes('published_at', ''));
    }

    public function test_passes_for_published_with_past_date(): void
    {
        $rule = new PublishedDateNotInFuture();
        $rule->setData(['status' => 'published']);

        $this->assertTrue($rule->passes('published_at', Carbon::now('UTC')->subDay()->toDateTimeString()));
    }

    public function test_passes_for_published_with_now_date(): void
    {
        $rule = new PublishedDateNotInFuture();
        $rule->setData(['status' => 'published']);

        $this->assertTrue($rule->passes('published_at', Carbon::now('UTC')->toDateTimeString()));
    }

    public function test_fails_for_published_with_future_date(): void
    {
        $rule = new PublishedDateNotInFuture();
        $rule->setData(['status' => 'published']);

        $this->assertFalse($rule->passes('published_at', Carbon::now('UTC')->addDay()->toDateTimeString()));
    }

    public function test_message_is_correct(): void
    {
        $rule = new PublishedDateNotInFuture();

        $this->assertEquals('Дата публикации не может быть в будущем для статуса "published"', $rule->message());
    }
}


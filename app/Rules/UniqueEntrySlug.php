<?php

namespace App\Rules;

use App\Models\Entry;
use App\Models\PostType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueEntrySlug implements ValidationRule
{
    public function __construct(
        private string $postTypeSlug,
        private ?int $exceptEntryId = null
    ) {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Find post_type_id by slug
        $postType = PostType::query()->where('slug', $this->postTypeSlug)->first();
        
        if (! $postType) {
            $fail('The specified post type does not exist.');
            return;
        }

        // Check if slug is already taken in this post_type (including soft-deleted)
        $query = Entry::query()
            ->withTrashed()
            ->where('post_type_id', $postType->id)
            ->where('slug', $value);

        if ($this->exceptEntryId) {
            $query->where('id', '!=', $this->exceptEntryId);
        }

        if ($query->exists()) {
            $fail('The slug is already taken for this post type.');
        }
    }
}


<?php

namespace App\Support\Slug;

interface Slugifier
{
    public function slugify(string $source, ?SlugOptions $opts = null): string;
}


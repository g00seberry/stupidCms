@extends('layouts.public')

@section('title', $entry->title . ' - ' . config('app.name'))

@section('content')
<article class="container mx-auto px-4 py-12">
    <div class="max-w-3xl mx-auto">
        <header class="mb-8">
            <h1 class="text-4xl font-bold mb-4">{{ $entry->title }}</h1>
            
            @if($entry->published_at)
            <time class="text-gray-600" datetime="{{ $entry->published_at->toIso8601String() }}">
                {{ $entry->published_at->format('F j, Y') }}
            </time>
            @endif
        </header>

        <div class="prose prose-lg max-w-none">
            {!! $entry->content_html !!}
        </div>
    </div>
</article>
@endsection


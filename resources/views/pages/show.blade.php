@extends('layouts.app')

@section('title', $entry->title)

@push('meta')
  @if(request()->routeIs('home'))
    {{-- Канонизация: главная страница с записью должна указывать на её прямой URL --}}
    <link rel="canonical" href="{{ url('/' . $entry->slug) }}">
  @endif
@endpush

@section('content')
  <article class="prose">
    <h1>{{ $entry->title }}</h1>
    @php
      // ВАЖНО: До включения санитайзера (задача 35) контент экранируется для безопасности
      // После реализации санитайзера использовать body_html_sanitized и {!! !!}
      $html = data_get($entry->data_json, 'body_html_sanitized');
      $content = data_get($entry->data_json, 'content');
      $bodyHtml = data_get($entry->data_json, 'body_html');
    @endphp
    
    @if($html !== null)
      {{-- Санитизированный HTML из задачи 35 --}}
      {!! $html !!}
    @elseif($bodyHtml !== null)
      {{-- Временно экранируем до включения санитайзера --}}
      {{ $bodyHtml }}
    @elseif($content !== null)
      {{-- Текстовый контент (безопасен) --}}
      {{ $content }}
    @endif
  </article>
@endsection


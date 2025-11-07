<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name'))</title>
  @stack('meta')
  @stack('head')
  @if (!app()->environment('testing') && file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css','resources/js/app.js'])
  @endif
</head>
<body class="min-h-screen flex flex-col">
  @include('partials.header')

  <main id="content" class="grow">
    @yield('content')
  </main>

  @include('partials.footer')

  @stack('scripts')
</body>
</html>


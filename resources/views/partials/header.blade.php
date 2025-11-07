<header data-partial="header" class="border-b">
  <div class="container mx-auto px-4 py-4 flex items-center justify-between">
    <a href="{{ url('/') }}" class="font-semibold text-lg">{{ config('app.name') }}</a>
    {{-- Простая навигация; можно расширить позже через меню/опции --}}
    <nav class="flex gap-4">
      <a href="#about">About</a>
    </nav>
  </div>
</header>


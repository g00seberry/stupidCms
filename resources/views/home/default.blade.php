@extends('layouts.public')

@section('title', config('app.name') . ' - Home')

@section('content')
<div class="container mx-auto px-4 py-12">
    <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl font-bold mb-6">Welcome to {{ config('app.name') }}</h1>
        <p class="text-xl text-gray-600 mb-8">
            A modern headless CMS built with Laravel.
        </p>
        
        <div class="prose prose-lg mx-auto">
            <p>
                This is the default home page template. 
                You can customize it by editing <code>resources/views/home/default.blade.php</code>
                or set a specific entry as homepage via <strong>Admin Panel → Settings → Home Entry ID</strong>.
            </p>
        </div>
    </div>
</div>
@endsection


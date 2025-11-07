<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body class="error-page">
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The requested path <code>{{ $path ?? request()->path() }}</code> was not found.</p>
        <p><a href="/">Go to homepage</a></p>
    </div>
</body>
</html>


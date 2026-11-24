<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Form Login')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen text-white">

    @if (session('success'))
        <div data-flash-success="{{ session('success') }}" class="hidden"></div>
    @endif
    @if (session('error'))
        <div data-flash-error="{{ session('error') }}" class="hidden"></div>
    @endif

    @yield('content')

    @include('components.ui.loading-screen')

</body>

</html>
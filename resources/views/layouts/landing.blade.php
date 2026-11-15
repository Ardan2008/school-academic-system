<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sistem Akademik Sekolah')</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    @include('components.layout.navbar')

    <main>
        @yield('content')
    </main>

    @include('components.layout.footer')

    @include('components.ui.back-to-top')

    @include('components.ui.loading-screen')

</body>
</html>
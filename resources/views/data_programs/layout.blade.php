<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - SCM Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Teks kecil tanpa letter-spacing negatif supaya tetap terbaca di HP */
        html, body, input, button, select, textarea {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>
{{--
    Halaman link WA (telemarketing, AR, Tim Pajak): satu kartu tanpa garis pemisah di header/footer,
    jarak di grid 4px, teks 12 / 14 / 16px dan satu angka besar 24px untuk nominal; muat satu layar laptop di zoom 100%
--}}
<body class="bg-gray-50 text-gray-900 text-sm min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-2xl border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
        <header class="px-5 pt-4 flex items-center justify-between gap-4">
            <span class="text-xs font-medium text-gray-900">SCM Automation</span>
            <span class="text-xs @yield('tag_class', 'text-gray-500')">@yield('tag')</span>
        </header>

        <div class="px-5 pt-4 pb-5 space-y-5">
            @yield('content')
        </div>

        <footer class="px-5 pb-4 text-xs text-gray-500">@yield('footer')</footer>
    </main>
</body>
</html>

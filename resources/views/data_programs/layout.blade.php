<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - SCM Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body, input, button, select, textarea {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            letter-spacing: -0.011em;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>
{{-- Halaman link WA (telemarketing, AR, Tim Pajak): tiga ukuran teks saja — 12px label & keterangan, 14px isi, 16px judul --}}
<body class="bg-gray-50 text-gray-900 text-sm min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">SCM Automation</span>
            <span class="text-xs font-medium rounded-full px-2.5 py-0.5 @yield('tag_class', 'bg-gray-100 text-gray-600')">@yield('tag')</span>
        </div>

        <div class="p-5 space-y-5">
            @yield('content')
        </div>

        <p class="px-5 py-3 border-t border-gray-100 text-xs text-gray-400 text-center">@yield('footer')</p>
    </div>
</body>
</html>

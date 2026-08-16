<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="dark" data-theme="violet">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Replay device preferences before the first paint to avoid a theme flash. --}}
    <script>
        (function() {
            const root = document.documentElement;
            const serverAppearance = @json($appearance ?? 'dark');
            let appearance = ['light', 'dark', 'system'].includes(serverAppearance)
                ? serverAppearance
                : 'dark';
            let theme = 'violet';

            try {
                const storedAppearance = localStorage.getItem('appearance');
                const storedTheme = localStorage.getItem('wacrm.theme');

                if (['light', 'dark', 'system'].includes(storedAppearance)) {
                    appearance = storedAppearance;
                }
                if (['violet', 'emerald', 'cobalt', 'amber', 'rose'].includes(storedTheme)) {
                    theme = storedTheme;
                }
            } catch {
                // Private browsing may deny localStorage; defaults remain valid.
            }

            const mode = appearance === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : appearance;

            root.dataset.mode = mode;
            root.dataset.theme = theme;
            root.style.colorScheme = mode;
        })();
    </script>
    <style>
        html {
            background-color: oklch(0.13 0.01 260);
        }

        html[data-mode="light"] {
            background-color: oklch(0.99 0.002 260);
        }
    </style>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    <x-inertia::head>
        <title>Wacrm</title>
    </x-inertia::head>
</head>

<body class="font-sans antialiased">
    <x-inertia::app />
</body>

</html>

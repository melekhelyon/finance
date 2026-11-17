<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="darkreader-lock">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>finance — Учёт финансов</title>
        <script>
            document.documentElement.dataset.theme = localStorage.getItem('theme') || (
                matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            );
        </script>
        @vite(['resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>

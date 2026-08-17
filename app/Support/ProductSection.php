<?php

declare(strict_types=1);

namespace App\Support;

final class ProductSection
{
    /**
     * @var array<string, string>
     */
    private const INDEX_ROUTES = [
        'dashboard' => 'dashboard',
        'inbox' => 'inbox',
        'contacts' => 'contacts',
        'pipelines' => 'pipelines',
        'broadcasts' => 'broadcasts',
        'automations' => 'automations',
        'flows' => 'flows',
        'agents' => 'agents',
        'notifications' => 'notifications',
        'settings' => 'settings.overview',
    ];

    public function routeName(?string $url): string
    {
        if ($url === null || $url === '') {
            return 'dashboard';
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || $path === '/') {
            return 'dashboard';
        }

        $segment = explode('/', ltrim($path, '/'), 2)[0];

        return self::INDEX_ROUTES[$segment] ?? 'dashboard';
    }
}

<?php

declare(strict_types=1);

arch('application security')
    ->preset()
    ->security();

arch('domain support is framework and transport independent')
    ->expect('App\\Domain\\*\\Support')
    ->not->toUse([
        'App\\Http',
        'Illuminate\\Http',
        'Inertia',
    ]);

arch('domain results are transport independent')
    ->expect('App\\Domain\\*\\Results')
    ->not->toUse([
        'App\\Http',
        'Illuminate\\Http',
        'Inertia',
    ]);

arch('domain services stay isolated from HTTP')
    ->expect('App\\Domain\\*\\Services')
    ->not->toUse([
        'App\\Http',
        'Illuminate\\Http',
        'Inertia',
    ]);

arch('responders stay isolated from persistence')
    ->expect('App\\Domain\\*\\Responders')
    ->not->toUse([
        'App\\Models',
        'Illuminate\\Database',
    ]);

arch('models do not depend on domain')
    ->expect('App\\Models')
    ->not->toUse('App\\Domain');

arch('only Fortify owns the legacy actions namespace')
    ->expect('App\\Actions')
    ->toOnlyBeUsedIn([
        'App\\Actions\\Fortify',
        'App\\Providers\\FortifyServiceProvider',
    ]);

test('the App\\Actions tree only contains the Fortify exception', function () {
    $directories = array_map(
        basename(...),
        glob(app_path('Actions/*'), GLOB_ONLYDIR) ?: [],
    );

    expect($directories)->toBe(['Fortify']);
});

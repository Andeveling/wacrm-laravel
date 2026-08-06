<?php

use App\Domain\Settings\Actions\DestroyApiKey;
use App\Domain\Settings\Actions\DestroyProfile;
use App\Domain\Settings\Actions\ShowApiKeys;
use App\Domain\Settings\Actions\ShowProfile;
use App\Domain\Settings\Actions\ShowSecurity;
use App\Domain\Settings\Actions\StoreApiKey;
use App\Domain\Settings\Actions\UpdatePassword;
use App\Domain\Settings\Actions\UpdateProfile;
/* @chisel-password-confirmation */
use Illuminate\Auth\Middleware\RequirePassword;
/* @end-chisel-password-confirmation */
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', function () {
        $panels = [
            ['slug' => 'profile', 'title' => 'Perfil', 'description' => 'Datos personales y verificación de email.', 'status' => 'Disponible', 'href' => route('profile.edit')],
            ['slug' => 'security', 'title' => 'Seguridad', 'description' => 'Contraseña, 2FA y passkeys.', 'status' => 'Disponible', 'href' => route('security.edit')],
            ['slug' => 'appearance', 'title' => 'Apariencia', 'description' => 'Tema claro/oscuro del sistema.', 'status' => 'Disponible', 'href' => route('appearance.edit')],
            ['slug' => 'members', 'title' => 'Miembros', 'description' => 'Gestión de miembros del equipo.', 'status' => 'Disponible', 'href' => null],
            ['slug' => 'api-keys', 'title' => 'API Keys', 'description' => 'Tokens de acceso programático.', 'status' => 'Disponible', 'href' => route('settings.api-keys')],
            ['slug' => 'whatsapp', 'title' => 'WhatsApp', 'description' => 'Conexión con WhatsApp Business.', 'status' => 'Disponible', 'href' => route('settings.whatsapp')],
            ['slug' => 'templates', 'title' => 'Plantillas', 'description' => 'Plantillas de mensajes aprobadas por Meta.', 'status' => 'Disponible', 'href' => route('settings.templates')],
            ['slug' => 'quick-replies', 'title' => 'Respuestas rápidas', 'description' => 'Atajos reutilizables para conversaciones.', 'status' => 'Disponible', 'href' => route('settings.quick-replies')],
            ['slug' => 'fields', 'title' => 'Campos personalizados', 'description' => 'Campos extra para contactos y negocios.', 'status' => 'Disponible', 'href' => route('settings.fields')],
            ['slug' => 'deals', 'title' => 'Pipelines', 'description' => 'Etapas y automatización del pipeline.', 'status' => 'Disponible', 'href' => route('settings.deals')],
        ];

        return inertia('settings/overview', [
            'panels' => $panels,
        ]);
    })->name('settings.overview');

    Route::inertia('settings/whatsapp', 'settings/whatsapp')->name('settings.whatsapp');
    Route::inertia('settings/templates', 'settings/templates')->name('settings.templates');
    Route::inertia('settings/quick-replies', 'settings/quick-replies')->name('settings.quick-replies');
    Route::inertia('settings/fields', 'settings/fields')->name('settings.fields');
    Route::inertia('settings/deals', 'settings/deals')->name('settings.deals');

    Route::get('settings/profile', ShowProfile::class)->name('profile.edit');
    Route::patch('settings/profile', UpdateProfile::class)->name('profile.update');

    Route::middleware('ensure.current-account')->group(function () {
        Route::get('settings/api-keys', ShowApiKeys::class)->name('settings.api-keys');
        Route::post('settings/api-keys', StoreApiKey::class)->name('settings.api-keys.store');
        Route::delete('settings/api-keys/{apiKey}', DestroyApiKey::class)->name('settings.api-keys.destroy');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', DestroyProfile::class)->name('profile.destroy');

    Route::get('settings/security', ShowSecurity::class)
        /* @chisel-password-confirmation */
        ->middleware(RequirePassword::class)
        /* @end-chisel-password-confirmation */
        ->name('security.edit');

    Route::put('settings/password', UpdatePassword::class)
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

/* @chisel-passkeys */
Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
/* @end-chisel-passkeys */

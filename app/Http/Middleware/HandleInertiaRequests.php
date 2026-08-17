<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use App\Support\CurrentAccountResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private CurrentAccountResolver $resolver) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'currentAccount' => fn (): ?array => $this->sharedCurrentAccount($request, $user),
            'accounts' => fn (): array => $this->sharedMemberships($user),
            'hasWhatsappConnection' => fn (): bool => $this->sharedHasWhatsappConnection($request, $user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: string, name: string, type: string, role: string}|null
     */
    private function sharedCurrentAccount(Request $request, ?User $user): ?array
    {
        if ($request->attributes->has('inertia.sharedCurrentAccount')) {
            /** @var array{id: string, name: string, type: string, role: string}|null $cached */
            $cached = $request->attributes->get('inertia.sharedCurrentAccount');

            return $cached;
        }

        if ($user === null) {
            $request->attributes->set('inertia.sharedCurrentAccount', null);

            return null;
        }

        if (app()->bound(CurrentAccount::class)) {
            $account = $user->accounts()->whereKey(app(CurrentAccount::class)->id())->first();
        } else {
            $account = $this->resolver->membership($request);

            if ($account !== null) {
                $this->resolver->remember($request, $account);
            }
        }

        $role = $account?->pivot?->role;

        if ($account === null || $role === null) {
            $request->attributes->set('inertia.sharedCurrentAccount', null);

            return null;
        }

        $shared = [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'role' => $role->value,
        ];

        $request->attributes->set('inertia.sharedCurrentAccount', $shared);

        return $shared;
    }

    /**
     * @return list<array{id: string, name: string, type: string}>
     */
    private function sharedMemberships(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $memberships = [];

        foreach ($user->accounts()->orderBy('name')->get(['id', 'name', 'type']) as $account) {
            $memberships[] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
            ];
        }

        return $memberships;
    }

    private function sharedHasWhatsappConnection(Request $request, ?User $user): bool
    {
        if (app()->bound(CurrentAccount::class)) {
            return WhatsappPhoneNumberConnection::query()->exists();
        }

        $current = $this->sharedCurrentAccount($request, $user);

        if ($current === null) {
            return false;
        }

        return WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScopes()
            ->where('account_id', $current['id'])
            ->exists();
    }
}

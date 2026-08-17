<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Enums\AccountType;
use App\Models\User;
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: string, name: string, type: string, role: string}|null
     */
    private function sharedCurrentAccount(Request $request, ?User $user): ?array
    {
        if ($user === null) {
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
            return null;
        }

        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'role' => $role->value,
        ];
    }

    /**
     * @return list<array{id: string, name: string, type: string}>
     */
    private function sharedMemberships(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $ordered = $user->accounts()
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->sortBy(fn (Account $account): int => $account->type === AccountType::Personal ? 1 : 0);

        $memberships = [];

        foreach ($ordered as $account) {
            $memberships[] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
            ];
        }

        return $memberships;
    }
}

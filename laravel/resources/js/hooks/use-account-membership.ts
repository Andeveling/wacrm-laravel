import { router, useForm } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';

import type {
    AccountMember,
    MemberRole,
} from '@/components/accounts/member-management';
import {
    errorMessageFor,
    isSoleOwner,
    type MembershipMutation,
    roleOptions as buildRoleOptions,
    type RoleOption,
} from '@/lib/account-membership';
import { destroy, store, update } from '@/routes/accounts/members';

/** `AccountMember` enriched with the sole-Owner flag (ADR 0002). */
export type EnrichedMember = AccountMember & {
    isSoleOwner: boolean;
};

type InviteForm = {
    email: string;
    role: MemberRole;
};

/**
 * Centralises every mutation + invariant the members page needs:
 * `members` enriched with `isSoleOwner`, `inviteState` + `inviteMember()`,
 * `changeRole(member, role)`, `removeMember(member, onSuccess?)`,
 * `roleOptions(isOwner)`.
 */
export function useAccountMembership(
    accountId: string,
    initialMembers: AccountMember[] = [],
) {
    const enrichedMembers = useMemo<EnrichedMember[]>(
        () =>
            initialMembers.map((member) => ({
                ...member,
                isSoleOwner: isSoleOwner(member, initialMembers),
            })),
        [initialMembers],
    );

    const [busyMemberId, setBusyMemberId] = useState<number | null>(null);

    const surfaceError = useCallback(
        (context: MembershipMutation, status: number | null) => {
            toast.error(errorMessageFor(context, status));

            // Returning false tells Inertia to skip its built-in error
            // dialog and global event — we already showed our own toast.
            return false;
        },
        [],
    );

    const inviteState = useForm<InviteForm>({
        email: '',
        role: 'member',
    });

    const inviteMember = useCallback(() => {
        inviteState.submit(store(accountId), {
            preserveScroll: true,
            onSuccess: () => {
                inviteState.reset('email');
                router.flash({
                    toast: {
                        type: 'success',
                        message: 'Invitación creada.',
                    },
                });
            },
            onHttpException: (response) =>
                surfaceError('invite-member', response.status),
            onNetworkError: () => surfaceError('invite-member', null),
        });
    }, [accountId, inviteState, surfaceError]);

    const changeRole = useCallback(
        (member: AccountMember, role: MemberRole) => {
            if (role === member.role) {
                return;
            }

            setBusyMemberId(member.id);
            router.patch(
                update({ account: accountId, member: member.id }),
                { role },
                {
                    preserveScroll: true,
                    onSuccess: () => setBusyMemberId(null),
                    onHttpException: (response) =>
                        surfaceError('change-role', response.status),
                    onNetworkError: () => surfaceError('change-role', null),
                    onFinish: () => setBusyMemberId(null),
                },
            );
        },
        [accountId, surfaceError],
    );

    const removeMember = useCallback(
        (
            member: AccountMember,
            onSuccess?: () => void,
        ) => {
            const id = member.id;

            setBusyMemberId(id);
            router.delete(destroy({ account: accountId, member: id }), {
                preserveScroll: true,
                onSuccess: () => {
                    setBusyMemberId(null);
                    onSuccess?.();
                },
                onHttpException: (response) =>
                    surfaceError('remove-member', response.status),
                onNetworkError: () => surfaceError('remove-member', null),
                onFinish: () => setBusyMemberId(null),
            });
        },
        [accountId, surfaceError],
    );

    return {
        members: enrichedMembers,
        busyMemberId,
        inviteState,
        inviteMember,
        changeRole,
        removeMember,
        roleOptions: buildRoleOptions,
    };
}

export type { RoleOption };
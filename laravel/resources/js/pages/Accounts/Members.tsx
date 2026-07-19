import { Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type {
    AccountMember,
    MemberRole,
} from '@/components/Accounts/member-management';
import {
    ConfirmRemoveMemberDialog,
    InviteMemberForm,
    MemberActionsCell,
} from '@/components/Accounts/member-management';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { destroy, update } from '@/routes/accounts/members';

type AccountSummary = {
    id: string;
    name: string;
    role: MemberRole;
};

type PageProps = {
    account: AccountSummary;
    members: AccountMember[];
    is_owner: boolean;
    is_admin: boolean;
};

const ROLE_LABEL: Record<MemberRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
    viewer: 'Viewer',
};

const ROLE_BADGE: Record<
    MemberRole,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    owner: 'default',
    admin: 'secondary',
    member: 'outline',
    viewer: 'outline',
};

function initials(name: string, email: string): string {
    const source = name || email || '?';

    return source.trim().charAt(0).toUpperCase() || '?';
}

function formatJoinedAt(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Members({
    account,
    members,
    is_owner,
    is_admin,
}: PageProps) {
    const { errors } = usePage<PageProps>().props;
    const [busyMemberId, setBusyMemberId] = useState<number | null>(null);
    const [memberToRemove, setMemberToRemove] = useState<AccountMember | null>(
        null,
    );
    const ownerCount = members.filter(
        (member) => member.role === 'owner',
    ).length;
    const lastOwnerError = errors.last_owner_blocked;

    function changeRole(member: AccountMember, role: MemberRole) {
        if (role === member.role) {
            return;
        }

        setBusyMemberId(member.id);
        router.patch(
            update({ account: account.id, member: member.id }),
            { role },
            {
                preserveScroll: true,
                onSuccess: () => setBusyMemberId(null),
                onHttpException: (response) => {
                    toast.error(
                        response.status === 403
                            ? 'No tenés permiso para cambiar roles.'
                            : 'No se pudo cambiar el rol.',
                    );

                    return false;
                },
                onNetworkError: () => {
                    toast.error('No se pudo conectar con el servidor.');

                    return false;
                },
                onFinish: () => setBusyMemberId(null),
            },
        );
    }

    function removeMember() {
        if (!memberToRemove) {
            return;
        }

        const memberId = memberToRemove.id;
        setBusyMemberId(memberId);
        router.delete(destroy({ account: account.id, member: memberId }), {
            preserveScroll: true,
            onSuccess: () => {
                setMemberToRemove(null);
                setBusyMemberId(null);
            },
            onHttpException: (response) => {
                toast.error(
                    response.status === 403
                        ? 'No tenés permiso para remover miembros.'
                        : 'No se pudo remover el miembro.',
                );

                return false;
            },
            onNetworkError: () => {
                toast.error('No se pudo conectar con el servidor.');

                return false;
            },
            onFinish: () => setBusyMemberId(null),
        });
    }

    return (
        <>
            <Head title="Members" />

            <div className="space-y-6">
                <Heading
                    title={`Members of ${account.name}`}
                    description="Todos los miembros de esta cuenta. Owners y Admins pueden invitar, cambiar roles y remover miembros."
                />

                {lastOwnerError && (
                    <Alert variant="destructive" data-testid="last-owner-error">
                        <AlertCircle />
                        <AlertTitle>
                            No podés dejar la cuenta sin Owner
                        </AlertTitle>
                        <AlertDescription>{lastOwnerError}</AlertDescription>
                    </Alert>
                )}

                {is_admin && (
                    <InviteMemberForm
                        accountId={account.id}
                        isOwner={is_owner}
                    />
                )}

                <Card>
                    <CardContent className="p-0">
                        <ul
                            className="divide-y divide-border"
                            data-testid="members-roster"
                        >
                            {members.length === 0 && (
                                <li className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                                    <Users className="size-6 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Todavía no hay miembros.
                                    </p>
                                </li>
                            )}

                            {members.map((member) => {
                                const soleOwnerSelf =
                                    member.is_you &&
                                    member.role === 'owner' &&
                                    ownerCount === 1;
                                const busy = busyMemberId === member.id;

                                return (
                                    <li
                                        key={member.id}
                                        className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4"
                                        data-testid={`member-row-${member.id}`}
                                    >
                                        <div className="flex min-w-0 flex-1 items-center gap-4">
                                            <Avatar className="size-9 shrink-0">
                                                <AvatarFallback className="bg-primary/10 text-sm font-medium text-primary">
                                                    {initials(
                                                        member.name,
                                                        member.email,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>

                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium text-foreground">
                                                        {member.name ||
                                                            member.email}
                                                    </span>
                                                    {member.is_you && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[10px] tracking-wide uppercase"
                                                        >
                                                            Vos
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {member.email}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="hidden text-right text-xs text-muted-foreground sm:block">
                                            {member.joined_at
                                                ? `Se unió ${formatJoinedAt(member.joined_at)}`
                                                : ''}
                                        </div>

                                        {is_admin ? (
                                            <MemberActionsCell
                                                member={member}
                                                isOwner={is_owner}
                                                soleOwnerSelf={soleOwnerSelf}
                                                busy={busy}
                                                onRoleChange={changeRole}
                                                onRemove={setMemberToRemove}
                                            />
                                        ) : (
                                            <Badge
                                                variant={
                                                    ROLE_BADGE[member.role]
                                                }
                                            >
                                                {ROLE_LABEL[member.role]}
                                            </Badge>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>

                <p
                    className="text-xs text-muted-foreground"
                    data-testid="viewer-flags"
                >
                    Tu rol actual es {ROLE_LABEL[account.role]}.
                </p>
            </div>

            <ConfirmRemoveMemberDialog
                accountName={account.name}
                member={memberToRemove}
                processing={
                    memberToRemove !== null &&
                    busyMemberId === memberToRemove.id
                }
                onClose={() => setMemberToRemove(null)}
                onConfirm={removeMember}
            />
        </>
    );
}

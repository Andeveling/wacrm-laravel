import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, Users } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { ROLE_BADGE, ROLE_LABEL } from '../roles';
import { useAccountMembership } from '../use-account-membership';
import {
  type AccountMember,
  ConfirmRemoveMemberDialog,
  InviteMemberForm,
  MemberActionsCell,
  type MemberRole,
} from './member-management';

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
  const {
    members: enrichedMembers,
    busyMemberId,
    inviteState,
    inviteMember,
    changeRole,
    removeMember,
    roleOptions,
  } = useAccountMembership(account.id, members);

  const [memberToRemove, setMemberToRemove] = useState<AccountMember | null>(
    null,
  );

  const lastOwnerError = errors.last_owner_blocked;

  function confirmRemove() {
    if (!memberToRemove) {
      return;
    }
    removeMember(memberToRemove, () => setMemberToRemove(null));
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
            <AlertTitle>No puedes dejar la cuenta sin Owner</AlertTitle>
            <AlertDescription>{lastOwnerError}</AlertDescription>
          </Alert>
        )}

        {is_admin && (
          <InviteMemberForm
            inviteState={inviteState}
            inviteMember={inviteMember}
            isOwner={is_owner}
            roleOptions={roleOptions}
          />
        )}

        <Card>
          <CardContent className="p-0">
            <ul className="divide-y divide-border" data-testid="members-roster">
              {enrichedMembers.length === 0 && (
                <li className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                  <Users className="size-6 text-muted-foreground" />
                  <p className="text-sm text-muted-foreground">
                    Todavía no hay miembros.
                  </p>
                </li>
              )}

              {enrichedMembers.map((member) => {
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
                          {initials(member.name, member.email)}
                        </AvatarFallback>
                      </Avatar>

                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <span className="truncate text-sm font-medium text-foreground">
                            {member.name || member.email}
                          </span>
                          {member.is_you && (
                            <Badge
                              variant="outline"
                              className="text-[10px] tracking-wide uppercase"
                            >
                              Tú
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
                        soleOwnerSelf={member.isSoleOwner && member.is_you}
                        busy={busy}
                        roleOptions={roleOptions}
                        onRoleChange={changeRole}
                        onRemove={setMemberToRemove}
                      />
                    ) : (
                      <Badge variant={ROLE_BADGE[member.role]}>
                        {ROLE_LABEL[member.role]}
                      </Badge>
                    )}
                  </li>
                );
              })}
            </ul>
          </CardContent>
        </Card>

        <p className="text-xs text-muted-foreground" data-testid="viewer-flags">
          Tu rol actual es {ROLE_LABEL[account.role]}.
        </p>

        <ConfirmRemoveMemberDialog
          accountName={account.name}
          member={memberToRemove}
          processing={
            memberToRemove !== null && busyMemberId === memberToRemove.id
          }
          onClose={() => setMemberToRemove(null)}
          onConfirm={confirmRemove}
        />
      </div>
    </>
  );
}

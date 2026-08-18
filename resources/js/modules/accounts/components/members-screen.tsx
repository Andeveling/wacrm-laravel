// biome-ignore lint/style/noExcessiveLinesPerFile: JSX is split into focused internal components.
import { Head, router, usePage } from '@inertiajs/react';
import {
  AlertCircle,
  Clock3,
  Loader2,
  RefreshCw,
  Trash2,
  Users,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import RevokeInvitation from '@/actions/App/Domain/Invitations/Actions/RevokeInvitation';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { ROLE_BADGE, ROLE_LABEL } from '../constants/roles';
import { useAccountMembership } from '../hooks/use-account-membership';
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
  invitations?: PendingInvitation[];
  invitation_url: string | null;
};

type PendingInvitation = {
  id: string;
  email: string | null;
  role: MemberRole;
  inviter: string;
  created_at: string | null;
  expires_at: string;
  status: 'Active' | 'Expired';
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

function formatDate(iso: string | null): string {
  return iso ? new Date(iso).toLocaleDateString() : 'Sin fecha';
}

type MemberRosterProps = {
  members: Array<AccountMember & { isSoleOwner: boolean }>;
  busyMemberId: number | null;
  isAdmin: boolean;
  isOwner: boolean;
  roleOptions: (isOwner: boolean) => { value: MemberRole; label: string }[];
  onRoleChange: (member: AccountMember, role: MemberRole) => void;
  onRemove: (member: AccountMember) => void;
};

function MemberRoster(props: MemberRosterProps) {
  const {
    members,
    busyMemberId,
    isAdmin,
    isOwner,
    roleOptions,
    onRoleChange,
    onRemove,
  } = props;

  return (
    <Card>
      <CardContent className="p-0">
        <ul className="divide-y divide-border" data-testid="members-roster">
          {members.length === 0 && (
            <li className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
              <Users className="size-6 text-muted-foreground" />
              <p className="text-sm text-muted-foreground">
                Todavía no hay miembros.
              </p>
            </li>
          )}
          {members.map((member) => {
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
                      {member.is_you ? (
                        <Badge
                          variant="outline"
                          className="text-[10px] tracking-wide uppercase"
                        >
                          Tú
                        </Badge>
                      ) : null}
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
                {isAdmin ? (
                  <MemberActionsCell
                    member={member}
                    isOwner={isOwner}
                    soleOwnerSelf={member.isSoleOwner && member.is_you}
                    busy={busy}
                    roleOptions={roleOptions}
                    onRoleChange={onRoleChange}
                    onRemove={onRemove}
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
  );
}

type PendingInvitationsProps = {
  invitations?: PendingInvitation[];
  busyInvitationId: string | null;
  revokingInvitationId: string | null;
  onRevoke: (invitation: PendingInvitation) => void;
  onRegenerate: (invitation: PendingInvitation) => void;
};

function PendingInvitations(props: PendingInvitationsProps) {
  const {
    invitations,
    busyInvitationId,
    revokingInvitationId,
    onRevoke,
    onRegenerate,
  } = props;

  return (
    <Card data-testid="pending-invitations">
      <CardContent className="p-0">
        <div className="flex items-center gap-2 border-b border-border px-4 py-3">
          <Clock3 className="size-4 text-muted-foreground" />
          <h2 className="text-sm font-medium">Invitaciones pendientes</h2>
        </div>
        <ul className="divide-y divide-border">
          {invitations?.length === 0 && (
            <li className="px-4 py-6 text-sm text-muted-foreground">
              No hay invitaciones pendientes.
            </li>
          )}
          {invitations?.map((invitation) => (
            <li
              key={invitation.id}
              className="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center"
              data-testid={`invitation-row-${invitation.id}`}
            >
              <div className="grid min-w-0 flex-1 gap-1 sm:grid-cols-3">
                <span className="truncate font-medium">
                  {invitation.email ?? 'Sin email'}
                </span>
                <span className="text-muted-foreground">
                  {ROLE_LABEL[invitation.role]} por {invitation.inviter}
                </span>
                <span className="text-muted-foreground sm:text-right">
                  Creada {formatDate(invitation.created_at)} · Expira{' '}
                  {formatDate(invitation.expires_at)} ·{' '}
                  {invitation.status === 'Active' ? 'Activa' : 'Expirada'}
                </span>
              </div>
              {invitation.status === 'Active' && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => onRevoke(invitation)}
                  disabled={revokingInvitationId === invitation.id}
                  className="self-start border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive sm:self-auto"
                  data-testid={`revoke-invitation-${invitation.id}`}
                >
                  {revokingInvitationId === invitation.id ? (
                    <Loader2 className="animate-spin" />
                  ) : (
                    <Trash2 />
                  )}
                  Revocar
                </Button>
              )}
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onRegenerate(invitation)}
                disabled={busyInvitationId === invitation.id}
                data-testid={`regenerate-invitation-${invitation.id}`}
              >
                <RefreshCw />
                {busyInvitationId === invitation.id
                  ? 'Regenerando…'
                  : 'Regenerar'}
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

export default function Members({
  account,
  members,
  is_owner,
  is_admin,
  invitations,
  invitation_url,
}: PageProps) {
  const { errors } = usePage<PageProps>().props;
  const {
    members: enrichedMembers,
    busyMemberId,
    busyInvitationId,
    inviteState,
    inviteMember,
    changeRole,
    removeMember,
    regenerateInvitation,
    roleOptions,
  } = useAccountMembership(account.id, members);

  const [memberToRemove, setMemberToRemove] = useState<AccountMember | null>(
    null,
  );
  const [invitationToRevoke, setInvitationToRevoke] =
    useState<PendingInvitation | null>(null);
  const [revokingInvitationId, setRevokingInvitationId] = useState<
    string | null
  >(null);
  const [invitationToRegenerate, setInvitationToRegenerate] =
    useState<PendingInvitation | null>(null);
  const [, copy] = useClipboard();

  const lastOwnerError = errors.last_owner_blocked;

  function confirmRemove() {
    if (!memberToRemove) {
      return;
    }
    removeMember(memberToRemove, () => setMemberToRemove(null));
  }

  function confirmRevokeInvitation() {
    if (!invitationToRevoke) {
      return;
    }

    setRevokingInvitationId(invitationToRevoke.id);
    router.delete(RevokeInvitation(invitationToRevoke.id), {
      preserveScroll: true,
      onSuccess: () => setInvitationToRevoke(null),
      onError: () => toast.error('No se pudo revocar la invitación.'),
      onFinish: () => setRevokingInvitationId(null),
    });
  }

  function confirmRegenerate() {
    if (!invitationToRegenerate) {
      return;
    }

    regenerateInvitation(invitationToRegenerate.id, () =>
      setInvitationToRegenerate(null),
    );
  }

  async function copyInvitationUrl() {
    if (invitation_url && (await copy(invitation_url))) {
      toast.success('Enlace copiado.');
    }
  }

  return (
    <>
      <Head title="Members" />

      <div className="space-y-6">
        <Heading
          title={`Members of ${account.name}`}
          description="Todos los miembros de esta cuenta. Owners y Admins pueden invitar, cambiar roles y remover miembros."
        />

        {lastOwnerError ? (
          <Alert variant="destructive" data-testid="last-owner-error">
            <AlertCircle />
            <AlertTitle>No puedes dejar la cuenta sin Owner</AlertTitle>
            <AlertDescription>{lastOwnerError}</AlertDescription>
          </Alert>
        ) : null}

        {is_admin ? (
          <InviteMemberForm
            inviteState={inviteState}
            inviteMember={inviteMember}
            isOwner={is_owner}
            roleOptions={roleOptions}
          />
        ) : null}

        <MemberRoster
          members={enrichedMembers}
          busyMemberId={busyMemberId}
          isAdmin={is_admin}
          isOwner={is_owner}
          roleOptions={roleOptions}
          onRoleChange={changeRole}
          onRemove={setMemberToRemove}
        />

        {is_admin ? (
          <PendingInvitations
            invitations={invitations}
            busyInvitationId={busyInvitationId}
            revokingInvitationId={revokingInvitationId}
            onRevoke={setInvitationToRevoke}
            onRegenerate={setInvitationToRegenerate}
          />
        ) : null}

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

        <InvitationActionDialogs
          invitationToRevoke={invitationToRevoke}
          onCloseRevoke={() => setInvitationToRevoke(null)}
          onConfirmRevoke={confirmRevokeInvitation}
          revokingInvitationId={revokingInvitationId}
          invitationToRegenerate={invitationToRegenerate}
          onCloseRegenerate={() => setInvitationToRegenerate(null)}
          onConfirmRegenerate={confirmRegenerate}
          busyInvitationId={busyInvitationId}
          invitationUrl={invitation_url}
          onCopyInvitationUrl={copyInvitationUrl}
        />
      </div>
    </>
  );
}

function InvitationActionDialogs({
  invitationToRevoke,
  onCloseRevoke,
  onConfirmRevoke,
  revokingInvitationId,
  invitationToRegenerate,
  onCloseRegenerate,
  onConfirmRegenerate,
  busyInvitationId,
  invitationUrl,
  onCopyInvitationUrl,
}: {
  invitationToRevoke: PendingInvitation | null;
  onCloseRevoke: () => void;
  onConfirmRevoke: () => void;
  revokingInvitationId: string | null;
  invitationToRegenerate: PendingInvitation | null;
  onCloseRegenerate: () => void;
  onConfirmRegenerate: () => void;
  busyInvitationId: string | null;
  invitationUrl: string | null;
  onCopyInvitationUrl: () => void;
}) {
  return (
    <>
      <Dialog
        open={invitationToRevoke !== null}
        onOpenChange={(open) => !open && onCloseRevoke()}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>¿Revocar invitación?</DialogTitle>
            <DialogDescription>
              La invitación para{' '}
              {invitationToRevoke?.email ?? 'este destinatario'} dejará de
              funcionar inmediatamente.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="secondary" />}>
              Cancelar
            </DialogClose>
            <Button
              type="button"
              variant="destructive"
              onClick={onConfirmRevoke}
              disabled={
                invitationToRevoke !== null &&
                revokingInvitationId === invitationToRevoke.id
              }
              data-testid="confirm-revoke-invitation"
            >
              Revocar invitación
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog
        open={invitationToRegenerate !== null}
        onOpenChange={(open) => !open && onCloseRegenerate()}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>¿Regenerar enlace de invitación?</DialogTitle>
            <DialogDescription>
              El enlace anterior dejará de funcionar. Se creará un enlace nuevo
              para {invitationToRegenerate?.email ?? 'esta invitación'} con
              siete días de vigencia.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="secondary" />}>
              Cancelar
            </DialogClose>
            <Button
              type="button"
              onClick={onConfirmRegenerate}
              disabled={
                invitationToRegenerate !== null &&
                busyInvitationId === invitationToRegenerate.id
              }
              data-testid="confirm-regenerate-invitation"
            >
              <RefreshCw />
              {invitationToRegenerate !== null &&
              busyInvitationId === invitationToRegenerate.id
                ? 'Regenerando…'
                : 'Regenerar enlace'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={invitationUrl !== null}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Enlace de invitación nuevo</DialogTitle>
            <DialogDescription>
              Compártelo con la persona invitada. Solo se muestra una vez.
            </DialogDescription>
          </DialogHeader>
          <p className="break-all rounded-md bg-muted p-3 font-mono text-xs">
            {invitationUrl}
          </p>
          <DialogFooter>
            <Button
              type="button"
              onClick={onCopyInvitationUrl}
              data-testid="copy-invitation-url"
            >
              Copiar enlace
            </Button>
            <DialogClose
              render={
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => router.reload()}
                />
              }
            >
              Cerrar
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

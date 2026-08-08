import type { InertiaFormProps } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import type { SubmitEvent } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type { AccountMember, MemberRole } from '../contracts';
import type { RoleOption } from '../model';

export type { AccountMember, MemberRole } from '../contracts';

type InviteForm = {
  email: string;
  role: MemberRole;
};

type InviteMemberFormProps = {
  inviteState: InertiaFormProps<InviteForm>;
  inviteMember: () => void;
  isOwner: boolean;
  roleOptions: (isOwner: boolean) => RoleOption[];
};

export function InviteMemberForm({
  inviteState,
  inviteMember,
  isOwner,
  roleOptions,
}: InviteMemberFormProps) {
  function onSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    inviteMember();
  }

  const options = roleOptions(isOwner);

  return (
    <Card data-testid="invite-member-form">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <UserPlus className="size-4" />
          Invitar miembro
        </CardTitle>
        <CardDescription>
          Envía una invitación por email y elige el rol inicial.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form
          onSubmit={onSubmit}
          className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end"
        >
          <div className="grid gap-2">
            <Label htmlFor="invite-email">Email</Label>
            <Input
              id="invite-email"
              name="email"
              type="email"
              value={inviteState.data.email}
              onChange={(event) =>
                inviteState.setData('email', event.target.value)
              }
              placeholder="persona@empresa.com"
              autoComplete="email"
              required
              aria-invalid={Boolean(inviteState.errors.email)}
              aria-describedby={
                inviteState.errors.email ? 'invite-email-error' : undefined
              }
            />
            <InputError
              id="invite-email-error"
              message={inviteState.errors.email}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="invite-role">Rol</Label>
            <Select
              name="role"
              value={inviteState.data.role}
              onValueChange={(role) =>
                inviteState.setData('role', role as MemberRole)
              }
            >
              <SelectTrigger
                id="invite-role"
                className="w-full"
                aria-describedby={
                  inviteState.errors.role ? 'invite-role-error' : undefined
                }
                aria-invalid={Boolean(inviteState.errors.role)}
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {options.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <InputError
              id="invite-role-error"
              message={inviteState.errors.role}
            />
          </div>

          <Button
            type="submit"
            disabled={inviteState.processing}
            data-testid="invite-member-submit"
          >
            <UserPlus />
            {inviteState.processing ? 'Invitando…' : 'Invitar'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

type MemberActionsCellProps = {
  member: AccountMember;
  isOwner: boolean;
  soleOwnerSelf: boolean;
  busy: boolean;
  roleOptions: (isOwner: boolean) => RoleOption[];
  onRoleChange: (member: AccountMember, role: MemberRole) => void;
  onRemove: (member: AccountMember) => void;
};

export function MemberActionsCell({
  member,
  isOwner,
  soleOwnerSelf,
  busy,
  roleOptions,
  onRoleChange,
  onRemove,
}: MemberActionsCellProps) {
  const options = roleOptions(isOwner);

  return (
    <div className="flex flex-wrap items-center gap-2 sm:justify-end sm:gap-3">
      <div className="grid gap-1">
        <Label htmlFor={`member-role-${member.id}`} className="sr-only">
          Rol de {member.name || member.email}
        </Label>
        <Select
          value={member.role}
          onValueChange={(role) => onRoleChange(member, role as MemberRole)}
          disabled={busy || soleOwnerSelf}
        >
          <SelectTrigger
            id={`member-role-${member.id}`}
            className="w-32"
            data-testid={`member-role-select-${member.id}`}
            data-locked={soleOwnerSelf ? 'true' : 'false'}
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {options.map((option) => (
              <SelectItem
                key={option.value}
                value={option.value}
                data-testid={`member-role-option-${member.id}-${option.value}`}
              >
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {soleOwnerSelf && (
          <p className="max-w-56 text-xs text-amber-700 dark:text-amber-300">
            Eres el único Owner — no puedes degradarte
          </p>
        )}
      </div>

      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() => onRemove(member)}
        disabled={busy || member.is_you || soleOwnerSelf}
        className="border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
        aria-label={`Remover a ${member.name || member.email}`}
        data-testid={`remove-member-${member.id}`}
      >
        <Trash2 />
        Remover
      </Button>
    </div>
  );
}

type ConfirmRemoveMemberDialogProps = {
  accountName: string;
  member: AccountMember | null;
  processing: boolean;
  onClose: () => void;
  onConfirm: () => void;
};

export function ConfirmRemoveMemberDialog({
  accountName,
  member,
  processing,
  onClose,
  onConfirm,
}: ConfirmRemoveMemberDialogProps) {
  return (
    <Dialog open={member !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>¿Remover miembro?</DialogTitle>
          <DialogDescription>
            {member?.name || member?.email} perderá acceso a {accountName}. Esta
            acción no elimina su usuario ni sus otras cuentas.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="secondary">
              Cancelar
            </Button>
          </DialogClose>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={processing}
            data-testid="confirm-remove-member"
          >
            Remover miembro
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

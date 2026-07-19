import { router, useForm } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

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
import { store } from '@/routes/accounts/members';

export type MemberRole = 'owner' | 'admin' | 'member' | 'viewer';

export type AccountMember = {
    id: number;
    name: string;
    email: string;
    role: MemberRole;
    joined_at: string;
    is_you: boolean;
};

type InviteForm = {
    email: string;
    role: MemberRole;
};

type InviteMemberFormProps = {
    accountId: string;
    isOwner: boolean;
};

export function InviteMemberForm({
    accountId,
    isOwner,
}: InviteMemberFormProps) {
    const form = useForm<InviteForm>({ email: '', role: 'member' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(store(accountId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('email');
                router.flash({
                    toast: {
                        type: 'success',
                        message: 'Invitación creada.',
                    },
                });
            },
            onHttpException: (response) => {
                toast.error(
                    response.status === 403
                        ? 'No tenés permiso para invitar miembros.'
                        : 'No se pudo crear la invitación.',
                );

                return false;
            },
            onNetworkError: () => {
                toast.error('No se pudo conectar con el servidor.');

                return false;
            },
        });
    }

    return (
        <Card data-testid="invite-member-form">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <UserPlus className="size-4" />
                    Invitar miembro
                </CardTitle>
                <CardDescription>
                    Enviá una invitación por email y elegí el rol inicial.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={submit}
                    className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="invite-email">Email</Label>
                        <Input
                            id="invite-email"
                            name="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            placeholder="persona@empresa.com"
                            autoComplete="email"
                            required
                            aria-invalid={Boolean(form.errors.email)}
                            aria-describedby={
                                form.errors.email
                                    ? 'invite-email-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="invite-email-error"
                            message={form.errors.email}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="invite-role">Rol</Label>
                        <Select
                            name="role"
                            value={form.data.role}
                            onValueChange={(role) =>
                                form.setData('role', role as MemberRole)
                            }
                        >
                            <SelectTrigger
                                id="invite-role"
                                className="w-full"
                                aria-describedby={
                                    form.errors.role
                                        ? 'invite-role-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(form.errors.role)}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {isOwner && (
                                    <SelectItem value="owner">Owner</SelectItem>
                                )}
                                <SelectItem value="admin">Admin</SelectItem>
                                <SelectItem value="member">Member</SelectItem>
                                <SelectItem value="viewer">Viewer</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            id="invite-role-error"
                            message={form.errors.role}
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={form.processing}
                        data-testid="invite-member-submit"
                    >
                        <UserPlus />
                        {form.processing ? 'Invitando…' : 'Invitar'}
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
    onRoleChange: (member: AccountMember, role: MemberRole) => void;
    onRemove: (member: AccountMember) => void;
};

export function MemberActionsCell({
    member,
    isOwner,
    soleOwnerSelf,
    busy,
    onRoleChange,
    onRemove,
}: MemberActionsCellProps) {
    return (
        <div className="flex flex-wrap items-center gap-2 sm:justify-end sm:gap-3">
            <div className="grid gap-1">
                <Label htmlFor={`member-role-${member.id}`} className="sr-only">
                    Rol de {member.name || member.email}
                </Label>
                <Select
                    value={member.role}
                    onValueChange={(role) =>
                        onRoleChange(member, role as MemberRole)
                    }
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
                        {isOwner && (
                            <SelectItem value="owner">Owner</SelectItem>
                        )}
                        <SelectItem value="admin">Admin</SelectItem>
                        <SelectItem value="member">Member</SelectItem>
                        <SelectItem value="viewer">Viewer</SelectItem>
                    </SelectContent>
                </Select>
                {soleOwnerSelf && (
                    <p className="max-w-56 text-xs text-amber-700 dark:text-amber-300">
                        Sos el único Owner — no podés degradarte
                    </p>
                )}
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onRemove(member)}
                disabled={busy || member.is_you}
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
        <Dialog
            open={member !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Remover miembro?</DialogTitle>
                    <DialogDescription>
                        {member?.name || member?.email} perderá acceso a{' '}
                        {accountName}. Esta acción no elimina su usuario ni sus
                        otras cuentas.
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

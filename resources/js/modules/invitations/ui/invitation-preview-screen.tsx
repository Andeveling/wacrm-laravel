import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { getInvitationStatus } from '../status';

type PreviewProps = {
  status: 'valid' | 'used' | 'expired' | 'invalid';
  account_name: string | null;
  role: string | null;
  inviter_name: string | null;
  label: string | null;
  expires_at: string | null;
  token: string;
};

export default function PreviewInvitation(props: PreviewProps) {
  const { status, account_name, role, inviter_name, label, expires_at, token } =
    props;
  const copy = getInvitationStatus(status);

  return (
    <>
      <Head title="Invitation" />

      <div className="mx-auto max-w-xl space-y-6">
        <Heading title={copy.title} description={copy.description} />

        {status === 'valid' && (
          <div className="space-y-4 rounded-lg border border-sidebar-border p-6">
            <dl className="space-y-3 text-sm">
              <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Account</dt>
                <dd className="font-medium">{account_name}</dd>
              </div>
              {label !== null && (
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">Note</dt>
                  <dd className="font-medium">{label}</dd>
                </div>
              )}
              <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Invited by</dt>
                <dd className="font-medium">{inviter_name}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Role</dt>
                <dd className="font-medium capitalize">{role}</dd>
              </div>
              {expires_at !== null && (
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">Expires</dt>
                  <dd className="font-medium">
                    {new Date(expires_at).toLocaleString()}
                  </dd>
                </div>
              )}
            </dl>

            <Button asChild className="w-full">
              <Link href={register({ query: { invite: token } })}>
                Accept and register
              </Link>
            </Button>
          </div>
        )}
      </div>
    </>
  );
}

import { Loader2, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ApiKey } from '../api-key-contracts';
import { computeApiKeyStatus, getApiKeyStatus } from '../api-key-status';

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function usageLabel(apiKey: ApiKey, status: string): string {
  const created = apiKey.created_at ? formatDate(apiKey.created_at) : '—';
  const used = apiKey.last_used_at
    ? `Usada por última vez ${formatDate(apiKey.last_used_at)}`
    : 'Nunca usada';
  const expires =
    apiKey.expires_at && status !== 'expired'
      ? ` · Expira ${formatDate(apiKey.expires_at)}`
      : '';

  return `Creada ${created} · ${used}${expires}`;
}

export function ApiKeyRow({
  apiKey,
  revoking,
  onRevoke,
}: {
  apiKey: ApiKey;
  revoking: boolean;
  onRevoke?: () => void;
}) {
  const status = computeApiKeyStatus(apiKey);
  const inactive = status !== 'active';
  const statusDisplay = getApiKeyStatus(status);

  return (
    <li className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span
            className={`truncate text-sm font-medium ${inactive ? 'text-muted-foreground line-through' : 'text-foreground'}`}
          >
            {apiKey.name}
          </span>
          {status !== 'active' ? (
            <Badge
              variant={statusDisplay.variant}
              className={statusDisplay.classes}
            >
              {statusDisplay.label}
            </Badge>
          ) : null}
        </div>
        <p className="mt-0.5 font-mono text-xs text-muted-foreground">
          {apiKey.key_prefix}…
        </p>
        <div className="mt-1.5 flex flex-wrap gap-1">
          {apiKey.scopes.length === 0 ? (
            <span className="text-xs text-muted-foreground">
              Sin permisos específicos
            </span>
          ) : (
            apiKey.scopes.map((scope) => (
              <Badge key={scope} variant="outline" className="text-[10px]">
                {scope}
              </Badge>
            ))
          )}
        </div>
        <p className="mt-1.5 text-xs text-muted-foreground">
          {usageLabel(apiKey, status)}
        </p>
      </div>

      {onRevoke && status === 'active' ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onRevoke}
          disabled={revoking}
          className="self-start border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/20 sm:self-auto"
        >
          {revoking ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <Trash2 className="size-4" />
          )}
          Revocar
        </Button>
      ) : null}
    </li>
  );
}

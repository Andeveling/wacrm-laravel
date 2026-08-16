import type { ApiKey } from '../types';

/** Effective runtime status derived from `revoked_at` / `expires_at`. */
export type ApiKeyStatus = 'active' | 'revoked' | 'expired';

/** Badge variant the shadcn `Badge` primitive understands. */
export type ApiKeyStatusBadgeVariant =
  | 'default'
  | 'secondary'
  | 'outline'
  | 'destructive';

/** Per-status badge display info. */
export interface ApiKeyStatusDisplay {
  label: string;
  variant: ApiKeyStatusBadgeVariant;
  /** Optional extra Tailwind classes appended to the badge. */
  classes?: string;
}

/**
 * Projects an `ApiKey` row to its effective status. `revoked_at` wins over
 * `expires_at` because a revocation is a deliberate operator action.
 */
export function computeApiKeyStatus(
  apiKey: Pick<ApiKey, 'revoked_at' | 'expires_at'>,
): ApiKeyStatus {
  if (apiKey.revoked_at) {
    return 'revoked';
  }
  if (
    apiKey.expires_at &&
    new Date(apiKey.expires_at).getTime() <= Date.now()
  ) {
    return 'expired';
  }
  return 'active';
}

const apiKeyStatusConfig: Record<ApiKeyStatus, ApiKeyStatusDisplay> = {
  active: { label: 'Activa', variant: 'default' },
  revoked: {
    label: 'Revocada',
    variant: 'outline',
    classes: 'text-[10px] tracking-wide uppercase',
  },
  expired: {
    label: 'Expirada',
    variant: 'outline',
    classes: 'text-[10px] tracking-wide uppercase',
  },
};

/** Tolerant lookup — unknown statuses render as `active`. */
export function getApiKeyStatus(status: string): ApiKeyStatusDisplay {
  return (
    apiKeyStatusConfig[status as ApiKeyStatus] ?? apiKeyStatusConfig.active
  );
}

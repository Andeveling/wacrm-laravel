import type { AccountMembership, Auth, CurrentAccount } from '@/types/auth';

declare module 'react' {
  interface InputHTMLAttributes<T> {
    passwordrules?: string;
  }
}

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      name: string;
      auth: Auth;
      currentAccount: CurrentAccount | null;
      accounts: AccountMembership[];
      hasWhatsappConnection: boolean;
      sidebarOpen: boolean;
      [key: string]: unknown;
    };
  }
}

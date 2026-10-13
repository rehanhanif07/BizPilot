import type { Auth } from '@/types/auth';
import type { Organization } from '@/types/organization';

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
            sidebarOpen: boolean;
            currentOrganization: Organization | null;
            [key: string]: unknown;
        };
    }
}

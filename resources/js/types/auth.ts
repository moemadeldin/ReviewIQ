export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    github_token?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Workspace = {
    id: string;
    name: string;
    slug: string;
    owner_id: string;
    created_at: string;
    updated_at: string;
    pivot?: {
        workspace_id: string;
        user_id: string;
        role: string;
    };
};

export type Auth = {
    user: User;
    workspaces: Workspace[];
    currentWorkspace: Workspace | null;
    role: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

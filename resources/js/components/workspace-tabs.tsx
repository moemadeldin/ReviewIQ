import { Link } from '@inertiajs/react';
import {
    FileCheck,
    GitBranch,
    LayoutDashboard,
    Mail,
    Settings,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

export type WorkspaceTabKey =
    | 'overview'
    | 'reviews'
    | 'repositories'
    | 'members'
    | 'invitations'
    | 'settings';

export interface WorkspaceTabCounts {
    reviews: number;
    repositories: number;
    members: number;
    invitations: number;
}

interface TabDefinition {
    key: WorkspaceTabKey;
    label: string;
    href: (workspaceId: string) => string;
    icon?: LucideIcon;
    countKey?: keyof WorkspaceTabCounts;
}

const tabs: TabDefinition[] = [
    {
        key: 'overview',
        label: 'Overview',
        href: (id) => `/workspaces/${id}`,
        icon: LayoutDashboard,
    },
    {
        key: 'reviews',
        label: 'Reviews',
        href: (id) => `/workspaces/${id}/reviews`,
        icon: FileCheck,
        countKey: 'reviews',
    },
    {
        key: 'repositories',
        label: 'Repositories',
        href: (id) => `/workspaces/${id}/repos`,
        icon: GitBranch,
        countKey: 'repositories',
    },
    {
        key: 'members',
        label: 'Members',
        href: (id) => `/workspaces/${id}/members`,
        icon: Users,
        countKey: 'members',
    },
    {
        key: 'invitations',
        label: 'Invitations',
        href: (id) => `/workspaces/${id}/invitations`,
        icon: Mail,
        countKey: 'invitations',
    },
    {
        key: 'settings',
        label: 'Settings',
        href: (id) => `/workspaces/${id}/settings`,
        icon: Settings,
    },
];

interface WorkspaceTabsProps {
    workspaceId: string;
    active: WorkspaceTabKey;
    counts: WorkspaceTabCounts;
    className?: string;
}

export function WorkspaceTabs({
    workspaceId,
    active,
    counts,
    className,
}: WorkspaceTabsProps) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div
            className={cn(
                'flex items-center gap-1 overflow-x-auto border-b border-border/70 pb-px',
                className,
            )}
        >
            {tabs.map((tab) => {
                const href = tab.href(workspaceId);
                const isActive =
                    active === tab.key || isCurrentOrParentUrl(href);
                const count = tab.countKey ? counts[tab.countKey] : null;

                return (
                    <Link
                        key={tab.key}
                        href={href}
                        className={cn(
                            'inline-flex shrink-0 items-center gap-2 rounded-t-lg border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                            isActive
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                        )}
                    >
                        {tab.icon && <tab.icon className="size-4" />}
                        <span>{tab.label}</span>
                        {count !== null && count > 0 && (
                            <span
                                className={cn(
                                    'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums',
                                    isActive
                                        ? 'bg-primary/15 text-primary'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {count > 99 ? '99+' : count}
                            </span>
                        )}
                    </Link>
                );
            })}
        </div>
    );
}

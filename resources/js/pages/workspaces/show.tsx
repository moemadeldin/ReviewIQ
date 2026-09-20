import { Head, usePage } from '@inertiajs/react';
import { RoleBadge } from '@/components/role-badge';
import { PageHeader } from '@/components/ui/page-header';
import { StatCard } from '@/components/ui/stat-card';
import {
    WorkspaceTabs,
    type WorkspaceTabCounts,
} from '@/components/workspace-tabs';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

interface WorkspaceShowProps {
    workspace: Workspace;
    tabCounts: WorkspaceTabCounts;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function WorkspaceShow() {
    const { auth, workspace, tabCounts } = usePage<
        { auth: Auth } & WorkspaceShowProps
    >().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={workspace.name} />

            <div className="space-y-6 px-4">
                <PageHeader
                    title={workspace.name}
                    subtitle="Overview and activity for this workspace"
                />

                <WorkspaceTabs
                    workspaceId={workspace.id}
                    active="overview"
                    counts={tabCounts}
                />

                <div>
                    <h3 className="text-lg font-semibold tracking-tight">
                        {workspace.name}
                    </h3>
                    <div className="mt-1">
                        <RoleBadge
                            role={
                                workspace.owner_id === auth.user.id
                                    ? 'owner'
                                    : 'member'
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Reviews" value={tabCounts.reviews} />
                    <StatCard
                        label="Repositories"
                        value={tabCounts.repositories}
                    />
                    <StatCard label="Members" value={tabCounts.members} />
                    <StatCard
                        label="Pending invitations"
                        value={tabCounts.invitations}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { Users, GitBranch, Mail } from 'lucide-react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Workspace } from '@/types';

interface WorkspaceShowProps {
    workspace: Workspace;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Show() {
    const { workspace } = usePage<WorkspaceShowProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={workspace.name} />

            <div className="space-y-6 px-4">
                <Heading
                    title={workspace.name}
                    description="Manage your workspace"
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        href={`/workspaces/${workspace.slug}/members`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <Users className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Members</div>
                            <div className="text-sm text-muted-foreground">
                                View and manage workspace members
                            </div>
                        </div>
                    </Link>

                    <Link
                        href={`/workspaces/${workspace.slug}/invitations`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <Mail className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Invitations</div>
                            <div className="text-sm text-muted-foreground">
                                View pending invitations
                            </div>
                        </div>
                    </Link>

                    <Link
                        href={`/workspaces/${workspace.slug}/repos`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <GitBranch className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Repositories</div>
                            <div className="text-sm text-muted-foreground">
                                View connected repositories
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

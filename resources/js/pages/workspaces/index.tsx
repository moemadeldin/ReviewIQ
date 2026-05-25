import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Index() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspaces" />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Your workspaces"
                        description="Select a workspace or create a new one"
                    />

                    <Link href="/workspaces/create">
                        <Button>Create workspace</Button>
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {auth.workspaces.map((workspace) => (
                        <WorkspaceCard
                            key={workspace.id}
                            workspace={workspace}
                            isCurrent={
                                auth.currentWorkspace?.id === workspace.id
                            }
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function WorkspaceCard({
    workspace,
    isCurrent,
}: {
    workspace: Workspace;
    isCurrent: boolean;
}) {
    return (
        <Card
            className={`transition-colors hover:bg-muted/50 ${
                isCurrent ? 'ring-2 ring-primary' : ''
            }`}
        >
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-lg">{workspace.name}</CardTitle>
                    {isCurrent && (
                        <span className="rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                            Current
                        </span>
                    )}
                </div>
                <CardDescription>
                    {workspace.pivot?.role === 'owner'
                        ? 'Owner'
                        : workspace.pivot?.role === 'admin'
                          ? 'Admin'
                          : 'Member'}
                </CardDescription>
                <div className="mt-2 flex gap-2">
                    <Link
                        href={`/workspaces/${workspace.slug}`}
                        className="flex-1"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                        >
                            <ArrowRight className="mr-2 h-4 w-4" />
                            View
                        </Button>
                    </Link>
                </div>
            </CardHeader>
        </Card>
    );
}

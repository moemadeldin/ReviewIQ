import { Head, Link, router, usePage } from '@inertiajs/react';
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
import { cn } from '@/lib/utils';
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
    const href = `/workspaces/${workspace.id}`;

    return (
        <Card
            role="link"
            tabIndex={0}
            onClick={() => router.visit(href)}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    router.visit(href);
                }
            }}
            className={cn(
                'cursor-pointer border-border/70 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                isCurrent &&
                    'border-primary/40 shadow-lg ring-1 shadow-primary/10 ring-primary/50',
            )}
        >
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-lg tracking-tight">
                        {workspace.name}
                    </CardTitle>
                    {isCurrent && (
                        <span className="rounded-full bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">
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
                        href={href}
                        onClick={(e) => e.stopPropagation()}
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

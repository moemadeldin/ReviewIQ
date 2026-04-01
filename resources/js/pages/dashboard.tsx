import { Head, Link, usePage } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { Auth, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 p-6">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        <div className="relative z-10 flex h-full flex-col justify-between">
                            <div className="space-y-2">
                                <h2 className="text-2xl font-semibold">
                                    Welcome back, {auth.user.name}!
                                </h2>
                                <p className="text-muted-foreground">
                                    You have {auth.workspaces.length} workspace
                                    {auth.workspaces.length !== 1 ? 's' : ''}
                                </p>
                                {auth.currentWorkspace && (
                                    <p className="text-sm">
                                        Current workspace:{' '}
                                        <span className="font-medium">
                                            {auth.currentWorkspace.name}
                                        </span>
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-4 text-sm">
                                <Link
                                    href="/workspaces"
                                    className="text-primary hover:underline"
                                >
                                    View all workspaces
                                </Link>
                                <Link
                                    href="/workspaces/create"
                                    className="text-primary hover:underline"
                                >
                                    Create new
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </AppLayout>
    );
}

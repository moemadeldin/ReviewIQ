import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

interface ConnectedRepo {
    id: string;
    full_name: string;
    language: string | null;
    is_active: boolean;
    webhook_id: string | null;
    connected_at: string;
}

interface ReposPageProps {
    workspace: Workspace;
    userRole: string;
    initialRepos: ConnectedRepo[];
    reposCurrentPage: number;
    reposHasMore: boolean;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Repos() {
    const {
        workspace,
        userRole,
        initialRepos,
        reposCurrentPage,
        reposHasMore,
    } = usePage<{ auth: Auth } & ReposPageProps>().props;
    const role = userRole;
    const isOwner = role === 'owner';
    const [repos, setRepos] = useState<ConnectedRepo[]>(initialRepos || []);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(reposCurrentPage || 1);
    const [hasMore, setHasMore] = useState(reposHasMore || false);
    const [toggling, setToggling] = useState<Record<string, boolean>>({});

    const fetchRepos = async (pageNum: number) => {
        setLoading(true);
        try {
            const response = await fetch(
                `/workspaces/${workspace.slug}/repos/data?page=${pageNum}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );
            const result = await response.json();
            if (result.status === 'Success') {
                setRepos(result.data.repositories);
                setHasMore(result.data.has_more);
                setPage(result.data.current_page);
            }
        } catch (error) {
            console.error('Failed to fetch repos:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleToggle = async (
        repoFullName: string,
        currentStatus: boolean,
    ) => {
        setToggling((prev) => ({ ...prev, [repoFullName]: true }));

        try {
            const method = currentStatus ? 'DELETE' : 'POST';
            const response = await fetch(`/workspaces/${workspace.slug}/repos/${repoFullName}`, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
            });

            const result = await response.json();

            if (result.status === 'Success') {
                setRepos((prev) =>
                    prev.map((repo) =>
                        repo.full_name === repoFullName
                            ? { ...repo, is_active: !currentStatus }
                            : repo,
                    ),
                );
            }
        } catch (error) {
            console.error('Failed to toggle repo:', error);
        } finally {
            setToggling((prev) => ({ ...prev, [repoFullName]: false }));
        }
    };

    const prevPage = () => {
        if (page > 1) {
            void fetchRepos(page - 1);
        }
    };

    const nextPage = () => {
        if (hasMore) {
            void fetchRepos(page + 1);
        }
    };

    const currentBreadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbs,
        {
            title: workspace.name,
            href: `/workspaces/${workspace.slug}`,
        },
        {
            title: 'Repositories',
            href: `/workspaces/${workspace.slug}/repos`,
        },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`${workspace.name} - Repositories`} />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Connected Repositories"
                        description={`${repos.length} repositor${repos.length !== 1 ? 'y' : 'ies'} connected to ${workspace.name}`}
                    />
                </div>

                <Card>
                    <CardContent className="pt-6">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                            </div>
                        ) : repos.length === 0 ? (
                            <div className="py-12 text-center">
                                <div className="mb-2 text-lg font-medium text-muted-foreground">
                                    No repositories connected
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Connect repositories from the Repositories
                                    page to start tracking PRs
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-left">
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Repository
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Language
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Connected
                                                </th>
                                                {isOwner && (
                                                    <th className="px-4 py-3 text-sm font-medium">
                                                        Toggle
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {repos.map((repo) => (
                                                <tr
                                                    key={repo.id}
                                                    className={cn(
                                                        'border-b',
                                                        repo.is_active &&
                                                            'bg-green-50/50',
                                                    )}
                                                >
                                                    <td className="px-4 py-3">
                                                        <span className="font-medium">
                                                            {repo.full_name}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {repo.language ? (
                                                            <Badge variant="outline">
                                                                {repo.language}
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={
                                                                repo.is_active
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {repo.is_active
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {new Date(
                                                            repo.connected_at,
                                                        ).toLocaleDateString()}
                                                    </td>
                                                    {isOwner && (
                                                        <td className="px-4 py-3">
                                                            <Button
                                                                size="sm"
                                                                variant={
                                                                    repo.is_active
                                                                        ? 'destructive'
                                                                        : 'default'
                                                                }
                                                                onClick={() =>
                                                                    handleToggle(
                                                                        repo.full_name,
                                                                        repo.is_active,
                                                                    )
                                                                }
                                                                disabled={
                                                                    toggling[
                                                                        repo
                                                                            .full_name
                                                                    ]
                                                                }
                                                            >
                                                                {toggling[
                                                                    repo
                                                                        .full_name
                                                                ]
                                                                    ? '...'
                                                                    : repo.is_active
                                                                      ? 'Disable'
                                                                      : 'Enable'}
                                                            </Button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="mt-4 flex items-center justify-between">
                                    <Button
                                        variant="outline"
                                        onClick={prevPage}
                                        disabled={page <= 1}
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-sm text-muted-foreground">
                                        Page {page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        onClick={nextPage}
                                        disabled={!hasMore}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

import { Head, usePage } from '@inertiajs/react';
import { FileCode2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

interface Repository {
    id: string;
    full_name: string;
}

interface Review {
    id: string;
    summary: string | null;
    score: number | null;
    recommendation: string | null;
    created_at: string;
}

interface PullRequest {
    id: string;
    title: string | null;
    number: number | null;
    author: string | null;
    diff_url: string | null;
    status: string;
    created_at: string;
    repository: Repository | null;
    review: Review | null;
}

interface ReviewsPageProps {
    workspace: Workspace;
    initialPullRequests: PullRequest[];
    currentPage: number;
    hasMore: boolean;
    repositories: Repository[];
    filters: {
        repository_id: string | null;
        status: string;
    };
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Reviews() {
    const {
        workspace,
        initialPullRequests,
        currentPage,
        hasMore,
        repositories,
        filters,
    } = usePage<{ auth: Auth } & ReviewsPageProps>().props;

    const [pullRequests, setPullRequests] = useState<PullRequest[]>(
        initialPullRequests || [],
    );
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(currentPage || 1);
    const [hasMoreState, setHasMoreState] = useState(hasMore || false);
    const [selectedRepo, setSelectedRepo] = useState<string>(
        filters.repository_id || 'all',
    );
    const [selectedStatus, setSelectedStatus] = useState<string>(
        filters.status || 'all',
    );

    const fetchPullRequests = async (
        pageNum: number,
        repo?: string,
        status?: string,
    ) => {
        setLoading(true);
        try {
            const params = new URLSearchParams({ page: pageNum.toString() });
            if (repo && repo !== 'all') params.set('repository_id', repo);
            if (status && status !== 'all') params.set('status', status);

            const response = await fetch(
                `/workspaces/${workspace.slug}/reviews/data?${params.toString()}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );
            const result = await response.json();
            if (result.status === 'Success') {
                setPullRequests(result.data.pull_requests);
                setHasMoreState(result.data.has_more);
                setPage(result.data.current_page);
            }
        } catch (error) {
            console.error('Failed to fetch PRs:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (repo: string, status: string) => {
        setSelectedRepo(repo);
        setSelectedStatus(status);
        void fetchPullRequests(1, repo, status);
    };

    const prevPage = () => {
        if (page > 1) {
            void fetchPullRequests(page - 1, selectedRepo, selectedStatus);
        }
    };

    const nextPage = () => {
        if (hasMoreState) {
            void fetchPullRequests(page + 1, selectedRepo, selectedStatus);
        }
    };

    const currentBreadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbs,
        {
            title: workspace.name,
            href: `/workspaces/${workspace.slug}`,
        },
        {
            title: 'Reviews',
            href: `/workspaces/${workspace.slug}/reviews`,
        },
    ];

    const getScoreColor = (score: number | null): string => {
        if (score === null) return 'bg-gray-500';
        if (score >= 80) return 'bg-green-500';
        if (score >= 50) return 'bg-amber-500';
        return 'bg-red-500';
    };

    const getStatusBadgeVariant = (
        status: string,
    ): 'default' | 'secondary' | 'outline' | 'destructive' => {
        switch (status) {
            case 'reviewed':
                return 'default';
            case 'pending':
            case 'reviewing':
                return 'outline';
            case 'failed':
                return 'destructive';
            default:
                return 'secondary';
        }
    };

    const tabs = [
        { value: 'all', label: 'All' },
        { value: 'pending', label: 'Pending' },
        { value: 'reviewed', label: 'Reviewed' },
        { value: 'failed', label: 'Failed' },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`${workspace.name} - Reviews`} />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Pull Request Reviews"
                        description={`${pullRequests.length} pull request${pullRequests.length !== 1 ? 's' : ''} in ${workspace.name}`}
                    />
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <Select
                            value={selectedRepo}
                            onValueChange={(value) =>
                                handleFilterChange(value, selectedStatus)
                            }
                        >
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="All Repositories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All Repositories
                                </SelectItem>
                                {repositories.map((repo) => (
                                    <SelectItem key={repo.id} value={repo.id}>
                                        {repo.full_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex gap-1 rounded-md bg-muted p-1">
                        {tabs.map((tab) => (
                            <Button
                                key={tab.value}
                                variant={
                                    selectedStatus === tab.value
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                onClick={() =>
                                    handleFilterChange(selectedRepo, tab.value)
                                }
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </div>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                            </div>
                        ) : pullRequests.length === 0 ? (
                            <div className="py-12 text-center">
                                <div className="mb-2 text-lg font-medium text-muted-foreground">
                                    No pull requests found
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Pull requests will appear here once they are
                                    tracked in connected repositories
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
                                                    PR Title
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Author
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Score
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Date
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {pullRequests.map((pr) => (
                                                <tr
                                                    key={pr.id}
                                                    className="border-b"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <FileCode2 className="h-4 w-4 text-muted-foreground" />
                                                            <span className="text-sm">
                                                                {pr.repository
                                                                    ?.full_name ||
                                                                    '-'}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {pr.diff_url ? (
                                                            <a
                                                                href={
                                                                    pr.diff_url
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="hover:underline"
                                                            >
                                                                {pr.title ||
                                                                    `#${pr.number}`}
                                                            </a>
                                                        ) : (
                                                            <span>
                                                                {pr.title ||
                                                                    `#${pr.number}`}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {pr.author || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {pr.review?.score !==
                                                        null ? (
                                                            <div className="flex items-center gap-2">
                                                                <div
                                                                    className={cn(
                                                                        'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white',
                                                                        getScoreColor(
                                                                            pr
                                                                                .review
                                                                                ?.score ??
                                                                                null,
                                                                        ),
                                                                    )}
                                                                >
                                                                    {
                                                                        pr
                                                                            .review
                                                                            ?.score
                                                                    }
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={getStatusBadgeVariant(
                                                                pr.status,
                                                            )}
                                                        >
                                                            {pr.status
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                                pr.status.slice(
                                                                    1,
                                                                )}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {new Date(
                                                            pr.created_at,
                                                        ).toLocaleDateString()}
                                                    </td>
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
                                        disabled={!hasMoreState}
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

import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    FileCheck,
    GitBranch,
    GitPullRequest,
    Inbox,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PrStatusBadge } from '@/components/pr-status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/components/ui/page-header';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Auth, BreadcrumbItem } from '@/types';

interface Review {
    id: string;
    summary: string | null;
    score: number | null;
    recommendation: string | null;
    created_at: string;
}

interface Repository {
    id: string;
    full_name: string;
}

interface PullRequest {
    id: string;
    title: string | null;
    number: number | null;
    author: string | null;
    status: string;
    created_at: string;
    workspace_id: string | null;
    repository: Repository | null;
    review: Review | null;
}

interface DashboardProps {
    stats: {
        workspaces: number;
        repositories: number;
        pullRequests: number;
        reviews: number;
    };
    recentPullRequests: PullRequest[];
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard() {
    const { auth, stats, recentPullRequests } = usePage<
        {
            auth: Auth;
        } & DashboardProps
    >().props;

    const getScoreChip = (score: number | null): string => {
        if (score === null) return 'bg-muted text-muted-foreground';
        if (score >= 80) return 'bg-score-high/15 text-score-high';
        if (score >= 50) return 'bg-score-medium/15 text-score-medium';
        return 'bg-score-low/15 text-score-low';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <PageHeader
                    title={`Welcome back, ${auth.user.name}`}
                    subtitle="Here's what's happening across your workspaces."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link href="/workspaces">
                        <StatCard
                            label="Workspaces"
                            value={stats.workspaces}
                            icon={Building2}
                        />
                    </Link>
                    <Link href="/repos">
                        <StatCard
                            label="Repositories"
                            value={stats.repositories}
                            icon={GitBranch}
                        />
                    </Link>
                    <StatCard
                        label="Pull requests"
                        value={stats.pullRequests}
                        icon={GitPullRequest}
                    />
                    <StatCard
                        label="Reviews"
                        value={stats.reviews}
                        icon={FileCheck}
                    />
                </div>

                <Card className="flex-1 bg-card/60">
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentPullRequests.length === 0 ? (
                            <EmptyState
                                icon={Inbox}
                                title="No pull requests yet"
                                description="Connect a repository to start reviewing pull requests."
                            />
                        ) : (
                            <div className="overflow-hidden rounded-lg border border-border/60">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b border-border/60 bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            <th className="px-4 py-3">
                                                Repository
                                            </th>
                                            <th className="px-4 py-3">
                                                Pull request
                                            </th>
                                            <th className="px-4 py-3">
                                                Author
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                            <th className="px-4 py-3">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPullRequests.map((pr) => (
                                            <tr
                                                key={pr.id}
                                                className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 text-sm text-muted-foreground">
                                                    {pr.repository?.full_name ||
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={`/workspaces/${pr.workspace_id}/reviews/${pr.id}`}
                                                        className="text-sm font-medium text-foreground hover:text-primary"
                                                    >
                                                        {pr.title ||
                                                            `#${pr.number}`}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-muted-foreground">
                                                    {pr.author || '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <PrStatusBadge
                                                        status={pr.status}
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    {pr.review?.score !==
                                                    null ? (
                                                        <span
                                                            className={cn(
                                                                'inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                                                getScoreChip(
                                                                    pr.review
                                                                        ?.score ??
                                                                        null,
                                                                ),
                                                            )}
                                                        >
                                                            {pr.review?.score}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            -
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

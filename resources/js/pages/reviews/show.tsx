import { Head, usePage } from '@inertiajs/react';
import { ExternalLink, FileCode2, GitBranch, User } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    score_rationale: string | null;
    issues: Array<{
        file: string;
        line: number;
        severity: string;
        message: string;
    }> | null;
    highlights: Array<{
        file: string;
        line: number;
        content: string;
    }> | null;
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
    head_sha: string | null;
    status: string;
    created_at: string;
    repository: Repository | null;
    review: Review | null;
}

interface PullRequestShowProps {
    workspace: Workspace;
    pullRequest: PullRequest;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function PullRequestShow() {
    const { workspace, pullRequest } = usePage<
        { auth: Auth } & PullRequestShowProps
    >().props;

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
        if (score === null) return 'text-gray-500';
        if (score >= 80) return 'text-green-500';
        if (score >= 50) return 'text-amber-500';
        return 'text-red-500';
    };

    const getScoreRingColor = (score: number | null): string => {
        if (score === null) return 'stroke-gray-500';
        if (score >= 80) return 'stroke-green-500';
        if (score >= 50) return 'stroke-amber-500';
        return 'stroke-red-500';
    };

    const getSeverityVariant = (
        severity: string,
    ): 'default' | 'secondary' | 'outline' | 'destructive' => {
        switch (severity.toLowerCase()) {
            case 'error':
            case 'critical':
                return 'destructive';
            case 'warning':
                return 'secondary';
            case 'info':
                return 'outline';
            default:
                return 'default';
        }
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

    const score = pullRequest.review?.score ?? null;
    const radius = 45;
    const circumference = 2 * Math.PI * radius;
    const progress = score !== null ? (score / 100) * circumference : 0;

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head
                title={`${pullRequest.title || `#${pullRequest.number}`} - Reviews`}
            />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={pullRequest.title || `#${pullRequest.number}`}
                        description={`Pull request in ${pullRequest.repository?.full_name || 'Unknown'}`}
                    />
                    {pullRequest.diff_url && (
                        <Button asChild>
                            <a
                                href={pullRequest.diff_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink className="mr-2 h-4 w-4" />
                                View on GitHub
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>PR Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex items-center gap-2">
                                        <GitBranch className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            Branch:
                                        </span>
                                        <code className="font-mono text-sm">
                                            {pullRequest.head_sha?.substring(
                                                0,
                                                7,
                                            ) || '-'}
                                        </code>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <User className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            Author:
                                        </span>
                                        <span className="text-sm">
                                            {pullRequest.author || '-'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <FileCode2 className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            Number:
                                        </span>
                                        <span className="text-sm">
                                            #{pullRequest.number || '-'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm text-muted-foreground">
                                            Status:
                                        </span>
                                        <Badge
                                            variant={getStatusBadgeVariant(
                                                pullRequest.status,
                                            )}
                                        >
                                            {pullRequest.status
                                                .charAt(0)
                                                .toUpperCase() +
                                                pullRequest.status.slice(1)}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {pullRequest.review?.summary && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Summary</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed">
                                        {pullRequest.review.summary}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {pullRequest.review?.score_rationale && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Score Rationale</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed">
                                        {pullRequest.review.score_rationale}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {pullRequest.review?.issues &&
                            pullRequest.review.issues.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Issues (
                                            {pullRequest.review.issues.length})
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {pullRequest.review.issues.map(
                                                (issue, index) => (
                                                    <div
                                                        key={index}
                                                        className="rounded-lg border p-4"
                                                    >
                                                        <div className="mb-2 flex items-center gap-2">
                                                            <Badge
                                                                variant={getSeverityVariant(
                                                                    issue.severity,
                                                                )}
                                                            >
                                                                {issue.severity}
                                                            </Badge>
                                                            <code className="font-mono text-sm">
                                                                {issue.file}:
                                                                {issue.line}
                                                            </code>
                                                        </div>
                                                        <p className="text-sm">
                                                            {issue.message}
                                                        </p>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                        {pullRequest.review?.highlights &&
                            pullRequest.review.highlights.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Highlights (
                                            {
                                                pullRequest.review.highlights
                                                    .length
                                            }
                                            )
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {pullRequest.review.highlights.map(
                                                (highlight, index) => (
                                                    <div
                                                        key={index}
                                                        className="rounded-lg border p-4"
                                                    >
                                                        <div className="mb-2 flex items-center gap-2">
                                                            <code className="font-mono text-sm">
                                                                {highlight.file}
                                                                :
                                                                {highlight.line}
                                                            </code>
                                                        </div>
                                                        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-sm">
                                                            <code>
                                                                {
                                                                    highlight.content
                                                                }
                                                            </code>
                                                        </pre>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                        {!pullRequest.review && (
                            <Card>
                                <CardContent className="py-12 text-center">
                                    <div className="text-lg font-medium text-muted-foreground">
                                        No review available
                                    </div>
                                    <div className="mt-2 text-sm text-muted-foreground">
                                        This pull request has not been reviewed
                                        yet
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Review Score</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col items-center">
                                    <div className="relative h-32 w-32">
                                        <svg className="h-full w-full -rotate-90">
                                            <circle
                                                cx="64"
                                                cy="64"
                                                r={radius}
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth="8"
                                                className="text-muted"
                                            />
                                            <circle
                                                cx="64"
                                                cy="64"
                                                r={radius}
                                                fill="none"
                                                strokeWidth="8"
                                                strokeLinecap="round"
                                                className={cn(
                                                    'transition-all',
                                                    getScoreRingColor(score),
                                                )}
                                                strokeDasharray={circumference}
                                                strokeDashoffset={
                                                    circumference - progress
                                                }
                                            />
                                        </svg>
                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <span
                                                className={cn(
                                                    'text-3xl font-bold',
                                                    getScoreColor(score),
                                                )}
                                            >
                                                {score ?? '-'}
                                            </span>
                                        </div>
                                    </div>
                                    {pullRequest.review?.recommendation && (
                                        <div className="mt-4 text-center">
                                            <Badge variant="outline">
                                                {
                                                    pullRequest.review
                                                        .recommendation
                                                }
                                            </Badge>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Metadata</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            Repository
                                        </div>
                                        <div className="text-sm font-medium">
                                            {pullRequest.repository
                                                ?.full_name || '-'}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            Created
                                        </div>
                                        <div className="text-sm font-medium">
                                            {new Date(
                                                pullRequest.created_at,
                                            ).toLocaleString()}
                                        </div>
                                    </div>
                                    {pullRequest.review?.created_at && (
                                        <div>
                                            <div className="text-sm text-muted-foreground">
                                                Reviewed
                                            </div>
                                            <div className="text-sm font-medium">
                                                {new Date(
                                                    pullRequest.review
                                                        .created_at,
                                                ).toLocaleString()}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

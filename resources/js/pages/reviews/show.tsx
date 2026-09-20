import { Head, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    FileCode2,
    FileSearch,
    GitBranch,
    Lightbulb,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { CodeBlock } from '@/components/code-block';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { PrStatusBadge } from '@/components/pr-status-badge';
import { ReviewStream } from '@/components/review-stream';
import { ScoreGauge } from '@/components/score-gauge';
import { SeverityBadge } from '@/components/severity-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
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
        line: number | null;
        severity: string;
        title: string | null;
        description: string | null;
        suggestion: string | null;
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

    const [liveReview, setLiveReview] = useState<Partial<Review> | null>(null);

    const currentBreadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbs,
        {
            title: workspace.name,
            href: `/workspaces/${workspace.id}`,
        },
        {
            title: 'Reviews',
            href: `/workspaces/${workspace.id}/reviews`,
        },
    ];

    const isReviewing = pullRequest.status === 'reviewing';
    const showReview = pullRequest.review || liveReview;
    const score = showReview?.score ?? null;

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head
                title={`${pullRequest.title || `#${pullRequest.number}`} - Reviews`}
            />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={pullRequest.title || `#${pullRequest.number}`}
                        description={`Pull request in ${pullRequest.repository?.full_name || 'Unknown'}`}
                    />
                    <div className="flex shrink-0 items-center gap-2">
                        {pullRequest.repository?.full_name &&
                            pullRequest.number && (
                                <Button asChild>
                                    <a
                                        href={`https://github.com/${pullRequest.repository.full_name}/pull/${pullRequest.number}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink />
                                        View on GitHub
                                    </a>
                                </Button>
                            )}
                        {showReview && (
                            <form
                                action={`/workspaces/${workspace.id}/reviews/${pullRequest.id}/re-review`}
                                method="POST"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value={
                                        (
                                            document.querySelector(
                                                'meta[name="csrf-token"]',
                                            ) as HTMLMetaElement
                                        )?.content ?? ''
                                    }
                                />
                                <Button
                                    variant="outline"
                                    type="submit"
                                    disabled={isReviewing}
                                >
                                    {isReviewing ? 'Reviewing...' : 'Re-review'}
                                </Button>
                            </form>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="bg-card/60">
                            <CardHeader>
                                <CardTitle>PR Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex items-center gap-2">
                                        <GitBranch className="size-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            Sha:
                                        </span>
                                        <code className="font-mono text-sm">
                                            {pullRequest.head_sha?.substring(
                                                0,
                                                7,
                                            ) || '-'}
                                        </code>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <User className="size-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            Author:
                                        </span>
                                        <span className="text-sm">
                                            {pullRequest.author || '-'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <FileCode2 className="size-4 text-muted-foreground" />
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
                                        <PrStatusBadge
                                            status={pullRequest.status}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {isReviewing && !showReview && (
                            <ReviewStream
                                prId={pullRequest.id}
                                onComplete={setLiveReview}
                            />
                        )}

                        {showReview?.summary && (
                            <Card className="bg-card/60">
                                <CardHeader>
                                    <CardTitle>Summary</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed text-foreground/90">
                                        {showReview.summary}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {showReview?.score_rationale && (
                            <Card className="bg-card/60">
                                <CardHeader>
                                    <CardTitle>Score Rationale</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed text-foreground/90">
                                        {showReview.score_rationale}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {showReview?.issues && showReview.issues.length > 0 && (
                            <Card className="bg-card/60">
                                <CardHeader>
                                    <CardTitle>
                                        Issues ({showReview.issues.length})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {showReview.issues.map(
                                            (issue, index) => (
                                                <div
                                                    key={index}
                                                    className="rounded-lg border border-border/70 bg-card/60 p-4"
                                                >
                                                    <div className="mb-2 flex items-center gap-2">
                                                        <SeverityBadge
                                                            severity={
                                                                issue.severity
                                                            }
                                                        />
                                                        {issue.file && (
                                                            <code className="font-mono text-xs text-muted-foreground">
                                                                {issue.file}
                                                                {issue.line !=
                                                                null
                                                                    ? `:${issue.line}`
                                                                    : ''}
                                                            </code>
                                                        )}
                                                    </div>
                                                    {issue.title && (
                                                        <p className="mb-1 text-sm font-medium">
                                                            {issue.title}
                                                        </p>
                                                    )}
                                                    {issue.description && (
                                                        <p className="mb-2 text-sm text-muted-foreground">
                                                            {issue.description}
                                                        </p>
                                                    )}
                                                    {issue.suggestion && (
                                                        <p className="flex gap-2 rounded-md border border-border/70 bg-muted/40 p-2.5 text-xs text-muted-foreground">
                                                            <Lightbulb className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                                                            {issue.suggestion}
                                                        </p>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {showReview?.highlights &&
                            showReview.highlights.length > 0 && (
                                <Card className="bg-card/60">
                                    <CardHeader>
                                        <CardTitle>
                                            Highlights (
                                            {showReview.highlights.length})
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            {showReview.highlights.map(
                                                (highlight, index) => (
                                                    <div key={index}>
                                                        {typeof highlight ===
                                                        'string' ? (
                                                            <p className="text-sm text-foreground/90">
                                                                {highlight}
                                                            </p>
                                                        ) : (
                                                            <CodeBlock
                                                                code={
                                                                    highlight.content
                                                                }
                                                                filename={`${highlight.file}${highlight.line != null ? `:${highlight.line}` : ''}`}
                                                            />
                                                        )}
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                        {!showReview && (
                            <Card className="bg-card/60">
                                <CardContent>
                                    <EmptyState
                                        icon={FileSearch}
                                        title="No review available"
                                        description="This pull request has not been reviewed yet"
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card className="bg-card/60">
                            <CardHeader>
                                <CardTitle>Review Score</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col items-center">
                                    <ScoreGauge
                                        score={score}
                                        size={144}
                                        strokeWidth={9}
                                        labelClassName="text-3xl font-bold"
                                    />
                                    {showReview?.recommendation && (
                                        <div className="mt-5 text-center">
                                            <Badge
                                                variant="outline"
                                                className="rounded-full px-3"
                                            >
                                                {showReview.recommendation}
                                            </Badge>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-card/60">
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
                                    {showReview?.created_at && (
                                        <div>
                                            <div className="text-sm text-muted-foreground">
                                                Reviewed
                                            </div>
                                            <div className="text-sm font-medium">
                                                {new Date(
                                                    showReview.created_at,
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

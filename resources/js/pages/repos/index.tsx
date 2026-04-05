import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Heading from '@/components/heading';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Auth, BreadcrumbItem } from '@/types';

interface GitHubRepo {
    id: number;
    full_name: string;
    name: string;
    private: boolean;
    language: string | null;
    html_url: string;
}

interface ConnectedRepo {
    id: string;
    workspace_id: string;
    github_repo_id: string;
    full_name: string;
    language: string | null;
    is_active: boolean;
    custom_rules: string | null;
    webhook_id: string | null;
}

interface RepositoryPageProps {
    status: string;
    message: string;
    data: {
        repositories: GitHubRepo[];
        connected_repos: Record<string, ConnectedRepo>;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Repositories',
        href: '/repos',
    },
];

export default function Index() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [repos, setRepos] = useState<GitHubRepo[]>([]);
    const [connectedRepos, setConnectedRepos] = useState<
        Record<string, ConnectedRepo>
    >({});
    const [loading, setLoading] = useState(true);
    const [toggling, setToggling] = useState<Record<string, boolean>>({});

    const isGitHubConnected = !!auth.user.github_token;

    useEffect(() => {
        if (!isGitHubConnected) {
            setLoading(false);
            return;
        }

        fetch('/repos/data')
            .then((res) => res.json())
            .then((data: RepositoryPageProps) => {
                console.log('API Response:', data)
                setRepos(data.data.repositories || []);
                setConnectedRepos(data.data.connected_repos || {});
            })
            .catch(() => { })
            .finally(() => setLoading(false));
    }, []);

    const handleToggle = async (repo: GitHubRepo) => {
        const isConnected = !!connectedRepos[repo.full_name]?.is_active;

        setToggling((prev) => ({ ...prev, [repo.full_name]: true }));

        try {
            if (isConnected) {
                const res = await fetch(`/repos/${repo.full_name}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken() },
                });

                if (res.ok) {
                    setConnectedRepos((prev) => ({
                        ...prev,
                        [repo.full_name]: {
                            ...prev[repo.full_name]!,
                            is_active: false,
                        },
                    }));
                }
            } else {
                const res = await fetch(`/repos/${repo.full_name}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken() },
                });

                if (res.ok) {
                    const data: { data: { repository: ConnectedRepo } } =
                        await res.json();
                    setConnectedRepos((prev) => ({
                        ...prev,
                        [repo.full_name]: {
                            id: data.data.repository.id,
                            workspace_id: data.data.repository.workspace_id,
                            github_repo_id: data.data.repository.github_repo_id,
                            full_name: data.data.repository.full_name,
                            language: data.data.repository.language,
                            is_active: true,
                            custom_rules: data.data.repository.custom_rules,
                            webhook_id: data.data.repository.webhook_id,
                        },
                    }));
                }
            }
        } finally {
            setToggling((prev) => ({ ...prev, [repo.full_name]: false }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Repositories" />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Your repositories"
                        description="Select which repositories to monitor for pull requests"
                    />
                </div>

                {!isGitHubConnected ? (
                    <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-yellow-800">
                        <p className="font-medium">
                            Connect your GitHub account
                        </p>
                        <p className="mt-1 text-sm">
                            Sign in with GitHub to see and manage your
                            repositories.
                        </p>
                        <a
                            href="/auth/github"
                            className="mt-3 inline-flex items-center justify-center rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                        >
                            Connect GitHub
                        </a>
                    </div>
                ) : loading ? (
                    <div className="flex items-center justify-center py-12">
                        <Spinner className="size-8" />
                    </div>
                ) : repos.length === 0 ? (
                    <div className="rounded-lg border p-4 text-center">
                        <p className="text-muted-foreground">
                            No repositories found. Make sure your GitHub account
                            has access to repositories.
                        </p>
                    </div>
                ) : (
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
                                        Toggle
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {repos.map((repo) => {
                                    const connected =
                                        connectedRepos[repo.full_name];
                                    const isActive =
                                        connected?.is_active ?? false;
                                    const isToggling =
                                        toggling[repo.full_name] ?? false;

                                    return (
                                        <tr
                                            key={repo.full_name}
                                            className={cn(
                                                'border-b transition-colors',
                                                isActive && 'bg-green-50/50',
                                            )}
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <a
                                                        href={repo.html_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="font-medium hover:underline"
                                                    >
                                                        {repo.name}
                                                    </a>
                                                    {repo.private && (
                                                        <span className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                            Private
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {repo.full_name}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {repo.language ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {isActive ? (
                                                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <button
                                                    onClick={() =>
                                                        handleToggle(repo)
                                                    }
                                                    disabled={isToggling}
                                                    className={cn(
                                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                                        isActive
                                                            ? 'bg-green-600'
                                                            : 'bg-muted',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition-transform',
                                                            isActive
                                                                ? 'translate-x-5'
                                                                : 'translate-x-0',
                                                        )}
                                                    />
                                                    {isToggling && (
                                                        <span className="absolute inset-0 flex items-center justify-center">
                                                            <Spinner className="size-3 text-white" />
                                                        </span>
                                                    )}
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Workspace } from '@/types';

interface Invitation {
    id: string;
    email: string;
    role: string;
    expires_at: string;
    created_at: string;
}

interface InvitationsPageProps {
    workspace: Workspace;
    userRole: string;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Invitations() {
    const { workspace, userRole } = usePage<InvitationsPageProps>().props;
    const [invitations, setInvitations] = useState<Invitation[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [cancelling, setCancelling] = useState<string | null>(null);

    const canManage = userRole === 'owner' || userRole === 'admin';

    const fetchInvitations = async (pageNum: number) => {
        setLoading(true);
        try {
            const response = await fetch(
                `/workspaces/${workspace.slug}/invitations/data?page=${pageNum}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );
            const result = await response.json();
            if (result.status === 'Success') {
                setInvitations(result.data.invitations);
                setHasMore(result.data.has_more);
                setPage(result.data.current_page);
            }
        } catch (error) {
            console.error('Failed to fetch invitations:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleCancel = async (invitationId: string) => {
        if (!confirm('Are you sure you want to cancel this invitation?'))
            return;

        setCancelling(invitationId);
        try {
            const response = await fetch(
                `/workspaces/${workspace.slug}/invitations/${invitationId}`,
                {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as HTMLMetaElement
                            )?.content || '',
                    },
                },
            );

            const result = await response.json();

            if (result.status === 'Success') {
                await fetchInvitations(page);
            } else {
                alert(result.message || 'Failed to cancel invitation');
            }
        } catch (error) {
            console.error('Failed to cancel invitation:', error);
            alert('Failed to cancel invitation');
        } finally {
            setCancelling(null);
        }
    };

    const prevPage = () => {
        if (page > 1) {
            void fetchInvitations(page - 1);
        }
    };

    const nextPage = () => {
        if (hasMore) {
            void fetchInvitations(page + 1);
        }
    };

    const currentBreadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbs,
        {
            title: workspace.name,
            href: `/workspaces/${workspace.slug}`,
        },
        {
            title: 'Invitations',
            href: `/workspaces/${workspace.slug}/invitations`,
        },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`${workspace.name} - Invitations`} />

            <div className="space-y-6 px-4">
                <Heading
                    title="Pending Invitations"
                    description={`${invitations.length} pending invitation${invitations.length !== 1 ? 's' : ''} to ${workspace.name}`}
                />

                <Card>
                    <CardContent className="pt-6">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                            </div>
                        ) : invitations.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                No pending invitations
                            </div>
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-left">
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Email
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Role
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Sent
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Expires
                                                </th>
                                                {canManage && (
                                                    <th className="px-4 py-3 text-right text-sm font-medium">
                                                        Actions
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {invitations.map((invitation) => (
                                                <tr
                                                    key={invitation.id}
                                                    className="border-b"
                                                >
                                                    <td className="px-4 py-3">
                                                        <span className="font-medium">
                                                            {invitation.email}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="secondary">
                                                            {invitation.role}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {new Date(
                                                            invitation.created_at,
                                                        ).toLocaleDateString()}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {new Date(
                                                            invitation.expires_at,
                                                        ).toLocaleDateString()}
                                                    </td>
                                                    {canManage && (
                                                        <td className="px-4 py-3 text-right">
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleCancel(
                                                                        invitation.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    cancelling ===
                                                                    invitation.id
                                                                }
                                                            >
                                                                {cancelling ===
                                                                invitation.id
                                                                    ? 'Cancelling...'
                                                                    : 'Cancel'}
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

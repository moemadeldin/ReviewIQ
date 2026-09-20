import { Form, Head, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    WorkspaceTabs,
    type WorkspaceTabCounts,
} from '@/components/workspace-tabs';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

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
    tabCounts: WorkspaceTabCounts;
    initialInvitations: Invitation[];
    invitationsCurrentPage: number;
    invitationsHasMore: boolean;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Invitations() {
    const {
        workspace,
        userRole,
        tabCounts,
        initialInvitations,
        invitationsCurrentPage,
        invitationsHasMore,
    } = usePage<{ auth: Auth } & InvitationsPageProps>().props;
    const role = userRole;
    const isOwner = role === 'owner';
    const [invitations, setInvitations] = useState<Invitation[]>(
        initialInvitations || [],
    );
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(invitationsCurrentPage || 1);
    const [hasMore, setHasMore] = useState(invitationsHasMore || false);
    const [cancelConfirmId, setCancelConfirmId] = useState<string | null>(null);

    const canManage = isOwner;

    const fetchInvitations = async (pageNum: number) => {
        setLoading(true);
        try {
            const response = await fetch(
                `/workspaces/${workspace.id}/invitations/data?page=${pageNum}`,
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
            href: `/workspaces/${workspace.id}`,
        },
        {
            title: 'Invitations',
            href: `/workspaces/${workspace.id}/invitations`,
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

                <WorkspaceTabs
                    workspaceId={workspace.id}
                    active="invitations"
                    counts={tabCounts}
                />

                <Card>
                    <CardContent className="pt-6">
                        {loading ? (
                            <div className="flex items-center justify-center py-10">
                                <div className="h-6 w-6 animate-spin rounded-full border-2 border-border border-t-primary" />
                            </div>
                        ) : invitations.length === 0 ? (
                            <EmptyState
                                icon={Mail}
                                title="No pending invitations"
                                description="Invite members to collaborate in this workspace"
                            />
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b border-border/60 bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                <th className="px-4 py-3">
                                                    Email
                                                </th>
                                                <th className="px-4 py-3">
                                                    Role
                                                </th>
                                                <th className="px-4 py-3">
                                                    Sent
                                                </th>
                                                <th className="px-4 py-3">
                                                    Expires
                                                </th>
                                                {canManage && (
                                                    <th className="px-4 py-3 text-right">
                                                        Actions
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {invitations.map((invitation) => (
                                                <tr
                                                    key={invitation.id}
                                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3">
                                                        <span className="font-medium">
                                                            {invitation.email}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <RoleBadge
                                                            role={
                                                                invitation.role
                                                            }
                                                        />
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
                                                            <Dialog
                                                                open={
                                                                    cancelConfirmId ===
                                                                    invitation.id
                                                                }
                                                                onOpenChange={(
                                                                    open,
                                                                ) =>
                                                                    !open &&
                                                                    setCancelConfirmId(
                                                                        null,
                                                                    )
                                                                }
                                                            >
                                                                <DialogTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        variant="destructive"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            setCancelConfirmId(
                                                                                invitation.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        Cancel
                                                                    </Button>
                                                                </DialogTrigger>
                                                                <DialogContent>
                                                                    <DialogHeader>
                                                                        <DialogTitle>
                                                                            Cancel
                                                                            invitation
                                                                        </DialogTitle>
                                                                        <DialogDescription>
                                                                            Are
                                                                            you
                                                                            sure
                                                                            you
                                                                            want
                                                                            to
                                                                            cancel
                                                                            this
                                                                            invitation
                                                                            for{' '}
                                                                            {
                                                                                invitation.email
                                                                            }
                                                                            ?
                                                                        </DialogDescription>
                                                                    </DialogHeader>
                                                                    <Form
                                                                        action={`/workspaces/${workspace.id}/invitations/${invitation.id}`}
                                                                        method="delete"
                                                                        disableWhileProcessing
                                                                        onSuccess={() =>
                                                                            setCancelConfirmId(
                                                                                null,
                                                                            )
                                                                        }
                                                                    >
                                                                        {({
                                                                            processing,
                                                                        }) => (
                                                                            <div className="flex justify-end gap-2">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="outline"
                                                                                    onClick={() =>
                                                                                        setCancelConfirmId(
                                                                                            null,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Cancel
                                                                                </Button>
                                                                                <Button
                                                                                    type="submit"
                                                                                    variant="destructive"
                                                                                    disabled={
                                                                                        processing
                                                                                    }
                                                                                >
                                                                                    {processing
                                                                                        ? 'Cancelling...'
                                                                                        : 'Cancel invitation'}
                                                                                </Button>
                                                                            </div>
                                                                        )}
                                                                    </Form>
                                                                </DialogContent>
                                                            </Dialog>
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

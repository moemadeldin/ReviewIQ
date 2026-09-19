import { Form, Head, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

interface Member {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    role: 'owner' | 'admin' | 'member';
}

interface MembersPageProps {
    workspace: Workspace;
    userRole: string;
    initialMembers: Member[];
    membersCurrentPage: number;
    membersHasMore: boolean;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Members() {
    const {
        workspace,
        userRole,
        initialMembers,
        membersCurrentPage,
        membersHasMore,
    } = usePage<{ auth: Auth } & MembersPageProps>().props;
    const role = userRole;
    const [members, setMembers] = useState<Member[]>(initialMembers || []);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(membersCurrentPage || 1);
    const [hasMore, setHasMore] = useState(membersHasMore || false);
    const [inviting, setInviting] = useState(false);
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('member');
    const [inviteError, setInviteError] = useState<string | null>(null);
    const [removeConfirmId, setRemoveConfirmId] = useState<string | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    const canInvite = role === 'owner';
    const isOwner = role === 'owner';

    useEffect(() => {
        setMembers(initialMembers || []);
        setPage(membersCurrentPage || 1);
        setHasMore(membersHasMore || false);
    }, [initialMembers, membersCurrentPage, membersHasMore]);

    const fetchMembers = async (pageNum: number) => {
        setLoading(true);
        try {
            const response = await fetch(
                `/workspaces/${workspace.slug}/members/data?page=${pageNum}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );
            const result = await response.json();
            if (result.status === 'Success') {
                setMembers(result.data.members);
                setHasMore(result.data.has_more);
                setPage(result.data.current_page);
            }
        } catch (error) {
            console.error('Failed to fetch members:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleInvite = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!inviteEmail) return;

        setInviting(true);
        setInviteError(null);
        try {
            const response = await fetch(
                `/workspaces/${workspace.slug}/invitations`,
                {
                    method: 'POST',
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
                    body: JSON.stringify({
                        email: inviteEmail,
                        role: inviteRole,
                    }),
                },
            );

            const result = await response.json();

            if (result.status === 'Success') {
                setInviteEmail('');
                setInviteRole('member');
                setIsModalOpen(false);
                await fetchMembers(page);
            } else {
                setInviteError(result.message || 'Failed to send invitation');
            }
        } catch (error) {
            console.error('Failed to send invitation:', error);
            setInviteError('Failed to send invitation');
        } finally {
            setInviting(false);
        }
    };

    const prevPage = () => {
        if (page > 1) {
            void fetchMembers(page - 1);
        }
    };

    const nextPage = () => {
        if (hasMore) {
            void fetchMembers(page + 1);
        }
    };

    const currentBreadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbs,
        {
            title: workspace.name,
            href: `/workspaces/${workspace.slug}`,
        },
        {
            title: 'Members',
            href: `/workspaces/${workspace.slug}/members`,
        },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`${workspace.name} - Members`} />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Members"
                        description={`${members.length} member${members.length !== 1 ? 's' : ''} in ${workspace.name}`}
                    />
                    {canInvite && (
                        <Dialog
                            open={isModalOpen}
                            onOpenChange={setIsModalOpen}
                        >
                            <DialogTrigger asChild>
                                <Button>Invite Member</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Invite Member</DialogTitle>
                                    <DialogDescription>
                                        Send an invitation to join{' '}
                                        {workspace.name}
                                    </DialogDescription>
                                </DialogHeader>
                                <form
                                    onSubmit={handleInvite}
                                    className="space-y-4"
                                >
                                    <div>
                                        <Input
                                            type="email"
                                            placeholder="Email address"
                                            value={inviteEmail}
                                            onChange={(e) =>
                                                setInviteEmail(e.target.value)
                                            }
                                            required
                                        />
                                    </div>
                                    <Select
                                        value={inviteRole}
                                        onValueChange={setInviteRole}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="admin">
                                                Admin
                                            </SelectItem>
                                            <SelectItem value="member">
                                                Member
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {inviteError && (
                                        <p className="text-sm text-red-500">
                                            {inviteError}
                                        </p>
                                    )}
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setIsModalOpen(false);
                                                setInviteError(null);
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={inviting || !inviteEmail}
                                        >
                                            {inviting
                                                ? 'Sending...'
                                                : 'Send Invite'}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                <Card>
                    <CardContent className="pt-6">
                        {loading ? (
                            <div className="flex items-center justify-center py-10">
                                <div className="h-6 w-6 animate-spin rounded-full border-2 border-border border-t-primary" />
                            </div>
                        ) : members.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="No members yet"
                                description="Invite members to collaborate in this workspace"
                            />
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b border-border/60 bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                <th className="px-4 py-3">
                                                    Member
                                                </th>
                                                <th className="px-4 py-3">
                                                    Role
                                                </th>

                                                {isOwner && (
                                                    <th className="px-4 py-3 text-right">
                                                        Actions
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {members.map((member) => (
                                                <tr
                                                    key={member.id}
                                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar>
                                                                <AvatarImage
                                                                    src={
                                                                        member.avatar ||
                                                                        undefined
                                                                    }
                                                                />
                                                                <AvatarFallback>
                                                                    {member.name?.charAt(
                                                                        0,
                                                                    ) || '?'}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div>
                                                                <div className="font-medium">
                                                                    {
                                                                        member.name
                                                                    }
                                                                </div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    {
                                                                        member.email
                                                                    }
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <RoleBadge
                                                            role={member.role}
                                                        />
                                                    </td>
                                                    {isOwner && (
                                                        <td className="px-4 py-3 text-right">
                                                            {member.role !==
                                                                'owner' && (
                                                                <Dialog
                                                                    open={
                                                                        removeConfirmId ===
                                                                        member.id
                                                                    }
                                                                    onOpenChange={(
                                                                        open,
                                                                    ) =>
                                                                        !open &&
                                                                        setRemoveConfirmId(
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
                                                                                setRemoveConfirmId(
                                                                                    member.id,
                                                                                )
                                                                            }
                                                                        >
                                                                            Remove
                                                                        </Button>
                                                                    </DialogTrigger>
                                                                    <DialogContent>
                                                                        <DialogHeader>
                                                                            <DialogTitle>
                                                                                Remove
                                                                                member
                                                                            </DialogTitle>
                                                                            <DialogDescription>
                                                                                Are
                                                                                you
                                                                                sure
                                                                                you
                                                                                want
                                                                                to
                                                                                remove{' '}
                                                                                {
                                                                                    member.name
                                                                                }{' '}
                                                                                from{' '}
                                                                                {
                                                                                    workspace.name
                                                                                }
                                                                                ?
                                                                            </DialogDescription>
                                                                        </DialogHeader>
                                                                        <Form
                                                                            action={`/workspaces/${workspace.slug}/members/${member.id}`}
                                                                            method="delete"
                                                                            disableWhileProcessing
                                                                            onSuccess={() =>
                                                                                setRemoveConfirmId(
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
                                                                                            setRemoveConfirmId(
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
                                                                                            ? 'Removing...'
                                                                                            : 'Remove'}
                                                                                    </Button>
                                                                                </div>
                                                                            )}
                                                                        </Form>
                                                                    </DialogContent>
                                                                </Dialog>
                                                            )}
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

function RoleBadge({ role }: { role: string }) {
    const variants: Record<string, string> = {
        owner: 'border-transparent bg-violet-500/15 text-violet-700 dark:text-violet-300',
        admin: 'border-transparent bg-sky-500/15 text-sky-600 dark:text-sky-400',
        member: 'border-transparent bg-muted text-muted-foreground',
    };

    return (
        <Badge className={variants[role] || variants.member}>
            {role.charAt(0).toUpperCase() + role.slice(1)}
        </Badge>
    );
}

import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
    joined_at: string;
}

interface MembersPageProps {
    workspace: Workspace;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Members() {
    const { workspace } = usePage<{ auth: Auth } & MembersPageProps>().props;
    const role = usePage().props.auth?.role;
    const [members, setMembers] = useState<Member[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [inviting, setInviting] = useState(false);
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('member');
    const [removing, setRemoving] = useState<string | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    const canInvite = role === 'owner';
    const isOwner = role === 'owner';

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
                alert(result.message || 'Failed to send invitation');
            }
        } catch (error) {
            console.error('Failed to send invitation:', error);
            alert('Failed to send invitation');
        } finally {
            setInviting(false);
        }
    };

    const handleRemove = async (userId: string) => {
        if (!confirm('Are you sure you want to remove this member?')) return;

        setRemoving(userId);
        try {
            const response = await fetch(
                `/workspaces/${workspace.slug}/members/${userId}`,
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
                await fetchMembers(page);
            } else {
                alert(result.message || 'Failed to remove member');
            }
        } catch (error) {
            console.error('Failed to remove member:', error);
            alert('Failed to remove member');
        } finally {
            setRemoving(null);
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
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setIsModalOpen(false)
                                            }
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
                            <div className="flex items-center justify-center py-8">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                            </div>
                        ) : members.length === 0 ? (
                            <div className="py-12 text-center">
                                <div className="mb-2 text-lg font-medium text-muted-foreground">
                                    No members yet
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Invite members to collaborate in this
                                    workspace
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-left">
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Member
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Role
                                                </th>
                                                <th className="px-4 py-3 text-sm font-medium">
                                                    Joined
                                                </th>
                                                {isOwner && (
                                                    <th className="px-4 py-3 text-right text-sm font-medium">
                                                        Actions
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {members.map((member) => (
                                                <tr
                                                    key={member.id}
                                                    className="border-b"
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
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {new Date(
                                                            member.joined_at,
                                                        ).toLocaleDateString()}
                                                    </td>
                                                    {isOwner && (
                                                        <td className="px-4 py-3 text-right">
                                                            {member.role !==
                                                                'owner' && (
                                                                <Button
                                                                    variant="destructive"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleRemove(
                                                                            member.id,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        removing ===
                                                                        member.id
                                                                    }
                                                                >
                                                                    {removing ===
                                                                    member.id
                                                                        ? 'Removing...'
                                                                        : 'Remove'}
                                                                </Button>
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
    const variants: Record<string, 'default' | 'secondary' | 'destructive'> = {
        owner: 'default',
        admin: 'secondary',
        member: 'secondary',
    };

    return (
        <Badge variant={variants[role] || 'secondary'}>
            {role.charAt(0).toUpperCase() + role.slice(1)}
        </Badge>
    );
}

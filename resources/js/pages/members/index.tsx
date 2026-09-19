import { Head, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem } from '@/types';

interface Member {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    role: 'owner' | 'admin' | 'member';
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Members',
        href: '/members',
    },
];

export default function Index() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [members, setMembers] = useState<Member[]>([]);
    const [loading, setLoading] = useState(true);
    const [inviting, setInviting] = useState(false);
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('member');
    const [removing, setRemoving] = useState<string | null>(null);

    const currentUserRole = auth.currentWorkspace?.pivot?.role as string;
    const canInvite =
        currentUserRole === 'owner' || currentUserRole === 'admin';
    const isOwner = currentUserRole === 'owner';

    const fetchMembers = async () => {
        try {
            const response = await fetch('/members/data', {
                headers: {
                    Accept: 'application/json',
                },
            });
            const result = await response.json();
            if (result.status === 'Success') {
                setMembers(result.data.members);
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
            const response = await fetch('/members/invite', {
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
            });

            const result = await response.json();

            if (result.status === 'Success') {
                setInviteEmail('');
                setInviteRole('member');
                await fetchMembers();
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
            const response = await fetch(`/members/${userId}`, {
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
            });

            const result = await response.json();

            if (result.status === 'Success') {
                await fetchMembers();
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Members"
                        description="Manage workspace members and invitations"
                    />
                </div>

                {canInvite && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Invite Member</CardTitle>
                            <CardDescription>
                                Send an invitation to join this workspace
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={handleInvite}
                                className="flex gap-4"
                            >
                                <div className="flex-1">
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
                                    <SelectTrigger className="w-[140px]">
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
                                <Button
                                    type="submit"
                                    disabled={inviting || !inviteEmail}
                                >
                                    {inviting ? 'Sending...' : 'Send Invite'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Workspace Members</CardTitle>
                        <CardDescription>
                            {members.length} member
                            {members.length !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="flex items-center justify-center py-10">
                                <div className="h-6 w-6 animate-spin rounded-full border-2 border-border border-t-primary" />
                            </div>
                        ) : members.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="No members found"
                                description="Members will appear here once they join this workspace"
                            />
                        ) : (
                            <div className="rounded-md border">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b border-border/60 bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            <th className="px-4 py-3">
                                                Member
                                            </th>
                                            <th className="px-4 py-3">Role</th>

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
                                                                {member.name}
                                                            </div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {member.email}
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

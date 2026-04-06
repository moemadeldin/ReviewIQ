import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem } from '@/types';

interface Member {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    role: 'owner' | 'admin' | 'member';
    joined_at: string;
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
    const canInvite = currentUserRole === 'owner' || currentUserRole === 'admin';
    const isOwner = currentUserRole === 'owner';

    const fetchMembers = async () => {
        try {
            const response = await fetch('/members/data', {
                headers: {
                    'Accept': 'application/json',
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
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
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
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
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
                            <form onSubmit={handleInvite} className="flex gap-4">
                                <div className="flex-1">
                                    <Input
                                        type="email"
                                        placeholder="Email address"
                                        value={inviteEmail}
                                        onChange={(e) => setInviteEmail(e.target.value)}
                                        required
                                    />
                                </div>
                                <Select value={inviteRole} onValueChange={setInviteRole}>
                                    <SelectTrigger className="w-[140px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="member">Member</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button type="submit" disabled={inviting || !inviteEmail}>
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
                            {members.length} member{members.length !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                            </div>
                        ) : members.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                No members found
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-muted/50 text-left">
                                            <th className="px-4 py-3 text-sm font-medium">Member</th>
                                            <th className="px-4 py-3 text-sm font-medium">Role</th>
                                            <th className="px-4 py-3 text-sm font-medium">Joined</th>
                                            {isOwner && <th className="px-4 py-3 text-sm font-medium text-right">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {members.map((member) => (
                                            <tr key={member.id} className="border-b">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <Avatar>
                                                            <AvatarImage src={member.avatar || undefined} />
                                                            <AvatarFallback>
                                                                {member.name?.charAt(0) || '?'}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <div className="font-medium">{member.name}</div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {member.email}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <RoleBadge role={member.role} />
                                                </td>
                                                <td className="px-4 py-3 text-sm text-muted-foreground">
                                                    {new Date(member.joined_at).toLocaleDateString()}
                                                </td>
                                                {isOwner && (
                                                    <td className="px-4 py-3 text-right">
                                                        {member.role !== 'owner' && (
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() => handleRemove(member.id)}
                                                                disabled={removing === member.id}
                                                            >
                                                                {removing === member.id ? 'Removing...' : 'Remove'}
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

import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Users,
    GitBranch,
    Mail,
    FileCheck,
    Settings,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

interface WorkspaceShowProps {
    workspace: Workspace;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function Show() {
    const { auth, workspace } = usePage<{ auth: Auth } & WorkspaceShowProps>()
        .props;
    const isOwner = auth.user.id === workspace.owner_id;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={workspace.name} />

            <div className="space-y-6 px-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={workspace.name}
                        description="Manage your workspace"
                    />
                    {isOwner && (
                        <div className="flex gap-2">
                            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        <Settings className="mr-2 h-4 w-4" />
                                        Edit
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Rename workspace
                                        </DialogTitle>
                                        <DialogDescription>
                                            Change the name of {workspace.name}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        action={`/workspaces/${workspace.slug}`}
                                        method="put"
                                        disableWhileProcessing
                                        className="space-y-4"
                                        onSuccess={() => setEditOpen(false)}
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="edit-name">
                                                        Workspace name
                                                    </Label>
                                                    <Input
                                                        id="edit-name"
                                                        type="text"
                                                        name="name"
                                                        required
                                                        defaultValue={
                                                            workspace.name
                                                        }
                                                    />
                                                    {errors.name && (
                                                        <p className="text-sm text-red-500">
                                                            {errors.name}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setEditOpen(false)
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        {processing
                                                            ? 'Saving...'
                                                            : 'Save'}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            <Dialog
                                open={deleteOpen}
                                onOpenChange={setDeleteOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Delete workspace
                                        </DialogTitle>
                                        <DialogDescription>
                                            This will permanently delete{' '}
                                            {workspace.name} and all associated
                                            data. This action cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        action={`/workspaces/${workspace.slug}`}
                                        method="delete"
                                        disableWhileProcessing
                                        className="space-y-4"
                                    >
                                        {({ processing }) => (
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setDeleteOpen(false)
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing
                                                        ? 'Deleting...'
                                                        : 'Delete workspace'}
                                                </Button>
                                            </div>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        href={`/workspaces/${workspace.slug}/members`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <Users className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Members</div>
                            <div className="text-sm text-muted-foreground">
                                View and manage workspace members
                            </div>
                        </div>
                    </Link>

                    <Link
                        href={`/workspaces/${workspace.slug}/invitations`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <Mail className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Invitations</div>
                            <div className="text-sm text-muted-foreground">
                                View pending invitations
                            </div>
                        </div>
                    </Link>

                    <Link
                        href={`/workspaces/${workspace.slug}/repos`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <GitBranch className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Repositories</div>
                            <div className="text-sm text-muted-foreground">
                                View connected repositories
                            </div>
                        </div>
                    </Link>

                    <Link
                        href={`/workspaces/${workspace.slug}/reviews`}
                        className="flex items-center rounded-lg border p-6 hover:bg-muted/50"
                    >
                        <FileCheck className="mr-4 h-8 w-8" />
                        <div>
                            <div className="font-semibold">Reviews</div>
                            <div className="text-sm text-muted-foreground">
                                View pull request reviews
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

import { Form, Head, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/ui/page-header';
import {
    WorkspaceTabs,
    type WorkspaceTabCounts,
} from '@/components/workspace-tabs';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem, Workspace } from '@/types';

interface WorkspaceSettingsProps {
    workspace: Workspace;
    tabCounts: WorkspaceTabCounts;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
];

export default function WorkspaceSettings() {
    const { auth, workspace, tabCounts } = usePage<
        {
            auth: Auth;
        } & WorkspaceSettingsProps
    >().props;
    const isOwner = auth.user.id === workspace.owner_id;

    const currentBreadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbs,
        {
            title: workspace.name,
            href: `/workspaces/${workspace.id}`,
        },
        {
            title: 'Settings',
            href: `/workspaces/${workspace.id}/settings`,
        },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`${workspace.name} - Settings`} />

            <div className="space-y-6 px-4">
                <PageHeader title="Settings" subtitle={workspace.name} />

                <WorkspaceTabs
                    workspaceId={workspace.id}
                    active="settings"
                    counts={tabCounts}
                />

                {isOwner ? (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Workspace name</CardTitle>
                                <CardDescription>
                                    Change the name of this workspace.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={`/workspaces/${workspace.id}`}
                                    method="put"
                                    disableWhileProcessing
                                    className="space-y-4"
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
                                                    <p className="text-sm text-destructive">
                                                        {errors.name}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Saving...'
                                                    : 'Save changes'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card className="border-destructive/40">
                            <CardHeader>
                                <CardTitle className="text-destructive">
                                    Danger zone
                                </CardTitle>
                                <CardDescription>
                                    Delete this workspace and all of its data.
                                    This cannot be undone.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={`/workspaces/${workspace.id}`}
                                    method="delete"
                                    disableWhileProcessing
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Deleting...'
                                                : 'Delete workspace'}
                                        </Button>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            Only the workspace owner can change settings.
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

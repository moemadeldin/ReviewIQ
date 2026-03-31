import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import type { Auth } from '@/types';

export default function Create() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isFirstWorkspace = auth.workspaces.length === 0;

    return (
        <AuthLayout
            title={
                isFirstWorkspace
                    ? 'Create your workspace'
                    : 'Create a new workspace'
            }
            description={
                isFirstWorkspace
                    ? 'Set up a workspace to get started with your team'
                    : 'Add another workspace for a different team'
            }
        >
            <Head title="Create Workspace" />
            <Form
                action="/workspaces"
                method="post"
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Workspace name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    name="name"
                                    placeholder="Acme Inc."
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={2}
                                data-test="create-workspace-button"
                            >
                                {processing && <Spinner />}
                                Create workspace
                            </Button>
                        </div>

                        {!isFirstWorkspace && (
                            <div className="text-center text-sm text-muted-foreground">
                                Changed your mind?{' '}
                                <TextLink href="/workspaces" tabIndex={3}>
                                    Back to workspaces
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}

import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import invitations from '@/routes/invitations';

type Invitation = {
    token: string;
    email: string;
    role: string;
    workspace: {
        name: string;
        description: string | null;
    };
};

type Props = {
    invitation: Invitation;
    isExistingUser: boolean;
};

export default function AcceptInvitation({ invitation, isExistingUser }: Props) {
    return (
        <AuthLayout
            title={`You're invited to join ${invitation.workspace.name}`}
            description={invitation.workspace.description ?? 'A workspace on ReviewIQ'}
        >
            <Head title="Accept Invitation" />

            <div className="mb-6 rounded-lg bg-muted p-4 text-sm">
                <p className="text-muted-foreground">
                    Invited as: <span className="font-medium text-foreground">{invitation.role}</span>
                </p>
                <p className="text-muted-foreground">
                    Email: <span className="font-medium text-foreground">{invitation.email}</span>
                </p>
            </div>

            {isExistingUser ? (
                <Form
                    {...invitations.accept.form.post({ token: invitation.token })}
                    className="flex flex-col gap-6"
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            className="w-full cursor-pointer"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Accept Invitation
                        </Button>
                    )}
                </Form>
            ) : (
                <Form
                    {...invitations.accept.form.post({ token: invitation.token })}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Your Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    placeholder="Your name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm Password</Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full cursor-pointer"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Create Account & Accept
                            </Button>
                        </div>
                    )}
                </Form>
            )}
        </AuthLayout>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import {
    GitPullRequestArrow,
    ShieldCheck,
    Zap,
    type LucideIcon,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

function Feature({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-xl border border-border/70 bg-card/50 p-6 text-left">
            <div className="mb-3 flex size-9 items-center justify-center rounded-lg border border-border/60 bg-muted/30 text-primary">
                <Icon className="size-4" />
            </div>
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
            </Head>
            <div className="bg-radial-glow flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-5">
                    <Link
                        href={dashboard()}
                        className="flex items-center gap-2"
                    >
                        <AppLogo />
                    </Link>
                    <nav className="flex items-center gap-3">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                {canRegister && (
                                    <Button asChild>
                                        <Link href={register()}>
                                            Get started
                                        </Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center px-6 py-20 text-center">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-violet-500/30 bg-violet-500/10 px-3 py-1 text-xs font-medium text-violet-600 dark:text-violet-300">
                        <Zap className="size-3" />
                        AI-powered code reviews
                    </span>
                    <h1 className="max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
                        Code review, on autopilot
                    </h1>
                    <p className="mt-5 max-w-2xl text-base text-muted-foreground sm:text-lg">
                        ReviewIQ reads every pull request, surfaces critical
                        issues, and scores your code - so your team ships with
                        confidence.
                    </p>
                    <div className="mt-8 flex gap-3">
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard()}>Open dashboard</Link>
                            </Button>
                        ) : (
                            <Button asChild size="lg">
                                <Link href={register()}>Start reviewing</Link>
                            </Button>
                        )}
                    </div>

                    <div className="mt-20 grid w-full gap-4 sm:grid-cols-3">
                        <Feature
                            icon={Zap}
                            title="Instant reviews"
                            description="AI analyzes each diff in seconds and streams findings live."
                        />
                        <Feature
                            icon={ShieldCheck}
                            title="Find the gotchas"
                            description="Critical, warning, and info findings with file-level context."
                        />
                        <Feature
                            icon={GitPullRequestArrow}
                            title="Actionable scores"
                            description="A clear 0-100 score with a rationale for every pull request."
                        />
                    </div>
                </main>

                <footer className="border-t border-sidebar-border/50 py-6 text-center text-xs text-muted-foreground">
                    ReviewIQ
                </footer>
            </div>
        </>
    );
}

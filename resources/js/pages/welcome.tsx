import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    FileCheck,
    FileCode2,
    GitBranch,
    GitPullRequestArrow,
    Scale,
    ShieldCheck,
    Target,
    Wrench,
    Zap,
    type LucideIcon,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    contact,
    dashboard,
    home,
    login,
    privacy,
    register,
    terms,
} from '@/routes';

interface OGSettings {
    url: string;
    width: number;
    height: number;
}

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

function SectionHeading({
    eyebrow,
    title,
    description,
}: {
    eyebrow: string;
    title: string;
    description: string;
}) {
    return (
        <div className="mx-auto max-w-2xl text-center">
            <p className="text-xs font-medium tracking-widest text-violet-500 uppercase dark:text-violet-300">
                {eyebrow}
            </p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                {title}
            </h2>
            <p className="mt-3 text-sm text-muted-foreground sm:text-base">
                {description}
            </p>
        </div>
    );
}

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth, og } = usePage<{
        auth?: { user?: unknown };
        og?: OGSettings;
    }>().props;

    const isLoggedIn = !!auth?.user;

    const primaryHref = isLoggedIn
        ? dashboard()
        : canRegister
          ? register()
          : login();
    const primaryLabel = isLoggedIn
        ? 'Go to dashboard'
        : canRegister
          ? 'Get started'
          : 'Log in';

    // TODO(confirm): retention period for stored reviews, and whether the
    // OpenAI-compatible provider (OpenRouter) uses submitted prompts for training.
    return (
        <>
            <Head title="ReviewIQ">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
                <meta
                    name="description"
                    content="AI code review for pull requests. ReviewIQ reads every diff, flags critical to low findings, and scores each change with suggested fixes."
                />
                {og && (
                    <>
                        <meta property="og:title" content="ReviewIQ" />
                        <meta
                            property="og:description"
                            content="AI code review for pull requests. ReviewIQ reads every diff, flags critical to low findings, and scores each change with suggested fixes."
                        />
                        <meta property="og:type" content="website" />
                        <meta property="og:image" content={og.url} />
                        <meta
                            property="og:image:width"
                            content={String(og.width)}
                        />
                        <meta
                            property="og:image:height"
                            content={String(og.height)}
                        />
                        <meta
                            name="twitter:card"
                            content="summary_large_image"
                        />
                        <meta name="twitter:title" content="ReviewIQ" />
                        <meta
                            name="twitter:description"
                            content="AI code review for pull requests. ReviewIQ reads every diff, flags critical to low findings, and scores each change with suggested fixes."
                        />
                        <meta name="twitter:image" content={og.url} />
                    </>
                )}
            </Head>
            <div className="bg-radial-glow flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-5">
                    <Link href={home()} className="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <nav className="flex items-center gap-3">
                        {isLoggedIn ? (
                            <Button asChild>
                                <Link href={dashboard()}>Go to dashboard</Link>
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
                        A second pair of eyes on every pull request. ReviewIQ
                        reads the diff, flags what matters, and scores the
                        change — your team still makes the call.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button asChild size="lg">
                            <Link href={primaryHref}>{primaryLabel}</Link>
                        </Button>
                    </div>

                    <div className="mt-20 grid w-full min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Feature
                            icon={Zap}
                            title="Instant reviews"
                            description="ReviewIQ analyzes each diff in seconds and streams findings live."
                        />
                        <Feature
                            icon={AlertCircle}
                            title="Clear severities"
                            description="Critical, high, medium, and low findings with file-level context."
                        />
                        <Feature
                            icon={Scale}
                            title="Your rules, per repo"
                            description="Set custom review rules for each repository. No more generic feedback."
                        />
                        <Feature
                            icon={GitPullRequestArrow}
                            title="Actionable scores"
                            description="A clear 0-100 score with a rationale for every pull request."
                        />
                    </div>

                    <section
                        id="how-it-works"
                        className="mt-24 w-full min-w-0 border-t border-border/60 pt-16"
                    >
                        <SectionHeading
                            eyebrow="How it works"
                            title="From connect to scored review"
                            description="Three steps, and ReviewIQ stays in the loop on every new push."
                        />
                        <ol className="mt-10 grid w-full gap-4 sm:grid-cols-3">
                            <Step
                                number={1}
                                icon={GitBranch}
                                title="Connect GitHub"
                                description="Grant access to your repositories. ReviewIQ opens the pull requests you review."
                            />
                            <Step
                                number={2}
                                icon={GitPullRequestArrow}
                                title="Open a pull request"
                                description="New and updated PRs are picked up automatically via webhooks."
                            />
                            <Step
                                number={3}
                                icon={FileCheck}
                                title="Get a scored review"
                                description="A 0-100 score, severity-ranked issues, suggested fixes, and highlights."
                            />
                        </ol>
                    </section>

                    <section
                        id="why-different"
                        className="mt-24 w-full min-w-0 border-t border-border/60 pt-16"
                    >
                        <SectionHeading
                            eyebrow="What makes it different"
                            title="Built for the way your team reviews"
                            description="A reviewer that follows your repository's rules and tells you why."
                        />
                        <div className="mt-10 grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Feature
                                icon={FileCode2}
                                title="Your rules, per repo"
                                description="Write custom review rules for any repository and ReviewIQ applies them to every pull request."
                            />
                            <Feature
                                icon={AlertCircle}
                                title="Ranked findings"
                                description="Issues are ranked critical, high, medium, or low, with file and line context."
                            />
                            <Feature
                                icon={Wrench}
                                title="Suggested fixes"
                                description="Every issue comes with a concrete suggestion, not just a complaint."
                            />
                            <Feature
                                icon={Target}
                                title="A score with rationale"
                                description="Each pull request gets a score and a written explanation your team can talk through."
                            />
                        </div>
                    </section>

                    <section
                        id="privacy"
                        className="mt-24 w-full min-w-0 border-t border-border/60 pt-16"
                    >
                        <SectionHeading
                            eyebrow="Privacy and access"
                            title="What ReviewIQ asks for, and stores"
                            description="Clear permissions, plain answers."
                        />
                        <div className="mx-auto mt-10 grid w-full max-w-4xl gap-4 text-left sm:grid-cols-3">
                            <Feature
                                icon={ShieldCheck}
                                title="Access requested"
                                description="Connecting GitHub requests two permissions: read:user for your profile, and repo to read diffs and post reviews."
                            />
                            <Feature
                                icon={FileCheck}
                                title="What is stored"
                                description="ReviewIQ stores the pull requests you review and the reviews it generates, including the raw model output."
                            />
                            <Feature
                                icon={Zap}
                                title="What is sent"
                                description="To write a review, the diff is sent to an AI model on OpenRouter. Diffs are cached for up to 24 hours per commit, and are not kept beyond that cache."
                            />
                        </div>
                    </section>
                </main>

                <footer className="border-t border-sidebar-border/50 px-6 py-6">
                    <div className="mx-auto flex w-full max-w-5xl flex-col items-center justify-between gap-4 sm:flex-row">
                        <span className="text-sm font-medium">ReviewIQ</span>
                        <nav className="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-muted-foreground">
                            <Link
                                href={privacy()}
                                className="transition-colors hover:text-foreground"
                            >
                                Privacy policy
                            </Link>
                            <Link
                                href={terms()}
                                className="transition-colors hover:text-foreground"
                            >
                                Terms
                            </Link>
                            <Link
                                href={contact()}
                                className="transition-colors hover:text-foreground"
                            >
                                Contact
                            </Link>
                            <a
                                href="https://github.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="transition-colors hover:text-foreground"
                            >
                                GitHub
                            </a>
                        </nav>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Step({
    number,
    icon: Icon,
    title,
    description,
}: {
    number: number;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <li className="rounded-xl border border-border/70 bg-card/50 p-6 text-left">
            <div className="mb-3 flex items-center gap-3">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full border border-violet-500/40 bg-violet-500/10 text-sm font-semibold text-violet-600 dark:text-violet-300">
                    {number}
                </span>
                <Icon className="size-4 text-muted-foreground" />
            </div>
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </li>
    );
}

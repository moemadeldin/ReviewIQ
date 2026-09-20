import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { home, login } from '@/routes';

export default function StaticPage({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={`${title} - ReviewIQ`} />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-6 py-5">
                    <Link href={home()} className="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <Link
                        href={login()}
                        className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Log in
                    </Link>
                </header>
                <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-12">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {title}
                    </h1>
                    <div className="mt-6 text-sm leading-relaxed text-muted-foreground">
                        {children}
                    </div>
                </main>
            </div>
        </>
    );
}

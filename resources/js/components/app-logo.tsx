export default function AppLogo() {
    return (
        <div className="flex items-center gap-2.5">
            <img
                src="/logo.png"
                alt="ReviewIQ"
                className="hidden h-8 w-auto shrink-0 overflow-hidden rounded-md object-contain ring-1 ring-white/15 dark:block"
            />
            <img
                src="/logo-light.png"
                alt="ReviewIQ"
                className="h-8 w-auto shrink-0 overflow-hidden rounded-md object-contain ring-1 ring-black/10 dark:hidden"
            />
            <span className="text-base font-semibold tracking-tight text-foreground group-data-[collapsible=icon]:hidden">
                ReviewIQ
            </span>
        </div>
    );
}

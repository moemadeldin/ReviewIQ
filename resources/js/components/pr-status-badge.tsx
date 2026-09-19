import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const statusStyles: Record<string, string> = {
    reviewed:
        'border-transparent bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    reviewing:
        'border-violet-500/40 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    pending: 'border-border bg-muted text-muted-foreground',
    failed: 'border-transparent bg-rose-500/15 text-rose-600 dark:text-rose-400',
};

interface PrStatusBadgeProps extends ComponentProps<typeof Badge> {
    status: string;
}

export function PrStatusBadge({
    status,
    className,
    ...props
}: PrStatusBadgeProps) {
    return (
        <Badge
            className={cn(
                statusStyles[status] ?? statusStyles.pending,
                className,
            )}
            {...props}
        >
            <span className="size-1.5 rounded-full bg-current opacity-80" />
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </Badge>
    );
}

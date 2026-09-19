import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const severityStyles: Record<string, string> = {
    critical:
        'border-transparent bg-rose-500/15 text-rose-600 dark:text-rose-400',
    error: 'border-transparent bg-rose-500/15 text-rose-600 dark:text-rose-400',
    high: 'border-transparent bg-orange-500/15 text-orange-600 dark:text-orange-400',
    warning:
        'border-transparent bg-amber-500/15 text-amber-600 dark:text-amber-400',
    medium: 'border-transparent bg-amber-500/15 text-amber-600 dark:text-amber-400',
    low: 'border-transparent bg-sky-500/15 text-sky-600 dark:text-sky-400',
    info: 'border-border bg-muted text-muted-foreground',
};

interface SeverityBadgeProps extends ComponentProps<typeof Badge> {
    severity: string;
}

export function SeverityBadge({
    severity,
    className,
    ...props
}: SeverityBadgeProps) {
    return (
        <Badge
            className={cn(
                severityStyles[severity.toLowerCase()] ?? severityStyles.info,
                className,
            )}
            {...props}
        >
            {severity.charAt(0).toUpperCase() + severity.slice(1)}
        </Badge>
    );
}

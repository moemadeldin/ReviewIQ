import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const severityStyles: Record<string, string> = {
    critical:
        'border-transparent bg-severity-critical/15 text-severity-critical dark:text-severity-critical',
    error: 'border-transparent bg-severity-critical/15 text-severity-critical dark:text-severity-critical',
    high: 'border-transparent bg-severity-high/15 text-severity-high dark:text-severity-high',
    warning:
        'border-transparent bg-severity-medium/15 text-severity-medium dark:text-severity-medium',
    medium: 'border-transparent bg-severity-medium/15 text-severity-medium dark:text-severity-medium',
    low: 'border-transparent bg-severity-low/15 text-severity-low dark:text-severity-low',
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

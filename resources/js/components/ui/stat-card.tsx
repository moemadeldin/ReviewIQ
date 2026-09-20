import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface StatCardProps {
    label: string;
    value: string | number;
    delta?: {
        value: number;
        label: string;
        trend: 'up' | 'down' | 'neutral';
    };
    warning?: string;
    icon?: LucideIcon;
    className?: string;
}

export function StatCard({
    label,
    value,
    delta,
    warning,
    icon: Icon,
    className,
}: StatCardProps) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border/70 bg-card/60 p-5 transition-colors',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="flex flex-col gap-1 min-w-0">
                    <p className="text-sm font-medium text-muted-foreground truncate">{label}</p>
                    <p className="text-2xl font-semibold tabular-nums text-foreground">
                        {value}
                    </p>
                    {delta && (
                        <p
                            className={cn(
                                'text-xs font-medium flex items-center gap-1',
                                delta.trend === 'up' && 'text-emerald-600 dark:text-emerald-400',
                                delta.trend === 'down' && 'text-rose-600 dark:text-rose-400',
                                delta.trend === 'neutral' && 'text-muted-foreground',
                            )}
                        >
                            {delta.trend === 'up' && '↑'}
                            {delta.trend === 'down' && '↓'}
                            {delta.trend === 'neutral' && '—'}
                            <span>{delta.value}{delta.label}</span>
                        </p>
                    )}
                    {warning && (
                        <p className="text-xs text-muted-foreground">{warning}</p>
                    )}
                </div>
                {Icon && (
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/30 text-muted-foreground">
                        <Icon className="size-5" />
                    </div>
                )}
            </div>
        </div>
    );
}
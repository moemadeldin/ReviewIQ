import { cn } from '@/lib/utils';

interface ScoreIndicatorProps {
    score: number | null;
    size?: 'sm' | 'md' | 'lg';
    showLabel?: boolean;
    className?: string;
}

const SIZE_CLASSES = {
    sm: 'size-6',
    md: 'size-10',
    lg: 'size-14',
};

const LABEL_CLASSES = {
    sm: 'text-[10px]',
    md: 'text-xs',
    lg: 'text-sm',
};

function getScoreBand(score: number | null): { label: string; colorClass: string } {
    if (score === null) {
        return { label: 'No score', colorClass: 'text-muted-foreground' };
    }
    if (score >= 90) {
        return { label: 'Excellent', colorClass: 'text-[var(--score-high)]' };
    }
    if (score >= 70) {
        return { label: 'Good', colorClass: 'text-[var(--score-medium)]' };
    }
    if (score >= 50) {
        return { label: 'Needs work', colorClass: 'text-[var(--score-low)]' };
    }
    if (score >= 30) {
        return { label: 'Poor', colorClass: 'text-[var(--score-critical)]' };
    }
    return { label: 'Critical', colorClass: 'text-[var(--score-critical)]' };
}

export function ScoreIndicator({
    score,
    size = 'md',
    showLabel = true,
    className,
}: ScoreIndicatorProps) {
    const { label, colorClass } = getScoreBand(score);
    const displayScore = score ?? '—';

    return (
        <div
            className={cn(
                'inline-flex flex-col items-center gap-1',
                className,
            )}
        >
            <div
                className={cn(
                    'relative inline-flex items-center justify-center font-mono font-semibold tabular-nums',
                    colorClass,
                    SIZE_CLASSES[size],
                )}
            >
                {displayScore}
            </div>
            {showLabel && (
                <span className={cn(LABEL_CLASSES[size], colorClass, 'font-medium')}>
                    {label}
                </span>
            )}
        </div>
    );
}
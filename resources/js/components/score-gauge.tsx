import { cn } from '@/lib/utils';

interface ScoreGaugeProps {
    score: number | null;
    size?: number;
    strokeWidth?: number;
    className?: string;
    labelClassName?: string;
}

function scoreColor(score: number | null): string {
    if (score === null) return 'text-muted-foreground';
    if (score >= 80) return 'text-score-high';
    if (score >= 50) return 'text-score-medium';
    return 'text-score-low';
}

export function ScoreGauge({
    score,
    size = 96,
    strokeWidth = 7,
    className,
    labelClassName,
}: ScoreGaugeProps) {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const clamped = score === null ? 0 : Math.max(0, Math.min(100, score));
    const offset = circumference - (clamped / 100) * circumference;

    return (
        <div
            className={cn(
                'relative inline-flex items-center justify-center',
                scoreColor(score),
                className,
            )}
            style={{ width: size, height: size }}
        >
            <svg width={size} height={size} className="-rotate-90">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    strokeWidth={strokeWidth}
                    className="fill-none stroke-border"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    strokeWidth={strokeWidth}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className="fill-none stroke-current transition-[stroke-dashoffset] duration-500 ease-out"
                />
            </svg>
            <span
                className={cn(
                    'absolute inset-0 flex items-center justify-center font-semibold tabular-nums',
                    labelClassName,
                )}
            >
                {score ?? '-'}
            </span>
        </div>
    );
}

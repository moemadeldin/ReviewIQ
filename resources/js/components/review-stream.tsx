import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Cpu } from 'lucide-react';
import { useEffect } from 'react';

interface ReviewStreamProps {
    prId: string;
    onComplete?: (review: ReviewData) => void;
}

interface ReviewData {
    summary: string;
    score: number;
    score_rationale: string;
    issues: Array<{
        file: string;
        line: number | null;
        severity: string;
        title: string | null;
        description: string | null;
        suggestion: string | null;
    }>;
    highlights: Array<{
        file: string;
        line: number;
        content: string;
    }>;
    recommendation: string;
}

export function ReviewStream({ prId, onComplete }: ReviewStreamProps) {
    const { leave } = useEcho(
        `reviews.${prId}`,
        ['.ReviewCompleted'],
        (payload: { prId: string; review?: ReviewData }) => {
            if ('review' in payload && payload.review) {
                onComplete?.(payload.review);
                router.reload();
            }
        },
    );

    useEffect(() => {
        return () => {
            leave();
        };
    }, [leave]);

    return (
        <div className="overflow-hidden rounded-xl border border-violet-500/25 bg-card/40">
            <div className="flex items-center gap-2 border-b border-violet-500/20 bg-violet-500/10 px-4 py-2.5">
                <Cpu className="size-3.5 animate-pulse text-violet-700 dark:text-violet-300" />
                <span className="text-sm font-medium text-violet-800 dark:text-violet-200">
                    AI is analyzing this pull request
                </span>
                <span className="ml-auto flex gap-1.5">
                    <span
                        className="size-1.5 animate-bounce rounded-full bg-violet-400"
                        style={{ animationDelay: '0ms' }}
                    />
                    <span
                        className="size-1.5 animate-bounce rounded-full bg-violet-400"
                        style={{ animationDelay: '150ms' }}
                    />
                    <span
                        className="size-1.5 animate-bounce rounded-full bg-violet-400"
                        style={{ animationDelay: '300ms' }}
                    />
                </span>
            </div>
            <div className="min-h-[120px] p-4">
                <p className="text-sm text-muted-foreground">
                    Reading the diff and building the review...
                </p>
            </div>
        </div>
    );
}

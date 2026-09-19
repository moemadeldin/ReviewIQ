import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Cpu } from 'lucide-react';
import { useEffect, useState } from 'react';

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
    const [chunks, setChunks] = useState<string>('');
    const [isStreaming, setIsStreaming] = useState(true);

    const { leave } = useEcho(
        `reviews.${prId}`,
        ['.ReviewChunkReceived', '.ReviewCompleted'],
        (payload: { prId: string; chunk?: string; review?: ReviewData }) => {
            if ('chunk' in payload && payload.chunk) {
                setChunks((prev) => prev + payload.chunk);
            }
            if ('review' in payload && payload.review) {
                setIsStreaming(false);
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

    if (!isStreaming) {
        return null;
    }

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
                {chunks.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Reading the diff and building the review...
                    </p>
                )}
                {chunks && (
                    <pre className="font-mono text-sm whitespace-pre-wrap text-muted-foreground">
                        {chunks}
                        <span className="ml-0.5 inline-block h-3.5 w-1.5 animate-pulse bg-violet-400 align-middle" />
                    </pre>
                )}
            </div>
        </div>
    );
}

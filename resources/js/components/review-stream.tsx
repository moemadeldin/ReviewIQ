import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
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
        <div className="rounded-lg border p-4">
            <div className="mb-2 text-sm font-medium text-muted-foreground">
                AI is analyzing...
            </div>
            <div className="min-h-[100px] space-y-2">
                {chunks.length === 0 && (
                    <div className="flex gap-1">
                        <div
                            className="h-2 w-2 animate-bounce rounded-full bg-muted-foreground/40"
                            style={{ animationDelay: '0ms' }}
                        />
                        <div
                            className="h-2 w-2 animate-bounce rounded-full bg-muted-foreground/40"
                            style={{ animationDelay: '150ms' }}
                        />
                        <div
                            className="h-2 w-2 animate-bounce rounded-full bg-muted-foreground/40"
                            style={{ animationDelay: '300ms' }}
                        />
                    </div>
                )}
                {chunks && (
                    <pre className="text-sm whitespace-pre-wrap">{chunks}</pre>
                )}
            </div>
        </div>
    );
}

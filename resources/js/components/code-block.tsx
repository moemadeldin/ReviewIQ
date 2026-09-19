import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface CodeBlockProps {
    code: string;
    filename?: string;
    language?: string;
    className?: string;
}

export function CodeBlock({
    code,
    filename,
    language,
    className,
}: CodeBlockProps) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-lg border bg-black/40',
                className,
            )}
        >
            <div className="flex items-center justify-between border-b border-border/60 bg-muted/30 px-3 py-1.5">
                <span className="font-mono text-xs text-muted-foreground">
                    {filename ?? language ?? 'code'}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-6 text-muted-foreground"
                    onClick={handleCopy}
                    aria-label="Copy code"
                >
                    {copied ? (
                        <Check className="size-3" />
                    ) : (
                        <Copy className="size-3" />
                    )}
                </Button>
            </div>
            <pre className="overflow-x-auto p-3 font-mono text-xs leading-relaxed">
                <code>{code}</code>
            </pre>
        </div>
    );
}

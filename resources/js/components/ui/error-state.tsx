import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ErrorStateProps {
    message: string;
    onRetry: () => void;
    title?: string;
    icon?: LucideIcon;
    className?: string;
}

export function ErrorState({
    message,
    onRetry,
    title = 'Something went wrong',
    icon: Icon = AlertCircle,
    className,
}: ErrorStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center gap-3 py-8 text-center',
                className,
            )}
        >
            <div className="flex size-11 items-center justify-center rounded-xl border border-destructive/30 bg-destructive/10 text-destructive">
                <Icon className="size-5" />
            </div>
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">{title}</p>
                <p className="text-sm text-muted-foreground max-w-md">{message}</p>
            </div>
            <Button onClick={onRetry} variant="outline" className="w-full sm:w-auto">
                Try again
            </Button>
        </div>
    );
}
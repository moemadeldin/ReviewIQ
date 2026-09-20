import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmText: string;
    variant?: 'danger' | 'warning';
    requireTyping?: string;
    onConfirm: () => void;
    children?: React.ReactNode;
}

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmText,
    variant = 'danger',
    requireTyping,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    const [typedValue, setTypedValue] = useState('');
    const isTypingRequired = !!requireTyping;
    const isConfirmDisabled = isTypingRequired ? typedValue !== requireTyping : false;

    const handleConfirm = () => {
        onConfirm();
        onOpenChange(false);
        if (isTypingRequired) {
            setTypedValue('');
        }
    };

    return (
        <>
            {children && (
                <DialogTrigger asChild>
                    {children}
                </DialogTrigger>
            )}
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className={cn('max-w-md', variant === 'danger' && 'border-destructive/30')}>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {isTypingRequired && (
                        <div className="py-4">
                            <p className="text-sm text-muted-foreground mb-2">
                                Type <code className="bg-muted px-1.5 py-0.5 rounded text-xs font-mono">{requireTyping}</code> to confirm
                            </p>
                            <Input
                                value={typedValue}
                                onChange={(e) => setTypedValue(e.target.value)}
                                placeholder={`Type "${requireTyping}"`}
                                autoFocus
                            />
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant={variant === 'danger' ? 'destructive' : 'default'}
                            onClick={handleConfirm}
                            disabled={isConfirmDisabled}
                        >
                            {confirmText}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export function RoleBadge({ role }: { role: string }) {
    const isOwner = role === 'owner';

    return (
        <Badge
            className={cn(
                'border-transparent',
                isOwner
                    ? 'bg-primary/15 text-primary'
                    : 'bg-muted text-foreground',
            )}
        >
            {role.charAt(0).toUpperCase() + role.slice(1)}
        </Badge>
    );
}

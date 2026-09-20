import { Breadcrumbs } from '@/components/breadcrumbs';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItemType[];
    actions?: React.ReactNode;
    variant?: 'default' | 'compact';
}

export function PageHeader({
    title,
    subtitle,
    breadcrumbs = [],
    actions,
    variant = 'default',
}: PageHeaderProps) {
    return (
        <header className={cn('w-full', variant === 'compact' ? 'mb-3' : 'mb-6')}>
            {breadcrumbs.length > 0 && (
                <div className={cn('mb-2', variant === 'compact' && 'mb-1')}>
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <Heading title={title} description={subtitle} variant={variant === 'compact' ? 'small' : 'default'} />
                {actions && (
                    <div className="flex items-center gap-2 shrink-0">
                        {actions}
                    </div>
                )}
            </div>
        </header>
    );
}
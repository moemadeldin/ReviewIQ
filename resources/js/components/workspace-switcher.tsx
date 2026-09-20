import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { Auth, Workspace } from '@/types';

export function WorkspaceSwitcher() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const current = auth.currentWorkspace;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                        >
                            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                {(current?.name ?? 'W').charAt(0).toUpperCase()}
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {current?.name ?? 'No workspace'}
                                </span>
                                <span className="truncate text-xs text-sidebar-accent-foreground/70">
                                    {current?.name
                                        ? 'Current workspace'
                                        : 'Select a workspace'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side="bottom"
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Workspaces
                        </DropdownMenuLabel>
                        {auth.workspaces.map((workspace: Workspace) => (
                            <DropdownMenuItem
                                key={workspace.id}
                                className={cn(
                                    'cursor-pointer gap-2',
                                    current?.id === workspace.id && 'bg-muted',
                                )}
                                onClick={() => {
                                    if (current?.id === workspace.id) {
                                        return;
                                    }
                                    router.post(
                                        `/workspaces/${workspace.id}/switch`,
                                        {},
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <div className="flex size-5 shrink-0 items-center justify-center rounded border border-border text-[10px] font-medium">
                                    {workspace.name.charAt(0).toUpperCase()}
                                </div>
                                <span className="flex-1 truncate">
                                    {workspace.name}
                                </span>
                                {current?.id === workspace.id && (
                                    <Check className="size-4 text-primary" />
                                )}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild className="cursor-pointer">
                            <Link href="/workspaces/create">
                                <Plus className="size-4" />
                                Create workspace
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

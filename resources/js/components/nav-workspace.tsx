import { Form, Link, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { Auth, Workspace } from '@/types';

export function NavWorkspace() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    if (!auth.currentWorkspace) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            data-test="workspace-switcher-button"
                        >
                            <div className="flex size-6 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <span className="text-xs font-bold">
                                    {auth.currentWorkspace.name
                                        .charAt(0)
                                        .toUpperCase()}
                                </span>
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {auth.currentWorkspace.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {auth.currentWorkspace.pivot?.role ??
                                        'member'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        <div className="px-2 py-1.5 text-xs font-medium text-muted-foreground">
                            Workspaces
                        </div>
                        {auth.workspaces.map((workspace) => (
                            <WorkspaceMenuItem
                                key={workspace.id}
                                workspace={workspace}
                                isCurrent={
                                    auth.currentWorkspace?.id === workspace.id
                                }
                            />
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href="/workspaces/create"
                                className="flex w-full items-center gap-2"
                            >
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

function WorkspaceMenuItem({
    workspace,
    isCurrent,
}: {
    workspace: Workspace;
    isCurrent: boolean;
}) {
    if (isCurrent) {
        return (
            <DropdownMenuItem className="flex items-center gap-2" disabled>
                <div className="flex size-6 items-center justify-center rounded-md border text-xs font-bold">
                    {workspace.name.charAt(0).toUpperCase()}
                </div>
                <div className="grid flex-1 text-left text-sm leading-tight">
                    <span className="truncate font-medium">
                        {workspace.name}
                    </span>
                </div>
                <Check className="size-4" />
            </DropdownMenuItem>
        );
    }

    return (
        <Form action={`/workspaces/${workspace.id}/select`} method="post">
            {({ processing }) => (
                <DropdownMenuItem
                    asChild
                    className="flex items-center gap-2"
                    disabled={processing}
                >
                    <button
                        type="submit"
                        className="flex w-full items-center gap-2"
                    >
                        <div className="flex size-6 items-center justify-center rounded-md border text-xs font-bold">
                            {workspace.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-medium">
                                {workspace.name}
                            </span>
                        </div>
                    </button>
                </DropdownMenuItem>
            )}
        </Form>
    );
}

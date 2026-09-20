import { router } from '@inertiajs/react';
import { Bell, Check } from 'lucide-react';
import { useCallback, useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface Notification {
    id: string;
    type: string;
    data: {
        title: string;
        message: string;
        workspace_name: string;
        workspace_slug: string;
        accept_url: string;
        token: string;
        review_url?: string;
    };
    read_at: string | null;
    created_at: string;
}

/**
 * Stored notification URLs may be absolute with a stale host (e.g. an old
 * ngrok tunnel or local server), so resolve them to a path on the current
 * origin before navigating.
 */
const resolveInternalPath = (url: string): string | null => {
    const parsed = new URL(url, window.location.origin);
    const path = parsed.pathname + parsed.search;

    return path.length > 1 ? path : null;
};

export function NotificationBell() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(true);
    const [open, setOpen] = useState(false);
    const [acceptingId, setAcceptingId] = useState<string | null>(null);

    const acceptInvitation = (notification: Notification) => {
        setAcceptingId(notification.id);
        router.post(
            `/invitations/${notification.data.token}/accept-as-member`,
            {},
            {
                onFinish: () => setAcceptingId(null),
                onSuccess: () => {
                    markAsRead(notification.id);
                    fetchNotifications();
                },
            },
        );
    };

    const fetchNotifications = useCallback(() => {
        fetch('/notifications', {
            headers: {
                Accept: 'application/json',
            },
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.status === 'Success') {
                    setNotifications(data.data.notifications);
                    setUnreadCount(data.data.unread_count);
                }
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, [fetchNotifications]);

    const markAsRead = (id: string) => {
        fetch(`/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error('Failed to mark notification as read');
                }

                return res.json();
            })
            .then(() => {
                setNotifications((prev) =>
                    prev.map((n) =>
                        n.id === id
                            ? { ...n, read_at: new Date().toISOString() }
                            : n,
                    ),
                );
                setUnreadCount((prev) => Math.max(0, prev - 1));
            })
            .catch(() => {});
    };

    const markAllAsRead = () => {
        fetch('/notifications/read-all', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error('Failed to mark notifications as read');
                }

                return res.json();
            })
            .then(() => {
                setNotifications((prev) =>
                    prev.map((n) => ({
                        ...n,
                        read_at: new Date().toISOString(),
                    })),
                );
                setUnreadCount(0);
            })
            .catch(() => {});
    };

    const openNotification = (
        event: React.MouseEvent,
        notification: Notification,
    ) => {
        if ((event.target as HTMLElement).closest('button')) {
            return;
        }

        const url = notification.data.review_url;

        if (!url) {
            return;
        }

        markAsRead(notification.id);

        const target = resolveInternalPath(url);

        if (target !== null) {
            router.visit(target);
        }
    };

    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9"
                >
                    <Bell className="size-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-notification px-1 text-xs text-notification-foreground">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Notifications</span>
                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-auto p-1 text-xs text-muted-foreground"
                            onClick={markAllAsRead}
                        >
                            Mark all read
                        </Button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {loading ? (
                    <div className="flex items-center justify-center py-4 text-muted-foreground">
                        Loading...
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="flex items-center justify-center py-4 text-muted-foreground">
                        No notifications
                    </div>
                ) : (
                    <div className="max-h-96 overflow-y-auto">
                        {notifications.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                className={cn(
                                    'flex flex-col items-start gap-1 p-3',
                                    !notification.read_at && 'bg-muted/50',
                                )}
                                onClick={(event) =>
                                    openNotification(event, notification)
                                }
                            >
                                <div className="flex w-full items-start justify-between gap-2">
                                    <span className="font-medium">
                                        {notification.data.title}
                                    </span>
                                    {!notification.read_at && (
                                        <span className="size-2 shrink-0 rounded-full bg-notification" />
                                    )}
                                </div>
                                <span className="line-clamp-2 text-sm text-muted-foreground">
                                    {notification.data.message}
                                </span>
                                <div className="flex w-full items-center justify-between">
                                    <span className="text-xs text-muted-foreground">
                                        {formatTime(notification.created_at)}
                                    </span>
                                    {notification.data.workspace_name && (
                                        <span className="text-xs text-muted-foreground">
                                            {notification.data.workspace_name}
                                        </span>
                                    )}
                                </div>
                                <div className="mt-1 flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-7"
                                        onClick={() =>
                                            markAsRead(notification.id)
                                        }
                                    >
                                        <Check className="mr-1 size-3" />
                                        Mark read
                                    </Button>
                                    {notification.data.token && (
                                        <Button
                                            size="sm"
                                            className="h-7"
                                            disabled={
                                                acceptingId === notification.id
                                            }
                                            onClick={() =>
                                                acceptInvitation(notification)
                                            }
                                        >
                                            {acceptingId === notification.id
                                                ? 'Accepting...'
                                                : 'Accept'}
                                        </Button>
                                    )}
                                </div>
                            </DropdownMenuItem>
                        ))}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

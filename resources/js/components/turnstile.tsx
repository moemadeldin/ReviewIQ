import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const TURNSTILE_SCRIPT =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

declare global {
    interface Window {
        turnstile?: {
            render: (
                container: HTMLElement,
                options: Record<string, unknown>,
            ) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
        };
    }
}

type Props = {
    onVerify?: (token: string) => void;
    onExpired?: () => void;
};

export default function Turnstile({ onVerify, onExpired }: Props) {
    const { turnstileSiteKey } = usePage<{ turnstileSiteKey: string }>().props;
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const onVerifyRef = useRef(onVerify);
    const onExpiredRef = useRef(onExpired);

    useEffect(() => {
        onVerifyRef.current = onVerify;
    }, [onVerify]);

    useEffect(() => {
        onExpiredRef.current = onExpired;
    }, [onExpired]);

    useEffect(() => {
        if (!turnstileSiteKey || !containerRef.current) {
            return;
        }

        const renderWidget = (): void => {
            if (window.turnstile && containerRef.current) {
                widgetIdRef.current = window.turnstile.render(
                    containerRef.current,
                    {
                        sitekey: turnstileSiteKey,
                        size: 'flexible',
                        'response-field-name': 'turnstile_token',
                        callback: (token: string) => {
                            onVerifyRef.current?.(token);
                        },
                        'expired-callback': () => {
                            onExpiredRef.current?.();
                            if (widgetIdRef.current) {
                                window.turnstile?.reset(widgetIdRef.current);
                            }
                        },
                        'error-callback': () => {
                            onExpiredRef.current?.();
                        },
                    },
                );
            }
        };

        const loadScript = (): void => {
            if (window.turnstile) {
                renderWidget();
                return;
            }

            const existing = document.querySelector<HTMLScriptElement>(
                `script[src="${TURNSTILE_SCRIPT}"]`,
            );

            if (existing) {
                existing.addEventListener('load', renderWidget);
                return;
            }

            const script = document.createElement('script');
            script.src = TURNSTILE_SCRIPT;
            script.async = true;
            script.defer = true;
            script.onload = renderWidget;
            document.head.appendChild(script);
        };

        loadScript();

        return () => {
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }
        };
    }, [turnstileSiteKey]);

    return (
        <div
            ref={containerRef}
            className="flex min-h-[65px] w-full min-w-0 items-center justify-center"
        />
    );
}

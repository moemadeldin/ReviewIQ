export const APP_CONSTANTS = {
    name: 'ReviewIQ',
    ogImage: '/og-image.png',
    ogImageWidth: 1200,
    ogImageHeight: 630,
    githubScopes: ['read:user', 'repo'] as const,

    privacy: {
        diffsSentToOpenRouter: true,
        diffsCachedMinutes: 5,
        codeNeverLeavesServers: false,
    },

    scoreBands: [
        { min: 90, max: 100, label: 'Excellent', color: 'var(--score-high)' },
        { min: 70, max: 89, label: 'Good', color: 'var(--score-medium)' },
        { min: 50, max: 69, label: 'Needs work', color: 'var(--score-low)' },
        { min: 30, max: 49, label: 'Poor', color: 'var(--score-critical)' },
        { min: 0, max: 29, label: 'Critical', color: 'var(--score-critical)' },
    ] as const,
} as const;

export type ScoreBand = (typeof APP_CONSTANTS.scoreBands)[number];

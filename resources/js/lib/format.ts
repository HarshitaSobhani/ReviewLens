export function formatDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function formatNumber(n: number | null | undefined): string {
    return n === null || n === undefined ? '—' : n.toLocaleString();
}

export function pct(part: number, total: number): number {
    return total > 0 ? Math.round((part / total) * 100) : 0;
}

export function titleCase(s: string): string {
    return s.charAt(0).toUpperCase() + s.slice(1);
}

export const sentimentLabel: Record<string, string> = {
    positive: 'Positive', negative: 'Negative', neutral: 'Neutral', mixed: 'Mixed',
};

export const methodLabel: Record<string, string> = {
    lexicon: 'Text word-list', 'lexicon+rating': 'Text + star rating', rating: 'Star rating only',
    model: 'AI model', none: 'No signal',
};

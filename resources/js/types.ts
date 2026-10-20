export type Sentiment = 'positive' | 'negative' | 'neutral' | 'mixed';
export type Confidence = 'low' | 'medium' | 'high';
export type RunStatus = 'pending' | 'running' | 'completed' | 'failed';

export interface AuthUser { id: number; name: string; email: string }

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    flash: { success?: string | null; error?: string | null };
    ai: { configured: boolean };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface ProductSummary {
    id: number; name: string; category: string | null; brand: string | null;
    price: string | null; image_url: string | null; is_demo: boolean; reviews_count: number;
    description?: string | null;
}

export interface AnalysisSummary {
    id: number; product_id: number; product_name: string | null; status: RunStatus; engine: string;
    review_count: number; average_rating: number | null;
    sentiment_distribution: Record<Sentiment, number> | null;
    created_at: string; finished_at: string | null; error: string | null; includes_demo: boolean;
}

export interface Evidence {
    review_id: number; excerpt: string; rating: number | null; date: string | null;
    source: string | null; is_demo: boolean; title: string | null;
}

export interface Finding {
    id: number; type: 'overview' | 'advantage' | 'complaint' | 'mixed' | 'caveat'; statement: string;
    strength: 'weak' | 'moderate' | 'strong'; origin: string; theme_id: number | null; evidence: Evidence[];
}

export interface ThemeRow {
    id: number; key: string; label: string; is_discovered: boolean; mention_count: number;
    positive: number; negative: number; neutral: number; method: string;
    top_terms: string[] | null; limitations: string[] | null;
}

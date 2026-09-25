/** How a figure is written out: a count, rupees or a percentage. */
export type AnalyticsFormat =
    | 'number'
    | 'money'
    | 'percent'
    | 'text'
    | 'date'
    | 'status';

export type AnalyticsPeriod = {
    key: string;
    label: string;
    from: string;
    to: string;
    granularity: 'day' | 'week' | 'month';
};

export type AnalyticsStat = {
    label: string;
    value: number;
    previous: number | null;
    format: AnalyticsFormat;
    icon: string;
    color: string;
};

export type AnalyticsDatum = { label: string; value: number; color?: string };

export type AnalyticsChart = {
    title: string;
    type: 'trend' | 'bar' | 'donut';
    format: AnalyticsFormat;
    data: AnalyticsDatum[];
};

export type AnalyticsTable = {
    title: string;
    columns: { key: string; label: string; format: AnalyticsFormat }[];
    rows: Record<string, string | number | null>[];
};

export type AnalyticsReport = {
    stats: AnalyticsStat[];
    charts: AnalyticsChart[];
    tables: AnalyticsTable[];
};

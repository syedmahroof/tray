/**
 * Options for the "no visit report within" filter on the contacts, projects,
 * and builders lists. Values must match VisitReport::NO_VISIT_PERIODS keys.
 */
export const noVisitPeriodOptions = [
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: '60d', label: 'Last 60 days' },
    { value: '3m', label: 'Last 3 months' },
    { value: '6m', label: 'Last 6 months' },
    { value: '1y', label: 'Last year' },
] as const;

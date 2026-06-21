export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

export type Filters = {
    search: string;
};

export type ReminderNotification = {
    id: number;
    title: string;
    remind_at: string;
    url: string | null;
    subject: string | null;
};

export type Notifications = {
    items: ReminderNotification[];
    total: number;
};

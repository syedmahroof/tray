import type { Country, District, NamedOption, State } from '@/types/admin';

export type Contact = {
    id: number;
    branch_id: number;
    contact_type_id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    country_id: number | null;
    state_id: number | null;
    district_id: number | null;
    assigned_to: number | null;
    created_by: number | null;
    created_at: string;
};

export type ContactListItem = Contact & {
    contact_type: NamedOption;
    assignee: NamedOption | null;
    creator: NamedOption | null;
};

export type ContactDetail = Contact & {
    contact_type: NamedOption;
    country: Country | null;
    state: State | null;
    district: District | null;
    assignee: NamedOption | null;
    creator: NamedOption | null;
    branch: NamedOption;
};

export type Customer = {
    id: number;
    branch_id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    country_id: number | null;
    state_id: number | null;
    district_id: number | null;
    assigned_to: number | null;
};

export type CustomerListItem = Customer & {
    assignee: NamedOption | null;
};

export type CustomerDetail = Customer & {
    country: Country | null;
    state: State | null;
    district: District | null;
    assignee: NamedOption | null;
    branch: NamedOption;
};

export type EnquiryStatus = 'new' | 'in_progress' | 'converted' | 'lost';

export type Enquiry = {
    id: number;
    branch_id: number;
    contact_id: number;
    project_id: number | null;
    product_id: number | null;
    assigned_to: number | null;
    status: EnquiryStatus;
    source: string | null;
    remarks: string | null;
};

export type EnquiryListItem = Enquiry & {
    contact: NamedOption;
    project: NamedOption | null;
    product: NamedOption | null;
    assignee: NamedOption | null;
};

export type EnquiryDetail = Enquiry & {
    contact: NamedOption;
    project: NamedOption | null;
    product: NamedOption | null;
    assignee: NamedOption | null;
    branch: NamedOption;
};

export type EnquiryStatusCount = { status: EnquiryStatus; count: number };

export type ActivityAuthor = {
    id: number;
    name: string;
};

export type Note = {
    id: number;
    body: string;
    user: ActivityAuthor;
    created_at: string;
};

export type Reminder = {
    id: number;
    title: string;
    remind_at: string;
    status: 'pending' | 'done';
    user: ActivityAuthor;
    created_at: string;
};

export type VisitType =
    | 'Site Visit'
    | 'Client Meeting'
    | 'Follow-up'
    | 'Inspection'
    | 'Other';

export type VisitReport = {
    id: number;
    branch_id: number;
    visit_date: string;
    visit_type: VisitType;
    objective: string;
    report: string | null;
    next_meeting_date: string | null;
    next_call_date: string | null;
    created_at: string;
};

export type VisitReportListItem = VisitReport & {
    user: ActivityAuthor;
    projects: NamedOption[];
    customers: NamedOption[];
    contacts: NamedOption[];
};

export type VisitReportDetail = VisitReport & {
    user: ActivityAuthor;
    branch: NamedOption;
    projects: NamedOption[];
    customers: NamedOption[];
    contacts: NamedOption[];
};

export type VisitReportTypeCount = { type: VisitType; count: number };

export type VisitReportMonthCount = { month: string; count: number };

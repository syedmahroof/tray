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

/** A contact shaped for a select box, carrying its type for the label. */
export type ContactSelectOption = NamedOption & {
    contact_type: NamedOption | null;
};

/** Build a "Name (Type)" label for a contact select option. */
export function contactOptionLabel(contact: ContactSelectOption): string {
    return contact.contact_type
        ? `${contact.name} (${contact.contact_type.name})`
        : contact.name;
}

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
    gst_number: string | null;
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
    customer_id: number | null;
    contact_id: number | null;
    project_id: number | null;
    product_id: number | null;
    assigned_to: number | null;
    status: EnquiryStatus;
    source: string | null;
    remarks: string | null;
};

export type EnquiryListItem = Enquiry & {
    customer: NamedOption | null;
    contact: NamedOption | null;
    project: NamedOption | null;
    product: NamedOption | null;
    assignee: NamedOption | null;
};

export type EnquiryDetail = Enquiry & {
    customer: NamedOption | null;
    contact: NamedOption | null;
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
    builders: NamedOption[];
};

export type VisitReportDetail = VisitReport & {
    user: ActivityAuthor;
    branch: NamedOption;
    projects: NamedOption[];
    customers: NamedOption[];
    contacts: NamedOption[];
    builders: NamedOption[];
};

/** A past visit report sharing a linked entity with the current one. */
export type VisitReportHistoryItem = {
    id: number;
    visit_date: string;
    visit_type: VisitType;
    objective: string;
    user: ActivityAuthor | null;
    contacts: NamedOption[];
    customers: NamedOption[];
    projects: NamedOption[];
    builders: NamedOption[];
};

/** A single audit-log timeline entry. */
export type AuditLogEntry = {
    id: number;
    action: string;
    description: string;
    user: ActivityAuthor | null;
    created_at: string;
};

export type VisitReportTypeCount = { type: VisitType; count: number };

export type VisitReportMonthCount = { month: string; count: number };

export type QuotationStatus =
    | 'draft'
    | 'sent'
    | 'accepted'
    | 'rejected'
    | 'expired';

export type QuotationItem = {
    id: number;
    quotation_id: number;
    product_id: number | null;
    description: string;
    hsn_code: string | null;
    quantity: string;
    unit_price: string;
    tax_percentage: string;
    tax_amount: string;
    product?: NamedOption | null;
};

export type QuotationSupplyType = 'intra' | 'inter';

export type Quotation = {
    id: number;
    branch_id: number;
    number: string;
    version: number;
    parent_id: number | null;
    customer_id: number | null;
    contact_id: number | null;
    project_id: number | null;
    enquiry_id: number | null;
    builder_id: number | null;
    gstin: string | null;
    supply_type: QuotationSupplyType;
    quotation_date: string;
    valid_until: string | null;
    status: QuotationStatus;
    subtotal: string;
    discount: string;
    tax_percent: string;
    tax_amount: string;
    cgst_amount: string;
    sgst_amount: string;
    igst_amount: string;
    total: string;
    notes: string | null;
    terms: string | null;
    created_by: number | null;
    created_at: string;
};

export type QuotationListItem = Quotation & {
    customer: NamedOption | null;
    contact: NamedOption | null;
    project: NamedOption | null;
    creator: NamedOption | null;
};

export type QuotationDetail = Quotation & {
    customer: NamedOption | null;
    contact:
        | (NamedOption & { phone: string | null; email: string | null })
        | null;
    project: NamedOption | null;
    enquiry: { id: number; contact: NamedOption | null } | null;
    builder: NamedOption | null;
    creator: NamedOption | null;
    branch: NamedOption;
    items: QuotationItem[];
};

/**
 * A compact quotation row for listing on related profile pages
 * (contact, project, enquiry, builder).
 */
export type QuotationSummary = Pick<
    Quotation,
    | 'id'
    | 'number'
    | 'version'
    | 'status'
    | 'total'
    | 'quotation_date'
    | 'created_at'
>;

/** A single entry in a quotation's revision history. */
export type QuotationVersion = Pick<
    Quotation,
    'id' | 'number' | 'version' | 'status' | 'total' | 'created_at'
>;

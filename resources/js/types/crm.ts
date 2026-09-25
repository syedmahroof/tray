import type {
    Country,
    District,
    Location,
    LocationWithDistrict,
    NamedOption,
    RateTier,
    Route,
    State,
} from '@/types/admin';

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
    location_id: number | null;
    route_id: number | null;
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
    location: Location | null;
    route: Route | null;
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
    location_id: number | null;
    route_id: number | null;
    assigned_to: number | null;
    /** The rate tier this customer's quotations are priced at by default. */
    rate_tier: RateTier | null;
};

export type CustomerListItem = Customer & {
    assignee: NamedOption | null;
};

export type CustomerDetail = Customer & {
    country: Country | null;
    state: State | null;
    district: District | null;
    location: Location | null;
    route: Route | null;
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
    location_id: number | null;
    route_id: number | null;
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
    location: LocationWithDistrict | null;
    route: Route | null;
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

/** A product in the quotation line picker, with its ex-tax rates per branch. */
export type QuotationProductOption = NamedOption & {
    price: string | null;
    taxable_amount: string | null;
    hsn_code: string | null;
    tax_percentage: string;
    rates: Record<number, Record<RateTier, string | null>>;
};

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
    rate_tier: RateTier | null;
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
    customer:
        | (NamedOption & {
              phone: string | null;
              email: string | null;
              address: string | null;
              gst_number: string | null;
              state: (NamedOption & { code: string | null }) | null;
          })
        | null;
    contact:
        | (NamedOption & {
              phone: string | null;
              email: string | null;
              address: string | null;
              state: (NamedOption & { code: string | null }) | null;
          })
        | null;
    project: NamedOption | null;
    enquiry: { id: number; contact: NamedOption | null } | null;
    builder: NamedOption | null;
    creator: NamedOption | null;
    branch: NamedOption;
    items: QuotationItem[];
};

/** One printed line of a quotation. */
export type InvoiceLine = {
    sl: number;
    description: string;
    hsn: string | null;
    quantity: number;
    unit: string;
    rate: number;
    amount: number;
    detail: string | null;
    discount_percent: number;
    net_rate: number;
    net_amount: number;
};

/** One row of the HSN tax summary printed under the totals. */
export type InvoiceTaxSummaryRow = {
    hsn: string;
    taxable: number;
    rate: number;
    cgst: number;
    sgst: number;
    igst: number;
    tax: number;
};

/** A quotation worked out the way its printed invoice states it. */
export type QuotationInvoice = {
    lines: InvoiceLine[];
    subtotal: number;
    discount: number;
    discount_percent: number;
    taxable_value: number;
    tax_lines: { label: string; rate: number; amount: number }[];
    tax_total: number;
    round_off: number;
    total: number;
    total_quantity: number;
    total_unit: string | null;
    hsn_summary: InvoiceTaxSummaryRow[];
    inter_state: boolean;
    amount_in_words: string;
    tax_in_words: string;
};

/** The seller identity a quotation is raised under. */
export type CompanyProfile = {
    name?: string | null;
    address?: string[];
    phone?: string | null;
    mobile?: string | null;
    udyam?: string | null;
    gstin?: string | null;
    state?: { name?: string | null; code?: string | null };
    email?: string | null;
    bank?: {
        name?: string | null;
        account?: string | null;
        branch?: string | null;
        ifsc?: string | null;
        branch_ifsc?: string | null;
    };
    website?: string | null;
    declaration?: string | null;
    quotation_title?: string | null;
    footer?: string | null;
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

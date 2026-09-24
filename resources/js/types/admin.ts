export type Branch = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    city: string | null;
    /** The letterhead this branch prints on its quotations. */
    company_name?: string | null;
    logo_url?: string | null;
    phone?: string | null;
    mobile?: string | null;
    email?: string | null;
    website?: string | null;
    gstin?: string | null;
    quotation_terms?: string | null;
    /** The brands shown under this branch's quotation footer. */
    brand_ids?: number[];
    /** The account this branch collects into, printed on its quotations. */
    bank_name?: string | null;
    bank_account_number?: string | null;
    bank_branch?: string | null;
    bank_ifsc?: string | null;
    is_active: boolean;
};

export type RoleSummary = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
};

export type RoleDetail = {
    id: number;
    name: string;
    permissions: string[];
};

export type PermissionGroups = Record<string, string[]>;

export type UserListItem = {
    id: number;
    name: string;
    email: string;
    branch: Branch | null;
    roles: { id: number; name: string }[];
};

export type UserDetail = {
    id: number;
    name: string;
    email: string;
    branch_id: number | null;
    branch_ids: number[];
    brand_ids: number[];
    role: string | null;
};

export type CategoryItem = {
    id: number;
    name: string;
    is_active: boolean;
    /** Set on product categories. */
    image_url?: string | null;
    /** Set on brands. */
    logo_url?: string | null;
    /** Optional count (e.g. product count on categories). */
    products_count?: number;
};

export type Country = {
    id: number;
    name: string;
    code: string | null;
};

export type State = {
    id: number;
    country_id: number;
    name: string;
    code: string | null;
};

export type District = {
    id: number;
    state_id: number;
    name: string;
};

export type StateWithCountry = State & {
    country: Country;
};

/** A locality within a district: the finest grain of the location tree. */
export type Location = {
    id: number;
    district_id: number;
    name: string;
    pincode: string | null;
    is_active: boolean;
};

export type LocationWithDistrict = Location & {
    district: NamedOption;
};

/** A district as a picker offers it, qualified by the state it sits in. */
export type DistrictOption = NamedOption & { state: NamedOption };

/** A state as the listing filters offer it: just enough to group by country. */
export type FilterState = NamedOption & { country_id: number };

/** A district as the listing filters offer it: just enough to group by state. */
export type FilterDistrict = NamedOption & { state_id: number };

/**
 * The place pickers offered on a listing screen, each list holding only what
 * the listing actually uses.
 */
export type PlaceFilterOptions = {
    countries: NamedOption[];
    states: FilterState[];
    districts: FilterDistrict[];
    locations: LocationWithDistrict[];
    routes: Route[];
};

/** The place filters a listing is narrowed by; "all" means no filter. */
export type PlaceFilterValues = {
    country_id: string;
    state_id: string;
    district_id: string;
    location_id: string;
    route_id: string;
};

export type DistrictWithState = District & {
    state: StateWithCountry;
};

/** A sales route, shared across districts rather than nested under one. */
export type Route = {
    id: number;
    name: string;
    is_active: boolean;
};

export type Builder = {
    id: number;
    branch_id: number;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    country_id: number | null;
    state_id: number | null;
    district_id: number | null;
    location_id: number | null;
    route_id: number | null;
    is_active: boolean;
    assigned_to: number | null;
    created_by: number | null;
    created_at: string;
};

export type BuilderListItem = Builder & {
    country: Country | null;
    state: State | null;
    district: District | null;
    location: Location | null;
    route: Route | null;
    assignee: NamedOption | null;
    creator: NamedOption | null;
};

export type NamedOption = {
    id: number;
    name: string;
};

export type ProjectContact = {
    id: number;
    project_id: number;
    name: string;
    role: string | null;
    phone: string | null;
    email: string | null;
};

export type Project = {
    id: number;
    branch_id: number;
    builder_id: number | null;
    project_category_id: number;
    name: string;
    address: string | null;
    country_id: number | null;
    state_id: number | null;
    district_id: number | null;
    location_id: number | null;
    route_id: number | null;
    status: string;
    description: string | null;
    owner_name: string | null;
    owner_phone: string | null;
    owner_email: string | null;
    location: string | null;
    pincode: string | null;
    expected_maturity: string | null;
    preferred_material: string | null;
    assignee_id: number | null;
    created_by: number | null;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    contacts?: NamedOption[];
    project_contacts?: ProjectContact[];
    products?: NamedOption[];
};

export type ProjectListItem = Project & {
    builder: NamedOption | null;
    location_master: Location | null;
    route: Route | null;
    project_category: NamedOption;
    assignee: NamedOption | null;
    creator: NamedOption | null;
};

export type RateTier = 'SR' | 'PR' | 'CR';

/** What a price row's tier percentages are worked out from. */
export type RateBasis = 'mrp' | 'cost';

export type ProductBranchPrice = {
    id: number;
    product_id: number;
    branch_id: number;
    branch?: Branch;
    cost: string | null;
    mrp: string | null;
    rate_basis: RateBasis;
    sr_discount: string | null;
    sr_rate: string | null;
    sr_rate_with_tax: string | null;
    pr_discount: string | null;
    pr_rate: string | null;
    pr_rate_with_tax: string | null;
    cr_discount: string | null;
    cr_rate: string | null;
    cr_rate_with_tax: string | null;
    effective_from: string | null;
    is_active: boolean;
    notes: string | null;
};

export type ProductPriceHistory = {
    id: number;
    product_id: number;
    branch_id: number;
    branch?: Branch;
    user: NamedOption | null;
    field: string;
    old_value: string | null;
    new_value: string | null;
    reason: string | null;
    changed_at: string;
};

export type Product = {
    id: number;
    product_category_id: number;
    brand_id: number | null;
    code: string | null;
    name: string;
    unit: string | null;
    hsn_code: string | null;
    price: string | null;
    taxable_amount: string | null;
    tax_type: string | null;
    tax_percentage: string;
    area_sqft: string | null;
    description: string | null;
    image_url: string | null;
    created_by: number | null;
    created_at: string;
};

/** One product's rates in one branch, as the price list renders them. */
export type BranchPriceCells = {
    cost: string | null;
    mrp: string | null;
    rate_basis: RateBasis;
    sr_discount: string | null;
    sr_rate: string | null;
    sr_rate_with_tax: string | null;
    pr_discount: string | null;
    pr_rate: string | null;
    pr_rate_with_tax: string | null;
    cr_discount: string | null;
    cr_rate: string | null;
    cr_rate_with_tax: string | null;
};

/** A row of the workbook-shaped price list. */
export type PriceMatrixRow = {
    id: number;
    code: string | null;
    name: string;
    unit: string | null;
    hsn_code: string | null;
    tax_percentage: string;
    category: string | null;
    category_id: number;
    brand: string | null;
    prices: Record<number, BranchPriceCells | null>;
};

export type ProductFilters = {
    search: string;
    product_category_ids: number[] | null;
    brand_ids: number[] | null;
    branch_ids: number[] | null;
    unit: string | null;
    created_by?: string | number | null;
    created_from?: string | null;
    created_to?: string | null;
};

export type ProductListItem = Product & {
    product_category: NamedOption;
    brand: NamedOption | null;
    creator: NamedOption | null;
    branch_prices_count?: number;
    branch_prices?: ProductBranchPrice[];
};

export type Branch = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    city: string | null;
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
    is_active: boolean;
    created_by: number | null;
    created_at: string;
};

export type BuilderListItem = Builder & {
    country: Country | null;
    state: State | null;
    district: District | null;
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
    project_category: NamedOption;
    creator: NamedOption | null;
};

export type Product = {
    id: number;
    branch_id: number;
    product_category_id: number;
    brand_id: number | null;
    name: string;
    price: string | null;
    area_sqft: string | null;
    description: string | null;
    created_by: number | null;
    created_at: string;
};

export type ProductListItem = Product & {
    product_category: NamedOption;
    brand: NamedOption | null;
    creator: NamedOption | null;
};

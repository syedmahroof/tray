<?php

namespace App\Support;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class McpListings
{
    /**
     * @return array<string, array{model: class-string<Model>, search: list<string>, filters: list<string>, columns?: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            'quotations' => ['model' => Models\Quotation::class, 'search' => ['number'], 'filters' => ['status', 'branch_id', 'customer_id', 'contact_id', 'project_id', 'builder_id', 'created_by']],
            'sales-orders' => ['model' => Models\Order::class, 'search' => ['number'], 'filters' => ['status', 'branch_id', 'customer_id', 'created_by']],
            'purchase-orders' => ['model' => Models\Order::class, 'search' => ['number'], 'filters' => ['status', 'branch_id', 'supplier_id', 'created_by']],
            'contacts' => ['model' => Models\Contact::class, 'search' => ['name', 'phone', 'email'], 'filters' => ['branch_id', 'contact_type_id', 'assigned_to', 'location_id', 'route_id']],
            'customers' => ['model' => Models\Customer::class, 'search' => ['name', 'phone', 'email'], 'filters' => ['branch_id', 'assigned_to', 'location_id', 'route_id']],
            'projects' => ['model' => Models\Project::class, 'search' => ['name', 'owner_name', 'owner_phone'], 'filters' => ['status', 'branch_id', 'builder_id', 'project_category_id', 'assignee_id', 'location_id', 'route_id']],
            'builders' => ['model' => Models\Builder::class, 'search' => ['name', 'phone', 'email'], 'filters' => ['branch_id', 'assigned_to', 'is_active', 'location_id', 'route_id']],
            'visit-reports' => ['model' => Models\VisitReport::class, 'search' => ['objective', 'report'], 'filters' => ['branch_id', 'user_id', 'visit_type', 'visit_date', 'location_id', 'route_id']],
            'products' => ['model' => Models\Product::class, 'search' => ['name', 'code'], 'filters' => ['brand_id', 'product_category_id', 'is_active']],
            'enquiries' => ['model' => Models\Enquiry::class, 'search' => ['remarks'], 'filters' => ['status', 'branch_id', 'contact_id', 'project_id', 'assigned_to']],
            'suppliers' => ['model' => Models\Supplier::class, 'search' => ['name', 'phone', 'email'], 'filters' => ['is_active']],
            'reminders' => ['model' => Models\Reminder::class, 'search' => ['title'], 'filters' => ['status']],
            'branches' => ['model' => Models\Branch::class, 'search' => ['name', 'code'], 'filters' => [], 'columns' => ['id', 'name', 'code']],
            'brands' => ['model' => Models\Brand::class, 'search' => ['name'], 'filters' => []],
            'product-categories' => ['model' => Models\ProductCategory::class, 'search' => ['name'], 'filters' => []],
            'project-categories' => ['model' => Models\ProjectCategory::class, 'search' => ['name'], 'filters' => []],
            'contact-types' => ['model' => Models\ContactType::class, 'search' => ['name'], 'filters' => []],
            'countries' => ['model' => Models\Country::class, 'search' => ['name'], 'filters' => []],
            'states' => ['model' => Models\State::class, 'search' => ['name'], 'filters' => ['country_id']],
            'districts' => ['model' => Models\District::class, 'search' => ['name'], 'filters' => ['state_id']],
            'locations' => ['model' => Models\Location::class, 'search' => ['name'], 'filters' => ['district_id']],
            'routes' => ['model' => Models\Route::class, 'search' => ['name'], 'filters' => []],
            'users' => ['model' => Models\User::class, 'search' => ['name', 'email'], 'filters' => ['branch_id'], 'columns' => ['id', 'name', 'email', 'branch_id']],
            'roles' => ['model' => Role::class, 'search' => ['name'], 'filters' => [], 'columns' => ['id', 'name']],
        ];
    }
}

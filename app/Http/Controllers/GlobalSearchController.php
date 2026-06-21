<?php

namespace App\Http\Controllers;

use App\Models\Builder;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Project;
use App\Models\VisitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Handle the incoming request to search builders, projects, products, contacts, customers, enquiries, and visit reports.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->input('q');

        if (! is_string($query) || trim($query) === '') {
            return response()->json([
                'projects' => [],
                'builders' => [],
                'contacts' => [],
                'products' => [],
                'customers' => [],
                'enquiries' => [],
                'visit_reports' => [],
            ]);
        }

        $query = trim($query);

        $projects = Project::query()
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'url' => route('projects.show', $project),
            ]);

        $builders = Builder::query()
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn ($builder) => [
                'id' => $builder->id,
                'name' => $builder->name,
                'url' => route('builders.show', $builder),
            ]);

        $contacts = Contact::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'url' => route('contacts.show', $contact),
            ]);

        $products = Product::query()
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'url' => route('products.show', $product),
            ]);

        $customers = Customer::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'url' => route('customers.show', $customer),
            ]);

        $enquiries = Enquiry::query()
            ->where(function ($q) use ($query) {
                $q->where('status', 'like', "%{$query}%")
                    ->orWhere('source', 'like', "%{$query}%")
                    ->orWhere('remarks', 'like', "%{$query}%")
                    ->orWhereHas('contact', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('project', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('product', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->with(['contact', 'project', 'product'])
            ->get()
            ->map(fn ($enquiry) => [
                'id' => $enquiry->id,
                'contact_name' => $enquiry->contact->name,
                'project_name' => $enquiry->project?->name,
                'product_name' => $enquiry->product?->name,
                'status' => $enquiry->status,
                'url' => route('enquiries.show', $enquiry),
            ]);

        $visitReports = VisitReport::query()
            ->where(function ($q) use ($query) {
                $q->where('visit_type', 'like', "%{$query}%")
                    ->orWhere('objective', 'like', "%{$query}%")
                    ->orWhere('report', 'like', "%{$query}%")
                    ->orWhereHas('projects', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('customers', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('contacts', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get()
            ->map(fn ($report) => [
                'id' => $report->id,
                'visit_type' => $report->visit_type,
                'objective' => $report->objective,
                'visit_date' => $report->visit_date->format('Y-m-d'),
                'url' => route('visit-reports.show', $report),
            ]);

        return response()->json([
            'projects' => $projects,
            'builders' => $builders,
            'contacts' => $contacts,
            'products' => $products,
            'customers' => $customers,
            'enquiries' => $enquiries,
            'visit_reports' => $visitReports,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Builder;
use App\Models\ContactType;
use App\Models\Country;
use App\Models\District;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'countries' => Country::orderBy('name')->get(['id', 'name']),
            'states' => State::orderBy('name')->get(['id', 'name', 'country_id']),
            'districts' => District::orderBy('name')->get(['id', 'name', 'state_id']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'contact_types' => ContactType::orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'project_categories' => ProjectCategory::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'builders' => Builder::orderBy('name')->get(['id', 'name']),
        ]);
    }
}

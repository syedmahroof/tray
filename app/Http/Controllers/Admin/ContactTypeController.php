<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveContactTypeRequest;
use App\Models\ContactType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactTypeController extends Controller
{
    /**
     * Display a listing of contact types.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/contact-types/Index', [
            'contactTypes' => ContactType::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created contact type.
     */
    public function store(SaveContactTypeRequest $request): RedirectResponse
    {
        ContactType::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact type created.')]);

        return to_route('contact-types.index');
    }

    /**
     * Update the given contact type.
     */
    public function update(SaveContactTypeRequest $request, ContactType $contactType): RedirectResponse
    {
        $contactType->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact type updated.')]);

        return to_route('contact-types.index');
    }

    /**
     * Remove the given contact type.
     */
    public function destroy(ContactType $contactType): RedirectResponse
    {
        if ($contactType->contacts()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a contact type that still has contacts.')]);

            return back();
        }

        $contactType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact type deleted.')]);

        return to_route('contact-types.index');
    }
}

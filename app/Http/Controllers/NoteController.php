<?php

namespace App\Http\Controllers;

use App\Actions\Activity\CreateNote;
use App\Http\Requests\SaveNoteRequest;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NoteController extends Controller
{
    /**
     * Add a note to the given contact.
     */
    public function storeForContact(SaveNoteRequest $request, Contact $contact, CreateNote $action): RedirectResponse
    {
        $action->handle($contact, $request->user(), $request->validated('body'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note added.')]);

        return back();
    }

    /**
     * Add a note to the given enquiry.
     */
    public function storeForEnquiry(SaveNoteRequest $request, Enquiry $enquiry, CreateNote $action): RedirectResponse
    {
        $action->handle($enquiry, $request->user(), $request->validated('body'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note added.')]);

        return back();
    }

    /**
     * Remove the given note.
     */
    public function destroy(Note $note): RedirectResponse
    {
        $note->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note deleted.')]);

        return back();
    }
}

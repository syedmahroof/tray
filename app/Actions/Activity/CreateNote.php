<?php

namespace App\Actions\Activity;

use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Note;
use App\Models\User;

class CreateNote
{
    /**
     * Add a note to the given notable model.
     */
    public function handle(Contact|Enquiry $notable, User $author, string $body): Note
    {
        return $notable->notes()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }
}

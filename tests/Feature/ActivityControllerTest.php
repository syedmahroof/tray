<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a note can be added to a contact and to an enquiry', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $enquiry = Enquiry::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($user)
        ->post(route('contacts.notes.store', $contact), ['body' => 'Called the customer.'])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('enquiries.notes.store', $enquiry), ['body' => 'Following up next week.'])
        ->assertRedirect();

    expect(Note::where('notable_type', Contact::class)->where('notable_id', $contact->id)->count())->toBe(1);
    expect(Note::where('notable_type', Enquiry::class)->where('notable_id', $enquiry->id)->count())->toBe(1);
});

test('users without permission cannot add a note', function () {
    $branch = Branch::factory()->create();
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->post(route('contacts.notes.store', $contact), ['body' => 'Hello'])
        ->assertForbidden();
});

test('a note can be deleted', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $note = Note::factory()->create(['notable_type' => Contact::class, 'notable_id' => $contact->id]);

    $this->actingAs($user)
        ->delete(route('notes.destroy', $note))
        ->assertRedirect();

    $this->assertModelMissing($note);
});

test('a reminder can be added to a contact and to an enquiry', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $enquiry = Enquiry::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($user)
        ->post(route('contacts.reminders.store', $contact), [
            'title' => 'Call back',
            'remind_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('enquiries.reminders.store', $enquiry), [
            'title' => 'Send brochure',
            'remind_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    expect(Reminder::where('remindable_type', Contact::class)->where('remindable_id', $contact->id)->count())->toBe(1);
    expect(Reminder::where('remindable_type', Enquiry::class)->where('remindable_id', $enquiry->id)->count())->toBe(1);
});

test('a reminder can be deleted', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $reminder = Reminder::factory()->create(['remindable_type' => Contact::class, 'remindable_id' => $contact->id]);

    $this->actingAs($user)
        ->delete(route('reminders.destroy', $reminder))
        ->assertRedirect();

    $this->assertModelMissing($reminder);
});

test('a visit report can be added and linked to projects, customers, and contacts', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $project = Project::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->post(route('visit-reports.store'), [
            'visit_date' => now()->format('Y-m-d'),
            'visit_type' => 'Site Visit',
            'objective' => 'Site inspection',
            'contact_ids' => [$contact->id],
            'project_ids' => [$project->id],
        ])
        ->assertRedirect();

    $report = VisitReport::latest()->first();
    expect($report->contacts->pluck('id')->toArray())->toContain($contact->id);
    expect($report->projects->pluck('id')->toArray())->toContain($project->id);
});

test('a visit report can be deleted', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');

    $visitReport = VisitReport::factory()->create(['branch_id' => $branch->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('visit-reports.destroy', $visitReport))
        ->assertRedirect();

    $this->assertModelMissing($visitReport);
});

test('deleting a contact also deletes its activity log', function () {
    $branch = Branch::factory()->create();
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $note = Note::factory()->create(['notable_type' => Contact::class, 'notable_id' => $contact->id]);
    $reminder = Reminder::factory()->create(['remindable_type' => Contact::class, 'remindable_id' => $contact->id]);

    $contact->delete();

    $this->assertModelMissing($note);
    $this->assertModelMissing($reminder);
});

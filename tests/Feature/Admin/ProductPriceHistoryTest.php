<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->action = app(SaveProductBranchPrice::class);
    $this->product = Product::factory()->create(['tax_percentage' => 18]);
    $this->branch = Branch::factory()->create();
});

test('changing a rate logs the old and new value against the user', function () {
    $user = User::factory()->create();

    $this->action->handle($this->product, $this->branch, ['sr_rate' => 62.40]);
    $this->action->handle($this->product, $this->branch, ['sr_rate' => 70.00], $user, 'April revision');

    $entry = ProductPriceHistory::where('field', 'sr_rate')->sole();

    expect((float) $entry->old_value)->toBe(62.40);
    expect((float) $entry->new_value)->toBe(70.00);
    expect($entry->user_id)->toBe($user->id);
    expect($entry->branch_id)->toBe($this->branch->id);
    expect($entry->reason)->toBe('April revision');
});

test('a derived tax inclusive rate is logged alongside the rate that moved', function () {
    $this->action->handle($this->product, $this->branch, ['sr_rate' => 62.40]);
    $this->action->handle($this->product, $this->branch, ['sr_rate' => 70.00]);

    expect(ProductPriceHistory::pluck('field')->all())
        ->toEqualCanonicalizing(['sr_rate', 'sr_rate_with_tax']);
});

test('re-saving identical values logs nothing', function () {
    $this->action->handle($this->product, $this->branch, ['cost' => 40, 'sr_rate' => 62.40]);
    $this->action->handle($this->product, $this->branch, ['cost' => 40, 'sr_rate' => 62.40]);

    expect(ProductPriceHistory::count())->toBe(0);
});

test('history is recorded per branch', function () {
    $other = Branch::factory()->create();

    $this->action->handle($this->product, $this->branch, ['sr_rate' => 62.40]);
    $this->action->handle($this->product, $this->branch, ['sr_rate' => 70.00]);
    $this->action->handle($this->product, $other, ['sr_rate' => 80.00]);
    $this->action->handle($this->product, $other, ['sr_rate' => 90.00]);

    expect(ProductPriceHistory::where('branch_id', $this->branch->id)->where('field', 'sr_rate')->count())->toBe(1);
    expect(ProductPriceHistory::where('branch_id', $other->id)->where('field', 'sr_rate')->count())->toBe(1);
    expect($this->product->priceHistories()->count())->toBe(4);
});

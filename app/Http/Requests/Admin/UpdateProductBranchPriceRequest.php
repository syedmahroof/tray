<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use App\Models\ProductBranchPrice;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One or more figures edited in place on the price list, for one product in
 * one branch. Tax-inclusive rates are never sent; they are derived on save.
 */
class UpdateProductBranchPriceRequest extends FormRequest
{
    /**
     * The figures a price list cell may change.
     *
     * @var list<string>
     */
    public const array FIELDS = [
        'cost', 'rate_basis',
        'sr_discount', 'sr_rate',
        'pr_discount', 'pr_rate',
        'cr_discount', 'cr_rate',
    ];

    /**
     * Only price editors, and only in a branch they can reach.
     */
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $this->user()?->can('products.price.update') === true
            && $branch instanceof Branch
            && BranchAccess::options()->contains('id', $branch->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rate_basis' => ['sometimes', Rule::in(array_keys(ProductBranchPrice::RATE_BASES))],
        ];

        foreach (['sr', 'pr', 'cr'] as $tier) {
            $rules["{$tier}_discount"] = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'];
            $rules["{$tier}_rate"] = ['sometimes', 'nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * The grid saves over plain JSON requests, and this app only renders JSON
     * errors for API routes, so answer with a 422 the cell can show instead
     * of a redirect back to the page.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * An edit must carry at least one figure, or it would create an empty row.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(self::FIELDS)) {
                    $validator->errors()->add('cost', __('There is no figure to save.'));
                }
            },
        ];
    }
}

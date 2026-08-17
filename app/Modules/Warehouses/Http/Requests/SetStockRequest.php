<?php

namespace App\Modules\Warehouses\Http\Requests;

use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;

class SetStockRequest extends FormRequest
{
    use ScopesValidationToCompany;

    public function authorize(): bool
    {
        return $this->user()?->can('stock.set') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', $this->companyScopedExists('warehouses', 'id')],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'minimo' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}

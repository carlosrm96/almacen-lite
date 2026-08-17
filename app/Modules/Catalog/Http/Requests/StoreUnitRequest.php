<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    use ScopesValidationToCompany;

    public function authorize(): bool
    {
        return $this->user()?->can('units.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', $this->companyScopedUnique('units', 'nombre')],
            'factor' => ['required', 'numeric', 'gt:0'],
        ];
    }
}

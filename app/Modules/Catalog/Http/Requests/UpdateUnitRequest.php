<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    use ScopesValidationToCompany;

    public function authorize(): bool
    {
        return $this->user()?->can('units.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:100', $this->companyScopedUnique('units', 'nombre')->ignore($this->route('unit'))],
            'factor' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }
}

<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Http\Requests\UpdateCompanyRequest;
use App\Modules\Tenancy\Http\Resources\CompanyResource;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Support\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * @group Empresa
 *
 * @authenticated
 */
class CompanyController extends Controller
{
    use AuthorizesRequests;

    /**
     * Ver mi empresa.
     *
     * No lleva id en la ruta: la empresa es la de quien llama, resuelta del
     * token. Pedir la de otro no es que dé 403, es que no hay forma de pedirla.
     */
    public function show(Request $request, CurrentCompany $current): CompanyResource
    {
        $company = $this->company($current);

        $this->authorize('view', $company);

        return new CompanyResource($company);
    }

    /**
     * Renombrar mi empresa.
     *
     * @bodyParam nombre string required Nombre del negocio. Example: Bodega La Habana
     */
    public function update(UpdateCompanyRequest $request, CurrentCompany $current): CompanyResource
    {
        $company = $this->company($current);

        $this->authorize('update', $company);

        $company->update($request->validated());

        return new CompanyResource($company);
    }

    private function company(CurrentCompany $current): Company
    {
        $company = $current->get();

        abort_if($company === null, 404);

        return $company;
    }
}

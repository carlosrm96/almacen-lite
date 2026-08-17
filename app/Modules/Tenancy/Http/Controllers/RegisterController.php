<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Resources\UserResource;
use App\Modules\Tenancy\Actions\RegisterCompany;
use App\Modules\Tenancy\Http\Requests\RegisterRequest;
use App\Modules\Tenancy\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Acceso · Autenticación
 */
class RegisterController extends Controller
{
    /**
     * Registrar un negocio.
     *
     * Crea la empresa y su usuario administrador (dueño) y devuelve un token
     * de Sanctum ya utilizable. Endpoint público y repetible: cada registro
     * crea una empresa aislada, con sus propios almacenes, catálogo y ventas,
     * que nadie de otra empresa ve. Ya dentro, el admin crea sus almacenes con
     * `POST /v1/warehouses` y sus vendedores con `POST /v1/users`.
     *
     * @unauthenticated
     *
     * @bodyParam empresa string required Nombre del negocio. Example: Bodega La Habana
     * @bodyParam name string required Nombre del administrador. Example: Ana
     * @bodyParam email string required Email (único). Example: ana@almacen.test
     * @bodyParam password string required Contraseña (mín. 8). Example: secreto123
     * @bodyParam password_confirmation string required Confirmación de la contraseña. Example: secreto123
     *
     * @response 201 {"token":"1|abc...","user":{"id":1,"name":"Ana","email":"ana@almacen.test","rol":"admin","warehouse_id":null},"company":{"id":1,"nombre":"Bodega La Habana","activo":true}}
     */
    public function store(RegisterRequest $request, RegisterCompany $action): JsonResponse
    {
        $user = $action->handle(
            companyName: $request->validated('empresa'),
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return new JsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
            'company' => (new CompanyResource($user->company))->resolve($request),
        ], Response::HTTP_CREATED);
    }
}

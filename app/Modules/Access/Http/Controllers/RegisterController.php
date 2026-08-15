<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Actions\RegisterAdmin;
use App\Modules\Access\Http\Requests\RegisterRequest;
use App\Modules\Access\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Acceso · Autenticación
 */
class RegisterController extends Controller
{
    /**
     * Registrar el administrador de la instalación.
     *
     * Puesta en marcha: crea el primer usuario, con rol `admin`, y devuelve
     * un token de Sanctum ya utilizable. Solo funciona mientras no exista
     * ningún usuario; después responde `403` y las altas las hace el admin
     * desde `POST /v1/users`.
     *
     * @unauthenticated
     *
     * @bodyParam name string required Nombre del administrador. Example: Ana
     * @bodyParam email string required Email (único). Example: ana@almacen.test
     * @bodyParam password string required Contraseña (mín. 8). Example: secreto123
     * @bodyParam password_confirmation string required Confirmación de la contraseña. Example: secreto123
     *
     * @response 201 {"token":"1|abc...","user":{"id":1,"name":"Ana","email":"ana@almacen.test","rol":"admin","warehouse_id":null}}
     * @response 403 {"message":"El registro está cerrado: ya hay usuarios en el sistema. Pide a un administrador que te dé de alta."}
     */
    public function store(RegisterRequest $request, RegisterAdmin $action): JsonResponse
    {
        $user = $action->handle(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return new JsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
        ], Response::HTTP_CREATED);
    }
}

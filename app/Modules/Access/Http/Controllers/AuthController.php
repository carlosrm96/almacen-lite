<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Http\Requests\LoginRequest;
use App\Modules\Access\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Acceso · Autenticación
 */
class AuthController extends Controller
{
    /**
     * Iniciar sesión.
     *
     * Devuelve un token de Sanctum para las siguientes peticiones.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        return new JsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    /**
     * Cerrar sesión (revoca el token en uso).
     *
     * @authenticated
     */
    public function logout(): Response
    {
        auth()->user()->currentAccessToken()->delete();

        // Olvida el usuario cacheado en el guard: sin esto, dentro del mismo
        // ciclo de proceso (p. ej. en tests que encadenan peticiones) el guard
        // de Sanctum seguiría devolviendo el usuario ya autenticado aunque su
        // token acabe de ser revocado, porque RequestGuard cachea el usuario
        // resuelto en la primera llamada a user() y no lo recalcula.
        auth()->guard()->forgetUser();

        return response()->noContent();
    }

    /**
     * Usuario autenticado.
     *
     * @authenticated
     */
    public function me(): UserResource
    {
        return new UserResource(auth()->user());
    }
}

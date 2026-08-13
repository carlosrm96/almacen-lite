<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Http\Requests\StoreUserRequest;
use App\Modules\Access\Http\Requests\UpdateUserRequest;
use App\Modules\Access\Http\Resources\UserResource;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Acceso · Usuarios
 *
 * @authenticated
 */
class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Listar usuarios.
     *
     * @queryParam filter[name] string Filtra por nombre (coincidencia parcial). Example: ana
     * @queryParam filter[email] string Filtra por email (coincidencia parcial). Example: ana@
     * @queryParam filter[warehouse_id] integer Filtra por almacén asignado. Example: 1
     * @queryParam sort string Orden: name, email, created_at. Prefijo - para descendente. Example: name
     * @queryParam page integer Número de página. Example: 1
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(AllowedFilter::partial('name'), AllowedFilter::partial('email'), AllowedFilter::exact('warehouse_id'))
            ->allowedSorts('name', 'email', 'created_at')
            ->paginate()
            ->appends(request()->query());

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = User::create($request->safe()->except('rol'));
        $user->assignRole($request->validated('rol'));

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $user->update($request->safe()->except('rol'));

        if ($request->has('rol')) {
            $user->syncRoles([$request->validated('rol')]);
        }

        return new UserResource($user->refresh());
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        // `sales.user_id` y `transfers.user_id` son restrictOnDelete para
        // conservar la atribución histórica: no se hacen nullable, se bloquea
        // el borrado en su lugar.
        $tieneVentas = Sale::where('user_id', $user->id)->exists();
        $tieneTransferencias = Transfer::where('user_id', $user->id)->exists();

        if ($tieneVentas || $tieneTransferencias) {
            throw ValidationException::withMessages([
                'user' => ['El usuario tiene ventas o transferencias asociadas. No puede borrarse.'],
            ]);
        }

        $user->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clans\AssignClanRole;
use App\Actions\Clans\ManageClanRole;
use App\Exceptions\ClanException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClanMemberResource;
use App\Http\Resources\ClanRoleResource;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\ClanRole;
use App\Support\ClanAuthorizer;
use App\Support\ClanPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClanRoleController extends Controller
{
    public function index(Clan $clan): AnonymousResourceCollection
    {
        Gate::authorize('view', $clan);

        return ClanRoleResource::collection(
            $clan->roles()->with('permissions')->orderBy('sort_order')->get(),
        );
    }

    public function store(Clan $clan, Request $request, ManageClanRole $action): ClanRoleResource
    {
        Gate::authorize('view', $clan);
        $this->authorizePermission($clan, $request, 'manage_roles');

        return new ClanRoleResource($action->create($clan, $request->user(), $this->validated($request, $clan)));
    }

    public function update(Clan $clan, ClanRole $role, Request $request, ManageClanRole $action): ClanRoleResource
    {
        Gate::authorize('view', $clan);
        $this->authorizePermission($clan, $request, 'manage_roles');

        return new ClanRoleResource($action->update($clan, $role, $request->user(), $this->validated($request, $clan, $role)));
    }

    public function destroy(Clan $clan, ClanRole $role, Request $request, ManageClanRole $action): JsonResponse
    {
        Gate::authorize('view', $clan);
        $this->authorizePermission($clan, $request, 'manage_roles');
        $action->delete($clan, $role, $request->user());

        return response()->json(['message' => 'Clan role deleted.']);
    }

    public function assign(Clan $clan, ClanMember $member, Request $request, AssignClanRole $action): ClanMemberResource
    {
        Gate::authorize('view', $clan);
        $this->authorizePermission($clan, $request, 'manage_members');
        $validated = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:clan_roles,id'],
        ]);

        return new ClanMemberResource(
            $action->handle($clan, $member, ClanRole::findOrFail($validated['role_id']), $request->user()),
        );
    }

    private function validated(Request $request, Clan $clan, ?ClanRole $role = null): array
    {
        $required = $role ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:80'],
            'slug' => [
                $required, 'string', 'min:2', 'max:90',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('clan_roles', 'slug')->where('clan_id', $clan->id)->ignore($role?->id),
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'permissions' => [$role ? 'sometimes' : 'required', 'array'],
            'permissions.*' => ['string', Rule::in(ClanPermissions::ALL), 'distinct'],
        ]);
    }

    private function authorizePermission(Clan $clan, Request $request, string $permission): void
    {
        if (! ClanAuthorizer::allows($clan, $request->user(), $permission)) {
            throw new ClanException('You cannot manage clan roles.', 'CLAN_PERMISSION_DENIED', 403);
        }
    }
}

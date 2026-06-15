<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        $catalog = Permissions::catalog();

        return view('roles.create', compact('catalog'));
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenancy->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::allSlugs())],
        ]);

        $role = Role::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $tenantId),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->syncPermissionsBySlug($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role)
    {
        $this->authorizeTenant($role);
        $catalog = Permissions::catalog();
        $assigned = $role->permissions->pluck('slug')->all();

        return view('roles.edit', compact('role', 'catalog', 'assigned'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeTenant($role);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::allSlugs())],
        ]);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $role->syncPermissionsBySlug($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        $this->authorizeTenant($role);

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('status', 'Role deleted.');
    }

    protected function uniqueSlug(string $name, int $tenantId): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 1;
        while (Role::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    protected function authorizeTenant(Role $role): void
    {
        abort_unless($role->tenant_id === $this->tenancy->id(), 404);
    }
}

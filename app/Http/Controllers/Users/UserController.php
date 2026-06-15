<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $users = User::forCurrentTenant()
            ->with('roles')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenancy->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->where('tenant_id', $tenantId)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
        ]);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        return redirect()->route('users.index')->with('status', 'Staff member added.');
    }

    public function edit(User $user)
    {
        $this->authorizeTenant($user);
        $roles = Role::orderBy('name')->get();
        $user->load('roles');

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTenant($user);
        $tenantId = $this->tenancy->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->where('tenant_id', $tenantId)->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // The owner always keeps full access; don't let roles strip it.
        if (! $user->is_owner) {
            $user->roles()->sync($data['roles'] ?? []);
        }

        return redirect()->route('users.index')->with('status', 'Staff member updated.');
    }

    public function destroy(User $user)
    {
        $this->authorizeTenant($user);

        if ($user->is_owner) {
            return back()->with('error', 'The store owner cannot be removed.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Staff member removed.');
    }

    /** Guard against editing users from another tenant. */
    protected function authorizeTenant(User $user): void
    {
        abort_unless($user->tenant_id === $this->tenancy->id(), 404);
    }
}

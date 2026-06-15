<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisteredTenantController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $result = $provisioner->provision(
            tenantData: [
                'name' => $data['business_name'],
                'currency' => strtoupper($data['currency']),
            ],
            ownerData: [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ],
        );

        Auth::login($result['owner']);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Welcome to xismarket! Your store is ready.');
    }
}

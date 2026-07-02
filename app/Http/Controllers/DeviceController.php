<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Manage the devices allowed to sign in when device restriction is enabled.
 * Admins approve pending devices, rename them, or revoke/remove them.
 */
class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::orderByDesc('approved')->orderByDesc('last_seen_at')->orderByDesc('id')->get();

        return view('settings.devices', compact('devices'));
    }

    public function approve(Request $request, Device $device)
    {
        $this->authorizeTenant($device);
        $device->update([
            'approved' => true,
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Device approved.');
    }

    public function revoke(Device $device)
    {
        $this->authorizeTenant($device);
        $device->update(['approved' => false, 'approved_by' => null]);

        return back()->with('status', 'Device revoked — its sessions will be signed out.');
    }

    public function rename(Request $request, Device $device)
    {
        $this->authorizeTenant($device);
        $data = $request->validate(['label' => ['required', 'string', 'max:80']]);
        $device->update(['label' => $data['label']]);

        return back()->with('status', 'Device renamed.');
    }

    public function destroy(Device $device)
    {
        $this->authorizeTenant($device);
        $device->delete();

        return back()->with('status', 'Device removed.');
    }

    protected function authorizeTenant(Device $device): void
    {
        abort_unless($device->tenant_id === app(\App\Support\Tenancy::class)->id(), 404);
    }
}

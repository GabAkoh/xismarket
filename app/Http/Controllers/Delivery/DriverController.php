<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Driver;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        $drivers = Driver::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('vehicle', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('drivers.index', compact('drivers'));
    }

    public function create()
    {
        return view('drivers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Driver::create($data + ['tenant_id' => $this->tenancy->id()]);

        return redirect()->route('drivers.index')->with('status', 'Driver added.');
    }

    public function edit(Driver $driver)
    {
        $this->authorizeTenant($driver);

        return view('drivers.edit', compact('driver'));
    }

    public function update(Request $request, Driver $driver)
    {
        $this->authorizeTenant($driver);

        $driver->update($this->validateData($request));

        return redirect()->route('drivers.index')->with('status', 'Driver updated.');
    }

    public function destroy(Driver $driver)
    {
        $this->authorizeTenant($driver);
        $driver->delete();

        return redirect()->route('drivers.index')->with('status', 'Driver removed.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vehicle' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    protected function authorizeTenant(Driver $driver): void
    {
        abort_unless($driver->tenant_id === $this->tenancy->id(), 404);
    }
}

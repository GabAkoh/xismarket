<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Driver;
use App\Services\Delivery\DeliveryService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    /** Delivery board: deliveries listed (and filterable) by status. */
    public function index(Request $request)
    {
        $deliveries = Delivery::query()
            ->with('driver', 'order')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('tracking_number', 'like', $term)
                    ->orWhere('recipient_name', 'like', $term)
                    ->orWhere('address', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $statuses = ['pending', 'assigned', 'out_for_delivery', 'delivered', 'failed'];

        return view('deliveries.index', compact('deliveries', 'statuses'));
    }

    public function show(Delivery $delivery)
    {
        $this->authorizeTenant($delivery);
        $delivery->load('driver', 'order');

        $drivers = Driver::where('is_active', true)->orderBy('name')->get();

        return view('deliveries.show', compact('delivery', 'drivers'));
    }

    /** Create form; accepts ?order={id} to prefill from an order. */
    public function create(Request $request)
    {
        $order = null;
        if ($request->filled('order') && class_exists(\App\Models\Orders\Order::class)) {
            $order = \App\Models\Orders\Order::find($request->integer('order'));
            if ($order && $order->tenant_id !== $this->tenancy->id()) {
                $order = null;
            }
        }

        $drivers = Driver::where('is_active', true)->orderBy('name')->get();

        return view('deliveries.create', compact('order', 'drivers'));
    }

    public function store(Request $request, DeliveryService $service)
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'scheduled_for' => ['nullable', 'date'],
            'driver_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = null;
        if (! empty($data['order_id']) && class_exists(\App\Models\Orders\Order::class)) {
            $order = \App\Models\Orders\Order::find($data['order_id']);
            if ($order && $order->tenant_id !== $this->tenancy->id()) {
                $order = null;
            }
        }

        if ($order) {
            $delivery = $service->createForOrder($order, $data);
        } else {
            $delivery = Delivery::create([
                'tenant_id' => $this->tenancy->id(),
                'driver_id' => $data['driver_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'zone' => $data['zone'] ?? null,
                'fee' => $data['fee'] ?? 0,
                'status' => 'pending',
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $delivery->update(['tracking_number' => 'DLV-'.str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT)]);
        }

        return redirect()->route('deliveries.show', $delivery)->with('status', 'Delivery created.');
    }

    public function assign(Request $request, Delivery $delivery, DeliveryService $service)
    {
        $this->authorizeTenant($delivery);

        $data = $request->validate([
            'driver_id' => ['required', 'integer'],
        ]);

        $driver = Driver::findOrFail($data['driver_id']);
        $this->authorizeTenant($driver);

        try {
            $service->assign($delivery, $driver);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Driver assigned.');
    }

    public function dispatchDelivery(Delivery $delivery, DeliveryService $service)
    {
        $this->authorizeTenant($delivery);

        try {
            $service->dispatch($delivery);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Delivery dispatched.');
    }

    public function deliver(Delivery $delivery, DeliveryService $service)
    {
        $this->authorizeTenant($delivery);

        try {
            $service->markDelivered($delivery);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Delivery marked as delivered.');
    }

    public function fail(Request $request, Delivery $delivery, DeliveryService $service)
    {
        $this->authorizeTenant($delivery);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->markFailed($delivery, $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Delivery marked as failed.');
    }

    protected function authorizeTenant(Delivery|Driver $model): void
    {
        abort_unless($model->tenant_id === $this->tenancy->id(), 404);
    }
}

<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\Subscriber;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Admin view of the storefront "Join our community" mailing list. Subscribers
 * are collected by the public storefront; here staff can search, export and
 * remove them.
 */
class SubscriberController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        $subscribers = $this->query($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $total = Subscriber::count();

        return view('subscribers.index', compact('subscribers', 'total'));
    }

    /** Download the (filtered) list as CSV. */
    public function export(Request $request)
    {
        $rows = $this->query($request)->latest()->get();
        $filename = 'subscribers-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Name', 'Joined'], ',', '"', '');
            foreach ($rows as $s) {
                fputcsv($out, [$s->email, $s->name, optional($s->created_at)->toDateTimeString()], ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Subscriber $subscriber)
    {
        abort_unless($subscriber->tenant_id === $this->tenancy->id(), 404);
        $subscriber->delete();

        return back()->with('status', 'Subscriber removed.');
    }

    /**
     * Send a "new arrivals" email featuring the latest products — either a test
     * to the logged-in user, or a broadcast to all active subscribers.
     */
    public function broadcast(Request $request, \App\Services\Storefront\NewArrivalsBroadcaster $broadcaster)
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:150'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'count' => ['required', 'integer', 'min:1', 'max:24'],
            'test' => ['nullable'],
        ]);

        $store = $this->tenancy->current();
        $products = \App\Models\Inventory\Product::where('is_active', true)
            ->latest()->take((int) $data['count'])->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'No active products to feature.');
        }

        // Test send → only the logged-in user, synchronously, for a quick preview.
        if ($request->boolean('test')) {
            $me = $request->user();
            $preview = new Subscriber(['email' => $me->email, 'name' => $me->name, 'token' => 'preview']);
            $preview->tenant_id = $store->id;
            try {
                \Illuminate\Support\Facades\Mail::to($me->email)->send(
                    new \App\Mail\NewArrivalsMail($preview, $store, $products, $data['intro'] ?? null, $data['subject'] ?? null)
                );
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'Test send failed: '.$e->getMessage());
            }

            return back()->with('status', 'Test email sent to '.$me->email.'.');
        }

        $n = $broadcaster->send($store, $products, $data['intro'] ?? null, $data['subject'] ?? null);

        return back()->with('status', $n > 0
            ? "Queued a new-arrivals email to {$n} subscriber(s)."
            : 'No active subscribers to email.');
    }

    /** Tenant-scoped, optionally search-filtered subscriber query. */
    protected function query(Request $request)
    {
        return Subscriber::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('email', 'like', $term)->orWhere('name', 'like', $term));
            });
    }
}

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

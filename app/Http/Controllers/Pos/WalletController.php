<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Customer;
use App\Models\Pos\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /** Store-credit overview across all customers. */
    public function index(Request $request)
    {
        $totalCredit = (float) Customer::sum('balance');
        $holders = Customer::where('balance', '>', 0)->count();

        $customers = Customer::query()
            ->where('balance', '>', 0)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderByDesc('balance')
            ->paginate(20)
            ->withQueryString();

        $recent = WalletTransaction::with('customer')
            ->latest()
            ->limit(12)
            ->get();

        return view('wallets.index', compact('totalCredit', 'holders', 'customers', 'recent'));
    }
}

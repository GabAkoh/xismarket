<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    /** Account types in their natural ordering for the chart of accounts. */
    protected array $types = ['asset', 'liability', 'equity', 'income', 'expense'];

    public function index()
    {
        $accounts = Account::orderBy('code')->get();

        $grouped = $accounts->groupBy('type')->sortBy(
            fn ($group, $type) => array_search($type, $this->types)
        );

        return view('accounting.accounts.index', compact('grouped'));
    }

    public function create()
    {
        $types = $this->types;

        return view('accounting.accounts.create', compact('types'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Account::create($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('accounts.index')->with('status', 'Account created.');
    }

    public function edit(Account $account)
    {
        $this->authorizeTenant($account);
        $types = $this->types;

        return view('accounting.accounts.edit', compact('account', 'types'));
    }

    public function update(Request $request, Account $account)
    {
        $this->authorizeTenant($account);

        $data = $this->validateData($request, $account);

        $account->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('accounts.index')->with('status', 'Account updated.');
    }

    public function destroy(Account $account)
    {
        $this->authorizeTenant($account);

        if ($account->lines()->exists()) {
            return back()->with('error', 'This account has journal activity and cannot be deleted.');
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Account deleted.');
    }

    protected function validateData(Request $request, ?Account $account = null): array
    {
        $tenantId = $this->tenancy->id();

        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounts')->where('tenant_id', $tenantId)->ignore($account?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($this->types)],
            'subtype' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function authorizeTenant(Account $account): void
    {
        abort_unless($account->tenant_id === $this->tenancy->id(), 404);
    }
}

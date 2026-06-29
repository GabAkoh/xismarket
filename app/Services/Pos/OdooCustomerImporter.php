<?php

namespace App\Services\Pos;

use App\Models\Pos\Customer;
use App\Support\Tenancy;

/**
 * Import customers from an Odoo Contacts CSV export. Matches existing customers
 * by name (the chosen key): create mode adds only names not already here,
 * update mode refreshes contact details of existing ones. Loyalty number,
 * points and store-credit balance are handled separately (loyalty-card export).
 */
class OdooCustomerImporter
{
    /** @var array<string, array<int, string>> field => header aliases (normalised) */
    protected const FIELDS = [
        'name' => ['displayname', 'name', 'contactname', 'customer', 'partnername', 'fullname'],
        'email' => ['email', 'emailaddress', 'workemail'],
        'phone' => ['phone', 'phonenumber', 'telephone', 'tel'],
        'mobile' => ['mobile', 'mobilephone', 'cell', 'cellphone'],
        'street' => ['street', 'address', 'street1', 'addressline1', 'street2'],
        'city' => ['city', 'town'],
        'state' => ['state', 'province', 'region', 'stateid'],
        'country' => ['country', 'countryid'],
        'identity' => ['identitynumber', 'idnumber', 'vat', 'taxid', 'reference', 'internalreference'],
    ];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @param  string  $mode  'create' (add names not already here) or 'update'
     *                        (refresh email/phone/address of existing customers).
     * @return array{created:int, updated:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, string $mode = 'create'): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (! is_file($path) || ! is_readable($path)) {
            $result['errors'][] = 'Import file not found or unreadable.';

            return $result;
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            $result['errors'][] = 'Could not open the file.';

            return $result;
        }

        $header = fgetcsv($fh, 0, ',', '"', '');
        if ($header === false) {
            fclose($fh);
            $result['errors'][] = 'The file is empty.';

            return $result;
        }

        $norm = [];
        foreach ($header as $i => $h) {
            $key = $this->normalize((string) $h);
            if ($key !== '' && ! isset($norm[$key])) {
                $norm[$key] = $i;
            }
        }
        $idx = [];
        foreach (self::FIELDS as $field => $aliases) {
            $idx[$field] = null;
            foreach ($aliases as $alias) {
                if (isset($norm[$alias])) {
                    $idx[$field] = $norm[$alias];
                    break;
                }
            }
        }

        if ($idx['name'] === null) {
            fclose($fh);
            $result['errors'][] = 'No name column found (expected "Display Name" or "Name").';

            return $result;
        }

        $col = fn (array $row, string $field) => $idx[$field] !== null && isset($row[$idx[$field]])
            ? trim((string) $row[$idx[$field]])
            : '';

        // Existing customers by name key — for dedupe / update matching.
        $existing = Customer::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [$this->key($name) => $id]);

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $name = $col($row, 'name');
            if ($name === '') {
                $result['skipped']++;

                continue;
            }

            $key = $this->key($name);
            $exists = $existing->has($key);

            if ($exists && $mode !== 'update') {
                $result['skipped']++;

                continue;
            }

            $attrs = [
                'name' => $name,
                'email' => $col($row, 'email') ?: null,
                'phone' => ($col($row, 'phone') ?: $col($row, 'mobile')) ?: null,
                'address' => $this->address($col, $row) ?: null,
            ];

            try {
                if ($exists) {
                    // Update only fills blanks / refreshes contact details, never the name.
                    Customer::whereKey($existing->get($key))->update(array_filter(
                        ['email' => $attrs['email'], 'phone' => $attrs['phone'], 'address' => $attrs['address']],
                        fn ($v) => $v !== null,
                    ));
                    $result['updated']++;
                } else {
                    $customer = Customer::create($attrs);
                    $existing->put($key, $customer->id);
                    $result['created']++;
                }
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = $name.': '.$e->getMessage();
            }
        }
        fclose($fh);

        return $result;
    }

    /** Join the non-empty address parts into one line. */
    protected function address(callable $col, array $row): string
    {
        $parts = array_filter([
            $col($row, 'street'),
            $col($row, 'city'),
            $col($row, 'state'),
            $col($row, 'country'),
        ], fn ($v) => $v !== '');

        return implode(', ', $parts);
    }

    /** Normalised name key for dedupe (trim + lowercase + collapse whitespace). */
    protected function key(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    /** Lowercase + strip everything but a-z0-9 (and the UTF-8 BOM) for header matching. */
    protected function normalize(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return strtolower(preg_replace('/[^a-z0-9]/i', '', $header));
    }
}

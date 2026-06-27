<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;

/**
 * Generates in-store barcodes for products. Uses a GS1 "restricted
 * circulation" prefix (20) so the codes never clash with real retail GTINs,
 * and a valid EAN-13 check digit so standard scanners read them. Uniqueness is
 * checked against the (tenant-scoped) products table.
 */
class BarcodeService
{
    /** A fresh, unique EAN-13 barcode value (13 digits). */
    public function generate(): string
    {
        do {
            // "20" prefix + 10 random digits = 12-digit body, then the check digit.
            $body = '20'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $code = $body.$this->checkDigit($body);
        } while (Product::where('barcode', $code)->exists());

        return $code;
    }

    /** EAN-13 check digit for a 12-digit body. */
    public function checkDigit(string $body12): int
    {
        $sum = 0;
        foreach (str_split($body12) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}

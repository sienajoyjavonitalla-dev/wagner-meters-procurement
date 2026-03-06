<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StoreDataImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'vendor_priority' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
            'item_spread' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
            'mpn_map' => ['nullable', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateInventoryColumns($validator);
            $this->validateVendorPriorityColumns($validator);
            $this->validateItemSpreadColumns($validator);
            if ($this->hasFile('mpn_map') && $this->file('mpn_map')->isValid()) {
                $this->validateMpnMapColumns($validator);
            }
        });
    }

    private function validateInventoryColumns($validator): void
    {
        $required = ['Transaction Date', 'Vendor Name', 'Item ID', 'Description', 'Ext. Cost', 'Unit Cost', 'Quantity'];
        $path = $this->file('inventory')->getRealPath();
        $headers = $this->readSheetHeaders($path);
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                $validator->errors()->add('inventory', "Missing required column: {$col}");
                return;
            }
        }
    }

    private function validateVendorPriorityColumns($validator): void
    {
        $path = $this->file('vendor_priority')->getRealPath();
        $headers = $this->readSheetHeaders($path);
        if (! in_array('Vendor Name', $headers, true) || ! in_array('priority_rank', $headers, true)) {
            $validator->errors()->add('vendor_priority', 'Missing required columns: Vendor Name, priority_rank');
        }
    }

    private function validateItemSpreadColumns($validator): void
    {
        $path = $this->file('item_spread')->getRealPath();
        $headers = $this->readSheetHeaders($path);
        if (! in_array('Item ID', $headers, true)) {
            $validator->errors()->add('item_spread', 'Missing required column: Item ID');
        }
    }

    private function validateMpnMapColumns($validator): void
    {
        $path = $this->file('mpn_map')->getRealPath();
        $headers = $this->readSheetHeaders($path);
        if (! in_array('Item ID', $headers, true) || ! in_array('mpn', $headers, true)) {
            $validator->errors()->add('mpn_map', 'MPN map must have columns: Item ID, mpn');
        }
    }

    private function readSheetHeaders(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $rows = $sheet->toArray();
            $first = $rows[0] ?? [];

            return array_map('trim', $first);
        } catch (\Throwable) {
            return [];
        }
    }
}

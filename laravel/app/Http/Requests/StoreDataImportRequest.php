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
            'inventory' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateInventoryColumns($validator);
        });
    }

    private function validateInventoryColumns($validator): void
    {
        $required = [
            'Transaction Date',
            'Item ID',
            'Description',
            'Unit Cost',
            'Ext. Cost',
            'Quantity',
            'Vendor Name',
            'Product Line',
        ];
        $path = $this->file('inventory')->getRealPath();
        $headers = $this->readSheetHeaders($path);
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                $validator->errors()->add('inventory', "Missing required column: {$col}");
                return;
            }
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

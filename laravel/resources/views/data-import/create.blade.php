<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Data Import') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600 mb-6">Upload inventory, vendor priority, item spread, and optional MPN map. This replaces the previous snapshot.</p>

            @if (session('success'))
                <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <ul class="mb-4 list-disc list-inside text-red-600 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('data-import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium mb-1">Inventory (Excel) *</label>
                        <input type="file" name="inventory" accept=".xlsx,.xls" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700">
                        <p class="text-xs text-gray-500 mt-1">Required columns: Transaction Date, Vendor Name, Item ID, Description, Ext. Cost, Unit Cost, Quantity</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Vendor priority (CSV/Excel) *</label>
                        <input type="file" name="vendor_priority" accept=".csv,.xlsx,.xls" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700">
                        <p class="text-xs text-gray-500 mt-1">Required columns: Vendor Name, priority_rank</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Item spread (CSV/Excel) *</label>
                        <input type="file" name="item_spread" accept=".csv,.xlsx,.xls" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700">
                        <p class="text-xs text-gray-500 mt-1">Required column: Item ID</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">MPN map (CSV/Excel, optional)</label>
                        <input type="file" name="mpn_map" accept=".csv,.xlsx,.xls" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700">
                        <p class="text-xs text-gray-500 mt-1">Columns: Item ID, mpn</p>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium">Upload and import</button>
                </form>
            </div>

            @if ($recentImports->isNotEmpty())
                <section class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h2 class="text-lg font-medium mb-2">Recent imports</h2>
                    <ul class="text-sm space-y-1 text-gray-900">
                        @foreach ($recentImports as $imp)
                            <li>
                                #{{ $imp->id }} – {{ $imp->status }} – {{ $imp->created_at->toDateTimeString() }}
                                @if ($imp->row_counts)
                                    ({{ json_encode($imp->row_counts) }})
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>

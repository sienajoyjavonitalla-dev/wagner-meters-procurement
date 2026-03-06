@extends('layouts.dark')

@section('title', 'Data Import')

@section('content')
<div class="app-main-inner" style="padding: 1.5rem 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #e6edf3;">Data Import</h1>
    <p style="color: #8b949e; margin-bottom: 1.5rem; font-size: 0.9375rem;">Upload inventory, vendor priority, item spread, and optional MPN map. This replaces the previous snapshot.</p>

    @if (session('success'))
        <div style="margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 6px; background: rgba(46, 160, 67, 0.15); color: #3fb950; font-size: 0.875rem;">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <ul style="margin-bottom: 1rem; padding-left: 1.25rem; color: #f85149; font-size: 0.875rem;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div style="background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 1.5rem;">
        <form action="{{ route('data-import.store') }}" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.25rem;">
            @csrf
            <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #e6edf3; margin-bottom: 0.375rem;">Inventory (Excel) *</label>
                <input type="file" name="inventory" accept=".xlsx,.xls" required
                    style="width: 100%; padding: 0.5rem; font-size: 0.875rem; color: #e6edf3; background: #0d1117; border: 1px solid #30363d; border-radius: 6px;">
                <p style="font-size: 0.75rem; color: #8b949e; margin-top: 0.25rem;">Required columns: Transaction Date, Vendor Name, Item ID, Description, Ext. Cost, Unit Cost, Quantity</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #e6edf3; margin-bottom: 0.375rem;">Vendor priority (CSV/Excel) *</label>
                <input type="file" name="vendor_priority" accept=".csv,.xlsx,.xls" required
                    style="width: 100%; padding: 0.5rem; font-size: 0.875rem; color: #e6edf3; background: #0d1117; border: 1px solid #30363d; border-radius: 6px;">
                <p style="font-size: 0.75rem; color: #8b949e; margin-top: 0.25rem;">Required columns: Vendor Name, priority_rank</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #e6edf3; margin-bottom: 0.375rem;">Item spread (CSV/Excel) *</label>
                <input type="file" name="item_spread" accept=".csv,.xlsx,.xls" required
                    style="width: 100%; padding: 0.5rem; font-size: 0.875rem; color: #e6edf3; background: #0d1117; border: 1px solid #30363d; border-radius: 6px;">
                <p style="font-size: 0.75rem; color: #8b949e; margin-top: 0.25rem;">Required column: Item ID</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #e6edf3; margin-bottom: 0.375rem;">MPN map (CSV/Excel, optional)</label>
                <input type="file" name="mpn_map" accept=".csv,.xlsx,.xls"
                    style="width: 100%; padding: 0.5rem; font-size: 0.875rem; color: #e6edf3; background: #0d1117; border: 1px solid #30363d; border-radius: 6px;">
                <p style="font-size: 0.75rem; color: #8b949e; margin-top: 0.25rem;">Columns: Item ID, mpn</p>
            </div>
            <button type="submit" style="align-self: flex-start; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #fff; background: #238636; border: 1px solid #2ea043; border-radius: 6px; cursor: pointer;">Upload and import</button>
        </form>
    </div>

    @if ($recentImports->isNotEmpty())
        <section style="margin-top: 2rem; background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 1.5rem;">
            <h2 style="font-size: 1rem; font-weight: 600; color: #e6edf3; margin-bottom: 0.75rem;">Recent imports</h2>
            <ul style="font-size: 0.875rem; color: #8b949e; list-style: none; padding: 0; margin: 0;">
                @foreach ($recentImports as $imp)
                    <li style="padding: 0.25rem 0; border-bottom: 1px solid #21262d;">#{{ $imp->id }} – {{ $imp->status }} – {{ $imp->created_at->toDateTimeString() }}
                        @if ($imp->row_counts) ({{ json_encode($imp->row_counts) }}) @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection

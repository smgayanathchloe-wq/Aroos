@extends('layouts.app')

@section('title', 'Import Pledges')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Michango</p>
    <h1 class="font-serif text-3xl mt-1">Import pledges &amp; contributions</h1>
    <p class="text-sm text-muted mt-2 max-w-2xl">
        Upload a spreadsheet to add pledges in bulk. Excel (.xlsx) and CSV files are both
        accepted. Rows that match someone already recorded will update that person instead of
        creating a duplicate, so you can safely re-upload a corrected file.
    </p>
</div>

@if($errors->any())
    <div class="card p-4 mb-6 border-l-4 border-red-400">
        <p class="eyebrow mb-2 text-red-600">Please fix the following</p>
        <ul class="text-sm text-muted list-disc ml-5 space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($rows === null)
    {{-- Step 1: upload --}}
    <div class="card p-6 max-w-2xl">
        <p class="eyebrow mb-4">Step 1 &mdash; choose your file</p>

        <form method="POST" action="{{ route('pledges.import.preview', $wedding) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="file" name="file" accept=".csv,.txt,.tsv,.xlsx" required class="lux-input w-full">

            <div class="text-sm text-muted">
                <p class="mb-2">The file should have a header row containing these columns:</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-1 pr-4">Column</th>
                                <th class="py-1">What it means</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b"><td class="py-1 pr-4 font-medium">name</td><td class="py-1">Pledger's full name <span class="text-red-500">(required)</span></td></tr>
                            <tr class="border-b"><td class="py-1 pr-4 font-medium">pledge</td><td class="py-1">What was pledged. If this column holds the pledged figure instead of a description, it is read as the amount.</td></tr>
                            <tr class="border-b"><td class="py-1 pr-4 font-medium">email</td><td class="py-1">Email address</td></tr>
                            <tr class="border-b"><td class="py-1 pr-4 font-medium">phone</td><td class="py-1">Phone number</td></tr>
                            <tr class="border-b"><td class="py-1 pr-4 font-medium">amount</td><td class="py-1">Amount pledged</td></tr>
                            <tr class="border-b"><td class="py-1 pr-4 font-medium">date</td><td class="py-1">Date of the pledge</td></tr>
                            <tr><td class="py-1 pr-4 font-medium">contribution</td><td class="py-1">Amount actually paid so far</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3">Column order does not matter, and common alternative names (such as <em>pledger name</em>, <em>mobile</em>, or <em>paid</em>) are recognised automatically. Excel (.xlsx) and CSV files both work.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button class="btn-gold">Preview import</button>
                <a href="{{ route('pledges.import.template', $wedding) }}" class="text-sm underline text-muted">Download template</a>
                <a href="{{ route('pledges.index', $wedding) }}" class="text-sm text-muted">Cancel</a>
            </div>
        </form>
    </div>
@else
    {{-- Step 2: preview before writing --}}
    <div class="card p-6 mb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="eyebrow mb-1">Step 2 &mdash; check before importing</p>
                <p class="text-sm text-muted">
                    {{ count($rows) }} row(s) ready. Nothing has been saved yet.
                </p>
            </div>
            <form method="POST" action="{{ route('pledges.import.store', $wedding) }}">
                @csrf
                <button class="btn-gold">Confirm and import {{ count($rows) }} row(s)</button>
            </form>
        </div>

        @if(!empty($notes))
            <div class="mt-4 text-sm text-muted">
                <p class="eyebrow mb-1">Notes</p>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $unmapped = collect($headers ?? [])->filter(fn ($i) => $i === null)->keys();
        @endphp
        @if($unmapped->isNotEmpty())
            <div class="mt-4 text-sm text-muted">
                <p>These columns were not found in your file and will be left empty:
                    <strong>{{ $unmapped->implode(', ') }}</strong>
                </p>
            </div>
        @endif
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b bg-black/5">
                    <tr>
                        <th class="py-2 px-3">#</th>
                        <th class="py-2 px-3">Name</th>
                        <th class="py-2 px-3">Pledge</th>
                        <th class="py-2 px-3">Email</th>
                        <th class="py-2 px-3">Phone</th>
                        <th class="py-2 px-3 text-right">Amount</th>
                        <th class="py-2 px-3">Date</th>
                        <th class="py-2 px-3 text-right">Contribution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2 px-3 text-muted">{{ $row['line'] }}</td>
                            <td class="py-2 px-3">{{ $row['name'] }}</td>
                            <td class="py-2 px-3 text-muted">{{ $row['pledge'] ?: '—' }}</td>
                            <td class="py-2 px-3 text-muted">{{ $row['email'] ?: '—' }}</td>
                            <td class="py-2 px-3 text-muted">{{ $row['phone'] ?: '—' }}</td>
                            <td class="py-2 px-3 text-right">{{ number_format($row['amount']) }}</td>
                            <td class="py-2 px-3 text-muted">{{ $row['date'] ?? '—' }}</td>
                            <td class="py-2 px-3 text-right text-gold">{{ $row['contribution'] > 0 ? number_format($row['contribution']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <a href="{{ route('pledges.import.index', $wedding) }}" class="text-sm underline text-muted">Upload a different file</a>
    </div>
@endif
@endsection

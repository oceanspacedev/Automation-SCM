{{--
    Ringkasan klaim di halaman link WA.
    $dp: baris Data Program; $status: ['label' => ..., 'tone' => 'emerald'|'blue'|'gray'];
    $rows: list of ['label' => ..., 'value' => ..., 'strong' => bool, 'wide' => bool, 'sub' => ?string];
    $documents (opsional): list of ['label' => ..., 'url' => ?string, 'note' => string]
--}}
@php
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'gray' => 'bg-gray-50 text-gray-600 ring-gray-200',
    ];
    $meta = collect([$dp->kode_bt, $dp->region ?: $dp->big_region, $dp->sales_person])->filter()->implode(' · ');
    $missing = collect($documents ?? [])->whereNull('url')->map(fn ($document) => $document['label'].' '.mb_strtolower($document['note']))->implode(' · ');
@endphp

<div class="rounded-xl border border-gray-200">
    <div class="px-4 pt-4 pb-3">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-semibold text-gray-950">{{ $dp->dealer_name ?: '-' }}</p>
            <span class="shrink-0 text-xs font-medium rounded-full px-2 py-0.5 ring-1 ring-inset {{ $tones[$status['tone']] ?? $tones['gray'] }}">{{ $status['label'] }}</span>
        </div>
        @if($meta !== '')
            <p class="mt-0.5 text-xs text-gray-500">{{ $meta }}</p>
        @endif
    </div>

    <dl class="border-t border-gray-100 px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-3">
        @foreach($rows as $row)
            <div class="{{ ($row['wide'] ?? false) ? 'col-span-2' : '' }} min-w-0">
                <dt class="text-xs text-gray-500">{{ $row['label'] }}</dt>
                <dd class="mt-0.5 text-sm tabular-nums break-words {{ ($row['strong'] ?? false) ? 'font-semibold text-gray-950' : 'text-gray-900' }}">
                    {{ filled($row['value']) ? $row['value'] : '-' }}
                    @if(! empty($row['sub']))
                        <span class="block text-xs text-gray-500">{{ $row['sub'] }}</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    @if(! empty($documents))
        <div class="border-t border-gray-100 px-4 py-3">
            <p class="text-xs text-gray-500">Dokumen</p>
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach($documents as $document)
                    @if($document['url'])
                        <a href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" title="Lihat {{ $document['label'] }}"
                           class="h-9 px-2 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition">
                            <svg class="hidden sm:block w-3.5 h-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                            </svg>
                            <span class="whitespace-nowrap">{{ $document['label'] }}</span>
                        </a>
                    @else
                        <span aria-disabled="true" title="{{ $document['label'] }} {{ mb_strtolower($document['note']) }}"
                              class="h-9 px-2 inline-flex items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-xs font-medium text-gray-400 whitespace-nowrap">
                            {{ $document['label'] }}
                        </span>
                    @endif
                @endforeach
            </div>
            @if($missing !== '')
                <p class="mt-2 text-xs text-gray-500">{{ $missing }}.</p>
            @endif
        </div>
    @endif
</div>

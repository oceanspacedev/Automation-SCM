{{--
    Ringkasan klaim di halaman link WA: nama toko & status, satu angka besar (baris 'strong', mis. Nominal Potongan),
    lalu tabel label | isi tanpa kotak, hanya garis tipis antar baris.
    $dp: baris Data Program; $status: ['label' => ..., 'tone' => 'emerald'|'blue'|'gray'];
    $rows: list of ['label' => ..., 'value' => ..., 'strong' => bool, 'sub' => ?string, 'wide' => bool];
    $compact (opsional): rincian dalam grid tiga kolom, untuk daftar panjang seperti halaman Tim Pajak ('wide' = dua kolom);
    $documents (opsional): list of ['label' => ..., 'url' => ?string, 'note' => string]
--}}
@php
    // Status: titik berwarna + teks kalimat biasa (BISA DI POTONG → Bisa di potong; AR tetap kapital)
    $tones = [
        'emerald' => ['text-emerald-700', 'bg-emerald-500'],
        'blue' => ['text-[#1257c2]', 'bg-[#1769e0]'],
        'gray' => ['text-gray-600', 'bg-gray-400'],
    ];
    [$statusText, $statusDot] = $tones[$status['tone']] ?? $tones['gray'];
    $statusLabel = preg_replace('/\bar\b/u', 'AR', ucfirst(mb_strtolower($status['label'])));
    $rows = collect($rows);
    $figure = $rows->first(fn (array $row) => $row['strong'] ?? false);
    // Identitas toko satu baris kecil di bawah namanya (Kode BT · Region · Sales), supaya kartu muat satu layar
    $meta = collect([$dp->kode_bt, $dp->region ?: $dp->big_region, $dp->sales_person])->filter()->implode(' · ');
    $tableRows = $rows->reject(fn (array $row) => $row['strong'] ?? false);
    $labelClass = 'w-32 py-2 pr-4 text-left align-top text-xs font-normal leading-5 text-gray-500 whitespace-nowrap';
@endphp

<section class="space-y-4">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium leading-5 text-gray-950">{{ $dp->dealer_name ?: '-' }}</p>
            @if($meta !== '')
                <p class="text-xs leading-5 text-gray-500">{{ $meta }}</p>
            @endif
        </div>
        <span class="shrink-0 inline-flex items-center gap-2 text-xs font-medium leading-5 {{ $statusText }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}" aria-hidden="true"></span>{{ $statusLabel }}
        </span>
    </div>

    @if($figure)
        <div>
            <p class="text-xs text-gray-500">{{ $figure['label'] }}</p>
            <p class="mt-1 text-2xl leading-8 font-medium tabular-nums text-gray-950">{{ filled($figure['value']) ? $figure['value'] : '-' }}</p>
        </div>
    @endif

    @if($compact ?? false)
        {{-- Rincian panjang (Tim Pajak): baris pertama penuh, sisanya grid tiga kolom dengan label di atas nilai --}}
        @php
            $lead = $tableRows->first();
            $cells = $tableRows->slice(1);
        @endphp
        <div class="border-t border-gray-200/80 pt-3 space-y-3 text-sm">
            @if($lead)
                <div>
                    <p class="text-xs leading-5 text-gray-500">{{ $lead['label'] }}</p>
                    <p class="leading-5 break-words text-gray-900">{{ filled($lead['value']) ? $lead['value'] : '-' }}</p>
                    @if(! empty($lead['sub']))
                        <p class="text-xs leading-5 text-gray-500">{{ $lead['sub'] }}</p>
                    @endif
                </div>
            @endif

            <dl class="grid grid-cols-3 gap-x-4 gap-y-3">
                @foreach($cells as $row)
                    <div class="{{ ($row['wide'] ?? false) ? 'col-span-2' : '' }} min-w-0">
                        <dt class="text-xs leading-5 text-gray-500">{{ $row['label'] }}</dt>
                        <dd class="leading-5 tabular-nums break-words text-gray-900">
                            {{ filled($row['value']) ? $row['value'] : '-' }}
                            @if(! empty($row['sub']))
                                <span class="block text-xs text-gray-500">{{ $row['sub'] }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if(! empty($documents))
                <div class="border-t border-gray-100 pt-3 flex items-start gap-4">
                    <p class="w-28 shrink-0 text-xs leading-5 text-gray-500">Dokumen</p>
                    <div class="min-w-0 leading-5">@include('data_programs.partials.document-links', ['documents' => $documents])</div>
                </div>
            @endif
        </div>
    @else
        <table class="w-full text-sm border-t border-gray-200/80">
            <tbody class="divide-y divide-gray-100">
                @foreach($tableRows as $row)
                    <tr>
                        <th scope="row" class="{{ $labelClass }}">{{ $row['label'] }}</th>
                        <td class="py-2 align-top leading-5 tabular-nums break-words text-gray-900">
                            {{ filled($row['value']) ? $row['value'] : '-' }}
                            @if(! empty($row['sub']))
                                <span class="block text-xs text-gray-500">{{ $row['sub'] }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if(! empty($documents))
                    <tr>
                        <th scope="row" class="{{ $labelClass }}">Dokumen</th>
                        <td class="py-2 align-top leading-5">@include('data_programs.partials.document-links', ['documents' => $documents])</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif
</section>

@if(! empty($documents))
    @once
        {{-- Pratinjau dokumen: PDF di bingkai, gambar langsung; perangkat tanpa penampil PDF diarahkan membuka file --}}
        <div id="document-preview" class="hidden fixed inset-0 z-50 bg-black/60 p-3 sm:p-6 items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="document-preview-title">
            <div class="w-full max-w-3xl h-full max-h-[90vh] bg-white rounded-xl shadow-xl flex flex-col overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                    <p id="document-preview-title" class="text-sm font-medium text-gray-950 truncate"></p>
                    <div class="flex items-center gap-1 shrink-0">
                        <a id="document-preview-open" href="#" target="_blank" rel="noopener noreferrer"
                           class="h-8 px-3 inline-flex items-center rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">Buka di tab baru</a>
                        <button type="button" data-preview-close aria-label="Tutup"
                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div id="document-preview-body" class="flex-1 min-h-0 bg-gray-100 flex items-center justify-center overflow-auto"></div>
            </div>
        </div>

        <script>
            (() => {
                const modal = document.getElementById('document-preview');
                const title = document.getElementById('document-preview-title');
                const openLink = document.getElementById('document-preview-open');
                const body = document.getElementById('document-preview-body');
                let opener = null;

                const show = (link) => {
                    const label = link.dataset.previewLabel;
                    const isPdf = /\.pdf$/i.test(new URL(link.href).pathname);
                    opener = link;
                    title.textContent = label;
                    openLink.href = link.href;
                    body.replaceChildren();

                    if (link.dataset.preview === 'image') {
                        const image = new Image();
                        image.src = link.href;
                        image.alt = label;
                        image.className = 'max-w-full max-h-full object-contain';
                        body.append(image);
                    } else if (isPdf && navigator.pdfViewerEnabled === false) {
                        const message = document.createElement('div');
                        message.className = 'p-6 text-center space-y-3';
                        message.innerHTML = '<p class="text-xs text-gray-500">Pratinjau PDF tidak tersedia di perangkat ini.</p>';
                        const button = openLink.cloneNode(true);
                        button.removeAttribute('id');
                        button.textContent = 'Buka dokumen';
                        button.className = 'inline-flex h-10 px-4 items-center rounded-[10px] bg-[#1769e0] hover:bg-[#1257c2] text-white text-sm font-medium transition-colors';
                        message.append(button);
                        body.append(message);
                    } else {
                        // PDF langsung selebar jendela, tanpa toolbar dan panel halaman penampil PDF browser
                        const frame = document.createElement('iframe');
                        frame.src = isPdf ? `${link.href.split('#')[0]}#toolbar=0&navpanes=0&view=FitH` : link.href;
                        frame.title = label;
                        frame.className = 'w-full h-full bg-white';
                        body.append(frame);
                    }

                    modal.classList.replace('hidden', 'flex');
                    document.body.style.overflow = 'hidden';
                    modal.querySelector('[data-preview-close]').focus();
                };

                const hide = () => {
                    modal.classList.replace('flex', 'hidden');
                    body.replaceChildren();
                    document.body.style.overflow = '';
                    opener?.focus();
                };

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('[data-preview]');
                    if (link) {
                        event.preventDefault();
                        show(link);
                    }
                });
                modal.addEventListener('click', (event) => {
                    if (event.target === modal || event.target.closest('[data-preview-close]')) {
                        hide();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.classList.contains('flex')) {
                        hide();
                    }
                });
            })();
        </script>
    @endonce
@endif

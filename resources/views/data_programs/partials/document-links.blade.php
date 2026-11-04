{{-- Tautan CN / Agreement / Faktur Pajak; yang belum ada ditulis dengan keterangannya. $documents: list of ['label', 'url', 'note'] --}}
<div class="flex flex-wrap gap-x-4 gap-y-1">
    @foreach($documents as $document)
        @if($document['url'])
            {{-- Klik membuka pratinjau di halaman ini; tanpa JavaScript tetap terbuka di tab baru --}}
            <a href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" title="Lihat {{ $document['label'] }}"
               data-preview="{{ preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) parse_url($document['url'], PHP_URL_PATH)) ? 'image' : 'file' }}"
               data-preview-label="{{ $document['label'] }}"
               class="font-medium text-[#1257c2] hover:underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1769e0] rounded-sm">{{ $document['label'] }}</a>
        @else
            <span class="text-gray-500">{{ $document['label'] }} ({{ mb_strtolower($document['note']) }})</span>
        @endif
    @endforeach
</div>

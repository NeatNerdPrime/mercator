{{-- Renders a UrlList value: web URLs as links, network paths as text with a copy button --}}
@foreach(\App\Rules\UrlList::entries($value) as $entry)
    @if(\App\Rules\UrlList::isWebUrl($entry))
        <a href="{{ $entry }}" target="_blank" rel="noopener noreferrer">{{ $entry }}</a>@if(!$loop->last), @endif
    @elseif(\App\Rules\UrlList::isNetworkPath($entry))
        <span class="text-nowrap">{{ $entry }}</span><button type="button" class="btn btn-link btn-sm p-0 align-baseline copy-path-button"
                data-path="{{ $entry }}" title="{{ trans('global.copyPath') }}">
            <i class="bi bi-clipboard"></i>
        </button>
    @else
        {{ $entry }}
    @endif
@endforeach

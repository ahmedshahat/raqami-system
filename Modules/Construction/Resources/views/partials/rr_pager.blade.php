@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    $rrCurrent = $paginator->currentPage();
    $rrLast = max(1, $paginator->lastPage());
    $rrPages = collect([1, $rrLast, $rrCurrent - 1, $rrCurrent, $rrCurrent + 1])
        ->filter(fn ($page) => $page >= 1 && $page <= $rrLast)->unique()->sort()->values();
@endphp
<nav class="rr-pager" aria-label="@lang('construction::lang.pagination_nav')">
    <div class="rr-pager__sum">
        @if($paginator->total() > 0)
            @lang('construction::lang.pagination_summary_prefix') <span class="rr-num">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> @lang('construction::lang.pagination_of') <span class="rr-num">{{ $paginator->total() }}</span> {{ $itemLabel }}
        @endif
    </div>
    @if($rrLast > 1)
        <div class="rr-pager__pages">
            @if($paginator->onFirstPage())
                <span class="rr-pager__page" aria-disabled="true">@include('construction::partials.rr_icon', ['name' => 'chevron-back', 'size' => 16])</span>
            @else
                <a class="rr-pager__page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('construction::lang.previous_page')">@include('construction::partials.rr_icon', ['name' => 'chevron-back', 'size' => 16])</a>
            @endif
            @foreach($rrPages as $page)
                @if($loop->index > 0 && $page - $rrPages[$loop->index - 1] > 1)
                    <span class="rr-pager__page" aria-hidden="true">…</span>
                @endif
                @if($page === $rrCurrent)
                    <span class="rr-pager__page" aria-current="page"><span class="rr-num">{{ $page }}</span></span>
                @else
                    <a class="rr-pager__page" href="{{ $paginator->url($page) }}"><span class="rr-num">{{ $page }}</span></a>
                @endif
            @endforeach
            @if($paginator->hasMorePages())
                <a class="rr-pager__page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('construction::lang.next_page')">@include('construction::partials.rr_icon', ['name' => 'chevron-forward', 'size' => 16])</a>
            @else
                <span class="rr-pager__page" aria-disabled="true">@include('construction::partials.rr_icon', ['name' => 'chevron-forward', 'size' => 16])</span>
            @endif
        </div>
    @endif
</nav>

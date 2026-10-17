@php
    /**
     * نافذة تحويل عرض السعر إلى مشروع.
     * @var \Modules\Construction\Entities\ConstructionQuote|null $quote
     *      لو اتبعت عرض: الحفظ عادي (صفحة المشاهدة). من غيره: الرابط بيتحدد بالسكريبت من زرار الصف (صفحة القائمة) والحفظ بـ AJAX.
     */
    $quote = $quote ?? null;
@endphp
<div class="modal fade rr-scope" id="ct-quote-convert" tabindex="-1" role="dialog" aria-labelledby="rr-quote-convert-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST"
                  @if($quote) action="{{ route('construction.quotes.convert', $quote->id) }}"
                  @else data-ct-quick-create data-ct-open-mode="redirect" data-convert-url="{{ route('construction.quotes.convert', ['quote' => '__QUOTE__']) }}" @endif>
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-quote-convert-title">@lang('construction::lang.convert_to_project')</h2>
                        @if($quote)<p class="rr-modal__desc"><span class="rr-code">{{ $quote->number }}</span> · {{ $quote->title }}</p>@endif
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert"></div>
                    @include('construction::quotes.partials.conversion_fields', ['projectName' => $quote?->title])
                </div>
                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.convert_to_project')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                </footer>
            </form>
        </div>
    </div>
</div>

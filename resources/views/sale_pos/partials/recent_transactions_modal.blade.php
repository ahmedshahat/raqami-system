{{--
    نافذة "آخر العمليات" بتصميم رقمي (شاشة الكاشير الجديدة والقديمة).
    pos.js بيحمّل كل تبويب في div#tab_final / #tab_quotation / #tab_draft (show.bs.modal و shown.bs.tab على a[href="#tab_…"])،
    فالروابط لازم تفضل data-toggle="tab" بنفس الـ href.
--}}
@php
    $rr_icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
@endphp
<div class="modal fade no-print" id="recent_transactions_modal" tabindex="-1" role="dialog" aria-labelledby="rr_recent_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--lg rr-modal-dialog--compact rr-scope" role="document">
        <div class="modal-content rr-modal">
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="rr_recent_title">@lang('pos_screen.recent_title')</h2>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $rr_icon('close') !!}</button>
            </header>

            <nav class="rr-tabs rr-tabs--inset rr-recent__tabs" role="tablist" aria-label="@lang('pos_screen.recent_title')">
                <a class="rr-tab" href="#tab_final" data-toggle="tab" role="tab" aria-current="page">{!! $rr_icon('check-circle', 16) !!}@lang('pos_screen.recent_final')</a>
                <a class="rr-tab" href="#tab_quotation" data-toggle="tab" role="tab">{!! $rr_icon('file', 16) !!}@lang('pos_screen.recent_quotation')</a>
                <a class="rr-tab" href="#tab_draft" data-toggle="tab" role="tab">{!! $rr_icon('edit', 16) !!}@lang('pos_screen.recent_draft')</a>
            </nav>

            <div class="rr-modal__body rr-recent tab-content">
                @foreach (['final', 'quotation', 'draft'] as $rr_tab)
                    <div class="tab-pane{{ $loop->first ? ' active' : '' }}" id="tab_{{ $rr_tab }}" role="tabpanel">
                        <div class="rr-recent__loading" aria-hidden="true"><span></span><span></span><span></span></div>
                    </div>
                @endforeach
            </div>

            <footer class="rr-modal__foot">
                @can('sell.view')
                    <a href="{{ url('/sells') }}" class="rr-btn rr-btn--ghost rr-btn--sm rr-recent__all">{!! $rr_icon('list', 16) !!}@lang('pos_screen.recent_all')</a>
                @endcan
                <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-dismiss="modal">@lang('messages.close')</button>
            </footer>
        </div>
    </div>
</div>
<script>
    // التبويب النشط بيتعلّم بـ aria-current (رقمي) بدل li.active (bootstrap)
    document.addEventListener('DOMContentLoaded', function() {
        if (!window.jQuery) return;
        jQuery(document).on('shown.bs.tab', '.rr-recent__tabs .rr-tab', function() {
            jQuery(this).attr('aria-current', 'page').siblings().removeAttr('aria-current');
        });
        jQuery(document).on('show.bs.modal', '#recent_transactions_modal', function(e) {
            if (e.target !== this) return;
            var $tabs = jQuery('.rr-recent__tabs .rr-tab');
            $tabs.removeAttr('aria-current').first().attr('aria-current', 'page');
            jQuery('#recent_transactions_modal .tab-pane').removeClass('active').first().addClass('active');
        });
    });
</script>

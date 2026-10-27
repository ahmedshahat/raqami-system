{{--
    نافذة تخصيص الرئيسية: إظهار/إخفاء كل قسم وترتيبه جوه منطقته.
    بتتحفظ لكل يوزر في users.dashboard_layout (HomeDashboardController@saveLayout).
--}}
<div class="modal fade rr-scope" id="rr-customize" tabindex="-1" role="dialog" aria-labelledby="rr-customize-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('home.v2.layout') }}">
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-customize-title">@lang('home_v2.customize')</h2>
                        <p class="rr-modal__desc">@lang('home_v2.customize_desc')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('home_v2.cancel')">@include('partials.rr.icon', ['name' => 'close'])</button>
                </header>

                <div class="rr-modal__body">
                    @foreach($prefs['order'] as $zone => $keys)
                        @php $keys = array_values(array_filter($keys, fn ($key) => ! empty($available[$key]))); @endphp
                        @if(! empty($keys))
                            <fieldset class="rr-sort">
                                <legend class="rr-sort__title">@lang('home_v2.zone_' . $zone)</legend>
                                <ol class="rr-sort__list" data-rr-sort>
                                    @foreach($keys as $key)
                                        <li class="rr-sort__item">
                                            <input type="hidden" name="order[{{ $zone }}][]" value="{{ $key }}">
                                            <label class="rr-toggle rr-sort__label">
                                                <input type="checkbox" class="rr-toggle__input" name="visible[]" value="{{ $key }}" @if(! in_array($key, $prefs['hidden'])) checked @endif>
                                                <span>@lang('home_v2.section_' . $key)</span>
                                            </label>
                                            @if(count($keys) > 1)
                                                <span class="rr-sort__moves">
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-move="up" aria-label="@lang('home_v2.move_up')">@include('partials.rr.icon', ['name' => 'arrow-up', 'size' => 16])</button>
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-move="down" aria-label="@lang('home_v2.move_down')">@include('partials.rr.icon', ['name' => 'arrow-down', 'size' => 16])</button>
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </fieldset>
                        @endif
                    @endforeach
                </div>

                <footer class="rr-modal__foot">
                    <button type="submit" class="rr-btn rr-btn--primary">@include('partials.rr.icon', ['name' => 'check'])@lang('home_v2.save_layout')</button>
                    <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('home_v2.cancel')</button>
                    <button type="submit" name="reset" value="1" class="rr-btn rr-btn--secondary rr-btn--sm rr-modal__foot-end">@lang('home_v2.reset_layout')</button>
                </footer>
            </form>
        </div>
    </div>
</div>

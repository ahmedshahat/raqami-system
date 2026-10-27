@extends('layouts.app')
@section('title', __('home.home'))

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? __('home_v2.good_morning') : __('home_v2.good_evening');

    // تغيّر القيمة عن الفترة السابقة؛ المصروفات (inverse) نزولها هو الكويس
    $delta = function ($value, $prev, $inverse = false) {
        if (empty($prev) || $prev == 0) {
            return null;
        }
        $pct = ($value - $prev) / abs($prev) * 100;

        return ['pct' => $pct, 'good' => $inverse ? $pct < 0 : $pct >= 0];
    };

    // أقرب رقم "مستدير" فوق أعلى قيمة + تسميات مختصرة للمحور
    $niceMax = function ($max) {
        if ($max <= 0) {
            return 0;
        }
        $magnitude = 10 ** floor(log10($max));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $step) {
            if ($step * $magnitude >= $max) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    };
    $compact = function ($value) {
        if ($value >= 1000000) return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . ' ' . __('home_v2.million_short');
        if ($value >= 1000) return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . ' ' . __('home_v2.thousand_short');
        return (string) round($value);
    };

    $presets = ['today', '7_days', 'this_month', 'last_month', 'this_fy'];
@endphp

@section('content')
<main class="content rr-page rr-home" aria-labelledby="rr-home-title">

    {{-- ═══ رأس الصفحة + إجراءات سريعة ═══ --}}
    <header class="rr-home__head">
        <div class="rr-home__titles">
            <p class="rr-home__date">{{ now()->translatedFormat('l j F Y') }}</p>
            <h1 id="rr-home-title" class="rr-head__title">{{ $greeting }}، <bdi>{{ session('user.first_name') }}</bdi></h1>
            <p class="rr-head__sub">{{ $can_view_data ? __('home_v2.hero_sub') : __('home_v2.hero_sub_basic') }}</p>
        </div>
        <div class="rr-head__actions">
            @can('sell.create')
                <a class="rr-btn rr-btn--primary" href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}">@lang('home_v2.action_pos')</a>
            @endcan
            @can('direct_sell.access')
                <a class="rr-btn rr-btn--secondary" href="{{ action([\App\Http\Controllers\SellController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'plus'])@lang('home_v2.action_invoice')</a>
            @endcan
            @can('purchase.create')
                <a class="rr-btn rr-btn--secondary" href="{{ action([\App\Http\Controllers\PurchaseController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'plus'])@lang('home_v2.action_purchase')</a>
            @endcan
            @can('expense.add')
                <a class="rr-btn rr-btn--secondary" href="{{ action([\App\Http\Controllers\ExpenseController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'plus'])@lang('home_v2.action_expense')</a>
            @endcan
        </div>
    </header>

    @if($can_view_data)
        {{-- ═══ الفترة والفرع ═══ --}}
        <section class="rr-period-wrap">
            <form class="rr-period" method="GET" action="{{ route('home') }}" id="rr-period-form">
                <input type="hidden" name="period" value="{{ $period['preset'] }}">
                <div class="rr-period__presets" role="group" aria-label="@lang('home_v2.period')">
                    @foreach($presets as $preset)
                        <button type="submit" name="period" value="{{ $preset }}" class="rr-chip-btn" aria-pressed="{{ $period['preset'] == $preset ? 'true' : 'false' }}">@lang('home_v2.period_' . $preset)</button>
                    @endforeach
                </div>
                <div class="rr-period__custom">
                    @if(count($all_locations) > 1)
                        <label class="rr-sr" for="rr-location">@lang('home_v2.location')</label>
                        <div class="rr-control rr-control--location">
                            <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'map-pin', 'size' => 16])</span>
                            <select class="rr-control__input rr-control__select" id="rr-location" name="location_id" data-rr-autosubmit>
                                <option value="">@lang('home_v2.all_locations')</option>
                                @foreach($all_locations as $id => $name)
                                    <option value="{{ $id }}" @if($location_id == $id) selected @endif>{{ $name }}</option>
                                @endforeach
                            </select>
                            <span class="rr-control__chev">@include('partials.rr.icon', ['name' => 'chevron-down', 'size' => 16])</span>
                        </div>
                    @endif
                    <label class="rr-period__date">@lang('home_v2.from')
                        <span class="rr-control"><input class="rr-control__input" type="date" name="start" value="{{ $period['start'] }}"></span>
                    </label>
                    <label class="rr-period__date">@lang('home_v2.to')
                        <span class="rr-control"><input class="rr-control__input" type="date" name="end" value="{{ $period['end'] }}"></span>
                    </label>
                    <button type="submit" name="period" value="custom" class="rr-btn rr-btn--secondary">@lang('home_v2.apply')</button>
                    <button type="button" class="rr-btn rr-btn--ghost" data-toggle="modal" data-target="#rr-customize">@include('partials.rr.icon', ['name' => 'filter'])@lang('home_v2.customize')</button>
                </div>
            </form>
            <p class="rr-period__range">
                <span>
                    @lang('home_v2.range', ['start' => \Carbon::parse($period['start'])->translatedFormat('j F Y'), 'end' => \Carbon::parse($period['end'])->translatedFormat('j F Y')])
                    · @lang('home_v2.compared_to', ['start' => \Carbon::parse($period['prev_start'])->translatedFormat('j F'), 'end' => \Carbon::parse($period['prev_end'])->translatedFormat('j F Y')])
                </span>
                {{-- الرجوع للواجهة القديمة لليوزر ده بس (users.use_classic_home) --}}
                <form method="POST" action="{{ route('home.switch') }}" class="rr-inline-form">
                    @csrf
                    <input type="hidden" name="version" value="classic">
                    <button type="submit" class="rr-linkbtn">@lang('home_v2.old_home')</button>
                </form>
            </p>
        </section>

        {{-- ═══ الأقسام حسب تخطيط اليوزر (home_v2/sections/*، الترتيب والإخفاء من نافذة التخصيص) ═══ --}}
        @foreach($layout['top'] as $section)
            @include('home_v2.sections.' . $section)
        @endforeach

        @if(! empty($layout['main']))
            <div class="rr-home__row">
                @foreach($layout['main'] as $section)
                    <div class="rr-home__cell{{ in_array($section, \App\Utils\HomeDashboardUtil::WIDE_SECTIONS) ? ' rr-home__cell--wide' : '' }}">
                        @include('home_v2.sections.' . $section)
                    </div>
                @endforeach
            </div>
        @endif

        @if(! empty($layout['cards']))
            <div class="rr-home__cards">
                @foreach($layout['cards'] as $column)
                    <div class="rr-home__column">
                        @foreach($column as $section)
                            @include('home_v2.sections.' . $section)
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        @foreach($layout['bottom'] as $section)
            @include('home_v2.sections.' . $section)
        @endforeach
    @endif
</main>

@if($can_view_data)
    @include('home_v2.partials.customize_modal')
@endif
@endsection

@section('javascript')
    @include('partials.rr.chart_script')
    <script>
        document.querySelectorAll('[data-rr-autosubmit]').forEach(function (el) {
            el.addEventListener('change', function () { el.form.submit(); });
        });

        // common.js بيعطّل أزرار الـ submit مع أي إرسال فورم، والزرار المعطّل قيمته مابتتبعتش؛
        // فبننقل قيمة الزرار اللي اتضغط (الفترة) للحقل المخفي قبل الإرسال
        var periodForm = document.getElementById('rr-period-form');
        if (periodForm) {
            periodForm.addEventListener('submit', function (event) {
                if (event.submitter && event.submitter.name === 'period') {
                    periodForm.querySelector('input[type="hidden"][name="period"]').value = event.submitter.value;
                }
            });
        }

        // زرار "عرض الخريطة التفصيلية" بيفتح ويقفل خريطة أوقات الذروة
        document.querySelectorAll('[data-rr-disclose]').forEach(function (button) {
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            button.addEventListener('click', function () {
                var open = button.getAttribute('aria-expanded') !== 'true';
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.hidden = !open;
                button.querySelector('[data-rr-disclose-label]').textContent = button.dataset[open ? 'labelClose' : 'labelOpen'];
            });
        });

        // النافذة لازم تبقى ابن مباشر للـ body، وإلا بتطلع تحت الخلفية المعتمة بتاعة Bootstrap
        var customizeModal = document.getElementById('rr-customize');
        if (customizeModal) document.body.appendChild(customizeModal);

        // ترتيب الأقسام في نافذة التخصيص: أسهم لفوق/لتحت جوه نفس المنطقة
        document.querySelectorAll('[data-rr-sort]').forEach(function (list) {
            function refresh() {
                var items = list.children;
                for (var i = 0; i < items.length; i++) {
                    var up = items[i].querySelector('[data-rr-move="up"]');
                    var down = items[i].querySelector('[data-rr-move="down"]');
                    if (up) up.disabled = i === 0;
                    if (down) down.disabled = i === items.length - 1;
                }
            }
            list.addEventListener('click', function (event) {
                var button = event.target.closest('[data-rr-move]');
                if (!button) return;
                var item = button.closest('li');
                if (button.dataset.rrMove === 'up' && item.previousElementSibling) {
                    list.insertBefore(item, item.previousElementSibling);
                } else if (button.dataset.rrMove === 'down' && item.nextElementSibling) {
                    list.insertBefore(item.nextElementSibling, item);
                }
                refresh();
                button.focus();
            });
            refresh();
        });
    </script>
@endsection

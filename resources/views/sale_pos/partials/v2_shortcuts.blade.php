{{--
    نافذة اختصارات شاشة الكاشير الجديدة (F1 أو "المزيد ← اختصارات لوحة المفاتيح").
    الاختصارات ثابتة ومتعرّفة في v2_js (مفاتيح F بتشتغل حتى والمؤشر في خانة البحث).
    اختصارات "إعدادات النشاط ← النظام" (Mousetrap) بتشتغل على شاشة الكاشير القديمة بس، لأنها كانت بتتعارض مع دي.
--}}
@php
    // [مفاتيح، النص، الفاصل]: "+" يعني مع بعض، "/" يعني أي واحد فيهم
    $kbd = fn ($keys, $sep = '+') => collect((array) $keys)->map(fn ($k) => '<kbd class="rr-pos-keys__kbd">' . e($k) . '</kbd>')->implode('<span class="rr-pos-keys__plus">' . $sep . '</span>');
    $groups = [
        'k_general' => [
            ['F1', 'k_help'], ['F2', 'k_search'], ['F3', 'k_recent'], ['F4', 'k_customer'], ['Esc', 'k_clear'],
        ],
        'k_payment' => array_values(array_filter([
            $is_discount_enabled && $edit_discount ? ['F7', 'k_discount'] : null,
            ['F8', 'k_cash'], ['F9', 'k_multi'], ['F10', 'k_suspend'], [['Ctrl', 'Delete'], 'k_cancel'],
        ])),
        'k_lines' => [
            [['↑', '↓'], 'k_lines_move', '/'], [['+', '−'], 'k_lines_qty', '/'], ['Delete', 'k_lines_remove'],
        ],
        'k_windows' => [
            ['Enter', 'k_enter'], ['Esc', 'k_close'],
        ],
    ];
@endphp
<div class="modal fade rr-scope" id="rr_pos_keys_modal" tabindex="-1" role="dialog" aria-labelledby="rr_pos_keys_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="rr_pos_keys_title">@lang('pos_screen.shortcuts')</h2>
                    <p class="rr-modal__desc">@lang('pos_screen.k_desc')</p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
            </header>
            <div class="rr-modal__body rr-pos-keys">
                @foreach ($groups as $title => $rows)
                    <section class="rr-pos-keys__group">
                        <h3 class="rr-pos-keys__h">@lang('pos_screen.' . $title)</h3>
                        <dl class="rr-pos-keys__list">
                            @foreach ($rows as $row)
                                <div><dt>@lang('pos_screen.' . $row[1])</dt><dd>{!! $kbd($row[0], $row[2] ?? '+') !!}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>

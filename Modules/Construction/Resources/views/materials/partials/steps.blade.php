{{-- مراحل إذن الصرف: 1 البيانات ← 2 الخامات ← 3 الاعتماد. $current = رقم المرحلة الحالية (4 = كله خلص) --}}
<section class="rr-card rr-card__body" aria-label="@lang('construction::lang.document_steps')">
    <div class="rr-steps-scroll">
        <ol class="rr-steps rr-steps--compact">
            @foreach([['document_data', 'document_data_hint'], ['add_materials_step', 'add_materials_step_hint'], ['approve_and_post', 'approve_and_post_hint']] as $index => [$label, $note])
                @php $step = $index + 1; @endphp
                <li class="rr-step {{ $step < $current ? 'rr-step--done' : ($step === $current ? 'rr-step--current' : '') }}" @if($step === $current) aria-current="step" @endif>
                    <span class="rr-step__dot">@if($step < $current)@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 16])@else{{ $step }}@endif</span>
                    <span class="rr-step__label">@lang('construction::lang.' . $label)</span>
                    <span class="rr-step__note">@lang('construction::lang.' . $note)</span>
                </li>
            @endforeach
        </ol>
    </div>
</section>

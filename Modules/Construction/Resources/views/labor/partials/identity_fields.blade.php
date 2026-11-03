{{-- حقول بيانات اليومية المشتركة بين الإضافة والتعديل (الأسماء زي ما هي عشان الـ controller) --}}
<div class="rr-field">
    <label class="rr-field__label" for="labor-number">@lang('construction::lang.sheet_number'){!! $req !!}</label>
    <div class="rr-control {{ $fieldError('number') }}"><input id="labor-number" name="number" value="{{ old('number', $sheet->number ?? $defaultNumber) }}" class="rr-control__input rr-control__input--num" maxlength="60" required></div>
    @error('number')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>
<div class="rr-field">
    <label class="rr-field__label" for="labor-site">@lang('construction::lang.work_site')</label>
    <div class="rr-control {{ $fieldError('work_site') }}"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'map-pin', 'size' => 18])</span><input id="labor-site" name="work_site" value="{{ old('work_site', $sheet->work_site ?? '') }}" class="rr-control__input" placeholder="@lang('construction::lang.work_site_placeholder')"></div>
    @error('work_site')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>
<div class="rr-field">
    <label class="rr-field__label" for="labor-start">@lang('construction::lang.period_start'){!! $req !!}</label>
    <div class="rr-control rr-control--visible {{ $fieldError('period_start') }}"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="labor-start" name="period_start" value="{{ old('period_start', $sheet ? @format_date($sheet->period_start) : @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
    @error('period_start')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>
<div class="rr-field">
    <label class="rr-field__label" for="labor-end">@lang('construction::lang.period_end'){!! $req !!}</label>
    <div class="rr-control rr-control--visible {{ $fieldError('period_end') }}"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="labor-end" name="period_end" value="{{ old('period_end', $sheet ? @format_date($sheet->period_end) : @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
    @error('period_end')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>
<div class="rr-field rr-field--full">
    <label class="rr-field__label" for="labor-notes">@lang('construction::lang.notes')</label>
    <div class="rr-control rr-control--area {{ $fieldError('notes') }}"><textarea id="labor-notes" name="notes" class="rr-control__input" rows="4" placeholder="@lang('construction::lang.labor_notes_placeholder')">{{ old('notes', $sheet->notes ?? '') }}</textarea></div>
    @error('notes')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>

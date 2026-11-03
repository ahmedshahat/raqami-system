{{-- رقم/تاريخ/ملاحظات المستند — مشتركة بين الإضافة والتعديل والمرتجع (الأسماء زي ما هي عشان الـ controller) --}}
<div class="rr-field">
    <label class="rr-field__label" for="material-number">@lang('construction::lang.document_number'){!! $req !!}</label>
    <div class="rr-control {{ $fieldError('number') }}"><input id="material-number" name="number" value="{{ old('number', $document->number ?? $defaultNumber) }}" class="rr-control__input rr-control__input--num" required maxlength="60"></div>
    @error('number')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>
<div class="rr-field">
    <label class="rr-field__label" for="material-date">@lang('construction::lang.document_date'){!! $req !!}</label>
    <div class="rr-control rr-control--visible {{ $fieldError('document_date') }}"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="material-date" name="document_date" value="{{ old('document_date', $document ? @format_date($document->document_date) : @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
    @error('document_date')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>
<div class="rr-field rr-field--full">
    <label class="rr-field__label" for="material-notes">@lang('construction::lang.notes')</label>
    <div class="rr-control rr-control--area {{ $fieldError('notes') }}"><textarea id="material-notes" name="notes" class="rr-control__input" rows="{{ $notesRows ?? 3 }}" placeholder="@lang('construction::lang.document_notes_placeholder')">{{ old('notes', $document->notes ?? '') }}</textarea></div>
    @error('notes')<p class="rr-field__error">{{ $message }}</p>@enderror
</div>

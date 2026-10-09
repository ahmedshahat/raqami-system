<div class="form-group ct-date-range-preset">
    <label>@lang('construction::lang.date_range')</label>
    <select name="date_range" class="form-control ct-report-date-range" data-selected-range="{{ $dateRange }}">
        @foreach(['today','yesterday','last_7_days','last_30_days','last_3_months','last_6_months','last_year','last_3_years','custom'] as $preset)
            <option value="{{ $preset }}" @selected($dateRange === $preset)>@lang('construction::lang.date_range_'.$preset)</option>
        @endforeach
    </select>
</div>
<div class="form-group ct-date-range-start" @if($dateRange !== 'custom') hidden @endif><label>@lang('construction::lang.from_date')</label><input name="from_date" value="{{ @format_date($fromDate) }}" class="form-control ct-date-picker" readonly></div>
<div class="form-group ct-date-range-end" @if($dateRange !== 'custom') hidden @endif><label>@lang('construction::lang.to_date')</label><input name="to_date" value="{{ @format_date($toDate) }}" class="form-control ct-date-picker" readonly></div>

@once
@push('ct_quick_js')
<script>
$(function () {
    $('.ct-report-date-range').each(function () {
        var $range = $(this);
        $range.val($range.data('selected-range'));
        if ($range.hasClass('select2-hidden-accessible')) {
            $range.trigger('change.select2');
        }
    });

    $('.ct-report-date-range').on('change', function () {
        var $form = $(this).closest('form');
        var preset = $(this).val();
        if (preset === 'custom') {
            $form.removeClass('is-preset-range').addClass('is-custom-range');
            $form.find('.ct-date-range-start, .ct-date-range-end').prop('hidden', false);
            $form.find('[name="from_date"]').focus();
            return;
        }

        $form.removeClass('is-custom-range').addClass('is-preset-range');
        $form.find('.ct-date-range-start, .ct-date-range-end').prop('hidden', true);

        var end = moment().startOf('day');
        var start = end.clone();
        if (preset === 'yesterday') { start.subtract(1, 'day'); end = start.clone(); }
        if (preset === 'last_7_days') start.subtract(6, 'days');
        if (preset === 'last_30_days') start.subtract(29, 'days');
        if (preset === 'last_3_months') start.subtract(3, 'months');
        if (preset === 'last_6_months') start.subtract(6, 'months');
        if (preset === 'last_year') start.subtract(1, 'year');
        if (preset === 'last_3_years') start.subtract(3, 'years');
        $form.find('[name="from_date"]').val(start.format(moment_date_format));
        $form.find('[name="to_date"]').val(end.format(moment_date_format));
    });

    $('.ct-financial-report-filter [name="from_date"], .ct-financial-report-filter [name="to_date"]').on('dp.change', function () {
        var $form = $(this).closest('form');
        $form.removeClass('is-preset-range').addClass('is-custom-range');
        $form.find('.ct-date-range-start, .ct-date-range-end').prop('hidden', false);
        $form.find('.ct-report-date-range').val('custom').trigger('change.select2');
    });
});
</script>
@endpush
@endonce

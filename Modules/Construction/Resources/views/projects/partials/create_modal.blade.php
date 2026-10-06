<div class="modal fade ct-quick-modal" id="ct-project-create" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="POST" action="{{ route('construction.projects.store') }}" data-ct-quick-create data-ct-open-mode="redirect">@csrf
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">@lang('construction::lang.new_project')</h4></div>
            <div class="modal-body">
                <div class="alert alert-danger ct-quick-errors"></div>
                <input type="hidden" name="code" value="{{ $suggestedCode }}"><input type="hidden" name="status" value="draft"><input type="hidden" name="consultant_name" value="">
                <div class="form-group"><label>@lang('construction::lang.import_from_quote')</label><select name="quote_id" id="ct-project-quote" class="form-control"><option value="">@lang('construction::lang.without_quote')</option>@foreach($acceptedQuotes as $quote)<option value="{{ $quote->id }}" data-customer="{{ $quote->customer_id }}" data-title="{{ $quote->title }}" @selected((string)request('quote_id') === (string)$quote->id)>{{ $quote->number }} — {{ $quote->title }} — {{ $quote->customer?->supplier_business_name ?: $quote->customer?->name }}</option>@endforeach</select><small class="text-muted">@lang('construction::lang.accepted_quotes_only_hint')</small></div>
                <div class="row"><div class="col-md-7 form-group"><label>@lang('construction::lang.project_name') *</label><input name="name" id="ct-project-name" class="form-control" maxlength="190" required value="{{ old('name') }}"></div><div class="col-md-5 form-group"><label>@lang('construction::lang.customer') *</label><select name="customer_id" id="ct-project-customer" class="form-control" required><option value="">@lang('construction::lang.choose')</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach</select></div></div>
                <div class="row"><div class="col-md-6 form-group"><label>@lang('construction::lang.project_manager')</label><select name="manager_id" class="form-control"><option value="">@lang('construction::lang.choose')</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->user_full_name ?: $user->username }}</option>@endforeach</select></div><div class="col-md-6 form-group"><label>@lang('construction::lang.consultant')</label><select name="consultant_selection" class="form-control" required><option value="none">@lang('construction::lang.no_consultant')</option>@foreach($consultants as $contact)<option value="{{ $contact->id }}">{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach</select></div></div>
                <div class="form-group"><label>@lang('construction::lang.location')</label><input name="location" class="form-control" maxlength="255"></div>
                <div class="row"><div class="col-md-6 form-group"><label>@lang('construction::lang.start_date')</label><input type="text" name="start_date" class="form-control ct-date-picker" readonly></div><div class="col-md-6 form-group"><label>@lang('construction::lang.end_date')</label><input type="text" name="end_date" class="form-control ct-date-picker" readonly></div></div>
                <div class="form-group"><label>@lang('construction::lang.notes')</label><textarea name="description" class="form-control" rows="3" maxlength="5000"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> @lang('construction::lang.create_project')</button></div>
        </form>
    </div></div>
</div>
@push('ct_quick_js')
<script>
$(function () {
    $('.ct-date-picker').datetimepicker({format: moment_date_format, ignoreReadonly: true});
    function applyQuote() {
        var option = $('#ct-project-quote option:selected');
        if (!option.val()) { $('#ct-project-customer').prop('disabled', false); return; }
        $('#ct-project-customer').val(String(option.data('customer'))).trigger('change').prop('disabled', false);
        if (!$('#ct-project-name').val()) $('#ct-project-name').val(option.data('title'));
    }
    $('#ct-project-quote').on('change', applyQuote); applyQuote();
    @if(request('quote_id') || request('open_create')) $('#ct-project-create').modal('show'); @endif
});
</script>
@endpush

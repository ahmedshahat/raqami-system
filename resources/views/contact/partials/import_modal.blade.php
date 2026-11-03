{{-- نافذة استيراد الموردين والعملاء على القائمة (contact/index_v2). الحفظ في ContactController::postImportContacts --}}
@php
    $import_required = [1, 3, 14];
    $import_if_supplier = [10, 11];
    $import_labels = json_decode(session('business.custom_labels'), true);
    $import_columns = __('contact_list.import_cols');
    foreach (range(1, 4) as $number) {
        $import_columns[23 + $number] = $import_labels['contact']['custom_field_' . $number] ?? __('lang_v1.contact_custom_field' . $number);
    }
@endphp
<div class="modal fade rr-scope" id="rr_contacts_import_modal" tabindex="-1" role="dialog" aria-labelledby="rr_contacts_import_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
        <div class="modal-content rr-modal">
            <form id="rr_contacts_import_form" action="{{ action([\App\Http\Controllers\ContactController::class, 'postImportContacts']) }}" method="post" enctype="multipart/form-data">
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr_contacts_import_title">@lang('contact_list.import_title')</h2>
                        <p class="rr-modal__desc">@lang('contact_list.import_desc')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('contact_list.close')">@include('partials.rr.icon', ['name' => 'close'])</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger" id="rr_contacts_import_error" role="alert"></div>

                    <div class="rr-field">
                        <span class="rr-field__label">@lang('contact_list.import_step_template')</span>
                        <div><a class="rr-btn rr-btn--secondary" href="{{ asset('files/import_contacts_csv_template.xls') }}" download>@include('partials.rr.icon', ['name' => 'download', 'size' => 20])@lang('contact_list.import_template')</a></div>
                        <span class="rr-field__hint">@lang('contact_list.import_template_hint')</span>
                    </div>

                    <div class="rr-field">
                        <label class="rr-field__label" for="rr_contacts_import_file">@lang('contact_list.import_step_file')</label>
                        <input class="rr-file-input" type="file" id="rr_contacts_import_file" name="contacts_csv" accept=".xls,.xlsx" required>
                    </div>

                    <details class="rr-help">
                        <summary>@lang('contact_list.import_columns_help')</summary>
                        <table class="rr-table rr-import-cols">
                            <thead>
                                <tr>
                                    <th class="rr-th">#</th>
                                    <th class="rr-th">@lang('contact_list.import_col_name')</th>
                                    <th class="rr-th">@lang('contact_list.import_col_note')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($import_columns as $number => $label)
                                    <tr class="rr-tr">
                                        <td class="rr-td"><span class="rr-num">{{ $number }}</span></td>
                                        <td class="rr-td">{{ $label }}</td>
                                        <td class="rr-td rr-td--wrap">
                                            @if(in_array($number, $import_required))
                                                <span class="rr-badge rr-badge--danger">@lang('contact_list.import_required')</span>
                                            @elseif(in_array($number, $import_if_supplier))
                                                <span class="rr-badge rr-badge--warning">@lang('contact_list.import_required_supplier')</span>
                                            @endif
                                            {{ __('contact_list.import_col_notes')[$number] ?? '' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </div>
                <footer class="rr-modal__foot">
                    <button type="submit" class="rr-btn rr-btn--primary" id="rr_contacts_import_go">@include('partials.rr.icon', ['name' => 'upload', 'size' => 20])<span>@lang('contact_list.import')</span></button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('contact_list.close')</button>
                </footer>
            </form>
        </div>
    </div>
</div>

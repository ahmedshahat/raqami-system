@extends('construction::layouts.module')

@section('title', __('construction::lang.install_module'))

@section('module_content')
    <section class="content">
        <div class="box box-solid">
            <div class="box-body">
                <p>@lang('construction::lang.install_explanation')</p>
                <p><strong>Version:</strong> {{ $moduleVersion }}</p>

                <form method="POST" action="{{ route('construction.install.store') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        @lang('construction::lang.install')
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection

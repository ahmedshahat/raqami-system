@extends($layout)

@section('title', __('superadmin::lang.subscription'))

@section('content')

<!-- Main content -->
<section class="content">

	@include('superadmin::layouts.partials.currency')

	<div class="subscribe-wrapper">

		{{-- ============================================================ --}}
		{{-- كارت الباقة                                                   --}}
		{{-- ============================================================ --}}
		<div class="package-card">

			<div class="package-card-header">
				<span class="package-card-label">@lang('superadmin::lang.pay_and_subscribe')</span>
				<h2 class="package-card-name">{{$package->name}}</h2>

				@php
				  if($coupon_status['status'] == 'success')	{
					$package->price =  number_format($package_price_after_discount , 2, '.', '');
				  }
				@endphp

				<div class="package-card-price">
					<span class="display_currency" data-currency_symbol="true">{{$package->price}}</span>
					<span class="package-card-interval">/ {{$package->interval_count}} {{ucfirst($package->interval)}}</span>
				</div>

				@if($package->trial_days != 0)
					<span class="package-trial-badge">
						<i class="fa fa-gift"></i> {{$package->trial_days}} @lang('superadmin::lang.trial_days')
					</span>
				@endif
			</div>

			<ul class="package-feature-list">
				<li>
					<i class="fa fa-check-circle"></i>
					<span>
						@if($package->location_count == 0)
							@lang('superadmin::lang.unlimited')
						@else
							{{$package->location_count}}
						@endif
						@lang('business.business_locations')
					</span>
				</li>

				<li>
					<i class="fa fa-check-circle"></i>
					<span>
						@if($package->user_count == 0)
							@lang('superadmin::lang.unlimited')
						@else
							{{$package->user_count}}
						@endif
						@lang('superadmin::lang.users')
					</span>
				</li>

				<li>
					<i class="fa fa-check-circle"></i>
					<span>
						@if($package->product_count == 0)
							@lang('superadmin::lang.unlimited')
						@else
							{{$package->product_count}}
						@endif
						@lang('superadmin::lang.products')
					</span>
				</li>

				<li>
					<i class="fa fa-check-circle"></i>
					<span>
						@if($package->invoice_count == 0)
							@lang('superadmin::lang.unlimited')
						@else
							{{$package->invoice_count}}
						@endif
						@lang('superadmin::lang.invoices')
					</span>
				</li>
			</ul>

			{{-- ============== الكوبون ============== --}}
			<div class="coupon-section">

				@if (request()->has('code'))
					<div class="coupon-alert coupon-alert-{{ $coupon_status['status'] }}">
						@if($coupon_status['status'] == 'success')
							<i class="fa fa-check-circle"></i>
							@lang('superadmin::lang.package_price_after_discount') =
							<span class="display_currency" data-currency_symbol="true">{{ number_format($package_price_after_discount , 2, '.', ''); }}</span>
							(@lang('superadmin::lang.you_save') <span class="display_currency" data-currency_symbol="true">{{ number_format($discount_amount , 2, '.', ''); }}</span>)
						@else
							<i class="fa fa-exclamation-circle"></i>
							{{  $coupon_status['msg'] }}
						@endif
					</div>
				@endif

				{!! Form::open([
					'method' => 'get',
					'id' => 'coupon_check',
					'class' => 'coupon-form',
				]) !!}
					<div class="coupon-form-row">
						{!! Form::text('code', request()->get('code') ?? null, [
							'class' => 'form-control coupon-input',
							'required',
							'placeholder' => __('superadmin::lang.coupon_code'),
						]) !!}
						{!! Form::submit('Apply', ['class' => 'tw-dw-btn tw-dw-btn-success tw-text-white tw-dw-btn-sm coupon-apply-btn']) !!}
					</div>
				{!! Form::close() !!}
			</div>

		</div>

		{{-- ============================================================ --}}
		{{-- تابات طريقة الدفع (يدوي / أونلاين)                            --}}
		{{-- ============================================================ --}}
		<div class="payment-tabs">

			<h3 class="payment-tabs-title">@lang('superadmin::lang.pay_and_subscribe')</h3>

			<div class="payment-tab-buttons">
				<button type="button" id="tab-btn-manual" class="payment-tab-btn active" onclick="showPaymentTab('manual')">
					<i class="fa fa-university"></i> الدفع اليدوي
				</button>
				<button type="button" id="tab-btn-online" class="payment-tab-btn" onclick="showPaymentTab('online')">
					<i class="fa fa-credit-card"></i> الدفع الأونلاين
				</button>
			</div>

			{{-- ============== تاب: الدفع اليدوي ============== --}}
			<div id="payment-panel-manual" class="payment-panel">

				<div class="payment-hours-note">
					<i class="fa fa-clock-o"></i>
					التفعيل اليدوي متاح يوميًا من الساعة 9 صباحًا إلى 9 مساءً، ما عدا يوم الجمعة.
				</div>

				<ul class="list-group">

					{{-- بوابات الدفع اليدوية المسجّلة في النظام (إن وجدت) --}}
					@foreach($gateways as $k => $v)
						<li class="list-group-item payment-method-card">
							<b>@lang('superadmin::lang.pay_via', ['method' => $v])</b>
							<div class="row" id="paymentdiv_{{$k}}">
								@php
									$view = 'superadmin::subscription.partials.pay_'.$k;
								@endphp
								@includeIf($view)
							</div>
						</li>
					@endforeach

					{{-- مصرف الراجحي - السعودية (QR) --}}
					<li class="list-group-item payment-method-card">
						<div class="payment-method-head">
							<span class="payment-method-icon"><i class="fa fa-qrcode"></i></span>
							<div>
								<b>مصرف الراجحي - السعودية</b>
								<p class="payment-note">امسح الكود من تطبيق الراجحي لتحويل المبلغ مباشرة، أو استخدم رقم الحساب</p>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12 tw-text-center">
								{{-- استبدل المسار بصورة QR الحقيقية الخاصة بحساب الراجحي --}}
								<img src="{{ asset('images/payments/alrajhi-qr.png') }}"
									 alt="QR كود مصرف الراجحي"
									 class="payment-qr-img">
							</div>
						</div>
						<div class="row">
							<div class="col-md-12 payment-number-row" style="margin-top:12px;">
								<span class="payment-number" dir="ltr">336000010006086218047</span>
								<button type="button" class="tw-dw-btn tw-dw-btn-sm payment-copy-btn" data-copy="336000010006086218047" onclick="copyPaymentNumber(this)">
									<i class="fa fa-copy"></i> نسخ
								</button>
							</div>
						</div>
					</li>

					{{-- إنستا باي - مصر --}}
					<li class="list-group-item payment-method-card">
						<div class="payment-method-head">
							<span class="payment-method-icon payment-method-icon-logo">
								{{-- استبدل المسار بشعار إنستا باي الحقيقي --}}
								<img src="{{ asset('images/payments/instapay-logo.png') }}" alt="InstaPay">
							</span>
							<div>
								<b>إنستا باي - مصر</b>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12 payment-number-row">
								{{-- استبدل الرقم برقم إنستا باي الحقيقي --}}
								<span class="payment-number" dir="ltr">01001179178</span>
								<button type="button" class="tw-dw-btn tw-dw-btn-sm payment-copy-btn" data-copy="01001179178" onclick="copyPaymentNumber(this)">
									<i class="fa fa-copy"></i> نسخ
								</button>
							</div>
						</div>
					</li>

					{{-- فودافون كاش - مصر --}}
					<li class="list-group-item payment-method-card">
						<div class="payment-method-head">
							<span class="payment-method-icon payment-method-icon-logo">
								{{-- استبدل المسار بشعار فودافون كاش الحقيقي --}}
								<img src="{{ asset('images/payments/vodafone-cash1.svg') }}" alt="Vodafone Cash">
							</span>
							<div>
								<b>فودافون كاش - مصر</b>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12 payment-number-row">
								{{-- استبدل الرقم برقم فودافون كاش الحقيقي --}}
								<span class="payment-number" dir="ltr">01001179178</span>
								<button type="button" class="tw-dw-btn tw-dw-btn-sm payment-copy-btn" data-copy="01001179178" onclick="copyPaymentNumber(this)">
									<i class="fa fa-copy"></i> نسخ
								</button>
							</div>
						</div>
					</li>

				</ul>

				<p class="payment-note tw-text-center" style="margin-top:10px;">
					بعد التحويل، يرجى إرفاق صورة إشعار التحويل ليتم مراجعته واعتماده من المشرف.
				</p>
			</div>

			{{-- ============== تاب: الدفع الأونلاين ============== --}}
			<div id="payment-panel-online" class="payment-panel" style="display:none;">
				<ul class="list-group">
					@if(!empty(env('KASHIER_MERCHANT_ID')))
						<li class="list-group-item payment-method-card tw-text-center">
							{{-- شعارات وسائل الدفع --}}
                            <div class="payment-logos-bar">
                                <img src="{{ asset('images/payments/visa-logo.png') }}" alt="Visa" class="payment-logo-img">
                             
                            </div>
							<p class="payment-note" style="margin-bottom:14px;">
								سيتم تحويلك إلى صفحة دفع آمنة لاختيار فيزا، ماستركارد، ميزة، فودافون كاش أو أورنج كاش.
							</p>
							<a href="{{ route('kashier.pay', $package->id) }}?price={{ $package->price }}" class="tw-dw-btn tw-dw-btn-success tw-text-white tw-dw-btn-md">
								<i class="fa fa-credit-card"></i>ادافع الان    
							</a>
						</li>
					@else
						<li class="list-group-item payment-method-card tw-text-center">
							<p class="payment-note">الدفع الأونلاين غير متاح حاليًا.</p>
						</li>
					@endif
				</ul>
			</div>

		</div>

	</div>

</section>

<style>
.subscribe-wrapper {
	direction: rtl;
	text-align: right;
	max-width: 760px;
	margin: 0 auto;
	font-family: inherit;
}

/* ===== كارت الباقة ===== */
.package-card {
	background: #fff;
	border-radius: 10px;
	border: 1px solid #e8e8e8;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	overflow: hidden;
	margin-bottom: 24px;
}
.package-card-header {
	background: #00a65a;
	color: #fff;
	padding: 24px 28px;
	position: relative;
}
.package-card-label {
	font-size: 12px;
	opacity: 0.85;
	text-transform: uppercase;
	letter-spacing: .5px;
}
.package-card-name {
	margin: 4px 0 10px;
	font-size: 24px;
	font-weight: 700;
	color: #fff;
}
.package-card-price {
	font-size: 28px;
	font-weight: 700;
}
.package-card-interval {
	font-size: 14px;
	font-weight: 400;
	opacity: 0.85;
	margin-right: 6px;
}
.package-trial-badge {
	display: inline-block;
	margin-top: 12px;
	background: rgba(255,255,255,0.18);
	border-radius: 20px;
	padding: 5px 14px;
	font-size: 12px;
	font-weight: 600;
}
.package-trial-badge i {
	margin-left: 5px;
}

.package-feature-list {
	list-style: none;
	margin: 0;
	padding: 20px 28px;
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px 20px;
}
.package-feature-list li {
	display: flex;
	align-items: center;
	font-size: 14px;
	color: #444;
}
.package-feature-list li i {
	color: #00a65a;
	margin-left: 8px;
	font-size: 16px;
}

/* ===== الكوبون ===== */
.coupon-section {
	border-top: 1px solid #f0f0f0;
	padding: 18px 28px 24px;
}
.coupon-alert {
	border-radius: 6px;
	padding: 10px 14px;
	font-size: 13px;
	margin-bottom: 12px;
}
.coupon-alert i {
	margin-left: 6px;
}
.coupon-alert-success {
	background: #eafaf1;
	border: 1px solid #c3e6cb;
	color: #1d7a46;
}
.coupon-alert-danger,
.coupon-alert-error,
.coupon-alert-warning {
	background: #fdecea;
	border: 1px solid #f5c6cb;
	color: #a02a2a;
}
.coupon-form-row {
	display: flex;
	gap: 10px;
}
.coupon-input {
	flex: 1;
}
.coupon-apply-btn {
	white-space: nowrap;
}

/* ===== تابات الدفع ===== */
.payment-tabs {
	background: #fff;
	border-radius: 10px;
	border: 1px solid #e8e8e8;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	padding: 24px 28px 28px;
}
.payment-tabs-title {
	font-size: 16px;
	font-weight: 700;
	margin: 0 0 16px;
	color: #333;
}
.payment-tab-buttons {
	display: flex;
	gap: 8px;
	margin-bottom: 18px;
}
.payment-tab-btn {
	flex: 1;
	padding: 10px 14px;
	font-weight: 600;
	font-size: 14px;
	border-radius: 6px;
	border: 1px solid #d9d9d9;
	background: #fff;
	color: #555;
	cursor: pointer;
	transition: all .15s ease-in-out;
}
.payment-tab-btn i {
	margin-left: 6px;
}
.payment-tab-btn.active {
	background: #00a65a;
	border-color: #00a65a;
	color: #fff;
}
.payment-hours-note {
	background: #fcf8e3;
	border: 1px solid #faebcc;
	color: #8a6d3b;
	border-radius: 6px;
	padding: 10px 14px;
	font-size: 13px;
	margin-bottom: 14px;
}
.payment-hours-note i {
	margin-left: 6px;
}
.payment-method-card {
	border-radius: 8px;
	border: 1px solid #ececec;
	margin-bottom: 10px;
	padding: 16px;
}
.payment-method-head {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 10px;
}
.payment-method-icon {
	width: 50px;
	height: 50px;
	flex-shrink: 0;
	border-radius: 50%;
	background: #f3f6f4;
	color: #00a65a;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 16px;
}
.payment-method-icon-logo {
    width: 50px !important;  /* يمكنك زيادة أو تقليل الرقم حسب رغبتك (مثلاً 50px أو 70px) */
    height: 50px !important;
    /*background: #fff;*/
    /*border: 1px solid #ececec;*/
    border-radius: 50%;      /* لجعل الشكل دائرياً تماماً */
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05); /* إضافة ظل خفيف لشكل أجمل */
}

.payment-method-icon-logo img {
    width: 90%;
    height: 90%;
    object-fit: cover; /* cover تجعل الصورة تملأ الدائرة بشكل أفضل من contain إذا كانت الصورة بخلفية بيضاء */
    padding: 2px; /* تقليل المسافة الداخلية لتكبر مساحة الشعار */
    border-radius: 50%;
}

.payment-qr-img {
	max-width: 140px;
	border: 1px solid #eee;
	border-radius: 8px;
	padding: 6px;
	background: #fff;
}
.payment-note {
	font-size: 12px;
	color: #888;
	margin: 2px 0 0;
}
.payment-number-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.payment-number {
	font-size: 14px;
	font-weight: 600;
	color: #333;
}
.payment-copy-btn {
	white-space: nowrap;
}
.online-gateway-icon {
	width: 44px;
	height: 44px;
	border-radius: 50%;
	background: #f3f6f4;
	color: #00a65a;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 18px;
	margin: 0 auto 12px;
}

@media (max-width: 600px) {
	.package-feature-list {
		grid-template-columns: 1fr;
	}
	.coupon-form-row {
		flex-direction: column;
	}
}
</style>

<script>
function showPaymentTab(tab) {
	var manualPanel = document.getElementById('payment-panel-manual');
	var onlinePanel = document.getElementById('payment-panel-online');
	var manualBtn = document.getElementById('tab-btn-manual');
	var onlineBtn = document.getElementById('tab-btn-online');

	if (tab === 'manual') {
		manualPanel.style.display = 'block';
		onlinePanel.style.display = 'none';
		manualBtn.classList.add('active');
		onlineBtn.classList.remove('active');
	} else {
		manualPanel.style.display = 'none';
		onlinePanel.style.display = 'block';
		onlineBtn.classList.add('active');
		manualBtn.classList.remove('active');
	}
}

function copyPaymentNumber(btn) {
	var text = btn.getAttribute('data-copy');
	navigator.clipboard.writeText(text).then(function () {
		var original = btn.innerHTML;
		btn.innerHTML = '<i class="fa fa-check"></i> تم النسخ';
		setTimeout(function () {
			btn.innerHTML = original;
		}, 1500);
	});
}
</script>

@endsection
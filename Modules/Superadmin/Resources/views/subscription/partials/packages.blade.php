@php
	$count = 0;
@endphp

<div class="packages-grid">
@foreach ($packages as $package)
	@if($package->is_private == 1 && !auth()->user()->can('superadmin'))
		@php
			continue;
		@endphp
	@endif
	@php
		$businesses_ids = json_decode($package->businesses);
	@endphp
	@if (Route::current()->getName() == 'subscription.index' && (is_array($businesses_ids) && in_array(auth()->user()->business_id, $businesses_ids) || is_null($package->businesses)))
		@php
			$count++;
		@endphp
		@include('superadmin::subscription.partials.package_card')
	@elseif(Route::current()->getName() == 'pricing' && is_null($package->businesses))
		@php
			$count++;
		@endphp
		@include('superadmin::subscription.partials.package_card')
	@endif

@endforeach
</div>

<style>
.packages-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 24px;
	align-items: stretch;
}

.package-card-col {
	display: flex;
}

.package-card-box {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
	background: #fff;
	border: 1px solid #e8e8ec;
	border-radius: 16px;
	padding: 28px 24px;
	position: relative;
	transition: transform .2s ease, box-shadow .2s ease;
}
.package-card-box:hover {
	box-shadow: 0 8px 24px rgba(79, 70, 229, 0.08);
}

.package-card-popular {
	background: #f3f1ff;
	border: 2px solid #6366f1;
}

.package-card-badge {
	position: absolute;
	top: -12px;
	right: 50%;
	transform: translateX(50%);
	background: #4f46e5;
	color: #fff;
	font-size: 12px;
	font-weight: 600;
	padding: 4px 16px;
	border-radius: 20px;
	white-space: nowrap;
}

.package-card-name {
	font-size: 20px;
	font-weight: 700;
	color: #1f1f1f;
	margin: 6px 0 4px;
}

.package-card-desc {
	font-size: 13px;
	color: #6b7280;
	margin: 0 0 18px;
	min-height: 34px;
}

.package-card-price-row {
	display: flex;
	align-items: baseline;
	gap: 4px;
}

.package-card-price {
	font-size: 36px;
	font-weight: 700;
	color: #1f1f1f;
}

.package-card-price-free {
	font-size: 18px;
	font-weight: 600;
	color: #4f46e5;
}

.package-card-price-caption {
	font-size: 13px;
	color: #6b7280;
	margin: 0 0 18px;
}

.package-card-cta {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 46px;
	width: 100%;
	border-radius: 999px;
	background: #00a65a;
	color: #fff;
	font-size: 14px;
	font-weight: 600;
	margin-bottom: 22px;
	text-decoration: none;
	transition: background-color .15s ease;
}
.package-card-cta:hover {
	background: #008c4c;
	color: #fff;
}

.package-card-features {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.package-card-features li {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 13px;
	color: #374151;
}
.package-card-features li i {
	font-size: 16px;
	color: #6366f1;
	flex-shrink: 0;
}

@media (max-width: 700px) {
	.packages-grid {
		grid-template-columns: 1fr;
	}
}
</style>
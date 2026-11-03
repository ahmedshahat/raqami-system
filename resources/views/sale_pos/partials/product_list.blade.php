@forelse($products as $product)
	<div class="col-md-3 col-xs-4 product_list no-print">
		<div class="product_box rr-pos-tone-{{ ($product->category_id ?: $product->product_id) % 6 }} hover:tw-shadow-lg hover:tw-animate-pulse" data-variation_id="{{$product->id}}" title="{{$product->name}} @if($product->type == 'variable')- {{$product->variation}} @endif {{ '(' . $product->sub_sku . ')'}} @if(!empty($show_prices)) @lang('lang_v1.default') - @format_currency($product->selling_price) @foreach($product->group_prices as $group_price) @if(array_key_exists($group_price->price_group_id, $allowed_group_prices)) {{$allowed_group_prices[$group_price->price_group_id]}} - @format_currency($group_price->price_inc_tax) @endif @endforeach @endif">

		@php
			$has_image = count($product->media) > 0 || !empty($product->product_image);
		@endphp
		<div class="image-container @if(!$has_image) image-container--empty @endif" 
			style="background-image: url(
					@if(count($product->media) > 0)
						{{$product->media->first()->display_url}}
					@elseif(!empty($product->product_image))
						{{asset('/uploads/img/' . rawurlencode($product->product_image))}}
					@else
						{{asset('/img/default.png')}}
					@endif
				);
			background-repeat: no-repeat; background-position: center;
			background-size: contain;">
			{{-- شاشة الكاشير الجديدة: أول حرف من الاسم بدل الصورة الافتراضية، وكمان لو ملف الصورة مش موجود --}}
			<span class="rr-pos-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($product->name), 0, 1)) }}</span>
			@if($has_image)<img src="@if(count($product->media) > 0){{$product->media->first()->display_url}}@else{{asset('/uploads/img/' . rawurlencode($product->product_image))}}@endif" alt="" hidden onerror="this.parentNode.classList.add('image-container--empty')">@endif
		</div>

		<div class="text_div">
			<small class="text text-muted">{{$product->name}} 
			@if($product->type == 'variable')
				- {{$product->variation}}
			@endif
			</small>

			<small class="text-muted">
				({{$product->sub_sku}})
			</small><br>
			@if(!empty($show_prices) && isset($product->sell_price_inc_tax))
				<span class="rr-pos-price rr-num">@format_currency($product->sell_price_inc_tax)</span>
			@endif
			<small class="text-muted" style="font-size: 60%;">
				@if($product->enable_stock)
				{{ @num_format($product->qty_available) }} {{$product->unit}} @lang('lang_v1.in_stock')
				@else
					--
				@endif
			</small>
		</div>
			
		</div>
	</div>
@empty
	<input type="hidden" id="no_products_found">
	<div class="col-md-12">
		<h4 class="text-center">
			@lang('lang_v1.no_products_to_display')
		</h4>
	</div>
@endforelse
@php
	$modern_total_quantity = collect($receipt_details->lines ?? [])->sum(function ($line) {
		return (float) ($line['quantity_uf'] ?? 0);
	});
	$modern_total_quantity = rtrim(rtrim(number_format($modern_total_quantity, 2, '.', ','), '0'), '.');
	$receipt_details->total_quantity_label = 'عدد المنتجات';
	$receipt_details->total_quantity = $modern_total_quantity;
@endphp

<div class="modern-color-invoice">
	@include('sale_pos.receipts.classic')

	<style>
		.modern-color-invoice {
			direction: rtl;
			font-family: Tahoma, Arial, sans-serif;
		}

		.modern-color-invoice .classic-receipt-print {
			position: relative;
			max-width: 100%;
			margin: 0 auto;
			padding: 24px 26px;
			background: #fff !important;
			color: #26364d !important;
			font-size: 14px;
			line-height: 1.65;
		}

		.modern-color-invoice .classic-receipt-print:before {
			content: '';
			display: block;
			height: 7px;
			margin: -24px -26px 20px;
			border-radius: 14px 14px 0 0;
			background: linear-gradient(90deg, #1769d2 0%, #14a3c7 55%, #0a8b67 100%) !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type {
			margin: 0 0 18px;
			padding: 20px 22px 16px;
			border: 1px solid #dfe8f3;
			border-radius: 16px;
			background: #fff !important;
			box-shadow: 0 8px 28px rgba(29, 66, 119, .07);
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type h2 {
			margin: 4px 0 8px;
			color: #123d91 !important;
			-webkit-text-fill-color: #123d91 !important;
			font-size: 26px;
			font-weight: 800;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type h3 {
			display: inline-block;
			margin: 12px 0 15px;
			padding: 8px 26px;
			border: 1px solid #c9dcf4;
			border-radius: 100px;
			background: #eef7ff !important;
			color: #1769d2 !important;
			-webkit-text-fill-color: #1769d2 !important;
			font-size: 19px;
			font-weight: 800;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type p {
			margin: 4px 0;
			color: #66758b !important;
			-webkit-text-fill-color: #66758b !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type > .col-xs-12.text-center:last-child {
			margin-top: 10px;
			padding: 14px 16px;
			border: 1px solid #e2eaf4;
			border-radius: 12px;
			background: #f7faff !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type > .col-xs-12.text-center:last-child > p {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 24px;
			margin: 0;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type .pull-left {
			float: right !important;
			width: 55%;
			text-align: right !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type .pull-right {
			float: left !important;
			width: 41%;
			text-align: left !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:first-of-type b,
		.modern-color-invoice .classic-receipt-print > .row:first-of-type strong {
			color: #173b70 !important;
			-webkit-text-fill-color: #173b70 !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) {
			margin: 0;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) > .col-xs-12 {
			padding: 0;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) br:first-child {
			display: none;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) table {
			width: 100%;
			margin: 0;
			border: 1px solid #d7e0eb;
			border-radius: 12px;
			border-collapse: separate;
			border-spacing: 0;
			overflow: hidden;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) thead th {
			padding: 11px 10px !important;
			border: 0 !important;
			border-left: 1px solid rgba(255, 255, 255, .2) !important;
			background: #1769d2 !important;
			color: #fff !important;
			-webkit-text-fill-color: #fff !important;
			font-weight: 800 !important;
			white-space: nowrap;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) tbody td {
			padding: 11px 10px !important;
			border: 0 !important;
			border-top: 1px solid #e8eef5 !important;
			background: #fff !important;
			color: #26364d !important;
			-webkit-text-fill-color: #26364d !important;
			vertical-align: top;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(3) tbody tr:nth-child(even) td {
			background: #f8fbff !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) {
			display: flex;
			align-items: flex-start;
			gap: 18px;
			margin: 20px 0 0;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4):before,
		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4):after,
		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-md-12:first-child {
			display: none;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6 {
			float: none;
			width: 50%;
			padding: 0;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6 table {
			width: 100%;
			margin: 0;
			border: 1px solid #dfe7f0;
			border-radius: 12px;
			border-collapse: separate;
			border-spacing: 0;
			overflow: hidden;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6 th,
		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6 td {
			padding: 9px 11px !important;
			border: 0 !important;
			border-top: 1px solid #e8eef5 !important;
			background: #fff !important;
			color: #405671 !important;
			-webkit-text-fill-color: #405671 !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6:nth-of-type(3) tbody > tr:first-child th,
		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6:nth-of-type(3) tbody > tr:first-child td {
			border-top: 0 !important;
			background: #eef7ff !important;
			color: #1769d2 !important;
			-webkit-text-fill-color: #1769d2 !important;
			font-size: 16px;
			font-weight: 800 !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6:nth-of-type(3) tbody > tr:last-child th,
		.modern-color-invoice .classic-receipt-print > .row:nth-of-type(4) > .col-xs-6:nth-of-type(3) tbody > tr:last-child td {
			border-top: 2px solid #8bd8b9 !important;
			background: #eafaf4 !important;
			color: #087453 !important;
			-webkit-text-fill-color: #087453 !important;
			font-size: 17px;
			font-weight: 800 !important;
		}

		.modern-color-invoice .classic-receipt-print > .row:last-of-type {
			margin-top: 20px;
			padding-top: 13px;
			border-top: 1px solid #e3eaf2;
			color: #7d8da2 !important;
			-webkit-text-fill-color: #7d8da2 !important;
		}

		@media print {
			@page {
				size: A4 portrait;
				margin: 9mm;
			}

			.print_section .modern-color-invoice .classic-receipt-print,
			.print_section .modern-color-invoice .classic-receipt-print * {
				-webkit-print-color-adjust: exact !important;
				print-color-adjust: exact !important;
			}

			.print_section .modern-color-invoice .classic-receipt-print {
				padding: 0;
				font-size: 12px;
			}

			.print_section .modern-color-invoice .classic-receipt-print:before {
				margin: 0 0 5mm;
				border-radius: 0;
			}

			.print_section .modern-color-invoice .classic-receipt-print > .row:first-of-type {
				box-shadow: none;
			}

			.print_section .modern-color-invoice .classic-receipt-print table,
			.print_section .modern-color-invoice .classic-receipt-print thead,
			.print_section .modern-color-invoice .classic-receipt-print tbody,
			.print_section .modern-color-invoice .classic-receipt-print tr,
			.print_section .modern-color-invoice .classic-receipt-print th,
			.print_section .modern-color-invoice .classic-receipt-print td {
				-webkit-print-color-adjust: exact !important;
				print-color-adjust: exact !important;
			}

			.print_section .modern-color-invoice .classic-receipt-print tr {
				break-inside: avoid;
				page-break-inside: avoid;
			}
		}
	</style>
</div>

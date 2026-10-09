@if(empty($pdfMode))
@font-face{font-family:"IBM Plex Sans Arabic";src:url("{{ route('construction.print-font', 'regular') }}") format("truetype");font-weight:400;font-style:normal}
@font-face{font-family:"IBM Plex Sans Arabic";src:url("{{ route('construction.print-font', 'medium') }}") format("truetype");font-weight:500;font-style:normal}
@font-face{font-family:"IBM Plex Sans Arabic";src:url("{{ route('construction.print-font', 'semibold') }}") format("truetype");font-weight:600;font-style:normal}
@font-face{font-family:"IBM Plex Sans Arabic";src:url("{{ route('construction.print-font', 'bold') }}") format("truetype");font-weight:700;font-style:normal}
@endif
html,body,body *{font-family:"IBM Plex Sans Arabic",sans-serif!important}
body{font-size:13.5px!important;line-height:1.8!important}
.print-company-name{font-size:18px!important;font-weight:700!important;line-height:1.4!important}
.print-company-contact{font-size:13px!important;line-height:1.6!important}
.print-document-title{font-size:22px!important;font-weight:700!important;line-height:1.4!important}
.print-document-reference{font-size:13px!important;font-weight:600!important}
.print-section-title{font-size:15px!important;font-weight:700!important;line-height:1.5!important}
.print-label{font-size:13px!important;font-weight:500!important;line-height:1.55!important}
.print-value{font-size:13.5px!important;font-weight:600!important;line-height:1.6!important}
.print-body-text{font-size:13.5px!important;line-height:1.8!important}
.print-table{width:100%;border-collapse:collapse;direction:rtl;table-layout:fixed;line-height:1.6!important}
.print-table thead{display:table-header-group}
.print-table tfoot{display:table-footer-group}
.print-table th,.print-table td{padding:7px 5px!important;vertical-align:middle}
.print-table th{font-size:12.5px!important;font-weight:700!important;line-height:1.55!important}
.print-table td{font-size:12px!important;line-height:1.6!important}
.print-table tr{page-break-inside:avoid;break-inside:avoid}
.print-total,.print-total td{font-size:14px!important;font-weight:700!important;page-break-inside:avoid;break-inside:avoid}
.print-summary td{font-size:13px!important;line-height:1.6!important}
.print-signature,.print-signature td,.print-signature strong{font-size:12.5px!important;page-break-inside:avoid;break-inside:avoid}
.print-signature .print-label{font-size:12px!important}
.print-footer,.print-footer td{font-size:10.5px!important;line-height:1.5!important}
.print-number{direction:ltr;unicode-bidi:isolate;text-align:left;white-space:nowrap}
.print-money{display:inline-flex;align-items:baseline;gap:3px;direction:inherit;white-space:nowrap;unicode-bidi:isolate}
.print-money .sar-currency-icon{display:inline-block;width:.72em;height:.81em;margin-left:2px;vertical-align:-.08em}
.print-money-symbol{margin-left:3px}
.print-money--before .print-money-symbol{margin-left:0;margin-right:3px}
.print-money--before .sar-currency-icon{margin-left:0;margin-right:2px}
.print-keep-together{page-break-inside:avoid;break-inside:avoid}
@if(!empty($pdfMode))
.print-label{font-family:ibmplexsansarabicmedium,sans-serif!important}
.print-value,.print-document-reference{font-family:ibmplexsansarabicsemibold,sans-serif!important}
.print-company-name,.print-document-title,.print-section-title,.print-table th,.print-total,.print-total td{font-family:ibmplexsansarabicbold,sans-serif!important}
@endif

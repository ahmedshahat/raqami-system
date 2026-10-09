<style id="business-dark-mode">
/* Business-selectable dark mode. Included only when theme_color is "dark". */
:root.theme-dark-root {
    color-scheme: dark;
    background: #0f172a !important;
}

body.theme-dark {
    --dark-bg: #0f172a;
    --dark-surface: #172033;
    --dark-surface-raised: #1e293b;
    --dark-surface-hover: #273449;
    --dark-border: #334155;
    --dark-text: #e5e7eb;
    --dark-text-muted: #aeb9ca;
    --dark-accent: #38bdf8;
    background: var(--dark-bg) !important;
    color: var(--dark-text) !important;
}

body.theme-dark main,
body.theme-dark #scrollable-container,
body.theme-dark .content-wrapper,
body.theme-dark .right-side,
body.theme-dark .tw-bg-gray-100 {
    background-color: var(--dark-bg) !important;
    color: var(--dark-text) !important;
}

body.theme-dark .side-bar,
body.theme-dark .main-sidebar,
body.theme-dark .sidebar,
body.theme-dark .sidebar-menu,
body.theme-dark .sidebar-menu .treeview-menu {
    background-color: #111827 !important;
}

body.theme-dark .side-bar > a:first-of-type,
body.theme-dark > .thetop > main > div:first-child,
body.theme-dark .tw-from-dark-800 {
    background-color: #172033 !important;
    background-image: linear-gradient(to right, #172033, #0f172a) !important;
}

body.theme-dark [class*="tw-bg-dark-800"] {
    background-color: #1e293b !important;
}

body.theme-dark [class*="hover:tw-bg-dark-700"]:hover {
    background-color: #334155 !important;
}

/* Tailwind utilities used by the dashboard and the generated sidebar. */
body.theme-dark .tw-text-gray-900,
body.theme-dark .tw-text-gray-800,
body.theme-dark .tw-text-gray-700,
body.theme-dark .tw-text-gray-600 {
    color: var(--dark-text) !important;
}

body.theme-dark .tw-text-gray-500,
body.theme-dark .tw-text-gray-400 {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .tw-ring-gray-200,
body.theme-dark .tw-border-gray-200,
body.theme-dark .tw-border-gray-300 {
    --tw-ring-color: var(--dark-border) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark #side-bar {
    background-color: #111827 !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark #side-bar > div,
body.theme-dark #side-bar .chiled,
body.theme-dark #side-bar .tw-bg-gray-200 {
    background-color: transparent !important;
}

body.theme-dark #side-bar > div.tw-bg-gray-200 {
    background-color: var(--dark-surface-raised) !important;
}

body.theme-dark #side-bar a,
body.theme-dark #side-bar a:hover,
body.theme-dark #side-bar a:focus,
body.theme-dark #side-bar svg {
    color: var(--dark-text) !important;
}

body.theme-dark #side-bar a:hover,
body.theme-dark #side-bar a:focus {
    background-color: var(--dark-surface-hover) !important;
}

body.theme-dark #side-bar .chiled a.tw-text-primary-700,
body.theme-dark #side-bar .chiled a:hover {
    color: #7dd3fc !important;
}

body.theme-dark .box,
body.theme-dark .panel,
body.theme-dark .card,
body.theme-dark .info-box,
body.theme-dark .small-box,
body.theme-dark .nav-tabs-custom,
body.theme-dark .well,
body.theme-dark .jumbotron,
body.theme-dark .modal-content,
body.theme-dark .popover,
body.theme-dark .dropdown-menu,
body.theme-dark .select2-dropdown,
body.theme-dark .select2-container--default .select2-selection--single,
body.theme-dark .select2-container--default .select2-selection--multiple,
body.theme-dark .dataTables_wrapper,
body.theme-dark .tw-bg-white {
    background-color: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .nav-tabs-custom > .tab-content,
body.theme-dark .tab-content,
body.theme-dark .tab-pane,
body.theme-dark .dataTables_scroll,
body.theme-dark .dataTables_scrollHead,
body.theme-dark .dataTables_scrollBody,
body.theme-dark .table-responsive {
    background-color: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .nav-tabs-custom > .nav-tabs > li.active > a,
body.theme-dark .nav-tabs-custom > .nav-tabs > li.active:hover > a,
body.theme-dark .nav-tabs > li.active > a,
body.theme-dark .nav-tabs > li.active > a:focus,
body.theme-dark .nav-tabs > li.active > a:hover {
    background-color: var(--dark-surface) !important;
    color: #fff !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .nav-tabs-custom > .nav-tabs > li:not(.active) > a:hover {
    background-color: var(--dark-surface-hover) !important;
    color: #fff !important;
}

body.theme-dark .box-header,
body.theme-dark .box-footer,
body.theme-dark .panel-heading,
body.theme-dark .panel-footer,
body.theme-dark .modal-header,
body.theme-dark .modal-footer,
body.theme-dark .nav-tabs,
body.theme-dark .table > thead > tr > th,
body.theme-dark table.dataTable thead th,
body.theme-dark .box-table thead th {
    background-color: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark h1,
body.theme-dark h2,
body.theme-dark h3,
body.theme-dark h4,
body.theme-dark h5,
body.theme-dark h6,
body.theme-dark .content-header > h1,
body.theme-dark .content-header > h1 > small {
    color: var(--dark-text) !important;
}

body.theme-dark .table,
body.theme-dark table.dataTable,
body.theme-dark .table > tbody > tr > td,
body.theme-dark .table > tbody > tr > th,
body.theme-dark .table > tfoot > tr > td,
body.theme-dark .table > tfoot > tr > th,
body.theme-dark .table > thead > tr > td,
body.theme-dark .table > thead > tr > th {
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .table-striped > tbody > tr:nth-of-type(odd),
body.theme-dark table.dataTable.stripe tbody tr.odd,
body.theme-dark table.dataTable.display tbody tr.odd {
    background-color: rgba(30, 41, 59, .72) !important;
}

body.theme-dark .table > tfoot > tr > td,
body.theme-dark .table > tfoot > tr > th,
body.theme-dark table.dataTable > tfoot > tr > td,
body.theme-dark table.dataTable > tfoot > tr > th,
body.theme-dark .footer-total > td,
body.theme-dark .footer-total > th {
    background-color: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .footer-total .text-muted,
body.theme-dark .footer-total small,
body.theme-dark .footer-total p {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .table-hover > tbody > tr:hover,
body.theme-dark table.dataTable.hover tbody tr:hover,
body.theme-dark table.dataTable.display tbody tr:hover,
body.theme-dark .dropdown-menu > li > a:hover,
body.theme-dark .select2-results__option--highlighted,
body.theme-dark .select2-results__option[aria-selected="true"] {
    background-color: var(--dark-surface-hover) !important;
    color: #fff !important;
}

body.theme-dark .form-control,
body.theme-dark input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),
body.theme-dark textarea,
body.theme-dark select,
body.theme-dark .input-group-addon,
body.theme-dark .select2-search__field,
body.theme-dark .bootstrap-tagsinput {
    background-color: #111827 !important;
    color: var(--dark-text) !important;
    border-color: #475569 !important;
}

body.theme-dark .form-control[disabled],
body.theme-dark .form-control[readonly],
body.theme-dark fieldset[disabled] .form-control,
body.theme-dark .select2-container--default.select2-container--disabled .select2-selection--single {
    background-color: #263247 !important;
    color: #94a3b8 !important;
}

body.theme-dark .form-control:focus,
body.theme-dark input:focus,
body.theme-dark textarea:focus,
body.theme-dark select:focus {
    border-color: var(--dark-accent) !important;
    box-shadow: 0 0 0 1px rgba(56, 189, 248, .35) !important;
}

body.theme-dark .form-control::placeholder,
body.theme-dark input::placeholder,
body.theme-dark textarea::placeholder {
    color: #7f8da3 !important;
}

body.theme-dark label,
body.theme-dark legend,
body.theme-dark .box-title,
body.theme-dark .modal-title,
body.theme-dark .control-label,
body.theme-dark .select2-selection__rendered,
body.theme-dark .pagination > li > a,
body.theme-dark .pagination > li > span,
body.theme-dark .dropdown-menu > li > a,
body.theme-dark .nav-tabs > li > a,
body.theme-dark .breadcrumb > li,
body.theme-dark .breadcrumb > li > a {
    color: var(--dark-text) !important;
}

body.theme-dark .help-block,
body.theme-dark .text-muted,
body.theme-dark small,
body.theme-dark .dataTables_info,
body.theme-dark .dataTables_length,
body.theme-dark .dataTables_filter,
body.theme-dark .select2-results__option {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .pagination > li > a,
body.theme-dark .pagination > li > span,
body.theme-dark .breadcrumb,
body.theme-dark .list-group-item {
    background-color: var(--dark-surface-raised) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .btn-default,
body.theme-dark .daterangepicker,
body.theme-dark .datepicker,
body.theme-dark .datepicker table tr td,
body.theme-dark .datepicker table tr th,
body.theme-dark .ui-autocomplete,
body.theme-dark .sweet-alert,
body.theme-dark .swal2-popup {
    background-color: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .daterangepicker .calendar-table,
body.theme-dark .daterangepicker td.off,
body.theme-dark .daterangepicker td.off.in-range,
body.theme-dark .daterangepicker td.off.start-date,
body.theme-dark .daterangepicker td.off.end-date {
    background-color: var(--dark-surface-raised) !important;
    color: var(--dark-text-muted) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .daterangepicker td.available:hover,
body.theme-dark .daterangepicker th.available:hover,
body.theme-dark .datepicker table tr td.day:hover,
body.theme-dark .ui-menu-item:hover {
    background-color: var(--dark-surface-hover) !important;
    color: #fff !important;
}

/* Highcharts is rendered with inline light colors, so SVG parts need explicit overrides. */
body.theme-dark .highcharts-background {
    fill: var(--dark-surface) !important;
}

body.theme-dark .highcharts-plot-background,
body.theme-dark .highcharts-plot-border {
    fill: transparent !important;
    stroke: var(--dark-border) !important;
}

body.theme-dark .highcharts-grid-line,
body.theme-dark .highcharts-axis-line,
body.theme-dark .highcharts-tick {
    stroke: var(--dark-border) !important;
}

body.theme-dark .highcharts-axis-labels text,
body.theme-dark .highcharts-axis-title,
body.theme-dark .highcharts-legend-item text,
body.theme-dark .highcharts-title,
body.theme-dark .highcharts-subtitle {
    fill: var(--dark-text-muted) !important;
    color: var(--dark-text-muted) !important;
}

body.theme-dark .highcharts-contextbutton .highcharts-button-box {
    fill: var(--dark-surface-raised) !important;
}

body.theme-dark .highcharts-menu {
    background: var(--dark-surface-raised) !important;
    border-color: var(--dark-border) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .35) !important;
}

body.theme-dark .highcharts-menu-item {
    color: var(--dark-text) !important;
}

body.theme-dark .highcharts-menu-item:hover {
    background: var(--dark-surface-hover) !important;
}

body.theme-dark .pagination > .active > a,
body.theme-dark .pagination > .active > span {
    background-color: #0369a1 !important;
    border-color: #0284c7 !important;
    color: #fff !important;
}

body.theme-dark hr,
body.theme-dark .nav-tabs,
body.theme-dark .box-header.with-border,
body.theme-dark .modal-header,
body.theme-dark .modal-footer {
    border-color: var(--dark-border) !important;
}

body.theme-dark a:not(.btn):not([class*="tw-text-white"]) {
    color: #7dd3fc;
}

body.theme-dark .alert,
body.theme-dark .callout,
body.theme-dark .label,
body.theme-dark .badge,
body.theme-dark .btn,
body.theme-dark [class*="bg-"]:not(.tw-bg-white):not(.tw-bg-gray-100) {
    color: #fff;
}

@media print {
    :root.theme-dark-root,
    body.theme-dark,
    body.theme-dark * {
        color-scheme: light;
    }

    body.theme-dark,
    body.theme-dark main,
    body.theme-dark #scrollable-container,
    body.theme-dark .content-wrapper,
    body.theme-dark .box,
    body.theme-dark .panel,
    body.theme-dark .modal-content,
    body.theme-dark .table,
    body.theme-dark table {
        background: #fff !important;
        color: #111 !important;
    }

    :root.theme-dark-root,
    body.theme-dark {
        background: #fff !important;
    }

    body.theme-dark .print_section,
    body.theme-dark .print_section * {
        color: #000 !important;
        text-shadow: none !important;
        box-shadow: none !important;
        filter: none !important;
    }

    body.theme-dark .print_section,
    body.theme-dark .print_section .invoice,
    body.theme-dark .print_section .table-responsive,
    body.theme-dark .print_section table,
    body.theme-dark .print_section thead,
    body.theme-dark .print_section tbody,
    body.theme-dark .print_section tfoot,
    body.theme-dark .print_section tr,
    body.theme-dark .print_section th,
    body.theme-dark .print_section td,
    body.theme-dark .print_section .bg-gray,
    body.theme-dark .print_section .bg-green,
    body.theme-dark .print_section .well,
    body.theme-dark .print_section .panel,
    body.theme-dark .print_section .box {
        background: #fff !important;
        background-color: #fff !important;
        border-color: #999 !important;
    }

    body.theme-dark .print_section img,
    body.theme-dark .print_section svg,
    body.theme-dark .print_section canvas {
        background: transparent !important;
        filter: none !important;
    }

    body.theme-dark .print_section a,
    body.theme-dark .print_section .text-muted,
    body.theme-dark .print_section small,
    body.theme-dark .print_section strong,
    body.theme-dark .print_section b {
        color: #000 !important;
    }
}
</style>

<style id="business-dark-mode-modules">
/* Shared navigation used by Accounting, Manufacturing, Gym, Project, ZATCA and Superadmin. */
body.theme-dark nav.navbar-default,
body.theme-dark nav.navbar,
body.theme-dark nav[class*="!tw-bg-white"],
body.theme-dark nav.bg-white {
    background: var(--dark-surface) !important;
    border-color: var(--dark-border) !important;
    color: var(--dark-text) !important;
}

body.theme-dark nav.navbar-default .navbar-brand,
body.theme-dark nav.navbar-default .navbar-nav > li > a,
body.theme-dark nav.navbar .navbar-brand,
body.theme-dark nav.navbar .navbar-nav > li > a {
    color: var(--dark-text-muted) !important;
}

body.theme-dark nav.navbar-default .navbar-nav > .active > a,
body.theme-dark nav.navbar-default .navbar-nav > .active > a:hover,
body.theme-dark nav.navbar-default .navbar-nav > li > a:hover,
body.theme-dark nav.navbar-default .navbar-nav > li > a:focus,
body.theme-dark nav.navbar .navbar-nav > .active > a,
body.theme-dark nav.navbar .navbar-nav > li > a:hover {
    background: var(--dark-surface-hover) !important;
    color: #fff !important;
}

body.theme-dark nav.navbar-default .navbar-toggle,
body.theme-dark nav.navbar .navbar-toggle {
    background: var(--dark-surface-raised) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark nav.navbar-default .navbar-toggle .icon-bar,
body.theme-dark nav.navbar .navbar-toggle .icon-bar {
    background-color: var(--dark-text) !important;
}

body.theme-dark .tw-text-black,
body.theme-dark .text-black {
    color: var(--dark-text) !important;
}

/* Tabbed module settings. */
body.theme-dark .pos-tab-container,
body.theme-dark .pos-tab-menu,
body.theme-dark .pos-tab,
body.theme-dark .pos-tab-content,
body.theme-dark .pos-tab-menu .list-group,
body.theme-dark .pos-tab-menu .list-group-item {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .pos-tab-menu .list-group-item:hover,
body.theme-dark .pos-tab-menu .list-group-item:focus {
    background: var(--dark-surface-hover) !important;
}

body.theme-dark .pos-tab-menu .list-group-item.active {
    background: #0369a1 !important;
    color: #fff !important;
}

/* WooCommerce dashboard and settings. */
body.theme-dark .wc-page {
    --wc-bg: var(--dark-bg);
    --wc-ink: var(--dark-text);
    --wc-muted: var(--dark-text-muted);
    --wc-line: var(--dark-border);
    background: var(--dark-bg) !important;
    color: var(--dark-text) !important;
}

body.theme-dark .wc-hero,
body.theme-dark .wc-panel,
body.theme-dark .wc-tabs,
body.theme-dark .wc-settings .pos-tab-menu,
body.theme-dark .wc-settings .pos-tab-content,
body.theme-dark .wc-settings .wc-settings-save {
    background: var(--dark-surface) !important;
    border-color: var(--dark-border) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .22) !important;
}

body.theme-dark .wc-panel__header,
body.theme-dark .wc-danger-zone,
body.theme-dark .wc-hero__copy {
    border-color: var(--dark-border) !important;
}

body.theme-dark .wc-hero h1,
body.theme-dark .wc-panel__header h2,
body.theme-dark .wc-panel__body p,
body.theme-dark .wc-sync-meta,
body.theme-dark .wc-danger-zone small,
body.theme-dark .wc-settings .form-group label,
body.theme-dark .wc-settings .help-block,
body.theme-dark .wc-tax-table td,
body.theme-dark .wc-data-table td,
body.theme-dark .wc-log-panel table.dataTable tbody td {
    color: var(--dark-text) !important;
}

body.theme-dark .wc-tabs a,
body.theme-dark .wc-eyebrow,
body.theme-dark .wc-panel__eyebrow {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .wc-tabs a:hover,
body.theme-dark .wc-action--soft,
body.theme-dark .wc-empty-note {
    background: var(--dark-surface-hover) !important;
    color: #bae6fd !important;
}

body.theme-dark .wc-tax-table th,
body.theme-dark .wc-data-table th,
body.theme-dark .wc-settings .table th,
body.theme-dark .wc-log-panel table.dataTable thead th {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

/* Bosta Shipping dashboard, shipment forms and shipment tables. */
body.theme-dark .bs-page {
    --bs-navy: var(--dark-text);
    --bs-muted: var(--dark-text-muted);
    --bs-line: var(--dark-border);
    --bs-bg: var(--dark-bg);
    --bs-shadow: 0 10px 30px rgba(0, 0, 0, .22);
    background: var(--dark-bg) !important;
    color: var(--dark-text) !important;
}

body.theme-dark .bs-hero,
body.theme-dark .bs-create-hero,
body.theme-dark .bs-hero__status,
body.theme-dark .bs-tabs,
body.theme-dark .bs-kpi,
body.theme-dark .bs-insight,
body.theme-dark .bs-panel,
body.theme-dark .bs-form-card,
body.theme-dark .bs-summary-card,
body.theme-dark .bs-switch,
body.theme-dark .bs-connection-card,
body.theme-dark .bs-tips-grid > div,
body.theme-dark .bs-bulk-bar,
body.theme-dark .bs-filter-panel,
body.theme-dark .bs-invoice-chip {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
    box-shadow: var(--bs-shadow) !important;
}

body.theme-dark .bs-hero__copy,
body.theme-dark .bs-create-hero__brand,
body.theme-dark .bs-panel > header,
body.theme-dark .bs-form-card > header,
body.theme-dark .bs-summary-logo,
body.theme-dark .bs-summary-card dl > div,
body.theme-dark .bs-submit-note,
body.theme-dark .bs-insight > div,
body.theme-dark .bs-recent-list > a,
body.theme-dark .bs-sales-filter,
body.theme-dark .bs-bulk-bar {
    border-color: var(--dark-border) !important;
}

body.theme-dark .bs-hero__copy h1,
body.theme-dark .bs-create-hero__copy h1,
body.theme-dark .bs-panel > header h2,
body.theme-dark .bs-form-card > header h2,
body.theme-dark .bs-kpi strong,
body.theme-dark .bs-insight strong,
body.theme-dark .bs-status-row b,
body.theme-dark .bs-summary-logo strong,
body.theme-dark .bs-summary-card dd,
body.theme-dark .bs-bulk-bar strong,
body.theme-dark .bs-tips-grid strong,
body.theme-dark .bs-settings-form strong {
    color: var(--dark-text) !important;
}

body.theme-dark .bs-hero__copy p,
body.theme-dark .bs-create-hero__copy p,
body.theme-dark .bs-kpi small,
body.theme-dark .bs-kpi p,
body.theme-dark .bs-insight span,
body.theme-dark .bs-recent-list small,
body.theme-dark .bs-settings-form small,
body.theme-dark .bs-form-grid small,
body.theme-dark .bs-summary-card dt,
body.theme-dark .bs-submit-note,
body.theme-dark .bs-tips-grid p,
body.theme-dark .bs-empty {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .bs-input-wrap > i,
body.theme-dark .bs-money-input span,
body.theme-dark .bs-checkbox-placeholder,
body.theme-dark .bs-order-date,
body.theme-dark .bs-bulk-bar > div,
body.theme-dark .bs-cancel-link {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .bs-tabs a,
body.theme-dark .bs-btn--soft,
body.theme-dark .bs-actions a,
body.theme-dark .bs-recent-list em,
body.theme-dark .bs-result-count,
body.theme-dark .bs-sequence {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
}

body.theme-dark .bs-tabs a:hover,
body.theme-dark .bs-actions a:hover,
body.theme-dark .bs-recent-list > a:hover,
body.theme-dark .bs-cancel-link:hover {
    background: var(--dark-surface-hover) !important;
    color: #fff !important;
}

body.theme-dark .bs-tabs a.is-active,
body.theme-dark .bs-btn--primary,
body.theme-dark .bs-submit {
    background: var(--bs-red) !important;
    color: #fff !important;
}

body.theme-dark .bs-table th,
body.theme-dark .bs-sales-table th {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .bs-table td,
body.theme-dark .bs-sales-table td {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .bs-table tbody tr:hover td,
body.theme-dark .bs-sales-table tbody tr:hover td {
    background: var(--dark-surface-hover) !important;
}

body.theme-dark .bs-filter label,
body.theme-dark .bs-settings-form label,
body.theme-dark .bs-panel > .form-group label,
body.theme-dark .bs-form-grid label,
body.theme-dark .bs-sales-filter label {
    color: var(--dark-text) !important;
}

body.theme-dark .bs-sales-filter,
body.theme-dark .bs-filter,
body.theme-dark .bs-pagination {
    background: var(--dark-surface) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .bs-progress {
    background: var(--dark-surface-hover) !important;
}

body.theme-dark .bs-state,
body.theme-dark .bs-sent-badge,
body.theme-dark .bs-pending-badge,
body.theme-dark .bs-excluded-badge,
body.theme-dark .bs-btn--muted,
body.theme-dark .bs-recent-mark {
    border-color: var(--dark-border) !important;
    box-shadow: none !important;
    filter: saturate(.85) brightness(.88);
}

body.theme-dark .bs-state:not(.bs-state--delivered):not(.bs-state--returned_to_business):not(.bs-state--exception):not(.bs-state--lost):not(.bs-state--damaged):not(.bs-state--canceled):not(.bs-state--terminated) {
    background: #183766 !important;
    color: #9bc2ff !important;
}

body.theme-dark .bs-state--delivered,
body.theme-dark .bs-state--returned_to_business,
body.theme-dark .bs-sent-badge {
    background: #123c32 !important;
    color: #8fe0c3 !important;
}

body.theme-dark .bs-state--exception,
body.theme-dark .bs-state--lost,
body.theme-dark .bs-state--damaged,
body.theme-dark .bs-state--canceled,
body.theme-dark .bs-state--terminated {
    background: #4a2029 !important;
    color: #ffadb9 !important;
}

body.theme-dark .bs-pending-badge {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text-muted) !important;
}

body.theme-dark .bs-excluded-badge,
body.theme-dark .bs-btn--muted {
    background: #493718 !important;
    color: #f1ce83 !important;
}

body.theme-dark .bs-inline-notice,
body.theme-dark .bs-location-callout,
body.theme-dark .bs-readiness,
body.theme-dark .bs-alert {
    border-color: var(--dark-border) !important;
    filter: saturate(.85) brightness(.86);
}

/* Construction: projects, quotations, measurements and certificates. */
body.theme-dark .ct-project-workspace {
    --pw-ink: var(--dark-text);
    --pw-muted: var(--dark-text-muted);
    --pw-line: var(--dark-border);
    color: var(--dark-text) !important;
}

body.theme-dark .ct-project-hero,
body.theme-dark .ct-project-panel,
body.theme-dark .ct-project-action,
body.theme-dark .ct-project-stat,
body.theme-dark .ct-quote-heading,
body.theme-dark .ct-quote-card,
body.theme-dark .ct-measurement-card,
body.theme-dark .ct-certificate-summary > div,
body.theme-dark .ct-quote-menu,
body.theme-dark .ct-quote-view,
body.theme-dark .ct-quote-menu-toggle,
body.theme-dark .ct-project-step__number,
body.theme-dark .ct-quote-btn:not(.ct-quote-btn--primary):not(.ct-quote-btn--success) {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .18) !important;
}

body.theme-dark .ct-quote-page,
body.theme-dark .ct-quote-heading h2,
body.theme-dark .ct-quote-card h3,
body.theme-dark .ct-quote-modal .modal-title,
body.theme-dark .ct-project-workspace h1,
body.theme-dark .ct-project-workspace h2,
body.theme-dark .ct-project-workspace h3,
body.theme-dark .ct-certificate-summary strong,
body.theme-dark .ct-certificate-financial strong,
body.theme-dark .ct-certificate-actions p,
body.theme-dark .ct-quote-fact strong,
body.theme-dark .ct-quote-menu a,
body.theme-dark .ct-quote-menu button {
    color: var(--dark-text) !important;
}

body.theme-dark .ct-quote-heading p,
body.theme-dark .ct-quote-fact small,
body.theme-dark .ct-certificate-summary small,
body.theme-dark .ct-certificate-form small,
body.theme-dark .ct-certificate-financial > div,
body.theme-dark .ct-certificate-form label {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .ct-quote-fact,
body.theme-dark .ct-project-code,
body.theme-dark .ct-project-button--quiet,
body.theme-dark .ct-quote-modal .modal-header {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .ct-quote-table th,
body.theme-dark .ct-quote-entry th,
body.theme-dark .ct-quote-table tfoot td {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .ct-quote-table td,
body.theme-dark .ct-quote-entry td,
body.theme-dark .ct-certificate-financial > div {
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .ct-quote-table tr:hover td,
body.theme-dark .ct-quote-menu a:hover,
body.theme-dark .ct-quote-menu button:hover {
    background: var(--dark-surface-hover) !important;
}

/* Stocktaking has its own late inline stylesheet. */
body.theme-dark .stocktaking-theme,
body.theme-dark .stocktake-workspace {
    --stk-text: var(--dark-text);
    --stk-muted: var(--dark-text-muted);
    --stk-border: var(--dark-border);
    color: var(--dark-text) !important;
}

body.theme-dark .stk-stat-card,
body.theme-dark .stk-panel,
body.theme-dark .stk-card,
body.theme-dark .stk-quick-link,
body.theme-dark .stk-modal .modal-body,
body.theme-dark .stk-more-button,
body.theme-dark .stk-menu-action,
body.theme-dark .stk-modal-cancel,
body.theme-dark .stk-summary-box,
body.theme-dark .stk-search-area,
body.theme-dark .stk-result-row,
body.theme-dark .stk-manage-entry,
body.theme-dark .stk-manage-modal .modal-body,
body.theme-dark .stk-review-detail td,
body.theme-dark .rv-card,
body.theme-dark .rv-topbar,
body.theme-dark .rv-stat,
body.theme-dark .rv-side,
body.theme-dark .rv-finalize-body,
body.theme-dark .rv-user {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
    box-shadow: 0 7px 22px rgba(0, 0, 0, .18) !important;
}

body.theme-dark .stk-table td,
body.theme-dark .stk-table th,
body.theme-dark .rv-table td,
body.theme-dark .rv-table th {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .stk-table th,
body.theme-dark .stk-card-head,
body.theme-dark .stk-closed,
body.theme-dark .rv-table th,
body.theme-dark .rv-card-head,
body.theme-dark .rv-closed {
    background: var(--dark-surface-raised) !important;
}

body.theme-dark .stk-table tr:hover td,
body.theme-dark .stk-quick-link:hover,
body.theme-dark .stk-menu-action:hover,
body.theme-dark .rv-table tr:hover td,
body.theme-dark .rv-filter:hover {
    background: var(--dark-surface-hover) !important;
}

body.theme-dark .stk-session-name,
body.theme-dark .stk-card-title,
body.theme-dark .stk-summary-value,
body.theme-dark .stk-latest-name,
body.theme-dark .stk-product-name,
body.theme-dark .stk-selected-product,
body.theme-dark .rv-card-title,
body.theme-dark .rv-title,
body.theme-dark .rv-product strong,
body.theme-dark .rv-user,
body.theme-dark .rv-confirm {
    color: var(--dark-text) !important;
}

body.theme-dark .stk-session-meta,
body.theme-dark .stk-session-user,
body.theme-dark .stk-summary-label,
body.theme-dark .stk-latest-code,
body.theme-dark .stk-latest-time,
body.theme-dark .stk-search-help,
body.theme-dark .stk-result-label,
body.theme-dark .stk-product-variant,
body.theme-dark .rv-product-variant,
body.theme-dark .rv-user-title,
body.theme-dark .rv-stat-label,
body.theme-dark .rv-finalize-text {
    color: var(--dark-text-muted) !important;
}

body.theme-dark .stk-search-input,
body.theme-dark .stk-quantity,
body.theme-dark .stk-manage-qty,
body.theme-dark .rv-search,
body.theme-dark .rv-filter {
    background: #111827 !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .stk-status-banner,
body.theme-dark .stk-selection-required,
body.theme-dark .stk-no-result,
body.theme-dark .rv-notice,
body.theme-dark .rv-tip {
    border-color: var(--dark-border) !important;
    filter: saturate(.8) brightness(.82);
}

body.theme-dark .stk-more-actions .dropdown-menu::after {
    background: var(--dark-surface) !important;
    border-color: var(--dark-border) !important;
}

/* Other module-specific reusable surfaces. */
body.theme-dark .card-body,
body.theme-dark .widget-user,
body.theme-dark .box-solid,
body.theme-dark .timeline-item,
body.theme-dark .invoice:not(.print_section),
body.theme-dark .callout:not(.callout-danger):not(.callout-warning):not(.callout-success):not(.callout-info) {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .close {
    color: #fff !important;
    opacity: .8 !important;
    text-shadow: none !important;
}

/* Sale invoice details modal. */
body.theme-dark .sale-view-modal .modal-content,
body.theme-dark .sale-view-modal .modal-body {
    background: var(--dark-surface) !important;
    color: var(--dark-text) !important;
}

body.theme-dark .sale-view-modal .modal-header,
body.theme-dark .sale-view-modal .modal-footer {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .sale-view-modal .table,
body.theme-dark .sale-view-modal .table-responsive,
body.theme-dark .sale-view-modal .table.bg-gray,
body.theme-dark .sale-view-modal .table > tbody > tr:not(.bg-green) > td,
body.theme-dark .sale-view-modal .table > tbody > tr:not(.bg-green) > th {
    background: var(--dark-surface-raised) !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .sale-view-modal .table > tbody > tr.bg-green > th,
body.theme-dark .sale-view-modal .table > thead > tr.bg-green > th {
    background: #047857 !important;
    color: #fff !important;
    border-color: #059669 !important;
}

body.theme-dark .sale-view-modal .well.bg-gray,
body.theme-dark .sale-view-modal .panel-body {
    background: #111827 !important;
    color: var(--dark-text) !important;
    border-color: var(--dark-border) !important;
}

body.theme-dark .sale-view-modal .text-muted,
body.theme-dark .sale-view-modal small {
    color: var(--dark-text-muted) !important;
}

@media print {
    body.theme-dark nav.navbar-default,
    body.theme-dark .wc-page,
    body.theme-dark .ct-project-hero,
    body.theme-dark .ct-project-panel,
    body.theme-dark .ct-quote-heading,
    body.theme-dark .ct-quote-card,
    body.theme-dark .stk-card,
    body.theme-dark .stk-panel {
        background: #fff !important;
        color: #111 !important;
        border-color: #ddd !important;
        box-shadow: none !important;
    }
}
</style>

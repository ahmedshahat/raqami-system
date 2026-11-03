@if(!empty($__subscription) && env('APP_ENV') != 'demo')

<details class="subscription-dropdown">

    <summary
        class="subscription-details-btn"
        title="تفاصيل الباقة"
        aria-label="عرض تفاصيل الباقة">

        <i class="fas fa-box-open" aria-hidden="true"></i>

        <span class="responsive-header-btn-text">
            تفاصيل الباقة
        </span>
    </summary>

    <div class="subscription-dropdown-menu">

        <div class="subscription-dropdown-header">
            <i class="fas fa-box-open" aria-hidden="true"></i>

            <span>تفاصيل الحزمة النشطة</span>
        </div>

        <div class="subscription-package-name">
            {{ $__subscription->package_details['name'] }}
        </div>

        <div class="subscription-package-dates">
            <i class="far fa-calendar-alt"></i>

            <span>
                {{ @format_date($__subscription->start_date) }}
                -
                {{ @format_date($__subscription->end_date) }}
            </span>
        </div>

        <div class="subscription-features">

            <div class="subscription-feature-row">
                <span class="subscription-check">
                    <i class="fas fa-check"></i>
                </span>

                <span>
                    @if($__subscription->package_details['location_count'] == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $__subscription->package_details['location_count'] }}
                    @endif

                    @lang('business.business_locations')
                </span>
            </div>

            <div class="subscription-feature-row">
                <span class="subscription-check">
                    <i class="fas fa-check"></i>
                </span>

                <span>
                    @if($__subscription->package_details['user_count'] == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $__subscription->package_details['user_count'] }}
                    @endif

                    @lang('superadmin::lang.users')
                </span>
            </div>

            <div class="subscription-feature-row">
                <span class="subscription-check">
                    <i class="fas fa-check"></i>
                </span>

                <span>
                    @if($__subscription->package_details['product_count'] == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $__subscription->package_details['product_count'] }}
                    @endif

                    @lang('superadmin::lang.products')
                </span>
            </div>

            <div class="subscription-feature-row">
                <span class="subscription-check">
                    <i class="fas fa-check"></i>
                </span>

                <span>
                    @if($__subscription->package_details['invoice_count'] == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $__subscription->package_details['invoice_count'] }}
                    @endif

                    @lang('superadmin::lang.invoices')
                </span>
            </div>

        </div>
    </div>
</details>

<style>
    .subscription-dropdown {
        position: relative;
        display: inline-block;
    }

    .subscription-dropdown > summary {
        list-style: none;
    }

    .subscription-dropdown > summary::-webkit-details-marker {
        display: none;
    }

    .subscription-details-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 7px;

        min-height: 36px;
        padding: 7px 13px;

        background-color: #f59e0b !important;
        color: #ffffff !important;

        border: 1px solid rgba(255, 255, 255, 0.45);
        border-radius: 8px;

        box-shadow: 0 2px 7px rgba(0, 0, 0, 0.15);

        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;

        cursor: pointer;
        user-select: none;

        transition: 0.2s ease;
    }

    .subscription-details-btn:hover,
    .subscription-details-btn:focus {
        background-color: #d97706 !important;
        color: #ffffff !important;
        transform: translateY(-1px);
        outline: none;
    }

    .subscription-details-btn i {
        font-size: 18px;
        margin: 0;
    }

    /* القائمة */

    .subscription-dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;

        width: 350px;
        max-width: calc(100vw - 24px);

        padding: 0;

        background-color: #ffffff;
        color: #1f2937;

        border: 1px solid #e5e7eb;
        border-radius: 14px;

        box-shadow:
            0 15px 35px rgba(0, 0, 0, 0.18),
            0 3px 8px rgba(0, 0, 0, 0.08);

        direction: rtl;
        text-align: right;

        overflow: hidden;
        z-index: 99999;
    }

    .subscription-dropdown-menu::before {
        content: "";

        position: absolute;
        top: -7px;
        right: 26px;

        width: 14px;
        height: 14px;

        background-color: #fff7ed;
        border-top: 1px solid #e5e7eb;
        border-left: 1px solid #e5e7eb;

        transform: rotate(45deg);
    }

    .subscription-dropdown-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;

        padding: 14px 16px;

        background-color: #fff7ed;
        color: #9a3412;

        border-bottom: 1px solid #fed7aa;

        font-size: 16px;
        font-weight: 700;
    }

    .subscription-package-name {
        padding: 16px 16px 8px;

        color: #111827;

        font-size: 17px;
        font-weight: 700;
        text-align: center;
    }

    .subscription-package-dates {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;

        padding: 0 16px 14px;

        color: #6b7280;
        font-size: 14px;
        font-weight: 600;

        direction: ltr;
    }

    .subscription-features {
        padding: 0 14px 14px;
    }

    .subscription-feature-row {
        display: flex;
        align-items: center;
        gap: 10px;

        min-height: 48px;
        padding: 10px 6px;

        color: #374151;

        border-top: 1px solid #f0f1f3;

        font-size: 14px;
        font-weight: 600;
    }

    .subscription-check {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        width: 25px;
        height: 25px;
        min-width: 25px;

        background-color: #dcfce7;
        color: #16a34a;

        border-radius: 50%;
        font-size: 12px;
    }

    /* الموبايل */

    @media screen and (max-width: 767px) {

        .subscription-details-btn .responsive-header-btn-text {
            display: none !important;
        }

        .subscription-details-btn {
            width: 44px !important;
            min-width: 44px !important;
            max-width: 44px !important;

            height: 44px !important;
            min-height: 44px !important;

            padding: 0 !important;
            gap: 0 !important;
        }

        .subscription-dropdown-menu {
            position: fixed;

            right: 12px;
            left: 12px;

            width: auto;
            max-width: none;

            max-height: calc(100vh - 24px);
            overflow-y: auto;
        }

        .subscription-dropdown-menu::before {
            display: none;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const dropdowns = document.querySelectorAll('.subscription-dropdown');

    dropdowns.forEach(function (dropdown) {

        dropdown.addEventListener('toggle', function () {

            if (!dropdown.open) {
                return;
            }

            // إغلاق أي قائمة أخرى مفتوحة
            dropdowns.forEach(function (otherDropdown) {
                if (otherDropdown !== dropdown) {
                    otherDropdown.removeAttribute('open');
                }
            });

            // تحديد مكان القائمة على الموبايل
            if (window.innerWidth <= 767) {

                window.requestAnimationFrame(function () {

                    const button = dropdown.querySelector(
                        '.subscription-details-btn'
                    );

                    const menu = dropdown.querySelector(
                        '.subscription-dropdown-menu'
                    );

                    if (!button || !menu) {
                        return;
                    }

                    const buttonRect = button.getBoundingClientRect();

                    let menuTop = buttonRect.bottom + 8;

                    const maximumTop =
                        window.innerHeight -
                        menu.offsetHeight -
                        12;

                    if (menuTop > maximumTop) {
                        menuTop = Math.max(12, maximumTop);
                    }

                    menu.style.top = menuTop + 'px';
                });
            }
        });
    });

    // الإغلاق عند الضغط خارج القائمة
    document.addEventListener('click', function (event) {

        dropdowns.forEach(function (dropdown) {

            if (
                dropdown.open &&
                !dropdown.contains(event.target)
            ) {
                dropdown.removeAttribute('open');
            }
        });
    });

    // الإغلاق عند تغيير حجم الشاشة
    window.addEventListener('resize', function () {

        dropdowns.forEach(function (dropdown) {
            dropdown.removeAttribute('open');
        });
    });
});
</script>

@endif
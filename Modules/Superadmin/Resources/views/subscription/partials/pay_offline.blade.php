
<style>
    .offline-payment-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 24px;
        margin-top: 10px;
    }

    .offline-payment-header {
        margin-bottom: 22px;
    }

    .offline-payment-title {
        margin: 0 0 8px;
        font-size: 19px;
        font-weight: 700;
        color: #1f2937;
    }

    .offline-payment-description {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.8;
    }

    .offline-payment-details {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        color: #374151;
        line-height: 1.9;
    }

    .offline-upload-label {
        display: block;
        margin-bottom: 10px;
        font-weight: 700;
        color: #1f2937;
    }

    .offline-file-input {
        display: none !important;
    }

    .offline-file-button {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 54px;
        width: 100%;
        border: 2px dashed #00a65a;
        border-radius: 10px;
        background: #f0fdf4;
        color: #008d4c;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .offline-file-button:hover {
        background: #dcfce7;
        border-color: #008d4c;
    }

    .offline-file-name {
        display: block;
        margin-top: 9px;
        font-size: 13px;
        color: #6b7280;
        text-align: center;
    }

    .offline-file-help {
        margin: 10px 0 0;
        font-size: 13px;
        color: #6b7280;
        text-align: center;
    }

    .offline-receipt-preview {
        display: none;
        margin-top: 16px;
        text-align: center;
    }

    .offline-receipt-preview img {
        max-width: 240px;
        max-height: 240px;
        object-fit: contain;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 5px;
        background: #ffffff;
    }

    .offline-submit-button {
        width: 100%;
        min-height: 48px;
        margin-top: 22px;
        border: 0;
        border-radius: 9px;
        background: #00a65a;
        color: #ffffff;
        font-size: 16px;
        font-weight: 700;
        transition: background 0.2s ease;
    }

    .offline-submit-button:hover,
    .offline-submit-button:focus {
        background: #008d4c;
        color: #ffffff;
    }

    .offline-submit-button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
</style>

<div class="col-md-12">

    <div class="offline-payment-card">

        <div class="offline-payment-header">
            <h4 class="offline-payment-title">
                <i class="fa fa-university"></i>
                الدفع عن طريق التحويل اليدوي
            </h4>

            <p class="offline-payment-description">
                بعد إتمام التحويل، أرفق صورة واضحة للإيصال ثم أرسل طلب التفعيل للمراجعة.
            </p>
        </div>

        @if(!empty($offline_payment_details))
            <div class="offline-payment-details">
                {!! nl2br(e($offline_payment_details)) !!}
            </div>
        @endif
        
        <form
            id="offline-payment-form-{{ $k }}"
            action="{{ action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'], [$package->id]) }}"
            method="POST"
            enctype="multipart/form-data"
            novalidate
        >
            {{ csrf_field() }}

            <input type="hidden" name="gateway" value="{{ $k }}">
            <input type="hidden" name="price" value="{{ $package->price }}">
            <input
                type="hidden"
                name="coupon_code"
                value="{{ request()->get('code') ?? null }}"
            >

            <div class="form-group">

                <label class="offline-upload-label">
                    إرفاق صورة إيصال التحويل
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="file"
                    name="payment_receipt"
                    id="offline_receipt_{{ $k }}"
                    accept="image/jpeg,image/png,image/webp"
                    class="offline-file-input"
                    
                >

                <label
                    for="offline_receipt_{{ $k }}"
                    class="offline-file-button"
                >
                    <i class="fa fa-cloud-upload"></i>
                    <span>اختيار صورة الإيصال</span>
                </label>

                <span
                    id="offline_file_name_{{ $k }}"
                    class="offline-file-name"
                >
                    لم يتم اختيار صورة
                </span>

                <p class="offline-file-help">
                    الصيغ المسموحة: JPG وPNG وWEBP — الحد الأقصى 5 ميجابايت
                </p>

                <div
                    id="offline_receipt_preview_{{ $k }}"
                    class="offline-receipt-preview"
                >
                    <img
                        src=""
                        alt="معاينة صورة إيصال التحويل"
                    >
                </div>

            </div>

            <button
                type="submit"
                id="offline-submit-button-{{ $k }}"
                class="offline-submit-button"
            >
                <i class="fa fa-paper-plane"></i>
                إرسال طلب التفعيل
            </button>

        </form>

    </div>

</div>

<script>
(function () {
    var fileInput = document.getElementById('offline_receipt_{{ $k }}');
    var fileName = document.getElementById('offline_file_name_{{ $k }}');
    var preview = document.getElementById('offline_receipt_preview_{{ $k }}');
    var previewImage = preview.querySelector('img');
    var form = document.getElementById('offline-payment-form-{{ $k }}');
    var submitButton = document.getElementById('offline-submit-button-{{ $k }}');

    var allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    var maximumSize = 5 * 1024 * 1024;

    function showError(message) {
        if (typeof toastr !== 'undefined') {
            toastr.error(message);
        } else {
            alert(message);
        }
    }

    function resetFileInput() {
        fileInput.value = '';
        fileName.textContent = 'لم يتم اختيار صورة';
        previewImage.src = '';
        preview.style.display = 'none';
    }

    fileInput.addEventListener('change', function (event) {
        var file = event.target.files[0];

        if (!file) {
            resetFileInput();
            return;
        }

        if (allowedTypes.indexOf(file.type) === -1) {
            showError('الصيغ المسموحة هي JPG أو PNG أو WEBP فقط.');
            resetFileInput();
            return;
        }

        if (file.size > maximumSize) {
            showError('حجم الصورة يجب ألا يتجاوز 5 ميجابايت.');
            resetFileInput();
            return;
        }

        fileName.textContent = file.name;

        var reader = new FileReader();

        reader.onload = function (readerEvent) {
            previewImage.src = readerEvent.target.result;
            preview.style.display = 'block';
        };

        reader.readAsDataURL(file);
    });


    form.addEventListener('submit', function (event) {
        if (!fileInput.files || fileInput.files.length === 0) {
            event.preventDefault();
    
           showError('أنت لم تختر صورة التحويل. يرجى اختيار صورة الإيصال أولًا ثم إرسال الطلب');
    
            fileInput.focus();
            return;
        }
    
        submitButton.disabled = true;
        submitButton.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> جارٍ إرسال الطلب...';
    });


})();
</script>
```

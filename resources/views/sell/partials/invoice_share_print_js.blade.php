{{--
    طباعة مجموعة فواتير ومشاركة صورة الفاتورة (واتساب)، مشتركة بين شاشة المبيعات القديمة والجديدة.
    الصفحة لازم يكون فيها: #receipt_section و#bulk_receipt_section ونافذة #invoice_image_share_modal
    (#invoice_image_preview, #invoice_whatsapp_recipient, #invoice_share_help, #download_invoice_image,
    #open_invoice_whatsapp, #share_invoice_image)، ومتغير $invoice_whatsapp_number.
--}}
<script src="{{ asset('js/html2canvas.min.js?v=1.4.1') }}"></script>
<script type="text/javascript">
    var invoiceImageFile = null;
    var invoiceImageUrl = null;
    var invoiceImageInvoiceNo = '';
    var invoiceWhatsappNumber = @json($invoice_whatsapp_number ?? '');

    function fetchInvoiceForBulkPrint(invoice) {
        if (!invoice.url) {
            return Promise.reject(new Error('رابط طباعة الفاتورة رقم ' + invoice.number + ' غير متاح.'));
        }

        return new Promise(function(resolve, reject) {
            $.ajax({
                method: 'GET',
                url: invoice.url,
                dataType: 'json',
                cache: false
            }).done(function(result) {
                if (result.success == 1 && result.receipt && result.receipt.html_content) {
                    resolve(result.receipt.html_content);
                    return;
                }

                reject(new Error(result.msg || 'تعذر تجهيز الفاتورة رقم ' + invoice.number));
            }).fail(function(xhr, textStatus, errorThrown) {
                var statusDetails = xhr.status ? ' (HTTP ' + xhr.status + ')' : '';
                var serverMessage = xhr.responseJSON && xhr.responseJSON.msg
                    ? ': ' + xhr.responseJSON.msg
                    : '';
                reject(new Error('تعذر تحميل الفاتورة رقم ' + invoice.number + statusDetails + serverMessage));
            });
        });
    }

    function waitForInvoiceImages(container) {
        var images = Array.prototype.slice.call(container.querySelectorAll('img'));

        return Promise.all(images.map(function(image) {
            if (image.complete) {
                return Promise.resolve();
            }

            return new Promise(function(resolve) {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            });
        }));
    }

    // invoices: [{id, number, url}] — بيحمّل الفواتير واحدة واحدة ويطبعهم مع فاصل صفحة بين كل فاتورة
    async function printInvoicesBulk(invoices, button) {
        var originalHtml = button.html();
        var bulkReceipt = $('#bulk_receipt_section');
        var invoiceHtml = [];

        button.prop('disabled', true);

        try {
            $('.print_section').not(bulkReceipt).empty();

            for (var index = 0; index < invoices.length; index++) {
                button.html('<i class="fas fa-spinner fa-spin"></i> جارٍ تحميل الفاتورة ' +
                    (index + 1) + ' من ' + invoices.length);
                invoiceHtml.push(await fetchInvoiceForBulkPrint(invoices[index]));
            }

            bulkReceipt.html(invoiceHtml.map(function(html, index) {
                var pageBreakClass = index < invoiceHtml.length - 1 ? ' bulk-invoice-page-break' : '';
                return '<div class="bulk-invoice-page' + pageBreakClass + '">' + html + '</div>';
            }).join(''));
            __currency_convert_recursively(bulkReceipt);
            await waitForInvoiceImages(bulkReceipt.get(0));
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }
            window.addEventListener('afterprint', function() {
                bulkReceipt.empty();
            }, { once: true });
            window.print();
        } catch (error) {
            console.error('Bulk invoice printing failed:', error);
            var message = error && error.message ? error.message : 'تعذر تحميل بعض الفواتير المحددة.';
            toastr.error(message);
        } finally {
            button.html(originalHtml).prop('disabled', false);
        }
    }

    function downloadInvoiceImage() {
        if (!invoiceImageFile || !invoiceImageUrl) {
            return;
        }

        var link = document.createElement('a');
        link.href = invoiceImageUrl;
        link.download = invoiceImageFile.name;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    $(document).on('click', 'a.print-invoice', function() {
        $('#bulk_receipt_section').empty();
    });

    $(document).on('click', '.share-invoice-image', function(event) {
        event.preventDefault();

        var button = $(this);
        var href = button.data('href');
        var invoiceNo = String(button.data('invoice-no') || 'invoice').replace(/[\\/:*?"<>|]/g, '-');

        button.addClass('disabled');
        toastr.info('جارٍ إنشاء صورة الفاتورة...');

        $.ajax({
            method: 'GET',
            url: href,
            dataType: 'json'
        }).done(async function(result) {
            if (result.success != 1 || !result.receipt || !result.receipt.html_content) {
                toastr.error(result.msg || 'تعذر تحميل الفاتورة');
                return;
            }

            var receipt = document.getElementById('receipt_section');
            var oldStyle = receipt.getAttribute('style');

            try {
                receipt.innerHTML = result.receipt.html_content;
                var invoiceImageHeading = document.createElement('div');
                invoiceImageHeading.textContent = 'فاتورة رقم: ' + invoiceNo;
                invoiceImageHeading.style.cssText = 'direction:rtl; text-align:center; font-size:28px; font-weight:700; margin:0 0 28px; padding-bottom:16px; border-bottom:2px solid #222; color:#111;';
                receipt.insertBefore(invoiceImageHeading, receipt.firstChild);
                __currency_convert_recursively($('#receipt_section'));
                // A4 portrait at 96 DPI: 794 x 1123 CSS pixels.
                receipt.style.cssText = 'display:block !important; position:fixed; left:-10000px; top:0; box-sizing:border-box; width:794px; min-height:1123px; padding:38px; margin:0; background:#fff; z-index:-1;';

                await waitForInvoiceImages(receipt);
                if (document.fonts && document.fonts.ready) {
                    await document.fonts.ready;
                }

                var canvas = await html2canvas(receipt, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    useCORS: true,
                    logging: false,
                    scrollX: 0,
                    scrollY: 0
                });

                var blob = await new Promise(function(resolve) {
                    canvas.toBlob(resolve, 'image/png');
                });

                if (!blob) {
                    throw new Error('Canvas could not be converted to an image.');
                }

                if (invoiceImageUrl) {
                    URL.revokeObjectURL(invoiceImageUrl);
                }

                invoiceImageFile = new File([blob], 'invoice-' + invoiceNo + '.png', { type: 'image/png' });
                invoiceImageInvoiceNo = invoiceNo;
                invoiceImageUrl = URL.createObjectURL(blob);
                $('#invoice_image_preview').attr('src', invoiceImageUrl);
                $('#invoice_whatsapp_recipient').text(invoiceWhatsappNumber
                    ? 'رقم المستلم: +' + invoiceWhatsappNumber
                    : 'لم يتم تحديد رقم واتساب في إعدادات النشاط.');
                $('#open_invoice_whatsapp').prop('disabled', !invoiceWhatsappNumber);

                var canShareFiles = navigator.share && (!navigator.canShare || navigator.canShare({ files: [invoiceImageFile] }));
                $('#share_invoice_image').prop('disabled', !canShareFiles);
                $('#invoice_share_help').text(canShareFiles
                    ? 'اضغط مشاركة ثم اختر WhatsApp ومحادثة المدير.'
                    : 'المتصفح الحالي لا يدعم مشاركة الملفات؛ نزّل الصورة ثم أرفقها في WhatsApp.');
                $('#invoice_image_share_modal').modal('show');
            } catch (error) {
                console.error(error);
                toastr.error('تعذر إنشاء صورة الفاتورة');
            } finally {
                receipt.innerHTML = '';
                if (oldStyle === null) {
                    receipt.removeAttribute('style');
                } else {
                    receipt.setAttribute('style', oldStyle);
                }
            }
        }).fail(function() {
            toastr.error('تعذر تحميل الفاتورة');
        }).always(function() {
            button.removeClass('disabled');
        });
    });

    $(document).on('click', '#share_invoice_image', async function() {
        if (!invoiceImageFile || !navigator.share) {
            downloadInvoiceImage();
            return;
        }

        try {
            await navigator.share({
                files: [invoiceImageFile],
                title: 'صورة الفاتورة',
                text: 'صورة الفاتورة ' + invoiceImageFile.name.replace(/^invoice-|\.png$/g, '')
            });
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
                toastr.error('تعذرت المشاركة؛ يمكنك تنزيل الصورة وإرفاقها يدويًا.');
            }
        }
    });

    $(document).on('click', '#download_invoice_image', downloadInvoiceImage);

    $(document).on('click', '#open_invoice_whatsapp', function() {
        if (!invoiceImageFile || !invoiceWhatsappNumber) {
            toastr.error('أضف رقم واتساب مستلم الفواتير من إعدادات النشاط أولًا.');
            return;
        }

        var message = encodeURIComponent('صورة الفاتورة رقم ' + invoiceImageInvoiceNo);
        window.open('https://wa.me/' + invoiceWhatsappNumber + '?text=' + message, '_blank');
        downloadInvoiceImage();
    });

    $(document).on('hidden.bs.modal', '#invoice_image_share_modal', function() {
        $('#invoice_image_preview').removeAttr('src');
        if (invoiceImageUrl) {
            URL.revokeObjectURL(invoiceImageUrl);
        }
        invoiceImageUrl = null;
        invoiceImageFile = null;
        invoiceImageInvoiceNo = '';
    });
</script>

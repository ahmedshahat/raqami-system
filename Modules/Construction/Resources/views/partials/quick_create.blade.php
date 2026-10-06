@push('ct_quick_css')
<style>
.ct-quick-modal .modal-dialog{max-width:620px;width:calc(100% - 24px);margin:30px auto}
.ct-quick-modal .modal-content{border:0;border-radius:16px;box-shadow:0 18px 55px rgba(16,43,67,.2);overflow:visible}
.ct-quick-modal .modal-header{background:#f5f9fc;padding:18px 24px;border-bottom:1px solid #e2e9ee}
.ct-quick-modal .modal-title{font-weight:700;color:#173b52}
.ct-quick-modal .modal-body{padding:24px}
.ct-quick-modal .modal-footer{padding:14px 24px;background:#f9fbfd;border-top:1px solid #e2e9ee}
.ct-quick-modal .close{float:left;margin:0;opacity:.65}
.ct-quick-modal .form-group label{display:block;font-weight:600}
.ct-quick-errors:empty{display:none}
#ct-quick-workspace:not(:empty){margin-top:20px}
#ct-quick-workspace>.content{padding:0}
.ct-workflow-step{width:20%;float:right;text-align:center}.ct-workflow-step h4{font-size:15px}
@media(max-width:767px){.ct-quick-modal .modal-dialog{margin:12px auto}.ct-quick-modal .modal-content{max-height:calc(100vh - 24px);overflow-y:auto}.ct-quick-modal .modal-body{padding:18px}.ct-quick-modal .modal-footer{padding:12px 18px}}
@media(max-width:767px){.ct-workflow-step{width:50%;margin-bottom:12px}}
</style>
@endpush

@push('ct_quick_js')
<script>
(function () {
    function errorText(data) {
        if (data.errors) return Object.values(data.errors).reduce(function (all, messages) { return all.concat(messages); }, []).join('\n');
        return data.message || @json(__('construction::lang.validation_failed'));
    }
    async function refreshList() {
        var list = document.getElementById('ct-quick-list');
        if (!list) return;
        var response = await fetch(window.location.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok) return;
        var doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        var refreshed = doc.getElementById('ct-quick-list');
        if (refreshed) list.innerHTML = refreshed.innerHTML;
        var summary = document.getElementById('ct-project-summary');
        var newSummary = doc.getElementById('ct-project-summary');
        if (summary && newSummary) summary.innerHTML = newSummary.innerHTML;
        var modal = document.querySelector('.ct-quick-modal');
        var freshModal = doc.querySelector('.ct-quick-modal');
        if (modal && freshModal) {
            ['parent_id', 'measurement_id'].forEach(function (name) {
                var current = modal.querySelector('[name="' + name + '"]');
                var fresh = freshModal.querySelector('[name="' + name + '"]');
                if (current && fresh) current.innerHTML = fresh.innerHTML;
            });
            ['code', 'number'].forEach(function (name) {
                var current = modal.querySelector('[name="' + name + '"]');
                var fresh = freshModal.querySelector('[name="' + name + '"]');
                if (current && fresh) current.value = fresh.value;
            });
            var certificateNumber = modal.querySelector('.ct-certificate-form input[readonly]');
            var freshCertificateNumber = freshModal.querySelector('.ct-certificate-form input[readonly]');
            if (certificateNumber && freshCertificateNumber) certificateNumber.value = freshCertificateNumber.value;
        }
    }
    window.ctRefreshList = refreshList;
    async function openWorkspace(url) {
        if (!url) return;
        var workspace = document.getElementById('ct-quick-workspace');
        if (!workspace) { window.location.assign(url); return; }
        var response;
        try { response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}); }
        catch (error) { window.location.assign(url); return; }
        if (!response.ok) { window.location.assign(url); return; }
        var doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        installDocumentStyles(doc);
        var content = doc.querySelector('.ct-module-content > section');
        if (!content) { window.location.assign(url); return; }
        workspace.innerHTML = '';
        workspace.appendChild(document.importNode(content, true));
        var script = doc.getElementById('ct-measurement-detail-script');
        if (script && window.jQuery) jQuery.globalEval(script.textContent);
        workspace.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
    function installDocumentStyles(doc) {
        var styles = doc.getElementById('ct-certificate-styles');
        if (styles && !document.getElementById('ct-certificate-styles')) document.head.appendChild(document.importNode(styles, true));
    }
    document.addEventListener('submit', async function (event) {
        var form = event.target.closest('form[data-ct-quick-create]');
        if (!form) return;
        event.preventDefault();
        var button = form.querySelector('[type="submit"]');
        var errors = form.querySelector('.ct-quick-errors');
        if (errors) { errors.style.display = 'none'; errors.textContent = ''; }
        button.disabled = true;
        try {
            var response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            var data = await response.json();
            if (!response.ok) throw new Error(errorText(data));
            jQuery(form.closest('.modal')).modal('hide');
            form.reset();
            if (form.dataset.ctOpenMode === 'redirect' && data.url) { window.location.assign(data.url); return; }
            await refreshList().catch(function () {});
            await openWorkspace(data.url);
            if (window.toastr) toastr.success(data.message);
        } catch (error) {
            if (errors) { errors.textContent = error.message; errors.style.display = 'block'; }
        } finally { button.disabled = false; }
    });
    document.addEventListener('click', function (event) {
        var certificateLink = event.target.closest('a[data-ct-certificate-create]');
        if (certificateLink && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            fetch(certificateLink.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(function (response) {
                if (!response.ok) throw new Error('open');
                return response.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                installDocumentStyles(doc);
                var modal = doc.getElementById('ct-certificate-create');
                if (!modal) throw new Error('open');
                document.getElementById('ct-certificate-create')?.remove();
                document.body.appendChild(document.importNode(modal, true));
                jQuery('#ct-certificate-create .ct-date-picker').datetimepicker({format: moment_date_format, ignoreReadonly: true});
                jQuery('#ct-certificate-create').modal('show');
            }).catch(function () { window.location.assign(certificateLink.href); });
            return;
        }
        var link = event.target.closest('a[data-ct-workspace]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        openWorkspace(link.href);
    });
    @if($errors->any())
    jQuery('.ct-quick-modal').first().modal('show');
    @endif
})();
</script>
@endpush

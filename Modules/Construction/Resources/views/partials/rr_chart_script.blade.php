{{--
    تلميح الرسوم البيانية (rr-chart) — يتضمَّن مرة واحدة في أي شاشة فيها رسم:
    @include('construction::partials.rr_chart_script')
    أي عنصر عليه data-rr-tip وجواه <div class="rr-chart__tip-src" hidden>…</div> بيظهر محتواه
    في تلميح عائم عند المرور بالماوس أو التركيز بالكيبورد.
--}}
@once
@push('ct_quick_js')
<script>
(function () {
    var tip = null;
    function ensureTip() {
        if (tip) return tip;
        tip = document.createElement('div');
        tip.className = 'rr-scope rr-chart-tip';
        tip.setAttribute('role', 'tooltip');
        document.body.appendChild(tip);
        return tip;
    }
    function show(target) {
        var source = target.querySelector('.rr-chart__tip-src');
        if (!source) return;
        var el = ensureTip();
        el.innerHTML = source.innerHTML;
        el.classList.add('is-visible');
        var anchor = target.querySelector('.rr-chart__stack') || target;
        var rect = anchor.getBoundingClientRect(), box = el.getBoundingClientRect();
        var left = Math.min(Math.max(8, rect.left + rect.width / 2 - box.width / 2), window.innerWidth - box.width - 8);
        var top = rect.top - box.height - 8;
        if (top < 8) top = Math.min(rect.bottom + 8, window.innerHeight - box.height - 8);
        el.style.left = left + 'px';
        el.style.top = top + 'px';
    }
    function hide() { if (tip) tip.classList.remove('is-visible'); }
    document.addEventListener('mouseover', function (event) {
        var target = event.target.closest('[data-rr-tip]');
        if (target) show(target);
    });
    document.addEventListener('mouseout', function (event) {
        var target = event.target.closest('[data-rr-tip]');
        if (target && !target.contains(event.relatedTarget)) hide();
    });
    document.addEventListener('focusin', function (event) {
        var target = event.target.closest('[data-rr-tip]');
        if (target) show(target); else hide();
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') hide(); });
    window.addEventListener('scroll', hide, true);
})();
</script>
@endpush
@endonce

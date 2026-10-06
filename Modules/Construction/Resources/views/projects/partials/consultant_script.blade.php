<script>
$(document).ready(function () {
    var $selection = $('#consultant-selection');
    var $manualWrap = $('#manual-consultant-wrap');
    var $manualName = $('#manual-consultant-name');

    function toggleManualConsultant() {
        var isManual = String($selection.val()) === 'manual';
        $manualWrap.toggle(isManual);
        $manualName.prop('required', isManual);

        if (!isManual) {
            $manualName.val('');
        }
    }

    $selection.on('change select2:select select2:clear', toggleManualConsultant);
    toggleManualConsultant();
});
</script>

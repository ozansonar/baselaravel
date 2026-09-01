@if(session('success') || session('error') || session('warning') || session('info'))
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
        window.showResultModal('success', @json(session('success')));
    @elseif(session('error'))
        window.showResultModal('error', @json(session('error')));
    @elseif(session('warning'))
        window.showResultModal('warning', @json(session('warning')));
    @elseif(session('info'))
        window.showResultModal('info', @json(session('info')));
    @endif
});
</script>
@endif

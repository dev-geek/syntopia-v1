@foreach (['success', 'error', 'warning', 'info'] as $msg)
    @if (session($msg))
        <div class="alert alert-{{ $msg }} alert-dismissible fade show mt-2 mx-3" role="alert">
            {{ session($msg) }}
            <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
@endforeach

<!-- Auto-dismiss script -->
<script>
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(el => {
            el.classList.remove('show');
            el.classList.add('fade');
            setTimeout(() => el.remove(), 300); // smoothly remove from DOM
        });
    }, 30000); // 30 seconds
</script>

@props([
    'tableId' => 'dataTable',
    'pageLength' => 10,
    'lengthMenu' => '[5, 10, 25, 50]',
    'order' => '[[0, "desc"]]',
    'buttons' => '["copy", "csv", "excel", "pdf", "print"]',
    'responsive' => true,
    'lengthChange' => true,
    'autoWidth' => false,
    'searching' => true,
    'ordering' => true,
    'info' => true,
    'language' => null
])

<script>
$(function() {
    const defaultLanguage = {
        "lengthMenu": "Show _MENU_ entries per page",
        "zeroRecords": "No records found",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "search": "Search:",
        "paginate": {
            "first": "First",
            "last": "Last",
            "next": "Next",
            "previous": "Previous"
        },
        "processing": "Processing...",
        "loadingRecords": "Loading...",
        "emptyTable": "No data available in table"
    };

    const config = {
        "responsive": {{ $responsive ? 'true' : 'false' }},
        "lengthChange": {{ $lengthChange ? 'true' : 'false' }},
        "autoWidth": {{ $autoWidth ? 'true' : 'false' }},
        "pageLength": {{ $pageLength }},
        "lengthMenu": {{ $lengthMenu }},
        "order": {!! $order !!},
        "searching": {{ $searching ? 'true' : 'false' }},
        "ordering": {{ $ordering ? 'true' : 'false' }},
        "info": {{ $info ? 'true' : 'false' }},
        "language": {!! $language ?: json_encode($defaultLanguage) !!},
        "buttons": {!! $buttons !!}
    };

    $("#{{ $tableId }}").DataTable(config).buttons().container().appendTo('#{{ $tableId }}_wrapper .col-md-6:eq(0)');
});
</script>

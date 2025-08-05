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
$(document).ready(function() {
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
        "language": {!! $language ?: json_encode($defaultLanguage) !!}
    };

    // Add buttons configuration only if buttons are provided and not empty
    const buttonsArray = {!! $buttons !!};
    if (buttonsArray && Array.isArray(buttonsArray) && buttonsArray.length > 0) {
        config.buttons = buttonsArray;
        config.dom = 'Bfrtip';
    }

    try {
        const table = $("#{{ $tableId }}").DataTable(config);

        // Initialize buttons if they exist
        if (config.buttons && config.buttons.length > 0) {
            table.buttons().container().appendTo('#{{ $tableId }}_wrapper .col-md-6:eq(0)');
        }
    } catch (error) {
        console.error('DataTable initialization error:', error);
        console.error('Config:', config);
    }
});
</script>

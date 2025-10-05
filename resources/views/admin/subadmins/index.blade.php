@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Sub Admins Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Sub Admins</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Sub Admins Management</h3>
                            <a href="{{ route('admin.subadmins.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Sub Admin
                            </a>
                        </div>
                        <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('swal_error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('swal_error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="subadminsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subAdmins as $subAdmin)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $subAdmin->name }}</td>
                                        <td>{{ $subAdmin->email }}</td>
                                        <td>
                                            <span class="badge badge-{{ $subAdmin->is_active ? 'success' : 'danger' }}">
                                                {{ $subAdmin->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $subAdmin->created_at->format('M d, Y H:i') }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap">
                                                <a href="{{ route('admin.subadmins.show', $subAdmin) }}"
                                                   class="btn btn-sm btn-info mr-1" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.subadmins.edit', $subAdmin) }}"
                                                   class="btn btn-sm btn-warning mr-1" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.subadmins.toggle-status', $subAdmin) }}"
                                                      method="POST" class="d-inline mr-1">
                                                    @csrf
                                                    <button type="button"
                                                            class="btn btn-sm btn-{{ $subAdmin->is_active ? 'secondary' : 'success' }}"
                                                            title="{{ $subAdmin->is_active ? 'Deactivate' : 'Activate' }}"
                                                            data-swal-toggle
                                                            data-is-active="{{ $subAdmin->is_active ? 'true' : 'false' }}">
                                                        <i class="fas fa-{{ $subAdmin->is_active ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.subadmins.destroy', $subAdmin) }}"
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger"
                                                            title="Delete"
                                                            data-swal-delete
                                                            data-item-name="Sub Admin">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No Sub Admins found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@include('dashboard.includes.footer')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/swal-confirm.js') }}"></script>
<script>
$(document).ready(function() {
    $('#subadminsTable').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "order": [[4, "desc"]], // Order by Created At column (5th column, index 4)
        "columnDefs": [
            { "orderable": false, "targets": 5 } // Disable ordering on Actions column
        ]
    });
});
</script>

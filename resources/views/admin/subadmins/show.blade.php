@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Sub Admin Details: {{ $subAdmin->name }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.subadmins.index') }}">Sub Admins</a></li>
                        <li class="breadcrumb-item active">Details</li>
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
                        <div class="card-header">
                            <h3 class="card-title">Sub Admin Details: {{ $subAdmin->name }}</h3>
                            <div class="card-tools">
                                <a href="{{ route('admin.subadmins.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Sub Admins
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-borderless table-striped">
                                    <tbody>
                                        <tr>
                                            <th class="text-nowrap">Name:</th>
                                            <td>{{ $subAdmin->name }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-nowrap">Email:</th>
                                            <td class="text-break">{{ $subAdmin->email }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-nowrap">Status:</th>
                                            <td>
                                                <span class="badge badge-{{ $subAdmin->status ? 'success' : 'danger' }}">
                                                    {{ $subAdmin->status ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="text-nowrap">Sub Admin Status:</th>
                                            <td>
                                                <span class="badge badge-{{ $subAdmin->is_active ? 'success' : 'danger' }}">
                                                    {{ $subAdmin->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="text-nowrap">Email Verified:</th>
                                            <td>
                                                @if($subAdmin->email_verified_at)
                                                    <span class="badge badge-success">
                                                        {{ $subAdmin->email_verified_at->format('M d, Y H:i') }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-warning">Not Verified</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="text-nowrap">Created At:</th>
                                            <td>{{ $subAdmin->created_at->format('M d, Y H:i') }}</td>
                                        </tr>
                                        <tr>
                                            <th class="text-nowrap">Updated At:</th>
                                            <td>{{ $subAdmin->updated_at->format('M d, Y H:i') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap">
                                <a href="{{ route('admin.subadmins.edit', $subAdmin) }}"
                                   class="btn btn-warning btn-sm mr-2 mb-2">
                                    <i class="fas fa-edit"></i>
                                    <span class="d-none d-sm-inline">Edit Sub Admin</span>
                                    <span class="d-sm-none">Edit</span>
                                </a>
                                <form action="{{ route('admin.subadmins.toggle-status', $subAdmin) }}"
                                      method="POST" class="d-inline mr-2 mb-2">
                                    @csrf
                                    <button type="button"
                                            class="btn btn-{{ $subAdmin->is_active ? 'secondary' : 'success' }} btn-sm"
                                            data-swal-toggle
                                            data-is-active="{{ $subAdmin->is_active ? 'true' : 'false' }}">
                                        <i class="fas fa-{{ $subAdmin->is_active ? 'ban' : 'check' }}"></i>
                                        <span class="d-none d-sm-inline">{{ $subAdmin->is_active ? 'Deactivate' : 'Activate' }}</span>
                                        <span class="d-sm-none">{{ $subAdmin->is_active ? 'Deactivate' : 'Activate' }}</span>
                                    </button>
                                </form>
                                <form action="{{ route('admin.subadmins.destroy', $subAdmin) }}"
                                      method="POST" class="d-inline mb-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            class="btn btn-danger btn-sm"
                                            data-swal-delete
                                            data-item-name="Sub Admin">
                                        <i class="fas fa-trash"></i>
                                        <span class="d-none d-sm-inline">Delete Sub Admin</span>
                                        <span class="d-sm-none">Delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
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

@include('dashboard.includes/header')
@include('dashboard.includes/sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Free Plan Attempt Details #{{ $attempt->id }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.free-plan-attempts.index') }}">Free Plan Attempts</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attempt Information</h3>
                            <div class="card-tools">
                                <a href="{{ route('admin.free-plan-attempts.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </div>

                        @include('components.alert-messages')

                        <div class="card-body">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h5><i class="fas fa-info-circle"></i> Basic Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="30%">ID:</th>
                                            <td>{{ $attempt->id }}</td>
                                        </tr>
                                        <tr>
                                            <th>IP Address:</th>
                                            <td>
                                                <code class="text-primary">{{ $attempt->ip_address }}</code>
                                                <button class="btn btn-sm btn-outline-danger ml-2" onclick="blockIdentifier('ip', '{{ $attempt->ip_address }}')">
                                                    <i class="fas fa-ban"></i> Block IP
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td>
                                                {{ $attempt->email ?: 'N/A' }}
                                                @if($attempt->email)
                                                <button class="btn btn-sm btn-outline-danger ml-2" onclick="blockIdentifier('email', '{{ $attempt->email }}')">
                                                    <i class="fas fa-ban"></i> Block Email
                                                </button>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>
                                                @if($attempt->is_blocked)
                                                    <span class="badge badge-danger">Blocked</span>
                                                    @if($attempt->blocked_at)
                                                        <br><small class="text-muted">Blocked at: {{ $attempt->blocked_at->format('M j, Y H:i:s') }}</small>
                                                    @endif
                                                    @if($attempt->block_reason)
                                                        <br><small class="text-muted">Reason: {{ $attempt->block_reason }}</small>
                                                    @endif
                                                @else
                                                    <span class="badge badge-success">Active</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Created At:</th>
                                            <td>{{ $attempt->created_at->format('M j, Y H:i:s') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Updated At:</th>
                                            <td>{{ $attempt->updated_at->format('M j, Y H:i:s') }}</td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Device Information -->
                                <div class="col-md-6">
                                    <h5><i class="fas fa-desktop"></i> Device Information</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="30%">User Agent:</th>
                                            <td>
                                                <small class="text-muted">{{ $attempt->user_agent }}</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Device Fingerprint:</th>
                                            <td>
                                                <code class="small">{{ Str::limit($attempt->device_fingerprint, 50) }}</code>
                                                <button class="btn btn-sm btn-outline-danger ml-2" onclick="blockIdentifier('device_fingerprint', '{{ $attempt->device_fingerprint }}')">
                                                    <i class="fas fa-ban"></i> Block Device
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Fingerprint ID:</th>
                                            <td>
                                                {{ $attempt->fingerprint_id ?: 'N/A' }}
                                                @if($attempt->fingerprint_id)
                                                <button class="btn btn-sm btn-outline-danger ml-2" onclick="blockIdentifier('fingerprint_id', '{{ $attempt->fingerprint_id }}')">
                                                    <i class="fas fa-ban"></i> Block FP ID
                                                </button>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Additional Data -->
                            @if($attempt->data)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5><i class="fas fa-database"></i> Additional Data</h5>
                                    <pre class="bg-light p-3 rounded"><code>{{ json_encode($attempt->data, JSON_PRETTY_PRINT) }}</code></pre>
                                </div>
                            </div>
                            @endif

                            <!-- Actions -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5><i class="fas fa-cogs"></i> Actions</h5>
                                    <div class="btn-group" role="group">
                                        @if($attempt->is_blocked)
                                            <form method="POST" action="{{ route('admin.free-plan-attempts.unblock') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="ids[]" value="{{ $attempt->id }}">
                                                <button type="submit" class="btn btn-success mr-2" onclick="return confirm('Are you sure you want to unblock this attempt?')">
                                                    <i class="fas fa-unlock"></i> Unblock Attempt
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.free-plan-attempts.block') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="ids[]" value="{{ $attempt->id }}">
                                                <button type="submit" class="btn btn-warning mr-2" onclick="return confirm('Are you sure you want to block this attempt?')">
                                                    <i class="fas fa-ban"></i> Block Attempt
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Related Attempts -->
                    @if($relatedAttempts->count() > 0)
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Related Attempts</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>IP Address</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($relatedAttempts as $relatedAttempt)
                                        <tr class="{{ $relatedAttempt->is_blocked ? 'table-danger' : '' }}">
                                            <td>
                                                <a href="{{ route('admin.free-plan-attempts.show', $relatedAttempt) }}">{{ $relatedAttempt->id }}</a>
                                            </td>
                                            <td><code>{{ $relatedAttempt->ip_address }}</code></td>
                                            <td>{{ $relatedAttempt->email ?: 'N/A' }}</td>
                                            <td>
                                                @if($relatedAttempt->is_blocked)
                                                    <span class="badge badge-danger">Blocked</span>
                                                @else
                                                    <span class="badge badge-success">Active</span>
                                                @endif
                                            </td>
                                            <td>{{ $relatedAttempt->created_at->format('M j, Y H:i') }}</td>
                                            <td>
                                                <a href="{{ route('admin.free-plan-attempts.show', $relatedAttempt) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Block Identifier Modal -->
<div class="modal fade" id="blockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Block Identifier</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.free-plan-attempts.block-identifier') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="blockType" name="type">
                    <input type="hidden" id="blockValue" name="value">
                    <div class="form-group">
                        <label for="blockReason">Reason (optional)</label>
                        <textarea class="form-control" id="blockReason" name="reason" rows="3" placeholder="Enter reason for blocking..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Block</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('dashboard.includes/footer')

<script>
function blockIdentifier(type, value) {
    document.getElementById('blockType').value = type;
    document.getElementById('blockValue').value = value;
    $('#blockModal').modal('show');
}
</script>

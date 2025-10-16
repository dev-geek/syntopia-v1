@include('dashboard.includes/header')
@include('dashboard.includes/sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Free Plan Attempts Management</h1>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-lg-3 mb-3" data-aos="fade-up">
                    <div class="card dashboard-card bg-gradient-blue h-100">
                        <div class="card-body d-flex flex-column justify-content-between text-center">
                            <div>
                                <div class="icon mb-2"><i class="fas fa-chart-line"></i></div>
                                <div class="card-title">Total Attempts</div>
                                <div class="card-number">{{ $stats['total_attempts'] }}</div>
                            </div>
                            <div class="card-meta"><span class="badge-soft">All time</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 mb-3" data-aos="fade-up" data-aos-delay="80">
                    <div class="card dashboard-card bg-gradient-red h-100">
                        <div class="card-body d-flex flex-column justify-content-between text-center">
                            <div>
                                <div class="icon mb-2"><i class="fas fa-shield-alt"></i></div>
                                <div class="card-title">Blocked Attempts</div>
                                <div class="card-number">{{ $stats['blocked_attempts'] }}</div>
                            </div>
                            <div class="card-meta"><span class="badge-soft">Block Rate: {{ $stats['block_rate'] }}%</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 mb-3" data-aos="fade-up" data-aos-delay="160">
                    <div class="card dashboard-card bg-gradient-green h-100">
                        <div class="card-body d-flex flex-column justify-content-between text-center">
                            <div>
                                <div class="icon mb-2"><i class="fas fa-network-wired"></i></div>
                                <div class="card-title">Unique IPs</div>
                                <div class="card-number">{{ $stats['unique_ips'] }}</div>
                            </div>
                            <div class="card-meta"><span class="badge-soft">Distinct sources</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 mb-3" data-aos="fade-up" data-aos-delay="240">
                    <div class="card dashboard-card bg-gradient-yellow h-100">
                        <div class="card-body d-flex flex-column justify-content-between text-center">
                            <div>
                                <div class="icon mb-2"><i class="fas fa-calendar-week"></i></div>
                                <div class="card-title">Last 7 Days</div>
                                <div class="card-number">{{ $stats['recent_attempts'] }}</div>
                            </div>
                            <div class="card-meta"><span class="badge-soft">Recent activity</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Free Plan Attempts</h3>
                            <div class="card-tools">
                                <a href="{{ route('admin.free-plan-attempts.export') }}" class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i> Export CSV
                                </a>
                            </div>
                        </div>

                        @include('components.alert-messages')

                        <div class="card-body">
                            <!-- Attempts Table -->
                            <div class="table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>IP Address</th>
                                            <th>Email</th>
                                            <th>User Agent</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($attempts as $attempt)
                                        <tr class="{{ $attempt->is_blocked ? 'table-danger' : '' }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <code class="text-primary">{{ $attempt->ip_address }}</code>
                                            </td>
                                            <td>
                                                {{ $attempt->email ?? '<span class="text-muted">N/A</span>' }}
                                            </td>
                                            <td>
                                                <span class="text-muted small" title="{{ $attempt->user_agent }}">
                                                    {{ Str::limit($attempt->user_agent, 50) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($attempt->is_blocked)
                                                    <span class="badge badge-danger">Blocked</span>
                                                    @if($attempt->blocked_at)
                                                        <br><small class="text-muted">{{ $attempt->blocked_at->format('M j, Y H:i') }}</small>
                                                    @endif
                                                @else
                                                    <span class="badge badge-success">Active</span>
                                                @endif
                                            </td>
                                            <td>{{ $attempt->created_at->format('Y-m-d H:i:s') }}</td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('admin.free-plan-attempts.show', $attempt) }}" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @if($attempt->is_blocked)
                                                        <form id="unblockForm-{{ $attempt->id }}" method="POST" action="{{ route('admin.free-plan-attempts.unblock') }}" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="ids[]" value="{{ $attempt->id }}">
                                                            <button type="button" class="btn btn-sm btn-success ml-1" onclick="confirmUnblockAttempt('unblockForm-{{ $attempt->id }}')">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('admin.free-plan-attempts.block') }}" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="ids[]" value="{{ $attempt->id }}">
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                No attempts found.
                                            </td>
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

@include('dashboard.includes/footer')

<!-- AOS Animate On Scroll CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

<!-- Dashboard Cards Styling -->
<style>
    .dashboard-card {
        border: none;
        border-radius: 1.2rem;
        box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        color: #fff;
        overflow: hidden;
        transition: transform 0.25s, box-shadow 0.25s;
        background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
        position: relative;
    }
    .dashboard-card .card-meta { margin-top: 0.75rem; }
    .badge-soft {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 999px;
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.25);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
    }
    @media (max-width: 575.98px) {
        .dashboard-card .card-number { font-size: 1.75rem; }
        .dashboard-card .icon { font-size: 2rem; }
    }
    .dashboard-card .card-body {
        padding: 2rem 1.5rem 1.5rem 1.5rem;
    }
    .dashboard-card,
    .dashboard-card .card-body,
    .dashboard-card .icon,
    .dashboard-card .card-title,
    .dashboard-card .card-number,
    .dashboard-card .card-meta,
    .dashboard-card .badge-soft {
        color: #fff !important;
    }
    .dashboard-card .icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        display: inline-block;
        transition: transform 0.3s;
    }
    .dashboard-card .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        letter-spacing: 0.5px;
    }
    .dashboard-card .card-number {
        font-size: 2.1rem;
        font-weight: 700;
        letter-spacing: 1px;
        margin-bottom: 0;
    }
    .dashboard-card.bg-gradient-blue { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); }
    .dashboard-card.bg-gradient-red { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
    .dashboard-card.bg-gradient-green { background: linear-gradient(135deg, #198754 0%, #20c997 100%); }
    .dashboard-card.bg-gradient-yellow { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    .dashboard-card:hover {
        transform: translateY(-6px) scale(1.03);
        box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        z-index: 2;
    }
    .dashboard-card:hover .icon {
        transform: scale(1.18) rotate(-8deg);
    }
</style>

<x-datatable
    tableId="example1"
    :order="json_encode([[5,'desc']])"
    :buttons="json_encode([])"
    :language="json_encode([
        'lengthMenu' => 'Show _MENU_ attempts per page',
        'zeroRecords' => 'No attempts found',
        'info' => 'Showing _START_ to _END_ of _TOTAL_ attempts',
        'infoEmpty' => 'Showing 0 to 0 of 0 attempts',
        'infoFiltered' => '(filtered from _MAX_ total attempts)',
        'search' => 'Search attempts:',
        'paginate' => [
            'first' => 'First',
            'last' => 'Last',
            'next' => 'Next',
            'previous' => 'Previous'
        ]
    ])"
/>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/swal-utils.js') }}"></script>


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

<script>
document.addEventListener('DOMContentLoaded', function(){
    AOS.init({duration: 900, once: true});
});

function blockIdentifier(type, value) {
    document.getElementById('blockType').value = type;
    document.getElementById('blockValue').value = value;
    $('#blockModal').modal('show');
}
</script>
<script>
function confirmUnblockAttempt(formId) {
    Swal.fire({
        title: 'Unblock Attempt?',
        text: 'This will allow registrations from this user/device/network again.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, unblock',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById(formId).submit();
        }
    });
}
</script>

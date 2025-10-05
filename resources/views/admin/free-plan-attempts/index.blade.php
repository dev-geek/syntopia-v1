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
            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up">
                    <div class="card dashboard-card bg-gradient-blue h-100">
                        <div class="card-body text-center">
                            <div class="icon mb-2"><i class="fas fa-chart-line"></i></div>
                            <div class="card-title">Total Attempts</div>
                            <div class="card-number">{{ $stats['total_attempts'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="card dashboard-card bg-gradient-red h-100">
                        <div class="card-body text-center">
                            <div class="icon mb-2"><i class="fas fa-ban"></i></div>
                            <div class="card-title">Blocked Attempts</div>
                            <div class="card-number">{{ $stats['blocked_attempts'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="card dashboard-card bg-gradient-green h-100">
                        <div class="card-body text-center">
                            <div class="icon mb-2"><i class="fas fa-network-wired"></i></div>
                            <div class="card-title">Unique IPs</div>
                            <div class="card-number">{{ $stats['unique_ips'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="card dashboard-card bg-gradient-yellow h-100">
                        <div class="card-body text-center">
                            <div class="icon mb-2"><i class="fas fa-calendar-week"></i></div>
                            <div class="card-title">Last 7 Days</div>
                            <div class="card-number">{{ $stats['recent_attempts'] }}</div>
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
                                <a href="{{ route('admin.free-plan-attempts.export', request()->query()) }}" class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i> Export CSV
                                </a>
                            </div>
                        </div>

                        @include('components.alert-messages')

                        <div class="card-body">
                            <!-- Filters -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="card card-outline card-primary">
                                        <div class="card-header">
                                            <h3 class="card-title">
                                                <i class="fas fa-filter"></i> Filters
                                            </h3>
                                            <div class="card-tools">
                                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <form method="GET" class="row">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>IP Address:</label>
                                                        <input type="text" name="ip" value="{{ request('ip') }}"
                                                               class="form-control" placeholder="Enter IP address">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>Email:</label>
                                                        <input type="email" name="email" value="{{ request('email') }}"
                                                               class="form-control" placeholder="Enter email">
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Status:</label>
                                                        <select name="blocked" class="form-control">
                                                            <option value="">All</option>
                                                            <option value="1" {{ request('blocked') === '1' ? 'selected' : '' }}>Blocked</option>
                                                            <option value="0" {{ request('blocked') === '0' ? 'selected' : '' }}>Not Blocked</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Days Back:</label>
                                                        <select name="days" class="form-control">
                                                            <option value="7" {{ request('days') == '7' ? 'selected' : '' }}>Last 7 days</option>
                                                            <option value="30" {{ request('days') == '30' ? 'selected' : '' }}>Last 30 days</option>
                                                            <option value="90" {{ request('days') == '90' ? 'selected' : '' }}>Last 90 days</option>
                                                            <option value="" {{ !request('days') ? 'selected' : '' }}>All time</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>&nbsp;</label>
                                                        <div class="btn-group" role="group">
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-search"></i> Filter
                                                            </button>
                                                            <a href="{{ route('admin.free-plan-attempts.index') }}" class="btn btn-secondary ml-2">
                                                                <i class="fas fa-times"></i> Clear
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                            <td>{{ $attempt->id }}</td>
                                            <td>
                                                <code class="text-primary">{{ $attempt->ip_address }}</code>
                                                <button class="btn btn-sm btn-outline-primary ml-2" onclick="blockIdentifier('ip', '{{ $attempt->ip_address }}')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </td>
                                            <td>
                                                {{ $attempt->email ?? '<span class="text-muted">N/A</span>' }}
                                                @if($attempt->email)
                                                <button class="btn btn-sm btn-outline-primary ml-2" onclick="blockIdentifier('email', '{{ $attempt->email }}')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                @endif
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
                                                        <form method="POST" action="{{ route('admin.free-plan-attempts.unblock') }}" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="ids[]" value="{{ $attempt->id }}">
                                                            <button type="submit" class="btn btn-sm btn-success ml-1" onclick="return confirm('Are you sure you want to unblock this attempt?')">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('admin.free-plan-attempts.block') }}" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="ids[]" value="{{ $attempt->id }}">
                                                            <button type="submit" class="btn btn-sm btn-warning ml-1" onclick="return confirm('Are you sure you want to block this attempt?')">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                No attempts found matching your criteria.
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
    .dashboard-card .card-body {
        padding: 2rem 1.5rem 1.5rem 1.5rem;
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
    .dashboard-card.bg-gradient-yellow { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #222; }
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

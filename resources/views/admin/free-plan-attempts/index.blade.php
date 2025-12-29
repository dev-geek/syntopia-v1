@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')
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
                                                @php
                                                    $ua = $attempt->user_agent ?? '';
                                                    $browser = 'Unknown';
                                                    $browserIcon = 'fa-globe';
                                                    $os = 'Unknown';
                                                    $osIcon = 'fa-desktop';
                                                    $deviceType = 'Desktop';

                                                    // Detect Browser
                                                    if (preg_match('/Chrome\/([0-9.]+)/i', $ua, $matches) && !preg_match('/Edg/i', $ua)) {
                                                        $browser = 'Chrome ' . explode('.', $matches[1])[0];
                                                        $browserIcon = 'fa-chrome';
                                                    } elseif (preg_match('/Edg\/([0-9.]+)/i', $ua, $matches)) {
                                                        $browser = 'Edge ' . explode('.', $matches[1])[0];
                                                        $browserIcon = 'fa-edge';
                                                    } elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua, $matches)) {
                                                        $browser = 'Firefox ' . explode('.', $matches[1])[0];
                                                        $browserIcon = 'fa-firefox';
                                                    } elseif (preg_match('/Safari\/([0-9.]+)/i', $ua, $matches) && !preg_match('/Chrome/i', $ua)) {
                                                        $browser = 'Safari ' . explode('.', $matches[1])[0];
                                                        $browserIcon = 'fa-safari';
                                                    } elseif (preg_match('/Opera|OPR\/([0-9.]+)/i', $ua, $matches)) {
                                                        $browser = 'Opera ' . (isset($matches[1]) ? explode('.', $matches[1])[0] : '');
                                                        $browserIcon = 'fa-opera';
                                                    } elseif (preg_match('/MSIE|Trident/i', $ua)) {
                                                        $browser = 'Internet Explorer';
                                                        $browserIcon = 'fa-internet-explorer';
                                                    }

                                                    // Detect OS
                                                    if (preg_match('/Windows NT 10.0/i', $ua)) {
                                                        $os = 'Windows 10/11';
                                                        $osIcon = 'fa-windows';
                                                    } elseif (preg_match('/Windows NT 6.3/i', $ua)) {
                                                        $os = 'Windows 8.1';
                                                        $osIcon = 'fa-windows';
                                                    } elseif (preg_match('/Windows NT 6.2/i', $ua)) {
                                                        $os = 'Windows 8';
                                                        $osIcon = 'fa-windows';
                                                    } elseif (preg_match('/Windows NT 6.1/i', $ua)) {
                                                        $os = 'Windows 7';
                                                        $osIcon = 'fa-windows';
                                                    } elseif (preg_match('/Windows/i', $ua)) {
                                                        $os = 'Windows';
                                                        $osIcon = 'fa-windows';
                                                    } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $matches)) {
                                                        $os = 'macOS';
                                                        $osIcon = 'fa-apple';
                                                    } elseif (preg_match('/Linux/i', $ua)) {
                                                        $os = 'Linux';
                                                        $osIcon = 'fa-linux';
                                                    } elseif (preg_match('/Android ([0-9.]+)/i', $ua, $matches)) {
                                                        $os = 'Android ' . $matches[1];
                                                        $osIcon = 'fa-android';
                                                        $deviceType = 'Mobile';
                                                    } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
                                                        $os = 'iOS';
                                                        $osIcon = 'fa-apple';
                                                        $deviceType = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Mobile';
                                                    }
                                                @endphp

                                                <div class="user-agent-info">
                                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                        <div class="d-flex align-items-center gap-1">
                                                            <i class="fas {{ $browserIcon }} text-primary" title="Browser"></i>
                                                            <span class="small font-weight-semibold">{{ $browser }}</span>
                                                        </div>
                                                        <span class="text-muted">•</span>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <i class="fab {{ $osIcon }} text-info" title="Operating System"></i>
                                                            <span class="small">{{ $os }}</span>
                                                        </div>
                                                        <span class="badge badge-secondary badge-sm">{{ $deviceType }}</span>
                                                    </div>
                                                    <small class="text-muted d-block" style="font-size: 0.7rem; word-break: break-all;" title="{{ $ua }}">
                                                        <i class="fas fa-info-circle"></i> {{ Str::limit($ua, 60) }}
                                                    </small>
                                                </div>
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
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
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

@include('dashboard.includes.footer')

<!-- AOS Animate On Scroll CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>


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

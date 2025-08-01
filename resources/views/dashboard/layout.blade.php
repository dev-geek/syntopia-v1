@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- alert component -->
             @include('components.alert-messages')

            {{-- Show to role Admin and Sub Admin --}}
            @hasanyrole(['Super Admin', 'Sub Admin'])
                @include('dashboard.includes.cards')
            @endhasanyrole

            {{-- Show to role User --}}
            @role('User')
                @if (Auth::user()->google_id == NULL || Auth::user()->google_id == '')
                    @include('dashboard.includes.password-notification')
                @endif
                <h2 class="overview-title">Basic Overview</h2>
                <div class="overview-grid">
                    <div class="overview-card">
                        <h4>Number of users</h4>
                        <div class="stat-line"><span>Current</span><span class="value">1</span><span>People</span></div>
                        <div class="total-right">Total 1 People</div>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>Livestream rooms created</h4>
                        <div class="stat-line"><span>Used</span><span class="value"
                                style="color:#facc15">2</span><span>Pieces</span></div>
                        <div class="total-right">Total 3 Pieces</div>
                        <div class="progress-bar">
                            <div class="progress-fill yellow" style="width: 66%;"></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>Livestream accounts</h4>
                        <div class="stat-line"><span>Used</span><span class="value">1</span><span>Pieces</span></div>
                        <div class="total-right">Total 1 Pieces</div>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>Streaming duration</h4>
                        <div class="stat-line"><span>Current</span><span class="value">0</span><span>Minutes</span></div>
                        <div class="total-right">Total 720 Minutes</div>
                        <div class="progress-bar">
                            <div class="progress-fill gray" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>Resource storage</h4>
                        <div class="stat-line"><span>Used</span><span class="value">0</span><span>GB</span></div>
                        <div class="total-right">Total 5 GB</div>
                        <div class="progress-bar">
                            <div class="progress-fill gray" style="width: 0%;"></div>
                        </div>
                        <div class="legend">
                            <span><span class="dot video"></span>Video</span>
                            <span><span class="dot picture"></span>Picture</span>
                            <span><span class="dot audio"></span>Audio</span>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>AI creation</h4>
                        <div class="stat-line"><span>Used</span><span class="value">0</span><span>Times</span></div>
                        <div class="total-right">Total 0 Times</div>
                        <div class="progress-bar">
                            <div class="progress-fill gray" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>AI rewriting</h4>
                        <div class="stat-line"><span>Used</span><span class="value">0</span><span>Times</span></div>
                        <div class="total-right">Total 0 Times</div>
                        <div class="progress-bar">
                            <div class="progress-fill gray" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <h4>Video synthesis duration</h4>
                        <div class="stat-line"><span>Used</span><span class="value">0</span><span>Minutes</span></div>
                        <div class="total-right">Total 30 Minutes</div>
                        <div class="progress-bar">
                            <div class="progress-fill gray" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            @endrole
        </div>
        <!--/. container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
</aside>
<!-- /.control-sidebar -->

<!-- Main Footer -->
@include('dashboard.includes.footer')
</body>

</html>

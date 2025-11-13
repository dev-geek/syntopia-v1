{{--        <footer class="main-footer">--}}
{{--            <div class="footer-content">--}}
{{--                <div class="footer-left">--}}
{{--                    <div class="footer-brand">--}}
{{--                        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp"--}}
{{--                            alt="Syntopia" class="footer-logo">--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <div class="footer-center">--}}
{{--                    <div class="footer-links">--}}
{{--                        <a href="https://live.syntopia.ai/login" target="_blank" class="footer-link">--}}
{{--                            <i class="fas fa-globe"></i> Website--}}
{{--                        </a>--}}
{{--                        <a href="mailto:info@syntopia.ai" class="footer-link">--}}
{{--                            <i class="fas fa-envelope"></i> Contact--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <div class="footer-right">--}}
{{--                    <div class="footer-info">--}}
{{--                        <div class="footer-version">--}}
{{--                            <i class="fas fa-code-branch"></i>--}}
{{--                            <span>v1.0.0</span>--}}
{{--                        </div>--}}
{{--                        <div class="footer-copyright">--}}
{{--                            <i class="fas fa-copyright"></i>--}}
{{--                            <span>2025 Syntopia.</span>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </footer>--}}
        </div>
        <!-- ./wrapper -->

        <!-- Spinner Component -->
        @include('components.spinner-overlay')

        <!-- REQUIRED SCRIPTS -->
        <!-- jQuery -->
        <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
        <!-- Bootstrap -->
        <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <!-- Spinner Utility Script -->
        <script src="{{ asset('js/spinner-utils.js') }}"></script>
        <!-- overlayScrollbars -->
        <script src="{{ asset('plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
        <!-- AdminLTE App -->
        <script src="{{ asset('dist/js/adminlte.js') }}"></script>

        <!-- PAGE PLUGINS -->
        <!-- jQuery Mapael -->
        <script src="{{ asset('plugins/jquery-mousewheel/jquery.mousewheel.js') }}"></script>
        <script src="{{ asset('plugins/raphael/raphael.min.js') }}"></script>
        <script src="{{ asset('plugins/jquery-mapael/jquery.mapael.min.js') }}"></script>
        <script src="{{ asset('plugins/jquery-mapael/maps/usa_states.min.js') }}"></script>
        <!-- ChartJS -->
        <script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
        <!-- DataTables  & Plugins -->
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
        <script src="{{ asset('plugins/jszip/jszip.min.js') }}"></script>
        <script src="{{ asset('plugins/pdfmake/pdfmake.min.js') }}"></script>
        <script src="{{ asset('plugins/pdfmake/vfs_fonts.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
        <!-- AdminLTE App -->
        <script src="{{ asset('dist/js/adminlte.min.js') }}"></script>

        <!-- AdminLTE for demo purposes -->
        <script src="{{ asset('dist/js/demo.js') }}"></script>
        <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
        <script src="{{ asset('dist/js/pages/dashboard2.js') }}"></script>

        <!-- SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- Bootstrap 5 Bundle JS (for Offcanvas) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Password Toggle Functionality -->
        <script src="{{ asset('js/password-toggle.js') }}"></script>

        <!-- Copy to Clipboard Utility -->
        <script src="{{ asset('js/copy-to-clipboard.js') }}"></script>

        <!-- Password Modal Script -->
        <script>
            (function() {
                const spinner = document.getElementById('globalSpinner');
                if (spinner) {
                    let requestCount = 0;
                    function show() { spinner.style.display = 'flex'; }
                    function hide() { spinner.style.display = 'none'; }
                    function inc() { requestCount++; show(); }
                    function dec() { requestCount = Math.max(0, requestCount - 1); if (requestCount === 0) hide(); }

                    // Hook fetch
                    const originalFetch = window.fetch;
                    window.fetch = function() {
                        inc();
                        return originalFetch.apply(this, arguments).finally(dec);
                    };

                    // Hook jQuery AJAX if available
                    if (window.jQuery) {
                        $(document).ajaxStart(function() { inc(); });
                        $(document).ajaxStop(function() { dec(); });
                    }

                    // Show on form submits and internal navigation clicks
                    document.addEventListener('submit', function(e) {
                        const form = e.target;
                        if (form && !form.hasAttribute('data-no-spinner')) {
                            show();
                        }
                    }, true);

                    document.addEventListener('click', function(e) {
                        const link = e.target.closest('a');
                        if (!link) return;
                        const href = link.getAttribute('href');
                        const target = link.getAttribute('target');
                        const noSpinner = link.hasAttribute('data-no-spinner');
                        if (!noSpinner && href && href.startsWith('/') && (!target || target === '_self')) {
                            show();
                        }
                    }, true);

                    window.addEventListener('pageshow', function() { hide(); });
                }
            })();

            function checkPasswordAndAccess() {
                @if (!Auth::user()->hasActiveSubscription())
                    Swal.fire({
                        icon: 'error',
                        title: 'Subscription Required',
                        text: 'You need an active plan to access the software.',
                        showCancelButton: true,
                        confirmButtonText: 'View Plans',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#dc3545'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '{{ route('subscription') }}';
                        }
                    });
                    return;
                @endif
                @if (!Auth::user()->hasValidSubscriberPassword())
                    // User doesn't have a password, show modal
                    showPasswordModal();
                @else
                    // User has a password, redirect to software
                    window.open('{{ route('software.access') }}', '_blank');
                @endif
            }

            function showPasswordModal() {
                console.log('Showing custom password modal...');

                const modalOverlay = document.getElementById('passwordModalOverlay');
                if (!modalOverlay) {
                    console.error('Password modal overlay not found');
                    return;
                }

                // Show the modal
                modalOverlay.style.display = 'flex';
                console.log('Custom password modal shown successfully');

                // Add click event listener to button
                const setPasswordBtn = modalOverlay.querySelector('.btn-set-password');
                if (setPasswordBtn) {
                    console.log('Found Set Password button, adding click listener');
                    setPasswordBtn.addEventListener('click', function(e) {
                        console.log('Set Password button clicked!');
                        e.stopPropagation();
                        window.open('/user/profile', '_self');
                    });
                } else {
                    console.error('Set Password button not found');
                }
            }
        </script>

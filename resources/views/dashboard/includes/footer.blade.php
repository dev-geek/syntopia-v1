        <footer class="main-footer">
            <div class="footer-content">
                <div class="footer-left">
                    <div class="footer-brand">
                        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp"
                            alt="Syntopia" class="footer-logo">
                    </div>
                </div>

                <div class="footer-center">
                    <div class="footer-links">
                        <a href="https://live.syntopia.ai/login" target="_blank" class="footer-link">
                            <i class="fas fa-globe"></i> Website
                        </a>
                        <a href="mailto:info@syntopia.ai" class="footer-link">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                    </div>
                </div>

                <div class="footer-right">
                    <div class="footer-info">
                        <div class="footer-version">
                            <i class="fas fa-code-branch"></i>
                            <span>v1.0.0</span>
                        </div>
                        <div class="footer-copyright">
                            <i class="fas fa-copyright"></i>
                            <span>2025 Syntopia.</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        </div>
        <!-- ./wrapper -->

        @if (Auth::user()->password == NULL || Auth::user()->password == '')
            {{-- Show an undismissable popup to set/change password --}}
            <div class="modal fade password-modal" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="passwordModalLabel">
                                <i class="fas fa-shield-alt me-2"></i>
                                Set/Change Password
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>You haven't set a password yet. Please set a password to continue.</p>
                            <a href="{{ route('password.request') }}" class="btn-set-password">
                                <i class="fas fa-key"></i>
                                Set Password
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- REQUIRED SCRIPTS -->
        <!-- jQuery -->
        <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
        <!-- Bootstrap -->
        <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
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
        @if (Auth::user()->password == NULL || Auth::user()->password == '')
        <script>
            console.log('Password modal script loaded');

            function initPasswordModal() {
                console.log('Initializing password modal...');

                const modalElement = document.getElementById('passwordModal');
                if (!modalElement) {
                    console.error('Password modal element not found');
                    return;
                }

                try {
                    // Try Bootstrap 5
                    if (typeof bootstrap !== 'undefined') {
                        console.log('Using Bootstrap 5');
                        const passwordModal = new bootstrap.Modal(modalElement, {
                            backdrop: 'static',
                            keyboard: false
                        });
                        passwordModal.show();

                        // Prevent modal from being closed
                        modalElement.addEventListener('hide.bs.modal', function (event) {
                            console.log('Modal hide event prevented');
                            event.preventDefault();
                            return false;
                        });
                    } else {
                        // Fallback to jQuery/bootstrap 4
                        console.log('Using jQuery/Bootstrap 4');
                        $(modalElement).modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });

                        // Prevent modal from being closed
                        $(modalElement).on('hide.bs.modal', function (event) {
                            console.log('Modal hide event prevented (jQuery)');
                            event.preventDefault();
                            return false;
                        });
                    }

                    console.log('Password modal shown successfully');
                } catch (error) {
                    console.error('Error showing password modal:', error);
                    // Final fallback
                    $(modalElement).modal('show');
                }
            }

            // Try to show modal when page is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPasswordModal);
            } else {
                initPasswordModal();
            }

            // Also try after a delay to ensure all scripts are loaded
            setTimeout(initPasswordModal, 500);
        </script>
        @endif

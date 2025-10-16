{{-- Bootstrap 5 Responsive Sidebar --}}
<aside>
    <!-- Offcanvas for mobile -->
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
        <div class="offcanvas-body p-0 overflow-auto" style="overflow-y: auto;">
            @include('dashboard.includes.sidebar-content')
                    </div>
                </div>

    <!-- Static sidebar for desktop -->
    <nav class="d-none d-lg-block bg-white border-end vh-100 position-fixed" style="width: 250px; z-index: 1030; overflow-y: scroll;">
        <div class="d-flex flex-column h-100 overflow-auto" style="overflow-y: auto;">
            @include('dashboard.includes.sidebar-content')
            </div>
    </nav>
        </aside>

{{-- Sidebar content partial for reuse --}}
{{-- Create resources/views/dashboard/includes/sidebar-content.blade.php --}}

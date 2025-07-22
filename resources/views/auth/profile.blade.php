@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">
    <section class="content">
        <div class="row justify-content-center mt-2 ">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">My Profile</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>

                    @include('components.alert-messages')

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" autofocus>
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" type="email" class="form-control" name="email" value="{{ $user->email }}" disabled>
                            </div>

                            @if($user->package)
                            <div class="form-group">
                                <label for="package">Package</label>
                                <input id="package" type="text" class="form-control" name="package" value="{{ $user->package->name }}" disabled>
                            </div>
                            @endif

                            @if($user->license_key)
                            <div class="form-group">
                                <label for="license_key">License Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="{{ $user->license_key }}" readonly id="licenseKey">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" data-copy="element" data-copy-element="licenseKey" data-toast="true" data-success-text="License key copied to clipboard!">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="new-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Confirm New Password</label>
                                <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

@include('dashboard.includes.footer')

<script>
    // Show SWAL for success message
    @if(session('success'))
        window.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: @json(session('success')),
                confirmButtonColor: '#3085d6',
            });
        });
    @endif
</script>

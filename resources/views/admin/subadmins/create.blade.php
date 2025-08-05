@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<div class="content-wrapper">
    <section class="content">
        <div class="row justify-content-center mt-2">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Create a Sub Admin</h3>
                    </div>

                    <form method="POST" action="{{ route('admin.sub-admins.store') }}">
                        @csrf

                        <div class="card-body">
                            @if(session('error'))
                                <div class="alert alert-danger">{{ session('error') }}</div>
                            @endif

                            {{-- Email --}}
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" type="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    name="email"
                                    value="{{ old('email') }}">
                                @error('email')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            {{-- Name --}}
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input id="name" type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    name="name"
                                    value="{{ old('name') }}">
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            {{-- Status --}}
                            <div class="form-group">
                                <label>Status</label>
                                <select class="custom-select @error('status') is-invalid @enderror" name="status">
                                    <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Deactive</option>
                                </select>
                                @error('status')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            {{-- Password --}}
                            <div class="form-group">
                                <label for="password">Password</label>
                                <x-password-toggle id="password" name="password"
                                    class="{{ $errors->has('password') ? 'is-invalid' : '' }}" />
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            {{-- Confirm Password --}}
                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <x-password-toggle id="password_confirmation" name="password_confirmation"
                                    class="{{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}" />
                                @error('password_confirmation')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Save') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
    </section>
</div>

@include('dashboard.includes.footer')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: @json(session('success')),
            confirmButtonColor: '#3085d6',
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: @json(session('error')),
            confirmButtonColor: '#d33',
        });
    @endif
</script>

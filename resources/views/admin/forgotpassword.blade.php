@extends('admin.layouts.auth')

@section('title', 'Forgot Password')

@section('body-class', 'login-page')

@section('content')
  <div class="login-box">
    <div class="card card-outline card-primary">

      <div class="card-header text-center">
        <a href="../../index2.html" class="h1"><b>Admin</b> Panel</a>
      </div>
      {{-- Alert removed: now handled by SWAL --}}
      <div class="card-body">
        <p class="login-box-msg">You forgot your password? Here you can easily retrieve a new password.</p>
        <form method="POST" action="{{ route('admin.password.email') }}">
          @csrf
          <div class="input-group mb-3">
            <input id="email" type="email"
              class="form-control @error('email') is-invalid @enderror"
              name="email" value="{{ old('email') }}" required
              autocomplete="email" placeholder="Enter Your email..." autofocus>

            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-envelope"></span>
              </div>
            </div>

            @error('email')
            <span class="invalid-feedback d-block" role="alert">
              <strong>{{ $message }}</strong>
            </span>
            @enderror
          </div>

          <small class="mb-2">If you are Super Admin, answer the security questions.</small>

          <!-- City Field -->
          <label>What is the name of your city?</label>
          <div class="input-group mb-2 mt-1">
            <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" placeholder="Enter your city">
          </div>
          @error('city')
          <span class="invalid-feedback d-block" role="alert">
            <strong>{{ $message }}</strong>
          </span>
          @enderror

          <!-- Pet Field -->
          <label>What is the name of your first pet?</label>
          <div class="input-group mb-2 mt-1">
            <input type="text" name="pet" class="form-control @error('pet') is-invalid @enderror" placeholder="Enter your pet's name">
          </div>
          @error('pet')
          <span class="invalid-feedback d-block" role="alert">
            <strong>{{ $message }}</strong>
          </span>
          @enderror

          <div class="row">
            <div class="col-12 mt-4">
              <button type="submit" class="btn btn-primary btn-block">Request new password</button>
            </div>
          </div>
        </form>

        <p class="mt-3 mb-1">
          <a href="login.html">Login</a>
        </p>
      </div>
      <!-- /.login-card-body -->
    </div>
  </div>
  <!-- /.login-box -->
@endsection

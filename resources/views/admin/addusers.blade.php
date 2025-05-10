@include('admin.includes.header')
@include('admin.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Users</h1>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">

        <!-- Display success message if any -->
        
            
       

          <div class="card">
            <div class="card-header">
              
              <h3 class="card-title">Add Users</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
            @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
            @endif
            <form action="{{ route('add-user-excel') }}" method="POST" enctype="multipart/form-data">

            @csrf
            <div class="form-group">
                <label for="fileUpload">Upload Excel File:</label>
                <input type="file" class="form-control" id="fileUpload" name="excel_file" accept=".xlsx, .xls">
            </div>
            <small  class=" mt-2 mb-2 form-text text-muted">Please upload an Excel file according to the <a href="https://docs.google.com/spreadsheets/d/17BDET5wNaBTuStDFkQnKa8gE-N-5tNUeS9SxYek_OrU/edit?usp=sharing">sample sheet</a>.</small>

            <button type="submit" class="btn btn-primary">Submit</button>
        </form>


                   
             
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
  </section>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->
@include('admin.includes.footer')

<!-- Control Sidebar -->

<!-- /.control-sidebar -->
</div>
 
</body>

</html>
@include('subscription.includes.header')

@include('subscription.includes.nav')


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
 

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">

        <!-- Display success message if any -->
        
            
       
        <div class="overview-wrapper">
          <div class="container">
            <h2 class="overview-title">PLAN - {{ strtoupper($latest_order_package) }}
            </h2>
            <div class="overview-grid">
              <div class="overview-card">
                <h4>Number of users</h4>
                <div class="stat-line"><span>Current</span><span class="value">1</span><span>People</span></div>
                @if($latest_order_package == 'Free')
                <div class="total-right">Total 1 People</div>
                <div class="progress-bar"><div class="progress-fill"></div></div>
              @elseif($latest_order_package == 'Starter')
              <div class="total-right">Total 1 People</div>
              <div class="progress-bar"><div class="progress-fill"></div></div>
              @elseif($latest_order_package == 'Pro')
              <div class="total-right">Total 2 People</div>
              <div class="progress-bar"><div class="progress-fill"></div></div>
              @elseif($latest_order_package == 'Business')
              <div class="total-right">Total 3 People</div>
              <div class="progress-bar"><div class="progress-fill"></div></div>
              @endif 
             
              </div>
      
              <div class="overview-card">
                <h4>Livestream rooms created</h4>
                <div class="stat-line"><span>Used</span><span class="value" style="color:#facc15">2</span><span>Pieces</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 1 Pieces
                @elseif($latest_order_package == 'Starter')
                Total 1 Pieces
                @elseif($latest_order_package == 'Pro')
                Total 3 Pieces
                @elseif($latest_order_package == 'Business')
                Total 1 Pieces
                @endif 
                  </div>
                <div class="progress-bar"><div class="progress-fill yellow" style="width: 66%;"></div></div>
              </div>
      
              <div class="overview-card">
                <h4>Livestream accounts</h4>
                <div class="stat-line"><span>Used</span><span class="value">1</span><span>Pieces</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 1 Pieces
                @elseif($latest_order_package == 'Starter')
                Total 1 Pieces
                @elseif($latest_order_package == 'Pro')
                Total 3 Pieces
                @elseif($latest_order_package == 'Business')
                Total 3 Pieces
                @endif
                  </div>
                <div class="progress-bar"><div class="progress-fill"></div></div>
              </div>
      
              <div class="overview-card">
                <h4>Streaming duration</h4>
                <div class="stat-line"><span>Current</span><span class="value">0</span><span>Minutes</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 10 Minutes
                @elseif($latest_order_package == 'Starter')
                Total 60 Hours
                @elseif($latest_order_package == 'Pro')
                Total 120 Hours
                @elseif($latest_order_package == 'Business')
                Unlimited
                @endif  
                  
                </div>
                <div class="progress-bar"><div class="progress-fill gray" style="width: 0%;"></div></div>
              </div>
      
              <div class="overview-card">
                <h4>Resource storage</h4>
                <div class="stat-line"><span>Used</span><span class="value">0</span><span>GB</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 5 MB
                @elseif($latest_order_package == 'Starter')
                Total 5 GB
                @elseif($latest_order_package == 'Pro')
                Total 5 GB
                @elseif($latest_order_package == 'Business')
                Total 5 GB
                @endif   
                </div>
                <div class="progress-bar"><div class="progress-fill gray" style="width: 0%;"></div></div>
                <div class="legend">
                  <span><span class="dot video"></span>Video</span>
                  <span><span class="dot picture"></span>Picture</span>
                  <span><span class="dot audio"></span>Audio</span>
                </div>
              </div>
      
              <div class="overview-card">
                <h4>AI creation</h4>
                <div class="stat-line"><span>Used</span><span class="value">0</span><span>Times</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 0 Times
                @elseif($latest_order_package == 'Starter')
                Total 10 Times
                @elseif($latest_order_package == 'Pro')
                Total 30 Times
                @elseif($latest_order_package == 'Business')
                Total 90 Times
                @endif 
                  
                  </div>
                <div class="progress-bar"><div class="progress-fill gray" style="width: 0%;"></div></div>
              </div>
      
              <div class="overview-card">
                <h4>AI rewriting</h4>
                <div class="stat-line"><span>Used</span><span class="value">0</span><span>Times</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 0 Times
                @elseif($latest_order_package == 'Starter')
                Total 10 Times
                @elseif($latest_order_package == 'Pro')
                Total 30 Times
                @elseif($latest_order_package == 'Business')
                Total 90 Times
                @endif  
                  
                  </div>
                <div class="progress-bar"><div class="progress-fill gray" style="width: 0%;"></div></div>
              </div>
      
              <div class="overview-card">
                <h4>Video synthesis duration</h4>
                <div class="stat-line"><span>Used</span><span class="value">0</span><span>Minutes</span></div>
                <div class="total-right">
                  @if($latest_order_package == 'Free')
                  Total 5 Minutes
                @elseif($latest_order_package == 'Starter')
                Total 5 Minutes
                @elseif($latest_order_package == 'Pro')
                Total 20 Minutes
                @elseif($latest_order_package == 'Business')
                Total 60 Minutes
                @endif 
                  </div>
                <div class="progress-bar"><div class="progress-fill gray" style="width: 0%;"></div></div>
              </div>
            </div>
          </div>
        </div>
  


                   
             
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
@include('subscription.includes.footer')

<!-- Control Sidebar -->

<!-- /.control-sidebar -->
</div>
 
</body>

</html>
@include('subscription.includes.header')
@include('subscription.includes.nav')

<body>
    
    <!-- cards -->
    <section id="pricing" class="pricing-content section-padding">
        <div class="container">
            <div class="section-title text-center mt-5">
                <h2>Pricing Plans</h2>
                <p>It is a long established fact that a reader will be distracted by the readable content of a page when
                    looking at its layout.</p>
            </div>
            <div class="row justify-content-center align-items-center text-center">
                <div class="col-lg-6 col-sm-6 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s"
                    data-wow-offset="0"
                    style="visibility: visible; animation-duration: 1s; animation-delay: 0.1s; animation-name: fadeInUp;">
                    <div class="pricing_design">
                        <div class="single-pricing">
                            <div class="price-head">
                                <h2>@if($plan==100)
                                    Personal
                                    @elseif($plan==0)
                                    Starter
                                    @endif
                                </h2>
                                <h1>{{$plan}}</h1>
                                <span>/Monthly</span>
                            </div>
                            <ul>
                                @if($plan==100)
                                    <li><b>15</b> website</li>
                                <li><b>500GB</b> Disk Space</li>
                                <li><b>100</b> Email</li>
                                <li><b>500GB</b> Bandwidth</li>
                                <li><b>100</b> Subdomains</li>
                                <li><b>Unlimited</b> Support</li>
                                
                                @elseif($plan==0)
                                <li><b>1</b> website</li>
                                <li><b>50GB</b> Disk Space</li>
                                <li><b>5</b> Email</li>
                                <li><b>100GB</b> Bandwidth</li>
                                <li><b>1</b> Subdomains</li>
                                <li><b>Unlimited</b> Support</li>
                                @endif
                            </ul>
                            <div class="pricing-price">

                            </div>
                            <a href="{{ route('confirm', ['plan' => $plan]) }}" class="price_btn">Confirm</a>
                        </div>
                    </div>
                </div>
                <!--- END COL -->
            </div>
            <!--- END ROW -->
        </div>
        <!--- END CONTAINER -->
    </section>
    <!-- End Pricing Table Section -->
</body>

</html>
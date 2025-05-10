@include('subscription.includes.header')
@include('subscription.includes.nav')

<body>

 
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
                                <h2> 
                                    {{$message}}
                                    
                                   
                                </h2>
                                 
                            <div class="pricing-price">

                            </div>
                            <a href="{{ url('/') }}" class="price_btn">Go Back to Home</a>
                        </div>
                    </div>
                </div>
                <!--- END COL -->
            </div>
            <!--- END ROW -->
        </div>
        <!--- END CONTAINER -->
    </section>
    
</body>

</html>
{{-- FirstPromoter Referral Tracking Component --}}
@if(Auth::check() && Auth::user()->email)
    <script>
        (function() {
            var email = @json(Auth::user()->email);
            function trackReferral() {
                if (typeof fpr !== 'undefined') {
                    fpr("referral", {email: email});
                } else {
                    setTimeout(trackReferral, 50);
                }
            }
            trackReferral();
        })();
    </script>
@elseif(session('email'))
    <script>
        (function() {
            var email = @json(session('email'));
            function trackReferral() {
                if (typeof fpr !== 'undefined') {
                    fpr("referral", {email: email});
                } else {
                    setTimeout(trackReferral, 50);
                }
            }
            trackReferral();
        })();
    </script>
@endif


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paddle Checkout - Pricing Plans</title>

  <style>
   .sc-fgyOaY.kEMhvF {
    display: none;
}
.kEMhvF {
    margin-top: 16px;
    -webkit-box-align: center;
    align-items: center;
    position: relative;
    gap: 0px 8px;
    display: none;
}
    /* Tailwind CSS Styling */
    .page-container {
      max-width: 900px;
      margin: 2em auto;
      text-align: center;
      padding-left: 1em;
      padding-right: 1em;
    }

    .grid {
      display: block;
    }

    .grid > * {
      padding: 1rem;
    }

    @media (min-width: 768px) {
      .grid {
        display: grid;
        grid-auto-rows: 1fr;
        grid-template-columns: 1fr 1fr 1fr;
      }
    }
  </style>

  <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>

</head>
<body>

<!-- Pricing Container -->
<div class="max-w-6xl mx-auto px-4 py-8">
  
  <!-- Billing Toggle -->
  <div class="text-center mb-8">
    <div class="inline-flex items-center bg-gray-100 rounded-lg p-1">
      <button id="monthlyBtn" class="px-4 py-2 rounded-md text-sm bg-white" onclick="updateBillingCycle('month')">Monthly</button>
      <button id="yearlyBtn" class="px-4 py-2 rounded-md text-sm" onclick="updateBillingCycle('year')">Yearly (Save 20%)</button>
    </div>
  </div>

  <!-- Pricing Grid -->
  <div class="grid md:grid-cols-3 gap-8">
    <!-- Starter Plan -->
    <div class="bg-white rounded-lg shadow-lg p-8">
      <h3 class="text-xl font-semibold mb-4">Starter</h3>
      <div class="mb-4">
        <span id="starter-price" class="text-4xl font-bold">$10.00</span>
        <span class="text-gray-500 ml-1">/month</span>
      </div>
      <button 
            onclick="openCheckout('starter')" 
            class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700 transition-colors" 
            data-success-url="http://your-example-url.com"
      >
        Get started
      </button>
    </div>



   
  </div>

  <!-- Country Selector -->
  <div class="mt-12 p-6 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="md:flex md:items-center md:justify-between">
      <div class="md:flex-1 md:pr-8">
        <h3 class="text-lg font-semibold mb-2">Explore customer localization</h3>
        <p class="mb-4 md:mb-0 text-sm text-gray-600">
          Test how price localization works by changing the country. You can pass a country, IP address, or existing customer ID to <code class="bg-blue-100 px-1 py-0.5 rounded">Paddle.PricePreview()</code> to get localized prices. In live implementations, we recommend using an IP address.
        </p>
      </div>

      <div class="text-center md:text-right md:flex-shrink-0" style="display:none">
        <select id="countrySelect" class="px-4 py-2 rounded-lg border border-gray-300">
          <option value="US">🇺🇸 United States</option>
          <option value="GB">🇬🇧 United Kingdom</option>
          <option value="DE">🇩🇪 Germany</option>
          <option value="FR">🇫🇷 France</option>
          <option value="AU">🇦🇺 Australia</option>
        </select>
      </div>
    </div>
  </div>

</div>

<footer>
  <hr>
  <p><small>
    For the tutorial, check out: 
    <a href="https://developer.paddle.com/build/checkout/build-overlay-checkout?utm_source=dx&utm_medium=codepen">Build an overlay checkout</a> - 
    <a href="https://developer.paddle.com/?utm_source=dx&utm_medium=codepen">
      developer.paddle.com
    </a>
    </small></p>
</footer>

<script>
// Configuration
// Replace with values from your sandbox account
const CONFIG = {
  clientToken: "test_9fd4a03f2864e04fb2abece9096", // Replace with your actual token
  prices: {
    starter: {
      month: "pri_01jtbgcyxtrr50xwybk00f2pzj",
      year: "pri_01jtbgcyxtrr50xwybk00f2pzj"
    },
    pro: {
      month: "pri_01jtbgcyxtrr50xwybk00f2pzj",
      year: "pri_01jtbgcyxtrr50xwybk00f2pzj"
    }
  }
};

// UI elements
const monthlyBtn = document.getElementById("monthlyBtn");
const yearlyBtn = document.getElementById("yearlyBtn");
const countrySelect = document.getElementById("countrySelect");
const starterPrice = document.getElementById("starter-price");
const proPrice = document.getElementById("pro-price");

// State
let currentBillingCycle = "month";
let currentCountry = "US";
let paddleInitialized = false;

// Initialize Paddle
function initializePaddle() {
  try {
    Paddle.Environment.set("sandbox");
    Paddle.Initialize({
      token: CONFIG.clientToken,
      eventCallback: function (event) {
        console.log("Paddle event:", event);
      }
      
    });
    paddleInitialized = true;
    updatePrices();
    
  } catch (error) {
    console.error("Initialization error:", error);
  }
  
}

// Update billing cycle
function updateBillingCycle(cycle) {
  currentBillingCycle = cycle;
  monthlyBtn.classList.toggle("bg-white", cycle === "month");
  yearlyBtn.classList.toggle("bg-white", cycle === "year");
  updatePrices();
}

// Update prices
async function updatePrices() {
  if (!paddleInitialized) {
    console.log("Paddle not initialized yet");
    return;
  }

  try {
    const request = {
      items: [
        {
          quantity: 1,
          priceId: CONFIG.prices.starter[currentBillingCycle]
        },
        {
          quantity: 1,
          priceId: CONFIG.prices.pro[currentBillingCycle]
        }
      ],
      address: {
        countryCode: currentCountry
      }
    };

    console.log("Fetching prices:", request);
    const result = await Paddle.PricePreview(request);

    result.data.details.lineItems.forEach((item) => {
      const price = item.formattedTotals.subtotal;
      if (item.price.id === CONFIG.prices.starter[currentBillingCycle]) {
        starterPrice.textContent = price;
      } else if (item.price.id === CONFIG.prices.pro[currentBillingCycle]) {
        proPrice.textContent = price;
      }
    });
    console.log("Prices updated:", result);
  } catch (error) {
    console.error(`Error fetching prices: ${error.message}`);
  }
}

// Open checkout
function openCheckout(plan) {
  if (!paddleInitialized) {
    console.log("Paddle not initialized yet");
    return;
  }

  try {
    Paddle.Checkout.open({
      items: [
        {
          priceId: CONFIG.prices[plan][currentBillingCycle],
          quantity: 1
        }
      ],
      settings: {
        theme: "light",
        displayMode: "overlay",
        variant: "one-page",        
    successUrl: "https://paddle.com/thankyou"
      }
    });
  } catch (error) {
    console.error(`Checkout error: ${error.message}`);
  }
}

// Event Listeners
countrySelect.addEventListener("change", (e) => {
  currentCountry = e.target.value;
  updatePrices();
});

// Initialize on page load
document.addEventListener("DOMContentLoaded", initializePaddle);
</script>

</body>
</html>

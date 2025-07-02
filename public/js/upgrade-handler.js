class UpgradeHandler {
    constructor() {
      this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
      this.apiToken = localStorage.getItem("api_token")
    }
  
    async checkEligibility() {
      try {
        const response = await fetch("/api/subscription/upgrade/eligibility", {
          headers: {
            Authorization: `Bearer ${this.apiToken}`,
            Accept: "application/json",
            "X-CSRF-TOKEN": this.csrfToken,
          },
        })
  
        if (!response.ok) {
          throw new Error("Failed to check upgrade eligibility")
        }
  
        return await response.json()
      } catch (error) {
        console.error("Eligibility check failed:", error)
        throw error
      }
    }
  
    async processUpgrade(packageName) {
      try {
        const response = await fetch(`/api/subscription/upgrade/${packageName}`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${this.apiToken}`,
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": this.csrfToken,
          },
        })
  
        if (!response.ok) {
          const errorData = await response.json()
          throw new Error(errorData.error || "Upgrade failed")
        }
  
        return await response.json()
      } catch (error) {
        console.error("Upgrade processing failed:", error)
        throw error
      }
    }
  
    showUpgradeModal(upgrades, currentPackage) {
      const modal = document.createElement("div")
      modal.className = "upgrade-modal"
      modal.innerHTML = `
              <div class="upgrade-modal-content">
                  <div class="upgrade-modal-header">
                      <h2>Upgrade Your Subscription</h2>
                      <button class="close-modal">&times;</button>
                  </div>
                  <div class="current-plan-info">
                      <p>Current Plan: <strong>${currentPackage.name}</strong> ($${currentPackage.price})</p>
                  </div>
                  <div class="upgrade-options">
                      ${upgrades
                        .map(
                          (upgrade) => `
                          <div class="upgrade-option">
                              <h3>${upgrade.name}</h3>
                              <p class="upgrade-price">+$${upgrade.upgrade_price} upgrade fee</p>
                              <p class="total-price">New price: $${upgrade.price}/${upgrade.duration}</p>
                              <ul class="upgrade-features">
                                  ${upgrade.features.map((feature) => `<li>${feature}</li>`).join("")}
                              </ul>
                              <button class="upgrade-btn" data-package="${upgrade.name.toLowerCase()}">
                                  Upgrade to ${upgrade.name}
                              </button>
                          </div>
                      `,
                        )
                        .join("")}
                  </div>
              </div>
          `
  
      document.body.appendChild(modal)
  
      // Add event listeners
      modal.querySelector(".close-modal").addEventListener("click", () => {
        document.body.removeChild(modal)
      })
  
      modal.querySelectorAll(".upgrade-btn").forEach((btn) => {
        btn.addEventListener("click", async (e) => {
          const packageName = e.target.dataset.package
          await this.handleUpgradeClick(packageName)
        })
      })
    }
  
    async handleUpgradeClick(packageName) {
      try {
        // Show loading state
        const btn = document.querySelector(`[data-package="${packageName}"]`)
        const originalText = btn.textContent
        btn.disabled = true
        btn.textContent = "Processing..."
  
        const result = await this.processUpgrade(packageName)
  
        if (result.checkout_url) {
          // PayProGlobal - open checkout in new window
          window.open(result.checkout_url, "_blank", "width=800,height=600")
          this.showMessage("Please complete the payment in the new window", "info")
        } else if (result.success) {
          // FastSpring/Paddle - upgrade completed
          this.showMessage(`Successfully upgraded to ${result.new_package}!`, "success")
          setTimeout(() => {
            window.location.reload()
          }, 2000)
        }
      } catch (error) {
        this.showMessage(error.message, "error")
  
        // Reset button state
        const btn = document.querySelector(`[data-package="${packageName}"]`)
        btn.disabled = false
        btn.textContent = btn.textContent.replace("Processing...", "Upgrade to")
      }
    }
  
    showMessage(message, type = "info") {
      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: type === "success" ? "Success" : type === "error" ? "Error" : "Info",
          text: message,
          icon: type,
          confirmButtonText: "OK",
        })
      } else {
        alert(message)
      }
    }
  }
  
  // Initialize upgrade handler
  window.upgradeHandler = new UpgradeHandler()
  
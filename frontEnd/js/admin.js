
const API = "http://localhost/bloomify_FINAL/bloomify_final/api";
(async function initAdmin() {

  try {
    const res = await fetch(`${API}/auth.php?action=check`, {
      credentials: "include"
    });

    const d = await res.json();

    // HARD SAFE CHECK (prevents crash loop)
    if (!d || !d.logged_in || !d.user) {
      window.location.replace("login.html");
      return;
    }

    if (d.user.role !== "admin") {
      window.location.replace("index.html");
      return;
    }

    // success → load once
    loadStats();
    loadOrders();
    loadProducts();
    loadCustomers();

  } catch (e) {
    console.error("Auth failed", e);
    window.location.replace("login.html");
  }

})();
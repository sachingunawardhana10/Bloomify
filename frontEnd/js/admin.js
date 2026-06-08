const API = "http://localhost/bloomify_FINAL/bloomify_final/api";

async function checkAuth() {
  const res = await fetch(`${API}/auth.php?action=check`, {
    credentials: "include"
  });

  const data = await res.json();

  if (!data.logged_in || data.user.role !== "admin") {
    window.location.href = "login.html";
  }
}

/* REAL TIME STATS */
async function loadStats() {
  try {
    const res = await fetch(`${API}/admin.php?action=stats`, {
      credentials: "include"
    });

    const text = await res.text();
    console.log("STATS RAW:", text);

    const data = JSON.parse(text);

    if (!data.stats) {
      console.error("NO STATS OBJECT:", data);
      return;
    }

    document.getElementById("stat-orders").innerText = data.stats.orders ?? 0;
    document.getElementById("stat-products").innerText = data.stats.products ?? 0;
    document.getElementById("stat-revenue").innerText = data.stats.revenue ?? 0;

  } catch (err) {
    console.error("STATS ERROR:", err);
  }
}

/* ORDERS */
async function loadOrders() {
  try {
    const res = await fetch(`${API}/admin.php?action=orders`, {
      credentials: "include"
    });

    const text = await res.text();
    console.log("ORDERS RAW:", text);

    const data = JSON.parse(text);

    if (!Array.isArray(data.orders)) {
      console.error("ORDERS NOT ARRAY:", data);
      return;
    }

    document.getElementById("orders-body").innerHTML =
      data.orders.map(o => `
        <tr>
          <td>${o.id ?? "-"}</td>
          <td>${o.name ?? "-"}</td>
          <td>${o.total ?? "-"}</td>
          <td>${o.status ?? "-"}</td>
        </tr>
      `).join("");

  } catch (err) {
    console.error("ORDERS ERROR:", err);
  }
}

/* PRODUCTS */
async function loadProducts() {
  try {
    const res = await fetch(`${API}/admin.php?action=products`, {
      credentials: "include"
    });

    const data = await res.json();

    if (!Array.isArray(data.products)) {
      console.error("PRODUCTS ERROR:", data);
      return;
    }

    document.getElementById("products-body").innerHTML =
      data.products.map(p =>
        `<tr>
          <td>${p.emoji ?? "🌸"} ${p.name ?? "-"}</td>
          <td>${p.price ?? "-"}</td>
        </tr>`
      ).join("");

  } catch (err) {
    console.error("PRODUCTS ERROR:", err);
  }
}

/* TAB SWITCH */
function switchTab(tab) {
  document.getElementById("ordersTab").style.display = "none";
  document.getElementById("productsTab").style.display = "none";
  document.getElementById("usersTab").style.display = "none";

  document.getElementById(tab + "Tab").style.display = "block";
}

/* INIT */
async function init() {
  await checkAuth();

  loadStats();
  loadOrders();
  loadProducts();
  loadUsers();

  setInterval(() => {
    loadStats();
  }, 3000);
}

init();
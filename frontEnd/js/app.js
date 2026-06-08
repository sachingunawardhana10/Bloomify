fetch("http://localhost/bloomify_FINAL/bloomify_final/api/auth.php?action=logout", {
  credentials: "include"
});

// ---------------- CART ----------------
let cart = JSON.parse(localStorage.getItem("cart")) || [];

function saveCart() {
  localStorage.setItem("cart", JSON.stringify(cart));
  updateCartCount(cart.reduce((a, b) => a + b.qty, 0));
}

function updateCartCount(n) {
  document.querySelectorAll("#cart-count").forEach(el => {
    el.textContent = n;
    el.style.display = n > 0 ? "inline-block" : "none";
  });
}

// ---------------- ADD TO CART ----------------
async function addToCart(btn, flowerId) {
  try {
    btn.disabled = true;

    const res = await fetch(`${API}/cart.php?action=add`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        flower_id: flowerId
      })
    });

    const text = await res.text();

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Server returned:", text);
      throw new Error("Invalid JSON from server");
    }

    if (data.success) {
      btn.innerHTML = "✔ Added";
      btn.classList.add("added");
    } else {
      alert(data.message || "Failed to add to cart");
    }

  } catch (err) {
    console.error("Error adding to cart:", err);
    alert("Error adding to cart. Check console.");
  } finally {
    btn.disabled = false;
  }
}



window.showToast = function(message, type = "success") {

  let container = document.getElementById("toast-container");

  if (!container) {
    container = document.createElement("div");
    container.id = "toast-container";
    container.style.position = "fixed";
    container.style.top = "20px";
    container.style.right = "20px";
    container.style.zIndex = "99999";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.textContent = message;

  toast.style.padding = "12px 16px";
  toast.style.marginBottom = "10px";
  toast.style.borderRadius = "10px";
  toast.style.fontSize = "13px";
  toast.style.color = "#fff";

  toast.style.background =
    type === "success" ? "#28a745" :
    type === "error" ? "#dc3545" : "#333";

  container.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 2500);
};

// ---------------- LOAD CART ----------------
function getCart() {
  return cart;
}

function clearCart() {
  cart = [];
  saveCart();
}

function handleLogout() {
  fetch(`${API}/auth.php?action=logout`, {
    method: "GET",
    credentials: "include"
  })
  .then(() => {
    sessionStorage.clear();
    localStorage.removeItem("cart");
    window.location.replace("login.html");
  });
}


// init
updateCartCount(cart.reduce((a, b) => a + b.qty, 0));
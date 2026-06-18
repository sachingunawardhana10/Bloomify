// ======================
// API FETCH WRAPPER
// ======================
async function apiFetch(endpoint, options = {}) {
  const settings = {
    credentials: 'include',
    cache: 'no-store',
    ...options,
    headers: {
      ...(options.headers || {})
    }
  };

  const response = await fetch(`${window.API}${endpoint}`, settings);
  const text = await response.text();

  let data;

  try {
    data = text ? JSON.parse(text) : {};
  } catch (error) {
    console.error('Invalid server response:', text);
    throw new Error('Server returned non-JSON. Check PHP errors.');
  }

  if (!response.ok && !data.message) {
    data.message = `Request failed with status ${response.status}`;
  }

  return { response, data };
}

// ======================
// HELPERS
// ======================
function money(value) {
  return `$${Number(value || 0).toFixed(2)}`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function updateCartCount(count) {
  document.querySelectorAll('#cart-count').forEach(el => {
    el.textContent = count;
    el.style.display = count > 0 ? 'inline-flex' : 'none';
  });
}

// ======================
// TOAST
// ======================
function showToast(message, type = 'success') {
  let container = document.getElementById('toast-container');

  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;

  container.appendChild(toast);

  setTimeout(() => toast.remove(), 2800);
}

// ======================
// SESSION CHECK
// ======================
async function checkSession() {
  try {
    const { data } = await apiFetch('/auth.php?action=check');
    return data;
  } catch (error) {
    return { logged_in: false, user: null };
  }
}

// ======================
// NAVBAR UPDATE
// ======================
async function refreshNavbar() {
  const session = await checkSession();

  const loginLink = document.getElementById('nav-login-link');
  const userBox = document.getElementById('nav-user-info');
  const userName = document.getElementById('nav-user-name');
  const adminLink = document.getElementById('nav-admin-link');

  if (session.logged_in) {
    if (loginLink) loginLink.style.display = 'none';
    if (userBox) userBox.style.display = 'flex';
    if (userName) userName.textContent = session.user.name;
    if (adminLink) adminLink.style.display = session.user.role === 'admin' ? 'list-item' : 'none';

    try {
      const { data } = await apiFetch('/cart.php?action=count');
      updateCartCount(data.success ? data.count : 0);
    } catch {
      updateCartCount(0);
    }

  } else {
    if (loginLink) loginLink.style.display = 'list-item';
    if (userBox) userBox.style.display = 'none';
    if (adminLink) adminLink.style.display = 'none';
    updateCartCount(0);
  }

  return session;
}

// ======================
// ADD TO CART
// ======================
// NOTE: varietyId is now required — every flower has at least one variety
// (see flower_varieties table), so a flower id alone is no longer enough
// to price or stock-check the item.
async function addToCart(button, flowerId, varietyId, quantity = 1) {
  const originalText = button ? button.innerHTML : '';

  try {
    if (button) {
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding';
    }

    const { response, data } = await apiFetch('/cart.php?action=add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ flower_id: flowerId, variety_id: varietyId, quantity })
    });

    if (response.status === 401) {
      showToast('Please login first.', 'error');
      setTimeout(() => window.location.href = 'login.html', 700);
      return false;
    }

    if (!data.success) {
      showToast(data.message || 'Failed to add to cart.', 'error');
      return false;
    }

    updateCartCount(data.count || 0);
    showToast('Added to cart');

    if (button) {
      button.innerHTML = '<i class="fa-solid fa-check"></i> Added';
      setTimeout(() => button.innerHTML = originalText, 900);
    }

    return true;

  } catch (error) {
    showToast('Network error', 'error');
    return false;

  } finally {
    if (button) button.disabled = false;
  }
}

// ======================
// LOGOUT
// ======================
async function handleLogout() {
  try {
    await apiFetch('/auth.php?action=logout');
  } finally {
    window.location.href = 'login.html';
  }
}

// ======================
// INIT NAVBAR
// ======================
document.addEventListener('DOMContentLoaded', refreshNavbar);

async function loadFlowers() {
  try {
    console.log("Loading flowers...");

    const { data } = await apiFetch('/products.php?action=flowers');

    console.log("API response:", data);

    const container = document.getElementById("flowerContainer");

    if (!container) {
      console.error("flowerContainer does not exist!");
      return;
    }

    container.innerHTML = "";

    data.data.forEach(flower => {
      container.innerHTML += `
        <div class="flower-card">
          <img 
            src="${flower.image}" 
            alt="${flower.name}"
          >

          <div class="details">
            <h3>
              ${flower.emoji} ${flower.name}
            </h3>

            <p>
              from ${money(flower.price)} • ${flower.stock} left
            </p>
          </div>
        </div>
      `;
    });

    console.log("Flowers rendered successfully");

  } catch (error) {
    console.error("Flower loading failed:", error);
  }
}

document.addEventListener("DOMContentLoaded", loadFlowers);

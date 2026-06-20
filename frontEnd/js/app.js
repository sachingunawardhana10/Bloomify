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
  return `Rs. ${Number(value || 0).toLocaleString('en-LK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
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
async function addToCart(event = null, flowerId, varietyId = null, quantity = 1) {
  if (event && event.preventDefault) {
    event.preventDefault();
  }

  try {
    const payload = {
      flower_id: Number(flowerId),
      variety_id: varietyId !== null ? Number(varietyId) : null,
      quantity: Number(quantity || 1)
    };

    const { data } = await apiFetch('/cart.php?action=add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!data.success) {
      showToast(data.message || 'Could not add to cart.', 'error');
      console.error('Cart add failed:', data);
      return false;
    }

    showToast(data.message || 'Added to cart.', 'success');

    if (typeof updateCartCount === 'function') {
      await updateCartCount();
    }

    return true;
  } catch (error) {
    console.error('Add to cart error:', error);
    showToast('Could not add to cart.', 'error');
    return false;
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
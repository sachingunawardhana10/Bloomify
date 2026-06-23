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

async function updateCartCount(count = null) {
  let finalCount = Number(count || 0);

  if (count === null || count === undefined) {
    try {
      const { data } = await apiFetch('/cart.php?action=count');
      finalCount = data.success ? Number(data.count || 0) : 0;
    } catch (error) {
      finalCount = 0;
    }
  }

  document.querySelectorAll('#cart-count').forEach(el => {
    el.textContent = finalCount;
    el.style.display = finalCount > 0 ? 'inline-flex' : 'none';
  });
}

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

async function checkSession() {
  try {
    const { data } = await apiFetch('/auth.php?action=check');
    return data;
  } catch (error) {
    return { logged_in: false, user: null };
  }
}

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
    if (adminLink) {
      adminLink.style.display = session.user.role === 'admin' ? 'list-item' : 'none';
    }

    await updateCartCount();
  } else {
    if (loginLink) loginLink.style.display = 'list-item';
    if (userBox) userBox.style.display = 'none';
    if (adminLink) adminLink.style.display = 'none';

    await updateCartCount(0);
  }

  return session;
}

async function addToCart(eventOrFlowerId = null, flowerId = null, varietyId = null, quantity = 1) {
  

  let realFlowerId = flowerId;
  let realVarietyId = varietyId;
  let realQuantity = quantity;

  if (eventOrFlowerId && typeof eventOrFlowerId.preventDefault === 'function') {
    eventOrFlowerId.preventDefault();
  }

  if (
    typeof eventOrFlowerId === 'number' ||
    typeof eventOrFlowerId === 'string'
  ) {
    realFlowerId = eventOrFlowerId;
    realVarietyId = flowerId ?? null;
    realQuantity = varietyId ?? 1;
  }

  realFlowerId = Number(realFlowerId);
  realQuantity = Number(realQuantity || 1);

  if (!realFlowerId || realFlowerId <= 0) {
    console.error('Invalid flower ID:', {
      eventOrFlowerId,
      flowerId,
      varietyId,
      quantity
    });

    showToast('Invalid flower item.', 'error');
    return false;
  }

  try {
    const payload = {
      flower_id: realFlowerId,
      variety_id: realVarietyId !== null && realVarietyId !== undefined
        ? Number(realVarietyId)
        : null,
      quantity: realQuantity
    };

    const { data } = await apiFetch('/cart.php?action=add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!data.success) {
      console.error('Cart add failed:', data);
      showToast(data.message || 'Could not add to cart.', 'error');
      return false;
    }

    showToast(data.message || 'Added to cart.', 'success');

    await updateCartCount();

    return true;
  } catch (error) {
    console.error('Add to cart error:', error);
    showToast('Could not add to cart.', 'error');
    return false;
  }
}

async function handleLogout() {
  try {
    await apiFetch('/auth.php?action=logout');
  } finally {
    window.location.href = 'login.html';
  }
}

// Carousel functionality
let currentSlideIndex = 1;
let carouselInterval = null;

function showSlide(n) {
  const slides = document.querySelectorAll('.carousel-slide');
  const dots = document.querySelectorAll('.dot');

  if (n > slides.length) {
    currentSlideIndex = 1;
  }
  if (n < 1) {
    currentSlideIndex = slides.length;
  }

  slides.forEach(slide => slide.classList.remove('active'));
  dots.forEach(dot => dot.classList.remove('active'));

  if (slides[currentSlideIndex - 1]) {
    slides[currentSlideIndex - 1].classList.add('active');
  }
  if (dots[currentSlideIndex - 1]) {
    dots[currentSlideIndex - 1].classList.add('active');
  }
}

function currentSlide(n) {
  clearInterval(carouselInterval);
  currentSlideIndex = n;
  showSlide(currentSlideIndex);
  startCarousel();
}

function nextSlide() {
  currentSlideIndex++;
  showSlide(currentSlideIndex);
}

function startCarousel() {
  carouselInterval = setInterval(() => {
    nextSlide();
  }, 4000); // Change slide every 4 seconds
}

document.addEventListener('DOMContentLoaded', function() {
  refreshNavbar();
  
  // Initialize carousel if it exists
  const carousel = document.querySelector('.carousel');
  if (carousel) {
    showSlide(currentSlideIndex);
    startCarousel();
  }
});
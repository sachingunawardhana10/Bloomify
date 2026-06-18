let editingProductId = 0;
const adminProducts = new Map();

let adminRealtimeBusy = false;
let lastStatsSignature = '';
let lastOrdersSignature = '';
let lastProductsSignature = '';
let lastUsersSignature = '';
let lastMessagesSignature = '';

async function requireAdmin() {
  const session = await checkSession();

  if (!session.logged_in || session.user.role !== 'admin') {
    window.location.href = 'login.html';
    return false;
  }

  const display = document.getElementById('admin-name-display');

  if (display) {
    display.textContent = session.user.name;
  }

  return true;
}

function switchTab(tab, button) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

  document.getElementById(`tab-${tab}`).classList.add('active');

  if (button) {
    button.classList.add('active');
  }
}

async function loadStats(silent = false) {
  try {
    const { data } = await apiFetch('/admin.php?action=stats');

    if (!data.success) {
      if (!silent) showToast(data.message || 'Stats failed.', 'error');
      return;
    }

    const signature = JSON.stringify(data.stats);

    if (signature === lastStatsSignature && silent) {
      return;
    }

    lastStatsSignature = signature;

    document.getElementById('stat-orders').textContent = data.stats.orders;
    document.getElementById('stat-products').textContent = data.stats.products;
    document.getElementById('stat-customers').textContent = data.stats.customers;
    document.getElementById('stat-revenue').textContent = money(data.stats.revenue);
  } catch (error) {
    console.error(error);
    if (!silent) showToast('Failed to load stats.', 'error');
  }
}

async function loadOrders(silent = false) {
  try {
    const { data } = await apiFetch('/admin.php?action=orders');
    const body = document.getElementById('orders-body');

    if (!data.success) {
      if (!silent) {
        body.innerHTML = '<tr><td colspan="6" class="empty-row">Could not load orders.</td></tr>';
      }
      return;
    }

    const orders = data.orders || [];
    const signature = JSON.stringify(orders);

    if (signature === lastOrdersSignature && silent) {
      return;
    }

    lastOrdersSignature = signature;

    if (!orders.length) {
      body.innerHTML = '<tr><td colspan="6" class="empty-row">No orders yet.</td></tr>';
      return;
    }

    body.innerHTML = orders.map(order => `
      <tr>
        <td>#${order.id}</td>

        <td>
          <strong>${escapeHtml(order.name)}</strong><br>
          <small>${escapeHtml(order.email)}</small>
        </td>

        <td>${money(order.total)}</td>

        <td>${escapeHtml(order.created_at)}</td>

        <td>
          <select onchange="updateOrder(${order.id}, this.value)">
            ${['pending', 'processing', 'delivered', 'cancelled'].map(status => `
              <option value="${status}" ${status === order.status ? 'selected' : ''}>
                ${status}
              </option>
            `).join('')}
          </select>
        </td>

        <td>
          <strong>${escapeHtml(order.payment_method || '-')}</strong><br>
          <small>${escapeHtml(order.payment_status || '-')}</small>
        </td>

        <td>
          ${renderOrderDetails(order)}
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error(error);

    if (!silent) {
      document.getElementById('orders-body').innerHTML =
        '<tr><td colspan="6" class="empty-row">Failed to load orders.</td></tr>';
    }
  }
}

function renderOrderDetails(order) {
  const details = [];

  if (order.cod_recipient_name) details.push(`<strong>${escapeHtml(order.cod_recipient_name)}</strong>`);
  if (order.cod_phone) details.push(escapeHtml(order.cod_phone));
  if (order.cod_address) details.push(escapeHtml(order.cod_address));
  if (order.cod_city) details.push(escapeHtml(order.cod_city));
  if (order.cod_delivery_time) details.push(`Time: ${escapeHtml(order.cod_delivery_time)}`);
  if (order.notes) details.push(`Notes: ${escapeHtml(order.notes)}`);

  return details.length ? details.join('<br>') : '-';
}

async function updateOrder(id, status) {
  const { data } = await apiFetch('/admin.php?action=update-order', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, status })
  });

  showToast(
    data.message || (data.success ? 'Order updated.' : 'Update failed.'),
    data.success ? 'success' : 'error'
  );

  await Promise.all([
    loadStats(false),
    loadOrders(false)
  ]);
}

async function loadProducts(silent = false) {
  try {
    const { data } = await apiFetch('/admin.php?action=products');
    const body = document.getElementById('products-body');

    if (!data.success) {
      if (!silent) {
        body.innerHTML = '<tr><td colspan="6" class="empty-row">Could not load flowers.</td></tr>';
      }
      return;
    }

    const products = data.products || [];
    const signature = JSON.stringify(products);

    if (signature === lastProductsSignature && silent) {
      return;
    }

    lastProductsSignature = signature;
    adminProducts.clear();

    products.forEach(product => {
      adminProducts.set(product.id, product);
    });

    if (!products.length) {
      body.innerHTML = '<tr><td colspan="6" class="empty-row">No flowers found.</td></tr>';
      return;
    }

    body.innerHTML = products.map(product => `
  <tr>
    <td class="product-name">
      <div class="admin-product-box">
        <img 
          class="admin-product-thumb" 
          src="${escapeHtml(product.image || 'images/flowers/default.jpg')}" 
          alt="${escapeHtml(product.name)}"
          onerror="this.src='images/flowers/default.jpg';"
        >

        <div>
          <strong>${escapeHtml(product.name)}</strong><br>
          <small>${escapeHtml(product.image || '-')}</small>
        </div>
      </div>
    </td>

    <td>${money(product.price)}</td>

    <td>${escapeHtml(product.tag || '-')}</td>

    <td>${escapeHtml(product.meaning)}</td>

    <td>${product.stock}</td>

    <td>
      <button class="small-btn" onclick="editProduct(${product.id})">Edit</button>
      <button class="small-btn danger" onclick="deleteProduct(${product.id})">Delete</button>
    </td>
  </tr>
`).join('');
  } catch (error) {
    console.error(error);

    if (!silent) {
      document.getElementById('products-body').innerHTML =
        '<tr><td colspan="6" class="empty-row">Failed to load flowers.</td></tr>';
    }
  }
}

function editProduct(id) {
  const product = adminProducts.get(id);

  if (!product) {
    showToast('Product data is no longer available. Wait for refresh and try again.', 'error');
    return;
  }

  editingProductId = product.id;

  document.getElementById('p-name').value = product.name;
  document.getElementById('p-image').value = product.image || 'images/flowers/default.jpg';
  document.getElementById('p-emoji').value = product.emoji;
  document.getElementById('p-price').value = product.price;
  document.getElementById('p-tag').value = product.tag || '';
  document.getElementById('p-meaning').value = product.meaning;
  document.getElementById('p-stock').value = product.stock;

  document.getElementById('product-form-title').textContent = 'Edit Flower';
  document.getElementById('product-submit-btn').textContent = 'Update Product';
}

function resetProductForm() {
  editingProductId = 0;

  document.getElementById('product-form').reset();
  document.getElementById('p-image').value = 'images/flowers/default.jpg';
  document.getElementById('p-emoji').value = '🌸';
  document.getElementById('p-stock').value = 20;

  document.getElementById('product-form-title').textContent = 'Add Flower';
  document.getElementById('product-submit-btn').textContent = 'Save Product';
}

async function saveProduct(event) {
  event.preventDefault();

  const payload = {
  id: editingProductId,
  name: document.getElementById('p-name').value.trim(),
  emoji: document.getElementById('p-emoji').value.trim() || '🌸',
  image: document.getElementById('p-image').value.trim() || 'images/flowers/default.jpg',
  price: Number(document.getElementById('p-price').value),
  tag: document.getElementById('p-tag').value.trim(),
  meaning: document.getElementById('p-meaning').value.trim(),
  stock: Number(document.getElementById('p-stock').value)
};

  const { data } = await apiFetch('/admin.php?action=save-product', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  showToast(data.message || 'Saved.', data.success ? 'success' : 'error');

  if (data.success) {
    resetProductForm();

    await Promise.all([
      loadProducts(false),
      loadStats(false)
    ]);
  }
}

async function deleteProduct(id) {
  if (!confirm('Delete this flower?')) {
    return;
  }

  const { data } = await apiFetch('/admin.php?action=delete-product', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });

  showToast(data.message || 'Deleted.', data.success ? 'success' : 'error');

  if (data.success) {
    await Promise.all([
      loadProducts(false),
      loadStats(false)
    ]);
  }
}

async function loadUsers(silent = false) {
  try {
    const { data } = await apiFetch('/admin.php?action=users');
    const body = document.getElementById('customers-body');

    if (!data.success) {
      if (!silent) {
        body.innerHTML = '<tr><td colspan="5" class="empty-row">Could not load users.</td></tr>';
      }
      return;
    }

    const users = data.users || [];
    const signature = JSON.stringify(users);

    if (signature === lastUsersSignature && silent) {
      return;
    }

    lastUsersSignature = signature;

    if (!users.length) {
      body.innerHTML = '<tr><td colspan="5" class="empty-row">No users found.</td></tr>';
      return;
    }

    body.innerHTML = users.map(user => `
      <tr>
        <td>#${user.id}</td>
        <td>${escapeHtml(user.name)}</td>
        <td>${escapeHtml(user.email)}</td>
        <td><span class="role-pill">${escapeHtml(user.role)}</span></td>
        <td>${escapeHtml(user.created_at)}</td>
      </tr>
    `).join('');
  } catch (error) {
    console.error(error);

    if (!silent) {
      document.getElementById('customers-body').innerHTML =
        '<tr><td colspan="5" class="empty-row">Failed to load users.</td></tr>';
    }
  }
}

function messageStatusLabel(status) {
  return String(status || 'new').charAt(0).toUpperCase() + String(status || 'new').slice(1);
}

async function loadMessages(silent = false) {
  try {
    const { data } = await apiFetch('/contact.php?action=list');
    const grid = document.getElementById('messages-grid');

    if (!data.success) {
      if (!silent) {
        grid.innerHTML = `<div class="empty-row">${escapeHtml(data.message || 'Could not load messages.')}</div>`;
      }
      return;
    }

    const messages = data.messages || [];
    const signature = JSON.stringify(messages);

    if (signature === lastMessagesSignature && silent) {
      return;
    }

    lastMessagesSignature = signature;
    document.getElementById('stat-messages').textContent = messages.length;
    document.getElementById('messages-tab-count').textContent = messages.length;

    if (!messages.length) {
      grid.innerHTML = '<div class="empty-row">No contact messages yet.</div>';
      return;
    }

    grid.innerHTML = messages.map(message => `
      <article class="message-card ${escapeHtml(message.status)}">
        <div class="message-card-top">
          <div>
            <span class="message-status ${escapeHtml(message.status)}">${escapeHtml(messageStatusLabel(message.status))}</span>
            <h3>${escapeHtml(message.subject)}</h3>
          </div>
          <small>#${message.id}</small>
        </div>

        <div class="message-meta">
          <span><i class="fa-solid fa-user"></i> ${escapeHtml(message.name)}</span>
          <span><i class="fa-solid fa-envelope"></i> ${escapeHtml(message.email)}</span>
          <span><i class="fa-solid fa-clock"></i> ${escapeHtml(message.created_at)}</span>
        </div>

        <p class="message-preview">${escapeHtml(message.message)}</p>

        <div class="message-actions">
          <a class="small-btn" href="mailto:${encodeURIComponent(message.email)}?subject=Re:%20${encodeURIComponent(message.subject)}">
            Reply
          </a>
          <button class="small-btn" onclick="updateMessageStatus(${message.id}, 'read')" ${message.status === 'read' ? 'disabled' : ''}>
            Mark Read
          </button>
          <button class="small-btn" onclick="updateMessageStatus(${message.id}, 'archived')" ${message.status === 'archived' ? 'disabled' : ''}>
            Archive
          </button>
          <button class="small-btn danger" onclick="deleteMessage(${message.id})">
            Delete
          </button>
        </div>
      </article>
    `).join('');
  } catch (error) {
    console.error(error);

    if (!silent) {
      document.getElementById('messages-grid').innerHTML =
        '<div class="empty-row">Failed to load messages.</div>';
    }
  }
}

async function updateMessageStatus(id, status) {
  const { data } = await apiFetch('/contact.php?action=update-status', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, status })
  });

  showToast(data.message || 'Message updated.', data.success ? 'success' : 'error');

  if (data.success) {
    await loadMessages(false);
  }
}

async function deleteMessage(id) {
  if (!confirm('Delete this message?')) {
    return;
  }

  const { data } = await apiFetch('/contact.php?action=delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });

  showToast(data.message || 'Message deleted.', data.success ? 'success' : 'error');

  if (data.success) {
    await loadMessages(false);
  }
}

async function runAdminRealtime() {
  if (adminRealtimeBusy) return;
  if (document.hidden) return;

  adminRealtimeBusy = true;

  try {
    await Promise.allSettled([
      loadStats(true),
      loadOrders(true),
      loadProducts(true),
      loadMessages(true),
      loadUsers(true)
    ]);
  } catch (error) {
    console.error(error);
  } finally {
    adminRealtimeBusy = false;
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const ok = await requireAdmin();

  if (!ok) {
    return;
  }

  document.getElementById('product-form').addEventListener('submit', saveProduct);

  await Promise.allSettled([
    loadStats(false),
    loadOrders(false),
    loadProducts(false),
    loadMessages(false),
    loadUsers(false)
  ]);

  // Real-time admin panel update every 4 seconds
  setInterval(runAdminRealtime, 4000);
});

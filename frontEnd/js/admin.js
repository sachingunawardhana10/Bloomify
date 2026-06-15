let editingProductId = 0;
const adminProducts = new Map();

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

async function loadStats() {
  const { data } = await apiFetch('/admin.php?action=stats');

  if (!data.success) {
    return showToast(data.message || 'Stats failed.', 'error');
  }

  document.getElementById('stat-orders').textContent = data.stats.orders;
  document.getElementById('stat-products').textContent = data.stats.products;
  document.getElementById('stat-customers').textContent = data.stats.customers;
  document.getElementById('stat-revenue').textContent = money(data.stats.revenue);
}

async function loadOrders() {
  const { data } = await apiFetch('/admin.php?action=orders');
  const body = document.getElementById('orders-body');

  if (!data.success || !data.orders.length) {
    body.innerHTML = '<tr><td colspan="6" class="empty-row">No orders yet.</td></tr>';
    return;
  }

  body.innerHTML = data.orders.map(order => `
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
            <option value="${status}" ${status === order.status ? 'selected' : ''}>${status}</option>
          `).join('')}
        </select>
      </td>
      <td>${escapeHtml(order.notes || '-')}</td>
    </tr>
  `).join('');
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

  loadStats();
}

async function loadProducts() {
  const { data } = await apiFetch('/admin.php?action=products');
  const body = document.getElementById('products-body');

  if (!data.success || !data.products.length) {
    body.innerHTML = '<tr><td colspan="6" class="empty-row">No flowers found.</td></tr>';
    return;
  }

  adminProducts.clear();

  data.products.forEach(product => {
    adminProducts.set(product.id, product);
  });

  body.innerHTML = data.products.map(product => `
    <tr>
      <td class="product-name">
        <span>${escapeHtml(product.emoji)}</span> ${escapeHtml(product.name)}
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
}

function editProduct(id) {
  const product = adminProducts.get(id);

  if (!product) {
    return;
  }

  editingProductId = product.id;

  document.getElementById('p-name').value = product.name;
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
    await Promise.all([loadProducts(), loadStats()]);
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
    await Promise.all([loadProducts(), loadStats()]);
  }
}

async function loadUsers() {
  const { data } = await apiFetch('/admin.php?action=users');
  const body = document.getElementById('customers-body');

  if (!data.success || !data.users.length) {
    body.innerHTML = '<tr><td colspan="5" class="empty-row">No users found.</td></tr>';
    return;
  }

  body.innerHTML = data.users.map(user => `
    <tr>
      <td>#${user.id}</td>
      <td>${escapeHtml(user.name)}</td>
      <td>${escapeHtml(user.email)}</td>
      <td><span class="role-pill">${escapeHtml(user.role)}</span></td>
      <td>${escapeHtml(user.created_at)}</td>
    </tr>
  `).join('');
}

document.addEventListener('DOMContentLoaded', async () => {
  const ok = await requireAdmin();

  if (!ok) {
    return;
  }

  document.getElementById('product-form').addEventListener('submit', saveProduct);

  await Promise.all([
    loadStats(),
    loadOrders(),
    loadProducts(),
    loadUsers()
  ]);
});
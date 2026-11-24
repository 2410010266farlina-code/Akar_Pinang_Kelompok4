
/* =========================================================
   AKAR PINANG - APP.JS
   Katalog, varian, keranjang database, wishlist,
   pesanan, dan pengajuan refund.
   ========================================================= */

const API_URL = 'backend/api.php';

let products = [];
let cartItems = [];
let wishlist = JSON.parse(localStorage.getItem('akarWish') || '[]');
let orders = [];
let refunds = [];

let category = 'Semua';
let discount = 0;
let selectedProduct = null;
let selectedVariantId = null;
let csrfToken = '';

const money = value =>
    'Rp' + Math.round(Number(value) || 0).toLocaleString('id-ID');

const escapeHTML = value =>
    String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char]);

function toast(message) {
    const element = document.getElementById('toast');
    if (!element) {
        alert(message);
        return;
    }

    element.textContent = message;
    element.classList.add('show');

    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => {
        element.classList.remove('show');
    }, 2800);
}

function saveWishlist() {
    localStorage.setItem('akarWish', JSON.stringify(wishlist));
}

/* =========================
   KONEKSI API DAN CSRF
   ========================= */

async function getCsrfToken() {
    const response = await fetch(`${API_URL}?action=csrf`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    });

    const data = await response.json();

    if (!response.ok || !data.csrf) {
        throw new Error(data.message || 'Token keamanan tidak ditemukan.');
    }

    csrfToken = data.csrf;
    return csrfToken;
}

async function apiRequest(action, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {})
    };

    if (method !== 'GET') {
        if (!csrfToken) {
            await getCsrfToken();
        }

        headers['X-CSRF-Token'] = csrfToken;

        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }
    }

    const response = await fetch(
        `${API_URL}?action=${encodeURIComponent(action)}`,
        {
            ...options,
            method,
            headers,
            credentials: 'same-origin'
        }
    );

    let data;

    try {
        data = await response.json();
    } catch {
        throw new Error('Respons server bukan JSON. Periksa backend PHP.');
    }

    if (!response.ok || data.success === false || data.ok === false) {
        throw new Error(
            data.message || data.error || 'Permintaan gagal diproses.'
        );
    }

    return data;
}

/* =========================
   PRODUK DARI DATABASE
   ========================= */

async function loadProductsFromDatabase() {
    try {
        const result = await apiRequest('products');

        const rows = Array.isArray(result.products)
            ? result.products
            : Array.isArray(result.data)
                ? result.data
                : [];

        products = rows.map((row, index) => {
            const variantRows = Array.isArray(row.varian)
                ? row.varian
                : Array.isArray(row.variants)
                    ? row.variants
                    : [];

            const categoryName = row.nama_kategori ||
                (Number(row.id_kategori) === 1
                    ? 'Akar Pinang'
                    : 'Camilan Lain');

            const imageName = String(row.foto_produk || '').trim();

            return {
                id: Number(row.id_produk),
                name: String(row.nama_produk || 'Produk Akar Pinang'),
                description: String(row.deskripsi || ''),
                price: Number(row.harga) || 0,
                stock: Number(row.stok) || 0,
                weight: Number(row.berat_gram) || 0,
                rating: Number(row.rating) || 0,
                reviews: 0,
                tag: Number(row.terlaris) === 1
                    ? 'Terlaris'
                    : (/paket hemat/i.test(row.nama_produk || '')
                        ? 'Paket Hemat'
                        : ''),
                category: categoryName,
                image: imageName || `assets/product${(index % 6) + 1}.jpg`,
                variants: variantRows.map(variant => ({
                    id: Number(variant.id_varian),
                    rasa: String(variant.rasa || ''),
                    weight: Number(variant.berat_gram) || 0,
                    price: Number(variant.harga) || 0,
                    stock: Number(variant.stok) || 0
                }))
            };
        });

        renderProducts();

        if (selectedProduct) {
            selectedProduct = products.find(
                product => product.id === selectedProduct.id
            ) || null;
        }

        console.log('Produk berhasil dimuat:', products.length);
    } catch (error) {
        console.error('Gagal memuat produk:', error);
        toast(error.message || 'Produk gagal dimuat dari database.');
    }
}

/* =========================
   KERANJANG DATABASE
   ========================= */

async function loadCartFromDatabase() {
    try {
        const result = await apiRequest('cart');
        const rows = Array.isArray(result.items) ? result.items : [];

        cartItems = rows.map(item => ({
            cartId: Number(item.id_detail_keranjang),
            variantId: Number(item.id_varian),
            productId: Number(item.id_produk),
            name: String(item.nama_produk || ''),
            variantName: String(item.rasa || ''),
            weight: Number(item.berat_gram) || 0,
            price: Number(item.harga) || 0,
            quantity: Number(item.jumlah) || 0,
            stock: Number(item.stok) || 0,
            image: item.foto_produk || 'assets/product1.jpg'
        }));

        renderCart();
        updateCount();
    } catch (error) {
        cartItems = [];
        renderCart();
        updateCount();

        console.warn('Keranjang belum dapat dimuat:', error.message);
    }
}

function updateCount() {
    const total = cartItems.reduce(
        (sum, item) => sum + item.quantity,
        0
    );

    const count = document.getElementById('cartCount');
    if (count) count.textContent = total;
}

async function addCart(productId, variantId = null) {
    try {
        const product = products.find(
            item => Number(item.id) === Number(productId)
        );

        if (!product) {
            throw new Error('Produk tidak ditemukan.');
        }

        const variants = product.variants || [];

        const variant = variantId
            ? variants.find(item => Number(item.id) === Number(variantId))
            : variants.find(item => Number(item.stock) > 0);

        if (!variant) {
            throw new Error('Varian produk belum tersedia.');
        }

        if (variant.stock < 1) {
            throw new Error('Stok produk sedang habis.');
        }

        const result = await apiRequest('cart-add', {
            method: 'POST',
            body: JSON.stringify({
                id_varian: Number(variant.id),
                jumlah: 1
            })
        });

        await loadCartFromDatabase();
        toast(result.message || 'Produk berhasil ditambahkan ke keranjang.');
    } catch (error) {
        console.error('Tambah keranjang gagal:', error);
        toast(error.message || 'Gagal menambahkan produk.');
    }
}

async function changeQty(cartId, change) {
    const item = cartItems.find(
        row => Number(row.cartId) === Number(cartId)
    );

    if (!item) return;

    const newQuantity = item.quantity + change;

    if (newQuantity > item.stock) {
        toast('Jumlah melebihi stok yang tersedia.');
        return;
    }

    try {
        await apiRequest('cart-update', {
            method: 'POST',
            body: JSON.stringify({
                id_detail_keranjang: Number(cartId),
                jumlah: Math.max(0, newQuantity)
            })
        });

        await loadCartFromDatabase();
    } catch (error) {
        toast(error.message || 'Jumlah produk gagal diperbarui.');
    }
}

async function removeCart(cartId) {
    try {
        await apiRequest('cart-update', {
            method: 'POST',
            body: JSON.stringify({
                id_detail_keranjang: Number(cartId),
                jumlah: 0
            })
        });

        await loadCartFromDatabase();
        toast('Produk dihapus dari keranjang.');
    } catch (error) {
        toast(error.message || 'Produk gagal dihapus.');
    }
}

function renderCart() {
    const box = document.getElementById('cartItems');
    if (!box) return;

    if (!cartItems.length) {
        box.innerHTML = `
            <div class="empty-state">
                <span>🛒</span>
                <b>Keranjangmu masih kosong</b>
                <small>Yuk, temukan camilan favoritmu.</small>
                <button class="primary" data-page="catalog">
                    Mulai belanja →
                </button>
            </div>
        `;
    } else {
        box.innerHTML = `
            <h3>Produk pilihanmu (${cartItems.reduce(
                (sum, item) => sum + item.quantity, 0
            )})</h3>
            ${cartItems.map(item => `
                <div class="cart-item">
                    <img src="${escapeHTML(item.image)}"
                         alt="${escapeHTML(item.name)}">

                    <div>
                        <h3>${escapeHTML(item.name)}</h3>
                        <small>
                            ${escapeHTML(item.variantName)}
                            · ${item.weight} gr
                        </small>

                        <div class="qty">
                            <button onclick="changeQty(${item.cartId}, -1)">−</button>
                            <b>${item.quantity}</b>
                            <button onclick="changeQty(${item.cartId}, 1)">+</button>
                        </div>
                    </div>

                    <div class="item-end">
                        <b class="price">
                            ${money(item.price * item.quantity)}
                        </b>
                        <button class="remove"
                                onclick="removeCart(${item.cartId})">
                            Hapus
                        </button>
                    </div>
                </div>
            `).join('')}
        `;
    }

    const subtotal = cartItems.reduce(
        (sum, item) => sum + item.price * item.quantity,
        0
    );

    const shipping = subtotal > 0 ? 12000 : 0;
    const total = Math.max(0, subtotal + shipping - discount);

    const subtotalElement = document.getElementById('subtotal');
    const shippingElement = document.getElementById('shipping');
    const discountElement = document.getElementById('discount');
    const totalElement = document.getElementById('total');

    if (subtotalElement) subtotalElement.textContent = money(subtotal);
    if (shippingElement) shippingElement.textContent = money(shipping);
    if (discountElement) discountElement.textContent = '− ' + money(discount);
    if (totalElement) totalElement.textContent = money(total);
}

/* =========================
   KATALOG DAN KARTU PRODUK
   ========================= */

function productCard(product) {
    const saved = wishlist.includes(product.id);
    const available = (product.variants || []).some(
        variant => variant.stock > 0
    );

    return `
        <article class="product-card">
            <div class="product-photo"
                 onclick="showDetail(${product.id})">

                <img src="${escapeHTML(product.image)}"
                     alt="${escapeHTML(product.name)}"
                     loading="lazy">

                ${product.tag
                    ? `<span class="tag">${escapeHTML(product.tag)}</span>`
                    : ''}

                <button class="heart ${saved ? 'saved' : ''}"
                        onclick="event.stopPropagation();toggleWish(${product.id})"
                        aria-label="Simpan produk">
                    ${saved ? '♥' : '♡'}
                </button>
            </div>

            <div class="product-info">
                <h3 onclick="showDetail(${product.id})">
                    ${escapeHTML(product.name)}
                </h3>

                <div class="rating">
                    <span>★</span>
                    ${product.rating} (${product.reviews} ulasan)
                </div>

                <div class="product-bottom">
                    <span class="price">${money(product.price)}</span>

                    <button class="add-button"
                            ${available ? '' : 'disabled'}
                            onclick="addCart(${product.id})"
                            aria-label="Tambah produk">
                        +
                    </button>
                </div>
            </div>
        </article>
    `;
}

function renderProducts() {
    const home = document.getElementById('homeProducts');
    const catalog = document.getElementById('catalogProducts');
    const wish = document.getElementById('wishlistProducts');

    const search = (
        document.getElementById('search')?.value || ''
    ).toLowerCase();

    let filtered = products.filter(product => {
        const categoryMatch =
            category === 'Semua' || product.category === category;

        const searchMatch =
            product.name.toLowerCase().includes(search);

        return categoryMatch && searchMatch;
    });

    const sort = document.getElementById('sort')?.value || 'popular';

    if (sort === 'low') {
        filtered.sort((a, b) => a.price - b.price);
    } else if (sort === 'high') {
        filtered.sort((a, b) => b.price - a.price);
    } else if (sort === 'rating') {
        filtered.sort((a, b) => b.rating - a.rating);
    }

    if (home) {
        home.innerHTML = products.slice(0, 3).map(productCard).join('');
    }

    if (catalog) {
        catalog.innerHTML = filtered.length
            ? filtered.map(productCard).join('')
            : `
                <div class="empty-state">
                    <span>⌕</span>
                    <b>Produk tidak ditemukan</b>
                    <small>Coba kata kunci yang berbeda.</small>
                </div>
            `;
    }

    if (wish) {
        const savedProducts = products.filter(
            product => wishlist.includes(product.id)
        );

        wish.innerHTML = savedProducts.length
            ? savedProducts.map(productCard).join('')
            : `
                <div class="panel empty-state">
                    <span>♡</span>
                    <b>Wishlist masih kosong</b>
                    <small>Tekan ikon hati pada produk yang kamu suka.</small>
                </div>
            `;
    }
}

function toggleWish(id) {
    id = Number(id);

    wishlist = wishlist.includes(id)
        ? wishlist.filter(item => item !== id)
        : [...wishlist, id];

    saveWishlist();
    renderProducts();

    toast(
        wishlist.includes(id)
            ? 'Ditambahkan ke wishlist.'
            : 'Dihapus dari wishlist.'
    );
}

/* =========================
   DETAIL DAN VARIAN PRODUK
   ========================= */

function showDetail(id) {
    selectedProduct = products.find(
        product => Number(product.id) === Number(id)
    );

    if (!selectedProduct) {
        toast('Produk tidak ditemukan.');
        return;
    }

    const variants = selectedProduct.variants || [];

    if (
        !selectedVariantId ||
        !variants.some(v => v.id === selectedVariantId)
    ) {
        selectedVariantId = variants[0]?.id || null;
    }

    renderDetail();
    go('detail');
}

function renderDetail() {
    if (!selectedProduct) return;

    const variants = selectedProduct.variants || [];
    const selectedVariant = variants.find(
        variant => variant.id === selectedVariantId
    ) || variants[0];

    const price = selectedVariant
        ? selectedVariant.price
        : selectedProduct.price;

    const detail = document.getElementById('detailContent');
    if (!detail) return;

    detail.innerHTML = `
        <div class="detail-layout">
            <div class="detail-image">
                <img src="${escapeHTML(selectedProduct.image)}"
                     alt="${escapeHTML(selectedProduct.name)}">
            </div>

            <div>
                <div class="eyebrow">CAMILAN PILIHAN AKAR PINANG</div>

                <h1>${escapeHTML(selectedProduct.name)}</h1>

                <div class="rating">
                    <span>★</span>
                    ${selectedProduct.rating} ·
                    ${selectedProduct.reviews} ulasan
                </div>

                <div class="detail-price">${money(price)}</div>

                <p class="muted">
                    ${escapeHTML(selectedProduct.description)}
                </p>

                <p>
                    ✓ Bahan pilihan dan rasa khas<br>
                    ✓ Renyah untuk teman santai<br>
                    ✓ Dikemas dengan rapi
                </p>

                <b>Pilih varian</b>

                <div class="variant-row">
                    ${variants.length
                        ? variants.map(variant => `
                            <button
                                class="${selectedVariant &&
                                    selectedVariant.id === variant.id
                                    ? 'selected' : ''}"
                                onclick="selectVariant(${variant.id})"
                                ${variant.stock < 1 ? 'disabled' : ''}>
                                ${escapeHTML(variant.rasa)}
                                · ${variant.weight} gr
                            </button>
                        `).join('')
                        : '<small>Varian belum tersedia.</small>'
                    }
                </div>

                ${selectedVariant ? `
                    <p class="muted">
                        Stok tersedia: ${selectedVariant.stock}
                    </p>
                ` : ''}

                <div class="product-bottom">
                    <span class="price">${money(price)}</span>

                    <button class="primary"
                        ${!selectedVariant || selectedVariant.stock < 1
                            ? 'disabled' : ''}
                        onclick="addCart(${selectedProduct.id}, ${selectedVariant?.id || 'null'})">
                        Tambah ke keranjang +
                    </button>
                </div>

                <hr style="border:0;border-top:1px solid var(--line);margin:25px 0">

                <h3>Deskripsi produk</h3>
                <p class="muted">
                    ${escapeHTML(selectedProduct.description)}
                    Simpan di tempat sejuk dan kering agar kerenyahannya terjaga.
                </p>
            </div>
        </div>
    `;
}

function selectVariant(id) {
    selectedVariantId = Number(id);
    renderDetail();
}

/* =========================
   NAVIGASI HALAMAN
   ========================= */

function go(page) {
    document.querySelectorAll('.page').forEach(element => {
        element.classList.toggle(
            'active',
            element.id === `page-${page}`
        );
    });

    document.querySelectorAll('[data-page]').forEach(button => {
        button.classList.toggle(
            'active',
            button.dataset.page === page
        );
    });

    const labels = {
        home: 'Beranda',
        catalog: 'Katalog Produk',
        cart: 'Keranjang Belanja',
        checkout: 'Checkout',
        orders: 'Pesanan Saya',
        refund: 'Refund',
        wishlist: 'Wishlist',
        account: 'Akun Saya',
        detail: 'Detail Produk'
    };

    const crumb = document.getElementById('crumb');
    if (crumb) crumb.textContent = labels[page] || 'Beranda';

    if (page === 'cart') loadCartFromDatabase();
    if (page === 'orders') loadOrdersFromDatabase();
    if (page === 'refund') loadRefundsFromDatabase();
    if (page === 'catalog' || page === 'wishlist') renderProducts();

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* =========================
   PESANAN DARI DATABASE
   ========================= */

async function loadOrdersFromDatabase() {
    const element = document.getElementById('ordersList');
    if (!element) return;

    try {
        const result = await apiRequest('orders');
        orders = Array.isArray(result.orders) ? result.orders : [];

        renderOrders();
    } catch (error) {
        element.innerHTML = `
            <div class="empty-state">
                <b>Pesanan belum dapat dimuat</b>
                <small>${escapeHTML(error.message)}</small>
            </div>
        `;
    }
}

function renderOrders() {
    const element = document.getElementById('ordersList');
    if (!element) return;

    if (!orders.length) {
        element.innerHTML = `
            <div class="empty-state">
                <span>▤</span>
                <b>Belum ada pesanan</b>
                <small>Pesananmu akan muncul di halaman ini.</small>
                <button class="primary" data-page="catalog">
                    Belanja sekarang →
                </button>
            </div>
        `;
    } else {
        element.innerHTML = orders.map(order => `
            <div class="order-row">
                <div>
                    <b>${escapeHTML(order.kode_pesanan || order.code || '')}</b>
                    <small>
                        ${escapeHTML(order.tanggal_pesanan || order.date || '')}
                    </small>
                    <small>
                        Total ${money(order.total_bayar || order.total || 0)}
                    </small>
                </div>

                <div>
                    <span class="status wait">
                        ${escapeHTML(order.status_pesanan || order.status || 'Menunggu')}
                    </span>
                </div>
            </div>
        `).join('');
    }

    const select = document.getElementById('refundOrder');

    if (select) {
        select.innerHTML = `
            <option value="">Pilih pesanan</option>
            ${orders.map(order => `
                <option value="${Number(order.id_pesanan)}">
                    ${escapeHTML(order.kode_pesanan || order.code || 'Pesanan')}
                </option>
            `).join('')}
        `;
    }
}

/* =========================
   CHECKOUT DATABASE
   ========================= */

document.getElementById('checkoutBtn')?.addEventListener('click', () => {
    if (!cartItems.length) {
        toast('Keranjang masih kosong.');
        return;
    }

    go('checkout');
});

document.getElementById('checkoutForm')?.addEventListener('submit', async event => {
    event.preventDefault();

    if (!cartItems.length) {
        toast('Keranjang masih kosong.');
        return;
    }

    const form = new FormData(event.target);

    /*
      CATATAN:
      Form HTML saat ini belum memiliki input provinsi dan kecamatan.
      Keduanya dikirim kosong sampai kolom tersebut ditambahkan ke HTML.
    */

    const payload = {
        nama_penerima: form.get('nama'),
        no_telepon: form.get('telepon'),
        provinsi: '',
        kota: form.get('kota'),
        kecamatan: '',
        kode_pos: form.get('pos'),
        alamat_lengkap: form.get('alamat'),
        ekspedisi: form.get('ekspedisi'),
        metode_pembayaran: form.get('payment')
    };

    try {
        const result = await apiRequest('checkout', {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        discount = 0;
        event.target.reset();

        await loadCartFromDatabase();
        await loadOrdersFromDatabase();

        toast(result.message || 'Pesanan berhasil dibuat.');
        go('orders');
    } catch (error) {
        toast(error.message || 'Checkout gagal. Periksa alamat pengiriman.');
    }
});

/* =========================
   VOUCHER
   ========================= */

document.getElementById('applyVoucher')?.addEventListener('click', () => {
    const voucher = (
        document.getElementById('voucher')?.value || ''
    ).trim().toUpperCase();

    const subtotal = cartItems.reduce(
        (sum, item) => sum + item.price * item.quantity,
        0
    );

    if (!voucher) {
        discount = 0;
        toast('Masukkan kode voucher terlebih dahulu.');
    } else if (voucher === 'AKAR10' && subtotal >= 50000) {
        discount = Math.min(subtotal * 0.1, 15000);
        toast('Voucher berhasil dihitung pada tampilan keranjang.');
    } else {
        discount = 0;
        toast('Voucher tidak valid atau minimum belanja Rp50.000.');
    }

    renderCart();
});

/* =========================
   REFUND DATABASE
   ========================= */

async function loadRefundsFromDatabase() {
    const element = document.getElementById('refundList');
    if (!element) return;

    try {
        const result = await apiRequest('refunds');
        refunds = Array.isArray(result.refunds) ? result.refunds : [];

        renderRefund();
    } catch (error) {
        element.className = 'empty-state';
        element.innerHTML = `
            <b>Riwayat refund belum dapat dimuat</b>
            <small>${escapeHTML(error.message)}</small>
        `;
    }
}

function renderRefund() {
    const element = document.getElementById('refundList');
    if (!element) return;

    if (!refunds.length) {
        element.className = 'empty-state';
        element.innerHTML = `
            <span>↶</span>
            <b>Belum ada pengajuan</b>
            <small>Riwayat refund akan tampil setelah pengajuan dibuat.</small>
        `;
        return;
    }

    element.className = '';

    element.innerHTML = refunds.map(refund => `
        <div class="order-row">
            <div>
                <b>${escapeHTML(refund.kode_refund || '')}</b>
                <small>
                    Pesanan: ${escapeHTML(refund.kode_pesanan || '')}
                </small>
                <small>
                    ${escapeHTML(refund.alasan || '')}
                </small>
            </div>

            <span class="status wait">
                ${escapeHTML(refund.status_refund || 'Diajukan')}
            </span>
        </div>
    `).join('');
}

document.getElementById('refundMethod')?.addEventListener('change', event => {
    const bankFields = document.getElementById('bankFields');

    if (bankFields) {
        bankFields.style.display =
            event.target.value === 'transfer_bank' ? 'grid' : 'none';
    }
});

document.getElementById('refundForm')?.addEventListener('submit', async event => {
    event.preventDefault();

    const orderId = document.getElementById('refundOrder')?.value;
    const reason = document.getElementById('refundReason')?.value;
    const method = document.getElementById('refundMethod')?.value;
    const description =
        document.getElementById('refundDescription')?.value || '';

    if (!orderId || !reason || !method) {
        toast('Lengkapi data pengajuan refund.');
        return;
    }

    const photo = document.getElementById('refundPhoto')?.files?.[0];

    try {
        let result;

        if (photo) {
            const formData = new FormData();

            formData.append('id_pesanan', orderId);
            formData.append('alasan', reason);
            formData.append('metode_refund', method);
            formData.append('deskripsi', description);
            formData.append('bukti_foto', photo);

            if (method === 'transfer_bank') {
                formData.append(
                    'nama_bank',
                    document.getElementById('bankName')?.value || ''
                );
                formData.append(
                    'nomor_rekening',
                    document.getElementById('accountNumber')?.value || ''
                );
                formData.append(
                    'atas_nama',
                    document.getElementById('accountHolder')?.value || ''
                );
            }

            result = await apiRequest('refund-upload', {
                method: 'POST',
                body: formData
            });
        } else {
            result = await apiRequest('refund', {
                method: 'POST',
                body: JSON.stringify({
                    id_pesanan: Number(orderId),
                    alasan: reason,
                    metode_refund: method,
                    deskripsi: description,
                    nama_bank: document.getElementById('bankName')?.value || '',
                    nomor_rekening:
                        document.getElementById('accountNumber')?.value || '',
                    atas_nama:
                        document.getElementById('accountHolder')?.value || ''
                })
            });
        }

        event.target.reset();

        const bankFields = document.getElementById('bankFields');
        if (bankFields) bankFields.style.display = 'grid';

        await loadRefundsFromDatabase();

        toast(result.message || 'Pengajuan refund berhasil dikirim.');
    } catch (error) {
        toast(error.message || 'Pengajuan refund gagal.');
    }
});

/* =========================
   EVENT NAVIGASI DAN FILTER
   ========================= */

document.addEventListener('click', event => {
    const button = event.target.closest('[data-page]');

    if (button) {
        event.preventDefault();
        go(button.dataset.page);
    }
});

document.querySelectorAll('.chip').forEach(button => {
    button.addEventListener('click', () => {
        category = button.dataset.category;

        document.querySelectorAll('.chip').forEach(chip => {
            chip.classList.toggle('active', chip === button);
        });

        renderProducts();
    });
});

document.getElementById('search')?.addEventListener('input', renderProducts);
document.getElementById('sort')?.addEventListener('change', renderProducts);

/* =========================
   MULAI APLIKASI
   ========================= */

async function initApp() {
    renderProducts();
    renderCart();
    updateCount();

    await loadProductsFromDatabase();
    await loadCartFromDatabase();
}

initApp();

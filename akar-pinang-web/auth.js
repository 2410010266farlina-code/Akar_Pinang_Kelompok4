/* Akar Pinang auth UI — connects to backend/api.php */
(() => {
  const API = 'backend/api.php?action=';
  let csrfToken = '';
  let currentUser = null;
  const $ = (s, root=document) => root.querySelector(s);
  async function request(action, options={}) {
    const headers = Object.assign({'Accept':'application/json'}, options.headers || {});
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (options.method === 'POST' && csrfToken) headers['X-CSRF-Token'] = csrfToken;
    const res = await fetch(API + action, {...options, headers, credentials:'same-origin'});
    let data;
    try { data = await res.json(); } catch { throw new Error('Respons server tidak valid. Periksa PHP/Apache.'); }
    if (data.csrf) csrfToken = data.csrf;
    if (!res.ok) throw new Error(data.error || 'Permintaan gagal.');
    return data;
  }
  function toastAuth(message) {
    if (typeof window.toast === 'function') window.toast(message);
    else alert(message);
  }
  function addUI() {
    if (!document.querySelector('.auth-launch')) {
      const top = document.querySelector('.top-actions');
      if (top) {
        const b = document.createElement('button'); b.className='auth-launch'; b.type='button'; b.textContent='Masuk / Daftar';
        b.addEventListener('click', () => openModal('login'));
        top.prepend(b);
      }
    }
    if (!document.getElementById('authModal')) {
      const modal = document.createElement('div'); modal.id='authModal'; modal.className='auth-modal'; modal.hidden=true;
      modal.innerHTML = `<div class="auth-backdrop" data-auth-close></div><section class="auth-card" role="dialog" aria-modal="true" aria-labelledby="authTitle"><button class="auth-close" type="button" aria-label="Tutup" data-auth-close>×</button><div class="auth-brand">❧ <span>Akar Pinang</span></div><p class="auth-kicker">TRADISI RASA, KINI LEBIH DEKAT</p><h2 id="authTitle">Selamat datang!</h2><p class="auth-subtitle">Masuk atau buat akun untuk melanjutkan.</p><div class="auth-tabs"><button type="button" data-auth-tab="login" class="selected">Login</button><button type="button" data-auth-tab="register">Daftar</button></div><form id="authForm"><div id="authNameWrap" class="auth-field" hidden><label for="authName">Nama lengkap</label><input id="authName" name="nama" autocomplete="name" minlength="2" maxlength="100" placeholder="Nama lengkap"></div><div class="auth-field"><label for="authEmail">Email</label><input id="authEmail" name="email" type="email" autocomplete="email" required maxlength="200" placeholder="nama@email.com"></div><div class="auth-field" id="authPhoneWrap" hidden><label for="authPhone">Nomor telepon</label><input id="authPhone" name="no_telepon" autocomplete="tel" maxlength="200" placeholder="08xxxxxxxxxx"></div><div class="auth-field"><label for="authPassword">Kata sandi</label><input id="authPassword" name="password" type="password" autocomplete="current-password" required minlength="10" placeholder="Minimal 10 karakter"></div><p id="authError" class="auth-error" role="alert"></p><button id="authSubmit" class="auth-submit" type="submit">Masuk</button></form><p class="auth-privacy">Data akun disimpan di database toko. Jangan gunakan kata sandi akun penting lainnya.</p></section>`;
      document.body.appendChild(modal);
      modal.querySelectorAll('[data-auth-close]').forEach(x=>x.addEventListener('click', closeModal));
      modal.querySelectorAll('[data-auth-tab]').forEach(x=>x.addEventListener('click',()=>setMode(x.dataset.authTab)));
      $('#authForm',modal).addEventListener('submit', submitAuth);
    }
  }
  let mode='login';
  function setMode(next) {
    mode=next; const m=$('#authModal');
    m.querySelectorAll('[data-auth-tab]').forEach(b=>b.classList.toggle('selected',b.dataset.authTab===mode));
    $('#authTitle',m).textContent=mode==='login'?'Selamat datang!':'Buat akun Akar Pinang';
    $('#authNameWrap',m).hidden=mode!=='register'; $('#authPhoneWrap',m).hidden=mode!=='register';
    $('#authName',m).required=mode==='register'; $('#authPhone',m).required=mode==='register';
    $('#authPassword',m).autocomplete=mode==='login'?'current-password':'new-password';
    $('#authSubmit',m).textContent=mode==='login'?'Masuk':'Daftar sekarang'; $('#authError',m).textContent='';
  }
  async function openModal(which='login') {
    addUI(); const m=$('#authModal'); m.hidden=false; document.body.classList.add('auth-open'); setMode(which);
    try { const data=await request('csrf'); currentUser=data.user||null; if(currentUser) updateUser(currentUser); }
    catch(e) { $('#authError',m).textContent=e.message; }
    setTimeout(()=>$('#authEmail',m).focus(),50);
  }
  function closeModal(){const m=$('#authModal'); if(m)m.hidden=true;document.body.classList.remove('auth-open');}
  function updateUser(user) {
    currentUser=user;
    const launch=$('.auth-launch'); if(launch){launch.textContent=user?`Hai, ${user.nama.split(' ')[0]}`:'Masuk / Daftar';launch.onclick=()=>user?logout():openModal('login');}
    document.querySelectorAll('.top-user b').forEach(x=>x.textContent=user?`Hai, ${user.nama}!`:'Hai, Pelanggan!');
    document.querySelectorAll('.profile-mini b').forEach(x=>x.textContent=user?user.nama:'Pelanggan Akar Pinang');
  }
  async function submitAuth(ev) {
    ev.preventDefault(); const m=$('#authModal'), form=ev.currentTarget, submit=$('#authSubmit',m), error=$('#authError',m);
    error.textContent=''; submit.disabled=true; submit.textContent='Memproses…';
    const fd=new FormData(form); const body={email:String(fd.get('email')||'').trim(),password:String(fd.get('password')||'')};
    if(mode==='register'){body.nama=String(fd.get('nama')||'').trim();body.no_telepon=String(fd.get('no_telepon')||'').trim();}
    try { const data=await request(mode==='login'?'login':'register',{method:'POST',body:JSON.stringify(body)}); updateUser(data.user); if(data.csrf)csrfToken=data.csrf; closeModal(); toastAuth(mode==='login'?'Berhasil masuk.':'Registrasi berhasil. Akun kamu sudah dibuat.'); }
    catch(e){error.textContent=e.message;}
    finally{submit.disabled=false;submit.textContent=mode==='login'?'Masuk':'Daftar sekarang';}
  }
  async function logout(){try{await request('logout',{method:'POST',body:JSON.stringify({})});currentUser=null;updateUser(null);toastAuth('Kamu sudah keluar.');}catch(e){toastAuth(e.message);}}
  async function init(){addUI();try{const data=await request('csrf');currentUser=data.user||null;if(currentUser)updateUser(currentUser);}catch(e){console.warn('Auth API:',e.message);} }
  window.akarPinangAuth={open:openModal,logout,request,getUser:()=>currentUser};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

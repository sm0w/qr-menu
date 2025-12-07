(() => {
  const st = document.getElementById('searchToggle');
  const sb = document.getElementById('searchBar');
  if (st && sb) st.addEventListener('click', () => sb.classList.toggle('active'));
  const si = document.getElementById('searchInput');
  const sr = document.getElementById('searchResults');
  if (si) {
    const render = (q) => {
      if (!sr) return;
      const data = window.__SEARCH__ || {categories:[], menu:[]};
      const qq = q.trim().toLowerCase();
      if (!qq) { sr.innerHTML=''; sr.style.display='none'; return; }
      const cats = data.categories.filter(c => (c.name||'').toLowerCase().includes(qq));
      const items = data.menu.filter(m => (m.name||'').toLowerCase().includes(qq));
      let html = '';
      if (cats.length) {
        html += '<div class="sr-group">Kategoriler</div>' + cats.map(c => `<a class="sr-item" href="category.php?id=${encodeURIComponent(c.id)}">${c.name}</a>`).join('');
      }
      if (items.length) {
        html += '<div class="sr-group">Menüler</div>' + items.map(m => `<a class="sr-item" href="category.php?id=${encodeURIComponent(m.category_id)}#item-${encodeURIComponent(m.id)}">${m.name}</a>`).join('');
      }
      sr.innerHTML = html || '<div class="sr-empty">Sonuç yok</div>';
      sr.style.display = 'block';
    };
    si.addEventListener('input', () => render(si.value));
  }
  const wt = document.getElementById('wifiToggle');
  const wm = document.getElementById('wifiModal');
  const wc = document.getElementById('wifiClose');
  const open = () => wm && wm.classList.add('active');
  const close = () => wm && wm.classList.remove('active');
  if (wt && wm) wt.addEventListener('click', open);
  if (wc && wm) wc.addEventListener('click', close);
  if (wm) wm.querySelector('.modal-backdrop')?.addEventListener('click', close);
  const vm = document.getElementById('variantsModal');
  const vc = document.getElementById('varClose');
  const vt = document.getElementById('varTitle');
  const vl = document.getElementById('varList');
  const esc = (s) => (s||'').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const openVar = (name, list) => {
    if (!vm) return;
    if (vt) vt.textContent = name || 'Çeşitler';
    if (vl) {
      if (Array.isArray(list) && list.length) {
        const head = '<li class="var-head"><span class="var-n">Çeşit</span><span class="var-p">Fiyat</span></li>';
        const rows = list.map(i => `<li class="var-item"><span class="var-n">${esc(i.name)}</span><span class="var-p">${esc(i.price)}</span></li>`).join('');
        vl.innerHTML = head + rows;
      } else {
        vl.innerHTML = '<li class="var-empty">Çeşit bulunamadı</li>';
      }
    }
    vm.classList.add('active');
  };
  const closeVar = () => vm && vm.classList.remove('active');
  if (vc && vm) vc.addEventListener('click', closeVar);
  if (vm) vm.querySelector('.modal-backdrop')?.addEventListener('click', closeVar);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeVar();
  });
  window.openVariants = (name, enc) => {
    let list = [];
    try { list = JSON.parse(decodeURIComponent(enc || '[]')); } catch (err) {}
    openVar(name, list);
  };
  document.querySelectorAll('.fav-var-btn[data-open="variants"]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const name = btn.getAttribute('data-name') || '';
      let list = [];
      try { list = JSON.parse(decodeURIComponent(btn.getAttribute('data-variants') || '[]')); } catch (err) {}
      openVar(name, list);
    });
    btn.addEventListener('touchstart', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const name = btn.getAttribute('data-name') || '';
      let list = [];
      try { list = JSON.parse(decodeURIComponent(btn.getAttribute('data-variants') || '[]')); } catch (err) {}
      openVar(name, list);
    }, { passive: false });
  });
  
})();

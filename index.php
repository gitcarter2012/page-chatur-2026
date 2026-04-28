<?php
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home</title>
  <link rel="stylesheet" href="./styles.css">
</head>
<body>
  <main class="layout">
    <section class="col-main">
      <div id="players-grid" class="players-grid"></div>
    </section>

    <aside class="col-side">
      <form id="filters-form" class="filters-panel">
        <div class="filter-actions" style="margin-bottom:8px; grid-template-columns: 1fr;">
          <button type="button" id="refresh-players" class="btn">Atualizar 4 frames</button>
        </div>
        <div class="favorites-toggle-wrap">
          <label class="btn favorites-toggle-btn" for="onlyFavorites">
            <input id="onlyFavorites" type="checkbox">
            <span class="favorites-toggle-label">Apenas favoritos da lista fixa</span>
          </label>
        </div>
        <div class="filter-actions" style="margin-bottom:8px; grid-template-columns: 1fr;">
          <button type="button" id="toggle-filters" class="btn">Minimizar filtros</button>
        </div>
        <div class="filters-grid">
          <div class="filter-row">
            <div class="filter-group">
              <label for="sort">Ordenacao</label>
              <select id="sort" name="sort" class="filter-control">
                <option value="viewers">Mais views</option>
                <option value="recent">Mais recentes</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="tag">Tag</label>
              <input id="tag" name="tag" class="filter-control" placeholder="ex: new">
            </div>
          </div>

          <div class="filter-group">
            <label>Regiao</label>
            <div class="filter-options">
              <label class="filter-chip"><input type="checkbox" name="region" value="northamerica">NA</label>
              <label class="filter-chip"><input type="checkbox" name="region" value="southamerica">SA</label>
              <label class="filter-chip"><input type="checkbox" name="region" value="europe_russia">EU</label>
              <label class="filter-chip"><input type="checkbox" name="region" value="asia">AS</label>
              <label class="filter-chip"><input type="checkbox" name="region" value="other">OUT</label>
            </div>
          </div>

          <input type="hidden" name="gender" value="f">

          <div class="filter-row">
            <div class="filter-group">
              <label for="maxAge">Idade max</label>
              <input id="maxAge" name="maxAge" type="number" min="18" max="99" class="filter-control" value="99">
            </div>

            <div class="filter-group">
              <label for="current_show">Show</label>
              <select id="current_show" name="current_show" class="filter-control">
                <option value="">Todos</option>
                <option value="public">Public</option>
                <option value="private">Private</option>
                <option value="group">Group</option>
                <option value="away">Away</option>
              </select>
            </div>
          </div>

          <div class="filter-row">
            <div class="filter-group">
              <label for="is_new">Novas</label>
              <select id="is_new" name="is_new" class="filter-control">
                <option value="">Todas</option>
                <option value="true">Somente novas</option>
                <option value="false">Nao novas</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="tokenMin">Token min</label>
              <input id="tokenMin" name="tokenMin" type="number" min="0" class="filter-control" placeholder="0">
            </div>
          </div>

          <div class="filter-actions">
            <button type="submit" class="btn">Buscar</button>
            <button type="button" id="clear-filters" class="btn">Limpar</button>
          </div>
        </div>
      </form>

      <div id="status" class="status">Atualizando...</div>
      <div id="error" class="error"></div>
      <div id="list" class="list"></div>
    </aside>
  </main>

  <script>
    const API_SEARCH_URL = './api-search-models.php';
    const API_FAVORITES_URL = './api-online-models.php';
    const REFRESH_MS = 10000;

    const listEl = document.getElementById('list');
    const statusEl = document.getElementById('status');
    const errorEl = document.getElementById('error');
    const playersGridEl = document.getElementById('players-grid');
    const refreshPlayersBtn = document.getElementById('refresh-players');
    const filtersForm = document.getElementById('filters-form');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const onlyFavoritesEl = document.getElementById('onlyFavorites');
    const favoritesToggleBtn = document.querySelector('.favorites-toggle-btn');
    const toggleFiltersBtn = document.getElementById('toggle-filters');
    const filtersGrid = filtersForm.querySelector('.filters-grid');

    let currentModels = [];
    let activeSlot = 0;
    const slots = [
      { username: '', src: '' },
      { username: '', src: '' },
      { username: '', src: '' },
      { username: '', src: '' }
    ];

    function escapeHtml(str) {
      return String(str || '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[ch]));
    }

    function secondsToHuman(seconds) {
      const s = Number(seconds || 0);
      if (s < 3600) {
        return Math.floor(s / 60) + ' min online';
      }
      return (s / 3600).toFixed(1) + ' h online';
    }

    function buildQueryFromFilters() {
      const params = new URLSearchParams();
      params.set('sort', document.getElementById('sort').value || 'viewers');

      document.querySelectorAll('input[name="region"]:checked').forEach((input) => {
        params.append('region', input.value);
      });

      document.querySelectorAll('input[name="gender"]:checked').forEach((input) => {
        params.append('gender', input.value);
      });

      // Gender is fixed to women by default.
      params.set('gender', 'f');

      const tag = document.getElementById('tag').value.trim();
      if (tag) params.set('tag', tag);

      const minAge = '';
      const maxAge = document.getElementById('maxAge').value;
      if (maxAge) params.set('maxAge', maxAge);

      const currentShow = document.getElementById('current_show').value;
      if (currentShow) params.set('current_show', currentShow);

      const isNew = document.getElementById('is_new').value;
      if (isNew !== '') params.set('is_new', isNew);

      const tokenMin = document.getElementById('tokenMin').value;
      if (tokenMin !== '') params.set('tokenMin', tokenMin);

      return params;
    }

    function syncFavoritesToggleVisualState() {
      if (!favoritesToggleBtn) {
        return;
      }
      favoritesToggleBtn.classList.toggle('is-active', !!onlyFavoritesEl.checked);
    }

    function renderPlayers() {
      playersGridEl.innerHTML = slots.map((slot, index) => {
        const isActiveTarget = index === activeSlot;

        return `
          <article class="player-card ${isActiveTarget ? 'active-target' : ''}" data-slot="${index}">
            <div class="player-stage">
              ${slot.src
                ? `<iframe class="player" src="${escapeHtml(slot.src)}" title="Player ${index + 1}" loading="lazy" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer"></iframe>`
                : '<div class="player-placeholder">Clique em Selecionar e depois em uma thumb.</div>'}
            </div>
            <div class="player-side ${isActiveTarget ? 'active' : ''}" data-select-slot="${index}" role="button" tabindex="0" aria-label="Selecionar slot ${index + 1}"></div>
          </article>
        `;
      }).join('');
    }

    function setActiveSlot(newActiveSlot) {
      activeSlot = Number(newActiveSlot || 0);
      playersGridEl.querySelectorAll('.player-card').forEach((card, index) => {
        card.classList.toggle('active-target', index === activeSlot);
      });
      playersGridEl.querySelectorAll('.player-side').forEach((side, index) => {
        side.classList.toggle('active', index === activeSlot);
      });
    }

    function updateSlotFrame(slotIndex, forceRefresh) {
      const slot = slots[slotIndex];
      const card = playersGridEl.querySelector('[data-slot="' + slotIndex + '"]');
      if (!card || !slot) {
        return;
      }

      const stage = card.querySelector('.player-stage');
      if (!stage) {
        return;
      }

      if (!slot.src) {
        stage.innerHTML = '<div class="player-placeholder">Clique em Selecionar e depois em uma thumb.</div>';
        return;
      }

      const iframe = stage.querySelector('iframe.player');
      if (iframe) {
        const targetSrc = forceRefresh ? withRefreshParam(slot.src) : slot.src;
        iframe.src = targetSrc;
        return;
      }

      const iframeSrc = forceRefresh ? withRefreshParam(slot.src) : slot.src;
      stage.innerHTML = '<iframe class="player" src="' + escapeHtml(iframeSrc) + '" title="Player ' + (slotIndex + 1) + '" loading="lazy" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer"></iframe>';
    }

    function withRefreshParam(src) {
      try {
        const url = new URL(src, window.location.href);
        url.searchParams.set('_r', Date.now().toString());
        return url.toString();
      } catch (_err) {
        const sep = src.includes('?') ? '&' : '?';
        return src + sep + '_r=' + Date.now();
      }
    }

    function refreshAllFrames() {
      for (let i = 0; i < slots.length; i += 1) {
        updateSlotFrame(i, true);
      }
    }

    function selectModelToActiveSlot(username) {
      const model = currentModels.find((m) => (m.username || '').toLowerCase() === String(username || '').toLowerCase());
      if (!model || !model.embed_src) {
        return;
      }

      slots[activeSlot] = {
        username: model.username,
        src: model.embed_src
      };
      updateSlotFrame(activeSlot, false);

      document.querySelectorAll('.item').forEach((item) => {
        item.classList.toggle('active', item.dataset.username === model.username.toLowerCase());
      });
    }

    function renderList(models) {
      currentModels = Array.isArray(models) ? models : [];

      if (!currentModels.length) {
        listEl.innerHTML = '<div class="empty">Nenhuma modelo da lista esta online agora.</div>';
        return;
      }

      const cacheBust = Date.now();
      listEl.innerHTML = currentModels.map((m) => {
        const usernameLower = (m.username || '').toLowerCase();
        const thumb = String(m.image_url || '').trim();
        const thumbUrl = thumb ? (thumb + (thumb.includes('?') ? '&' : '?') + 't=' + cacheBust) : '';

        return `
          <article class="item" data-username="${escapeHtml(usernameLower)}" tabindex="0" role="button" aria-label="Assistir ${escapeHtml(m.username)}">
            ${thumbUrl ? `<img class="thumb" src="${escapeHtml(thumbUrl)}" alt="${escapeHtml(m.username)}">` : '<div class="thumb"></div>'}
            <div class="meta">
              <div class="name">${escapeHtml(m.username)}</div>
              <div class="time">${escapeHtml(secondsToHuman(m.seconds_online))} • ${escapeHtml((m.num_users || 0).toString())} views</div>
            </div>
          </article>
        `;
      }).join('');
    }

    playersGridEl.addEventListener('click', (event) => {
      const side = event.target.closest('[data-select-slot]');
      if (!side) {
        return;
      }
      setActiveSlot(Number(side.dataset.selectSlot || 0));
      statusEl.textContent = 'Slot ' + (activeSlot + 1) + ' selecionado • clique em uma thumb para enviar o video';
    });

    playersGridEl.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      const side = event.target.closest('[data-select-slot]');
      if (!side) {
        return;
      }
      event.preventDefault();
      setActiveSlot(Number(side.dataset.selectSlot || 0));
      statusEl.textContent = 'Slot ' + (activeSlot + 1) + ' selecionado • clique em uma thumb para enviar o video';
    });

    refreshPlayersBtn.addEventListener('click', () => {
      refreshAllFrames();
      statusEl.textContent = '4 frames atualizadas manualmente • slot ativo: ' + (activeSlot + 1);
    });

    listEl.addEventListener('click', (event) => {
      const item = event.target.closest('.item');
      if (!item) {
        return;
      }
      selectModelToActiveSlot(item.dataset.username || '');
    });

    listEl.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      const item = event.target.closest('.item');
      if (!item) {
        return;
      }
      event.preventDefault();
      selectModelToActiveSlot(item.dataset.username || '');
    });

    async function load() {
      try {
        let models = [];
        let count = 0;
        let modeLabel = 'busca';

        if (onlyFavoritesEl.checked) {
          const favRes = await fetch(API_FAVORITES_URL + '?_=' + Date.now(), { cache: 'no-store' });
          const favData = await favRes.json();
          if (!favData.ok) {
            throw new Error(favData.error || 'Erro ao carregar favoritos');
          }
          models = Array.isArray(favData.models) ? favData.models : [];
          count = Number(favData.count || models.length);
          modeLabel = 'favoritos';
        } else {
          const params = buildQueryFromFilters();
          params.set('_', Date.now().toString());

          const searchRes = await fetch(API_SEARCH_URL + '?' + params.toString(), { cache: 'no-store' });
          const searchData = await searchRes.json();
          if (!searchData.ok) {
            throw new Error(searchData.error || 'Erro ao carregar dados');
          }
          models = Array.isArray(searchData.results) ? searchData.results : [];
          count = Number(searchData.count || models.length);
        }

        errorEl.style.display = 'none';
        renderList(models);

        const now = new Date();
        statusEl.textContent = count + ' encontrados (' + modeLabel + ') • slot ativo: ' + (activeSlot + 1) + ' • ' + now.toLocaleTimeString('pt-BR');
      } catch (err) {
        errorEl.textContent = 'Falha ao carregar: ' + err.message;
        errorEl.style.display = 'block';
        statusEl.textContent = 'Erro na atualizacao';
      }
    }

    filtersForm.addEventListener('submit', (event) => {
      event.preventDefault();
      load();
    });

    clearFiltersBtn.addEventListener('click', () => {
      filtersForm.reset();
      document.getElementById('maxAge').value = '99';
      onlyFavoritesEl.checked = false;
      syncFavoritesToggleVisualState();
      load();
    });

    onlyFavoritesEl.addEventListener('change', () => {
      syncFavoritesToggleVisualState();
      load();
    });

    toggleFiltersBtn.addEventListener('click', () => {
      const hidden = filtersGrid.style.display === 'none';
      filtersGrid.style.display = hidden ? 'grid' : 'none';
      toggleFiltersBtn.textContent = hidden ? 'Minimizar filtros' : 'Mostrar filtros';
    });

    renderPlayers();
    setActiveSlot(activeSlot);
    syncFavoritesToggleVisualState();
    load();
    setInterval(load, REFRESH_MS);
  </script>
</body>
</html>

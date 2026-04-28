<?php
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Multi Live Monitor</title>
  <link rel="stylesheet" href="./styles.css">
</head>
<body>
  <main class="layout">
    <section class="col-main">
      <div id="players-grid" class="players-grid"></div>
    </section>

    <aside class="col-side">
      <div id="status" class="status">Atualizando...</div>
      <div id="error" class="error"></div>
      <div id="list" class="list"></div>
    </aside>
  </main>

  <script>
    const API_URL = './api-online-models.php';
    const REFRESH_MS = 10000;

    const listEl = document.getElementById('list');
    const statusEl = document.getElementById('status');
    const errorEl = document.getElementById('error');
    const playersGridEl = document.getElementById('players-grid');

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

    function selectModelToActiveSlot(username) {
      const model = currentModels.find((m) => (m.username || '').toLowerCase() === String(username || '').toLowerCase());
      if (!model || !model.embed_src) {
        return;
      }

      slots[activeSlot] = {
        username: model.username,
        src: model.embed_src
      };
      renderPlayers();

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
              <div class="time">${escapeHtml(secondsToHuman(m.seconds_online))}</div>
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
      activeSlot = Number(side.dataset.selectSlot || 0);
      renderPlayers();
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
      activeSlot = Number(side.dataset.selectSlot || 0);
      renderPlayers();
      statusEl.textContent = 'Slot ' + (activeSlot + 1) + ' selecionado • clique em uma thumb para enviar o video';
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
        const res = await fetch(API_URL + '?_=' + Date.now(), { cache: 'no-store' });
        const data = await res.json();
        if (!data.ok) {
          throw new Error(data.error || 'Erro ao carregar dados');
        }

        errorEl.style.display = 'none';
        renderList(Array.isArray(data.models) ? data.models : []);

        const now = new Date();
        statusEl.textContent = (data.count || 0) + ' online • ordem: mais recentes primeiro • slot ativo: ' + (activeSlot + 1) + ' • ' + now.toLocaleTimeString('pt-BR');
      } catch (err) {
        errorEl.textContent = 'Falha ao carregar: ' + err.message;
        errorEl.style.display = 'block';
        statusEl.textContent = 'Erro na atualizacao';
      }
    }

    renderPlayers();
    load();
    setInterval(load, REFRESH_MS);
  </script>
</body>
</html>

<?php
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Multi Live Monitor</title>
  <style>
    :root {
      --bg: #05070a;
      --panel: #0d1219;
      --panel-alt: #131925;
      --text: #e8edf6;
      --muted: #93a0b4;
      --line: #263246;
      --accent: #16c784;
      --danger: #e75f5f;
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      width: 100%;
      height: 100%;
      background: var(--bg);
      color: var(--text);
      font-family: "Segoe UI", Tahoma, sans-serif;
      overflow: hidden;
    }

    .layout {
      width: 100%;
      height: 100%;
      display: grid;
      grid-template-columns: 10fr 2fr;
      gap: 0;
    }

    .col-main {
      min-width: 0;
      height: 100%;
      border-right: 1px solid var(--line);
      background: #05070a;
      padding: 8px;
    }

    .players-grid {
      width: 100%;
      height: 100%;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      grid-template-rows: repeat(2, minmax(0, 1fr));
      align-content: center;
      gap: 8px;
    }

    .player-card {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #070b12;
      overflow: hidden;
      display: grid;
      grid-template-columns: 1fr 62px;
      aspect-ratio: 1 / 1;
      min-width: 0;
      min-height: 0;
    }

    .player-card.active-target {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px rgba(22, 199, 132, 0.5);
    }

    .player-side {
      border-left: 1px solid var(--line);
      background: #101722;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 8px 6px;
    }

    .player-name {
      color: var(--muted);
      font-weight: 600;
      font-size: 11px;
      line-height: 1.25;
      text-align: center;
      word-break: break-word;
      max-width: 100%;
    }

    .select-btn {
      border: 1px solid var(--line);
      color: var(--text);
      background: #151d2b;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 700;
      padding: 6px 8px;
      cursor: pointer;
      width: 100%;
    }

    .select-btn:hover {
      border-color: #42516b;
    }

    .select-btn.active {
      border-color: var(--accent);
      background: rgba(22, 199, 132, 0.15);
    }

    .player-stage {
      min-height: 0;
      position: relative;
      overflow: hidden;
      background: #000;
    }

    .player {
      width: 100%;
      height: 100%;
      border: 0;
      display: block;
      background: #000;
      /* Focus mode: crops the provider frame to prioritize video and hide side UI/chat. */
      width: 155%;
      margin-left: -27.5%;
    }

    .player-placeholder {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      text-align: center;
      color: var(--muted);
      padding: 24px;
    }

    .col-side {
      height: 100%;
      background: linear-gradient(180deg, var(--panel), var(--panel-alt));
      overflow-y: auto;
      overflow-x: hidden;
      padding: 8px;
      scrollbar-width: thin;
      scrollbar-color: #43516a transparent;
    }

    .col-side::-webkit-scrollbar {
      width: 8px;
    }

    .col-side::-webkit-scrollbar-thumb {
      background: #43516a;
      border-radius: 99px;
    }

    .status {
      position: sticky;
      top: 0;
      z-index: 20;
      font-size: 12px;
      color: var(--muted);
      padding: 8px;
      margin: -8px -8px 8px;
      background: rgba(18, 22, 31, 0.95);
      border-bottom: 1px solid var(--line);
      backdrop-filter: blur(8px);
    }

    .error {
      display: none;
      margin: 0 0 10px;
      padding: 8px;
      border: 1px solid rgba(231, 95, 95, 0.7);
      border-radius: 8px;
      background: rgba(231, 95, 95, 0.15);
      color: #ffe8e8;
      font-size: 12px;
    }

    .list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }

    .item {
      border: 1px solid var(--line);
      border-radius: 10px;
      overflow: hidden;
      background: #0f141d;
      cursor: pointer;
      transition: border-color .15s ease, transform .15s ease;
      outline: none;
    }

    .item:hover,
    .item:focus-visible {
      border-color: #4c5b76;
      transform: translateY(-1px);
    }

    .item.active {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px rgba(25, 195, 125, 0.45);
    }

    .thumb {
      width: 100%;
      aspect-ratio: 4 / 3;
      object-fit: cover;
      display: block;
      background: #0a0d12;
    }

    .meta {
      padding: 6px;
      display: grid;
      gap: 3px;
    }

    .name {
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: var(--text);
    }

    .time {
      font-size: 11px;
      color: var(--muted);
    }

    .empty {
      border: 1px dashed #4c5770;
      border-radius: 10px;
      padding: 14px;
      color: var(--muted);
      text-align: center;
      font-size: 12px;
    }

    @media (max-width: 1100px) {
      .layout {
        grid-template-columns: 1fr;
        grid-template-rows: 65vh 35vh;
      }

      .col-main {
        border-right: 0;
        border-bottom: 1px solid var(--line);
      }

      .players-grid {
        grid-template-columns: 1fr;
        grid-template-rows: repeat(4, minmax(0, 1fr));
      }

      .player-card {
        max-width: 100%;
      }

      .list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
  </style>
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
        const safeName = slot.username ? escapeHtml(slot.username) : 'vazio';
        const isActiveTarget = index === activeSlot;

        return `
          <article class="player-card ${isActiveTarget ? 'active-target' : ''}" data-slot="${index}">
            <div class="player-stage">
              ${slot.src
                ? `<iframe class="player" src="${escapeHtml(slot.src)}" title="Player ${index + 1}" loading="lazy" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer"></iframe>`
                : '<div class="player-placeholder">Clique em Selecionar e depois em uma thumb.</div>'}
            </div>
            <div class="player-side">
              <button class="select-btn ${isActiveTarget ? 'active' : ''}" type="button" data-select-slot="${index}">S${index + 1}</button>
              <span class="player-name">${safeName}</span>
            </div>
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
      const button = event.target.closest('[data-select-slot]');
      if (!button) {
        return;
      }
      activeSlot = Number(button.dataset.selectSlot || 0);
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

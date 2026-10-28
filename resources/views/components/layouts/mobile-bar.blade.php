{{-- Шапка тем ігор і головної на телефоні: навігація ігор — смугою з прокруткою під шапкою,
     кнопка «Додати сервер» ховається (є в банері й кабінеті), щоб дії вміщалися в екран --}}
<style>
  @media (max-width: 1024px) {
    .bar { flex-wrap: wrap; row-gap: 8px; }
    .bar .actions { display: flex; align-items: center; gap: 6px; margin-left: auto; }
    .bar nav.games-nav { display: flex !important; order: 3; width: 100%; margin: 0 !important; overflow-x: auto; scrollbar-width: none; white-space: nowrap; }
    .bar nav.games-nav::-webkit-scrollbar { display: none; }
    .bar nav.games-nav a { flex: none; }
  }
  @media (max-width: 640px) {
    .bar { gap: 8px !important; padding: 8px 8px 8px 12px !important; }
    .bar .header-cta { display: none !important; }
    /* Тема головної ховала «Увійти» на телефоні — тепер замість неї ховається «Додати сервер» */
    .bar .actions .login { display: inline-flex !important; }
    .bar .logo { gap: 8px; font-size: 15px; }
    .bar .actions .btn-sm { padding: 7px 10px; }
  }
</style>

{{-- Шапка тем ігор і головної на телефоні й планшеті: навігація ігор — смугою з прокруткою під шапкою,
     логотип менший, кнопка «Додати сервер» ховається (є в банері й кабінеті), щоб логотип і дії вміщалися в один рядок --}}
<style>
  /* Логотип GRAJ.CLUB широкий (~7:1): на вузьких екранах менший, щоб логотип і дії вміщалися в один рядок */
  @media (max-width: 1024px) {
    .bar .logo img { height: 32px !important; }
    .bar { flex-wrap: wrap; row-gap: 8px; }
    .bar .actions { display: flex; align-items: center; gap: 6px; margin-left: auto; }
    .bar nav.games-nav { display: flex !important; order: 3; width: 100%; margin: 0 !important; overflow-x: auto; scrollbar-width: none; white-space: nowrap; }
    .bar nav.games-nav::-webkit-scrollbar { display: none; }
    .bar nav.games-nav a { flex: none; }
  }
  /* «Додати сервер» ховається вже з 768 px — є в банері й кабінеті */
  @media (max-width: 768px) {
    .bar .header-cta { display: none !important; }
  }
  @media (max-width: 640px) {
    .bar { gap: 8px !important; padding: 8px 8px 8px 12px !important; }
    .bar .logo img { height: 26px !important; }
    /* Тема головної ховала «Увійти» на телефоні — тепер замість неї ховається «Додати сервер» */
    .bar .actions .login { display: inline-flex !important; }
    .bar .logo { gap: 8px; font-size: 15px; }
    .bar .actions .btn-sm { padding: 7px 10px; }
  }
  /* Телефон: замість широкого логотипа — квадратний значок (<picture> у шапці), дії вміщаються поруч */
  @media (max-width: 480px) {
    .bar .logo img { height: 34px !important; border-radius: 8px; }
  }
</style>

(function () {
  const STORAGE_KEY = 'alabama-theme';

  function getPreferredTheme() {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
      return stored;
    }
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    window.localStorage.setItem(STORAGE_KEY, theme);
  }

  function toggleTheme() {
    const current = getPreferredTheme();
    const next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  }

  // Exponibiliza API global simples
  window.AlabamaTheme = {
    apply: applyTheme,
    toggle: toggleTheme,
    current: getPreferredTheme
  };

  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(getPreferredTheme());
  });
})();

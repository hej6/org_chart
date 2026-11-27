/**
 * Javascript Main File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 * @param     none
 * @return    void
 */

// Output "program started" to the console
console.log("program started");

// go back to the prevous page
function goBack() {
  window.history.back();
}
/*
const users_01 =fetch('https://jsonplaceholder.typicode.com/users')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.log('Error:', error));

  console.table(users);
*/
async function fetchUsers() {
  try {
    const response = await fetch('https://jsonplaceholder.typicode.com/users');
    const data = await response.json();
    console.log(data);
    console.table(data);
  } catch (error) {
    console.log('Error:', error);
  }
};

fetchUsers();

// Output "completed" to the console
console.log("program completed");

// Toggle the theme and remember the selection between pages.
const themeToggle = document.getElementById('theme-toggle');

if (themeToggle) {
  function updateThemeButton() {
    const darkMode =
      document.documentElement.getAttribute('data-bs-theme') === 'dark';

    themeToggle.textContent = darkMode ? 'Dark mode: On' : 'Dark mode: Off';
    themeToggle.setAttribute('aria-pressed', String(darkMode));
  }

  themeToggle.addEventListener('click', function () {
    const currentTheme =
      document.documentElement.getAttribute('data-bs-theme');

    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', nextTheme);

    try {
      localStorage.setItem('org-chart-theme', nextTheme);
    } catch (error) {
      // The button still works when browser storage is unavailable.
    }

    updateThemeButton();
  });

  updateThemeButton();
  themeToggle.hidden = false;
}
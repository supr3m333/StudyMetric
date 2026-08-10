const searchInput = document.querySelector('#student-search');
const loginError = document.querySelector('#login-error');
const registerError = document.querySelector('#register-error');
const registerSuccess = document.querySelector('#register-success');
const loginForm = document.querySelector('#login-form');
const registerForm = document.querySelector('#register-form');

if (loginError && new URLSearchParams(window.location.search).has('error')) {
    loginError.classList.remove('hidden');
}

if (registerSuccess && new URLSearchParams(window.location.search).has('registered')) {
    registerSuccess.classList.remove('hidden');
}

if (registerError && new URLSearchParams(window.location.search).has('register_error')) {
    loginForm.classList.add('hidden');
    registerForm.classList.remove('hidden');
    registerError.classList.remove('hidden');
}

document.querySelector('#show-login')?.addEventListener('click', function () {
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
});

document.querySelector('#show-register')?.addEventListener('click', function () {
    loginForm.classList.add('hidden');
    registerForm.classList.remove('hidden');
});

if (searchInput) {
    searchInput.addEventListener('input', function () {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#student-table tbody tr');

        rows.forEach(function (row) {
            const matches = row.textContent.toLowerCase().includes(searchText);
            row.style.display = matches ? '' : 'none';
        });
    });
}

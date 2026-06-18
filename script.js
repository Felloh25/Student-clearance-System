document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('error-message');

    loginForm.addEventListener('submit', (event) => {
        const usernameInput = document.getElementById('username').value.trim();
        const passwordInput = document.getElementById('password').value.trim();
        
        let errors = [];

        // Simple validation checks
        if (usernameInput === '') {
            errors.push('Username or Admission Number is required.');
        }
        if (passwordInput === '') {
            errors.push('Password field cannot be empty.');
        }

        // If there are errors, stop the form from submitting to PHP
        if (errors.length > 0) {
            event.preventDefault(); // Stop form submission
            errorMessage.innerHTML = errors.join('<br>');
            errorMessage.style.display = 'block'; // Show the error box
        } else {
            errorMessage.style.display = 'none'; // Hide if everything is fine
        }
    });
});
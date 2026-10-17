document.getElementById('login-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('error-message');

    errorDiv.style.display = 'none';

    try {
        const response = await fetch('https://pb685.brighton.domains/ci609/api/auth/login', {
            method: 'POST',
            credentials: 'include', // Tells the browser to accept and store the set-cookie header
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();
        console.log('Login response:', data);
        if (response.ok) {
            // 1. Store the access token in JS memory inside auth.js
            accessToken = data.access_token;

            // 2. Redirect to main interactive map page
            window.location.href = 'map.html';
        } else {
            errorDiv.textContent = data.message || 'Login failed.';
            errorDiv.style.display = 'block';
        }
    } catch (err) {
        console.error('Login error:', err);
        errorDiv.textContent = 'Network error. Please try again later.';
        errorDiv.style.display = 'block';
    }
});
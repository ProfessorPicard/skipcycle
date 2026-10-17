// Access token is held strictly in memory
let accessToken = null;

// 1. Silent Initialization on Page Load
async function initAuth() {
    try {
        const response = await fetch('https://pb685.brighton.domains/ci609/api/auth/refresh', {
            method: 'POST',
            credentials: 'include', // Tells fetch to send the HttpOnly cookie
            headers: { 'Content-Type': 'application/json' }
        });

        if (response.ok) {
            const data = await response.json();
            accessToken = data.access_token; // Store in JS variable
            // updateUI(true);
        } else {
            // updateUI(false);
        }
    } catch (err) {
        console.error("Session restoration failed:", err);
        updateUI(false);
    }
}

// 2. Authenticated Fetch Wrapper
async function apiFetch(url, options = {}) {
    options.headers = options.headers || {};
    
    if (accessToken) {
        options.headers['Authorization'] = `Bearer ${accessToken}`;
    }

    let response = await fetch(url, options);

    // If access token expired mid-session (401), attempt silent refresh
    if (response.status === 401) {
        const refreshed = await silentRefresh();
        if (refreshed) {
            options.headers['Authorization'] = `Bearer ${accessToken}`;
            response = await fetch(url, options); // Retry original request
        }
    }

    return response;
}

async function silentRefresh() {
    try {
        const res = await fetch('https://pb685.brighton.domains/ci609/api/auth/refresh', {
            method: 'POST',
            credentials: 'include'
        });

        if (res.ok) {
            const data = await res.json();
            accessToken = data.access_token;
            return true;
        }
    } catch (e) {
        console.error("Refresh failed", e);
    }

    accessToken = null;
    // updateUI(false);
    return false;
}

document.addEventListener('DOMContentLoaded', initAuth);
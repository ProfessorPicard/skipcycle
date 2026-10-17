// Access token is held strictly in memory
let accessToken = null;
let refreshPromise = null;
let userId = null;

// 1. Silent Initialization on Page Load
async function initAuth() {
    getAccessToken().then(token => {
        if (token) {
            console.log("Access token retrieved: ", accessToken);
            setAuthState(true);
        } else {
            console.log("No access token found.");
            setAuthState(false);
        }
    }).catch(err => {
        console.error("Session restoration failed:", err);
        setAuthState(false);
    });
}

async function getAccessToken() {
    // 1. If we already have an access token in memory, return it instantly
    if (accessToken) {
        return accessToken;
    }

    // 2. If a silent refresh is ALREADY running, wait for it instead of starting a new one
    if (refreshPromise) {
        return await refreshPromise;
    }

    // 3. First caller triggers the actual silent refresh request
    refreshPromise = silentRefresh();

    try {
        const token = await refreshPromise;
        return token;
    } finally {
        // Clear the promise lock once finished (success or failure)
        refreshPromise = null;
    }
}


async function silentRefresh() {
    try {
        const res = await fetch('https://pb685.brighton.domains/ci609/api/auth/refresh', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' }
        });

        if (res.ok) {
            const data = await res.json();
            accessToken = data.access_token;
            userId = data.user_id;
            return accessToken;
        }
    } catch (e) {
        console.error("Refresh failed", e);
    }

    accessToken = null;
    // updateUI(false);
    return accessToken;
}

function setAuthState(isLoggedIn) {
    const event = new CustomEvent('authStateChanged', { 
        detail: { isLoggedIn } 
    });
    window.dispatchEvent(event);
}

document.addEventListener('DOMContentLoaded', initAuth);
let user = null;
async function getProfile() {
    const token = await getAccessToken();
    if (token) {
        try {
            const response = await fetch('https://pb685.brighton.domains/ci609/api/users/profile', {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            const data = await response.json();
            if (!response.ok) {
                console.error('Failed to fetch profile:', data.message || 'Unknown error');
                return null;
            }
            return data.data;
        } catch (error) {
            console.error('Error fetching profile:', error);
            return null;
        }
    }
    return null;
}

async function initHeader() {
    user = await getProfile();
    console.log("User profile retrieved:", user);
    (user) ? updateUi(true) : updateUi(false);
}

function updateUi(loggedIn) {
    if (loggedIn) {
        document.getElementById('accountName').textContent = user.first_name;
        document.getElementById('profileImage').src = user.profile_image;
    } else {
        document.getElementById('accountName').textContent = 'Log In';
        document.getElementById('profileImage').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', initHeader);
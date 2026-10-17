async function getItemsByRadius(latitude, longitude, radius_meters) {
    try {
        const response = await fetch('https://pb685.brighton.domains/ci609/api/items/findInRadius', {
            method: 'POST',
            credentials: 'include', // Tells fetch to send the HttpOnly cookie
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                latitude: latitude,
                longitude: longitude,
                radius_meters: radius_meters
            })
        });

        if (response.ok) {
            const data = await response.json();
            console.log("Items within radius:", data);
        } else {
            // updateUI(false);
        }
    } catch (err) {
        console.error("Session restoration failed:", err);
        updateUI(false);
    }
}
document.addEventListener('DOMContentLoaded', getItemsByRadius(50.8352, 0.4781, 2000));
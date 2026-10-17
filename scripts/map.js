var map;
const renderedMarkers = new Map();

async function getItemsByRadius(latitude, longitude, radius_meters) {
   const token = await getAccessToken();
    if (token) {
        try {
            const response = await fetch('https://pb685.brighton.domains/ci609/api/items/findInRadius', {
                method: 'POST',
                credentials: 'include', // Tells fetch to send the HttpOnly cookie
                headers: { 
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${accessToken}`
                 },
                body: JSON.stringify({
                    latitude: latitude,
                    longitude: longitude,
                    radius_meters: radius_meters
                })
            });

            if (response.ok) {
                const data = await response.json();
                return data;
            } else {
                // updateUI(false);
            }
        } catch (err) {
            console.error("Items within radius Error:", err);
            // updateUI(false);
        }
    } else {
        console.log("No valid session found. Cannot fetch items.");
        // updateUI(false);
    }
    
}

async function onMapClick(e) {
    //If signed in, show add skip dialog, otherwise show sign in dialog
    const token = await getAccessToken();
    if (token) {
        try {
            const response = await fetch('https://pb685.brighton.domains/ci609/api/items/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    user_id: userId,
                    title: "Sample Item",
                    description: "This is a sample item.",
                    image: "sample-image.jpg",
                    is_skip: 0,
                    latitude: e.latlng.lat,
                    longitude: e.latlng.lng
                })
            });
            if (response.ok) {
                // const data = await response.json();
                populateMap(); // Refresh the map to show the new item
            } else {
                // updateUI(false);
            }
        } catch (err) {
            console.error("Error checking session:", err);
        }
    } else {
        // Show sign in dialog
    }
}

async function mapMovedEnd(e) {
    var lat = e.target.getCenter().lat;
    var lng = e.target.getCenter().lng;

    console.log("Current Lat: ", lat, "Current Lng: ", lng);
    await populateMap();
    
}

async function initMap() {
        map = L.map('map').setView([51.505, -0.09], 13);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            map.on('moveend', mapMovedEnd);

            map.on('click', onMapClick);

            if(navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    console.log("Current Position - Lat: ", position.coords.latitude, "Lng: ", position.coords.longitude);
                    map.setView([position.coords.latitude, position.coords.longitude], 13);
                });
            } else {
                console.log("Geolocation is not supported by this browser.");
            }
            
            await populateMap();

}

async function populateMap() {

    if (!map) {
        console.error("Map is not initialized.");
        return;
    }

    var jsonString = await getItemsByRadius(map.getCenter().lat, map.getCenter().lng, 5000);
    if (!jsonString || !Array.isArray(jsonString.data)) {
        console.error("Invalid data received from getItemsByRadius.");
        return;
    }

    // console.log("Items within radius: ", jsonString);

    jsonString.data.forEach(item => {
        if(renderedMarkers.has(item.id)) {
            console.log(`Item ID ${item.id} already rendered. Skipping.`);
            return;
        }

        const itemMarker = L.marker([item.latitude, item.longitude]).addTo(map);
        itemMarker.bindPopup(`<b>${item.title}</b><br>${item.description}`);

        renderedMarkers.set(item.id, itemMarker);

        console.log(`Item ID ${item.id}: ${item.title} at (${item.latitude}, ${item.longitude})`);
    });

}

document.addEventListener('DOMContentLoaded', initMap);
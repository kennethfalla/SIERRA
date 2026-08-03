// assets/js/map.js
// Environmental Reporting System - Map Functions

let map;
let marker;
let currentMarker = null;

// Initialize map
function initMap(lat = 14.5995, lng = 120.9842, zoom = 13) {
    if (map) {
        map.remove();
    }
    
    map = L.map('map').setView([lat, lng], zoom);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    return map;
}

// Get user's current location
function getCurrentLocation(inputLatId, inputLngId, inputAddressId) {
    if (!navigator.geolocation) {
        showNotification('Geolocation is not supported by your browser', 'error');
        return;
    }
    
    showLoading('Getting your location...');
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            document.getElementById(inputLatId).value = lat;
            document.getElementById(inputLngId).value = lng;
            
            map.setView([lat, lng], 15);
            
            if (currentMarker) {
                map.removeLayer(currentMarker);
            }
            
            currentMarker = L.marker([lat, lng]).addTo(map);
            currentMarker.bindPopup("<b>Your Location</b><br>Report will be marked here").openPopup();
            
            // Reverse geocoding
            reverseGeocode(lat, lng, inputAddressId);
            
            hideLoading();
            showNotification('Location captured successfully!', 'success');
        },
        (error) => {
            hideLoading();
            let message = 'Unable to get your location';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = 'Please allow location access to use this feature';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'Location information is unavailable';
                    break;
                case error.TIMEOUT:
                    message = 'Location request timed out';
                    break;
            }
            showNotification(message, 'error');
        }
    );
}

// Reverse geocoding to get address from coordinates
async function reverseGeocode(lat, lng, inputAddressId) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
        const data = await response.json();
        
        if (data.display_name) {
            document.getElementById(inputAddressId).value = data.display_name;
        }
    } catch (error) {
        console.error('Reverse geocoding failed:', error);
        document.getElementById(inputAddressId).value = `${lat}, ${lng}`;
    }
}

// Add marker on click
function enableMarkerOnClick(inputLatId, inputLngId, inputAddressId) {
    if (!map) return;
    
    map.on('click', async function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        document.getElementById(inputLatId).value = lat;
        document.getElementById(inputLngId).value = lng;
        
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }
        
        currentMarker = L.marker([lat, lng]).addTo(map);
        currentMarker.bindPopup("<b>Selected Location</b>").openPopup();
        
        await reverseGeocode(lat, lng, inputAddressId);
        showNotification('Location selected on map', 'success');
    });
}

// Load reports on map
function loadReportsOnMap(reportsData, onMarkerClick) {
    if (!map) return;
    
    reportsData.forEach(report => {
        if (report.latitude && report.longitude) {
            const marker = L.marker([parseFloat(report.latitude), parseFloat(report.longitude)]).addTo(map);
            
            let statusColor = '';
            switch(report.status) {
                case 'pending': statusColor = '#f59e0b'; break;
                case 'verified': statusColor = '#3b82f6'; break;
                case 'resolved': statusColor = '#10b981'; break;
                default: statusColor = '#6b7280';
            }
            
            const popupContent = `
                <div class="p-2">
                    <h4 class="font-bold text-sm">${escapeHtml(report.title)}</h4>
                    <p class="text-xs text-gray-600 mt-1">${escapeHtml(report.category_name)}</p>
                    <p class="text-xs mt-1"><span class="inline-block w-2 h-2 rounded-full" style="background: ${statusColor}"></span> ${report.status}</p>
                    <button onclick="viewReport(${report.id})" class="mt-2 text-xs bg-blue-500 text-white px-2 py-1 rounded">View Details</button>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            
            if (onMarkerClick) {
                marker.on('click', () => onMarkerClick(report));
            }
        }
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white fade-in ${
        type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Show loading indicator
function showLoading(message = 'Loading...') {
    const loader = document.createElement('div');
    loader.id = 'loadingOverlay';
    loader.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
    loader.innerHTML = `
        <div class="bg-white rounded-lg p-6 flex flex-col items-center">
            <div class="spinner"></div>
            <p class="mt-4 text-gray-700">${message}</p>
        </div>
    `;
    document.body.appendChild(loader);
}

// Hide loading indicator
function hideLoading() {
    const loader = document.getElementById('loadingOverlay');
    if (loader) loader.remove();
}
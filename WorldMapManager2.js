class WorldMap {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = options;
        this.map = null;
        this.tileCache = new Map();
        this.loadingTiles = new Set();
        this.tileSize = 256;
        this.currentPopup = null;
        this.loadingIndicator = document.getElementById('loadingIndicator');
    }

    init() {
        this.setupMap();
        this.setupTileLayer();
        this.setupEventListeners();
    }

    setupMap() {
        // Define custom CRS for isometric game world
        const customCRS = L.extend({}, L.CRS.Simple, {
            transformation: new L.Transformation(1, 0, -1, 0)
        });

        // Initialize map with custom settings
        this.map = L.map(this.containerId, {
            crs: customCRS,
            center: [0, 0],
            zoom: 2,
            minZoom: 1,
            maxZoom: 6,
            zoomControl: true,
            attributionControl: false,
            preferCanvas: true,
            // Disable world wrapping for game maps
            maxBounds: [[-1000, -1000], [1000, 1000]],
            maxBoundsViscosity: 1.0
        });

        // Add zoom control to top-right
        L.control.zoom({
            position: 'topright'
        }).addTo(this.map);

        // Add coordinates display
        this.addCoordinateDisplay();
    }

    setupTileLayer() {
        const tileLayer = L.gridLayer({
            tileSize: this.tileSize,
            keepBuffer: 2,
            updateWhenIdle: false,
            updateWhenZooming: true,
            detectRetina: false
        });

        // Override createTile method to create custom tiles
        tileLayer.createTile = (coords, done) => {
            return this.createCustomTile(coords, done);
        };

        tileLayer.addTo(this.map);
        this.tileLayer = tileLayer;
    }

    createCustomTile(coords, done) {
        const tile = document.createElement('div');
        tile.className = 'world-tile';
        tile.style.width = this.tileSize + 'px';
        tile.style.height = this.tileSize + 'px';
        
        // Convert tile coordinates to world coordinates
        const worldX = coords.x - Math.pow(2, coords.z - 1);
        const worldY = coords.y - Math.pow(2, coords.z - 1);
        
        // Create tile key for caching
        const tileKey = `${worldX}_${worldY}_${coords.z}`;
        
        // Check cache first
        if (this.tileCache.has(tileKey)) {
            this.renderTile(tile, this.tileCache.get(tileKey), worldX, worldY);
            done(null, tile);
            return tile;
        }

        // Load tile data if not already loading
        if (!this.loadingTiles.has(tileKey)) {
            this.loadTileData(worldX, worldY, coords.z, tileKey, tile, done);
        }

        // Set initial tile appearance
        tile.style.background = '#3a4a2a';
        tile.innerHTML = `<div class="tile-info">${worldX},${worldY}</div>`;
        
        done(null, tile);
        return tile;
    }

    async loadTileData(worldX, worldY, zoom, tileKey, tile, done) {
        this.loadingTiles.add(tileKey);
        this.showLoading();

        try {
            // Calculate the range of regions to load for this tile
            const regionsPerTile = Math.max(1, Math.floor(8 / Math.pow(2, zoom - 1)));
            const coords = [];
            
            for (let x = 0; x < regionsPerTile; x++) {
                for (let y = 0; y < regionsPerTile; y++) {
                    coords.push({
                        x: worldX * regionsPerTile + x,
                        y: worldY * regionsPerTile + y
                    });
                }
            }

            const response = await fetch('/game/world/get-tiles', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ coords })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const tileData = await response.json();
            
            // Cache the tile data
            this.tileCache.set(tileKey, tileData);
            
            // Render the tile
            this.renderTile(tile, tileData, worldX, worldY);
            
        } catch (error) {
            console.error('Failed to load tile data:', error);
            tile.style.background = '#ff4444';
            tile.innerHTML = `<div class="tile-info">Error</div>`;
        } finally {
            this.loadingTiles.delete(tileKey);
            if (this.loadingTiles.size === 0) {
                this.hideLoading();
            }
        }
    }

    renderTile(tile, tileData, worldX, worldY) {
        // Clear existing content
        tile.innerHTML = '';
        
        if (!tileData || tileData.length === 0) {
            // Empty/ocean tile
            tile.style.background = '#2c5530';
            tile.innerHTML = `<div class="tile-info">${worldX},${worldY}</div>`;
            return;
        }

        // Find the most significant region for this tile
        const primaryRegion = this.getPrimaryRegion(tileData);
        
        if (primaryRegion) {
            // Set background based on terrain type
            const terrainImage = this.getTerrainImage(primaryRegion.type);
            if (terrainImage) {
                tile.style.backgroundImage = `url(${terrainImage})`;
            } else {
                tile.style.background = this.getTerrainColor(primaryRegion.type);
            }

            // Add owner information if present
            if (primaryRegion.owner && primaryRegion.owner.trim() !== '') {
                const ownerDiv = document.createElement('div');
                ownerDiv.className = 'tile-owner';
                ownerDiv.textContent = primaryRegion.owner;
                tile.appendChild(ownerDiv);
            }

            // Add structures count if any
            if (primaryRegion.structures && primaryRegion.structures.length > 0) {
                const structuresDiv = document.createElement('div');
                structuresDiv.className = 'tile-structures';
                structuresDiv.textContent = `${primaryRegion.structures.length} structures`;
                tile.appendChild(structuresDiv);
            }
        }

        // Add coordinate info
        const infoDiv = document.createElement('div');
        infoDiv.className = 'tile-info';
        infoDiv.textContent = `${worldX},${worldY}`;
        tile.appendChild(infoDiv);

        // Store tile data for click events
        tile.tileData = tileData;
        tile.worldX = worldX;
        tile.worldY = worldY;
    }

    getPrimaryRegion(tileData) {
        if (!tileData || tileData.length === 0) return null;
        
        // Priority: owned regions > regions with structures > any region
        const ownedRegions = tileData.filter(region => region.owner && region.owner.trim() !== '');
        if (ownedRegions.length > 0) return ownedRegions[0];
        
        const structureRegions = tileData.filter(region => region.structures && region.structures.length > 0);
        if (structureRegions.length > 0) return structureRegions[0];
        
        return tileData[0];
    }

    getTerrainImage(terrainType) {
        // Return URL to terrain image if available
        const terrainImages = {
            'grass': '/images/map/grass.png',
            'forest': '/images/map/forest.png',
            'mountain': '/images/map/mountain.png',
            'water': '/images/map/water.png',
            'desert': '/images/map/desert.png'
        };
        
        return terrainImages[terrainType] || null;
    }

    getTerrainColor(terrainType) {
        // Fallback colors for terrain types
        const terrainColors = {
            'grass': '#4a5a2a',
            'forest': '#2d3f1f',
            'mountain': '#666666',
            'water': '#2c5590',
            'desert': '#c4a484',
            'none': '#2c5530'
        };
        
        return terrainColors[terrainType] || terrainColors['none'];
    }

    setupEventListeners() {
        // Handle tile clicks
        this.map.on('click', (e) => {
            this.handleMapClick(e);
        });

        // Handle zoom changes
        this.map.on('zoomend', () => {
            // Clear cache on zoom change to force reload with appropriate detail
            this.tileCache.clear();
        });

        // Handle right-click for context menu
        this.map.on('contextmenu', (e) => {
            e.originalEvent.preventDefault();
            this.showTileContextMenu(e);
        });
    }

    handleMapClick(e) {
        // Close any existing popup
        if (this.currentPopup) {
            this.map.closePopup();
        }

        // Find the clicked tile element
        const clickedElement = e.originalEvent.target;
        let tileElement = clickedElement;
        
        // Traverse up to find the tile element
        while (tileElement && !tileElement.tileData) {
            tileElement = tileElement.parentElement;
        }

        if (tileElement && tileElement.tileData) {
            this.showTilePopup(e.latlng, tileElement.tileData, tileElement.worldX, tileElement.worldY);
        }
    }

    showTilePopup(latlng, tileData, worldX, worldY) {
        if (!tileData || tileData.length === 0) {
            const popup = L.popup()
                .setLatLng(latlng)
                .setContent(`
                    <div>
                        <h4>Empty Region</h4>
                        <p>Coordinates: ${worldX}, ${worldY}</p>
                        <p>No regions found in this area</p>
                    </div>
                `)
                .openOn(this.map);
            
            this.currentPopup = popup;
            return;
        }

        const primaryRegion = this.getPrimaryRegion(tileData);
        let content = `
            <div>
                <h4>World Region</h4>
                <p><strong>Coordinates:</strong> ${primaryRegion.x}, ${primaryRegion.y}</p>
                <p><strong>Type:</strong> ${primaryRegion.type}</p>
        `;

        if (primaryRegion.owner && primaryRegion.owner.trim() !== '') {
            content += `<p><strong>Owner:</strong> ${primaryRegion.owner}</p>`;
        }

        if (primaryRegion.structures && primaryRegion.structures.length > 0) {
            content += `<p><strong>Structures:</strong> ${primaryRegion.structures.length}</p>`;
            content += '<ul>';
            primaryRegion.structures.forEach(structure => {
                content += `<li>${structure.name}</li>`;
            });
            content += '</ul>';
        }

        // Add action buttons if applicable
        content += '<div style="margin-top: 10px;">';
        if (!primaryRegion.owner || primaryRegion.owner.trim() === '') {
            content += `<button onclick="worldMap.buyTile(${primaryRegion.x}, ${primaryRegion.y})" class="btn btn-sm btn-primary">Buy Tile</button>`;
        }
        
        if (primaryRegion.owner === this.options.player.name) {
            content += `<button onclick="worldMap.showBuildingOptions(${primaryRegion.x}, ${primaryRegion.y})" class="btn btn-sm btn-success">Build</button>`;
        }
        content += '</div>';

        content += '</div>';

        const popup = L.popup()
            .setLatLng(latlng)
            .setContent(content)
            .openOn(this.map);
        
        this.currentPopup = popup;
    }

    async buyTile(x, y) {
        try {
            this.showLoading();
            
            const response = await fetch('/game/world/buy-tile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ x, y })
            });

            const result = await response.json();
            
            if (response.ok && result.success) {
                // Clear cache to force reload
                this.tileCache.clear();
                this.map.closePopup();
                
                // Refresh the current view
                this.tileLayer.redraw();
                
                alert('Tile purchased successfully!');
            } else {
                alert(result.message || 'Failed to purchase tile');
            }
        } catch (error) {
            console.error('Error buying tile:', error);
            alert('An error occurred while purchasing the tile');
        } finally {
            this.hideLoading();
        }
    }

    async showBuildingOptions(x, y) {
        try {
            this.showLoading();
            
            const response = await fetch('/game/world/get-building-list', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ x, y })
            });

            const buildings = await response.json();
            
            if (response.ok && buildings.length > 0) {
                let content = '<div><h4>Available Buildings</h4>';
                
                buildings.forEach(building => {
                    content += `
                        <div style="margin: 5px 0; padding: 5px; border: 1px solid #ddd;">
                            <strong>${building.name}</strong><br>
                            <small>Price: ${building.price} | Income: ${building.income}</small><br>
                            <button onclick="worldMap.buildStructure(${x}, ${y}, ${building.classId})" class="btn btn-sm btn-success">Build</button>
                        </div>
                    `;
                });
                
                content += '</div>';
                
                const popup = L.popup()
                    .setLatLng(this.map.getCenter())
                    .setContent(content)
                    .openOn(this.map);
                
                this.currentPopup = popup;
            } else {
                alert('No buildings available for this region');
            }
        } catch (error) {
            console.error('Error loading building options:', error);
            alert('Failed to load building options');
        } finally {
            this.hideLoading();
        }
    }

    async buildStructure(x, y, buildingId) {
        try {
            this.showLoading();
            
            const response = await fetch('/game/world/build', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ x, y, cid: buildingId })
            });

            const result = await response.json();
            
            if (response.ok && result.success) {
                // Clear cache to force reload
                this.tileCache.clear();
                this.map.closePopup();
                
                // Refresh the current view
                this.tileLayer.redraw();
                
                alert('Building constructed successfully!');
            } else {
                alert(result.message || 'Failed to construct building');
            }
        } catch (error) {
            console.error('Error building structure:', error);
            alert('An error occurred while constructing the building');
        } finally {
            this.hideLoading();
        }
    }

    addCoordinateDisplay() {
        const coordControl = L.control({ position: 'bottomleft' });
        
        coordControl.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'coordinate-display');
            div.style.background = 'rgba(255, 255, 255, 0.8)';
            div.style.padding = '5px';
            div.style.borderRadius = '3px';
            div.style.fontSize = '12px';
            div.innerHTML = 'Move mouse to see coordinates';
            
            map.on('mousemove', function(e) {
                const lat = Math.round(e.latlng.lat);
                const lng = Math.round(e.latlng.lng);
                div.innerHTML = `World: ${lng}, ${lat}`;
            });
            
            return div;
        };
        
        coordControl.addTo(this.map);
    }

    showLoading() {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = 'block';
        }
    }

    hideLoading() {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = 'none';
        }
    }
}

// Make worldMap globally accessible for button clicks
let worldMap;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('worldMapContainer');
    if (container) {
        // This will be initialized from the template script tag
        // worldMap = new WorldMap('worldMapContainer', options);
    }
});

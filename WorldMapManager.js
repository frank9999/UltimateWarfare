class WorldMapManager {
    constructor(containerId) {
        this.containerId = containerId;
        this.map = null;
        this.tileLayer = null;
        this.tileCache = new Map();
        this.currentTiles = new Map();
        this.loadedTileCoords = new Set();
        
        // Configuration
        this.config = {
            tileSize: 256,
            maxZoom: 6,
            minZoom: 1,
            initialZoom: 3,
            initialCenter: [0, 0],
            maxBounds: [[-500, -500], [500, 500]]
        };
        
        // API endpoints
        this.endpoints = {
            getTiles: '/game/world/get-tiles',
            buyTile: '/game/world/buy-tile',
            getBuildingList: '/game/world/get-building-list',
            build: '/game/world/build'
        };
        
        this.loadingOverlay = document.getElementById('loadingOverlay');
    }

    initialize() {
        this.setupMap();
        this.setupCustomTileLayer();
        this.setupEventHandlers();
        console.log('WorldMapManager initialized');
    }

    setupMap() {
        // Define custom CRS for isometric coordinates
        const customCRS = L.extend({}, L.CRS.Simple, {
            transformation: new L.Transformation(1, 0, 1, 0)
        });

        this.map = L.map(this.containerId, {
            crs: customCRS,
            center: this.config.initialCenter,
            zoom: this.config.initialZoom,
            minZoom: this.config.minZoom,
            maxZoom: this.config.maxZoom,
            maxBounds: this.config.maxBounds,
            zoomControl: true,
            attributionControl: false
        });

        // Add zoom control
        L.control.zoom({
            position: 'topright'
        }).addTo(this.map);
    }

    setupCustomTileLayer() {
        this.tileLayer = L.gridLayer({
            tileSize: this.config.tileSize,
            maxZoom: this.config.maxZoom,
            minZoom: this.config.minZoom
        });

        this.tileLayer.createTile = (coords) => {
            return this.createTile(coords);
        };

        this.tileLayer.addTo(this.map);
    }

    createTile(coords) {
        const tile = document.createElement('div');
        tile.style.width = this.config.tileSize + 'px';
        tile.style.height = this.config.tileSize + 'px';
        tile.style.position = 'relative';
        tile.style.overflow = 'hidden';
        
        // Calculate world coordinates from tile coordinates
        const worldCoords = this.tileToWorldCoords(coords);
        
        // Load tile data
        this.loadTileData(worldCoords, tile);
        
        return tile;
    }

    tileToWorldCoords(coords) {
        // Convert Leaflet tile coordinates to world coordinates
        const tilesPerWorldUnit = Math.pow(2, coords.z - 1);
        const worldX = Math.floor(coords.x / tilesPerWorldUnit) + (coords.x % tilesPerWorldUnit);
        const worldY = Math.floor(coords.y / tilesPerWorldUnit) + (coords.y % tilesPerWorldUnit);
        
        return {
            x: worldX - Math.pow(2, coords.z - 2),
            y: worldY - Math.pow(2, coords.z - 2),
            z: coords.z
        };
    }

    async loadTileData(worldCoords, tileElement) {
        const coordKey = `${worldCoords.x},${worldCoords.y}`;
        
        // Check cache first
        if (this.tileCache.has(coordKey)) {
            this.renderTile(this.tileCache.get(coordKey), tileElement);
            return;
        }

        try {
            this.showLoading(true);
            
            const response = await fetch(this.endpoints.getTiles, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    coords: [{ x: worldCoords.x, y: worldCoords.y }]
                })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const tilesData = await response.json();
            
            if (tilesData && tilesData.length > 0) {
                const tileData = tilesData[0];
                this.tileCache.set(coordKey, tileData);
                this.renderTile(tileData, tileElement);
            } else {
                // Render empty/default tile
                this.renderDefaultTile(worldCoords, tileElement);
            }
            
        } catch (error) {
            console.error('Error loading tile data:', error);
            this.renderErrorTile(tileElement);
        } finally {
            this.showLoading(false);
        }
    }

    renderTile(tileData, tileElement) {
        tileElement.innerHTML = '';
        
        // Create base terrain
        const terrain = document.createElement('div');
        terrain.style.width = '100%';
        terrain.style.height = '100%';
        terrain.style.backgroundImage = `url(/images/map/${tileData.type}.png)`;
        terrain.style.backgroundSize = 'cover';
        terrain.style.backgroundPosition = 'center';
        terrain.style.position = 'absolute';
        
        tileElement.appendChild(terrain);
        
        // Add owner indicator if tile is owned
        if (tileData.owner && tileData.owner !== '') {
            const ownerLabel = document.createElement('div');
            ownerLabel.textContent = tileData.owner;
            ownerLabel.style.position = 'absolute';
            ownerLabel.style.bottom = '5px';
            ownerLabel.style.left = '5px';
            ownerLabel.style.right = '5px';
            ownerLabel.style.background = 'rgba(0, 0, 0, 0.7)';
            ownerLabel.style.color = 'white';
            ownerLabel.style.padding = '2px 5px';
            ownerLabel.style.fontSize = '11px';
            ownerLabel.style.borderRadius = '3px';
            ownerLabel.style.textAlign = 'center';
            ownerLabel.style.overflow = 'hidden';
            ownerLabel.style.whiteSpace = 'nowrap';
            ownerLabel.style.textOverflow = 'ellipsis';
            
            tileElement.appendChild(ownerLabel);
        }
        
        // Add structures if any
        if (tileData.structures && tileData.structures.length > 0) {
            const structure = tileData.structures[0];
            const structureImg = document.createElement('div');
            structureImg.style.width = '32px';
            structureImg.style.height = '32px';
            structureImg.style.position = 'absolute';
            structureImg.style.top = '50%';
            structureImg.style.left = '50%';
            structureImg.style.transform = 'translate(-50%, -50%)';
            structureImg.style.backgroundImage = `url(${structure.imageName})`;
            structureImg.style.backgroundSize = 'contain';
            structureImg.style.backgroundRepeat = 'no-repeat';
            structureImg.style.backgroundPosition = 'center';
            
            tileElement.appendChild(structureImg);
        }
        
        // Store tile data for click handling
        tileElement.tileData = tileData;
        
        // Add click handler
        tileElement.style.cursor = 'pointer';
        tileElement.addEventListener('click', (e) => {
            this.handleTileClick(tileData, e);
        });
    }

    renderDefaultTile(worldCoords, tileElement) {
        tileElement.innerHTML = '';
        
        const defaultTile = document.createElement('div');
        defaultTile.style.width = '100%';
        defaultTile.style.height = '100%';
        defaultTile.style.background = '#7f8c8d';
        defaultTile.style.border = '1px solid #95a5a6';
        defaultTile.style.display = 'flex';
        defaultTile.style.alignItems = 'center';
        defaultTile.style.justifyContent = 'center';
        defaultTile.style.color = '#ecf0f1';
        defaultTile.style.fontSize = '12px';
        defaultTile.textContent = `${worldCoords.x},${worldCoords.y}`;
        
        tileElement.appendChild(defaultTile);
        
        // Store minimal tile data
        tileElement.tileData = {
            x: worldCoords.x,
            y: worldCoords.y,
            type: 'none',
            owner: '',
            structures: []
        };
    }

    renderErrorTile(tileElement) {
        tileElement.innerHTML = '';
        
        const errorTile = document.createElement('div');
        errorTile.style.width = '100%';
        errorTile.style.height = '100%';
        errorTile.style.background = '#e74c3c';
        errorTile.style.display = 'flex';
        errorTile.style.alignItems = 'center';
        errorTile.style.justifyContent = 'center';
        errorTile.style.color = 'white';
        errorTile.style.fontSize = '12px';
        errorTile.textContent = 'Error';
        
        tileElement.appendChild(errorTile);
    }

    handleTileClick(tileData, event) {
        event.preventDefault();
        event.stopPropagation();
        
        // Create popup content
        const popupContent = this.createTilePopupContent(tileData);
        
        // Convert tile coordinates to map coordinates for popup
        const latlng = this.map.mouseEventToLatLng(event);
        
        L.popup()
            .setLatLng(latlng)
            .setContent(popupContent)
            .openOn(this.map);
    }

    createTilePopupContent(tileData) {
        const container = document.createElement('div');
        container.className = 'tile-info-popup';
        
        // Title
        const title = document.createElement('h4');
        title.textContent = `Region (${tileData.x}, ${tileData.y})`;
        container.appendChild(title);
        
        // Information rows
        const infoData = [
            { label: 'Type', value: tileData.type || 'Unknown' },
            { label: 'Owner', value: tileData.owner || 'Unclaimed' },
            { label: 'Coordinates', value: `${tileData.x}, ${tileData.y}` }
        ];
        
        if (tileData.z !== undefined) {
            infoData.push({ label: 'Z-Level', value: tileData.z });
        }
        
        infoData.forEach(info => {
            const row = document.createElement('div');
            row.className = 'info-row';
            
            const label = document.createElement('span');
            label.className = 'info-label';
            label.textContent = info.label + ':';
            
            const value = document.createElement('span');
            value.textContent = info.value;
            
            row.appendChild(label);
            row.appendChild(value);
            container.appendChild(row);
        });
        
        // Actions
        const actions = document.createElement('div');
        actions.className = 'actions';
        
        if (tileData.owner === '' || !tileData.owner) {
            const buyBtn = document.createElement('button');
            buyBtn.textContent = 'Buy Tile';
            buyBtn.className = 'btn btn-primary';
            buyBtn.onclick = () => this.buyTile(tileData.x, tileData.y);
            actions.appendChild(buyBtn);
        }
        
        if (tileData.owner === 'admin') { // Replace with current player check
            const buildBtn = document.createElement('button');
            buildBtn.textContent = 'Build';
            buildBtn.className = 'btn btn-success';
            buildBtn.onclick = () => this.showBuildMenu(tileData.x, tileData.y);
            actions.appendChild(buildBtn);
        }
        
        const infoBtn = document.createElement('button');
        infoBtn.textContent = 'More Info';
        infoBtn.className = 'btn btn-info';
        infoBtn.onclick = () => this.showTileDetails(tileData);
        actions.appendChild(infoBtn);
        
        if (actions.children.length > 0) {
            container.appendChild(actions);
        }
        
        return container;
    }

    async buyTile(x, y) {
        try {
            this.showLoading(true);
            
            const response = await fetch(this.endpoints.buyTile, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ x: x, y: y })
            });
            
            const result = await response.text();
            
            if (response.ok && result) {
                alert('Tile purchased successfully!');
                // Refresh the tile
                this.tileCache.delete(`${x},${y}`);
                this.map.closePopup();
                // Force refresh the tile layer
                this.tileLayer.redraw();
            } else {
                alert('Failed to purchase tile: ' + result);
            }
            
        } catch (error) {
            console.error('Error buying tile:', error);
            alert('Error purchasing tile. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    async showBuildMenu(x, y) {
        try {
            this.showLoading(true);
            
            const response = await fetch(this.endpoints.getBuildingList, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ x: x, y: y })
            });
            
            const buildings = await response.json();
            
            if (buildings && buildings.length > 0) {
                this.showBuildingSelectionDialog(x, y, buildings);
            } else {
                alert('No buildings available to construct on this tile.');
            }
            
        } catch (error) {
            console.error('Error loading building list:', error);
            alert('Error loading building options. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    showBuildingSelectionDialog(x, y, buildings) {
        const dialog = document.createElement('div');
        dialog.style.position = 'fixed';
        dialog.style.top = '50%';
        dialog.style.left = '50%';
        dialog.style.transform = 'translate(-50%, -50%)';
        dialog.style.background = 'white';
        dialog.style.padding = '20px';
        dialog.style.border = '1px solid #ccc';
        dialog.style.borderRadius = '5px';
        dialog.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        dialog.style.zIndex = '10000';
        dialog.style.maxWidth = '400px';
        dialog.style.maxHeight = '300px';
        dialog.style.overflow = 'auto';
        
        const title = document.createElement('h3');
        title.textContent = 'Select Building to Construct';
        title.style.marginBottom = '15px';
        dialog.appendChild(title);
        
        buildings.forEach(building => {
            const buildingOption = document.createElement('div');
            buildingOption.style.padding = '10px';
            buildingOption.style.margin = '5px 0';
            buildingOption.style.border = '1px solid #ddd';
            buildingOption.style.borderRadius = '3px';
            buildingOption.style.cursor = 'pointer';
            buildingOption.style.transition = 'background-color 0.2s';
            
            buildingOption.innerHTML = `
                <strong>${building.name}</strong><br>
                <small>Cost: ${building.cost || 'Unknown'}</small>
            `;
            
            buildingOption.onmouseover = () => {
                buildingOption.style.backgroundColor = '#f0f0f0';
            };
            
            buildingOption.onmouseout = () => {
                buildingOption.style.backgroundColor = 'white';
            };
            
            buildingOption.onclick = () => {
                this.buildStructure(x, y, building.classId);
                document.body.removeChild(dialog);
            };
            
            dialog.appendChild(buildingOption);
        });
        
        const closeBtn = document.createElement('button');
        closeBtn.textContent = 'Cancel';
        closeBtn.style.marginTop = '15px';
        closeBtn.style.padding = '5px 15px';
        closeBtn.onclick = () => document.body.removeChild(dialog);
        dialog.appendChild(closeBtn);
        
        document.body.appendChild(dialog);
    }

    async buildStructure(x, y, classId) {
        try {
            this.showLoading(true);
            
            const response = await fetch(this.endpoints.build, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ x: x, y: y, cid: classId })
            });
            
            const result = await response.text();
            alert(result);
            
            // Refresh the tile
            this.tileCache.delete(`${x},${y}`);
            this.map.closePopup();
            this.tileLayer.redraw();
            
        } catch (error) {
            console.error('Error building structure:', error);
            alert('Error constructing building. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    showTileDetails(tileData) {
        const detailsWindow = window.open('', '_blank', 'width=400,height=500');
        detailsWindow.document.write(`
            <html>
                <head><title>Tile Details - (${tileData.x}, ${tileData.y})</title></head>
                <body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h2>Tile Details</h2>
                    <p><strong>Coordinates:</strong> ${tileData.x}, ${tileData.y}</p>
                    <p><strong>Type:</strong> ${tileData.type}</p>
                    <p><strong>Owner:</strong> ${tileData.owner || 'Unclaimed'}</p>
                    <p><strong>Structures:</strong> ${tileData.structures.length}</p>
                    ${tileData.structures.length > 0 ? 
                        '<h3>Structures:</h3><ul>' + 
                        tileData.structures.map(s => `<li>${s.name}</li>`).join('') + 
                        '</ul>' : ''
                    }
                </body>
            </html>
        `);
    }

    setupEventHandlers() {
        // Handle map movement for loading new tiles
        this.map.on('moveend zoomend', () => {
            console.log('Map moved/zoomed - tiles will refresh automatically');
        });
        
        // Handle keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.target === document.body || e.target.id === this.containerId) {
                const moveDistance = 50;
                const center = this.map.getCenter();
                
                switch(e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        this.map.setView([center.lat + moveDistance, center.lng], this.map.getZoom());
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        this.map.setView([center.lat - moveDistance, center.lng], this.map.getZoom());
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        this.map.setView([center.lat, center.lng - moveDistance], this.map.getZoom());
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        this.map.setView([center.lat, center.lng + moveDistance], this.map.getZoom());
                        break;
                }
            }
        });
    }

    showLoading(show) {
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = show ? 'flex' : 'none';
        }
    }

    // Method to navigate to specific coordinates
    navigateToCoords(x, y, zoom = null) {
        const targetZoom = zoom || this.map.getZoom();
        this.map.setView([y, x], targetZoom);
    }

    // Method to refresh all visible tiles
    refreshMap() {
        this.tileCache.clear();
        this.tileLayer.redraw();
    }
}

// Make it globally available
window.WorldMapManager = WorldMapManager;

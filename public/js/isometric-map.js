/**
 * IsometricMap - A scrollable isometric world map renderer
 * Main orchestrator that coordinates all map modules
 */
class IsometricMap {
    constructor(canvasId, config = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            throw new Error(`Canvas element with id "${canvasId}" not found`);
        }

        this.ctx = this.canvas.getContext('2d');
        this.config = {
            tileWidth: config.tileWidth || 128,
            tileHeight: config.tileHeight || 64,
            imageBasePath: config.imageBasePath || '/images',
            overlaysEnabled: config.overlaysEnabled || false,
            ...config
        };

        // Map data
        this.sectors = [];
        this.images = new Map();
        this.imagesLoaded = false;
        this.hoveredTile = null;
        this.highlightedRegionIds = null;  // Set of region IDs to highlight
        this.attackTargetRegionId = null;  // The target region being attacked

        // Initialize modules
        this.cameraController = new CameraController(this.canvas, {
            minZoom: 0.5,
            maxZoom: 2.0
        });
        this.fleetManager = new FleetManager(this);
        this.tileRenderer = new TileRenderer(this.ctx, this.config);

        // Wire up camera callbacks
        this.cameraController.onRenderRequest = () => this.render();
        this.cameraController.onTileHover = (e) => this.updateHoveredTile(e);
        this.cameraController.onTileClick = (e) => this.handleTileClick(e);

        // Expose camera for external access
        this.camera = this.cameraController.camera;

        this.init();
    }

    init() {
        this.setupCanvas();
        this.cameraController.setupEventListeners();
        this.cameraController.centerCamera();
    }

    setupCanvas() {
        const resizeCanvas = () => {
            const rect = this.canvas.getBoundingClientRect();
            this.canvas.width = rect.width;
            this.canvas.height = rect.height;
            
            if (this.imagesLoaded) {
                this.render();
            }
        };

        requestAnimationFrame(() => resizeCanvas());
        window.addEventListener('resize', resizeCanvas);
    }

    // ===== Data Methods =====

    loadImages(sectors) {
        const imageUrls = new Set();

        sectors.forEach(sector => {
            imageUrls.add(`${this.config.imageBasePath}/map/${sector.image}`);
        });

        const loadPromises = Array.from(imageUrls).map(url => {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    this.images.set(url, img);
                    resolve();
                };
                img.onerror = () => reject(new Error(`Failed to load image: ${url}`));
                img.src = url;
            });
        });

        return Promise.all(loadPromises).then(() => {
            this.imagesLoaded = true;
        });
    }

    setSectors(sectors) {
        this.sectors = sectors;
        return this.loadImages(sectors);
    }

    setFleets(fleets) {
        this.fleetManager.setFleets(fleets);
    }

    setHighlightedRegions(regionIds, targetRegionId) {
        this.highlightedRegionIds = regionIds;
        this.attackTargetRegionId = targetRegionId;
    }

    clearHighlightedRegions() {
        this.highlightedRegionIds = null;
        this.attackTargetRegionId = null;
    }

    // ===== Coordinate Conversion =====

    cartesianToIsometric(cartX, cartY) {
        const isoX = (cartX - cartY) * (this.config.tileWidth / 2);
        const isoY = (cartX + cartY) * (this.config.tileHeight / 2);
        return { x: isoX, y: isoY };
    }

    getTileAtScreenPos(screenX, screenY) {
        const world = this.cameraController.screenToWorld(screenX, screenY);
        
        const gridX = Math.floor(world.x / this.config.tileWidth + world.y / this.config.tileHeight + 0.5);
        const gridY = Math.floor(world.y / this.config.tileHeight - world.x / this.config.tileWidth + 0.5);

        for (const sector of this.sectors) {
            if (sector.x === gridX && sector.y === gridY) {
                return sector;
            }
        }

        return null;
    }

    // ===== Interaction =====

    updateHoveredTile(e) {
        const rect = this.canvas.getBoundingClientRect();
        const tile = this.getTileAtScreenPos(e.clientX - rect.left, e.clientY - rect.top);

        if (tile !== this.hoveredTile) {
            this.hoveredTile = tile;
            this.render();
        }
    }

    handleTileClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const tile = this.getTileAtScreenPos(e.clientX - rect.left, e.clientY - rect.top);

        if (tile && this.config.onTileClick) {
            this.config.onTileClick(tile);
        }
    }

    // ===== Rendering =====

    render() {
        if (!this.imagesLoaded) {
            return;
        }

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.ctx.save();
        this.cameraController.applyTransform(this.ctx);

        // Sort and render tiles
        const sortedSectors = [...this.sectors].sort((a, b) => (a.x + a.y) - (b.x + b.y));
        sortedSectors.forEach(sector => this.renderTile(sector));

        // Render fleet lines
        this.fleetManager.renderFleetLines(this.ctx, (x, y) => this.cartesianToIsometric(x, y));

        this.ctx.restore();

        // Render UI elements
        this.renderUI();
        this.fleetManager.renderFleetUIElements(
            this.canvas, 
            this.camera, 
            (x, y) => this.cartesianToIsometric(x, y)
        );
    }

    renderTile(sector) {
        const iso = this.cartesianToIsometric(sector.x, sector.y);
        const imageUrl = `${this.config.imageBasePath}/map/${sector.image}`;
        const img = this.images.get(imageUrl);

        // Delegate to tile renderer with all game-specific logic
        this.tileRenderer.renderTile(sector, iso, img, this.hoveredTile, this.images);
    }

    /**
     * Proxy method for unit indicator rendering
     * Allows external access if needed
     */
    renderUnitIndicators(region, isoX, isoY) {
        this.tileRenderer.renderUnitIndicators(region, isoX, isoY);
    }

    renderUI() {
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
        this.ctx.fillRect(10, 10, 150, 30);

        this.ctx.fillStyle = '#f3e6c1';
        this.ctx.font = '14px Arial';
        this.ctx.textAlign = 'left';
        this.ctx.fillText(`Zoom: ${(this.camera.zoom * 100).toFixed(0)}%`, 20, 30);
    }

    // ===== Cleanup =====

    destroy() {
        this.fleetManager.destroy();
        window.removeEventListener('resize', this.setupCanvas);
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = IsometricMap;
}

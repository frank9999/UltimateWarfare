/**
 * HexMap - A scrollable hexagonal world map renderer
 * Main orchestrator that coordinates all map modules
 * Uses pointy-top hexagons with odd-r offset coordinates
 */
class HexMap {
    constructor(canvasId, config = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            throw new Error(
                'Canvas element with id "' + canvasId + '" not found'
            );
        }

        this.ctx = this.canvas.getContext('2d');

        // Hex geometry (pointy-top)
        const hexSize = config.hexSize || 40;
        const hexWidth = Math.sqrt(3) * hexSize;
        const hexHeight = 2 * hexSize;

        this.config = {
            hexSize: hexSize,
            hexWidth: hexWidth,
            hexHeight: hexHeight,
            rowStepY: hexHeight * 0.75,
            imageBasePath: config.imageBasePath || '/images',
            overlaysEnabled: config.overlaysEnabled || false,
            ...config
        };

        // Map data
        this.sectors = [];
        this.sectorLookup = new Map();
        this.images = new Map();
        this.imagesLoaded = false;
        this.hoveredTile = null;
        this.highlightedRegionIds = null;
        this.attackTargetRegionId = null;

        // Initialize modules
        this.cameraController = new CameraController(this.canvas, {
            minZoom: 0.3,
            maxZoom: 3.0
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
            imageUrls.add(
                this.config.imageBasePath + '/map/' + sector.image
            );
        });

        const loadPromises = Array.from(imageUrls).map(url => {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    this.images.set(url, img);
                    resolve();
                };
                img.onerror = () => {
                    console.warn('Failed to load tile image: ' + url);
                    resolve();
                };
                img.src = url;
            });
        });

        return Promise.all(loadPromises).then(() => {
            this.imagesLoaded = true;
        });
    }

    setSectors(sectors) {
        this.sectors = sectors;

        // Build O(1) lookup map keyed by "col,row"
        this.sectorLookup.clear();
        sectors.forEach(sector => {
            this.sectorLookup.set(sector.x + ',' + sector.y, sector);
        });

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

    // ===== Hex Coordinate Conversion =====

    /**
     * Convert grid (col, row) to pixel position (center of hex)
     * Uses odd-r offset: odd rows are shifted right by half a hex width
     */
    hexToPixel(col, row) {
        var x = col * this.config.hexWidth
            + (row % 2) * (this.config.hexWidth / 2);
        var y = row * this.config.rowStepY;
        return { x: x, y: y };
    }

    /**
     * Convert pixel position to grid (col, row) via nearest-hex lookup
     * Checks the estimated tile and its neighbors for the closest match
     */
    getTileAtScreenPos(screenX, screenY) {
        var world = this.cameraController.screenToWorld(screenX, screenY);

        // Approximate row from y
        var approxRow = Math.round(world.y / this.config.rowStepY);

        // Approximate col from x (accounting for odd-row offset)
        var rowOffset = (approxRow % 2) * (this.config.hexWidth / 2);
        var approxCol = Math.round(
            (world.x - rowOffset) / this.config.hexWidth
        );

        // Check candidate tile and 8 surrounding grid cells
        var bestTile = null;
        var bestDist = Infinity;

        for (var dr = -1; dr <= 1; dr++) {
            for (var dc = -1; dc <= 1; dc++) {
                var r = approxRow + dr;
                var c = approxCol + dc;
                var sector = this.sectorLookup.get(c + ',' + r);

                if (sector) {
                    var pos = this.hexToPixel(c, r);
                    var dx = world.x - pos.x;
                    var dy = world.y - pos.y;
                    var dist = dx * dx + dy * dy;

                    if (dist < bestDist) {
                        bestDist = dist;
                        bestTile = sector;
                    }
                }
            }
        }

        return bestTile;
    }

    // ===== Interaction =====

    updateHoveredTile(e) {
        var rect = this.canvas.getBoundingClientRect();
        var tile = this.getTileAtScreenPos(
            e.clientX - rect.left,
            e.clientY - rect.top
        );

        if (tile !== this.hoveredTile) {
            this.hoveredTile = tile;
            this.render();
        }
    }

    handleTileClick(e) {
        var rect = this.canvas.getBoundingClientRect();
        var tile = this.getTileAtScreenPos(
            e.clientX - rect.left,
            e.clientY - rect.top
        );

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

        // Render tiles row by row (no depth sorting needed for hex)
        var sortedSectors = this.sectors.slice().sort(function (a, b) {
            return (a.y - b.y) || (a.x - b.x);
        });
        var self = this;
        sortedSectors.forEach(function (sector) {
            self.renderTile(sector);
        });

        // Render fleet lines
        this.fleetManager.renderFleetLines(
            this.ctx,
            function (x, y) { return self.hexToPixel(x, y); }
        );

        this.ctx.restore();

        // Render UI elements
        this.renderUI();
        this.fleetManager.renderFleetUIElements(
            this.canvas,
            this.camera,
            function (x, y) { return self.hexToPixel(x, y); }
        );
    }

    renderTile(sector) {
        var pos = this.hexToPixel(sector.x, sector.y);
        var imageUrl = this.config.imageBasePath + '/map/' + sector.image;
        var img = this.images.get(imageUrl);

        this.tileRenderer.renderTile(
            sector, pos, img, this.hoveredTile, this.images
        );
    }

    renderUnitIndicators(region, posX, posY) {
        this.tileRenderer.renderUnitIndicators(region, posX, posY);
    }

    renderUI() {
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
        this.ctx.fillRect(10, 10, 150, 30);

        this.ctx.fillStyle = '#f3e6c1';
        this.ctx.font = '14px Arial';
        this.ctx.textAlign = 'left';
        this.ctx.fillText(
            'Zoom: ' + (this.camera.zoom * 100).toFixed(0) + '%',
            20,
            30
        );
    }

    // ===== Cleanup =====

    destroy() {
        this.fleetManager.destroy();
        window.removeEventListener('resize', this.setupCanvas);
    }
}

// Backward compatibility alias
var IsometricMap = HexMap;

if (typeof module !== 'undefined' && module.exports) {
    module.exports = HexMap;
}

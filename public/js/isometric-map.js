/**
 * IsometricMap - A scrollable isometric world map renderer
 * Works on all modern browsers with Canvas API support
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
            ...config
        };

        // Map data
        this.sectors = [];
        this.images = new Map();
        this.imagesLoaded = false;

        // Camera/viewport
        this.camera = {
            x: 0,
            y: 0,
            zoom: 1.0,
            minZoom: 0.5,
            maxZoom: 2.0
        };

        // Interaction
        this.isDragging = false;
        this.hasDragged = false;
        this.lastMousePos = { x: 0, y: 0 };
        this.hoveredTile = null;

        this.init();
    }

    init() {
        this.setupCanvas();
        this.setupEventListeners();
        this.centerCamera();
    }

    setupCanvas() {
        // Make canvas responsive
        const resizeCanvas = () => {
            const container = this.canvas.parentElement;
            this.canvas.width = container.clientWidth;
            this.canvas.height = Math.max(600, window.innerHeight * 0.7);
            this.render();
        };

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
    }

    setupEventListeners() {
        // Mouse wheel zoom
        this.canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomDelta = e.deltaY > 0 ? 0.9 : 1.1;
            const newZoom = this.camera.zoom * zoomDelta;

            if (newZoom >= this.camera.minZoom && newZoom <= this.camera.maxZoom) {
                // Zoom towards mouse position
                const rect = this.canvas.getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;

                this.camera.x -= (mouseX - this.canvas.width / 2) * (zoomDelta - 1);
                this.camera.y -= (mouseY - this.canvas.height / 2) * (zoomDelta - 1);
                this.camera.zoom = newZoom;

                this.render();
            }
        }, { passive: false });

        // Mouse dragging
        this.canvas.addEventListener('mousedown', (e) => {
            this.isDragging = true;
            this.hasDragged = false;
            this.lastMousePos = { x: e.clientX, y: e.clientY };
            this.canvas.style.cursor = 'grabbing';
        });

        this.canvas.addEventListener('mousemove', (e) => {
            if (this.isDragging) {
                const dx = e.clientX - this.lastMousePos.x;
                const dy = e.clientY - this.lastMousePos.y;

                // If mouse moved more than a small threshold, mark as dragged
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                    this.hasDragged = true;
                }

                this.camera.x += dx;
                this.camera.y += dy;

                this.lastMousePos = { x: e.clientX, y: e.clientY };
                this.render();
            } else {
                // Update hovered tile
                this.updateHoveredTile(e);
            }
        });

        this.canvas.addEventListener('mouseup', () => {
            this.isDragging = false;
            this.canvas.style.cursor = 'grab';
        });

        this.canvas.addEventListener('mouseleave', () => {
            this.isDragging = false;
            this.canvas.style.cursor = 'default';
        });

        // Touch support for mobile
        this.setupTouchEvents();

        // Click handling
        this.canvas.addEventListener('click', (e) => {
            // Only trigger click if user didn't drag
            if (!this.hasDragged) {
                this.handleTileClick(e);
            }
            this.hasDragged = false;
        });

        this.canvas.style.cursor = 'grab';
    }

    setupTouchEvents() {
        let lastTouchDistance = 0;

        this.canvas.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                this.isDragging = true;
                this.hasDragged = false;
                this.lastMousePos = {
                    x: e.touches[0].clientX,
                    y: e.touches[0].clientY
                };
            } else if (e.touches.length === 2) {
                lastTouchDistance = this.getTouchDistance(e.touches);
            }
            e.preventDefault();
        }, { passive: false });

        this.canvas.addEventListener('touchmove', (e) => {
            if (e.touches.length === 1 && this.isDragging) {
                const dx = e.touches[0].clientX - this.lastMousePos.x;
                const dy = e.touches[0].clientY - this.lastMousePos.y;

                // If touch moved more than a small threshold, mark as dragged
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                    this.hasDragged = true;
                }

                this.camera.x += dx;
                this.camera.y += dy;

                this.lastMousePos = {
                    x: e.touches[0].clientX,
                    y: e.touches[0].clientY
                };
                this.render();
            } else if (e.touches.length === 2) {
                // Pinch to zoom
                const currentDistance = this.getTouchDistance(e.touches);
                const zoomDelta = currentDistance / lastTouchDistance;

                const newZoom = this.camera.zoom * zoomDelta;
                if (newZoom >= this.camera.minZoom && newZoom <= this.camera.maxZoom) {
                    this.camera.zoom = newZoom;
                    this.render();
                }

                lastTouchDistance = currentDistance;
            }
            e.preventDefault();
        }, { passive: false });

        this.canvas.addEventListener('touchend', () => {
            this.isDragging = false;
        });
    }

    getTouchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    updateHoveredTile(e) {
        const rect = this.canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const tile = this.getTileAtScreenPos(mouseX, mouseY);

        if (tile !== this.hoveredTile) {
            this.hoveredTile = tile;
            this.render();
        }
    }

    handleTileClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const tile = this.getTileAtScreenPos(mouseX, mouseY);

        if (tile && this.config.onTileClick) {
            this.config.onTileClick(tile);
        }
    }

    getTileAtScreenPos(screenX, screenY) {
        // Convert screen coordinates to world coordinates
        const worldX = (screenX - this.canvas.width / 2 - this.camera.x) / this.camera.zoom;
        const worldY = (screenY - this.canvas.height / 2 - this.camera.y) / this.camera.zoom;

        // Convert world coordinates to isometric grid coordinates
        const gridX = Math.floor((worldX / (this.config.tileWidth / 2) + worldY / (this.config.tileHeight / 2)) / 2);
        const gridY = Math.floor((worldY / (this.config.tileHeight / 2) - worldX / (this.config.tileWidth / 2)) / 2);

        // Find the tile in our sectors array
        for (const sector of this.sectors) {
            if (sector.x === gridX && sector.y === gridY) {
                return sector;
            }
        }

        return null;
    }

    loadImages(sectors) {
        const imageUrls = new Set();

        sectors.forEach(sector => {
            imageUrls.add(`${this.config.imageBasePath}/map/${sector.image}`);
            if (sector.regionCount > 0) {
                imageUrls.add(`${this.config.imageBasePath}/player.gif`);
                imageUrls.add(`${this.config.imageBasePath}/you.png`);
            }
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

    centerCamera() {
        // Center on the middle of the map
        this.camera.x = 0;
        this.camera.y = 0;
    }

    cartesianToIsometric(cartX, cartY) {
        const isoX = (cartX - cartY) * (this.config.tileWidth / 2);
        const isoY = (cartX + cartY) * (this.config.tileHeight / 2);
        return { x: isoX, y: isoY };
    }

    render() {
        if (!this.imagesLoaded) {
            return;
        }

        // Clear canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Save context state
        this.ctx.save();

        // Apply camera transformations
        this.ctx.translate(this.canvas.width / 2, this.canvas.height / 2);
        this.ctx.translate(this.camera.x, this.camera.y);
        this.ctx.scale(this.camera.zoom, this.camera.zoom);

        // Sort sectors for proper rendering order (back to front)
        const sortedSectors = [...this.sectors].sort((a, b) => {
            return (a.x + a.y) - (b.x + b.y);
        });

        // Render all tiles
        sortedSectors.forEach(sector => {
            this.renderTile(sector);
        });

        // Restore context state
        this.ctx.restore();

        // Render UI elements (not affected by camera)
        this.renderUI();
    }

    renderTile(sector) {
        const iso = this.cartesianToIsometric(sector.x, sector.y);

        // Get the tile image
        const imageUrl = `${this.config.imageBasePath}/map/${sector.image}`;
        const img = this.images.get(imageUrl);

        if (img) {
            // Draw the base tile
            this.ctx.drawImage(
                img,
                iso.x - this.config.tileWidth / 2,
                iso.y - this.config.tileHeight / 2,
                this.config.tileWidth,
                this.config.tileHeight
            );

            // Highlight hovered tile
            if (this.hoveredTile && this.hoveredTile.x === sector.x && this.hoveredTile.y === sector.y) {
                this.ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
                this.ctx.fillRect(
                    iso.x - this.config.tileWidth / 2,
                    iso.y - this.config.tileHeight / 2,
                    this.config.tileWidth,
                    this.config.tileHeight
                );
            }

            // Draw region count if applicable
            if (sector.regionCount > 0) {
                this.ctx.save();
                this.ctx.fillStyle = '#f3e6c1';
                this.ctx.font = '14px Arial';
                this.ctx.textAlign = 'center';
                this.ctx.fillText(
                    `${sector.regionCount} regions`,
                    iso.x,
                    iso.y
                );
                this.ctx.restore();
            }
        }
    }

    renderUI() {
        // Draw zoom controls
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
        this.ctx.fillRect(10, 10, 150, 30);

        this.ctx.fillStyle = '#f3e6c1';
        this.ctx.font = '14px Arial';
        this.ctx.textAlign = 'left';
        this.ctx.fillText(`Zoom: ${(this.camera.zoom * 100).toFixed(0)}%`, 20, 30);
    }

    destroy() {
        // Clean up event listeners and resources
        window.removeEventListener('resize', this.setupCanvas);
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = IsometricMap;
}

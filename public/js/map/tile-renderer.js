/**
 * TileRenderer - Handles rendering of map tiles with game-specific features
 */
class TileRenderer {
    constructor(ctx, config) {
        this.ctx = ctx;
        this.config = config;
        this.unitRenderer = new UnitRenderer(ctx);
    }

    /**
     * Render a tile with all game-specific features
     */
    renderTile(region, iso, img, hoveredTile, images) {
        if (img) {
            // Draw the base terrain tile
            this.ctx.drawImage(
                img,
                iso.x - this.config.tileWidth / 2,
                iso.y - this.config.tileHeight / 2,
                this.config.tileWidth,
                this.config.tileHeight
            );

            // Add ownership overlay if enabled
            if (this.config.overlaysEnabled) {
                this.renderOwnershipOverlay(region, iso);
            }

            // Draw coordinates and owner name
            this.renderTileLabels(region, iso);

            // Render unit indicators for your regions
            this.unitRenderer.renderUnitIndicators(region, iso.x, iso.y, this.config.tileHeight);

            // Highlight hovered tile
            if (hoveredTile && hoveredTile.x === region.x && hoveredTile.y === region.y) {
                this.renderHoverHighlight(region, iso);
            }
        } else {
            // Fallback if image not loaded - draw colored diamond
            this.renderFallbackTile(region, iso);
        }
    }

    /**
     * Render ownership overlay as diamond shape
     */
    renderOwnershipOverlay(region, iso) {
        this.ctx.save();
        
        if (region.hasOwner) {
            if (region.isYours) {
                // Blue tint for your regions
                this.ctx.fillStyle = 'rgba(74, 90, 124, 0.9)';
            } else {
                // Red tint for enemy regions
                this.ctx.fillStyle = 'rgba(124, 74, 74, 0.9)';
            }
        } else {
            // Green tint for free regions
            this.ctx.fillStyle = 'rgba(74, 124, 89, 0.9)';
        }

        this.drawDiamond(iso);
        this.ctx.fill();
        this.ctx.restore();
    }

    /**
     * Render coordinates and owner name labels
     */
    renderTileLabels(region, iso) {
        this.ctx.save();
        this.ctx.fillStyle = '#ffffff';
        this.ctx.font = '9px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 2;

        // Coordinates
        const coordText = `${region.x},${region.y}`;
        this.ctx.strokeText(coordText, iso.x, iso.y - 8);
        this.ctx.fillText(coordText, iso.x, iso.y - 8);

        // Owner name if applicable
        if (region.hasOwner && region.ownerName) {
            this.ctx.font = '8px Arial';
            this.ctx.strokeText(region.ownerName, iso.x, iso.y + 2);
            this.ctx.fillText(region.ownerName, iso.x, iso.y + 2);
        }

        this.ctx.restore();
    }

    /**
     * Render hover highlight with diamond shape and info
     */
    renderHoverHighlight(region, iso) {
        // Draw diamond-shaped outline
        this.ctx.save();
        this.ctx.strokeStyle = '#ffffff';
        this.ctx.lineWidth = 3;
        
        this.drawDiamond(iso);
        this.ctx.stroke();
        this.ctx.restore();

        // Draw enhanced region info on hover
        this.ctx.save();
        this.ctx.fillStyle = '#ffffff';
        this.ctx.font = 'bold 12px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 3;

        const hoverText = `${region.x}, ${region.y}`;
        this.ctx.strokeText(hoverText, iso.x, iso.y - 20);
        this.ctx.fillText(hoverText, iso.x, iso.y - 20);

        this.ctx.restore();
    }

    /**
     * Render fallback tile when image not loaded
     */
    renderFallbackTile(region, iso) {
        this.ctx.save();
        
        if (region.hasOwner) {
            this.ctx.fillStyle = region.isYours ? '#4a5a7c' : '#7c4a4a';
        } else {
            this.ctx.fillStyle = '#4a7c59';
        }
        
        this.drawDiamond(iso);
        this.ctx.fill();
        this.ctx.restore();
    }

    /**
     * Draw a diamond shape at the given isometric position
     */
    drawDiamond(iso) {
        this.ctx.beginPath();
        this.ctx.moveTo(iso.x, iso.y - this.config.tileHeight / 2); // Top
        this.ctx.lineTo(iso.x + this.config.tileWidth / 2, iso.y);   // Right
        this.ctx.lineTo(iso.x, iso.y + this.config.tileHeight / 2); // Bottom
        this.ctx.lineTo(iso.x - this.config.tileWidth / 2, iso.y);   // Left
        this.ctx.closePath();
    }

    /**
     * Proxy to unit renderer for external access
     */
    renderUnitIndicators(region, isoX, isoY) {
        this.unitRenderer.renderUnitIndicators(region, isoX, isoY, this.config.tileHeight);
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TileRenderer;
}

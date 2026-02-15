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

            // Draw fog of war if a region is not visible
            if (region.isVisible === false) {
                this.renderFogOfWar(
                    iso.x,
                    iso.y,
                    this.config.tileWidth,
                    this.config.tileHeight,
                    region.x,
                    region.y
                );
            }

            // Add ownership overlay if enabled
            if (this.config.overlaysEnabled) {
                this.renderOwnershipOverlay(region, iso);
            }

            // Attack mode highlighting
            if (region._attackTarget) {
                this.ctx.save();
                this.ctx.strokeStyle = '#ff0000';
                this.ctx.lineWidth = 3;
                this.drawDiamond(iso);
                this.ctx.stroke();
                this.ctx.restore();
            } else if (region._sendSource) {
                this.ctx.save();
                this.ctx.strokeStyle = '#2196F3';
                this.ctx.lineWidth = 3;
                this.drawDiamond(iso);
                this.ctx.stroke();
                this.ctx.restore();
            } else if (region._attackEligible) {
                this.ctx.save();
                this.ctx.fillStyle = 'rgba(0, 255, 100, 0.35)';
                this.drawDiamond(iso);
                this.ctx.fill();
                this.ctx.strokeStyle = '#00ff64';
                this.ctx.lineWidth = 2;
                this.ctx.stroke();
                this.ctx.restore();
            } else if (typeof region._attackEligible !== 'undefined' && !region._attackEligible) {
                this.ctx.save();
                this.ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
                this.drawDiamond(iso);
                this.ctx.fill();
                this.ctx.restore();
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
     * Render ownership overlay as a diamond shape
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
            this.ctx.fillStyle = region.isYours ? 'rgba(100, 150, 255, 0.95)' : 'rgba(255, 100, 100, 0.95)';
            this.ctx.font = '10px Arial';
            this.ctx.strokeText(region.ownerName, iso.x, iso.y + 2);
            this.ctx.fillText(region.ownerName, iso.x, iso.y + 2);
        }

        this.ctx.restore();
    }

    /**
     * Render hover highlight with diamond shape and info
     */
    renderHoverHighlight(region, iso) {
        // Draw a diamond-shaped outline
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

    /**
     * Render fog of war as a semi-transparent cloud overlay
     * Highly optimized for performance with static shapes
     */
    renderFogOfWar(isoX, isoY, tileWidth, tileHeight, tileX, tileY) {
        // Save context state
        this.ctx.save();

        // Create a diamond shape clipping path
        this.ctx.beginPath();
        this.ctx.moveTo(isoX, isoY - tileHeight / 2);
        this.ctx.lineTo(isoX + tileWidth / 2, isoY);
        this.ctx.lineTo(isoX, isoY + tileHeight / 2);
        this.ctx.lineTo(isoX - tileWidth / 2, isoY);
        this.ctx.closePath();
        this.ctx.clip();

        // Base dark overlay with subtle gradient (50% transparent)
        const baseGradient = this.ctx.createRadialGradient(
            isoX, isoY, 0,
            isoX, isoY, tileWidth / 2
        );
        baseGradient.addColorStop(0, 'rgba(25, 25, 35, 0.45)');
        baseGradient.addColorStop(1, 'rgba(15, 15, 25, 0.5)');
        this.ctx.fillStyle = baseGradient;
        this.ctx.fill();

        // Add static cloud effect (cheap to render)
        // Using deterministic positions based on tile coordinates for consistency
        const seed = tileX * 73 + tileY * 37;
        const numClouds = 2; // Keep minimal for performance

        for (let i = 0; i < numClouds; i++) {
            const angle = ((seed + i * 100) % 360) * Math.PI / 180;
            const offsetX = Math.cos(angle) * 12;
            const offsetY = Math.sin(angle) * 8;
            const radius = 25 + ((seed + i * 50) % 10);

            const gradient = this.ctx.createRadialGradient(
                isoX + offsetX, isoY + offsetY, 0,
                isoX + offsetX, isoY + offsetY, radius
            );
            gradient.addColorStop(0, 'rgba(50, 50, 70, 0.25)');
            gradient.addColorStop(0.5, 'rgba(40, 40, 60, 0.15)');
            gradient.addColorStop(1, 'rgba(30, 30, 50, 0)');

            this.ctx.fillStyle = gradient;
            this.ctx.fillRect(
                isoX - tileWidth / 2,
                isoY - tileHeight / 2,
                tileWidth,
                tileHeight
            );
        }

        // Add a question mark in the center for unknown territory (50% transparent)
        this.ctx.fillStyle = 'rgba(160, 160, 180, 0.5)';
        this.ctx.font = 'bold 28px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        this.ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
        this.ctx.shadowBlur = 4;
        this.ctx.fillText('?', isoX, isoY);

        // Restore context state (removes clipping and shadow)
        this.ctx.restore();
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TileRenderer;
}

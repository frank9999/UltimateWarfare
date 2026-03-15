/**
 * TileRenderer - Handles rendering of hex map tiles with game-specific features
 * Uses pointy-top hexagons
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
    renderTile(region, pos, img, hoveredTile, images) {
        if (img) {
            // Clip terrain image to hex shape
            this.ctx.save();
            this.drawHex(pos);
            this.ctx.clip();

            // Draw the terrain image scaled to fill the hex bounding box
            this.ctx.drawImage(
                img,
                pos.x - this.config.hexWidth / 2,
                pos.y - this.config.hexHeight / 2,
                this.config.hexWidth,
                this.config.hexHeight
            );
            this.ctx.restore();

            // Draw subtle hex border
            this.ctx.save();
            this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.3)';
            this.ctx.lineWidth = 1;
            this.drawHex(pos);
            this.ctx.stroke();
            this.ctx.restore();

            // Draw fog of war if region is not visible
            if (region.isVisible === false) {
                this.renderFogOfWar(pos, region.x, region.y);
            }

            // Add ownership overlay if enabled
            if (this.config.overlaysEnabled) {
                this.renderOwnershipOverlay(region, pos);
            }

            // Attack mode highlighting
            this.renderModeHighlights(region, pos);

            // Draw coordinates and owner name
            this.renderTileLabels(region, pos);

            // Render unit indicators for your regions
            this.unitRenderer.renderUnitIndicators(
                region, pos.x, pos.y, this.config.hexHeight
            );

            // Highlight hovered tile
            if (
                hoveredTile
                && hoveredTile.x === region.x
                && hoveredTile.y === region.y
            ) {
                this.renderHoverHighlight(region, pos);
            }
        } else {
            // Fallback if image not loaded - draw colored hex
            this.renderFallbackTile(region, pos);
        }
    }

    /**
     * Render attack/send mode highlights on a tile
     */
    renderModeHighlights(region, pos) {
        if (region._attackTarget) {
            this.ctx.save();
            this.ctx.strokeStyle = '#ff0000';
            this.ctx.lineWidth = 3;
            this.drawHex(pos);
            this.ctx.stroke();
            this.ctx.restore();
        } else if (region._sendSource) {
            this.ctx.save();
            this.ctx.strokeStyle = '#2196F3';
            this.ctx.lineWidth = 3;
            this.drawHex(pos);
            this.ctx.stroke();
            this.ctx.restore();
        } else if (region._attackEligible) {
            this.ctx.save();
            this.ctx.fillStyle = 'rgba(0, 255, 100, 0.35)';
            this.drawHex(pos);
            this.ctx.fill();
            this.ctx.strokeStyle = '#00ff64';
            this.ctx.lineWidth = 2;
            this.ctx.stroke();
            this.ctx.restore();
        } else if (
            typeof region._attackEligible !== 'undefined'
            && !region._attackEligible
        ) {
            this.ctx.save();
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
            this.drawHex(pos);
            this.ctx.fill();
            this.ctx.restore();
        }
    }

    /**
     * Render ownership overlay as a hex shape
     */
    renderOwnershipOverlay(region, pos) {
        this.ctx.save();

        if (region.hasOwner) {
            if (region.isYours) {
                this.ctx.fillStyle = 'rgba(74, 90, 124, 0.9)';
            } else {
                this.ctx.fillStyle = 'rgba(124, 74, 74, 0.9)';
            }
        } else {
            this.ctx.fillStyle = 'rgba(74, 124, 89, 0.9)';
        }

        this.drawHex(pos);
        this.ctx.fill();
        this.ctx.restore();
    }

    /**
     * Render coordinates and owner name labels
     */
    renderTileLabels(region, pos) {
        this.ctx.save();
        this.ctx.fillStyle = '#ffffff';
        this.ctx.font = '9px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 2;

        var coordText = region.x + ',' + region.y;
        this.ctx.strokeText(coordText, pos.x, pos.y - 8);
        this.ctx.fillText(coordText, pos.x, pos.y - 8);

        if (region.hasOwner && region.ownerName) {
            this.ctx.fillStyle = region.isYours
                ? 'rgba(100, 150, 255, 0.95)'
                : 'rgba(255, 100, 100, 0.95)';
            this.ctx.font = '10px Arial';
            this.ctx.strokeText(region.ownerName, pos.x, pos.y + 2);
            this.ctx.fillText(region.ownerName, pos.x, pos.y + 2);
        }

        this.ctx.restore();
    }

    /**
     * Render hover highlight with hex outline and coordinate info
     */
    renderHoverHighlight(region, pos) {
        // Draw hex outline
        this.ctx.save();
        this.ctx.strokeStyle = '#ffffff';
        this.ctx.lineWidth = 3;
        this.drawHex(pos);
        this.ctx.stroke();
        this.ctx.restore();

        // Draw enhanced region info above the tile
        this.ctx.save();
        this.ctx.fillStyle = '#ffffff';
        this.ctx.font = 'bold 12px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 3;

        var hoverText = region.x + ', ' + region.y;
        var labelY = pos.y - this.config.hexSize - 5;
        this.ctx.strokeText(hoverText, pos.x, labelY);
        this.ctx.fillText(hoverText, pos.x, labelY);

        this.ctx.restore();
    }

    /**
     * Render fallback tile when image not loaded
     */
    renderFallbackTile(region, pos) {
        this.ctx.save();

        var terrainColors = {
            deep_water: '#143278',
            water: '#2350a5',
            shallow_water: '#4696c3',
            sand: '#d2be8c',
            grassland: '#4b8c3c',
            forest: '#235523',
            hills: '#6e914b',
            mountain: '#645f55',
            beach: '#d2be8c',
            forrest: '#235523'
        };

        if (region.hasOwner) {
            this.ctx.fillStyle = region.isYours
                ? '#4a5a7c'
                : '#7c4a4a';
        } else {
            this.ctx.fillStyle = terrainColors[region.type] || '#4a7c59';
        }

        this.drawHex(pos);
        this.ctx.fill();
        this.ctx.restore();
    }

    /**
     * Draw a pointy-top hexagon centered at pos
     * 6 vertices at 60-degree intervals starting at -30 degrees
     */
    drawHex(pos) {
        var size = this.config.hexSize;
        this.ctx.beginPath();

        for (var i = 0; i < 6; i++) {
            var angle = (Math.PI / 180) * (60 * i - 30);
            var vx = pos.x + size * Math.cos(angle);
            var vy = pos.y + size * Math.sin(angle);

            if (i === 0) {
                this.ctx.moveTo(vx, vy);
            } else {
                this.ctx.lineTo(vx, vy);
            }
        }

        this.ctx.closePath();
    }

    /**
     * Proxy to unit renderer for external access
     */
    renderUnitIndicators(region, posX, posY) {
        this.unitRenderer.renderUnitIndicators(
            region, posX, posY, this.config.hexHeight
        );
    }

    /**
     * Render fog of war as a semi-transparent overlay clipped to hex
     */
    renderFogOfWar(pos, tileX, tileY) {
        this.ctx.save();

        // Hex clipping path
        this.drawHex(pos);
        this.ctx.clip();

        // Dark overlay with radial gradient
        var gradient = this.ctx.createRadialGradient(
            pos.x, pos.y, 0,
            pos.x, pos.y, this.config.hexSize
        );
        gradient.addColorStop(0, 'rgba(25, 25, 35, 0.45)');
        gradient.addColorStop(1, 'rgba(15, 15, 25, 0.5)');
        this.ctx.fillStyle = gradient;
        this.ctx.fill();

        // Static cloud effects (deterministic positions)
        var seed = tileX * 73 + tileY * 37;
        for (var i = 0; i < 2; i++) {
            var angle = ((seed + i * 100) % 360) * Math.PI / 180;
            var offsetX = Math.cos(angle) * 12;
            var offsetY = Math.sin(angle) * 8;
            var radius = 25 + ((seed + i * 50) % 10);

            var cloudGrad = this.ctx.createRadialGradient(
                pos.x + offsetX, pos.y + offsetY, 0,
                pos.x + offsetX, pos.y + offsetY, radius
            );
            cloudGrad.addColorStop(0, 'rgba(50, 50, 70, 0.25)');
            cloudGrad.addColorStop(0.5, 'rgba(40, 40, 60, 0.15)');
            cloudGrad.addColorStop(1, 'rgba(30, 30, 50, 0)');

            this.ctx.fillStyle = cloudGrad;
            this.ctx.fillRect(
                pos.x - this.config.hexWidth / 2,
                pos.y - this.config.hexHeight / 2,
                this.config.hexWidth,
                this.config.hexHeight
            );
        }

        // Question mark for unknown territory
        this.ctx.fillStyle = 'rgba(160, 160, 180, 0.5)';
        this.ctx.font = 'bold 28px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        this.ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
        this.ctx.shadowBlur = 4;
        this.ctx.fillText('?', pos.x, pos.y);

        this.ctx.restore();
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TileRenderer;
}

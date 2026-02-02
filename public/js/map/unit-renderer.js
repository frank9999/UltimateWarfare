/**
 * UnitRenderer - Handles rendering of unit indicators on tiles
 */
class UnitRenderer {
    constructor(ctx) {
        this.ctx = ctx;
    }

    /**
     * Draw a simple icon representing a unit type
     */
    drawUnitIcon(type, x, y, size, color) {
        this.ctx.save();
        this.ctx.fillStyle = color;
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 1;

        switch (type) {
            case 'buildings':
                this.drawBuildingIcon(x, y, size);
                break;
            case 'defences':
                this.drawShieldIcon(x, y, size);
                break;
            case 'units':
                this.drawTankIcon(x, y, size);
                break;
            case 'special':
                this.drawStarIcon(x, y, size);
                break;
            case 'specialUnits':
                this.drawDiamondIcon(x, y, size);
                break;
        }

        this.ctx.restore();
    }

    drawBuildingIcon(x, y, size) {
        this.ctx.beginPath();
        this.ctx.moveTo(x, y - size * 0.6);
        this.ctx.lineTo(x + size * 0.5, y - size * 0.1);
        this.ctx.lineTo(x + size * 0.5, y + size * 0.5);
        this.ctx.lineTo(x - size * 0.5, y + size * 0.5);
        this.ctx.lineTo(x - size * 0.5, y - size * 0.1);
        this.ctx.closePath();
        this.ctx.fill();
        this.ctx.stroke();
    }

    drawShieldIcon(x, y, size) {
        this.ctx.beginPath();
        this.ctx.moveTo(x, y - size * 0.5);
        this.ctx.lineTo(x + size * 0.45, y - size * 0.3);
        this.ctx.lineTo(x + size * 0.45, y + size * 0.1);
        this.ctx.quadraticCurveTo(x, y + size * 0.6, x, y + size * 0.6);
        this.ctx.quadraticCurveTo(x, y + size * 0.6, x - size * 0.45, y + size * 0.1);
        this.ctx.lineTo(x - size * 0.45, y - size * 0.3);
        this.ctx.closePath();
        this.ctx.fill();
        this.ctx.stroke();
    }

    drawTankIcon(x, y, size) {
        const ctx = this.ctx;
        const fillStyle = ctx.fillStyle;
        
        // Tank body
        ctx.beginPath();
        ctx.roundRect(x - size * 0.45, y - size * 0.1, size * 0.9, size * 0.45, 3);
        ctx.fill();
        ctx.stroke();
        
        // Tank turret
        ctx.beginPath();
        ctx.roundRect(x - size * 0.25, y - size * 0.35, size * 0.5, size * 0.3, 2);
        ctx.fill();
        ctx.stroke();
        
        // Tank cannon
        ctx.beginPath();
        ctx.moveTo(x + size * 0.25, y - size * 0.2);
        ctx.lineTo(x + size * 0.55, y - size * 0.2);
        ctx.lineTo(x + size * 0.55, y - size * 0.12);
        ctx.lineTo(x + size * 0.25, y - size * 0.12);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Tank tracks
        ctx.fillStyle = '#333';
        ctx.beginPath();
        ctx.roundRect(x - size * 0.5, y + size * 0.2, size * 1.0, size * 0.2, 2);
        ctx.fill();
        ctx.stroke();
        
        ctx.fillStyle = fillStyle;
    }

    drawStarIcon(x, y, size) {
        this.drawStar(x, y, 5, size * 0.5, size * 0.25);
        this.ctx.fill();
        this.ctx.stroke();
    }

    drawDiamondIcon(x, y, size) {
        this.ctx.beginPath();
        this.ctx.moveTo(x, y - size * 0.5);
        this.ctx.lineTo(x + size * 0.4, y);
        this.ctx.lineTo(x, y + size * 0.5);
        this.ctx.lineTo(x - size * 0.4, y);
        this.ctx.closePath();
        this.ctx.fill();
        this.ctx.stroke();
    }

    drawStar(cx, cy, spikes, outerRadius, innerRadius) {
        let rot = Math.PI / 2 * 3;
        let step = Math.PI / spikes;

        this.ctx.beginPath();
        this.ctx.moveTo(cx, cy - outerRadius);

        for (let i = 0; i < spikes; i++) {
            let x = cx + Math.cos(rot) * outerRadius;
            let y = cy + Math.sin(rot) * outerRadius;
            this.ctx.lineTo(x, y);
            rot += step;

            x = cx + Math.cos(rot) * innerRadius;
            y = cy + Math.sin(rot) * innerRadius;
            this.ctx.lineTo(x, y);
            rot += step;
        }

        this.ctx.lineTo(cx, cy - outerRadius);
        this.ctx.closePath();
    }

    /**
     * Render unit indicators on a tile
     */
    renderUnitIndicators(region, isoX, isoY, tileHeight) {
        if (!region.isYours || !region.units) {
            return;
        }

        const units = region.units;
        const iconSize = 10;
        const spacing = 14;
        
        // Collect active unit types with color palette
        const activeTypes = [];
        if (units.buildings > 0) activeTypes.push({ type: 'buildings', count: units.buildings, color: '#3d5a80', label: 'Buildings' });
        if (units.defences > 0) activeTypes.push({ type: 'defences', count: units.defences, color: '#7b6d8d', label: 'Defences' });
        if (units.units > 0) activeTypes.push({ type: 'units', count: units.units, color: '#6b8e6b', label: 'Units' });
        if (units.special > 0) activeTypes.push({ type: 'special', count: units.special, color: '#d4a03c', label: 'Special' });
        if (units.specialUnits > 0) activeTypes.push({ type: 'specialUnits', count: units.specialUnits, color: '#c75146', label: 'Elite Units' });

        if (activeTypes.length === 0) {
            return;
        }

        const startX = isoX - ((activeTypes.length - 1) * spacing) / 2;
        const baseY = isoY + tileHeight * 0.15;

        // Store icon positions for hover detection
        region.iconPositions = [];

        activeTypes.forEach((item, index) => {
            const iconX = startX + (index * spacing);
            const iconY = baseY;

            region.iconPositions.push({
                x: iconX,
                y: iconY,
                size: iconSize,
                type: item.type,
                count: item.count,
                label: item.label,
                color: item.color,
                details: units.details ? units.details[item.type] : null
            });

            // Draw background circle
            this.ctx.save();
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
            this.ctx.beginPath();
            this.ctx.arc(iconX, iconY, iconSize * 0.8, 0, Math.PI * 2);
            this.ctx.fill();
            this.ctx.restore();

            // Draw the icon
            this.drawUnitIcon(item.type, iconX, iconY, iconSize, item.color);
        });
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UnitRenderer;
}

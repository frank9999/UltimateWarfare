/**
 * Map initialization, UI controls, and unit tooltip.
 * Depends on: notifications.js, modals.js (WorldApp.defaultTileClick)
 */
(function () {
    var worldRegions = WorldApp.worldRegions;
    var playerFleets = WorldApp.playerFleets;

    console.log('Loading ' + worldRegions.length + ' world regions...');

    var worldMap = new IsometricMap('worldMap', {
        tileWidth: 128,
        tileHeight: 64,
        imageBasePath: WorldApp.imageBasePath,
        overlaysEnabled: false,
        onTileClick: WorldApp.defaultTileClick
    });

    WorldApp.worldMap = worldMap;

    worldMap.setSectors(worldRegions).then(function () {
        worldMap.setFleets(playerFleets);
        worldMap.render();
        console.log('World map loaded successfully with ' + worldRegions.length + ' regions!');
    }).catch(function (error) {
        console.error('Failed to load world map:', error);
        showNotification('Failed to load world map. Please check the console for details.', 'error');
    });

    // ===== UI Controls =====
    document.getElementById('toggleOverlay').addEventListener('click', function () {
        worldMap.config.overlaysEnabled = !worldMap.config.overlaysEnabled;
        this.textContent = worldMap.config.overlaysEnabled ? 'Hide Overlays' : 'Show Overlays';
        worldMap.render();
    });

    document.getElementById('resetViewBtn').addEventListener('click', function () {
        worldMap.cameraController.resetView();
    });

    document.getElementById('zoomInBtn').addEventListener('click', function () {
        worldMap.cameraController.zoomIn();
    });

    document.getElementById('zoomOutBtn').addEventListener('click', function () {
        worldMap.cameraController.zoomOut();
    });

    document.getElementById('profileDropdownBtn').addEventListener('click', function (e) {
        e.stopPropagation();
        document.getElementById('profileDropdown').classList.toggle('show');
    });

    window.addEventListener('click', function () {
        var dropdown = document.getElementById('profileDropdown');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    });

    // ===== Unit Tooltip =====
    var unitTooltip = document.getElementById('unitTooltip');

    worldMap.canvas.addEventListener('mousemove', function (e) {
        var rect = worldMap.canvas.getBoundingClientRect();
        var mouseX = e.clientX - rect.left;
        var mouseY = e.clientY - rect.top;

        var worldPos = worldMap.cameraController.screenToWorld(mouseX, mouseY);
        var foundIcon = null;

        for (var i = 0; i < worldMap.sectors.length; i++) {
            var region = worldMap.sectors[i];
            if (!region.iconPositions) continue;

            for (var j = 0; j < region.iconPositions.length; j++) {
                var icon = region.iconPositions[j];
                var dx = worldPos.x - icon.x;
                var dy = worldPos.y - icon.y;
                var distance = Math.sqrt(dx * dx + dy * dy);

                if (distance <= icon.size) {
                    foundIcon = { icon: icon, region: region };
                    break;
                }
            }
            if (foundIcon) break;
        }

        if (foundIcon) {
            var fi = foundIcon.icon;
            var tooltipHtml = '<div class="tooltip-title" style="color: ' + fi.color + '">' + fi.label + '</div>';

            if (fi.details && fi.details.length > 0) {
                fi.details.forEach(function (unit) {
                    tooltipHtml += '<div class="tooltip-item">' +
                        '<span class="unit-name">' + unit.name + '</span>' +
                        '<span class="unit-count">' + unit.amount + '</span></div>';
                });
            }

            tooltipHtml += '<div class="tooltip-total">Total: ' + fi.count + '</div>';

            unitTooltip.innerHTML = tooltipHtml;
            unitTooltip.style.display = 'block';
            unitTooltip.style.left = (e.clientX + 15) + 'px';
            unitTooltip.style.top = (e.clientY + 10) + 'px';
        } else {
            unitTooltip.style.display = 'none';
        }
    });

    worldMap.canvas.addEventListener('mouseleave', function () {
        unitTooltip.style.display = 'none';
    });
})();

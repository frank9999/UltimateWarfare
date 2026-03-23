/**
 * Map initialization, UI controls, and unit tooltip.
 * Depends on: notifications.js, modals.js (WorldApp.defaultTileClick)
 */
(function () {
    var worldRegions = WorldApp.worldRegions;
    var playerFleets = WorldApp.playerFleets;
    var playerBombardments = WorldApp.playerBombardments || [];

    // Find the player's first owned region for centering
    var homeRegion = null;
    for (var i = 0; i < worldRegions.length; i++) {
        if (worldRegions[i].isYours) {
            homeRegion = worldRegions[i];
            break;
        }
    }

    console.log('Loading ' + worldRegions.length + ' world regions...');

    var worldMap = new HexMap('worldMap', {
        hexSize: 40,
        imageBasePath: WorldApp.imageBasePath,
        overlaysEnabled: false,
        onTileClick: WorldApp.defaultTileClick
    });

    WorldApp.worldMap = worldMap;

    function centerOnHomeRegion() {
        if (homeRegion) {
            var pos = worldMap.hexToPixel(homeRegion.x, homeRegion.y);
            worldMap.cameraController.resetView(pos.x, pos.y);
        } else {
            worldMap.cameraController.resetView();
        }
    }

    worldMap.setSectors(worldRegions).then(function () {
        worldMap.setFleets(playerFleets);
        worldMap.setBombardments(playerBombardments);
        centerOnHomeRegion();
        console.log('World map loaded successfully with ' + worldRegions.length + ' regions!');
    }).catch(function (error) {
        console.error('Failed to load world map:', error);
        showNotification('Failed to load world map. Please check the console for details.', 'error');
    });

    // ===== UI Controls =====
    var overlayLegend = document.getElementById('overlayLegend');

    document.getElementById('toggleOverlay').addEventListener('click', function () {
        worldMap.config.overlaysEnabled = !worldMap.config.overlaysEnabled;
        overlayLegend.style.display = worldMap.config.overlaysEnabled ? 'flex' : 'none';
        worldMap.render();
    });

    document.getElementById('resetViewBtn').addEventListener('click', function () {
        centerOnHomeRegion();
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
    var tooltipRAF = null;

    worldMap.canvas.addEventListener('mousemove', function (e) {
        if (tooltipRAF) return;
        var evt = e;
        tooltipRAF = requestAnimationFrame(function () {
            tooltipRAF = null;

            var rect = worldMap.canvas.getBoundingClientRect();
            var mouseX = evt.clientX - rect.left;
            var mouseY = evt.clientY - rect.top;

            // O(1) tile lookup instead of iterating all sectors
            var hoveredRegion = worldMap.getTileAtScreenPos(mouseX, mouseY);
            var foundIcon = null;

            if (hoveredRegion && hoveredRegion.iconPositions) {
                var worldPos = worldMap.cameraController.screenToWorld(mouseX, mouseY);
                for (var j = 0; j < hoveredRegion.iconPositions.length; j++) {
                    var icon = hoveredRegion.iconPositions[j];
                    var dx = worldPos.x - icon.x;
                    var dy = worldPos.y - icon.y;
                    var distance = Math.sqrt(dx * dx + dy * dy);

                    if (distance <= icon.size) {
                        foundIcon = { icon: icon, region: hoveredRegion };
                        break;
                    }
                }
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
                unitTooltip.style.left = (evt.clientX + 15) + 'px';
                unitTooltip.style.top = (evt.clientY + 10) + 'px';
            } else {
                unitTooltip.style.display = 'none';
            }
        });
    });

    worldMap.canvas.addEventListener('mouseleave', function () {
        unitTooltip.style.display = 'none';
    });
})();

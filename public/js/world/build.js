/**
 * Build modal logic.
 * Depends on: notifications.js (showNotification), and WorldApp.imageBasePath being set.
 */
(function () {
    const buildModal = document.getElementById('buildModal');
    const closeBuildModal = document.getElementById('closeBuildModal');
    const closeBuildBtn = document.getElementById('closeBuildBtn');
    const confirmBuildBtn = document.getElementById('confirmBuildBtn');

    let selectedBuildRegion = null;
    let currentBuildData = null;
    let selectedGameUnitCategoryId = 1;
    let buildQuantities = {};
    let availableCategories = [];

    const gameUnitCategories = [
        { id: 1, name: 'Buildings' },
        { id: 2, name: 'Defense Buildings' },
        { id: 3, name: 'Special Buildings' },
        { id: 5, name: 'Elite Units' },
        { id: 6, name: 'Troops' },
        { id: 7, name: 'Naval Units' },
        { id: 8, name: 'Air Units' },
        { id: 9, name: 'Missiles' }
    ];

    // ===== Unit Info Tooltip =====
    const unitInfoTooltip = document.getElementById('unitInfoTooltip');
    let currentTooltipEvent = null;

    function showUnitInfoTooltip(event, unit) {
        currentTooltipEvent = event;
        const imgBase = WorldApp.imageBasePath;

        let tooltipHtml = '<div class="unit-info-header">' +
            '<img src="' + imgBase + '/' + unit.imageDir + unit.image + '" alt="' + unit.name + '" class="unit-info-image">' +
            '<div class="unit-info-title"><div class="unit-info-name">' + unit.name + '</div></div></div>';

        if (unit.description) {
            tooltipHtml += '<div class="unit-info-description">' + unit.description + '</div>';
        }

        tooltipHtml += '<div class="unit-info-section"><div class="unit-info-section-title">Build Cost</div><div class="unit-info-grid">';

        const costItems = [
            { key: 'costCash', icon: 'resource_cash.jpg', label: 'Cash' },
            { key: 'costWood', icon: 'resource_wood.jpg', label: 'Wood' },
            { key: 'costSteel', icon: 'resource_steel.jpg', label: 'Steel' },
            { key: 'costFood', icon: 'resource_food.jpg', label: 'Food' }
        ];
        costItems.forEach(function (item) {
            if (unit[item.key] > 0) {
                tooltipHtml += '<div class="unit-info-item">' +
                    '<img src="' + imgBase + '/icons/' + item.icon + '" class="unit-info-icon">' +
                    '<span class="unit-info-label">' + item.label + ':</span>' +
                    '<span class="unit-info-value">' + unit[item.key].toLocaleString() + '</span></div>';
            }
        });
        tooltipHtml += '</div></div>';

        const incomeItems = [
            { key: 'incomeCash', icon: 'resource_cash.jpg', label: 'Cash' },
            { key: 'incomeWood', icon: 'resource_wood.jpg', label: 'Wood' },
            { key: 'incomeSteel', icon: 'resource_steel.jpg', label: 'Steel' },
            { key: 'incomeFood', icon: 'resource_food.jpg', label: 'Food' }
        ];
        const hasIncome = incomeItems.some(function (i) { return unit[i.key] > 0; });
        if (hasIncome) {
            tooltipHtml += '<div class="unit-info-section"><div class="unit-info-section-title">Income (per hour)</div><div class="unit-info-grid">';
            incomeItems.forEach(function (item) {
                if (unit[item.key] > 0) {
                    tooltipHtml += '<div class="unit-info-item">' +
                        '<img src="' + imgBase + '/icons/' + item.icon + '" class="unit-info-icon">' +
                        '<span class="unit-info-label">' + item.label + ':</span>' +
                        '<span class="unit-info-value positive">+' + unit[item.key].toLocaleString() + '</span></div>';
                }
            });
            tooltipHtml += '</div></div>';
        }

        const upkeepItems = [
            { key: 'upkeepCash', icon: 'resource_cash.jpg', label: 'Cash' },
            { key: 'upkeepWood', icon: 'resource_wood.jpg', label: 'Wood' },
            { key: 'upkeepSteel', icon: 'resource_steel.jpg', label: 'Steel' },
            { key: 'upkeepFood', icon: 'resource_food.jpg', label: 'Food' }
        ];
        const hasUpkeep = upkeepItems.some(function (i) { return unit[i.key] > 0; });
        if (hasUpkeep) {
            tooltipHtml += '<div class="unit-info-section"><div class="unit-info-section-title">Upkeep (per hour)</div><div class="unit-info-grid">';
            upkeepItems.forEach(function (item) {
                if (unit[item.key] > 0) {
                    tooltipHtml += '<div class="unit-info-item">' +
                        '<img src="' + imgBase + '/icons/' + item.icon + '" class="unit-info-icon">' +
                        '<span class="unit-info-label">' + item.label + ':</span>' +
                        '<span class="unit-info-value negative">-' + unit[item.key].toLocaleString() + '</span></div>';
                }
            });
            tooltipHtml += '</div></div>';
        }

        const hours = Math.floor(unit.timestamp / 3600);
        const minutes = Math.floor((unit.timestamp % 3600) / 60);
        const seconds = unit.timestamp % 60;
        const timeStr = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        tooltipHtml += '<div class="unit-info-section"><div class="unit-info-grid">' +
            '<div class="unit-info-item"><span class="unit-info-label">Net Worth:</span>' +
            '<span class="unit-info-value">' + unit.netWorth.toLocaleString() + '</span></div>' +
            '<div class="unit-info-item"><img src="' + imgBase + '/icons/time.gif" class="unit-info-icon">' +
            '<span class="unit-info-label">Build Time:</span>' +
            '<span class="unit-info-value">' + timeStr + '</span></div></div></div>';

        unitInfoTooltip.innerHTML = tooltipHtml;
        unitInfoTooltip.style.display = 'block';
        updateUnitInfoTooltipPosition(event);
    }

    function hideUnitInfoTooltip() {
        unitInfoTooltip.style.display = 'none';
        currentTooltipEvent = null;
    }

    function updateUnitInfoTooltipPosition(event) {
        const e = event || currentTooltipEvent;
        if (!e) return;

        const tooltipWidth = unitInfoTooltip.offsetWidth;
        const tooltipHeight = unitInfoTooltip.offsetHeight;
        const padding = 15;

        let left = e.clientX + padding;
        let top = e.clientY + padding;

        if (left + tooltipWidth > window.innerWidth) left = e.clientX - tooltipWidth - padding;
        if (top + tooltipHeight > window.innerHeight) top = e.clientY - tooltipHeight - padding;

        unitInfoTooltip.style.left = left + 'px';
        unitInfoTooltip.style.top = top + 'px';
    }

    // ===== Build Data =====
    async function showBuildModal(region) {
        selectedBuildRegion = region;
        buildQuantities = {};
        document.getElementById('buildRegionCoords').textContent = region.x + ', ' + region.y;
        buildModal.style.display = 'block';

        const container = document.getElementById('buildUnitsContainer');
        container.innerHTML = '<div class="build-loading">Loading...</div>';

        const tabsContainer = document.getElementById('buildTabs');
        tabsContainer.innerHTML = '';

        try {
            const response = await fetch('/game/api/world/region/available-categories/' + region.id);
            const result = await response.json();
            availableCategories = result.success ? result.categories : gameUnitCategories;
        } catch (error) {
            console.error('Error loading available categories:', error);
            availableCategories = gameUnitCategories;
        }

        if (availableCategories.length === 0) {
            container.innerHTML = '<div class="build-loading">No build options available for this region.</div>';
            return;
        }

        selectedGameUnitCategoryId = availableCategories[0].id;

        availableCategories.forEach(function (type, index) {
            const tab = document.createElement('div');
            tab.className = 'build-tab' + (index === 0 ? ' active' : '');
            tab.textContent = type.name;
            tab.addEventListener('click', function () {
                selectedGameUnitCategoryId = type.id;
                document.querySelectorAll('#buildTabs .build-tab').forEach(function (t, i) {
                    t.classList.toggle('active', availableCategories[i].id === type.id);
                });
                loadBuildData(type.id);
            });
            tabsContainer.appendChild(tab);
        });

        loadBuildData(selectedGameUnitCategoryId);
    }

    async function loadBuildData(gameUnitCategoryId) {
        const container = document.getElementById('buildUnitsContainer');
        container.innerHTML = '<div class="build-loading">Loading units...</div>';

        try {
            const response = await fetch('/game/api/world/region/build-data/' + selectedBuildRegion.id + '/' + gameUnitCategoryId);
            const result = await response.json();

            if (result.success) {
                currentBuildData = result;
                renderBuildUnits(result);
            } else {
                container.innerHTML = '<div class="build-loading" style="color: #f44336;">' + result.message + '</div>';
            }
        } catch (error) {
            console.error('Error loading build data:', error);
            container.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load units</div>';
        }
    }

    function renderBuildUnits(data) {
        const container = document.getElementById('buildUnitsContainer');
        const spaceInfo = document.getElementById('buildSpaceInfo');
        const imgBase = WorldApp.imageBasePath;

        if (data.gameUnitCategory.id === 1) {
            spaceInfo.style.display = 'block';
            spaceInfo.innerHTML = 'You have <span style="color: #4CAF50;">' + data.spaceLeft + '</span> building space left on this region.';
        } else {
            spaceInfo.style.display = 'none';
        }

        if (data.units.length === 0) {
            container.innerHTML = '<div class="build-loading">No units available in this category</div>';
            return;
        }

        container.innerHTML = '';
        data.units.forEach(function (unit) {
            const card = document.createElement('div');
            card.className = 'build-unit-card';

            const constructionText = unit.inConstruction > 0 ? ' (' + unit.inConstruction + ')' : '';
            const hours = Math.floor(unit.timestamp / 3600);
            const minutes = Math.floor((unit.timestamp % 3600) / 60);
            const seconds = unit.timestamp % 60;
            const timeStr = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

            card.innerHTML =
                '<div class="build-unit-header ' + (!unit.canBuild ? 'unit-locked' : '') + '">' +
                    '<img src="' + imgBase + '/' + unit.imageDir + unit.image + '" alt="' + unit.name + '" class="build-unit-image ' + (!unit.canBuild ? 'grayscale' : '') + '">' +
                    '<div>' +
                        '<div class="build-unit-name">' + unit.name + (!unit.canBuild ? ' 🔒' : '') +
                            ' <span class="build-unit-info-icon" data-unit-id="' + unit.id + '">i</span></div>' +
                        (!unit.canBuild
                            ? '<div class="build-requirement">' + unit.buildRequirement + '</div>'
                            : '<div class="build-unit-owned">You have: ' + unit.owned + constructionText + '</div>') +
                    '</div>' +
                '</div>' +
                '<div class="build-unit-costs">' +
                    '<div class="build-unit-cost-item"><img src="' + imgBase + '/icons/resource_cash.jpg" class="build-unit-cost-icon"><span>' + unit.costCash.toLocaleString() + '</span></div>' +
                    '<div class="build-unit-cost-item"><img src="' + imgBase + '/icons/resource_wood.jpg" class="build-unit-cost-icon"><span>' + unit.costWood.toLocaleString() + '</span></div>' +
                    '<div class="build-unit-cost-item"><img src="' + imgBase + '/icons/resource_steel.jpg" class="build-unit-cost-icon"><span>' + unit.costSteel.toLocaleString() + '</span></div>' +
                    '<div class="build-unit-cost-item"><img src="' + imgBase + '/icons/time.gif" class="build-unit-cost-icon"><span>' + timeStr + '</span></div>' +
                '</div>' +
                '<div class="build-unit-input">' +
                    '<input type="number" min="0" value="0" data-unit-id="' + unit.id + '" class="build-quantity-input">' +
                '</div>';

            container.appendChild(card);

            const infoIcon = card.querySelector('.build-unit-info-icon');
            infoIcon.addEventListener('mouseenter', function (e) { e.stopPropagation(); showUnitInfoTooltip(e, unit); });
            infoIcon.addEventListener('mouseleave', function (e) { e.stopPropagation(); hideUnitInfoTooltip(); });
            infoIcon.addEventListener('mousemove', function (e) { e.stopPropagation(); updateUnitInfoTooltipPosition(e); });

            card.querySelector('input').disabled = !unit.canBuild;
        });

        document.querySelectorAll('.build-quantity-input').forEach(function (input) {
            input.addEventListener('input', function (e) {
                buildQuantities[parseInt(e.target.dataset.unitId)] = parseInt(e.target.value) || 0;
            });
        });
    }

    async function confirmBuild() {
        const hasSelection = Object.values(buildQuantities).some(function (qty) { return qty > 0; });
        if (!hasSelection) {
            showNotification('Please select at least one unit to build', 'error');
            return;
        }

        confirmBuildBtn.disabled = true;
        confirmBuildBtn.textContent = 'Building...';

        try {
            const response = await fetch('/game/api/world/region/build/' + selectedBuildRegion.id + '/' + selectedGameUnitCategoryId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ construct: buildQuantities })
            });

            const result = await response.json();

            if (result.success) {
                const amounts = document.querySelectorAll('.resource-amount');
                if (result.newCash !== undefined && amounts[0]) amounts[0].textContent = result.newCash.toLocaleString('en-US');
                if (result.newWood !== undefined && amounts[1]) amounts[1].textContent = result.newWood.toLocaleString('en-US');
                if (result.newSteel !== undefined && amounts[3]) amounts[3].textContent = result.newSteel.toLocaleString('en-US');

                showNotification(result.message, 'success');
                buildModal.style.display = 'none';
                selectedBuildRegion = null;
                buildQuantities = {};
            } else {
                showNotification(result.message || 'Failed to build units', 'error');
            }
        } catch (error) {
            console.error('Error building units:', error);
            showNotification('An error occurred while building. Please try again.', 'error');
        } finally {
            confirmBuildBtn.disabled = false;
            confirmBuildBtn.textContent = 'Build Selected';
        }
    }

    closeBuildModal.onclick = function () { buildModal.style.display = 'none'; selectedBuildRegion = null; };
    closeBuildBtn.onclick = function () { buildModal.style.display = 'none'; selectedBuildRegion = null; };
    confirmBuildBtn.onclick = confirmBuild;

    // Expose globally
    window.WorldBuild = {
        showBuildModal: showBuildModal,
        modal: buildModal
    };
})();

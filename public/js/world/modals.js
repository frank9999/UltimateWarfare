/**
 * Region modal handlers (buy, enemy, yours, headquarter) and UI controls.
 * Depends on: notifications.js, WorldApp config, WorldBuild, WorldAttack
 */
(function () {
    var imgBase = WorldApp.imageBasePath;

    // ===== Buy Region Modal =====
    var modal = document.getElementById('buyRegionModal');
    var closeModal = document.getElementById('closeModal');
    var cancelBtn = document.getElementById('cancelBtn');
    var confirmBuyBtn = document.getElementById('confirmBuyBtn');
    var selectedRegion = null;
    var isBuyingRegion = false;
    var currentRegionPrice = WorldApp.regionPrice;

    function showBuyRegionModal(region) {
        selectedRegion = region;
        var modalBody = document.getElementById('modalBody');
        var imageUrl = imgBase + '/map/' + region.image;

        modalBody.innerHTML =
            '<div class="region-info"><div class="region-image"><img src="' + imageUrl + '" alt="' + region.type + '"></div>' +
            '<div class="region-details">' +
            '<p><strong>Coordinates:</strong> ' + region.x + ', ' + region.y + '</p>' +
            '<p><strong>Type:</strong> ' + region.type + '</p>' +
            '<p style="margin-top: 15px;"><strong>Price:</strong> $' + currentRegionPrice.toLocaleString('en-US') + '</p>' +
            '<p style="margin-top: 10px;">Do you want to buy this region?</p>' +
            '<p style="color: #ffa500; font-size: 11px; margin-top: 5px;">Note: The price increases with each region you own.</p>' +
            '</div></div>';
        modal.style.display = 'block';
    }

    closeModal.onclick = function () { modal.style.display = 'none'; selectedRegion = null; };
    cancelBtn.onclick = function () { modal.style.display = 'none'; selectedRegion = null; };

    confirmBuyBtn.onclick = async function () {
        if (!selectedRegion || isBuyingRegion) return;
        isBuyingRegion = true;
        confirmBuyBtn.disabled = true;
        confirmBuyBtn.textContent = 'Buying...';

        try {
            var response = await fetch('/game/api/world/region/buy/' + selectedRegion.id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            var result = await response.json();

            if (result.success) {
                var worldMap = WorldApp.worldMap;
                var regionIndex = worldMap.sectors.findIndex(function (r) { return r.id === selectedRegion.id; });
                if (regionIndex !== -1) {
                    worldMap.sectors[regionIndex].hasOwner = true;
                    worldMap.sectors[regionIndex].isYours = true;
                    worldMap.sectors[regionIndex].ownerName = WorldApp.playerName;
                }

                if (result.newCash !== undefined) {
                    var cashElements = document.querySelectorAll('.resource-amount');
                    if (cashElements.length > 0) cashElements[0].textContent = result.newCash.toLocaleString('en-US');
                }

                if (result.newRegionPrice !== undefined) {
                    currentRegionPrice = result.newRegionPrice;
                }

                worldMap.setSectors(WorldApp.worldRegions).then(function () { worldMap.render(); });
                showNotification(result.message, 'success');
                modal.style.display = 'none';
                selectedRegion = null;
            } else {
                showNotification(result.message || 'Failed to buy region', 'error');
            }
        } catch (error) {
            console.error('Error buying region:', error);
            showNotification('An error occurred while buying the region. Please try again.', 'error');
        } finally {
            isBuyingRegion = false;
            confirmBuyBtn.disabled = false;
            confirmBuyBtn.textContent = 'Buy Region';
        }
    };

    // ===== Enemy Region Modal =====
    var enemyModal = document.getElementById('enemyRegionModal');
    var closeEnemyModal = document.getElementById('closeEnemyModal');
    var selectedEnemyRegion = null;

    function showEnemyRegionModal(region) {
        selectedEnemyRegion = region;
        var enemyModalBody = document.getElementById('enemyModalBody');
        var imageUrl = imgBase + '/map/' + region.image;

        enemyModalBody.innerHTML =
            '<div class="region-info"><div class="region-image"><img src="' + imageUrl + '" alt="' + region.type + '"></div>' +
            '<div class="region-details">' +
            '<p><strong>Coordinates:</strong> ' + region.x + ', ' + region.y + '</p>' +
            '<p><strong>Type:</strong> ' + region.type + '</p>' +
            '<p><strong>Owner:</strong> <span style="color: #ff6b6b;">' + region.ownerName + '</span></p>' +
            '</div></div>';
        enemyModal.style.display = 'block';
    }

    closeEnemyModal.onclick = function () { enemyModal.style.display = 'none'; selectedEnemyRegion = null; };
    document.getElementById('sendMessageBtn').onclick = function () {
        if (selectedEnemyRegion) window.location.href = '/game/message/new/' + encodeURIComponent(selectedEnemyRegion.ownerName);
    };
    document.getElementById('attackBtn').onclick = function () {
        if (selectedEnemyRegion) WorldAttack.startAttackFromSelection(selectedEnemyRegion);
    };
    document.getElementById('operationBtn').onclick = function () {
        if (selectedEnemyRegion) window.location.href = '/game/world/region/select-operation/' + selectedEnemyRegion.id;
    };

    // ===== Your Region Modal =====
    var yourModal = document.getElementById('yourRegionModal');
    var closeYourModal = document.getElementById('closeYourModal');
    var selectedYourRegion = null;

    function showYourRegionModal(region) {
        selectedYourRegion = region;
        var yourModalBody = document.getElementById('yourModalBody');
        var imageUrl = imgBase + '/map/' + region.image;

        yourModalBody.innerHTML =
            '<div class="region-info"><div class="region-image"><img src="' + imageUrl + '" alt="' + region.type + '"></div>' +
            '<div class="region-details">' +
            '<p><strong>Coordinates:</strong> ' + region.x + ', ' + region.y + '</p>' +
            '<p><strong>Type:</strong> ' + region.type + '</p>' +
            '<p><strong>Owner:</strong> <span style="color: #6bafff;">You</span></p>' +
            '</div></div>';
        yourModal.style.display = 'block';
    }

    closeYourModal.onclick = function () { yourModal.style.display = 'none'; selectedYourRegion = null; };
    document.getElementById('buildBtn').onclick = function () {
        if (selectedYourRegion) {
            yourModal.style.display = 'none';
            WorldBuild.showBuildModal(selectedYourRegion);
        }
    };
    document.getElementById('sendUnitsBtn').onclick = function () {
        if (selectedYourRegion) window.location.href = '/game/world/region/send-units/' + selectedYourRegion.id;
    };

    // ===== Headquarter Modal =====
    var hqModal = document.getElementById('headquarterModal');
    document.getElementById('closeHqModal').onclick = function () { hqModal.style.display = 'none'; };
    document.getElementById('headquarterBtn').addEventListener('click', function () { hqModal.style.display = 'block'; });
    document.getElementById('hqOldInterfaceBtn').onclick = function () { window.location.href = '/game/headquarter'; };
    document.getElementById('hqMarketBtn').onclick = function () { window.location.href = '/game/market'; };
    document.getElementById('hqFederationBtn').onclick = function () { window.location.href = '/game/federation'; };
    document.getElementById('hqResearchBtn').onclick = function () { window.location.href = '/game/research'; };
    document.getElementById('hqRegionsBtn').onclick = function () { window.location.href = '/game/region-list'; };
    document.getElementById('hqFleetsBtn').onclick = function () { window.location.href = '/game/fleets'; };
    document.getElementById('hqConstructionBtn').onclick = function () { window.location.href = '/game/construction'; };

    // ===== Close modals on outside click =====
    window.onclick = function (event) {
        if (event.target === modal) { modal.style.display = 'none'; selectedRegion = null; }
        if (event.target === enemyModal) { enemyModal.style.display = 'none'; selectedEnemyRegion = null; }
        if (event.target === yourModal) { yourModal.style.display = 'none'; selectedYourRegion = null; }
        if (event.target === hqModal) { hqModal.style.display = 'none'; }
        if (event.target === WorldBuild.modal) { WorldBuild.modal.style.display = 'none'; }
        if (event.target === WorldReports.modal) { WorldReports.modal.style.display = 'none'; }
    };

    // ===== Default tile click handler =====
    WorldApp.defaultTileClick = function (region) {
        if (!region.hasOwner) {
            showBuyRegionModal(region);
        } else if (region.isYours) {
            showYourRegionModal(region);
        } else {
            showEnemyRegionModal(region);
        }
    };
})();

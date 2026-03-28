/**
 * Region modal handlers (buy, enemy, yours, headquarter) and UI controls.
 * Depends on: notifications.js, WorldApp config, WorldBuild, WorldAttack
 */
(function () {
    const imgBase = WorldApp.imageBasePath;

    // ===== Buy Region Modal =====
    const modal = document.getElementById('buyRegionModal');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const confirmBuyBtn = document.getElementById('confirmBuyBtn');
    let selectedRegion = null;
    let isBuyingRegion = false;
    let currentRegionPrice = WorldApp.regionPrice;

    function showBuyRegionModal(region) {
        selectedRegion = region;
        const modalBody = document.getElementById('modalBody');
        const imageUrl = imgBase + '/map/' + region.image;

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
            const response = await fetch('/game/api/world/region/buy/' + selectedRegion.id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const result = await response.json();

            if (result.success) {
                const worldMap = WorldApp.worldMap;
                const regionIndex = worldMap.sectors.findIndex(function (r) { return r.id === selectedRegion.id; });
                if (regionIndex !== -1) {
                    worldMap.sectors[regionIndex].hasOwner = true;
                    worldMap.sectors[regionIndex].isYours = true;
                    worldMap.sectors[regionIndex].ownerName = WorldApp.playerName;
                }

                if (result.newCash !== undefined) {
                    const cashElements = document.querySelectorAll('.resource-amount');
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
    const enemyModal = document.getElementById('enemyRegionModal');
    const closeEnemyModal = document.getElementById('closeEnemyModal');
    let selectedEnemyRegion = null;

    function showEnemyRegionModal(region) {
        selectedEnemyRegion = region;
        const enemyModalBody = document.getElementById('enemyModalBody');
        const imageUrl = imgBase + '/map/' + region.image;

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

    // ===== Send Message Modal =====
    const sendMessageModal = document.getElementById('sendMessageModal');
    const messageSubject = document.getElementById('messageSubject');
    const messageBody = document.getElementById('messageBody');
    const confirmSendMessageBtn = document.getElementById('confirmSendMessageBtn');
    let isSendingMessage = false;

    let sendMessageToPlayer = null;

    function showSendMessageModal(playerName, subject) {
        sendMessageToPlayer = playerName;
        document.getElementById('sendMessageRecipient').textContent = playerName;
        messageSubject.value = subject || '';
        messageBody.value = '';
        confirmSendMessageBtn.disabled = false;
        confirmSendMessageBtn.textContent = 'Send Message';
        sendMessageModal.style.display = 'block';
    }

    // Allow messages.js to trigger reply
    if (window.WorldMessages) {
        window.WorldMessages.onReply = function (playerName, subject) {
            showSendMessageModal(playerName, subject);
        };
    }

    document.getElementById('closeSendMessageModal').onclick = function () { sendMessageModal.style.display = 'none'; };
    document.getElementById('cancelSendMessageBtn').onclick = function () { sendMessageModal.style.display = 'none'; };

    confirmSendMessageBtn.onclick = async function () {
        if (isSendingMessage) return;

        var subject = messageSubject.value.trim();
        var message = messageBody.value.trim();

        if (subject === '') {
            showNotification('Please type a subject', 'error');
            return;
        }
        if (message === '') {
            showNotification('Please type a message', 'error');
            return;
        }

        isSendingMessage = true;
        confirmSendMessageBtn.disabled = true;
        confirmSendMessageBtn.textContent = 'Sending...';

        try {
            var response = await fetch('/game/api/message/send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    toPlayerName: sendMessageToPlayer,
                    subject: subject,
                    message: message
                })
            });
            var result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                sendMessageModal.style.display = 'none';
                enemyModal.style.display = 'none';
            } else {
                showNotification(result.message || 'Failed to send message', 'error');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            showNotification('An error occurred while sending the message. Please try again.', 'error');
        } finally {
            isSendingMessage = false;
            confirmSendMessageBtn.disabled = false;
            confirmSendMessageBtn.textContent = 'Send Message';
        }
    };

    document.getElementById('sendMessageBtn').onclick = function () {
        if (selectedEnemyRegion) showSendMessageModal(selectedEnemyRegion.ownerName);
    };
    document.getElementById('attackBtn').onclick = function () {
        if (selectedEnemyRegion) WorldAttack.startAttackFromSelection(selectedEnemyRegion);
    };
    document.getElementById('operationBtn').onclick = function () {
        if (selectedEnemyRegion) WorldOperations.startOperation(selectedEnemyRegion);
    };

    // ===== Your Region Modal =====
    const yourModal = document.getElementById('yourRegionModal');
    const closeYourModal = document.getElementById('closeYourModal');
    let selectedYourRegion = null;

    function showYourRegionModal(region) {
        selectedYourRegion = region;
        const yourModalBody = document.getElementById('yourModalBody');
        const imageUrl = imgBase + '/map/' + region.image;

        yourModalBody.innerHTML =
            '<div class="region-info"><div class="region-image"><img src="' + imageUrl + '" alt="' + region.type + '"></div>' +
            '<div class="region-details">' +
            '<p><strong>Coordinates:</strong> ' + region.x + ', ' + region.y + '</p>' +
            '<p><strong>Type:</strong> ' + region.type + '</p>' +
            '<p><strong>Owner:</strong> <span style="color: #6bafff;">You</span></p>' +
            '</div></div>';
        yourModal.style.display = 'block';

        // Preload build data while user views region info
        WorldBuild.prefetchBuildData(region.id);
    }

    closeYourModal.onclick = function () { yourModal.style.display = 'none'; selectedYourRegion = null; };
    document.getElementById('buildBtn').onclick = function () {
        if (selectedYourRegion) {
            yourModal.style.display = 'none';
            WorldBuild.showBuildModal(selectedYourRegion);
        }
    };
    document.getElementById('sendUnitsBtn').onclick = function () {
        if (selectedYourRegion) {
            yourModal.style.display = 'none';
            WorldSendUnits.startSendUnits(selectedYourRegion);
        }
    };

    // ===== Headquarter Modal =====
    const hqModal = document.getElementById('headquarterModal');
    document.getElementById('closeHqModal').onclick = function () { hqModal.style.display = 'none'; };
    document.getElementById('hqOldInterfaceBtn').onclick = function () { window.location.href = '/game/headquarter'; };

    // ===== Navigation Hamburger Menu =====
    const navMenu = document.getElementById('navMenu');
    document.getElementById('navMenuBtn').addEventListener('click', function (e) {
        e.stopPropagation();
        navMenu.classList.toggle('show');
    });

    function closeNavMenu() { navMenu.classList.remove('show'); }

    document.getElementById('navHqBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); hqModal.style.display = 'block'; };
    document.getElementById('navReportsBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldReports.show(); };
    document.getElementById('navRegionsBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldRegionOverview.show(); };
    document.getElementById('navConstructionBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldConstruction.show(); };
    document.getElementById('navFleetsBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldFleetOverview.show(); };
    document.getElementById('navMarketBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldMarket.show(); };
    document.getElementById('navResearchBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldResearch.show(); };
    document.getElementById('navFederationBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldFederation.show(); };
    document.getElementById('navRankingsBtn').onclick = function (e) { e.preventDefault(); closeNavMenu(); WorldRankings.show(); };

    // ===== Close modals on outside click =====
    window.onclick = function (event) {
        if (event.target === modal) { modal.style.display = 'none'; selectedRegion = null; }
        if (event.target === enemyModal) { enemyModal.style.display = 'none'; selectedEnemyRegion = null; }
        if (event.target === yourModal) { yourModal.style.display = 'none'; selectedYourRegion = null; }
        if (event.target === hqModal) { hqModal.style.display = 'none'; }
        if (event.target === sendMessageModal) { sendMessageModal.style.display = 'none'; }
        if (event.target === WorldBuild.modal) { WorldBuild.modal.style.display = 'none'; }
        if (event.target === WorldReports.modal) { WorldReports.modal.style.display = 'none'; }
        if (event.target === WorldConstruction.modal) { WorldConstruction.modal.style.display = 'none'; }
        if (event.target === WorldFleetOverview.modal) { WorldFleetOverview.modal.style.display = 'none'; }
        if (event.target === WorldSendUnits.modal) { WorldSendUnits.modal.style.display = 'none'; }
        if (event.target === WorldMessages.modal) { WorldMessages.modal.style.display = 'none'; }
        if (event.target === WorldOperations.selectOperationModal) { WorldOperations.selectOperationModal.style.display = 'none'; }
        if (event.target === WorldOperations.operationUnitsModal) { WorldOperations.operationUnitsModal.style.display = 'none'; }
        if (event.target === WorldOperations.operationResultsModal) { WorldOperations.operationResultsModal.style.display = 'none'; }
        if (event.target === WorldRegionOverview.modal) { WorldRegionOverview.modal.style.display = 'none'; }
        if (event.target === WorldResearch.modal) { WorldResearch.modal.style.display = 'none'; }
        if (event.target === WorldMarket.modal) { WorldMarket.modal.style.display = 'none'; }
        if (event.target === WorldFederation.modal) { WorldFederation.modal.style.display = 'none'; }
        if (event.target === WorldRankings.modal) { WorldRankings.modal.style.display = 'none'; }

        // Close nav dropdown on outside click
        if (navMenu.classList.contains('show')) { closeNavMenu(); }
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

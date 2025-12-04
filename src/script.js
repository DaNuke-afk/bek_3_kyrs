const API_BASE = 'http://localhost:8888';
const USER_ID = 1;

let mySelection = [];
let marketSelection = [];
let steamSelection = [];
let currentBalance = 0;
let pendingTradeId = null;

const steamMockData = [
    { name: "AWP | Dragon Lore", type: "snipers", rarity: "ancient", wear: "FN", price: 4500.00, image: "assets/weapon_awp_cu_medieval_dragon.png" },
    { name: "M4A4 | Howl", type: "rifles", rarity: "immortal", wear: "MW", price: 3200.00, image: "assets/weapon_m4a1.png" },
    { name: "Karambit | Fade", type: "knives", rarity: "mythical", wear: "FN", price: 1800.00, image: "assets/weapon_knife_karambit.png" },
    { name: "AK-47 | Fire Serpent", type: "rifles", rarity: "ancient", wear: "FT", price: 950.00, image: "assets/weapon_ak47.png" },
    { name: "Glock-18 | Fade", type: "pistols", rarity: "legendary", wear: "FN", price: 1200.00, image: "assets/weapon_deagle_aa_flames.png" },
    { name: "Sport Gloves | Vice", type: "gloves", rarity: "legendary", wear: "MW", price: 2100.00, image: "assets/sporty_gloves_sporty_bluee.png" },
    { name: "Butterfly Knife | Doppler", type: "knives", rarity: "mythical", wear: "FN", price: 2400.00, image: "assets/weapon_knife_butterfl.png" },
    { name: "Desert Eagle | Blaze", type: "pistols", rarity: "rare", wear: "FN", price: 650.00, image: "assets/weapon_deagle_aa_flames.png" }
];

document.addEventListener('DOMContentLoaded', () => {
    loadAllData();
});

function switchPage(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.getElementById(pageId + '-page').classList.add('active');
    
    if (pageId === 'trade' || pageId === 'profile') {
        loadAllData();
    }
}

async function loadAllData() {
    await updateBalance();
    
    try {
        const res = await fetch(`${API_BASE}/items.php`);
        if (!res.ok) throw new Error('Network response was not ok');
        const items = await res.json();
        renderInventories(items);
        loadHistory();
    } catch (e) {
        console.error(e);
    }
}

async function updateBalance() {
    try {
        const res = await fetch(`${API_BASE}/users.php?action=get_balance&user_id=${USER_ID}`);
        if (!res.ok) return;
        
        const data = await res.json();
        if (data && typeof data.balance !== 'undefined') {
            currentBalance = parseFloat(data.balance);
            document.getElementById('balance-display').innerText = currentBalance.toFixed(2);
        }
    } catch (e) {
        console.error(e);
    }
}

function renderInventories(items) {
    const myContainer = document.getElementById('my-inventory');
    const marketContainer = document.getElementById('market-inventory');
    
    if (!myContainer || !marketContainer) return;

    myContainer.innerHTML = '';
    marketContainer.innerHTML = '';

    if (!Array.isArray(items)) return;

    items.forEach(item => {
        const card = createCard(item);
        if (item.owner_id == USER_ID) {
            card.onclick = () => toggleItem(card, item, mySelection);
            myContainer.appendChild(card);
        } else {
            card.onclick = () => toggleItem(card, item, marketSelection);
            marketContainer.appendChild(card);
        }
    });

    document.getElementById('my-count').innerText = myContainer.children.length + ' шт.';
    document.getElementById('market-count').innerText = marketContainer.children.length + ' шт.';
}

function createCard(item) {
    const div = document.createElement('div');
    const rarityClass = item.rarity ? `rarity-${item.rarity}` : 'rarity-common';
    div.className = `item-card ${rarityClass}`;
    
    const imgUrl = item.image_url ? item.image_url : './assets/default.png';

    div.innerHTML = `
        <div class="item-img">
            <img src="${imgUrl}" onerror="this.src='./assets/weapon_ak47.png'" alt="${item.name}">
        </div>
        <div class="item-details">
            <div class="item-name">${item.name}</div>
            <div class="item-price">$${parseFloat(item.price).toFixed(2)}</div>
            <div style="font-size: 11px; color: #888;">${item.wear}</div>
        </div>
    `;
    return div;
}

function toggleItem(el, item, arr) {
    const idx = arr.findIndex(i => i.id === item.id);
    if (idx > -1) {
        arr.splice(idx, 1);
        el.classList.remove('selected');
    } else {
        arr.push(item);
        el.classList.add('selected');
    }
}

function clearSelection() {
    mySelection = [];
    marketSelection = [];
    document.querySelectorAll('.item-card.selected').forEach(el => el.classList.remove('selected'));
}

function openSteamModal() {
    steamSelection = [];
    const container = document.getElementById('steam-grid');
    container.innerHTML = '';
    
    steamMockData.forEach((item, index) => {
        const div = document.createElement('div');
        div.className = `item-card rarity-${item.rarity}`;
        div.innerHTML = `
             <div class="item-img"><img src="${item.image}" alt="${item.name}"></div>
             <div class="item-details">
                 <div class="item-name">${item.name}</div>
                 <div class="item-price">$${item.price.toFixed(2)}</div>
             </div>
        `;
        div.onclick = () => {
            if(steamSelection.includes(index)) {
                steamSelection = steamSelection.filter(i => i !== index);
                div.classList.remove('selected');
            } else {
                steamSelection.push(index);
                div.classList.add('selected');
            }
        };
        container.appendChild(div);
    });
    
    document.getElementById('steam-modal').classList.add('active');
}

async function importSteamItems() {
    if(steamSelection.length === 0) return;
    
    const itemsToImport = steamSelection.map(i => steamMockData[i]);
    closeModal('steam-modal');
    
    for(let item of itemsToImport) {
        try {
            await fetch(`${API_BASE}/items.php`, {
                method: 'POST',
                body: JSON.stringify({
                    name: item.name,
                    type: item.type,
                    price: item.price,
                    rarity: item.rarity,
                    wear: item.wear,
                    image_url: item.image,
                    owner_id: USER_ID
                })
            });
        } catch(e) { console.error(e); }
    }
    
    showNotification('Предметы добавлены в инвентарь');
    setTimeout(loadAllData, 500);
}

function prepareTrade() {
    if (mySelection.length === 0 && marketSelection.length === 0) {
        return showNotification('Выберите предметы', 'error');
    }

    const myTotal = mySelection.reduce((sum, i) => sum + parseFloat(i.price), 0);
    const marketTotal = marketSelection.reduce((sum, i) => sum + parseFloat(i.price), 0);
    const diff = marketTotal - myTotal;

    document.getElementById('summary-give').innerText = '$' + myTotal.toFixed(2);
    document.getElementById('summary-get').innerText = '$' + marketTotal.toFixed(2);
    
    const diffEl = document.getElementById('summary-diff');
    const warnEl = document.getElementById('balance-warning');
    const confirmBtn = document.getElementById('btn-confirm-final');

    if (diff > 0) {
        diffEl.innerText = `Доплатить: $${diff.toFixed(2)}`;
        diffEl.className = 'summary-total diff-negative';
        
        if (currentBalance < diff) {
            warnEl.style.display = 'block';
            warnEl.innerText = `Не хватает $${(diff - currentBalance).toFixed(2)}`;
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
        } else {
            warnEl.style.display = 'none';
            confirmBtn.disabled = false;
            confirmBtn.style.opacity = '1';
        }
    } else {
        diffEl.innerText = `Получите: $${Math.abs(diff).toFixed(2)}`;
        diffEl.className = 'summary-total diff-positive';
        warnEl.style.display = 'none';
        confirmBtn.disabled = false;
        confirmBtn.style.opacity = '1';
    }

    document.getElementById('confirm-modal').classList.add('active');
}

async function finalConfirmTrade() {
    closeModal('confirm-modal');
    
    const payload = {
        user_id: USER_ID,
        my_items: mySelection.map(i => i.id),
        market_items: marketSelection.map(i => i.id)
    };

    try {
        const res = await fetch(`${API_BASE}/trades.php?action=create`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        
        if (data.status === 'success') {
            document.getElementById('success-modal').classList.add('active');
            clearSelection();
            loadAllData();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (e) {
        showNotification('Ошибка обмена', 'error');
    }
}

function openTopUpModal() {
    document.getElementById('topup-modal').classList.add('active');
}

async function processTopUp() {
    const amountInput = document.getElementById('topup-amount');
    const amount = amountInput.value;

    if (!amount || amount <= 0) return;

    try {
        const res = await fetch(`${API_BASE}/users.php?action=topup`, {
            method: 'POST',
            body: JSON.stringify({ user_id: USER_ID, amount: amount })
        });
        
        const data = await res.json();
        if (data.status === 'success') {
            closeModal('topup-modal');
            showNotification('Баланс пополнен');
            amountInput.value = '';
            updateBalance();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (e) {
        showNotification('Ошибка', 'error');
    }
}

async function loadHistory() {
    try {
        const res = await fetch(`${API_BASE}/trades.php?action=list`);
        if (!res.ok) return;
        const trades = await res.json();
        const tbody = document.getElementById('history-body');
        if (!tbody) return;
        tbody.innerHTML = '';

        trades.forEach(t => {
            const tr = document.createElement('tr');
            const amount = parseFloat(t.amount_change);
            const color = amount >= 0 ? '#2ecc71' : '#e74c3c';
            const sign = amount > 0 ? '+' : '';
            
            tr.innerHTML = `
                <td>${t.description}</td>
                <td style="color: ${color}; font-weight: bold;">${sign}${amount.toFixed(2)}$</td>
                <td style="color: #888; font-size: 12px;">${t.trade_date}</td>
                <td>
                    <button class="btn btn-outline btn-small" style="border-color: #e74c3c; color: #e74c3c;" onclick="revertTrade(${t.id})">Оспорить</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
    }
}

function revertTrade(id) {
    pendingTradeId = id;
    document.getElementById('revert-modal').classList.add('active');
}

async function confirmRevertAction() {
    closeModal('revert-modal');
    
    if (!pendingTradeId) return;

    try {
        const res = await fetch(`${API_BASE}/trades.php?action=revert`, {
            method: 'POST',
            body: JSON.stringify({ trade_id: pendingTradeId })
        });

        const data = await res.json();

        if (data.status === 'success') {
            showNotification('Сделка отменена, средства возвращены', 'success');
            loadAllData();
        } else {
            showNotification('Ошибка отмены', 'error');
        }
    } catch (e) {
        showNotification('Ошибка сети', 'error');
    }
    
    pendingTradeId = null;
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function showNotification(msg, type = 'success') {
    const container = document.getElementById('notification-container');
    const el = document.createElement('div');
    el.className = `notification ${type}`;
    
    let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    el.innerHTML = `
        <i class="fas ${icon}"></i>
        <div>${msg}</div>
    `;
    
    container.appendChild(el);

    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(100%)';
        el.style.transition = 'all 0.5s ease';
        setTimeout(() => el.remove(), 500);
    }, 3000);
}
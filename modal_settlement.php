<div id="settlementModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-[#0f172a] p-6 rounded-2xl w-full max-w-sm border border-slate-300 shadow-2xl">
        <h2 class="text-lg font-bold text-slate-900 mb-4"><i class="fa-solid fa-link text-emerald-500 mr-2"></i>Connect Account</h2>

        <form id="settlementForm" action="process_settlement.php" method="POST">
            <input type="hidden" name="type" id="hiddenType" value="paybill">
            
            <div class="flex gap-2 mb-4 p-1 bg-[#1e293b] rounded-lg">
                <button type="button" onclick="setMode('bank_account')" class="flex-1 py-1.5 rounded-md text-[10px] font-bold bg-slate-200 text-slate-900" id="btn-bank_account">Bank</button>
                <button type="button" onclick="setMode('paybill')" class="flex-1 py-1.5 rounded-md text-[10px] font-bold text-slate-500" id="btn-paybill">Paybill</button>
                <button type="button" onclick="setMode('till')" class="flex-1 py-1.5 rounded-md text-[10px] font-bold text-slate-500" id="btn-till">Till</button>
            </div>

            <input type="text" name="merchant_name" id="m_name" placeholder="Organisation Name" class="w-full bg-[#1e293b] border border-slate-300 p-3 rounded-lg mb-3 text-slate-900 text-sm" required>
            
            <div id="bankTrigger" class="mb-3">
                <div onclick="openBankList()" class="w-full bg-[#1e293b] border border-slate-300 p-3 rounded-lg text-slate-500 cursor-pointer flex justify-between items-center text-sm hover:border-emerald-500 transition">
                    <span class="truncate"><i class="fa-solid fa-magnifying-glass mr-2"></i> <span id="selectedBankText">Select Bank</span></span>
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </div>

            <input type="text" name="shortcode" id="m_code" placeholder="Shortcode" class="w-full bg-[#1e293b] border border-slate-300 p-3 rounded-lg mb-3 text-slate-900 text-sm" required>
            
            <div id="accNumberContainer">
                <input type="text" name="account_number" id="m_acc" placeholder="Account Number" class="w-full bg-[#1e293b] border border-slate-300 p-3 rounded-lg mb-3 text-slate-900 text-sm">
            </div>

            <div class="flex gap-2 mt-2">
                <button type="button" onclick="document.getElementById('settlementModal').classList.add('hidden')" class="w-1/3 py-3 rounded-lg bg-slate-100 text-slate-500 text-xs font-bold">Cancel</button>
                <button type="submit" class="w-2/3 py-3 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="bankListOverlay" class="fixed inset-0 bg-black/90 z-[60] hidden p-4">
    <div class="max-w-sm mx-auto bg-white rounded-2xl p-4 mt-6 border border-slate-300">
        <div class="relative mb-3">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-slate-500 text-xs"></i>
            <input type="text" id="bankSearch" onkeyup="filterBanks()" placeholder="Search..." class="w-full bg-slate-50 border border-slate-300 p-3 pl-9 rounded-lg text-slate-900 text-sm">
        </div>
        <div id="bankList" class="space-y-1 max-h-[300px] overflow-y-auto">
            </div>
        <button onclick="closeBankList()" class="mt-3 w-full py-3 bg-slate-100 rounded-lg text-slate-900 text-xs font-bold">Close</button>
    </div>
</div>

<script>
function setMode(mode) {
    // If 'bank_account', set DB type to 'paybill'
    let dbType = (mode === 'bank_account') ? 'paybill' : mode;
    document.getElementById('hiddenType').value = dbType;
    
    document.getElementById('bankTrigger').style.display = (mode === 'bank_account') ? 'block' : 'none';
    document.getElementById('accNumberContainer').style.display = (mode === 'till') ? 'none' : 'block';
    
    ['bank_account', 'paybill', 'till'].forEach(m => {
        let el = document.getElementById('btn-'+m);
        el.className = 'flex-1 py-2 rounded-lg text-xs font-bold transition ' + (mode === m ? 'bg-slate-200 text-slate-900' : 'text-slate-500');
    });
}
function openBankList() { document.getElementById('bankListOverlay').classList.remove('hidden'); }
function closeBankList() { document.getElementById('bankListOverlay').classList.add('hidden'); }
function selectBank(name, code) {
    document.getElementById('selectedBankText').innerText = name;
    document.getElementById('m_name').value = name;
    document.getElementById('m_code').value = code;
    closeBankList();
}
function filterBanks() {
    let input = document.getElementById('bankSearch').value.toLowerCase();
    document.querySelectorAll('.bank-item').forEach(item => {
        item.style.display = item.querySelector('.b-name').innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>
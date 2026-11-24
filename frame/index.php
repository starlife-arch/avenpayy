<?php 
// 1. Include the Header and Sidebar framework
include_once 'header.php'; 
?>

<div class="relative bg-gradient-to-r from-white via-teal-950/40 to-white p-6 md:p-8 rounded-2xl border border-slate-200/80 shadow-xl mb-8 overflow-hidden">
    <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 rounded-full blur-3xl pointer-events-none"></div>
    
    <h1 class="text-2xl md:text-3xl font-black tracking-wide text-slate-900 mb-2">
        Welcome to your Console Workspace
    </h1>
    <p class="text-sm md:text-base text-slate-500 max-w-xl">
        All systems are operational. Your database layer connection via <code class="bg-slate-50 px-1.5 py-0.5 rounded text-teal-600 border border-slate-200 font-mono text-xs">database_config/db.php</code> is primed and active.
    </p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    
    <div class="bg-white/40 p-6 rounded-2xl border border-slate-900 hover:border-teal-500/30 transition-all duration-300 shadow-xl flex flex-col justify-between group">
        <div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Payment Link</span>
                <span class="p-2 bg-teal-500/10 text-teal-600 rounded-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-credit-card"></i>
                </span>
            </div>
            <div class="text-3xl font-black text-slate-800 tracking-tight">M-Pesa API</div>
            <p class="text-xs text-slate-500 mt-1">Callbacks & STK streaming operational.</p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between text-xs text-teal-600 font-medium">
            <span>View Logs</span>
            <i class="fa-solid fa-arrow-right-long text-[10px]"></i>
        </div>
    </div>

    <div class="bg-white/40 p-6 rounded-2xl border border-slate-900 hover:border-teal-500/30 transition-all duration-300 shadow-xl flex flex-col justify-between group">
        <div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Queue Status</span>
                <span class="p-2 bg-teal-500/10 text-teal-600 rounded-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-print"></i>
                </span>
            </div>
            <div class="text-3xl font-black text-slate-800 tracking-tight">99.9% Up</div>
            <p class="text-xs text-slate-500 mt-1">Terminal automated streams online.</p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between text-xs text-teal-600 font-medium">
            <span>Terminals Group</span>
            <i class="fa-solid fa-arrow-right-long text-[10px]"></i>
        </div>
    </div>

    <div class="bg-white/40 p-6 rounded-2xl border border-slate-900 hover:border-emerald-500/30 transition-all duration-300 shadow-xl flex flex-col justify-between group sm:col-span-2 lg:col-span-1">
        <div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Database Sync</span>
                <span class="p-2 bg-emerald-500/10 text-emerald-600 rounded-xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-database"></i>
                </span>
            </div>
            <div class="text-3xl font-black text-slate-800 tracking-tight">Connected</div>
            <p class="text-xs text-slate-500 mt-1">Local and remote data mapping stable.</p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between text-xs text-emerald-600 font-medium">
            <span>Check Latency</span>
            <i class="fa-solid fa-arrow-right-long text-[10px]"></i>
        </div>
    </div>

</div>

<?php 
// 2. Include the Footer architecture and layout script toggles
include_once 'footer.php'; 
?>

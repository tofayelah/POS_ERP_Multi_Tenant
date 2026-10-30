<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KC ERP - Tenant Registration & Onboarding</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-xl w-full bg-slate-800/80 backdrop-blur border border-slate-700/60 rounded-2xl p-8 shadow-2xl shadow-sky-950/20">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/20 mb-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Start your Free Retail ERP Trial</h1>
            <p class="text-sm text-slate-400 mt-1">Multi-tenant retail management with Bangladesh NBR-compliant VAT & POS.</p>
        </div>

        <form id="regForm" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Store / Tenant Name</label>
                <input type="text" id="tenant_name" required placeholder="e.g. Fresh Supermarket" class="w-full px-4 py-2.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Subdomain</label>
                <div class="flex rounded-lg overflow-hidden border border-slate-700 focus-within:ring-2 focus-within:ring-sky-500">
                    <input type="text" id="subdomain" required placeholder="freshmart" class="flex-1 px-4 py-2.5 bg-slate-900/60 text-white placeholder-slate-500 focus:outline-none lowercase">
                    <span class="px-4 py-2.5 bg-slate-800 text-slate-400 text-sm border-l border-slate-700 select-none">.dev.kcerp.com</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Lowercase letters, numbers, and hyphens only.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Admin Full Name</label>
                    <input type="text" id="admin_name" required placeholder="Abdul Karim" class="w-full px-4 py-2.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Admin Email</label>
                    <input type="email" id="admin_email" required placeholder="admin@freshmart.com" class="w-full px-4 py-2.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Phone Number (For OTP Verification)</label>
                <input type="text" id="admin_phone" required placeholder="+8801712345678" class="w-full px-4 py-2.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Password</label>
                    <input type="password" id="admin_password" required minlength="8" placeholder="••••••••" class="w-full px-4 py-2.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Confirm Password</label>
                    <input type="password" id="admin_password_confirmation" required minlength="8" placeholder="••••••••" class="w-full px-4 py-2.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div id="errorBox" class="hidden p-3 rounded-lg bg-red-900/30 border border-red-500/40 text-red-200 text-sm"></div>
            <div id="successBox" class="hidden p-3 rounded-lg bg-emerald-900/30 border border-emerald-500/40 text-emerald-200 text-sm"></div>

            <button type="submit" id="submitBtn" class="w-full py-3 px-4 rounded-xl bg-sky-500 hover:bg-sky-400 active:bg-sky-600 text-white font-semibold shadow-lg shadow-sky-500/20 transition duration-150">
                Create Tenant & Launch Wizard
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-400">
            Already have an account? <a href="/admin" class="text-sky-400 hover:underline">Sign in to Tenant Admin</a>
        </div>
    </div>

    <script>
        document.getElementById('regForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const err = document.getElementById('errorBox');
            const succ = document.getElementById('successBox');
            err.classList.add('hidden');
            succ.classList.add('hidden');
            btn.disabled = true;
            btn.innerText = 'Creating tenant...';

            const payload = {
                tenant_name: document.getElementById('tenant_name').value,
                subdomain: document.getElementById('subdomain').value.toLowerCase().trim(),
                admin_name: document.getElementById('admin_name').value,
                admin_email: document.getElementById('admin_email').value,
                admin_phone: document.getElementById('admin_phone').value,
                admin_password: document.getElementById('admin_password').value,
                admin_password_confirmation: document.getElementById('admin_password_confirmation').value,
                plan_code: 'standard'
            };

            try {
                const res = await fetch('/api/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok) {
                    const msg = data.errors ? Object.values(data.errors).flat().join('<br>') : (data.message || 'Registration failed');
                    err.innerHTML = msg;
                    err.classList.remove('hidden');
                    btn.disabled = false;
                    btn.innerText = 'Create Tenant & Launch Wizard';
                    return;
                }

                succ.innerHTML = data.message + (data.raw_otp ? `<br><strong>Dev OTP:</strong> ${data.raw_otp}` : '');
                succ.classList.remove('hidden');
                btn.innerText = 'Registration Created!';
            } catch (ex) {
                err.innerText = 'Network error: ' + ex.message;
                err.classList.remove('hidden');
                btn.disabled = false;
                btn.innerText = 'Create Tenant & Launch Wizard';
            }
        });
    </script>
</body>
</html>

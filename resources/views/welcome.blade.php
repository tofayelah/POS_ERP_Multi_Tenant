<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KC ERP — Bangladesh Retail ERP & Multi-Tenant POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0b0f19;
            --bg-card: #111827;
            --bg-card-hover: #1f2937;
            --border-color: #374151;
            --border-accent: #0284c7;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --primary: #0284c7;
            --primary-hover: #0369a1;
            --accent: #f59e0b;
            --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            width: 100%;
            flex: 1;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-main);
        }
        .logo-box {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.25rem;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
        }
        .brand-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .brand-text p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .top-nav {
            display: flex;
            gap: 1rem;
        }
        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-outline:hover {
            border-color: var(--primary);
            background-color: rgba(2, 132, 199, 0.1);
            color: #38bdf8;
        }
        .hero {
            text-align: center;
            margin-bottom: 3rem;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.85rem;
            background-color: rgba(2, 132, 199, 0.15);
            border: 1px solid rgba(2, 132, 199, 0.35);
            color: #38bdf8;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .hero h2 {
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.15rem;
            color: var(--text-muted);
            max-width: 720px;
            margin: 0 auto;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .card:hover {
            border-color: var(--border-accent);
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4);
        }
        .card.featured {
            border-color: var(--primary);
            background: linear-gradient(180deg, rgba(2, 132, 199, 0.08) 0%, #111827 100%);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .card-icon.blue { background-color: rgba(2, 132, 199, 0.2); color: #38bdf8; }
        .card-icon.green { background-color: rgba(16, 185, 129, 0.2); color: #34d399; }
        .card-icon.amber { background-color: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .card-icon.purple { background-color: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .card-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem;
            flex-grow: 1;
        }
        .credentials-box {
            background-color: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
            padding: 0.75rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            color: #94a3b8;
            margin-bottom: 1.25rem;
        }
        .credentials-box div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.2rem;
        }
        .credentials-box div:last-child {
            margin-bottom: 0;
        }
        .credentials-box span.val {
            color: #f1f5f9;
            font-weight: 500;
        }
        .btn-primary {
            display: block;
            width: 100%;
            text-align: center;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.4);
            transform: translateY(-1px);
        }
        .btn-secondary {
            display: block;
            width: 100%;
            text-align: center;
            padding: 0.75rem 1rem;
            background-color: var(--bg-card-hover);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-secondary:hover {
            background-color: #374151;
            border-color: #4b5563;
        }
        .tenant-selector {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .tenant-selector h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tenant-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .tenant-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.9rem;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .tenant-chip:hover {
            border-color: var(--primary);
            background-color: rgba(2, 132, 199, 0.15);
            color: #38bdf8;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--success);
        }
        footer {
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="/" class="brand">
                <div class="logo-box">KC</div>
                <div class="brand-text">
                    <h1>KC Retail ERP</h1>
                    <p>Bangladesh Multi-Tenant Platform</p>
                </div>
            </a>
            <nav class="top-nav">
                <a href="/pos" class="btn-outline">🛒 POS Terminal</a>
                <a href="/register" class="btn-outline">✍️ Register Store</a>
                <a href="/admin/login" class="btn-outline" style="border-color: var(--primary); color: #38bdf8;">🔑 Tenant Login</a>
            </nav>
        </header>

        <section class="hero">
            <div class="badge">🚀 Production-Ready Architecture</div>
            <h2>Enterprise Retail ERP & Touch POS</h2>
            <p>Cloud-native, offline-first multi-tenant retail management with double-entry general ledger, automated stock costing, and statutory NBR Mushak 6.3 VAT compliance.</p>
        </section>

        <section class="grid">
            <!-- 1. Tenant Store ERP Panel -->
            <div class="card featured">
                <div>
                    <div class="card-header">
                        <div class="card-icon blue">🏬</div>
                        <div>
                            <div class="card-title">Tenant Store ERP</div>
                            <span style="font-size: 0.78rem; color: #38bdf8; font-weight: 600;">Filament Admin Panel</span>
                        </div>
                    </div>
                    <p class="card-desc">Complete store operations: branch management, inventory ledger, purchasing, double-entry GL, and Mushak 6.1/6.2/6.3/6.7/6.8 tax reports.</p>
                    <div class="credentials-box">
                        <div><span>URL:</span><span class="val">/admin/login</span></div>
                        <div><span>Email:</span><span class="val">admin@acme.com</span></div>
                        <div><span>Password:</span><span class="val">Password123!</span></div>
                    </div>
                </div>
                <a href="/admin/login" class="btn-primary">Login to Store ERP →</a>
            </div>

            <!-- 2. Offline-First POS Shell -->
            <div class="card">
                <div>
                    <div class="card-header">
                        <div class="card-icon green">🛒</div>
                        <div>
                            <div class="card-title">POS Touch Terminal</div>
                            <span style="font-size: 0.78rem; color: #34d399; font-weight: 600;">Alpine.js + Dexie PWA</span>
                        </div>
                    </div>
                    <p class="card-desc">High-speed cashier checkout terminal: barcode scanning, multi-tender payments, offline receipt caching, and automatic server batch sync.</p>
                    <div class="credentials-box">
                        <div><span>Mode:</span><span class="val">Touch Register PWA</span></div>
                        <div><span>Sync:</span><span class="val">Offline-First IndexedDB</span></div>
                        <div><span>Fiscal:</span><span class="val">SDC Bridge Integration</span></div>
                    </div>
                </div>
                <a href="/pos" class="btn-secondary">Launch POS Terminal →</a>
            </div>

            <!-- 3. Platform Control Plane -->
            <div class="card">
                <div>
                    <div class="card-header">
                        <div class="card-icon amber">⚙️</div>
                        <div>
                            <div class="card-title">Platform Control Plane</div>
                            <span style="font-size: 0.78rem; color: #fbbf24; font-weight: 600;">Super-Admin Console</span>
                        </div>
                    </div>
                    <p class="card-desc">Platform administration: multi-tenant cluster management, subscription plan definitions, billing, tenant lifecycle, and telemetry.</p>
                    <div class="credentials-box">
                        <div><span>URL:</span><span class="val">/platform/login</span></div>
                        <div><span>Email:</span><span class="val">superadmin@kcerp.com</span></div>
                        <div><span>Password:</span><span class="val">Password123!</span></div>
                    </div>
                </div>
                <a href="/platform/login" class="btn-secondary">Open Control Plane →</a>
            </div>

            <!-- 4. Merchant Onboarding & Self-Registration -->
            <div class="card">
                <div>
                    <div class="card-header">
                        <div class="card-icon purple">✨</div>
                        <div>
                            <div class="card-title">Merchant Registration</div>
                            <span style="font-size: 0.78rem; color: #a78bfa; font-weight: 600;">Self-Service Onboarding</span>
                        </div>
                    </div>
                    <p class="card-desc">Onboard a brand-new retail enterprise: automatic chart of accounts provisioning, default Bangladesh tax codes, warehouse setup, and trial subscription.</p>
                    <div class="credentials-box">
                        <div><span>Feature:</span><span class="val">Automated RLS Tenant</span></div>
                        <div><span>Seed:</span><span class="val">Default COA & Tax Codes</span></div>
                        <div><span>Access:</span><span class="val">Instant Store Access</span></div>
                    </div>
                </div>
                <a href="/register" class="btn-secondary">Register New Store →</a>
            </div>
        </section>

        <!-- Active Tenants Directory -->
        <div class="tenant-selector">
            <h3><span>🏬</span> Active Seeded Tenants Directory</h3>
            <div class="tenant-links">
                <a href="/admin/acme" class="tenant-chip">
                    <span class="dot"></span>
                    <span><strong>Acme Retail BD</strong> (/admin/acme)</span>
                </a>
                <a href="/admin/staging" class="tenant-chip">
                    <span class="dot"></span>
                    <span><strong>Staging Pilot Mart</strong> (/admin/staging)</span>
                </a>
                <a href="/admin/kazi-retail" class="tenant-chip">
                    <span class="dot"></span>
                    <span><strong>Kazi Retail Mart</strong> (/admin/kazi-retail)</span>
                </a>
                <a href="/admin/demo-mart" class="tenant-chip">
                    <span class="dot"></span>
                    <span><strong>Demo Mart</strong> (/admin/demo-mart)</span>
                </a>
            </div>
        </div>
    </div>

    <footer>
        <p>KC Retail ERP • Bangladesh Multi-Tenant Platform • Powered by Laravel 13, Filament 5 & PostgreSQL 17</p>
    </footer>
</body>
</html>

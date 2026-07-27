<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FUMS | DOH-EVCHD</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.1.0/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/doh_logo.jpg') }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .main-sidebar { background-color: #001f3f !important; }
        .nav-sidebar .nav-link.active { background-color: #17a2b8 !important; color: #fff !important; }
        .nav-header { font-size: 0.75rem; letter-spacing: 1px; padding: 0.5rem 1rem !important; color: #888 !important; }
        .content-wrapper { transition: margin-left .3s ease-in-out; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ url('/dashboard') }}" class="brand-link">
            <img src="{{ asset('images/doh_logo.jpg') }}" alt="DOH Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-bold">FUMS</span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
                <div class="image">
                    <i class="fas fa-user-circle fa-2x text-white-50 mt-1"></i>
                </div>
                <div class="info ml-2">
                    <a href="#" class="d-block text-white font-weight-bold mb-0" style="line-height: 1.2; font-size: 0.9rem;">
                        {{ Auth::user()->username ?? 'User Account' }}
                    </a>
                </div>
            </div>

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
                    
                    <li class="nav-header">MAIN NAVIGATION</li>
                    
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->is('dashboard*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('funds.index') }}" class="nav-link {{ request()->is('funds*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <p>Transactions</p>
                        </a>
                    </li>

                    <li class="nav-header">REPORTS & ANALYTICS</li>

                    <li class="nav-item {{ request()->is('reports*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('reports*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>Budget Tracking <i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('reports.by_source') }}" class="nav-link {{ request()->is('reports/budget-by-source*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon text-info"></i>
                                    <p>By Source</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.by_line_item') }}" class="nav-link {{ request()->routeIs('reports.by_line_item') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon text-primary"></i>
                                    <p>By Line Item</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.by_transactions') }}" class="nav-link {{ request()->routeIs('reports.by_transactions') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon text-success"></i>
                                    <p>By Transactions</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-header">SYSTEM MANAGEMENT</li>

                    <li class="nav-item {{ request()->is('settings*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('settings*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>Settings <i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            
                            {{-- Visible to Everyone: WFP --}}
                            <li class="nav-item">
                                <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.index') ? 'active' : '' }}">
                                    <i class="fas fa-file-signature nav-icon text-warning"></i>
                                    <p>WFP Configuration</p>
                                </a>
                            </li>

                            {{-- Visible to Everyone: Self-Service Password Update --}}
                            <li class="nav-item">
                                <a href="{{ route('settings.profile.password') }}" class="nav-link {{ request()->routeIs('settings.profile.password') ? 'active' : '' }}">
                                    <i class="fas fa-user-shield nav-icon text-info"></i>
                                    <p>User Profile</p>
                                </a>
                            </li>

                            {{-- Visible to Budget Section & Admin Only --}}
                            @can('budget-section')
                                <li class="nav-item">
                                    <a href="{{ route('settings.budget_line_items') }}" class="nav-link {{ request()->routeIs('settings.budget_line_items') ? 'active' : '' }}">
                                        <i class="fas fa-wallet nav-icon text-primary"></i>
                                        <p>Budget Line Items</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('settings.fund_sources') }}" class="nav-link {{ request()->routeIs('settings.fund_sources') ? 'active' : '' }}">
                                        <i class="fas fa-database nav-icon text-success"></i>
                                        <p>Fund Sources</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('settings.uacs_codes') }}" class="nav-link {{ request()->routeIs('settings.uacs_codes') ? 'active' : '' }}">
                                        <i class="fas fa-code nav-icon text-primary"></i>
                                        <p>UACS Codes</p>
                                    </a>
                                </li>
                            @endcan
                            
                            {{-- ONLY SHOW TO ADMIN: Account Management --}}
                            @can('admin-only')
                                <li class="nav-item">
                                    <a href="{{ route('settings.accounts') }}" class="nav-link {{ request()->routeIs('settings.accounts') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-users-cog text-info"></i>
                                        <p>Account Management</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>

                    <li class="nav-item mt-3">
                        <a href="#" class="nav-link" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                            <p>Sign out</p>
                        </a>
                        <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display: none;">
                            @csrf
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">@yield('header')</div>
        </div>
        <section class="content">
            <div class="container-fluid">@yield('content')</div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

@yield('js')
</body>
</html>
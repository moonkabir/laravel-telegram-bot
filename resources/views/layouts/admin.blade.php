<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <!-- CSRF Token (dummy) -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap 5 + AdminLTE 3 + Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Tailwind (utility only, used sparingly) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- additional font-awesome (already included) -->
    <style>
        /* subtle modern touches */
        .content-wrapper {
            background: #f4f6f9;
        }
        .main-sidebar .brand-link {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .card-modern {
            border-radius: 1.25rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.02);
            transition: transform 0.15s ease, box-shadow 0.2s ease;
            border: none;
        }
        .card-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 30px -10px rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }
        .bg-soft-primary { background: #e0edff; color: #1a56db; }
        .bg-soft-success { background: #d1fae5; color: #0b7e4b; }
        .bg-soft-warning { background: #fef3c7; color: #b45309; }
        .bg-soft-purple { background: #ede9fe; color: #6d28d9; }
        .table-policy td, .table-policy th {
            vertical-align: middle;
            border-top: 1px solid #e9ecef;
        }
        .badge-policy {
            font-weight: 500;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            letter-spacing: 0.01em;
        }
        .nav-link.active-policy {
            background: rgba(255,255,255,0.12) !important;
            border-radius: 0.5rem;
        }
        .avatar-demo {
            width: 38px;
            height: 38px;
            background: linear-gradient(145deg, #3b82f6, #2563eb);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        .main-header {
            border-bottom: 1px solid rgba(0,0,0,0.03);
            background: #ffffff;
        }
        .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link.active {
            background: rgba(255,255,255,0.15);
        }
        /* demo gradient for brand */
        .brand-text {
            font-weight: 500;
            letter-spacing: -0.01em;
        }
        .btn-outline-search {
            border-radius: 40px;
            border: 1px solid #dee2e6;
            color: #4b5563;
            background: white;
            padding: 0.4rem 1.2rem;
        }
        .btn-outline-search:hover {
            background: #f8fafc;
            border-color: #b9c1ca;
        }
        .nav-link.active {
            background: rgba(255,255,255,0.08);
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- ====== MODERN NAVBAR ====== -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light px-3">
        <!-- Left: brand / toggle (optional) -->
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none me-2" type="button" data-widget="pushmenu" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <span class="d-none d-md-inline text-secondary fw-light me-2">
                <i class="fas fa-shield-alt text-primary me-1"></i> {{ config('app.name') }}
            </span>
        </div>

        <!-- Right: search bar + user -->
        <ul class="navbar-nav ms-auto align-items-center gap-2 flex-row">
            <!-- user avatar + logout -->
            <li class="nav-item dropdown">
                <a class="nav-link d-flex align-items-center" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                    <div class="avatar-demo me-2">{{ auth()->user()->name }}</div>
                    <span class="d-none d-sm-inline fw-semibold small me-1">{{ auth()->user()->email }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="border-radius: 16px;">
                    <a class="dropdown-item rounded-3 py-2" href="#"><i class="fas fa-user-circle me-2"></i>Profile</a>
                    <a class="dropdown-item rounded-3 py-2" href="#"><i class="fas fa-cog me-2"></i>Settings</a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="dropdown-item rounded-3 py-2 text-danger" style="background: transparent; border: none; width: 100%; text-align: left;">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    <!-- ====== SIDEBAR with modern icons ====== -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4" style="background: #1a2639;">
        <a href="{{ route('dashboard.index') }}" class="brand-link d-flex align-items-center">
            <span class="brand-text font-weight-light fs-5 ps-1">
                <i class="fas fa-scale-balanced me-2" style="color: #8ab4f8;"></i> {{ config('app.name') }}
            </span>
        </a>
        <div class="sidebar pt-3">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.index') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-book-open me-2" style="color: #8ab4f8;"></i>
                            <p>Knowledge Base</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-clock me-2" style="color: #a5b4cb;"></i>
                            <p>Pending Review</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('documents.create') }}" class="nav-link {{ request()->routeIs('documents.create') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-circle-plus me-2" style="color: #7c8db0;"></i>
                            <p>New policy</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                @include('layouts.partials.flash-messages')
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>
</div>

<!-- Scripts: Bootstrap bundle + AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- optional: any extra script -->
<script>
    // demo: auto-hide alert after 5s
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            }, 5000);
        });
    });
</script>
@yield('scripts')
</body>
</html>
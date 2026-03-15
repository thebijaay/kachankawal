{{-- ─────────────────────────────────────────────
     resources/views/layouts/app.blade.php
     Master layout for admin panel
──────────────────────────────────────────── --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') – Kachankawal Gaunpalika</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --sidebar-w: 260px; --primary: #006B3C; --primary-dark: #004e2c; }
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .sidebar {
            width: var(--sidebar-w); position: fixed; top: 0; left: 0; height: 100vh;
            background: var(--primary); overflow-y: auto; z-index: 1000;
        }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.15); }
        .sidebar-brand h5 { color: #fff; margin: 0; font-size: .95rem; }
        .sidebar-brand small { color: rgba(255,255,255,.65); font-size: .75rem; }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8); padding: .65rem 1.25rem;
            display: flex; align-items: center; gap: .6rem; font-size: .875rem; border-radius: 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.12); color: #fff; }
        .sidebar .nav-link i { font-size: 1.1rem; }
        .sidebar .nav-section { color: rgba(255,255,255,.4); font-size: .7rem; text-transform: uppercase;
            letter-spacing: .08em; padding: 1rem 1.25rem .4rem; }
        .main-content { margin-left: var(--sidebar-w); min-height: 100vh; }
        .topbar {
            background: #fff; border-bottom: 1px solid #e5e9f0;
            padding: .75rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
        }
        .stat-card { background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .stat-card .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; font-size: 1.4rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; }
        .stat-card .stat-label { color: #6c757d; font-size: .8rem; }
        .badge-status { font-size: .72rem; padding: .3em .6em; border-radius: 20px; font-weight: 600; }
        .table-action-btn { padding: .2rem .5rem; font-size: .8rem; }
        .sidebar-toggle { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .sidebar-toggle { display: block; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- Sidebar --}}
<nav class="sidebar">
    <div class="sidebar-brand">
        <h5><i class="bi bi-building me-2"></i>Kachankawal</h5>
        <small>Gaunpalika Admin</small>
    </div>
    <ul class="nav flex-column mt-1">
        <li class="nav-section">Main</li>
        <li class="nav-item">
            <a class="nav-link @active('admin.dashboard')" href="{{ route('admin.dashboard') }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-section">Content</li>
        <li class="nav-item">
            <a class="nav-link @active('admin.notices.*')" href="{{ route('admin.notices.index') }}">
                <i class="bi bi-megaphone"></i> Notices
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @active('admin.events.*')" href="{{ route('admin.events.index') }}">
                <i class="bi bi-calendar-event"></i> Events
            </a>
        </li>
        <li class="nav-section">Services</li>
        <li class="nav-item">
            <a class="nav-link @active('admin.services.*')" href="{{ route('admin.services.index') }}">
                <i class="bi bi-file-earmark-text"></i> Service Requests
                @if($pendingServices ?? 0)
                <span class="badge bg-warning text-dark ms-auto">{{ $pendingServices }}</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @active('admin.complaints.*')" href="{{ route('admin.complaints.index') }}">
                <i class="bi bi-exclamation-triangle"></i> Complaints
                @if($openComplaints ?? 0)
                <span class="badge bg-danger ms-auto">{{ $openComplaints }}</span>
                @endif
            </a>
        </li>
        <li class="nav-section">Municipality</li>
        <li class="nav-item">
            <a class="nav-link @active('admin.representatives.*')" href="{{ route('admin.representatives.index') }}">
                <i class="bi bi-person-badge"></i> Representatives
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @active('admin.wards.*')" href="{{ route('admin.wards.index') }}">
                <i class="bi bi-geo-alt"></i> Wards
            </a>
        </li>
        <li class="nav-section">Users</li>
        <li class="nav-item">
            <a class="nav-link @active('admin.users.*')" href="{{ route('admin.users.index') }}">
                <i class="bi bi-people"></i> Citizens
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @active('admin.notifications.*')" href="{{ route('admin.notifications.index') }}">
                <i class="bi bi-bell"></i> Push Notifications
            </a>
        </li>
        <li class="nav-section">Reports</li>
        <li class="nav-item">
            <a class="nav-link @active('admin.reports.*')" href="{{ route('admin.reports.index') }}">
                <i class="bi bi-bar-chart"></i> Reports
            </a>
        </li>
    </ul>
    <div class="p-3 mt-auto border-top border-white border-opacity-25">
        <small class="text-white-50">{{ auth()->user()->name }}</small><br>
        <small class="text-white-50">{{ auth()->user()->role }}</small>
        <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
            @csrf
            <button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
        </form>
    </div>
</nav>

{{-- Main --}}
<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary sidebar-toggle"><i class="bi bi-list"></i></button>
            <h6 class="mb-0 text-muted fw-normal">@yield('page-title', 'Dashboard')</h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">{{ now()->format('D, d M Y') }}</span>
        </div>
    </div>

    <div class="p-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @yield('content')
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
document.querySelector('.sidebar-toggle')?.addEventListener('click', () =>
    document.querySelector('.sidebar').classList.toggle('open'));
</script>
@stack('scripts')
</body>
</html>


{{-- ─────────────────────────────────────────────
     resources/views/admin/dashboard.blade.php
──────────────────────────────────────────── --}}
@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')
{{-- Stat cards --}}
<div class="row g-3 mb-4">
    @php
    $stats = [
        ['label'=>'Registered Citizens','value'=>$stats['users'],'icon'=>'bi-people','color'=>'#006B3C','bg'=>'rgba(0,107,60,.1)'],
        ['label'=>'Pending Services',   'value'=>$stats['services_pending'],'icon'=>'bi-file-earmark-clock','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)'],
        ['label'=>'Open Complaints',    'value'=>$stats['complaints_open'],'icon'=>'bi-exclamation-circle','color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
        ['label'=>'Active Notices',     'value'=>$stats['notices_active'],'icon'=>'bi-megaphone','color'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)'],
        ['label'=>'Upcoming Events',    'value'=>$stats['events_upcoming'],'icon'=>'bi-calendar-check','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.1)'],
        ['label'=>'Today\'s Requests',  'value'=>$stats['services_today'],'icon'=>'bi-arrow-up-circle','color'=>'#06b6d4','bg'=>'rgba(6,182,212,.1)'],
    ];
    @endphp
    @foreach($stats as $s)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon mb-2" style="background:{{ $s['bg'] }}; color:{{ $s['color'] }}">
                <i class="bi {{ $s['icon'] }}"></i>
            </div>
            <div class="stat-value" style="color:{{ $s['color'] }}">{{ number_format($s['value']) }}</div>
            <div class="stat-label">{{ $s['label'] }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Charts row --}}
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="stat-card">
            <h6 class="mb-3">Service Requests & Complaints (Last 7 Days)</h6>
            <canvas id="trendChart" height="100"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <h6 class="mb-3">Service Type Breakdown</h6>
            <canvas id="serviceTypeChart" height="220"></canvas>
        </div>
    </div>
</div>

{{-- Ward activity + Recent --}}
<div class="row g-3">
    <div class="col-md-7">
        <div class="stat-card">
            <div class="d-flex justify-content-between mb-3">
                <h6 class="mb-0">Ward Activity</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light"><tr>
                        <th>Ward</th><th>Citizens</th><th>Pending Services</th><th>Open Complaints</th>
                    </tr></thead>
                    <tbody>
                    @foreach($wardActivity as $w)
                    <tr>
                        <td><a href="{{ route('admin.wards.show', $w['ward_no']) }}">Ward {{ $w['ward_no'] }}: {{ $w['ward_name'] }}</a></td>
                        <td>{{ $w['citizens'] }}</td>
                        <td><span class="badge bg-warning text-dark">{{ $w['services_pending'] }}</span></td>
                        <td><span class="badge bg-danger">{{ $w['complaints_open'] }}</span></td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="stat-card">
            <h6 class="mb-3">Recent Complaints</h6>
            <ul class="list-unstyled mb-0">
            @foreach($recentComplaints as $c)
            <li class="border-bottom py-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-600">{{ $c->title }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $c->tracking_no }} · Ward {{ $c->ward_no }}</div>
                    </div>
                    <span class="badge-status bg-{{ $c->status === 'submitted' ? 'secondary' : ($c->status === 'in_progress' ? 'primary' : 'success') }} bg-opacity-10 text-{{ $c->status === 'submitted' ? 'secondary' : ($c->status === 'in_progress' ? 'primary' : 'success') }}">
                        {{ str_replace('_',' ',strtoupper($c->status)) }}
                    </span>
                </div>
            </li>
            @endforeach
            </ul>
            <a href="{{ route('admin.complaints.index') }}" class="btn btn-sm btn-outline-primary mt-3 w-100">View All</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const chartData  = @json($chartData);
const trendCtx   = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: chartData.servicesChart.map(d => d.date),
        datasets: [
            { label: 'Service Requests', data: chartData.servicesChart.map(d => d.total),
              borderColor: '#006B3C', backgroundColor: 'rgba(0,107,60,.08)', tension: .4, fill: true },
            { label: 'Complaints', data: chartData.complaintsChart.map(d => d.total),
              borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.08)', tension: .4, fill: true },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});

const typeCtx = document.getElementById('serviceTypeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: chartData.serviceTypes.map(d => d.service_type.replace(/_/g,' ')),
        datasets: [{ data: chartData.serviceTypes.map(d => d.total),
          backgroundColor: ['#006B3C','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>
@endpush


{{-- ─────────────────────────────────────────────
     resources/views/admin/services/index.blade.php
──────────────────────────────────────────── --}}
@extends('layouts.app')
@section('title', 'Service Requests')
@section('page-title', 'Service Request Management')

@section('content')
{{-- Filters --}}
<div class="stat-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-3">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                @foreach(['pending','processing','approved','rejected','completed'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Service Type</label>
            <select name="service_type" class="form-select form-select-sm">
                <option value="">All Types</option>
                @foreach(['birth_registration','death_registration','marriage_registration','migration_certificate','recommendation_letter','business_registration'] as $t)
                <option value="{{ $t }}" @selected(request('service_type')===$t)>{{ str_replace('_',' ',ucfirst($t)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2">
            <label class="form-label small">Ward</label>
            <select name="ward_no" class="form-select form-select-sm">
                <option value="">All Wards</option>
                @foreach(range(1,7) as $w)
                <option value="{{ $w }}" @selected(request('ward_no')==$w)>Ward {{ $w }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2">
            <button class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <div class="col-sm-2">
            <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
        </div>
    </form>
</div>

<div class="stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tracking No.</th><th>Citizen</th><th>Service</th><th>Ward</th><th>Status</th><th>Submitted</th><th>Action</th></tr>
            </thead>
            <tbody>
            @forelse($requests as $req)
            <tr>
                <td><code>{{ $req->tracking_no }}</code></td>
                <td>
                    <div class="fw-600 small">{{ $req->user->name }}</div>
                    <div class="text-muted" style="font-size:.75rem">{{ $req->user->phone }}</div>
                </td>
                <td><span class="badge bg-light text-dark">{{ str_replace('_',' ',ucfirst($req->service_type)) }}</span></td>
                <td>Ward {{ $req->ward_no }}</td>
                <td>
                    @php $sc = ['pending'=>'warning','processing'=>'primary','approved'=>'success','rejected'=>'danger','completed'=>'success']; @endphp
                    <span class="badge-status bg-{{ $sc[$req->status] ?? 'secondary' }} bg-opacity-10 text-{{ $sc[$req->status] ?? 'secondary' }}">
                        {{ strtoupper($req->status) }}
                    </span>
                </td>
                <td class="small text-muted">{{ $req->created_at->diffForHumans() }}</td>
                <td>
                    <a href="{{ route('admin.services.show', $req->id) }}" class="btn btn-sm btn-outline-primary table-action-btn"><i class="bi bi-eye"></i></a>
                    <button class="btn btn-sm btn-outline-success table-action-btn" onclick="updateStatus({{ $req->id }}, '{{ $req->status }}')"><i class="bi bi-pencil"></i></button>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No service requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $requests->withQueryString()->links() }}</div>
</div>

{{-- Status update modal --}}
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="statusForm">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Update Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="statusSelect" class="form-select">
                            @foreach(['pending','processing','approved','rejected','completed'] as $s)
                            <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks (optional)</label>
                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateStatus(id, currentStatus) {
    document.getElementById('statusForm').action = `/admin/services/${id}/status`;
    document.getElementById('statusSelect').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}
</script>
@endpush


{{-- ─────────────────────────────────────────────
     resources/views/admin/complaints/index.blade.php
──────────────────────────────────────────── --}}
@extends('layouts.app')
@section('title', 'Complaints')
@section('page-title', 'Complaint Management')

@section('content')
<div class="stat-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-2">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach(['submitted','under_review','in_progress','resolved','closed'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2">
            <label class="form-label small">Category</label>
            <select name="category" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach(['road','water_supply','electricity','sanitation','public_service','corruption','environment','other'] as $c)
                <option value="{{ $c }}" @selected(request('category')===$c)>{{ ucwords(str_replace('_',' ',$c)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2">
            <label class="form-label small">Ward</label>
            <select name="ward_no" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach(range(1,7) as $w)<option value="{{ $w }}" @selected(request('ward_no')==$w)>Ward {{ $w }}</option>@endforeach
            </select>
        </div>
        <div class="col-sm-2"><button class="btn btn-primary btn-sm w-100 mt-3">Filter</button></div>
        <div class="col-sm-2"><a href="{{ route('admin.complaints.index') }}" class="btn btn-outline-secondary btn-sm w-100 mt-3">Clear</a></div>
    </form>
</div>

<div class="stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tracking</th><th>Citizen</th><th>Title</th><th>Category</th><th>Ward</th><th>Status</th><th>Reported</th><th>Action</th></tr>
            </thead>
            <tbody>
            @forelse($complaints as $c)
            @php
            $sc = ['submitted'=>'secondary','under_review'=>'info','in_progress'=>'primary','resolved'=>'success','closed'=>'dark'];
            @endphp
            <tr>
                <td><code>{{ $c->tracking_no }}</code></td>
                <td class="small">{{ $c->user->name }}<br><span class="text-muted">{{ $c->user->phone }}</span></td>
                <td class="small">
                    {{ Str::limit($c->title,40) }}
                    @if($c->photos->count()) <i class="bi bi-images text-muted ms-1" title="{{ $c->photos->count() }} photos"></i> @endif
                    @if($c->latitude) <i class="bi bi-geo-alt text-success ms-1" title="Has GPS"></i> @endif
                </td>
                <td><span class="badge bg-light text-dark small">{{ ucwords(str_replace('_',' ',$c->category)) }}</span></td>
                <td>Ward {{ $c->ward_no }}</td>
                <td>
                    <span class="badge-status bg-{{ $sc[$c->status]??'secondary' }} bg-opacity-10 text-{{ $sc[$c->status]??'secondary' }}">
                        {{ strtoupper(str_replace('_',' ',$c->status)) }}
                    </span>
                </td>
                <td class="small text-muted">{{ $c->created_at->diffForHumans() }}</td>
                <td>
                    <a href="{{ route('admin.complaints.show', $c->id) }}" class="btn btn-sm btn-outline-primary table-action-btn"><i class="bi bi-eye"></i></a>
                    <button class="btn btn-sm btn-outline-success table-action-btn" onclick="updateComplaint({{ $c->id }}, '{{ $c->status }}')"><i class="bi bi-pencil"></i></button>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No complaints found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $complaints->withQueryString()->links() }}</div>
</div>

<div class="modal fade" id="complaintModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="complaintForm">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Update Complaint</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="complaintStatus" class="form-select">
                            @foreach(['submitted','under_review','in_progress','resolved','closed'] as $s)
                            <option value="{{ $s }}">{{ ucwords(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Resolution Note</label>
                        <textarea name="resolution_note" class="form-control" rows="3" placeholder="Describe the action taken..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateComplaint(id, currentStatus) {
    document.getElementById('complaintForm').action = `/admin/complaints/${id}/status`;
    document.getElementById('complaintStatus').value = currentStatus;
    new bootstrap.Modal(document.getElementById('complaintModal')).show();
}
</script>
@endpush

<?php
// ─────────────────────────────────────────────
//  routes/web.php  –  Admin Panel Web Routes
// ─────────────────────────────────────────────

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
    AdminAuthController,
    AdminDashboardController,
    AdminServiceController,
    AdminComplaintController,
    AdminNoticeController,
    AdminEventController,
    AdminUserController,
    AdminWardController,
    AdminRepresentativeController,
    AdminNotificationWebController,
    AdminReportController,
};
use App\Http\Middleware\AdminWebMiddleware;

Route::prefix('admin')->name('admin.')->group(function () {

    // Login
    Route::get('login',  [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login']);

    Route::middleware(AdminWebMiddleware::class)->group(function () {

        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/',          [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard',  [AdminDashboardController::class, 'index'])->name('dashboard');

        // Service requests
        Route::prefix('services')->name('services.')->group(function () {
            Route::get('/',            [AdminServiceController::class, 'index'])->name('index');
            Route::get('/{id}',        [AdminServiceController::class, 'show'])->name('show');
            Route::put('/{id}/status', [AdminServiceController::class, 'updateStatus'])->name('update-status');
            Route::put('/{id}/assign', [AdminServiceController::class, 'assign'])->name('assign');
        });

        // Complaints
        Route::prefix('complaints')->name('complaints.')->group(function () {
            Route::get('/',            [AdminComplaintController::class, 'index'])->name('index');
            Route::get('/{id}',        [AdminComplaintController::class, 'show'])->name('show');
            Route::put('/{id}/status', [AdminComplaintController::class, 'updateStatus'])->name('update-status');
            Route::put('/{id}/assign', [AdminComplaintController::class, 'assign'])->name('assign');
        });

        // Notices
        Route::resource('notices', AdminNoticeController::class);

        // Events
        Route::resource('events', AdminEventController::class);

        // Representatives
        Route::resource('representatives', AdminRepresentativeController::class);

        // Wards
        Route::get('wards',          [AdminWardController::class, 'index'])->name('wards.index');
        Route::get('wards/{ward_no}',[AdminWardController::class, 'show'])->name('wards.show');
        Route::put('wards/{ward_no}',[AdminWardController::class, 'update'])->name('wards.update');

        // Users
        Route::get('users',          [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{id}',     [AdminUserController::class, 'show'])->name('users.show');
        Route::put('users/{id}/status',[AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');

        // Push notifications
        Route::get('notifications',        [AdminNotificationWebController::class, 'index'])->name('notifications.index');
        Route::post('notifications/send',  [AdminNotificationWebController::class, 'send'])->name('notifications.send');

        // Reports
        Route::get('reports',             [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export',      [AdminReportController::class, 'export'])->name('reports.export');
    });
});

// ─────────────────────────────────────────────
//  Admin Panel Controllers (Web)
// ─────────────────────────────────────────────
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User,ServiceRequest,Complaint,Notice,Ward,Representative,MunicipalEvent,Feedback};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Http,Storage};

class AdminWebMiddleware {
    public function handle($request, \Closure $next) {
        if (!Auth::check() || !in_array(Auth::user()->role, ['municipality_admin','ward_admin'])) {
            return redirect()->route('admin.login')->with('error', 'Please log in.');
        }
        return $next($request);
    }
}

// ── Auth ──────────────────────────────────────
class AdminAuthController extends Controller {
    public function showLogin()   { return view('admin.auth.login'); }
    public function login(Request $r) {
        $r->validate(['phone'=>'required','password'=>'required']);
        if (Auth::attempt(['phone'=>$r->phone,'password'=>$r->password,'is_active'=>true])) {
            $user = Auth::user();
            if (!in_array($user->role, ['municipality_admin','ward_admin'])) {
                Auth::logout();
                return back()->with('error','Access denied.');
            }
            return redirect()->route('admin.dashboard');
        }
        return back()->with('error','Invalid credentials.')->withInput();
    }
    public function logout(Request $r) {
        Auth::logout(); $r->session()->invalidate();
        return redirect()->route('admin.login');
    }
}

// ── Dashboard ────────────────────────────────
class AdminDashboardController extends Controller {

    private function globalCounts(): array {
        return [
            'pendingServices' => ServiceRequest::where('status','pending')->count(),
            'openComplaints'  => Complaint::whereIn('status',['submitted','under_review','in_progress'])->count(),
        ];
    }

    public function index() {
        $stats = [
            'users'            => User::where('role','citizen')->count(),
            'services_pending' => ServiceRequest::where('status','pending')->count(),
            'services_today'   => ServiceRequest::whereDate('created_at', today())->count(),
            'complaints_open'  => Complaint::whereIn('status',['submitted','under_review','in_progress'])->count(),
            'notices_active'   => Notice::where('is_active',true)->count(),
            'events_upcoming'  => MunicipalEvent::where('is_active',true)->where('starts_at','>=',now())->count(),
        ];

        $chartData   = $this->getChartData();
        $wardActivity= $this->getWardActivity();
        $recentComplaints = Complaint::with('user')->latest()->limit(5)->get();

        return view('admin.dashboard', array_merge(compact('stats','chartData','wardActivity','recentComplaints'), $this->globalCounts()));
    }

    private function getChartData(): array {
        $servicesChart = ServiceRequest::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at','>=',now()->subDays(6))->groupBy('date')->orderBy('date')->get();
        $complaintsChart = Complaint::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at','>=',now()->subDays(6))->groupBy('date')->orderBy('date')->get();
        $serviceTypes = ServiceRequest::selectRaw('service_type, count(*) as total')->groupBy('service_type')->get();
        return compact('servicesChart','complaintsChart','serviceTypes');
    }

    private function getWardActivity(): array {
        return collect(range(1,7))->map(fn($w) => [
            'ward_no'          => $w,
            'ward_name'        => Ward::where('ward_no',$w)->value('ward_name') ?? 'Ward '.$w,
            'citizens'         => User::where('ward_no',$w)->count(),
            'services_pending' => ServiceRequest::where('ward_no',$w)->where('status','pending')->count(),
            'complaints_open'  => Complaint::where('ward_no',$w)->whereIn('status',['submitted','under_review','in_progress'])->count(),
        ])->toArray();
    }
}

// ── Services ─────────────────────────────────
class AdminServiceController extends Controller {
    public function index(Request $r) {
        $requests = ServiceRequest::with(['user','assignee'])
            ->when($r->status,       fn($q) => $q->where('status',$r->status))
            ->when($r->service_type, fn($q) => $q->where('service_type',$r->service_type))
            ->when($r->ward_no,      fn($q) => $q->where('ward_no',$r->ward_no))
            ->latest()->paginate(20);
        $pendingServices = ServiceRequest::where('status','pending')->count();
        $openComplaints  = Complaint::whereIn('status',['submitted','under_review','in_progress'])->count();
        return view('admin.services.index', compact('requests','pendingServices','openComplaints'));
    }

    public function show($id) {
        $request = ServiceRequest::with(['user','documents','assignee'])->findOrFail($id);
        $staff   = User::whereIn('role',['ward_admin','municipality_admin'])->get();
        return view('admin.services.show', compact('request','staff'));
    }

    public function updateStatus(Request $r, $id) {
        $r->validate(['status'=>'required|in:pending,processing,approved,rejected,completed']);
        ServiceRequest::findOrFail($id)->update([
            'status'      => $r->status,
            'remarks'     => $r->remarks,
            'resolved_at' => in_array($r->status,['approved','completed','rejected']) ? now() : null,
        ]);
        return back()->with('success','Status updated successfully.');
    }

    public function assign(Request $r, $id) {
        $r->validate(['assigned_to'=>'required|exists:users,id']);
        ServiceRequest::findOrFail($id)->update(['assigned_to'=>$r->assigned_to, 'status'=>'processing']);
        return back()->with('success','Request assigned.');
    }
}

// ── Complaints ───────────────────────────────
class AdminComplaintController extends Controller {
    public function index(Request $r) {
        $complaints = Complaint::with(['user','assignee','photos'])
            ->when($r->status,   fn($q) => $q->where('status',$r->status))
            ->when($r->category, fn($q) => $q->where('category',$r->category))
            ->when($r->ward_no,  fn($q) => $q->where('ward_no',$r->ward_no))
            ->latest()->paginate(20);
        $pendingServices = ServiceRequest::where('status','pending')->count();
        $openComplaints  = Complaint::whereIn('status',['submitted','under_review','in_progress'])->count();
        return view('admin.complaints.index', compact('complaints','pendingServices','openComplaints'));
    }

    public function show($id) {
        $complaint = Complaint::with(['user','photos','assignee'])->findOrFail($id);
        $staff     = User::whereIn('role',['ward_admin','municipality_admin'])->get();
        return view('admin.complaints.show', compact('complaint','staff'));
    }

    public function updateStatus(Request $r, $id) {
        $r->validate(['status'=>'required|in:submitted,under_review,in_progress,resolved,closed']);
        Complaint::findOrFail($id)->update([
            'status'          => $r->status,
            'resolution_note' => $r->resolution_note,
            'resolved_at'     => in_array($r->status,['resolved','closed']) ? now() : null,
        ]);
        return back()->with('success','Complaint updated.');
    }

    public function assign(Request $r, $id) {
        $r->validate(['assigned_to'=>'required|exists:users,id']);
        Complaint::findOrFail($id)->update(['assigned_to'=>$r->assigned_to, 'status'=>'under_review']);
        return back()->with('success','Complaint assigned.');
    }
}

// ── Notices ──────────────────────────────────
class AdminNoticeController extends Controller {
    public function index()   { return view('admin.notices.index', ['notices' => Notice::with('creator')->latest()->paginate(20)]); }
    public function create()  { return view('admin.notices.form'); }
    public function edit($id) { return view('admin.notices.form', ['notice' => Notice::findOrFail($id)]); }
    public function store(Request $r) {
        $data = $r->validate(['title'=>'required','body'=>'required','type'=>'required','ward_no'=>'nullable|integer','effective_date'=>'nullable|date','expiry_date'=>'nullable|date']);
        $data['created_by'] = auth()->id();
        if ($r->hasFile('attachment')) $data['attachment'] = $r->file('attachment')->store('notices','public');
        Notice::create($data);
        return redirect()->route('admin.notices.index')->with('success','Notice published.');
    }
    public function update(Request $r, $id) {
        $notice = Notice::findOrFail($id);
        $notice->update($r->except(['_token','_method','attachment']));
        if ($r->hasFile('attachment')) {
            if ($notice->attachment) Storage::delete($notice->attachment);
            $notice->update(['attachment' => $r->file('attachment')->store('notices','public')]);
        }
        return redirect()->route('admin.notices.index')->with('success','Notice updated.');
    }
    public function destroy($id) { Notice::findOrFail($id)->delete(); return back()->with('success','Deleted.'); }
}

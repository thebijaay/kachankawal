<?php
namespace App\Http\Controllers\Api;

// ─────────────────────────────────────────────
//  Kachankawal Rural Municipality – API Controllers
// ─────────────────────────────────────────────

use App\Http\Controllers\Controller;
use App\Models\{User,OtpToken,Ward,Representative,Notice,ServiceRequest,ServiceDocument,Complaint,ComplaintPhoto,MunicipalEvent,Feedback,NotificationLog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash,Storage,DB};
use Tymon\JWTAuth\Facades\JWTAuth;

// ══════════════════════════════════════════════
//  AUTH CONTROLLER
// ══════════════════════════════════════════════
class AuthController extends Controller {

    // POST /api/v1/auth/send-otp
    public function sendOtp(Request $r) {
        $r->validate(['phone' => 'required|digits_between:10,15']);

        $otp = rand(100000, 999999);

        OtpToken::where('phone', $r->phone)->delete();
        OtpToken::create([
            'phone'      => $r->phone,
            'otp'        => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        // TODO: Integrate with local Nepali SMS provider (Sparrow SMS / Aakash SMS)
        // SmsService::send($r->phone, "Your OTP: {$otp}. Valid for 5 minutes.");

        // For development: return OTP in response (remove in production)
        return response()->json([
            'message' => 'OTP sent successfully',
            'debug_otp' => app()->isLocal() ? $otp : null,
        ]);
    }

    // POST /api/v1/auth/verify-otp
    public function verifyOtp(Request $r) {
        $r->validate([
            'phone' => 'required|digits_between:10,15',
            'otp'   => 'required|digits:6',
        ]);

        $record = OtpToken::where('phone', $r->phone)->where('used', false)->latest()->first();
        if (!$record || $record->isExpired() || !Hash::check($r->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        $record->update(['used' => true]);

        $user = User::where('phone', $r->phone)->first();
        if (!$user) {
            return response()->json(['message' => 'Phone not registered', 'needs_registration' => true], 200);
        }

        $user->update(['is_verified' => true]);
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->userResource($user),
        ]);
    }

    // POST /api/v1/auth/register
    public function register(Request $r) {
        $r->validate([
            'name'           => 'required|string|max:100',
            'phone'          => 'required|digits_between:10,15|unique:users,phone',
            'email'          => 'nullable|email|unique:users,email',
            'ward_no'        => 'required|integer|between:1,7',
            'citizenship_no' => 'nullable|string|max:50',
        ]);

        // Verify OTP was completed for this phone
        $otpUsed = OtpToken::where('phone', $r->phone)->where('used', true)
                    ->where('updated_at', '>=', now()->subMinutes(30))->exists();
        if (!$otpUsed) {
            return response()->json(['message' => 'Phone not verified'], 422);
        }

        $user = User::create([
            'name'           => $r->name,
            'phone'          => $r->phone,
            'email'          => $r->email,
            'ward_no'        => $r->ward_no,
            'citizenship_no' => $r->citizenship_no,
            'role'           => 'citizen',
            'is_verified'    => true,
        ]);

        $token = JWTAuth::fromUser($user);
        return response()->json(['message' => 'Registered successfully', 'token' => $token, 'user' => $this->userResource($user)], 201);
    }

    // POST /api/v1/auth/logout
    public function logout() {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logged out']);
    }

    // GET /api/v1/auth/me
    public function me() {
        return response()->json(['user' => $this->userResource(auth()->user())]);
    }

    // POST /api/v1/auth/refresh
    public function refresh() {
        $token = JWTAuth::refresh(JWTAuth::getToken());
        return response()->json(['token' => $token]);
    }

    private function userResource(User $u): array {
        return [
            'id'            => $u->id,
            'name'          => $u->name,
            'phone'         => $u->phone,
            'email'         => $u->email,
            'ward_no'       => $u->ward_no,
            'role'          => $u->role,
            'is_verified'   => $u->is_verified,
            'profile_photo' => $u->profile_photo ? asset('storage/'.$u->profile_photo) : null,
        ];
    }
}

// ══════════════════════════════════════════════
//  USER CONTROLLER
// ══════════════════════════════════════════════
class UserController extends Controller {

    public function profile() {
        return response()->json(['user' => auth()->user()->load('ward')]);
    }

    public function updateProfile(Request $r) {
        $user = auth()->user();
        $r->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'nullable|email|unique:users,email,'.$user->id,
            'ward_no'        => 'required|integer|between:1,7',
            'citizenship_no' => 'nullable|string|max:50',
        ]);
        $user->update($r->only('name','email','ward_no','citizenship_no'));
        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    public function updatePhoto(Request $r) {
        $r->validate(['photo' => 'required|image|max:2048']);
        $user = auth()->user();
        if ($user->profile_photo) Storage::delete($user->profile_photo);
        $path = $r->file('photo')->store('profile_photos', 'public');
        $user->update(['profile_photo' => $path]);
        return response()->json(['photo_url' => asset('storage/'.$path)]);
    }

    public function updateDeviceToken(Request $r) {
        $r->validate(['device_token' => 'required|string']);
        auth()->user()->update(['device_token' => $r->device_token]);
        return response()->json(['message' => 'Device token updated']);
    }

    // Admin methods
    public function adminIndex(Request $r) {
        $users = User::query()
            ->when($r->ward_no,  fn($q) => $q->where('ward_no', $r->ward_no))
            ->when($r->role,     fn($q) => $q->where('role', $r->role))
            ->when($r->search,   fn($q) => $q->where(fn($w) =>
                $w->where('name','like',"%{$r->search}%")->orWhere('phone','like',"%{$r->search}%")
            ))
            ->latest()->paginate(20);
        return response()->json($users);
    }

    public function adminShow($id)     { return response()->json(User::findOrFail($id)); }
    public function toggleStatus($id)  {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['is_active' => $user->is_active]);
    }
}

// ══════════════════════════════════════════════
//  WARD CONTROLLER
// ══════════════════════════════════════════════
class WardController extends Controller {

    public function index() {
        return response()->json(Ward::where('is_active', true)->orderBy('ward_no')->get());
    }

    public function show($wardNo) {
        $ward = Ward::where('ward_no', $wardNo)->with([
            'representatives' => fn($q) => $q->where('is_active', true),
        ])->firstOrFail();
        return response()->json($ward);
    }

    public function update(Request $r, $wardNo) {
        $ward = Ward::where('ward_no', $wardNo)->firstOrFail();
        $ward->update($r->only('ward_name','secretary_name','secretary_phone','secretary_email','address'));
        return response()->json($ward);
    }
}

// ══════════════════════════════════════════════
//  REPRESENTATIVE CONTROLLER
// ══════════════════════════════════════════════
class RepresentativeController extends Controller {

    public function index(Request $r) {
        $reps = Representative::query()
            ->when($r->ward_no, fn($q) => $q->where('ward_no', $r->ward_no))
            ->where('is_active', true)->get();
        return response()->json($reps);
    }

    public function store(Request $r) {
        $r->validate(['name'=>'required','position'=>'required','ward_no'=>'nullable|integer|between:1,7']);
        $rep = Representative::create($r->all());
        return response()->json($rep, 201);
    }

    public function update(Request $r, $id) {
        $rep = Representative::findOrFail($id);
        $rep->update($r->all());
        return response()->json($rep);
    }

    public function destroy($id) {
        Representative::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

// ══════════════════════════════════════════════
//  NOTICE CONTROLLER
// ══════════════════════════════════════════════
class NoticeController extends Controller {

    public function index(Request $r) {
        $notices = Notice::active()
            ->when($r->type,    fn($q) => $q->ofType($r->type))
            ->when($r->ward_no, fn($q) => $q->forWard($r->ward_no))
            ->latest()->paginate(15);
        return response()->json($notices);
    }

    public function show($id) {
        return response()->json(Notice::active()->findOrFail($id));
    }

    public function wardNotices() {
        $wardNo  = auth()->user()->ward_no;
        $notices = Notice::active()->forWard($wardNo)->latest()->paginate(15);
        return response()->json($notices);
    }

    public function store(Request $r) {
        $r->validate(['title'=>'required','body'=>'required','type'=>'required']);
        $data = $r->all();
        $data['created_by'] = auth()->id();
        if ($r->hasFile('attachment')) {
            $data['attachment'] = $r->file('attachment')->store('notices', 'public');
        }
        $notice = Notice::create($data);
        return response()->json($notice, 201);
    }

    public function update(Request $r, $id) {
        $notice = Notice::findOrFail($id);
        $notice->update($r->except('attachment'));
        if ($r->hasFile('attachment')) {
            if ($notice->attachment) Storage::delete($notice->attachment);
            $notice->update(['attachment' => $r->file('attachment')->store('notices', 'public')]);
        }
        return response()->json($notice);
    }

    public function destroy($id) {
        Notice::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

// ══════════════════════════════════════════════
//  SERVICE REQUEST CONTROLLER
// ══════════════════════════════════════════════
class ServiceRequestController extends Controller {

    public function index() {
        $requests = ServiceRequest::forUser(auth()->id())->with('documents')->latest()->paginate(10);
        return response()->json($requests);
    }

    public function show($trackingNo) {
        $req = ServiceRequest::where('tracking_no', $trackingNo)
            ->where('user_id', auth()->id())
            ->with('documents')
            ->firstOrFail();
        return response()->json($req);
    }

    public function store(Request $r) {
        $r->validate([
            'service_type' => 'required|string',
            'ward_no'      => 'required|integer|between:1,7',
            'form_data'    => 'required|array',
        ]);

        $req = ServiceRequest::create([
            'user_id'      => auth()->id(),
            'service_type' => $r->service_type,
            'ward_no'      => $r->ward_no,
            'form_data'    => $r->form_data,
            'status'       => 'pending',
        ]);

        return response()->json(['message' => 'Service request submitted', 'tracking_no' => $req->tracking_no, 'request' => $req], 201);
    }

    public function uploadDocument(Request $r, $id) {
        $r->validate([
            'document_type' => 'required|string',
            'file'          => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ]);

        $req  = ServiceRequest::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $path = $r->file('file')->store('service_docs/'.$req->id, 'public');

        $doc = ServiceDocument::create([
            'service_request_id' => $req->id,
            'document_type'      => $r->document_type,
            'file_path'          => $path,
            'original_name'      => $r->file('file')->getClientOriginalName(),
            'file_size'          => $r->file('file')->getSize(),
        ]);

        return response()->json($doc, 201);
    }

    // ── Admin ──
    public function adminIndex(Request $r) {
        $reqs = ServiceRequest::query()
            ->when($r->status,       fn($q) => $q->byStatus($r->status))
            ->when($r->service_type, fn($q) => $q->where('service_type', $r->service_type))
            ->when($r->ward_no,      fn($q) => $q->byWard($r->ward_no))
            ->with(['user','assignee'])->latest()->paginate(20);
        return response()->json($reqs);
    }

    public function adminShow($id) {
        return response()->json(ServiceRequest::with(['user','documents','assignee'])->findOrFail($id));
    }

    public function updateStatus(Request $r, $id) {
        $r->validate(['status'=>'required|in:pending,processing,approved,rejected,completed','remarks'=>'nullable|string']);
        $req = ServiceRequest::findOrFail($id);
        $req->update([
            'status'      => $r->status,
            'remarks'     => $r->remarks,
            'resolved_at' => in_array($r->status, ['approved','completed','rejected']) ? now() : null,
        ]);
        return response()->json($req);
    }

    public function assign(Request $r, $id) {
        $r->validate(['assigned_to'=>'required|exists:users,id']);
        ServiceRequest::findOrFail($id)->update(['assigned_to'=>$r->assigned_to]);
        return response()->json(['message'=>'Assigned']);
    }
}

// ══════════════════════════════════════════════
//  COMPLAINT CONTROLLER
// ══════════════════════════════════════════════
class ComplaintController extends Controller {

    public function index() {
        $complaints = Complaint::where('user_id', auth()->id())->with('photos')->latest()->paginate(10);
        return response()->json($complaints);
    }

    public function show($trackingNo) {
        $complaint = Complaint::where('tracking_no', $trackingNo)
            ->where('user_id', auth()->id())
            ->with('photos')
            ->firstOrFail();
        return response()->json($complaint);
    }

    public function store(Request $r) {
        $r->validate([
            'category'             => 'required|string',
            'title'                => 'required|string|max:200',
            'description'          => 'required|string',
            'ward_no'              => 'required|integer|between:1,7',
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'location_description' => 'nullable|string|max:200',
        ]);

        $complaint = Complaint::create([
            'user_id'              => auth()->id(),
            'category'             => $r->category,
            'title'                => $r->title,
            'description'          => $r->description,
            'ward_no'              => $r->ward_no,
            'latitude'             => $r->latitude,
            'longitude'            => $r->longitude,
            'location_description' => $r->location_description,
            'status'               => 'submitted',
        ]);

        return response()->json(['message' => 'Complaint submitted', 'tracking_no' => $complaint->tracking_no, 'complaint' => $complaint], 201);
    }

    public function uploadPhoto(Request $r, $id) {
        $r->validate(['photos' => 'required|array|max:5', 'photos.*' => 'image|max:3072']);

        $complaint = Complaint::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $saved = [];

        foreach ($r->file('photos') as $photo) {
            $path   = $photo->store('complaints/'.$complaint->id, 'public');
            $saved[] = ComplaintPhoto::create(['complaint_id' => $complaint->id, 'photo_path' => $path]);
        }

        return response()->json($saved, 201);
    }

    // ── Admin ──
    public function adminIndex(Request $r) {
        $complaints = Complaint::query()
            ->when($r->status,   fn($q) => $q->byStatus($r->status))
            ->when($r->category, fn($q) => $q->byCategory($r->category))
            ->when($r->ward_no,  fn($q) => $q->byWard($r->ward_no))
            ->with(['user','assignee','photos'])->latest()->paginate(20);
        return response()->json($complaints);
    }

    public function adminShow($id) {
        return response()->json(Complaint::with(['user','photos','assignee'])->findOrFail($id));
    }

    public function updateStatus(Request $r, $id) {
        $r->validate(['status'=>'required|in:submitted,under_review,in_progress,resolved,closed','resolution_note'=>'nullable|string']);
        $c = Complaint::findOrFail($id);
        $c->update([
            'status'          => $r->status,
            'resolution_note' => $r->resolution_note,
            'resolved_at'     => in_array($r->status, ['resolved','closed']) ? now() : null,
        ]);
        return response()->json($c);
    }

    public function assign(Request $r, $id) {
        $r->validate(['assigned_to'=>'required|exists:users,id']);
        Complaint::findOrFail($id)->update(['assigned_to'=>$r->assigned_to]);
        return response()->json(['message'=>'Assigned']);
    }
}

// ══════════════════════════════════════════════
//  EVENT CONTROLLER
// ══════════════════════════════════════════════
class EventController extends Controller {

    public function index(Request $r) {
        $events = MunicipalEvent::active()->upcoming()
            ->when($r->ward_no, fn($q) => $q->where(fn($w) => $w->whereNull('ward_no')->orWhere('ward_no', $r->ward_no)))
            ->paginate(15);
        return response()->json($events);
    }

    public function show($id) {
        return response()->json(MunicipalEvent::active()->findOrFail($id));
    }

    public function wardEvents() {
        $wardNo = auth()->user()->ward_no;
        $events = MunicipalEvent::active()->upcoming()
            ->where(fn($q) => $q->whereNull('ward_no')->orWhere('ward_no', $wardNo))
            ->paginate(15);
        return response()->json($events);
    }

    public function store(Request $r) {
        $r->validate(['title'=>'required','starts_at'=>'required|date']);
        $event = MunicipalEvent::create(array_merge($r->all(), ['created_by'=>auth()->id()]));
        return response()->json($event, 201);
    }

    public function update(Request $r, $id) {
        $event = MunicipalEvent::findOrFail($id);
        $event->update($r->all());
        return response()->json($event);
    }

    public function destroy($id) {
        MunicipalEvent::findOrFail($id)->delete();
        return response()->json(['message'=>'Deleted']);
    }
}

// ══════════════════════════════════════════════
//  NOTIFICATION CONTROLLER
// ══════════════════════════════════════════════
class NotificationController extends Controller {

    public function send(Request $r) {
        $r->validate(['title'=>'required','body'=>'required','ward_nos'=>'nullable|array']);

        $query = User::where('is_active', true)->whereNotNull('device_token');
        if ($r->ward_nos) $query->whereIn('ward_no', $r->ward_nos);

        $tokens = $query->pluck('device_token')->toArray();

        // TODO: Send via FCM (Firebase Cloud Messaging)
        // FcmService::sendMulticast($tokens, $r->title, $r->body);

        NotificationLog::create([
            'title'        => $r->title,
            'body'         => $r->body,
            'target_wards' => $r->ward_nos,
            'sent_count'   => count($tokens),
            'sent_by'      => auth()->id(),
        ]);

        return response()->json(['message' => 'Notification sent', 'sent_to' => count($tokens)]);
    }

    public function logs() {
        return response()->json(NotificationLog::with('sender')->latest()->paginate(20));
    }
}

// ══════════════════════════════════════════════
//  FEEDBACK CONTROLLER
// ══════════════════════════════════════════════
class FeedbackController extends Controller {

    public function store(Request $r) {
        $r->validate(['rating'=>'required|integer|between:1,5','message'=>'nullable|string','category'=>'nullable|string']);
        $feedback = Feedback::create(array_merge($r->all(), ['user_id'=>auth()->id()]));
        return response()->json($feedback, 201);
    }

    public function index() {
        return response()->json(Feedback::with('user')->latest()->paginate(20));
    }
}

// ══════════════════════════════════════════════
//  DASHBOARD CONTROLLER
// ══════════════════════════════════════════════
class DashboardController extends Controller {

    public function stats() {
        return response()->json([
            'users'              => User::where('role','citizen')->count(),
            'services_pending'   => ServiceRequest::where('status','pending')->count(),
            'services_today'     => ServiceRequest::whereDate('created_at', today())->count(),
            'complaints_open'    => Complaint::whereIn('status',['submitted','under_review','in_progress'])->count(),
            'complaints_today'   => Complaint::whereDate('created_at', today())->count(),
            'notices_active'     => Notice::active()->count(),
            'events_upcoming'    => MunicipalEvent::active()->upcoming()->count(),
        ]);
    }

    public function charts() {
        // Last 7 days service requests
        $servicesChart = ServiceRequest::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays(6))
            ->groupBy('date')->orderBy('date')->get();

        // Last 7 days complaints
        $complaintsChart = Complaint::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays(6))
            ->groupBy('date')->orderBy('date')->get();

        // Service type breakdown
        $serviceTypes = ServiceRequest::select('service_type', DB::raw('count(*) as total'))
            ->groupBy('service_type')->get();

        // Complaint categories
        $complaintCats = Complaint::select('category', DB::raw('count(*) as total'))
            ->groupBy('category')->get();

        return response()->json(compact('servicesChart','complaintsChart','serviceTypes','complaintCats'));
    }

    public function wardActivity() {
        $wards = Ward::withCount([
            'users',
            'notices',
        ])->orderBy('ward_no')->get();

        $wardStats = $wards->map(fn($w) => [
            'ward_no'           => $w->ward_no,
            'ward_name'         => $w->ward_name,
            'citizens'          => $w->users_count,
            'notices'           => $w->notices_count,
            'services_pending'  => ServiceRequest::byWard($w->ward_no)->byStatus('pending')->count(),
            'complaints_open'   => Complaint::byWard($w->ward_no)->whereIn('status',['submitted','under_review','in_progress'])->count(),
        ]);

        return response()->json($wardStats);
    }

    public function serviceSummary() {
        $summary = ServiceRequest::select('service_type','status', DB::raw('count(*) as total'))
            ->groupBy('service_type','status')->get()
            ->groupBy('service_type');
        return response()->json($summary);
    }
}
